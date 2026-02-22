<?php
/**
 * Lead model class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Lead {

    /**
     * Get table name
     */
    private static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'aichat_leads';
    }

    /**
     * Check if leads table exists
     */
    public static function table_exists(): bool {
        global $wpdb;
        $table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return $result === $table;
    }

    /**
     * Create leads table if not exists
     */
    public static function maybe_create_table(): void {
        if (self::table_exists()) {
            return;
        }

        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            company VARCHAR(255) DEFAULT NULL,
            custom_fields LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY email (email),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Create a new lead
     *
     * @param array $data Lead data
     * @return array|false Created lead data or false on failure
     */
    public static function create(array $data) {
        global $wpdb;

        // Ensure table exists
        self::maybe_create_table();

        $table = self::get_table_name();

        $insert_data = [
            'conversation_id' => absint($data['conversation_id'] ?? 0),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'company' => sanitize_text_field($data['company'] ?? ''),
            'custom_fields' => isset($data['custom_fields']) ? wp_json_encode($data['custom_fields']) : null,
            'created_at' => current_time('mysql'),
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $insert_data, ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        if ($result === false) {
            return false;
        }

        $insert_data['id'] = $wpdb->insert_id;
        if ($insert_data['custom_fields']) {
            $insert_data['custom_fields'] = json_decode($insert_data['custom_fields'], true);
        }

        return $insert_data;
    }

    /**
     * Get lead by ID
     *
     * @param int $id Lead ID
     * @return array|null Lead data or null if not found
     */
    public static function get_by_id(int $id) {
        global $wpdb;

        // Table name is safe - uses $wpdb->prefix with hardcoded suffix
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$lead) {
            return null;
        }

        if ($lead['custom_fields']) {
            $lead['custom_fields'] = json_decode($lead['custom_fields'], true);
        }

        return $lead;
    }

    /**
     * Get lead by conversation ID
     *
     * @param int $conversation_id Conversation ID
     * @return array|null Lead data or null if not found
     */
    public static function get_by_conversation(int $conversation_id) {
        // If table doesn't exist, return null
        if (!self::table_exists()) {
            return null;
        }

        global $wpdb;

        // Table name is safe - uses $wpdb->prefix with hardcoded suffix
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE conversation_id = %d", $conversation_id),
            ARRAY_A
        );

        if (!$lead) {
            return null;
        }

        if ($lead['custom_fields']) {
            $lead['custom_fields'] = json_decode($lead['custom_fields'], true);
        }

        return $lead;
    }

    /**
     * Get lead by email
     *
     * @param string $email Email address
     * @return array|null Lead data or null if not found
     */
    public static function get_by_email(string $email) {
        global $wpdb;

        // Table name is safe - uses $wpdb->prefix with hardcoded suffix
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s ORDER BY created_at DESC LIMIT 1", $email),
            ARRAY_A
        );

        if (!$lead) {
            return null;
        }

        if ($lead['custom_fields']) {
            $lead['custom_fields'] = json_decode($lead['custom_fields'], true);
        }

        return $lead;
    }

    /**
     * Get leads list with pagination
     */
    public static function get_list(array $args = []): array {
        global $wpdb;

        // Table name is safe - uses $wpdb->prefix with hardcoded suffix
        $table = self::get_table_name();

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $where_clauses = ['1=1'];
        $where_values = [];

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = '(name LIKE %s OR email LIKE %s OR company LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        // Date range filter
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Validate orderby
        $allowed_orderby = ['id', 'name', 'email', 'company', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build query - table name, orderby, and order are validated/sanitized above
        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $where_values[] = $args['per_page'];
        $where_values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results(
            $wpdb->prepare($query, $where_values),
            ARRAY_A
        );

        // Decode custom_fields
        foreach ($leads as &$lead) {
            if ($lead['custom_fields']) {
                $lead['custom_fields'] = json_decode($lead['custom_fields'], true);
            }
        }

        return $leads;
    }

    /**
     * Get total count of leads
     */
    public static function get_count(array $args = []): int {
        global $wpdb;

        // Table name is safe - uses $wpdb->prefix with hardcoded suffix
        $table = self::get_table_name();

        $where_clauses = ['1=1'];
        $where_values = [];

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = '(name LIKE %s OR email LIKE %s OR company LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        // Date range filter
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return (int) $wpdb->get_var($wpdb->prepare($query, $where_values));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get count of leads for a specific time period
     *
     * @param string $period 'today', 'week', or 'month'
     * @return int
     */
    public static function get_period_count($period) {
        global $wpdb;
        $table = self::get_table_name();

        // Use WP timezone instead of MySQL CURDATE()/NOW()
        $now = wp_date('Y-m-d H:i:s');

        switch ($period) {
            case 'today':
                $start = wp_date('Y-m-d 00:00:00');
                $end   = wp_date('Y-m-d 23:59:59');
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                return (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s", $start, $end)
                );
            case 'week':
                $start = wp_date('Y-m-d H:i:s', strtotime('-7 days'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                return (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $start)
                );
            case 'month':
                $start = wp_date('Y-m-d H:i:s', strtotime('-30 days'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                return (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $start)
                );
            default:
                return 0;
        }
    }

    /**
     * Delete a lead
     */
    public static function delete(int $id): bool {
        global $wpdb;

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    /**
     * Export leads for given filters
     */
    public static function export(array $filters = []): array {
        $args = array_merge($filters, ['per_page' => 10000, 'page' => 1]);
        return self::get_list($args);
    }

    /**
     * Format leads for CSV export
     */
    public static function format_for_csv(array $leads): array {
        $rows = [];

        // Header row
        $rows[] = [
            __('ID', 'rapls-ai-chatbot'),
            __('Name', 'rapls-ai-chatbot'),
            __('Email', 'rapls-ai-chatbot'),
            __('Phone', 'rapls-ai-chatbot'),
            __('Company', 'rapls-ai-chatbot'),
            __('Conversation ID', 'rapls-ai-chatbot'),
            __('Created At', 'rapls-ai-chatbot'),
        ];

        foreach ($leads as $lead) {
            $rows[] = [
                $lead['id'],
                $lead['name'],
                $lead['email'],
                $lead['phone'],
                $lead['company'],
                $lead['conversation_id'],
                $lead['created_at'],
            ];
        }

        return $rows;
    }
}
