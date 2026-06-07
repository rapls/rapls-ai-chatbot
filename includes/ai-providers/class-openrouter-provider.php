<?php
/**
 * OpenRouter API Provider
 *
 * OpenAI-compatible API providing access to 100+ AI models via a single API key.
 * Uses the Chat Completions compatible endpoint at openrouter.ai/api/v1.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_OpenRouter_Provider implements RAPLSAICH_AI_Provider_Interface {

    /**
     * API Key
     */
    private string $api_key = '';

    /**
     * Model
     */
    private string $model = 'openrouter/auto';

    /**
     * API Endpoint
     */
    private string $api_url = 'https://openrouter.ai/api/v1/chat/completions';

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
            throw new Exception(esc_html__('OpenRouter API key is not configured.', 'rapls-ai-chatbot'));
        }

        // Legacy/virtual id: OpenRouter has no "openrouter/free" router. Resolve to a
        // concrete :free model at request time so installs from pre-1.9.1 onboarding
        // (which saved this magic value) keep working without manual settings cleanup.
        if ($this->model === 'openrouter/free') {
            $this->model = $this->resolve_free_model_id();
        }

        // Inject file as text into the last user message (no native file support)
        if (!empty($options['file'])) {
            $file_name = $options['file_name'] ?? '';
            $file_data = $options['file'];
            $comma_pos = strpos($file_data, ',');
            if ($comma_pos !== false) {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                $decoded = base64_decode(substr($file_data, $comma_pos + 1), true);
                if ($decoded !== false) {
                    $text = wp_check_invalid_utf8($decoded, true);
                    if (!empty(trim($text))) {
                        $max = 30000;
                        $text = function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
                        $file_text = sprintf("Content of uploaded file (%s):\n%s", $file_name, $text);
                        for ($i = count($messages) - 1; $i >= 0; $i--) {
                            if ($messages[$i]['role'] === 'user') {
                                if (is_array($messages[$i]['content'])) {
                                    $messages[$i]['content'][] = ['type' => 'text', 'text' => "\n\n---\n" . $file_text];
                                } elseif (is_string($messages[$i]['content'])) {
                                    $messages[$i]['content'] .= "\n\n---\n" . $file_text;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Inject image into the last user message (OpenAI vision format)
        if (!empty($options['image'])) {
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if ($messages[$i]['role'] === 'user') {
                    $text = $messages[$i]['content'];
                    if (is_string($text)) {
                        $messages[$i]['content'] = [
                            ['type' => 'text', 'text' => $text],
                            ['type' => 'image_url', 'image_url' => ['url' => $options['image']]],
                        ];
                    } elseif (is_array($text)) {
                        $messages[$i]['content'][] = ['type' => 'image_url', 'image_url' => ['url' => $options['image']]];
                    }
                    break;
                }
            }
        }

        try {
            return $this->_send_message_once($messages, $options);
        } catch (RAPLSAICH_Quota_Exceeded_Exception $e) {
            // Free models on OpenRouter are routed through upstream providers (Venice,
            // OpenAI, NVIDIA, etc) that throttle the free pool aggressively. When the
            // active :free model 429s, mark it throttled, pick a different :free model
            // from the catalog, persist the change so the next request uses it directly,
            // and retry the chat ONCE. Paid models legitimately 429 on quota and should
            // surface the error to the user unchanged.
            if (substr($this->model, -5) !== ':free') {
                throw $e;
            }
            $failed_model = $this->model;
            $this->mark_model_throttled($failed_model);
            $alternative = $this->resolve_free_model_id([$failed_model]);
            if ($alternative === $failed_model || $alternative === 'openrouter/auto') {
                throw $e; // no viable alternative — surface original quota error
            }
            $this->model = $alternative;
            $this->persist_model_change($alternative);
            return $this->_send_message_once($messages, $options);
        }
    }

    /**
     * Single HTTP round-trip to OpenRouter's chat/completions endpoint with the
     * current $this->model and $this->api_key. Throws on non-2xx via handle_api_error.
     */
    private function _send_message_once(array $messages, array $options): array {
        $body = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }

        $body['temperature'] = (float) ($options['temperature'] ?? 0.7);

        // Web search tool (OpenAI-compatible)
        if (!empty($options['web_search'])) {
            $body['tools'] = [['type' => 'web_search_preview']];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => site_url(),
            'X-Title'       => get_bloginfo('name'),
        ];

        /**
         * Filter the timeout for OpenRouter API requests.
         *
         * @param int $timeout Timeout in seconds (clamped to 10-300).
         */
        $requested = (int) apply_filters('raplsaich_api_timeout', 120, $this->api_url, $this->model);
        $max_exec = (int) ini_get('max_execution_time');
        if ($max_exec > 0) {
            $upper = min(300, max(10, $max_exec - 5));
        } else {
            $upper = 300;
        }
        $timeout = max(10, min($upper, $requested));

        $response = wp_remote_post($this->api_url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            throw new RAPLSAICH_Communication_Exception(
                esc_html__('API communication error: ', 'rapls-ai-chatbot') . esc_html($response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $this->handle_api_error($response_code, $data, $response);
        }

        return $this->parse_response($data);
    }

    /**
     * Handle API error responses
     *
     * @throws Exception|RAPLSAICH_Quota_Exceeded_Exception
     */
    private function handle_api_error(int $response_code, ?array $data, $raw_response = null): void {
        if (!is_array($data)) {
            throw new Exception(esc_html__('OpenRouter API error: ', 'rapls-ai-chatbot') . esc_html(wp_remote_retrieve_response_message($raw_response)), (int) $response_code);
        }
        $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
        $error_code = $data['error']['code'] ?? '';

        raplsaich_rate_limited_log(
            'openrouter_api_error_' . $response_code,
            sprintf(
                'RAPLSAICH OpenRouter API Error: HTTP %d | code=%s | model=%s | message=%s',
                $response_code,
                $error_code,
                $this->model,
                $error_message
            )
        );

        if ($response_code === 401) {
            throw new Exception(esc_html__('OpenRouter API key is invalid or has been revoked.', 'rapls-ai-chatbot'), 401);
        }

        if ($response_code === 402 ||
            $response_code === 429 ||
            stripos($error_message, 'quota') !== false ||
            stripos($error_message, 'credits') !== false) {
            $ex = new RAPLSAICH_Quota_Exceeded_Exception(esc_html($error_message));
            if ($raw_response && !is_wp_error($raw_response)) {
                $retry_after = wp_remote_retrieve_header($raw_response, 'retry-after');
                if (is_numeric($retry_after) && (int) $retry_after > 0) {
                    $ex->set_retry_after((int) $retry_after);
                }
            }
            throw $ex;
        }

        if ($response_code === 403) {
            throw new Exception(
                /* translators: %s: AI model name */
                sprintf(esc_html__('Access denied for model "%s". Your OpenRouter account may not have permission to use this model.', 'rapls-ai-chatbot'), esc_html($this->model)),
                403
            );
        }

        if ($response_code === 404) {
            throw new Exception(
                /* translators: %s: AI model name */
                sprintf(esc_html__('OpenRouter model "%s" not found. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model)),
                404
            );
        }

        if ($response_code >= 500) {
            throw new Exception(
                /* translators: %d: HTTP status code */
                sprintf(esc_html__('OpenRouter server error (HTTP %d). Please try again later.', 'rapls-ai-chatbot'), (int) $response_code),
                (int) $response_code
            );
        }

        throw new Exception(
            esc_html__('OpenRouter API error: ', 'rapls-ai-chatbot') . esc_html($error_message),
            (int) $response_code
        );
    }

    /**
     * Parse API response (OpenAI Chat Completions format)
     */
    private function parse_response(array $data): array {
        $content = '';

        if (isset($data['choices'][0]['message']['content'])) {
            $raw = $data['choices'][0]['message']['content'];
            $content = is_string($raw) ? $raw : (is_array($raw) ? wp_json_encode($raw) : '');
        }

        $input_tokens = $data['usage']['prompt_tokens'] ?? 0;
        $output_tokens = $data['usage']['completion_tokens'] ?? 0;
        $tokens_used = $input_tokens + $output_tokens;
        if ($tokens_used === 0 && isset($data['usage']['total_tokens'])) {
            $tokens_used = $data['usage']['total_tokens'];
        }

        if (empty($content)) {
            throw new Exception(esc_html__('Failed to get response from AI.', 'rapls-ai-chatbot'));
        }

        return [
            'content'       => $content,
            'tokens_used'   => $tokens_used,
            'input_tokens'  => $input_tokens,
            'output_tokens' => $output_tokens,
            'model'         => $data['model'] ?? $this->model,
            'provider'      => $this->get_name(),
        ];
    }

    /**
     * Available models (hardcoded recommended list)
     */
    public function get_available_models(): array {
        return [
            'openrouter/auto'              => 'Auto (' . __('Best model auto-selected', 'rapls-ai-chatbot') . ')',
            'anthropic/claude-sonnet-4'    => 'Claude Sonnet 4',
            'openai/gpt-4o'               => 'GPT-4o',
            'google/gemini-2.5-flash'     => 'Gemini 2.5 Flash',
            'meta-llama/llama-4-maverick' => 'Llama 4 Maverick',
            'deepseek/deepseek-chat-v3'   => 'DeepSeek V3',
        ];
    }

    /**
     * Fetch models from OpenRouter API with pricing info
     */
    public function fetch_models_from_api(): array {
        if (empty($this->api_key)) {
            return [];
        }

        $cache_key = 'raplsaich_models_openrouter_v2_' . md5($this->api_key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
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
        $models = [];

        foreach ($data['data'] as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            // Only include chat-capable models
            if (isset($model['architecture']['modality'])) {
                $modality = $model['architecture']['modality'];
                if (strpos($modality, 'text') === false) {
                    continue;
                }
            }

            $name = $model['name'] ?? $id;

            // Add pricing info if available
            $prompt_price = $model['pricing']['prompt'] ?? null;
            if ($prompt_price !== null && (float) $prompt_price > 0) {
                $price_per_m = (float) $prompt_price * 1000000;
                if ($price_per_m < 1) {
                    $name .= sprintf(' ($%s/M)', number_format($price_per_m, 3));
                } else {
                    $name .= sprintf(' ($%s/M)', number_format($price_per_m, 2));
                }
            } elseif ($prompt_price !== null && (float) $prompt_price == 0) {
                $name .= ' (' . __('Free', 'rapls-ai-chatbot') . ')';
            }

            $models[$id] = $name;
        }

        // Ensure hardcoded models are included at top
        $result = $hardcoded;
        foreach ($models as $id => $label) {
            if (!isset($result[$id])) {
                $result[$id] = $label;
            }
        }

        set_transient($cache_key, $result, DAY_IN_SECONDS);
        return $result;
    }

    /**
     * Resolve "openrouter/free" (or pick a free fallback) to a concrete :free model id.
     *
     * Hits /v1/models, then picks from a preferred shortlist of currently-existing
     * chat-tuned free models. Excludes anything in $exclude or in the throttled
     * transient (models that recently returned 429). Falls back to the first
     * chat-capable :free model in the catalog, then to 'openrouter/auto'.
     *
     * Cached for 1 hour (short, because free model availability flutters).
     *
     * @param string[] $exclude Model ids to skip (e.g. one that just 429'd this turn).
     */
    public function resolve_free_model_id(array $exclude = []): string {
        $cache_key = 'raplsaich_or_free_model_v1';
        $throttled = get_transient('raplsaich_or_throttled_models_v1');
        if (!is_array($throttled)) {
            $throttled = [];
        }
        $skip = array_merge($exclude, $throttled);

        $cached = get_transient($cache_key);
        if (is_string($cached) && $cached !== '' && !in_array($cached, $skip, true)) {
            return $cached;
        }

        $headers = [];
        if (!empty($this->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return 'openrouter/auto';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['data']) || !is_array($body['data'])) {
            return 'openrouter/auto';
        }

        $ids = [];
        foreach ($body['data'] as $m) {
            if (!empty($m['id']) && is_string($m['id'])) {
                $ids[$m['id']] = $m;
            }
        }

        // Preferred shortlist: currently-existing chat-tuned free models, ordered
        // by general quality. Verified against the live OpenRouter catalog in
        // June 2026 — the previous list (Llama 3.1, Mistral 7B, Gemma 2, Qwen 2.5)
        // was 4-of-7 retired and never matched. Extenders can override.
        $preferred = apply_filters('raplsaich_openrouter_free_preferred', [
            'openai/gpt-oss-120b:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
            'google/gemma-4-31b-it:free',
            'z-ai/glm-4.5-air:free',
            'openai/gpt-oss-20b:free',
            'meta-llama/llama-3.3-70b-instruct:free',
            'nousresearch/hermes-3-llama-3.1-405b:free',
        ]);

        foreach ($preferred as $candidate) {
            if (isset($ids[$candidate]) && !in_array($candidate, $skip, true)) {
                set_transient($cache_key, $candidate, HOUR_IN_SECONDS);
                return $candidate;
            }
        }

        // Fallback: first :free model with zero prompt price, excluding obvious
        // non-chat patterns (safety classifiers, embeddings, audio, TTS).
        $non_chat_patterns = ['safety', 'embed', '-audio', 'tts', 'guard'];
        foreach ($ids as $id => $m) {
            if (substr($id, -5) !== ':free') {
                continue;
            }
            if ((float) ($m['pricing']['prompt'] ?? 1) != 0) {
                continue;
            }
            if (in_array($id, $skip, true)) {
                continue;
            }
            $lower = strtolower($id);
            $is_non_chat = false;
            foreach ($non_chat_patterns as $pat) {
                if (strpos($lower, $pat) !== false) {
                    $is_non_chat = true;
                    break;
                }
            }
            if ($is_non_chat) {
                continue;
            }
            set_transient($cache_key, $id, HOUR_IN_SECONDS);
            return $id;
        }

        return 'openrouter/auto';
    }

    /**
     * Mark a free model as throttled so resolve_free_model_id() skips it for the
     * next 10 minutes. Used by the chat-time 429 fallback.
     */
    private function mark_model_throttled(string $model): void {
        $key = 'raplsaich_or_throttled_models_v1';
        $list = get_transient($key);
        if (!is_array($list)) {
            $list = [];
        }
        if (!in_array($model, $list, true)) {
            $list[] = $model;
        }
        set_transient($key, $list, 10 * MINUTE_IN_SECONDS);
        // Force re-resolve so the cached id (now throttled) is not reused.
        delete_transient('raplsaich_or_free_model_v1');
    }

    /**
     * Persist a model swap to plugin settings so the next request uses the
     * fallback automatically instead of re-discovering it.
     */
    private function persist_model_change(string $new_model): void {
        $settings = get_option('raplsaich_settings', []);
        if (!is_array($settings)) {
            return;
        }
        if (($settings['ai_provider'] ?? '') !== 'openrouter') {
            return;
        }
        $settings['openrouter_model'] = $new_model;
        update_option('raplsaich_settings', $settings);
    }

    /**
     * Validate API Key
     *
     * Uses /v1/auth/key (requires real auth — returns 401 for invalid keys),
     * not /v1/models (which returns the public catalog with 200 even when the
     * supplied bearer is bogus, so it cannot distinguish a real key from junk).
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key) || strpos($this->api_key, 'sk-or-') !== 0) {
            return false;
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/auth/key', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 10,
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
        return 'openrouter';
    }
}
