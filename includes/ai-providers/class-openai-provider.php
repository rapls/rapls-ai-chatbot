<?php
/**
 * OpenAI API Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_OpenAI_Provider implements WPAIC_AI_Provider_Interface {

    /**
     * API Key
     */
    private string $api_key = '';

    /**
     * Model
     */
    private string $model = 'gpt-4o-mini';

    /**
     * API Endpoint
     */
    private string $api_url = 'https://api.openai.com/v1/chat/completions';

    /**
     * Reasoning models (no system role, temperature fixed at 1)
     * Matched by prefix: 'o1' matches o1, o1-mini, o1-preview, o1-pro
     */
    private array $reasoning_models = ['o1', 'o3', 'o4'];

    /**
     * Legacy reasoning models (no system/developer role, must use user role)
     */
    private array $legacy_reasoning_models = ['o1-mini', 'o1-preview'];

    /**
     * Models using max_completion_tokens instead of max_tokens
     * All models from GPT-4.1 onwards and reasoning models use this parameter
     */
    private array $new_api_models = ['gpt-5', 'gpt-4.1', 'o1', 'o3', 'o4'];

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
     * Check if reasoning model (includes legacy reasoning models)
     */
    private function is_reasoning_model(): bool {
        foreach ($this->reasoning_models as $rm) {
            if (strpos($this->model, $rm) === 0) {
                return true;
            }
        }
        foreach ($this->legacy_reasoning_models as $rm) {
            if (strpos($this->model, $rm) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if new API format model
     * (models using max_completion_tokens)
     */
    private function uses_new_api(): bool {
        foreach ($this->new_api_models as $nm) {
            if (strpos($this->model, $nm) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if GPT-5 series model
     * (temperature not supported)
     */
    private function is_gpt5_model(): bool {
        return strpos($this->model, 'gpt-5') === 0;
    }

    /**
     * Send message
     */
    public function send_message(array $messages, array $options = []): array {
        if (empty($this->api_key)) {
            throw new Exception(esc_html__('OpenAI API key is not configured.', 'rapls-ai-chatbot'));
        }

        $is_reasoning = $this->is_reasoning_model();

        // Convert messages for reasoning models
        if ($is_reasoning) {
            $messages = $this->convert_messages_for_reasoning($messages);
        }

        $body = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        // New models (GPT-5, GPT-4.1, o series) use max_completion_tokens
        $uses_new_api = $this->uses_new_api();

        $configured_max = $options['max_tokens'] ?? 4000;

        if ($uses_new_api) {
            if ($this->is_gpt5_model()) {
                // GPT-5 includes internal reasoning tokens in max_completion_tokens,
                // so multiply the configured output limit by 4 (reasoning overhead),
                // capped at 16384 to prevent cost spikes
                $body['max_completion_tokens'] = min($configured_max * 4, 16384);
            } else {
                $body['max_completion_tokens'] = $configured_max;
            }
        } else {
            $body['max_tokens'] = $options['max_tokens'] ?? 1000;
        }

        // Specify temperature except for GPT-5 series and reasoning models
        // GPT-5 and o series only support temperature=1
        if (!$is_reasoning && !$this->is_gpt5_model()) {
            $body['temperature'] = $options['temperature'] ?? 0.7;
        }

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 120, // Reasoning models may take longer
        ]);

        if (is_wp_error($response)) {
            throw new Exception(esc_html__('API communication error: ', 'rapls-ai-chatbot') . esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
            $error_type = $data['error']['type'] ?? '';
            $error_code = $data['error']['code'] ?? '';

            // Log detailed error for debugging (only when WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'WPAIC OpenAI API Error: HTTP %d | type=%s | code=%s | model=%s | message=%s',
                    $response_code,
                    $error_type,
                    $error_code,
                    $this->model,
                    $error_message
                ));
            }

            // Authentication errors (invalid or revoked API key)
            if ($response_code === 401 || $error_code === 'invalid_api_key') {
                throw new Exception(esc_html__('OpenAI API key is invalid or has been revoked.', 'rapls-ai-chatbot'));
            }

            // Quota/billing errors
            if ($response_code === 402 ||
                $error_code === 'insufficient_quota' ||
                $error_type === 'insufficient_quota' ||
                stripos($error_message, 'quota') !== false ||
                stripos($error_message, 'billing') !== false ||
                stripos($error_message, 'exceeded') !== false) {
                throw new WPAIC_Quota_Exceeded_Exception(esc_html($error_message));
            }

            // Rate limit errors (429 without quota keywords = rate limit, not billing)
            if ($response_code === 429) {
                throw new WPAIC_Quota_Exceeded_Exception(esc_html($error_message));
            }

            // Invalid parameter errors (model doesn't support a parameter)
            if ($response_code === 400 && (
                $error_code === 'invalid_request_error' ||
                stripos($error_message, 'not supported') !== false ||
                stripos($error_message, 'invalid') !== false
            )) {
                throw new Exception(
                    /* translators: 1: model name, 2: error message */
                    sprintf(esc_html__('OpenAI API parameter error (model: %1$s): %2$s. Please try selecting a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model), esc_html($error_message))
                );
            }

            // Model access denied (requires special permissions or paid tier)
            if ($response_code === 403) {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('Access denied for model "%s". Your API account may not have permission to use this model. Please check your OpenAI plan or select a different model.', 'rapls-ai-chatbot'), esc_html($this->model))
                );
            }

            // Model not found
            if ($response_code === 404 || $error_code === 'model_not_found') {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('OpenAI model "%s" not found. It may have been deprecated or renamed. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model))
                );
            }

            // Server errors
            if ($response_code >= 500) {
                throw new Exception(
                    /* translators: %d: HTTP status code */
                    sprintf(esc_html__('OpenAI server error (HTTP %d). The service may be temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'), $response_code)
                );
            }

            throw new Exception(esc_html__('OpenAI API error: ', 'rapls-ai-chatbot') . esc_html($error_message));
        }

        // Get content from response (support multiple formats)
        $content = '';
        $tokens_used = 0;

        // Standard format: choices[0].message.content
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
        }
        // Alternative format: output (some new models)
        elseif (isset($data['output'])) {
            if (is_array($data['output'])) {
                // output is array
                foreach ($data['output'] as $output) {
                    if (isset($output['content'])) {
                        if (is_array($output['content'])) {
                            foreach ($output['content'] as $part) {
                                if (isset($part['text'])) {
                                    $content .= $part['text'];
                                }
                            }
                        } else {
                            $content .= $output['content'];
                        }
                    }
                }
            } else {
                $content = $data['output'];
            }
        }
        // Alternative format: choices[0].text (legacy)
        elseif (isset($data['choices'][0]['text'])) {
            $content = $data['choices'][0]['text'];
        }

        // Get token usage
        $input_tokens = 0;
        $output_tokens = 0;

        if (isset($data['usage']['prompt_tokens'])) {
            $input_tokens = $data['usage']['prompt_tokens'];
        } elseif (isset($data['usage']['input_tokens'])) {
            $input_tokens = $data['usage']['input_tokens'];
        }

        if (isset($data['usage']['completion_tokens'])) {
            $output_tokens = $data['usage']['completion_tokens'];
        } elseif (isset($data['usage']['output_tokens'])) {
            $output_tokens = $data['usage']['output_tokens'];
        }

        $tokens_used = $input_tokens + $output_tokens;
        if ($tokens_used === 0 && isset($data['usage']['total_tokens'])) {
            $tokens_used = $data['usage']['total_tokens'];
        }

        // Error if content is empty
        if (empty($content)) {
            throw new Exception(esc_html__('Failed to get response from AI.', 'rapls-ai-chatbot'));
        }

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
     * Convert messages for reasoning models
     * - Convert system messages to developer or user messages
     */
    private function convert_messages_for_reasoning(array $messages): array {
        $converted = [];

        // Legacy reasoning models (o1-mini, o1-preview) don't support developer role
        $is_legacy = false;
        foreach ($this->legacy_reasoning_models as $rm) {
            if (strpos($this->model, $rm) === 0) {
                $is_legacy = true;
                break;
            }
        }
        $supports_developer = !$is_legacy;

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                if ($supports_developer) {
                    $converted[] = [
                        'role'    => 'developer',
                        'content' => $message['content'],
                    ];
                } else {
                    // o1-mini, o1-preview don't support system/developer, add to user message
                    $converted[] = [
                        'role'    => 'user',
                        'content' => "[System Instructions]\n" . $message['content'],
                    ];
                }
            } else {
                $converted[] = $message;
            }
        }

        return $converted;
    }

    /**
     * Available models — sorted to match WP AI Client order:
     * versioned GPT (desc) → non-versioned GPT → non-GPT reasoning
     */
    public function get_available_models(): array {
        return [
            // Versioned GPT — highest version first, base > -mini > -nano > others
            'gpt-5.2'       => 'GPT-5.2 (' . __('Most powerful', 'rapls-ai-chatbot') . ')',
            'gpt-5.2-pro'   => 'GPT-5.2 Pro (' . __('Complex problem solving', 'rapls-ai-chatbot') . ')',
            'gpt-5.1'       => 'GPT-5.1 (' . __('Powerful and stable', 'rapls-ai-chatbot') . ')',
            'gpt-5'         => 'GPT-5 (' . __('Coding and analysis', 'rapls-ai-chatbot') . ')',
            'gpt-5-mini'    => 'GPT-5 mini (' . __('Fast and affordable', 'rapls-ai-chatbot') . ')',
            'gpt-5-nano'    => 'GPT-5 nano (' . __('Fastest and cheapest', 'rapls-ai-chatbot') . ')',
            'gpt-5-pro'     => 'GPT-5 Pro (' . __('Deep analysis', 'rapls-ai-chatbot') . ')',
            'gpt-4.1'       => 'GPT-4.1 (' . __('Long context (1M tokens)', 'rapls-ai-chatbot') . ')',
            'gpt-4.1-mini'  => 'GPT-4.1 mini (' . __('Fast, long context', 'rapls-ai-chatbot') . ')',
            'gpt-4.1-nano'  => 'GPT-4.1 nano (' . __('Fastest, long context', 'rapls-ai-chatbot') . ')',
            'gpt-4'         => 'GPT-4 (' . __('Legacy', 'rapls-ai-chatbot') . ')',
            'gpt-4-turbo'   => 'GPT-4 Turbo (' . __('Legacy, vision', 'rapls-ai-chatbot') . ')',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (' . __('Legacy, cheapest', 'rapls-ai-chatbot') . ')',
            // Non-versioned GPT (gpt-4o — "4o" not purely numeric)
            'gpt-4o'        => 'GPT-4o (' . __('★ Recommended — multimodal', 'rapls-ai-chatbot') . ')',
            'gpt-4o-mini'   => 'GPT-4o mini (' . __('★ Recommended — affordable', 'rapls-ai-chatbot') . ')',
            // Reasoning models (non-GPT prefix)
            'o1'            => 'o1 (' . __('Reasoning model', 'rapls-ai-chatbot') . ')',
            'o1-pro'        => 'o1 Pro (' . __('Reasoning, highest accuracy', 'rapls-ai-chatbot') . ')',
            'o3'            => 'o3 (' . __('Advanced reasoning', 'rapls-ai-chatbot') . ')',
            'o3-mini'       => 'o3 mini (' . __('Reasoning, affordable', 'rapls-ai-chatbot') . ')',
            'o4-mini'       => 'o4 mini (' . __('Latest reasoning, fast', 'rapls-ai-chatbot') . ')',
        ];
    }

    /**
     * Get vision-capable models
     */
    public function get_vision_models(): array {
        return [
            'gpt-5.2',
            'gpt-5.2-pro',
            'gpt-5.1',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-5-pro',
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4.1-nano',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
        ];
    }

    /**
     * Check if a model supports vision
     */
    public function is_vision_model(string $model_id): bool {
        // Known hardcoded vision models
        if (in_array($model_id, $this->get_vision_models(), true)) {
            return true;
        }
        // GPT-4o, GPT-4.1, GPT-5 series are vision-capable
        if (strpos($model_id, 'gpt-4o') === 0 ||
            strpos($model_id, 'gpt-4.1') === 0 ||
            strpos($model_id, 'gpt-5') === 0) {
            return true;
        }
        // GPT-4-turbo is vision-capable
        if (strpos($model_id, 'gpt-4-turbo') === 0) {
            return true;
        }
        return false;
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        return $this->is_vision_model($this->model);
    }

    /**
     * Fetch models from API
     */
    public function fetch_models_from_api(): array {
        if (empty($this->api_key)) {
            return [];
        }

        $cache_key = 'wpaic_models_openai_v2_' . md5($this->api_key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $hardcoded = $this->get_available_models();

        // Exclude non-text-generation model prefixes/substrings
        $exclude_prefixes = ['dall-e-', 'gpt-image-', 'tts-', 'text-embedding-', 'whisper-', 'babbage-', 'davinci-'];
        $exclude_contains = ['ft:', '-instruct', '-realtime'];

        $models = [];
        foreach ($data['data'] as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            // Exclude by prefix
            $excluded = false;
            foreach ($exclude_prefixes as $prefix) {
                if (strpos($id, $prefix) === 0) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Exclude by substring
            foreach ($exclude_contains as $substr) {
                if (strpos($id, $substr) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Only include gpt-* and o-series reasoning models
            $is_gpt = strpos($id, 'gpt-') === 0;
            $is_reasoning = preg_match('/^o[134]/', $id);
            if (!$is_gpt && !$is_reasoning) {
                continue;
            }

            // Exclude -audio models except gpt-4o-audio variants
            if (strpos($id, '-audio') !== false && strpos($id, 'gpt-4o') !== 0) {
                continue;
            }

            // Exclude -tts models
            if (strpos($id, '-tts') !== false) {
                continue;
            }

            // Use hardcoded label if available, otherwise auto-generate
            if (isset($hardcoded[$id])) {
                $models[$id] = $hardcoded[$id];
            } else {
                $models[$id] = $this->format_model_name($id);
            }
        }

        // Also include hardcoded models not returned by API
        foreach ($hardcoded as $id => $label) {
            if (!isset($models[$id])) {
                $models[$id] = $label;
            }
        }

        // Sort matching WP AI Client sort order
        uksort($models, function ($a, $b) {
            return $this->compare_model_ids_desc($a, $b);
        });

        set_transient($cache_key, $models, DAY_IN_SECONDS);
        return $models;
    }

    /**
     * Format model ID to display name
     */
    private function format_model_name(string $id): string {
        // e.g. gpt-4o-2024-11-20 → GPT-4o (2024-11-20)
        $name = $id;
        // Extract date suffix
        if (preg_match('/^(.+?)(-\d{4}-\d{2}-\d{2})$/', $name, $m)) {
            $base = strtoupper(str_replace('-', '-', $m[1]));
            $base = str_replace('GPT-', 'GPT-', $base);
            return $base . ' (' . ltrim($m[2], '-') . ')';
        }
        $name = strtoupper($name);
        $name = str_replace('GPT-', 'GPT-', $name);
        return $name;
    }

    /**
     * Compare model IDs for sorting — matches WP AI Client modelSortCallback.
     *
     * Order: non-preview > preview, GPT > non-GPT,
     * versioned GPT (gpt-5.2) > non-versioned GPT (gpt-4o),
     * higher version first, base > suffix, -mini > other suffix,
     * alphabetical fallback.
     */
    private function compare_model_ids_desc(string $a, string $b): int {
        // 1. Prefer non-preview over preview
        $a_preview = strpos($a, '-preview') !== false;
        $b_preview = strpos($b, '-preview') !== false;
        if ($a_preview && !$b_preview) {
            return 1;
        }
        if ($b_preview && !$a_preview) {
            return -1;
        }

        // 2. Prefer GPT over non-GPT
        $a_gpt = strpos($a, 'gpt-') === 0;
        $b_gpt = strpos($b, 'gpt-') === 0;
        if ($a_gpt && !$b_gpt) {
            return -1;
        }
        if ($b_gpt && !$a_gpt) {
            return 1;
        }

        // 3. Among GPT models: prefer versioned (gpt-5.2) over non-versioned (gpt-4o)
        $a_match = preg_match('/^gpt-([0-9.]+)(-[a-z0-9-]+)?$/', $a, $am);
        $b_match = preg_match('/^gpt-([0-9.]+)(-[a-z0-9-]+)?$/', $b, $bm);
        if ($a_match && !$b_match) {
            return -1;
        }
        if ($b_match && !$a_match) {
            return 1;
        }
        if ($a_match && $b_match) {
            // 4. Higher version first
            $cmp = version_compare($bm[1], $am[1]);
            if ($cmp !== 0) {
                return $cmp;
            }

            // 5. Base (no suffix) before suffixed
            $a_has_suffix = isset($am[2]);
            $b_has_suffix = isset($bm[2]);
            if (!$a_has_suffix && $b_has_suffix) {
                return -1;
            }
            if ($a_has_suffix && !$b_has_suffix) {
                return 1;
            }

            // 6. -mini before other suffixes
            if ($a_has_suffix && $b_has_suffix) {
                if ($am[2] === '-mini' && $bm[2] !== '-mini') {
                    return -1;
                }
                if ($bm[2] === '-mini' && $am[2] !== '-mini') {
                    return 1;
                }
            }
        }

        // 7. Alphabetical fallback
        return strcmp($a, $b);
    }

    // Removed: no longer used.

    /**
     * Validate API Key
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key)) {
            return false;
        }

        try {
            $response = wp_remote_get('https://api.openai.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'timeout' => 10,
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            return wp_remote_retrieve_response_code($response) === 200;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Provider name
     */
    public function get_name(): string {
        return 'openai';
    }
}
