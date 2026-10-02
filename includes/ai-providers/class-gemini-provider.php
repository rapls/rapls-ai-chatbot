<?php
/**
 * Gemini AI Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Gemini_Provider implements RAPLSAICH_AI_Provider_Interface {

    /**
     * @var string API Key
     */
    private string $api_key = '';

    /**
     * @var string Model name
     */
    private string $model = 'gemini-3.5-flash-lite';

    /**
     * @var string API URL
     */
    private string $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Set API Key
     */
    public function set_api_key(string $key): void {
        $this->api_key = $key;
    }

    /**
     * Set Model
     *
     * A model Google has shut down is swapped for its replacement here, the
     * one place every Gemini request passes through. The saved setting is left
     * as it is; the settings screen and an admin notice say which model is
     * actually answering.
     */
    public function set_model(string $model): void {
        $resolved = self::resolve_model($model);
        if ($resolved !== $model) {
            raplsaich_rate_limited_log(
                'gemini_retired_model_' . md5($model),
                sprintf('RAPLSAICH Gemini: model %s has been shut down by Google; sending %s instead.', $model, $resolved)
            );
        }
        $this->model = $resolved;
    }

    /**
     * Gemini models that are shut down or scheduled to be, with the model to
     * send instead and the shutdown date. Source: the Gemini API deprecations
     * page; replacements are the ones it recommends. A row whose date is still
     * ahead is ignored until that day (see raplsaich_retirement_in_effect()).
     *
     * @return array<string, array{to: string, retired: string}>
     */
    public static function retired_models(): array {
        return [
            'gemini-2.0-flash'              => ['to' => 'gemini-3.6-flash', 'retired' => '2026-06-01'],
            'gemini-2.0-flash-001'          => ['to' => 'gemini-3.6-flash', 'retired' => '2026-06-01'],
            'gemini-2.0-flash-lite'         => ['to' => 'gemini-3.1-flash-lite', 'retired' => '2026-06-01'],
            'gemini-2.0-flash-lite-001'     => ['to' => 'gemini-3.1-flash-lite', 'retired' => '2026-06-01'],
            'gemini-3-pro-preview'          => ['to' => 'gemini-3.1-pro-preview', 'retired' => '2026-03-09'],
            'gemini-3.1-flash-lite-preview' => ['to' => 'gemini-3.1-flash-lite', 'retired' => '2026-05-25'],
        ];
    }

    /**
     * Shutdown details for a model, or null if it is still served.
     *
     * Every Gemini 1.x and 2.0 model is shut down, so an ID not in the table
     * but from those generations (a typed per-bot model, a dated variant)
     * still gets a replacement rather than failing.
     *
     * @return array{to: string, retired: string}|null
     */
    public static function retirement(string $model): ?array {
        /**
         * Filter the shut-down Gemini models and their replacements.
         *
         * @param array $retired Model ID => ['to' => replacement ID, 'retired' => 'Y-m-d'].
         */
        $retired = (array) apply_filters('raplsaich_gemini_retired_models', self::retired_models());
        if (isset($retired[$model]['to'])) {
            return raplsaich_retirement_in_effect((string) ($retired[$model]['retired'] ?? '')) ? $retired[$model] : null;
        }
        if (preg_match('/^gemini-(?:1\.|2\.0-)/', $model)) {
            return [
                'to'      => strpos($model, '-lite') !== false ? 'gemini-3.1-flash-lite' : 'gemini-3.6-flash',
                'retired' => '',
            ];
        }
        return null;
    }

    /**
     * Details of an announced retirement that has not happened yet, or null.
     * The model still answers until the date; the admin is warned meanwhile.
     *
     * @return array{to: string, retired: string}|null
     */
    public static function scheduled_retirement(string $model): ?array {
        /** This filter is documented in retirement(). */
        $retired = (array) apply_filters('raplsaich_gemini_retired_models', self::retired_models());
        return raplsaich_scheduled_retirement($retired, $model);
    }

    /**
     * The model to actually send: the replacement if $model is shut down.
     */
    public static function resolve_model(string $model): string {
        $info = self::retirement($model);
        return $info ? $info['to'] : $model;
    }

    /**
     * Gemini 3 and later think by default, and thought tokens count toward
     * maxOutputTokens; Google also advises leaving temperature at its default
     * on them (below 1.0 can cause looping).
     */
    private function is_thinking_generation(): bool {
        return (bool) preg_match('/^gemini-(?:[3-9]|\d{2,})(?:[.\-]|$)/', $this->model);
    }

    /**
     * Send message
     */
    public function send_message(array $messages, array $options = []): array {
        if (empty($this->api_key)) {
            throw new Exception(esc_html__('Gemini API key is not configured.', 'rapls-ai-chatbot'));
        }

        // Separate system message and convert to system_instruction
        $system_instruction = '';
        $contents = [];

        $image_data = $options['image'] ?? '';
        $file_data = $options['file'] ?? '';
        $file_name = $options['file_name'] ?? '';

        // Find last user message index for image/file injection
        $last_user_idx = -1;
        if (!empty($image_data) || !empty($file_data)) {
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if ($messages[$i]['role'] === 'user') {
                    $last_user_idx = $i;
                    break;
                }
            }
        }

        foreach ($messages as $idx => $msg) {
            if ($msg['role'] === 'system') {
                $system_instruction .= $msg['content'] . "\n";
            } else {
                // Convert to Gemini role format (user/model)
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $parts = [['text' => $msg['content']]];

                if ($idx === $last_user_idx) {
                    // Inject image into the last user message for vision
                    if (!empty($image_data)) {
                        $mime = 'image/jpeg';
                        $b64 = $image_data;
                        if (preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $image_data, $m)) {
                            $mime = $m[1];
                            $b64 = $m[2];
                        }
                        $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => $b64]];
                    }

                    // Inject file into the last user message
                    if (!empty($file_data)) {
                        $fmime = 'application/pdf';
                        $fb64 = $file_data;
                        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $file_data, $fm)) {
                            $fmime = $fm[1];
                            $fb64 = $fm[2];
                        }
                        if (!empty($file_name)) {
                            $parts[] = ['text' => sprintf('Uploaded file: %s', $file_name)];
                        }
                        $parts[] = ['inline_data' => ['mime_type' => $fmime, 'data' => $fb64]];
                    }
                }

                $contents[] = [
                    'role'  => $role,
                    'parts' => $parts,
                ];
            }
        }

        $max_tokens = (int) ($options['max_tokens'] ?? 1000);
        $generation_config = [
            'maxOutputTokens' => $max_tokens,
            'temperature'     => (float) ($options['temperature'] ?? 0.7),
        ];
        if ($this->is_thinking_generation()) {
            // Leave room for thinking so the reply is not cut off (same
            // multiplier as GPT-5), and keep Google's default temperature.
            $generation_config['maxOutputTokens'] = RAPLSAICH_OpenAI_Provider::get_gpt5_effective_tokens($max_tokens)['tokens'];
            unset($generation_config['temperature']);
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => $generation_config,
        ];

        // Add system prompt as system_instruction if exists
        if (!empty($system_instruction)) {
            $body['system_instruction'] = [
                'parts' => [
                    ['text' => trim($system_instruction)]
                ]
            ];
        }

        // Web search grounding tool
        $has_web_search = false;
        if (!empty($options['web_search'])) {
            $body['tools'] = [['google_search' => new \stdClass()]];
            $has_web_search = true;
        }

        // Pass the API key in the x-goog-api-key header (not the ?key= query string).
        // This is Google's recommended method, keeps the key out of URLs/logs, and is
        // required for the new "AQ." auth keys that AI Studio now issues (the legacy
        // "AIza" query-string style is being phased out — standard keys are rejected
        // from 2026-06-19 unless restricted, and fully from 2026-09).
        $url = $this->api_url . rawurlencode($this->model) . ':generateContent';

        // Pass base URL (without API key) to the filter to prevent key leakage
        $filter_url = $this->api_url . $this->model . ':generateContent';
        /** @see RAPLSAICH_OpenAI_Provider::send_http_request() for filter docs */
        $requested = (int) apply_filters('raplsaich_api_timeout', 120, $filter_url, $this->model);
        $max_exec  = (int) ini_get('max_execution_time');
        $upper     = ($max_exec > 0) ? min(300, max(10, $max_exec - 5)) : 300;
        $timeout   = max(10, min($upper, $requested));

        $request_args = [
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $this->api_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ];

        $response = wp_remote_post($url, $request_args);

        // If web search tool causes an error, retry without it
        if ($has_web_search && !is_wp_error($response)) {
            $resp_code = wp_remote_retrieve_response_code($response);
            if ($resp_code === 400) {
                unset($body['tools']);
                $request_args['body'] = wp_json_encode($body);
                $response = wp_remote_post($url, $request_args);
            }
        }

        if (is_wp_error($response)) {
            throw new RAPLSAICH_Communication_Exception(esc_html__('API communication error: ', 'rapls-ai-chatbot') . esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            if (!is_array($data)) {
                throw new Exception(esc_html__('Gemini API error: ', 'rapls-ai-chatbot') . esc_html(wp_remote_retrieve_response_message($response)), (int) $response_code);
            }
            $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
            $error_status = $data['error']['status'] ?? '';

            // Rate-limited: under API outages, every chat request triggers this.
            raplsaich_rate_limited_log(
                'gemini_api_error_' . $response_code,
                sprintf(
                    'RAPLSAICH Gemini API Error: HTTP %d | status=%s | model=%s | message=%s',
                    $response_code,
                    $error_status,
                    $this->model,
                    $error_message
                )
            );

            // Authentication errors
            if ($response_code === 401 || $response_code === 403) {
                throw new Exception(esc_html__('Gemini API key is invalid or does not have permission to use this model.', 'rapls-ai-chatbot'), (int) $response_code);
            }

            // Check for quota/billing errors
            if ($response_code === 429 || $response_code === 402 ||
                $error_status === 'RESOURCE_EXHAUSTED' ||
                stripos($error_message, 'quota') !== false ||
                stripos($error_message, 'billing') !== false ||
                stripos($error_message, 'exceeded') !== false ||
                stripos($error_message, 'exhausted') !== false) {
                $ex = new RAPLSAICH_Quota_Exceeded_Exception(esc_html($error_message));
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if (is_numeric($retry_after) && (int) $retry_after > 0) {
                    $ex->set_retry_after((int) $retry_after);
                }
                throw $ex;
            }

            // Invalid parameter errors
            if ($response_code === 400 || $error_status === 'INVALID_ARGUMENT') {
                throw new Exception(
                    /* translators: 1: model name, 2: error message */
                    sprintf(esc_html__('Gemini API parameter error (model: %1$s): %2$s. Please try selecting a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model), esc_html($error_message)),
                    400
                );
            }

            // Model not found
            if ($response_code === 404 || $error_status === 'NOT_FOUND') {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('Gemini model "%s" not found. It may have been deprecated or renamed. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model)),
                    404
                );
            }

            // Server errors
            if ($response_code >= 500) {
                throw new Exception(
                    /* translators: %d: HTTP status code */
                    sprintf(esc_html__('Gemini server error (HTTP %d). The service may be temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'), (int) $response_code),
                    (int) $response_code
                );
            }

            throw new Exception(esc_html__('Gemini API error: ', 'rapls-ai-chatbot') . esc_html($error_message), (int) $response_code);
        }

        // Extract content from response
        $content = '';
        if (isset($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }

        if ($content === '') {
            throw new Exception(esc_html__('Failed to get response from AI.', 'rapls-ai-chatbot'));
        }

        // Extract web search grounding sources
        $web_sources = [];
        $grounding = $data['candidates'][0]['groundingMetadata'] ?? null;
        if ($grounding && isset($grounding['groundingChunks']) && is_array($grounding['groundingChunks'])) {
            $seen = [];
            foreach ($grounding['groundingChunks'] as $chunk) {
                if (!isset($chunk['web'])) {
                    continue;
                }
                $url = $chunk['web']['uri'] ?? '';
                if (!empty($url) && !isset($seen[$url])) {
                    $seen[$url] = true;
                    $web_sources[] = [
                        'url'   => $url,
                        'title' => $chunk['web']['title'] ?? '',
                    ];
                }
            }
        }

        // Get token usage
        $input_tokens = 0;
        $output_tokens = 0;
        if (isset($data['usageMetadata'])) {
            $input_tokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
            $output_tokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
        }
        $tokens_used = $input_tokens + $output_tokens;

        $result = [
            'content'       => $content,
            'tokens_used'   => $tokens_used,
            'input_tokens'  => $input_tokens,
            'output_tokens' => $output_tokens,
            'model'         => $this->model,
            'provider'      => $this->get_name(),
        ];

        if (!empty($web_sources)) {
            $result['web_sources'] = $web_sources;
        }

        return $result;
    }

    /**
     * Available models
     */
    public function get_available_models(): array {
        return [
            // Gemini 3 series (current)
            'gemini-3.8-flash'        => 'Gemini 3.8 Flash (' . __('★ Recommended — fast and smart', 'rapls-ai-chatbot') . ')',
            'gemini-3.5-flash-lite'   => 'Gemini 3.5 Flash Lite (' . __('Fastest, cheapest', 'rapls-ai-chatbot') . ')',
            'gemini-3.1-pro-preview'  => 'Gemini 3.1 Pro (' . __('Preview, most capable', 'rapls-ai-chatbot') . ')',
            'gemini-3.7-flash'        => 'Gemini 3.7 Flash',
            'gemini-3.6-flash'        => 'Gemini 3.6 Flash',
            'gemini-3.5-flash'        => 'Gemini 3.5 Flash',
            'gemini-3.1-flash-lite'   => 'Gemini 3.1 Flash Lite (' . __('Stable, cheapest', 'rapls-ai-chatbot') . ')',
            'gemini-3-flash-preview'  => 'Gemini 3 Flash (' . __('Preview, fast', 'rapls-ai-chatbot') . ')',
            // Gemini 2.5 series (Google serves these only to projects that
            // already used them)
            'gemini-2.5-pro'          => 'Gemini 2.5 Pro',
            'gemini-2.5-flash'        => 'Gemini 2.5 Flash',
            'gemini-2.5-flash-lite'   => 'Gemini 2.5 Flash Lite',
            // Gemini 2.0 and 3 Pro Preview are shut down; set_model() sends
            // their replacements instead.
        ];
    }

    /**
     * Get vision-capable models (all Gemini models support vision)
     */
    public function get_vision_models(): array {
        return array_keys($this->get_available_models());
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        return (bool) preg_match('/^gemini-(?:[2-9]|\d{2,})(?:[.\-]|$)/', $this->model);
    }

    /**
     * Fetch models from API
     */
    public function fetch_models_from_api(): array {
        if (empty($this->api_key)) {
            return [];
        }

        $cache_key = 'raplsaich_models_gemini_v2_' . md5($this->api_key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['x-goog-api-key' => $this->api_key],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['models']) || !is_array($data['models'])) {
            return [];
        }

        $hardcoded = $this->get_available_models();

        // Build set of API model IDs
        $api_ids = [];
        foreach ($data['models'] as $model) {
            $name = $model['name'] ?? '';
            $api_ids[str_replace('models/', '', $name)] = true;
        }

        // Exclude patterns (gemini-1.5 series was discontinued by Google)
        $exclude_substrings = [
            '-exp', '-image', '-embedding', '-aqa', '-bisheng', 'gemini-1.5',
        ];

        $models = [];

        foreach ($data['models'] as $model) {
            $name = $model['name'] ?? '';
            $display_name = $model['displayName'] ?? '';
            $methods = $model['supportedGenerationMethods'] ?? [];

            // Only models that support generateContent and start with gemini
            if (!in_array('generateContent', $methods, true)) {
                continue;
            }

            // Remove models/ prefix
            $id = str_replace('models/', '', $name);

            if (strpos($id, 'gemini') !== 0) {
                continue;
            }

            // Exclude unwanted variants
            $excluded = false;
            foreach ($exclude_substrings as $pattern) {
                if (strpos($id, $pattern) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Skip dated variants if base model exists (e.g. gemini-2.0-flash-001)
            if (preg_match('/^(gemini-[\d.]+-(?:pro|flash|flash-lite)(?:-preview)?)-\d+$/', $id, $dm)) {
                $base = $dm[1];
                if (isset($api_ids[$base]) || isset($hardcoded[$base])) {
                    continue;
                }
            }

            if (isset($hardcoded[$id])) {
                $models[$id] = $hardcoded[$id];
            } else {
                $models[$id] = $display_name ?: $id;
            }
        }

        // Also include hardcoded models not returned by API
        foreach ($hardcoded as $id => $label) {
            if (!isset($models[$id])) {
                $models[$id] = $label;
            }
        }

        // Sort all models by version descending
        uksort($models, function ($a, $b) {
            preg_match('/gemini-(\d+(?:\.\d+)?)/', $a, $ma);
            preg_match('/gemini-(\d+(?:\.\d+)?)/', $b, $mb);
            $va = isset($ma[1]) ? (float) $ma[1] : 0.0;
            $vb = isset($mb[1]) ? (float) $mb[1] : 0.0;
            if ($va !== $vb) {
                return $vb <=> $va;
            }
            // Same version: pro before flash before lite
            $ta = $this->gemini_tier($a);
            $tb = $this->gemini_tier($b);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return strcmp($a, $b);
        });

        set_transient($cache_key, $models, DAY_IN_SECONDS);
        return $models;
    }

    /**
     * Get tier order for Gemini models: pro=0, flash=1, lite=2
     */
    private function gemini_tier(string $id): int {
        if (strpos($id, '-lite') !== false) {
            return 2;
        }
        if (strpos($id, '-flash') !== false) {
            return 1;
        }
        return 0; // pro or other
    }

    /**
     * Validate API Key
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key)) {
            return false;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models';
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['x-goog-api-key' => $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Provider name
     */
    public function get_name(): string {
        return 'gemini';
    }
}
