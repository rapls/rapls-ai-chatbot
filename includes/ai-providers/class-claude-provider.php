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
     *
     * A model Anthropic has retired is swapped for its successor here, the one
     * place every Claude request passes through (chat, FAQ generation, the
     * fallback model, per-bot models, and the Pro calls that go through this
     * provider). The saved setting is left as it is; the settings screen and an
     * admin notice say which model is actually answering.
     */
    public function set_model(string $model): void {
        $resolved = self::resolve_model($model);
        if ($resolved !== $model) {
            raplsaich_rate_limited_log(
                'claude_retired_model_' . md5($model),
                sprintf('RAPLSAICH Claude: model %s has been retired by Anthropic; sending %s instead.', $model, $resolved)
            );
        }
        $this->model = $resolved;
    }

    /**
     * Claude models Anthropic has retired or scheduled for retirement, with the
     * model to send instead and the retirement date. Source: the Claude model
     * deprecations page. A row whose date is still ahead is ignored until that
     * day (see raplsaich_retirement_in_effect()), so a scheduled retirement can
     * be listed as soon as it is announced.
     *
     * Successors are the current model of the same family in this plugin's
     * list, so the admin can see and re-select it in Settings: Sonnet goes to
     * Sonnet 5.5 (Anthropic's replacement for Sonnet 4.5), Opus to Opus 5.5.
     *
     * @return array<string, array{to: string, retired: string}>
     */
    public static function retired_models(): array {
        return [
            'claude-sonnet-4-5-20250929' => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-11-30'],
            'claude-sonnet-4-5'          => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-11-30'],
            'claude-sonnet-4-20250514'   => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-06-15'],
            'claude-sonnet-4-0'          => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-06-15'],
            'claude-opus-4-20250514'     => ['to' => 'claude-opus-5-5', 'retired' => '2026-06-15'],
            'claude-opus-4-0'            => ['to' => 'claude-opus-5-5', 'retired' => '2026-06-15'],
            'claude-opus-4-1-20250805'   => ['to' => 'claude-opus-5-5', 'retired' => '2026-08-05'],
            'claude-opus-4-1'            => ['to' => 'claude-opus-5-5', 'retired' => '2026-08-05'],
            'claude-3-7-sonnet-20250219' => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-02-19'],
            'claude-3-7-sonnet-latest'   => ['to' => 'claude-sonnet-5-5', 'retired' => '2026-02-19'],
            'claude-3-5-haiku-20241022'  => ['to' => 'claude-haiku-4-5-20251001', 'retired' => '2026-02-19'],
            'claude-3-5-haiku-latest'    => ['to' => 'claude-haiku-4-5-20251001', 'retired' => '2026-02-19'],
            'claude-3-haiku-20240307'    => ['to' => 'claude-haiku-4-5-20251001', 'retired' => '2026-04-20'],
            'claude-3-opus-20240229'     => ['to' => 'claude-opus-5-5', 'retired' => '2026-01-05'],
            'claude-3-opus-latest'       => ['to' => 'claude-opus-5-5', 'retired' => '2026-01-05'],
            'claude-3-5-sonnet-20240620' => ['to' => 'claude-sonnet-5-5', 'retired' => '2025-10-28'],
            'claude-3-5-sonnet-20241022' => ['to' => 'claude-sonnet-5-5', 'retired' => '2025-10-28'],
            'claude-3-5-sonnet-latest'   => ['to' => 'claude-sonnet-5-5', 'retired' => '2025-10-28'],
            'claude-3-sonnet-20240229'   => ['to' => 'claude-sonnet-5-5', 'retired' => '2025-07-21'],
        ];
    }

    /**
     * Retirement details for a model, or null if it is not retired.
     *
     * Every Claude 3.x model is retired, so an ID not in the table but starting
     * "claude-3-" (a typed per-bot model, another alias) still gets a successor
     * from its family rather than failing.
     *
     * @return array{to: string, retired: string}|null
     */
    public static function retirement(string $model): ?array {
        /**
         * Filter the retired Claude models and their successors.
         *
         * @param array $retired Model ID => ['to' => successor ID, 'retired' => 'Y-m-d'].
         */
        $retired = (array) apply_filters('raplsaich_claude_retired_models', self::retired_models());
        if (isset($retired[$model]['to'])) {
            return raplsaich_retirement_in_effect((string) ($retired[$model]['retired'] ?? '')) ? $retired[$model] : null;
        }
        if (strpos($model, 'claude-3-') === 0) {
            if (strpos($model, 'haiku') !== false) {
                return ['to' => 'claude-haiku-4-5-20251001', 'retired' => ''];
            }
            if (strpos($model, 'opus') !== false) {
                return ['to' => 'claude-opus-5-5', 'retired' => ''];
            }
            return ['to' => 'claude-sonnet-5-5', 'retired' => ''];
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
        $retired = (array) apply_filters('raplsaich_claude_retired_models', self::retired_models());
        return raplsaich_scheduled_retirement($retired, $model);
    }

    /**
     * The model to actually send: the successor if $model is retired.
     */
    public static function resolve_model(string $model): string {
        $info = self::retirement($model);
        return $info ? $info['to'] : $model;
    }

    /**
     * Whether the model accepts temperature / top_p / top_k.
     *
     * An allow-list of the generations that do. Opus 4.7 and later, Sonnet 5,
     * Opus 5 / 5.5 and Fable reject them with a 400, and a model this plugin
     * has not seen yet is more likely to follow them than to go back. Matters
     * here because a Pro bot's model is typed in freely.
     */
    public static function accepts_sampling_params(string $model): bool {
        return (bool) preg_match('/^claude-(?:3-|haiku-4|sonnet-4|opus-4(?:-[0-6])?(?:-\d{8})?$)/', $model);
    }

    /**
     * Whether the model accepts a forced tool_choice ("any" / "tool").
     *
     * Same allow-list reasoning as accepts_sampling_params(): Opus 5.5 and
     * Fable 5.1 reject a forced choice with a 400, and later models are more
     * likely to follow them. Where it is refused, the [WEB SEARCH — MANDATORY]
     * system-prompt block does the steering on its own.
     */
    public static function accepts_forced_tool_choice(string $model): bool {
        return (bool) preg_match('/^claude-(?:3-|haiku-4|sonnet-4|opus-4|(?:sonnet|opus|fable)-5(?:-\d{8})?$)/', $model);
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
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'messages'   => $chat_messages,
        ];

        // Claude Opus 4.7 and later and Sonnet 5 reject any temperature with a
        // 400; send it only to the generations that take it.
        if (self::accepts_sampling_params($this->model)) {
            $body['temperature'] = (float) ($options['temperature'] ?? 0.7);
        } else {
            // Those same generations take an effort level instead. On Opus 5 /
            // 5.5, Sonnet 5 and Fable thinking is on by default and counts
            // toward max_tokens, so at the default 1000 a reply can run out
            // before any text. A site chatbot answers from supplied context
            // and rarely needs deep reasoning.
            $body['output_config'] = [
                'effort' => (string) apply_filters('raplsaich_claude_effort', 'low', $this->model),
            ];
        }

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
            if (!empty($options['force_web_search']) && self::accepts_forced_tool_choice($this->model)) {
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
            'claude-opus-5-5'             => 'Claude Opus 5.5 (' . __('Most powerful', 'rapls-ai-chatbot') . ')',
            'claude-sonnet-5-5'           => 'Claude Sonnet 5.5 (' . __('★ Recommended — fast and powerful', 'rapls-ai-chatbot') . ')',
            'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5 (' . __('★ Recommended — fastest, cheapest', 'rapls-ai-chatbot') . ')',
            // Previous generation (still served by Anthropic)
            'claude-sonnet-4-6'           => 'Claude Sonnet 4.6',
            'claude-opus-4-6'             => 'Claude Opus 4.6',
            'claude-opus-4-5-20251101'    => 'Claude Opus 4.5',
            // Claude Sonnet 4.5 retires 2026-11-30 and Opus 4.1 / Sonnet 4 have
            // retired; set_model() sends their successors instead.
        ];
    }

    /**
     * Get vision-capable models
     */
    public function get_vision_models(): array {
        return array_keys($this->get_available_models());
    }

    /**
     * Check if current model supports vision
     */
    public function supports_vision(): bool {
        // Every Claude 4 and later model reads images.
        return (bool) preg_match('/^claude-(?:opus|sonnet|haiku|fable|mythos)-(?:[4-9]|\d{2})/', $this->model);
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
