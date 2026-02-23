<?php
/**
 * Conversation model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Conversation {

    /**
     * Table name
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aichat_conversations';
    }
    // Table name is always $wpdb->prefix + hardcoded suffix — never user input.

    /**
     * Get or create conversation by session ID
     */
    public static function get_or_create($session_id, $data = []) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE session_id = %s AND status = 'active'",
            $session_id
        ), ARRAY_A);

        if ($conversation) {
            return $conversation;
        }

        // Create new
        // Use visitor_ip from $data if provided (caller should pass get_client_ip() result),
        // otherwise fall back to REMOTE_ADDR
        $raw_ip = $data['visitor_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $user_id = get_current_user_id();

        $insert_data = [
            'session_id' => $session_id,
            'visitor_ip' => self::hash_ip($raw_ip, $user_agent),
            'page_url'   => isset($data['page_url']) ? esc_url_raw($data['page_url']) : '',
            'status'     => 'active',
        ];
        $formats = ['%s', '%s', '%s', '%s'];

        // Only include user_id when logged in; omitting it lets MySQL store actual NULL
        // (avoids 0 or '' for anonymous visitors, which breaks IS NULL queries)
        if ($user_id) {
            $insert_data['user_id'] = $user_id;
            $formats[] = '%d';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table, $insert_data, $formats);
        wpaic_log_db_error('Conversation::create');

        return self::get_by_id($wpdb->insert_id);
    }

    /**
     * Get conversation by ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get conversation by session ID
     */
    public static function get_by_session($session_id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE session_id = %s AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $session_id
        ), ARRAY_A);
    }

    /**
     * Get conversation list
     */
    public static function get_list($args = []) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'per_page'  => 20,
            'page'      => 1,
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = '1=1';
        $params = [];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['date_from'])) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $params[] = $args['per_page'];
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, WHERE and ORDER BY are safe internal values
        $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Get total conversation count
     */
    public static function get_count($status = '') {
        global $wpdb;
        $table = self::get_table_name();

        if ($status) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
                $status
            ));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    /**
     * Get count of conversations created today
     */
    public static function get_today_count() {
        global $wpdb;
        $table = self::get_table_name();

        // Use MySQL CURDATE() to match CURRENT_TIMESTAMP stored in created_at (same TZ)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table}` WHERE DATE(created_at) = CURDATE()"
        );
    }

    /**
     * Update status
     */
    public static function update_status($id, $status) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Delete conversation (including related messages)
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();

        // Delete related messages first
        WPAIC_Message::delete_by_conversation($id);

        // Delete conversation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );
    }

    /**
     * Delete multiple conversations
     */
    public static function delete_multiple($ids) {
        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if (self::delete(absint($id))) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Delete all conversations
     */
    public static function delete_all() {
        global $wpdb;
        $table = self::get_table_name();

        // Delete all messages first
        WPAIC_Message::delete_all();

        // Delete all conversations
        // Table name is $wpdb->prefix + hardcoded suffix — never user input. Backtick-only for identifiers.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query("TRUNCATE TABLE `{$table}`");
        if ($result === false) {
            // Fallback: TRUNCATE may fail due to DB permissions or configuration
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query("DELETE FROM `{$table}`");
        }
        return $result;
    }

    /**
     * Hash IP + User-Agent into a privacy-safe fingerprint.
     * Stored in the `visitor_ip` column (column name is legacy; value is NOT a raw IP).
     */
    private static function hash_ip($ip, $user_agent = '') {
        if (empty($ip)) {
            return '';
        }
        return hash('sha256', $ip . $user_agent . wp_salt());
    }

    /**
     * Generate new session ID
     */
    public static function generate_session_id() {
        return wp_generate_uuid4();
    }

    /**
     * Mark a conversation as converted
     *
     * @param string $session_id Session ID
     * @param string $goal       Conversion goal name
     * @return bool
     */
    public static function mark_converted(string $session_id, string $goal = ''): bool {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE `{$table}` SET converted_at = NOW(), conversion_goal = %s WHERE session_id = %s AND converted_at IS NULL",
            $goal,
            $session_id
        ));

        return $result !== false;
    }

    /**
     * Get conversion statistics
     *
     * @param int $days Number of days to look back
     * @return array Conversion stats
     */
    public static function get_conversion_stats(int $days = 30): array {
        global $wpdb;
        $table = self::get_table_name();

        // Total conversations in period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Converted conversations
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $converted = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE converted_at IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Conversion rate
        $rate = $total > 0 ? round(($converted / $total) * 100, 1) : 0;

        // By goal
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $by_goal = $wpdb->get_results($wpdb->prepare(
            "SELECT conversion_goal, COUNT(*) as count
            FROM `{$table}`
            WHERE converted_at IS NOT NULL AND conversion_goal != '' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY conversion_goal
            ORDER BY count DESC",
            $days
        ), ARRAY_A);

        // Daily trend
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(converted_at) as date, COUNT(*) as count
            FROM `{$table}`
            WHERE converted_at IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(converted_at)
            ORDER BY date",
            $days
        ), ARRAY_A);

        return [
            'total_conversations' => $total,
            'converted'           => $converted,
            'rate'                => $rate,
            'by_goal'             => $by_goal ?: [],
            'daily'               => $daily ?: [],
        ];
    }

    /**
     * Export conversations for given filters
     */
    public static function export(array $filters = []): array {
        $args = array_merge($filters, ['per_page' => 10000, 'page' => 1]);
        return self::get_list($args);
    }

    /**
     * Format conversations for CSV export
     */
    public static function format_for_csv(array $conversations): array {
        $rows = [];

        $rows[] = [
            __('ID', 'rapls-ai-chatbot'),
            __('Session ID', 'rapls-ai-chatbot'),
            __('Status', 'rapls-ai-chatbot'),
            __('Messages', 'rapls-ai-chatbot'),
            __('Converted', 'rapls-ai-chatbot'),
            __('Page URL', 'rapls-ai-chatbot'),
            __('Created At', 'rapls-ai-chatbot'),
        ];

        foreach ($conversations as $c) {
            $rows[] = [
                $c['id'] ?? '',
                $c['session_id'] ?? '',
                $c['status'] ?? '',
                $c['message_count'] ?? '',
                !empty($c['is_converted']) ? 'Yes' : 'No',
                self::csv_safe_cell($c['page_url'] ?? ''),
                $c['created_at'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Sanitize a cell value to prevent CSV injection.
     * Prefixes values starting with =, +, -, @ with a single quote.
     */
    private static function csv_safe_cell($value): string {
        $s = (string) $value;
        if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
            return "'" . $s;
        }
        return $s;
    }
}
