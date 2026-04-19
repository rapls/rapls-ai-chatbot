<?php
/**
 * Claude (Anthropic) API Provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Claude_Provider implements RAPLSAICH_AI_Provider_Interface {

    /**
     * API Key
     */
    private string $api_key = '';

    /**
     * Model
     */
    private string $model = 'claude-haiku-4-5-20251001';

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

        // Validate model format (allow any claude-* model, including dynamically fetched ones)
        if (!preg_match('/^claude-/', $this->model)) {
            $default_model = 'claude-haiku-4-5-20251001';
            throw new Exception(
                sprintf(
                    /* translators: 1: current model name, 2: default model name */
                    esc_html__('The model "%1$s" is not a valid Claude model. Please go to Settings and select a current model (e.g. %2$s).', 'rapls-ai-chatbot'),
                    esc_html($this->model),
                    esc_html($default_model)
                )
            );
        }

        // Separate system message
        $system_message = '';
        $chat_messages = [];

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
                $system_message .= $msg['content'] . "\n";
            } else {
                $content = $msg['content'];

                // Inject file first, then image, so content order is: document, image, text
                if ($idx === $last_user_idx && !empty($file_data)) {
                    $content = $this->build_document_content($content, $file_data, $file_name);
                }

                // Inject image into the last user message for vision
                if ($idx === $last_user_idx && !empty($image_data)) {
                    $content = $this->build_vision_content($content, $image_data);
                }

                $chat_messages[] = [
                    'role'    => $msg['role'],
                    'content' => $content,
                ];
            }
        }

        $body = [
            'model'       => $this->model,
            'max_tokens'  => $options['max_tokens'] ?? 1000,
            'messages'    => $chat_messages,
            'temperature' => (float) ($options['temperature'] ?? 0.7),
        ];

        if (!empty($system_message)) {
            $body['system'] = trim($system_message);
        }

        // Web search tool
        if (!empty($options['web_search'])) {
            $body['tools'] = [
                [
                    'type'     => 'web_search_20250305',
                    'name'     => 'web_search',
                    'max_uses' => 3,
                ],
            ];
            // Force web search when knowledge base has no relevant content
            if (!empty($options['force_web_search'])) {
                $body['tool_choice'] = ['type' => 'any'];
            }
        }

        /** @see RAPLSAICH_OpenAI_Provider::send_http_request() for filter docs */
        $requested = (int) apply_filters('raplsaich_api_timeout', 120, $this->api_url, $this->model);
        $max_exec  = (int) ini_get('max_execution_time');
        $upper     = ($max_exec > 0) ? min(300, max(10, $max_exec - 5)) : 300;
        $timeout   = max(10, min($upper, $requested));

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => $this->api_version,
                'Content-Type'      => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            throw new RAPLSAICH_Communication_Exception(esc_html__('API communication error: ', 'rapls-ai-chatbot') . esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            if (!is_array($data)) {
                throw new Exception(esc_html__('Claude API error: ', 'rapls-ai-chatbot') . esc_html(wp_remote_retrieve_response_message($response)), (int) $response_code);
            }
            $error_message = $data['error']['message'] ?? __('Unknown error', 'rapls-ai-chatbot');
            $error_type = $data['error']['type'] ?? '';

            // Rate-limited: under API outages, every chat request triggers this.
            raplsaich_rate_limited_log(
                'claude_api_error_' . $response_code,
                sprintf(
                    'RAPLSAICH Claude API Error: HTTP %d | type=%s | model=%s | message=%s',
                    $response_code,
                    $error_type,
                    $this->model,
                    $error_message
                )
            );

            // Authentication errors
            if ($response_code === 401 || $error_type === 'authentication_error') {
                throw new Exception(esc_html__('Claude API key is invalid or has been revoked.', 'rapls-ai-chatbot'), 401);
            }

            // Check for quota/billing errors
            if ($response_code === 429 || $response_code === 402 ||
                $error_type === 'rate_limit_error' ||
                stripos($error_message, 'credit') !== false ||
                stripos($error_message, 'quota') !== false ||
                stripos($error_message, 'billing') !== false ||
                stripos($error_message, 'exceeded') !== false) {
                $ex = new RAPLSAICH_Quota_Exceeded_Exception(esc_html($error_message));
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if (is_numeric($retry_after) && (int) $retry_after > 0) {
                    $ex->set_retry_after((int) $retry_after);
                }
                throw $ex;
            }

            // Invalid parameter errors
            if ($response_code === 400 || $error_type === 'invalid_request_error') {
                throw new Exception(
                    /* translators: 1: model name, 2: error message */
                    sprintf(esc_html__('Claude API parameter error (model: %1$s): %2$s. Please try selecting a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model), esc_html($error_message)),
                    400
                );
            }

            // Model not found
            if ($response_code === 404) {
                throw new Exception(
                    /* translators: %s: model name */
                    sprintf(esc_html__('Claude model "%s" not found. It may have been deprecated or renamed. Please select a different model in Settings.', 'rapls-ai-chatbot'), esc_html($this->model)),
                    404
                );
            }

            // Server errors
            if ($response_code >= 500) {
                throw new Exception(
                    /* translators: %d: HTTP status code */
                    sprintf(esc_html__('Claude server error (HTTP %d). The service may be temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'), (int) $response_code),
                    (int) $response_code
                );
            }

            throw new Exception(esc_html__('Claude API error: ', 'rapls-ai-chatbot') . esc_html($error_message), (int) $response_code);
        }

        $content = '';
        $web_sources = [];
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                    $content .= $block['text'];
                    // Extract web search citations
                    if (isset($block['citations']) && is_array($block['citations'])) {
                        foreach ($block['citations'] as $citation) {
                            if (($citation['type'] ?? '') === 'web_search_result_location'
                                && !empty($citation['url'])) {
                                $web_sources[] = [
                                    'url'   => $citation['url'],
                                    'title' => $citation['title'] ?? '',
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Deduplicate web sources by URL
        if (!empty($web_sources)) {
            $seen = [];
            $unique = [];
            foreach ($web_sources as $src) {
                if (!isset($seen[$src['url']])) {
                    $seen[$src['url']] = true;
                    $unique[] = $src;
                }
            }
            $web_sources = $unique;
        }

        if ($content === '') {
            throw new Exception(esc_html__('Failed to get response from AI.', 'rapls-ai-chatbot'));
        }

        // Calculate token usage
        $input_tokens = $data['usage']['input_tokens'] ?? 0;
        $output_tokens = $data['usage']['output_tokens'] ?? 0;
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
            // Latest generation
            'claude-opus-4-6'             => 'Claude Opus 4.6 (' . __('Most powerful', 'rapls-ai-chatbot') . ')',
            'claude-sonnet-4-5-20250929'  => 'Claude Sonnet 4.5 (' . __('★ Recommended — fast and powerful', 'rapls-ai-chatbot') . ')',
            'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5 (' . __('★ Recommended — fastest, cheapest', 'rapls-ai-chatbot') . ')',
            // Previous generation
            'claude-opus-4-5-20251101'    => 'Claude Opus 4.5 (' . __('Previous flagship', 'rapls-ai-chatbot') . ')',
            'claude-opus-4-1-20250805'    => 'Claude Opus 4.1 (' . __('Coding focused', 'rapls-ai-chatbot') . ')',
            'claude-sonnet-4-20250514'    => 'Claude Sonnet 4 (' . __('Reliable all-purpose', 'rapls-ai-chatbot') . ')',
        ];
    }

    /**
     * Get vision-capable models
     */
    public function get_vision_models(): array {
        return [
            'claude-opus-4-6',
            'claude-sonnet-4-5-20250929',
            'claude-haiku-4-5-20251001',
            'claude-opus-4-5-20251101',
            'claude-opus-4-1-20250805',
            'claude-sonnet-4-20250514',
        ];
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        return strpos($this->model, 'claude-opus-4') !== false ||
               strpos($this->model, 'claude-sonnet-4') !== false ||
               strpos($this->model, 'claude-haiku-4') !== false;
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
                    'model'      => $this->model ?: 'claude-haiku-4-5-20251001',
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
     * Build document content array for Claude API (PDF support).
     */
    private function build_document_content($content, string $file_data, string $file_name): array {
        $media_type = 'application/pdf';
        $base64 = $file_data;

        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $file_data, $m)) {
            $media_type = $m[1];
            $base64 = $m[2];
        }

        $text = is_array($content) ? $content : [['type' => 'text', 'text' => $content]];

        $doc_block = [
            'type'   => 'document',
            'source' => [
                'type'       => 'base64',
                'media_type' => $media_type,
                'data'       => $base64,
            ],
        ];

        // Include file name as document title for better AI context
        if (!empty($file_name)) {
            $doc_block['title'] = $file_name;
        }

        array_unshift($text, $doc_block);

        return $text;
    }

    /**
     * Build vision content array for Claude API.
     * Converts text + image data URL to Claude's multimodal content format.
     */
    private function build_vision_content($text, string $image_data): array {
        // Parse data URI: data:image/jpeg;base64,/9j/4AAQ...
        $media_type = 'image/jpeg';
        $base64 = $image_data;

        if (preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $image_data, $m)) {
            $media_type = $m[1];
            $base64 = $m[2];
        }

        $image_block = [
            'type'   => 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => $media_type,
                'data'       => $base64,
            ],
        ];

        // If $text is already a multimodal content array (e.g. from build_document_content), prepend image
        if (is_array($text)) {
            array_unshift($text, $image_block);
            return $text;
        }

        return [
            $image_block,
            [
                'type' => 'text',
                'text' => $text,
            ],
        ];
    }

    /**
     * Provider name
     */
    public function get_name(): string {
        return 'claude';
    }
}
