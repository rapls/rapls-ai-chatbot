<?php
/**
 * Lead model class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Lead {

    /**
     * Table name — whitelist-validated via raplsaich_validated_table().
     */
    private static function get_table_name(): string {
        return trim(raplsaich_validated_table('raplsaich_leads'), '`');
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

        $sql = "CREATE TABLE `{$table}` (
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
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $insert_data, ['%d', '%s', '%s', '%s', '%s', '%s']);
        raplsaich_log_db_error('Lead::create');

        if ($result === false) {
            return false;
        }

        // Re-fetch the full row to include DB-generated columns (created_at)
        $lead = self::get_by_id((int) $wpdb->insert_id);
        if ($lead) {
            return $lead;
        }

        // Fallback if re-fetch fails
        $insert_data['id'] = $wpdb->insert_id;
        $insert_data['created_at'] = current_time('mysql');
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
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id),
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
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE conversation_id = %d", $conversation_id),
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
        $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $where_values[] = $args['per_page'];
        $where_values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results(
            $wpdb->prepare($query, $where_values), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        // Decode custom_fields
        foreach ($leads as &$lead) {
            if ($lead['custom_fields']) {
                $lead['custom_fields'] = json_decode($lead['custom_fields'], true);
            }
        }
        unset($lead);

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

        $query = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";

        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return (int) $wpdb->get_var($wpdb->prepare($query, $where_values));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($query);
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
        $all = [];
        $page = 1;
        $per_page = 1000;
        $max_pages = 1000;
        do {
            $args = array_merge($filters, ['per_page' => $per_page, 'page' => $page]);
            $batch = self::get_list($args);
            $all = array_merge($all, $batch);
            $page++;
        } while (count($batch) === $per_page && $page <= $max_pages);
        return $all;
    }

    /**
     * Format leads for CSV export
     */
    public static function format_for_csv(array $leads): array {
        $rows = [];

        // Collect all custom field keys across leads
        $cf_keys = [];
        foreach ($leads as $lead) {
            if (!empty($lead['custom_fields']) && is_array($lead['custom_fields'])) {
                foreach (array_keys($lead['custom_fields']) as $k) {
                    if (!in_array($k, $cf_keys, true)) {
                        $cf_keys[] = $k;
                    }
                }
            }
        }

        // Header row
        $header = [
            __('ID', 'rapls-ai-chatbot'),
            __('Name', 'rapls-ai-chatbot'),
            __('Email', 'rapls-ai-chatbot'),
            __('Phone', 'rapls-ai-chatbot'),
            __('Company', 'rapls-ai-chatbot'),
            __('Conversation ID', 'rapls-ai-chatbot'),
            __('Created At', 'rapls-ai-chatbot'),
        ];
        foreach ($cf_keys as $cfk) {
            $header[] = self::csv_safe_cell($cfk);
        }
        $rows[] = $header;

        foreach ($leads as $lead) {
            $row = [
                $lead['id'],
                self::csv_safe_cell($lead['name']),
                self::csv_safe_cell($lead['email']),
                self::csv_safe_cell($lead['phone']),
                self::csv_safe_cell($lead['company']),
                $lead['conversation_id'],
                $lead['created_at'],
            ];
            $cf_data = (!empty($lead['custom_fields']) && is_array($lead['custom_fields'])) ? $lead['custom_fields'] : [];
            foreach ($cf_keys as $cfk) {
                $row[] = self::csv_safe_cell($cf_data[$cfk] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Sanitize a cell value to prevent CSV injection.
     */
    private static function csv_safe_cell($value): string {
        $s = str_replace("\r\n", "\n", (string) $value);
        // Check for formula chars after stripping leading whitespace (Excel ignores leading spaces)
        $trimmed = ltrim($s);
        if ($trimmed !== '' && preg_match('/^[=+\-@\t]/', $trimmed)) {
            return "'" . $s;
        }
        return $s;
    }
}
