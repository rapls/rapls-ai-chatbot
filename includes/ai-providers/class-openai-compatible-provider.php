<?php
/**
 * Generic OpenAI-compatible API Provider
 *
 * Talks to any service that exposes an OpenAI-style Chat Completions endpoint
 * (base URL + model + Bearer key). Built for Chinese vendors that are hard to
 * reach through OpenAI/Gemini from mainland China — Alibaba Tongyi Qwen
 * (DashScope compatible-mode), DeepSeek, Zhipu GLM, Moonshot — but works with
 * any OpenAI-compatible endpoint.
 *
 * The base URL is user-supplied (e.g. https://dashscope.aliyuncs.com/compatible-mode/v1)
 * and this class appends /chat/completions. Embeddings for the same vendors are
 * handled separately in RAPLSAICH_Embedding_Generator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_OpenAI_Compatible_Provider implements RAPLSAICH_AI_Provider_Interface {

    /**
     * API Key
     */
    private string $api_key = '';

    /**
     * Model id (free-text — vendor-specific, e.g. qwen-plus)
     */
    private string $model = '';

    /**
     * Base URL, without a trailing slash. The /chat/completions and /models
     * paths are appended to this.
     */
    private string $base_url = '';

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
        $this->model = trim($model);
    }

    /**
     * Set the base URL (e.g. https://dashscope.aliyuncs.com/compatible-mode/v1).
     * Not part of the interface — the factory calls it after construction.
     */
    public function set_base_url(string $url): void {
        // Tolerate users pasting a full endpoint (…/v1/chat/completions or …/v1/embeddings).
        $this->base_url = function_exists('raplsaich_normalize_compat_base_url')
            ? raplsaich_normalize_compat_base_url($url)
            : rtrim(trim($url), '/');
    }

    /**
     * Full chat/completions endpoint.
     */
    private function chat_url(): string {
        return $this->base_url . '/chat/completions';
    }

    /**
     * Send message
     */
    public function send_message(array $messages, array $options = []): array {
        if (empty($this->api_key)) {
            throw new Exception(esc_html__('OpenAI-compatible API key is not configured.', 'rapls-ai-chatbot'));
        }
        if (empty($this->base_url)) {
            throw new Exception(esc_html__('OpenAI-compatible API base URL is not configured.', 'rapls-ai-chatbot'));
        }
        if (empty($this->model)) {
            throw new Exception(esc_html__('OpenAI-compatible model is not configured.', 'rapls-ai-chatbot'));
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

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
        ];

        /** This filter is documented in includes/ai-providers/class-openrouter-provider.php */
        $requested = (int) apply_filters('raplsaich_api_timeout', 120, $this->chat_url(), $this->model);
        $max_exec = (int) ini_get('max_execution_time');
        $upper = $max_exec > 0 ? min(300, max(10, $max_exec - 5)) : 300;
        $timeout = max(10, min($upper, $requested));

        $response = wp_remote_post($this->chat_url(), [
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
            $this->handle_api_error((int) $response_code, is_array($data) ? $data : null, $response);
        }

        return $this->parse_response(is_array($data) ? $data : []);
    }

    /**
     * Handle API error responses (OpenAI-style error envelope).
     *
     * @throws Exception|RAPLSAICH_Quota_Exceeded_Exception
     */
    private function handle_api_error(int $response_code, ?array $data, $raw_response = null): void {
        if (!is_array($data)) {
            throw new Exception(esc_html__('OpenAI-compatible API error: ', 'rapls-ai-chatbot') . esc_html(wp_remote_retrieve_response_message($raw_response)), $response_code);
        }
        $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');

        raplsaich_rate_limited_log(
            'compat_api_error_' . $response_code,
            sprintf(
                'RAPLSAICH OpenAI-compatible API Error: HTTP %d | model=%s | message=%s',
                $response_code,
                $this->model,
                $error_message
            )
        );

        if ($response_code === 401) {
            throw new Exception(esc_html__('OpenAI-compatible API key is invalid or has been revoked.', 'rapls-ai-chatbot'), 401);
        }

        if ($response_code === 402 ||
            $response_code === 429 ||
            stripos($error_message, 'quota') !== false ||
            stripos($error_message, 'insufficient') !== false ||
            stripos($error_message, 'balance') !== false) {
            $ex = new RAPLSAICH_Quota_Exceeded_Exception(esc_html($error_message));
            if ($raw_response && !is_wp_error($raw_response)) {
                $retry_after = wp_remote_retrieve_header($raw_response, 'retry-after');
                if (is_numeric($retry_after) && (int) $retry_after > 0) {
                    $ex->set_retry_after((int) $retry_after);
                }
            }
            throw $ex;
        }

        if ($response_code === 404) {
            throw new Exception(
                /* translators: %s: AI model name */
                sprintf(esc_html__('Model "%s" not found at this endpoint. Check the model name and base URL in Settings.', 'rapls-ai-chatbot'), esc_html($this->model)),
                404
            );
        }

        if ($response_code >= 500) {
            throw new Exception(
                /* translators: %d: HTTP status code */
                sprintf(esc_html__('Provider server error (HTTP %d). Please try again later.', 'rapls-ai-chatbot'), $response_code),
                $response_code
            );
        }

        throw new Exception(
            esc_html__('OpenAI-compatible API error: ', 'rapls-ai-chatbot') . esc_html($error_message),
            $response_code
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
     * Available models — curated hints for common vendors. The UI keeps the
     * model as a free-text field, so this is only a convenience shortlist.
     */
    public function get_available_models(): array {
        return [
            'qwen-plus'     => 'Qwen Plus (Alibaba DashScope)',
            'qwen-turbo'    => 'Qwen Turbo (Alibaba DashScope)',
            'qwen-max'      => 'Qwen Max (Alibaba DashScope)',
            'deepseek-chat' => 'DeepSeek Chat',
            'glm-4-plus'    => 'Zhipu GLM-4 Plus',
        ];
    }

    /**
     * Fetch models from the endpoint's /models list (OpenAI-compatible).
     * Best-effort — vendors that don't expose /models just yield an empty list.
     */
    public function fetch_models_from_api(): array {
        if (empty($this->api_key) || empty($this->base_url)) {
            return [];
        }

        $cache_key = 'raplsaich_models_compat_' . md5($this->base_url . '|' . $this->api_key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($this->base_url . '/models', [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $models = $this->get_available_models();
        foreach ($data['data'] as $model) {
            $id = $model['id'] ?? '';
            if ($id !== '' && !isset($models[$id])) {
                $models[$id] = $id;
            }
        }

        set_transient($cache_key, $models, DAY_IN_SECONDS);
        return $models;
    }

    /**
     * Validate API Key by hitting the /models endpoint.
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key) || empty($this->base_url)) {
            return false;
        }

        $response = wp_remote_get($this->base_url . '/models', [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
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
        return 'compat';
    }
}
