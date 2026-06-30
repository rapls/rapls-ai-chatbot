<?php
/**
 * Usage Store — storage layer for ⑤ usage metering.
 *
 * Server-side only. All counters live here, in the {prefix}raplsaich_usage
 * table; the client is never trusted for usage figures.
 *
 * Race safety: increments use a single atomic
 * `INSERT … ON DUPLICATE KEY UPDATE … = col + VALUES(col)` statement, so
 * concurrent requests cannot double-count or lose increments. The UNIQUE key
 * (actor_type, actor_key, window_type, window_start) is what makes this atomic.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Usage_Store {

    /**
     * Truncate "now" to the start of the given window.
     *
     * @param string $window_type 'hour' | 'day' | 'month'
     * @return string MySQL DATETIME of the window start (site timezone).
     */
    public static function window_start(string $window_type): string {
        switch ($window_type) {
            case 'hour':
                return wp_date('Y-m-d H:00:00');
            case 'month':
                return wp_date('Y-m-01 00:00:00');
            case 'day':
            default:
                return wp_date('Y-m-d 00:00:00');
        }
    }

    /**
     * Atomically add usage for an actor in a window. Returns false on failure.
     */
    public static function add(string $actor_type, string $actor_key, string $window_type, int $requests, int $tokens, int $credits = 0): bool {
        $table = raplsaich_require_table('raplsaich_usage', 'usage_store_add');
        if ($table === '') {
            return false;
        }
        $window_start = self::window_start($window_type);

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (actor_type, actor_key, window_type, window_start, requests, tokens, credits_used, updated_at)
             VALUES (%s, %s, %s, %s, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE
                requests = requests + VALUES(requests),
                tokens = tokens + VALUES(tokens),
                credits_used = credits_used + VALUES(credits_used),
                updated_at = VALUES(updated_at)",
            $actor_type,
            $actor_key,
            $window_type,
            $window_start,
            max(0, $requests),
            max(0, $tokens),
            max(0, $credits),
            current_time('mysql')
        ));
        return $result !== false;
    }

    /**
     * Current usage row for an actor in the active window.
     *
     * @return array{requests:int,tokens:int,credits_used:int}
     */
    public static function get(string $actor_type, string $actor_key, string $window_type): array {
        $zero = ['requests' => 0, 'tokens' => 0, 'credits_used' => 0];
        $table = raplsaich_validated_table('raplsaich_usage');
        if ($table === '') {
            return $zero;
        }
        $window_start = self::window_start($window_type);

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT requests, tokens, credits_used FROM {$table}
             WHERE actor_type = %s AND actor_key = %s AND window_type = %s AND window_start = %s",
            $actor_type,
            $actor_key,
            $window_type,
            $window_start
        ), ARRAY_A);

        if (!is_array($row)) {
            return $zero;
        }
        return [
            'requests'     => (int) $row['requests'],
            'tokens'       => (int) $row['tokens'],
            'credits_used' => (int) $row['credits_used'],
        ];
    }

    /**
     * Delete usage rows older than $days (guest TTL / housekeeping).
     * Returns the number of rows removed, or 0.
     */
    public static function cleanup(int $days): int {
        if ($days < 1) {
            return 0;
        }
        $table = raplsaich_validated_table('raplsaich_usage');
        if ($table === '') {
            return 0;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        return is_numeric($deleted) ? (int) $deleted : 0;
    }
}
