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

        // Batch message counts in a single query to avoid N+1
        $conv_ids = array_map(function ($c) { return (int) $c['id']; }, $conversations);
        $message_counts = [];
        if (!empty($conv_ids)) {
            global $wpdb;
            $msg_table = trim(wpaic_validated_table('aichat_messages'), '`');
            $placeholders = implode(',', array_fill(0, count($conv_ids), '%d'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT conversation_id, COUNT(*) AS cnt FROM `{$msg_table}` WHERE conversation_id IN ({$placeholders}) GROUP BY conversation_id",
                ...$conv_ids
            ));
            foreach ($rows as $row) {
                $message_counts[(int) $row->conversation_id] = (int) $row->cnt;
            }
        }

        $formatted = [];
        foreach ($conversations as $conv) {
            $cid = (int) $conv['id'];
            $formatted[] = [
                'id'            => $cid,
                'session_id'    => $conv['session_id'] ?? '',
                'status'        => $conv['status'] ?? 'active',
                'message_count' => $message_counts[$cid] ?? 0,
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
