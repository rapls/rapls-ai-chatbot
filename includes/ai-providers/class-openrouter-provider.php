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

class WPAIC_OpenRouter_Provider implements WPAIC_AI_Provider_Interface {

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
        $requested = (int) apply_filters('wpaic_api_timeout', 120, $this->api_url, $this->model);
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
            throw new WPAIC_Communication_Exception(
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
     * @throws Exception|WPAIC_Quota_Exceeded_Exception
     */
    private function handle_api_error(int $response_code, ?array $data, $raw_response = null): void {
        if (!is_array($data)) {
            throw new Exception(esc_html__('OpenRouter API error: ', 'rapls-ai-chatbot') . esc_html(wp_remote_retrieve_response_message($raw_response)), (int) $response_code);
        }
        $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
        $error_code = $data['error']['code'] ?? '';

        wpaic_rate_limited_log(
            'openrouter_api_error_' . $response_code,
            sprintf(
                'WPAIC OpenRouter API Error: HTTP %d | code=%s | model=%s | message=%s',
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
            $ex = new WPAIC_Quota_Exceeded_Exception(esc_html($error_message));
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

        $cache_key = 'wpaic_models_openrouter_v2_' . md5($this->api_key);
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
     * Validate API Key
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key)) {
            return false;
        }

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
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
