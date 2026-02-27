<?php
/**
 * MCP Tool: list_conversations
 *
 * Lists chat conversations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_MCP_Tool_List_Conversations {

    /**
     * Register this tool with the registry.
     *
     * @param WPAIC_MCP_Tool_Registry $registry Tool registry.
     */
    public function register(WPAIC_MCP_Tool_Registry $registry): void {
        $registry->register('list_conversations', $this->get_schema(), [$this, 'execute']);
    }

    /**
     * Tool schema for tools/list.
     *
     * @return array
     */
    private function get_schema(): array {
        return [
            'name'        => 'list_conversations',
            'description' => 'List chat conversations with message counts. Returns recent conversations sorted by creation date.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Maximum number of conversations to return (default: 20).',
                        'default'     => 20,
                    ],
                    'status' => [
                        'type'        => 'string',
                        'description' => 'Filter by conversation status (e.g., "active", "ended").',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array $args Tool arguments.
     * @return array Conversation list.
     */
    public function execute(array $args): array {
        $limit  = absint($args['limit'] ?? 20);
        $status = sanitize_text_field($args['status'] ?? '');

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        $list_args = [
            'per_page' => $limit,
            'page'     => 1,
        ];

        if (!empty($status)) {
            $list_args['status'] = $status;
        }

        $conversations = WPAIC_Conversation::get_list($list_args);

        $formatted = [];
        foreach ($conversations as $conv) {
            $message_count = WPAIC_Message::get_count_by_conversation((int) $conv['id']);
            $formatted[] = [
                'id'            => (int) $conv['id'],
                'session_id'    => $conv['session_id'] ?? '',
                'status'        => $conv['status'] ?? 'active',
                'message_count' => $message_count,
                'page_url'      => $conv['page_url'] ?? '',
                'created_at'    => $conv['created_at'] ?? '',
            ];
        }

        return [
            'conversations' => $formatted,
            'total'         => count($formatted),
        ];
    }
}
