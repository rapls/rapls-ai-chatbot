<?php
/**
 * Gemini AI Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Gemini_Provider implements WPAIC_AI_Provider_Interface {

    /**
     * @var string API Key
     */
    private string $api_key = '';

    /**
     * @var string Model name
     */
    private string $model = 'gemini-2.0-flash-exp';

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
     */
    public function set_model(string $model): void {
        $this->model = $model;
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

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_instruction .= $msg['content'] . "\n";
            } else {
                // Convert to Gemini role format (user/model)
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role'  => $role,
                    'parts' => [
                        ['text' => $msg['content']]
                    ]
                ];
            }
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 1000,
                'temperature'     => $options['temperature'] ?? 0.7,
            ],
        ];

        // Add system prompt as system_instruction if exists
        if (!empty($system_instruction)) {
            $body['system_instruction'] = [
                'parts' => [
                    ['text' => trim($system_instruction)]
                ]
            ];
        }

        $url = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            throw new Exception(esc_html__('API communication error: ', 'rapls-ai-chatbot') . esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
            $error_status = $data['error']['status'] ?? '';

            // Log detailed error for debugging (only when WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'WPAIC Gemini API Error: HTTP %d | status=%s | model=%s | message=%s',
                    $response_code,
                    $error_status,
                    $this->model,
                    $error_message
                ));
            }

            // Authentication errors
            if ($response_code === 401 || $response_code === 403) {
                throw new Exception(esc_html__('Gemini API key is invalid or does not have permission to use this model.', 'rapls-ai-chatbot'));
            }

            // Check for quota/billing errors
            if ($response_code === 429 || $response_code === 402 ||
                $error_status === 'RESOURCE_EXHAUSTED' ||
                stripos($error_message, 'quota') !== false ||
                stripos($error_message, 'billing') !== false ||
                stripos($error_message, 'exceeded') !== false ||
                stripos($error_message, 'exhausted') !== false) {
                throw new WPAIC_Quota_Exceeded_Exception(esc_html($error_message));
            }

            // Invalid parameter errors
            if ($response_code === 400 || $error_status === 'INVALID_ARGUMENT') {
                throw new Exception(
                    /* translators: 1: model name, 2: error message */
                    sprintf(esc_html__('Gemini API parameter error (model: %1$s): %2$s. Please try selecting a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model), esc_html($error_message))
                );
            }

            // Model not found
            if ($response_code === 404 || $error_status === 'NOT_FOUND') {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('Gemini model "%s" not found. It may have been deprecated or renamed. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model))
                );
            }

            // Server errors
            if ($response_code >= 500) {
                throw new Exception(
                    /* translators: %d: HTTP status code */
                    sprintf(esc_html__('Gemini server error (HTTP %d). The service may be temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'), $response_code)
                );
            }

            throw new Exception(esc_html__('Gemini API error: ', 'rapls-ai-chatbot') . esc_html($error_message));
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

        // Get token usage
        $input_tokens = 0;
        $output_tokens = 0;
        if (isset($data['usageMetadata'])) {
            $input_tokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
            $output_tokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
        }
        $tokens_used = $input_tokens + $output_tokens;

        return [
            'content'       => $content,
            'tokens_used'   => $tokens_used,
            'input_tokens'  => $input_tokens,
            'output_tokens' => $output_tokens,
            'model'         => $this->model,
            'provider'      => $this->get_name(),
        ];
    }

    /**
     * Available models
     */
    public function get_available_models(): array {
        return [
            // Gemini 3 series (latest)
            'gemini-3-pro-preview'    => 'Gemini 3 Pro (' . __('Preview, most capable', 'rapls-ai-chatbot') . ')',
            'gemini-3-flash-preview'  => 'Gemini 3 Flash (' . __('Preview, fast', 'rapls-ai-chatbot') . ')',
            // Gemini 2.5 series
            'gemini-2.5-pro'          => 'Gemini 2.5 Pro (' . __('Powerful, reasoning', 'rapls-ai-chatbot') . ')',
            'gemini-2.5-flash'        => 'Gemini 2.5 Flash (' . __('★ Recommended — fast and smart', 'rapls-ai-chatbot') . ')',
            'gemini-2.5-flash-lite'   => 'Gemini 2.5 Flash Lite (' . __('Fastest, cheapest', 'rapls-ai-chatbot') . ')',
            // Gemini 2.0 series
            'gemini-2.0-flash'        => 'Gemini 2.0 Flash (' . __('★ Recommended — stable', 'rapls-ai-chatbot') . ')',
            // Gemini 1.5 series
            'gemini-1.5-pro'          => 'Gemini 1.5 Pro (' . __('Legacy, long context', 'rapls-ai-chatbot') . ')',
            'gemini-1.5-flash'        => 'Gemini 1.5 Flash (' . __('Legacy, fast', 'rapls-ai-chatbot') . ')',
            'gemini-1.5-flash-8b'     => 'Gemini 1.5 Flash 8B (' . __('Legacy, cheapest', 'rapls-ai-chatbot') . ')',
        ];
    }

    /**
     * Get vision-capable models (all Gemini models support vision)
     */
    public function get_vision_models(): array {
        return [
            'gemini-3-pro-preview',
            'gemini-3-flash-preview',
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b',
        ];
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        // All Gemini 1.5+ and 2.0 models support vision
        return strpos($this->model, 'gemini-1.5') !== false ||
               strpos($this->model, 'gemini-2') !== false;
    }

    /**
     * Fetch models from API
     */
    public function fetch_models_from_api(): array {
        if (empty($this->api_key)) {
            return [];
        }

        $cache_key = 'wpaic_models_gemini_v2_' . md5($this->api_key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->api_key;
        $response = wp_remote_get($url, [
            'timeout' => 15,
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

        // Exclude patterns
        $exclude_substrings = [
            '-exp', '-image', '-embedding', '-aqa', '-bisheng',
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

        $url = $this->api_url . 'gemini-1.5-flash:generateContent?key=' . $this->api_key;

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => 'Hi']]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 10,
                ],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }

    /**
     * Provider name
     */
    public function get_name(): string {
        return 'gemini';
    }
}
