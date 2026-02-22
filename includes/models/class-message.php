<?php
/**
 * Message model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Message {

    /**
     * Table name
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aichat_messages';
    }

    /**
     * Create message
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $insert_data = [
            'conversation_id' => $data['conversation_id'],
            'role'            => $data['role'],
            'content'         => $data['content'],
            'tokens_used'     => $data['tokens_used'] ?? 0,
            'input_tokens'    => $data['input_tokens'] ?? 0,
            'output_tokens'   => $data['output_tokens'] ?? 0,
            'ai_provider'     => $data['ai_provider'] ?? null,
            'ai_model'        => $data['ai_model'] ?? null,
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $insert_data, ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s']);

        if ($result) {
            return self::get_by_id($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Get message by ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get messages by conversation ID
     */
    public static function get_by_conversation($conversation_id, $limit = 50) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC LIMIT %d",
            $conversation_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get latest messages for context
     */
    public static function get_context_messages($conversation_id, $limit = 10) {
        global $wpdb;
        $table = self::get_table_name();

        // Get latest messages (fetch in reverse order and reorder)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM {$table}
             WHERE conversation_id = %d AND role IN ('user', 'assistant')
             ORDER BY created_at DESC LIMIT %d",
            $conversation_id,
            $limit
        ), ARRAY_A);

        return array_reverse($messages);
    }

    /**
     * Get message count by conversation
     */
    public static function get_count_by_conversation($conversation_id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE conversation_id = %d",
            $conversation_id
        ));
    }

    /**
     * Get today's message count (for statistics)
     */
    public static function get_today_count() {
        global $wpdb;
        $table = self::get_table_name();

        // Use MySQL CURDATE() to match CURRENT_TIMESTAMP stored in created_at (same TZ)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()"
        );
    }

    /**
     * Get total token usage
     */
    public static function get_total_tokens($days = 30) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(tokens_used) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * Delete messages by conversation ID
     */
    public static function delete_by_conversation($conversation_id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete(
            $table,
            ['conversation_id' => $conversation_id],
            ['%d']
        );
    }

    /**
     * Delete all messages
     */
    public static function delete_all() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->query("TRUNCATE TABLE {$table}");
    }

    /**
     * Update message feedback (Pro feature)
     *
     * @param int $message_id Message ID
     * @param int $feedback 1 = positive, -1 = negative, 0 = neutral
     * @return bool
     */
    public static function update_feedback(int $message_id, int $feedback): bool {
        global $wpdb;
        $table = self::get_table_name();

        // Validate feedback value
        if (!in_array($feedback, [-1, 0, 1], true)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            ['feedback' => $feedback],
            ['id' => $message_id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get feedback statistics (Pro feature)
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public static function get_feedback_stats(int $days = 30): array {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(CASE WHEN feedback = 1 THEN 1 END) as positive,
                COUNT(CASE WHEN feedback = -1 THEN 1 END) as negative,
                COUNT(CASE WHEN feedback IS NOT NULL AND feedback != 0 THEN 1 END) as total
            FROM {$table}
            WHERE role = 'assistant'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A);

        $total = (int) ($stats['total'] ?? 0);
        $positive = (int) ($stats['positive'] ?? 0);
        $negative = (int) ($stats['negative'] ?? 0);

        return [
            'positive' => $positive,
            'negative' => $negative,
            'total' => $total,
            'satisfaction_rate' => $total > 0 ? round(($positive / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get messages with negative feedback (Pro feature)
     *
     * @param int $limit
     * @return array
     */
    public static function get_negative_feedback_messages(int $limit = 50): array {
        global $wpdb;
        $table = self::get_table_name();
        $conv_table = $wpdb->prefix . 'aichat_conversations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, c.page_url
            FROM {$table} m
            LEFT JOIN {$conv_table} c ON m.conversation_id = c.id
            WHERE m.feedback = -1 AND m.role = 'assistant'
            ORDER BY m.created_at DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get negative feedback Q&A pairs for learning (what to avoid)
     *
     * @param int $limit Number of pairs to retrieve
     * @return array Array of Q&A pairs with negative feedback
     */
    public static function get_negative_feedback_examples(int $limit = 3): array {
        global $wpdb;
        $table = self::get_table_name();

        // Get assistant messages with negative feedback and their preceding user messages
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $negative_messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.conversation_id, m.content as answer, m.created_at
            FROM {$table} m
            WHERE m.feedback = -1 AND m.role = 'assistant'
            ORDER BY m.created_at DESC
            LIMIT %d",
            $limit * 2
        ), ARRAY_A);

        $examples = [];
        foreach ($negative_messages as $msg) {
            // Get the user message that preceded this assistant message
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $user_msg = $wpdb->get_row($wpdb->prepare(
                "SELECT content FROM {$table}
                WHERE conversation_id = %d AND role = 'user' AND id < %d
                ORDER BY id DESC LIMIT 1",
                $msg['conversation_id'],
                $msg['id']
            ), ARRAY_A);

            if ($user_msg && !empty($user_msg['content'])) {
                $examples[] = [
                    'question' => mb_substr($user_msg['content'], 0, 200),
                    'answer'   => mb_substr($msg['answer'], 0, 300),
                ];

                if (count($examples) >= $limit) {
                    break;
                }
            }
        }

        return $examples;
    }

    /**
     * Get positive feedback Q&A pairs for learning
     *
     * @param int $limit Number of pairs to retrieve
     * @return array Array of Q&A pairs with positive feedback
     */
    public static function get_positive_feedback_examples(int $limit = 5): array {
        global $wpdb;
        $table = self::get_table_name();

        // Get assistant messages with positive feedback and their preceding user messages
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $positive_messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.conversation_id, m.content as answer, m.created_at
            FROM {$table} m
            WHERE m.feedback = 1 AND m.role = 'assistant'
            ORDER BY m.created_at DESC
            LIMIT %d",
            $limit * 2  // Get more to filter
        ), ARRAY_A);

        $examples = [];
        foreach ($positive_messages as $msg) {
            // Get the user message that preceded this assistant message
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $user_msg = $wpdb->get_row($wpdb->prepare(
                "SELECT content FROM {$table}
                WHERE conversation_id = %d AND role = 'user' AND id < %d
                ORDER BY id DESC LIMIT 1",
                $msg['conversation_id'],
                $msg['id']
            ), ARRAY_A);

            if ($user_msg && !empty($user_msg['content'])) {
                $examples[] = [
                    'question' => mb_substr($user_msg['content'], 0, 200),
                    'answer'   => mb_substr($msg['answer'], 0, 500),
                ];

                if (count($examples) >= $limit) {
                    break;
                }
            }
        }

        return $examples;
    }

    /**
     * Find a cached response for the given message + context hash
     *
     * @param string $cache_hash SHA-256 hash of user message + knowledge context
     * @param int    $ttl_days   Cache TTL in days
     * @return array|null Cached assistant message or null
     */
    public static function find_cached_response(string $cache_hash, int $ttl_days = 7) {
        global $wpdb;
        $table = self::get_table_name();

        // Find the most recent assistant message with this cache_hash,
        // positive or neutral feedback, and within TTL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE cache_hash = %s
              AND role = 'assistant'
              AND (feedback IS NULL OR feedback >= 0)
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY
              CASE WHEN feedback = 1 THEN 0 ELSE 1 END,
              created_at DESC
            LIMIT 1",
            $cache_hash,
            $ttl_days
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Store cache hash on a message
     *
     * @param int    $message_id Message ID
     * @param string $cache_hash SHA-256 hash
     * @return bool
     */
    public static function store_cache_hash(int $message_id, string $cache_hash): bool {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->update(
            $table,
            ['cache_hash' => $cache_hash],
            ['id' => $message_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Build a cache hash from user message and context
     *
     * @param string $message User message
     * @param string $context Knowledge/RAG context
     * @return string SHA-256 hash
     */
    public static function build_cache_hash(string $message, string $context = ''): string {
        // Normalize: lowercase, trim whitespace
        $normalized = mb_strtolower(trim($message)) . '||' . trim($context);
        return hash('sha256', $normalized);
    }

    /**
     * Get cache statistics
     *
     * @param int $days Number of days to look back
     * @return array Cache statistics
     */
    public static function get_cache_stats(int $days = 30): array {
        global $wpdb;
        $table = self::get_table_name();

        // Count total assistant messages with cache_hash (cacheable responses)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_cached = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE role = 'assistant'
              AND cache_hash IS NOT NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Count cache hits (messages flagged as cache_hit)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $cache_hits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE role = 'assistant'
              AND cache_hit = 1
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Total AI calls (non-cache-hit assistant messages)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_ai_calls = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE role = 'assistant'
              AND (cache_hit IS NULL OR cache_hit = 0)
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Estimated saved tokens from cache hits
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $saved_tokens = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(tokens_used), 0) FROM {$table}
            WHERE role = 'assistant'
              AND cache_hit = 1
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        $total_requests = $cache_hits + $total_ai_calls;
        $hit_rate = $total_requests > 0 ? round(($cache_hits / $total_requests) * 100, 1) : 0;

        return [
            'cache_hits'     => $cache_hits,
            'total_ai_calls' => $total_ai_calls,
            'total_requests' => $total_requests,
            'hit_rate'       => $hit_rate,
            'saved_tokens'   => $saved_tokens,
            'total_cached'   => $total_cached,
        ];
    }

    /**
     * Clear all cache hashes (invalidate cache)
     *
     * @return int Number of rows affected
     */
    public static function clear_cache(): int {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query("UPDATE {$table} SET cache_hash = NULL WHERE cache_hash IS NOT NULL");

        return $wpdb->rows_affected;
    }
}
