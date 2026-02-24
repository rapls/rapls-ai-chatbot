<?php
/**
 * Plugin uninstall handler
 */

// Prevent direct access outside WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up data for a single site.
 *
 * Idempotent: only performs DB deletes (options, transients, tables).
 * Do NOT add external side effects (file I/O, remote API calls) here —
 * this function may be re-run after a partial failure.
 */
function wpaic_uninstall_site() {
    global $wpdb;

    // Check if user opted to delete data on uninstall
    $settings = get_option('wpaic_settings', []);
    $delete_data = !empty($settings['delete_data_on_uninstall']);

    // Always clear transients (temporary cache, safe to remove)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_wpaic_') . '%'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_wpaic_') . '%'
        )
    );

    // Always clear diagnostic counters (wpaic_diag_*).
    // These are lightweight, auto-regenerated runtime metrics — not user data.
    // Cleared regardless of delete_data_on_uninstall setting.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('wpaic_diag_') . '%'
        )
    );

    // Always clear scheduled hooks
    wp_clear_scheduled_hook('wpaic_crawl_site');
    wp_clear_scheduled_hook('wpaic_cleanup_old_conversations');
    wp_clear_scheduled_hook('wpaic_monthly_report');

    // Only delete settings, options, and database tables if user opted in
    if ($delete_data) {
        // Delete options
        delete_option('wpaic_settings');
        delete_option('wpaic_version');
        delete_option('wpaic_db_version');
        delete_option('wpaic_last_crawl');
        delete_option('wpaic_last_crawl_results');
        delete_option('wpaic_session_version');
        delete_option('wpaic_pro_active');
        delete_option('wpaic_pro_version');
        delete_option('wpaic_pro_license');
        delete_option('wpaic_pro_license_last_valid');
        delete_option('wpaic_pro_license_type');
        delete_option('wpaic_pro_license_revoked');

        // Delete database tables
        $tables = [
            $wpdb->prefix . 'aichat_conversations',
            $wpdb->prefix . 'aichat_messages',
            $wpdb->prefix . 'aichat_index',
            $wpdb->prefix . 'aichat_knowledge',
            $wpdb->prefix . 'aichat_leads',
            $wpdb->prefix . 'aichat_user_context',
            $wpdb->prefix . 'aichat_audit_log',
        ];

        foreach ($tables as $table) {
            // Table name is safe: $wpdb->prefix (WordPress-controlled) + hardcoded suffix.
            // Backtick-quoted as identifier; esc_sql() is not appropriate for identifiers.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}

// Multisite: auto-select snapshot (<10k sites) or batched (>=10k) mode.
// Each site operation is idempotent (safe to re-run if interrupted).
// try/finally ensures restore_current_blog() runs even on exception.
if (is_multisite()) {
    $site_count = (int) get_sites(['count' => true]);
    // Threshold filterable for memory-constrained hosts (default 10000).
    // Clamped to 100–100000; falls back to batch if count=0 (query failure).
    $threshold = (int) apply_filters('wpaic_uninstall_snapshot_threshold', 10000);
    $threshold = max(100, min($threshold, 100000));
    if ($site_count > 0 && $site_count < $threshold) {
        // Snapshot: load all IDs at once (~4 bytes/site + zval overhead).
        $all_ids = get_sites([
            'fields'  => 'ids',
            'number'  => 0,
            'orderby' => 'id',
            'order'   => 'ASC',
        ]);
        foreach ($all_ids as $site_id) {
            switch_to_blog((int) $site_id);
            try {
                wpaic_uninstall_site();
            } finally {
                restore_current_blog();
            }
        }
    } else {
        // Batched: for large networks, process in chunks of 100.
        $offset = 0;
        $batch  = 100;
        do {
            $sites = get_sites([
                'fields'  => 'ids',
                'number'  => $batch,
                'offset'  => $offset,
                'orderby' => 'id',
                'order'   => 'ASC',
            ]);
            foreach ($sites as $site_id) {
                switch_to_blog((int) $site_id);
                try {
                    wpaic_uninstall_site();
                } finally {
                    restore_current_blog();
                }
            }
            $offset += $batch;
        } while (count($sites) === $batch);
    }
} else {
    wpaic_uninstall_site();
}
