<?php
/**
 * Claude (Anthropic) API Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Claude_Provider implements WPAIC_AI_Provider_Interface {

    /**
     * API Key
     */
    private string $api_key = '';

    /**
     * Model
     */
    private string $model = 'claude-3-haiku-20240307';

    /**
     * API Endpoint
     */
    private string $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * API Version
     */
    private string $api_version = '2023-06-01';

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
            throw new Exception(esc_html__('Claude API key is not configured.', 'rapls-ai-chatbot'));
        }

        // Validate model is still available
        $available_models = $this->get_available_models();
        if (!array_key_exists($this->model, $available_models)) {
            $default_model = 'claude-haiku-4-5-20251001';
            throw new Exception(
                sprintf(
                    /* translators: 1: current model name, 2: default model name */
                    esc_html__('The model "%1$s" is no longer available. Please go to Settings and select a current model (e.g. %2$s).', 'rapls-ai-chatbot'),
                    esc_html($this->model),
                    esc_html($default_model)
                )
            );
        }

        // Separate system message
        $system_message = '';
        $chat_messages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_message .= $msg['content'] . "\n";
            } else {
                $chat_messages[] = [
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        $body = [
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'messages'   => $chat_messages,
        ];

        if (!empty($system_message)) {
            $body['system'] = trim($system_message);
        }

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => $this->api_version,
                'Content-Type'      => 'application/json',
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
            $error_type = $data['error']['type'] ?? '';

            // Log detailed error for debugging
            error_log(sprintf(
                'WPAIC Claude API Error: HTTP %d | type=%s | model=%s | message=%s',
                $response_code,
                $error_type,
                $this->model,
                $error_message
            ));

            // Authentication errors
            if ($response_code === 401 || $error_type === 'authentication_error') {
                throw new Exception(esc_html__('Claude API key is invalid or has been revoked.', 'rapls-ai-chatbot'));
            }

            // Check for quota/billing errors
            if ($response_code === 429 || $response_code === 402 ||
                $error_type === 'rate_limit_error' ||
                stripos($error_message, 'credit') !== false ||
                stripos($error_message, 'quota') !== false ||
                stripos($error_message, 'billing') !== false ||
                stripos($error_message, 'exceeded') !== false) {
                throw new WPAIC_Quota_Exceeded_Exception(esc_html($error_message));
            }

            // Invalid parameter errors
            if ($response_code === 400 || $error_type === 'invalid_request_error') {
                throw new Exception(
                    /* translators: 1: model name, 2: error message */
                    sprintf(esc_html__('Claude API parameter error (model: %1$s): %2$s. Please try selecting a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model), esc_html($error_message))
                );
            }

            // Model not found
            if ($response_code === 404) {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('Claude model "%s" not found. It may have been deprecated or renamed. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model))
                );
            }

            // Server errors
            if ($response_code >= 500) {
                throw new Exception(
                    /* translators: %d: HTTP status code */
                    sprintf(esc_html__('Claude server error (HTTP %d). The service may be temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'), $response_code)
                );
            }

            throw new Exception(esc_html__('Claude API error: ', 'rapls-ai-chatbot') . esc_html($error_message));
        }

        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        // Calculate token usage
        $input_tokens = $data['usage']['input_tokens'] ?? 0;
        $output_tokens = $data['usage']['output_tokens'] ?? 0;
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
            // Latest generation
            'claude-opus-4-6'             => 'Claude Opus 4.6 (' . __('Most powerful', 'rapls-ai-chatbot') . ')',
            'claude-sonnet-4-5-20250929'  => 'Claude Sonnet 4.5 (' . __('★ Recommended — fast and powerful', 'rapls-ai-chatbot') . ')',
            'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5 (' . __('★ Recommended — fastest, cheapest', 'rapls-ai-chatbot') . ')',
            // Previous generation
            'claude-opus-4-5-20251101'    => 'Claude Opus 4.5 (' . __('Previous flagship', 'rapls-ai-chatbot') . ')',
            'claude-opus-4-1-20250805'    => 'Claude Opus 4.1 (' . __('Coding focused', 'rapls-ai-chatbot') . ')',
            'claude-sonnet-4-20250514'    => 'Claude Sonnet 4 (' . __('Reliable all-purpose', 'rapls-ai-chatbot') . ')',
            'claude-3-7-sonnet-20250219'  => 'Claude 3.7 Sonnet (' . __('Reasoning capable', 'rapls-ai-chatbot') . ')',
        ];
    }

    /**
     * Get vision-capable models (all Claude 3+ models support vision)
     */
    public function get_vision_models(): array {
        return [
            'claude-opus-4-6',
            'claude-sonnet-4-5-20250929',
            'claude-haiku-4-5-20251001',
            'claude-opus-4-5-20251101',
            'claude-opus-4-1-20250805',
            'claude-sonnet-4-20250514',
            'claude-3-7-sonnet-20250219',
        ];
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        // All Claude 3+ models support vision
        return strpos($this->model, 'claude-3') !== false ||
               strpos($this->model, 'claude-opus-4') !== false ||
               strpos($this->model, 'claude-sonnet-4') !== false;
    }

    /**
     * Validate API Key
     */
    public function validate_api_key(): bool {
        if (empty($this->api_key)) {
            return false;
        }

        try {
            // Validate with minimal request
            $response = wp_remote_post($this->api_url, [
                'headers' => [
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => $this->api_version,
                    'Content-Type'      => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'model'      => 'claude-3-haiku-20240307',
                    'max_tokens' => 10,
                    'messages'   => [
                        ['role' => 'user', 'content' => 'Hi']
                    ],
                ]),
                'timeout' => 10,
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);
            return $code === 200;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Fetch models from API
     * Anthropic does not provide a public model listing API
     */
    public function fetch_models_from_api(): array {
        return [];
    }

    /**
     * Provider name
     */
    public function get_name(): string {
        return 'claude';
    }
}
