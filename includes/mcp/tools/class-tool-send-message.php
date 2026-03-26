<?php
/**
 * MCP Tool: send_message
 *
 * Sends a message to the AI provider and returns the response.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_MCP_Tool_Send_Message {

    /**
     * Register this tool with the registry.
     *
     * @param RAPLSAICH_MCP_Tool_Registry $registry Tool registry.
     */
    public function register(RAPLSAICH_MCP_Tool_Registry $registry): void {
        $registry->register('send_message', $this->get_schema(), [$this, 'execute']);
    }

    /**
     * Tool schema for tools/list.
     *
     * @return array
     */
    private function get_schema(): array {
        return [
            'name'        => 'send_message',
            'description' => 'Send a message to the AI chatbot and get a response. Uses the configured AI provider and includes knowledge base context.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'message' => [
                        'type'        => 'string',
                        'description' => 'The message to send to the AI.',
                    ],
                    'session_id' => [
                        'type'        => 'string',
                        'description' => 'Optional session ID for conversation continuity. A new session is created if not provided.',
                    ],
                ],
                'required' => ['message'],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array $args Tool arguments.
     * @return array AI response.
     */
    public function execute(array $args): array {
        $message    = sanitize_textarea_field($args['message'] ?? '');
        $session_id = sanitize_text_field($args['session_id'] ?? '');

        if (empty($message)) {
            return ['error' => __('Message is required.', 'rapls-ai-chatbot')];
        }

        $max_length = (int) apply_filters('raplsaich_max_message_length', 8000);
        $msg_length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if ($msg_length > $max_length) {
            return ['error' => __('Message exceeds maximum length.', 'rapls-ai-chatbot')];
        }

        $settings = get_option('raplsaich_settings', []);

        // Pre-check hook (Pro: banned words, message limit, budget checks)
        $pre_check_error = apply_filters('raplsaich_mcp_pre_send_check', null, $message);
        if (is_array($pre_check_error) && !empty($pre_check_error['error'])) {
            return $pre_check_error;
        }

        // Create or get session
        if (empty($session_id)) {
            $session_id = RAPLSAICH_Conversation::generate_session_id();
        }

        $conversation = RAPLSAICH_Conversation::get_or_create($session_id, [
            'page_url' => 'mcp',
        ]);

        if (!$conversation) {
            return ['error' => __('Failed to create conversation.', 'rapls-ai-chatbot')];
        }

        $conversation_id = (int) $conversation['id'];

        // Get conversation history for context
        $history_count = absint($settings['message_history_count'] ?? 10);
        $context_messages = RAPLSAICH_Message::get_context_messages($conversation_id, $history_count);

        // Build AI messages array
        $ai_messages = [];

        // System prompt
        $system_prompt = $settings['system_prompt'] ?? '';
        if (!empty($system_prompt)) {
            $system_prompt = apply_filters('raplsaich_system_prompt', $system_prompt);
            $ai_messages[] = ['role' => 'system', 'content' => $system_prompt];
        }

        // Search for related content (RAG)
        $sources = [];
        $search_engine = new RAPLSAICH_Search_Engine();
        $related_content = $search_engine->search($message, $settings['crawler_max_results'] ?? 3);

        if (!empty($related_content)) {
            $context = $search_engine->build_context($related_content, 50000, $message);
            $context = apply_filters('raplsaich_context', $context, $message);

            if (!empty($context)) {
                $ai_messages[] = [
                    'role'    => 'system',
                    'content' => __('Use the following reference information to answer the question:', 'rapls-ai-chatbot') . "\n\n" . $context,
                ];
            }

            // Collect sources
            foreach ($related_content as $item) {
                if (!empty($item['url'])) {
                    $sources[] = $item['url'];
                }
            }
        }

        // Add conversation history
        foreach ($context_messages as $ctx_msg) {
            $ai_messages[] = $ctx_msg;
        }

        // Add current message
        $ai_messages[] = ['role' => 'user', 'content' => $message];

        // Get AI provider
        $provider = $this->get_ai_provider($settings);

        // Send to AI
        $options = [];
        if (!empty($settings['max_tokens'])) {
            $options['max_tokens'] = (int) $settings['max_tokens'];
        }
        if (isset($settings['temperature'])) {
            $options['temperature'] = (float) $settings['temperature'];
        }

        try {
            $ai_response = $provider->send_message($ai_messages, $options);
        } catch (\Throwable $e) {
            return ['error' => __('AI provider error:', 'rapls-ai-chatbot') . ' ' . $e->getMessage()];
        }

        $response_content = $ai_response['content'] ?? '';
        $response_content = apply_filters('raplsaich_ai_response', $response_content, $message);

        // Save messages to conversation
        $save_history = !empty($settings['save_history']);
        if ($save_history) {
            RAPLSAICH_Message::create([
                'conversation_id' => $conversation_id,
                'role'            => 'user',
                'content'         => $message,
            ]);

            RAPLSAICH_Message::create([
                'conversation_id' => $conversation_id,
                'role'            => 'assistant',
                'content'         => $response_content,
                'tokens_used'     => $ai_response['tokens_used'] ?? 0,
                'input_tokens'    => $ai_response['input_tokens'] ?? 0,
                'output_tokens'   => $ai_response['output_tokens'] ?? 0,
                'ai_provider'     => $provider->get_name(),
                'ai_model'        => $ai_response['model'] ?? '',
            ]);
        }

        return [
            'content'     => $response_content,
            'session_id'  => $session_id,
            'tokens_used' => $ai_response['tokens_used'] ?? 0,
            'model'       => $ai_response['model'] ?? '',
            'provider'    => $provider->get_name(),
            'sources'     => array_unique($sources),
        ];
    }

    /**
     * Get AI provider instance — delegates to global helper.
     *
     * @param array $settings Plugin settings.
     * @return RAPLSAICH_AI_Provider_Interface
     */
    private function get_ai_provider(array $settings): RAPLSAICH_AI_Provider_Interface {
        return raplsaich_create_ai_provider($settings);
    }
}
