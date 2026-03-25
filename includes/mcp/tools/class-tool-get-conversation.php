<?php
/**
 * MCP Tool: get_conversation
 *
 * Retrieves a conversation with its messages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_MCP_Tool_Get_Conversation {

    /**
     * Register this tool with the registry.
     *
     * @param RAPLSAICH_MCP_Tool_Registry $registry Tool registry.
     */
    public function register(RAPLSAICH_MCP_Tool_Registry $registry): void {
        $registry->register('get_conversation', $this->get_schema(), [$this, 'execute']);
    }

    /**
     * Tool schema for tools/list.
     *
     * @return array
     */
    private function get_schema(): array {
        return [
            'name'        => 'get_conversation',
            'description' => 'Get a conversation with all its messages. Returns the conversation details and message history.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'conversation_id' => [
                        'type'        => 'integer',
                        'description' => 'The conversation ID to retrieve.',
                    ],
                ],
                'required' => ['conversation_id'],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array $args Tool arguments.
     * @return array Conversation with messages.
     */
    public function execute(array $args): array {
        $conversation_id = absint($args['conversation_id'] ?? 0);

        if ($conversation_id < 1) {
            return ['error' => __('conversation_id is required.', 'rapls-ai-chatbot')];
        }

        $conversation = RAPLSAICH_Conversation::get_by_id($conversation_id);

        if (!$conversation) {
            return ['error' => __('Conversation not found.', 'rapls-ai-chatbot')];
        }

        $messages = RAPLSAICH_Message::get_by_conversation($conversation_id, 200);

        $formatted_messages = [];
        foreach ($messages as $msg) {
            $entry = [
                'role'       => $msg['role'] ?? '',
                'content'    => $msg['content'] ?? '',
                'created_at' => $msg['created_at'] ?? '',
            ];

            if (!empty($msg['tokens_used'])) {
                $entry['tokens_used'] = (int) $msg['tokens_used'];
            }

            if (!empty($msg['ai_provider'])) {
                $entry['provider'] = $msg['ai_provider'];
            }

            if (!empty($msg['ai_model'])) {
                $entry['model'] = $msg['ai_model'];
            }

            if (!empty($msg['feedback'])) {
                $entry['feedback'] = $msg['feedback'];
            }

            $formatted_messages[] = $entry;
        }

        // Remove sensitive fields from conversation
        unset($conversation['visitor_ip']);

        return [
            'conversation' => [
                'id'         => (int) $conversation['id'],
                'session_id' => $conversation['session_id'] ?? '',
                'status'     => $conversation['status'] ?? 'active',
                'page_url'   => $conversation['page_url'] ?? '',
                'created_at' => $conversation['created_at'] ?? '',
            ],
            'messages'     => $formatted_messages,
            'total'        => count($formatted_messages),
        ];
    }
}
