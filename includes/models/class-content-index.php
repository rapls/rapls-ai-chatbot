<?php
/**
 * Content index model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Content_Index {

    /**
     * Table name
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aichat_index';
    }

    /**
     * Create index
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $insert_data = [
            'post_id'      => $data['post_id'],
            'post_type'    => $data['post_type'],
            'title'        => $data['title'],
            'content'      => $data['content'],
            'content_hash' => $data['content_hash'],
            'chunk_index'  => $data['chunk_index'] ?? 0,
            'url'          => $data['url'],
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert($table, $insert_data, ['%d', '%s', '%s', '%s', '%s', '%d', '%s']);
    }

    /**
     * Get index by post ID
     */
    public static function get_by_post_id($post_id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d ORDER BY chunk_index ASC",
            $post_id
        ), ARRAY_A);
    }

    /**
     * Delete index by post ID
     */
    public static function delete_by_post_id($post_id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete($table, ['post_id' => $post_id], ['%d']);
    }

    /**
     * Check if content hash exists
     */
    public static function hash_exists($post_id, $content_hash) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND content_hash = %s",
            $post_id,
            $content_hash
        ));

        return $exists > 0;
    }

    /**
     * Get list of indexed post IDs
     */
    public static function get_indexed_post_ids() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_col("SELECT DISTINCT post_id FROM {$table}");
    }

    /**
     * Get total index count
     */
    public static function get_count() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$table}");
    }

    /**
     * Delete all indexes
     */
    public static function truncate() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query("TRUNCATE TABLE " . esc_sql($table));
        if ($result === false) {
            // Fallback: TRUNCATE may fail due to DB permissions or configuration
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query("DELETE FROM " . esc_sql($table));
        }
        return $result;
    }

    /**
     * Get index list (for admin)
     */
    public static function get_list($args = []) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'per_page'  => 20,
            'page'      => 1,
            'post_type' => '',
            'orderby'   => 'indexed_at',
            'order'     => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = 'chunk_index = 0'; // Only first chunk of each post
        $params = [];

        if (!empty($args['post_type'])) {
            $where .= ' AND post_type = %s';
            $params[] = $args['post_type'];
        }

        // Validate orderby
        $allowed_orderby = ['title', 'post_type', 'indexed_at'];
        $orderby_col = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'indexed_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $params[] = $args['per_page'];
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, WHERE, and ORDER BY are safe internal values
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby_col} {$order} LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Get count of indexed items grouped by post type
     */
    public static function get_post_type_counts() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            "SELECT post_type, COUNT(DISTINCT post_id) as count FROM {$table} GROUP BY post_type ORDER BY count DESC",
            ARRAY_A
        );

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['post_type']] = (int) $row['count'];
        }
        return $counts;
    }
}
