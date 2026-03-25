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
 * MUST: Do NOT swallow exceptions/errors silently — let failures propagate
 * so the completed_at marker accurately reflects success.
 */
function raplsaich_uninstall_site() {
    // MUST: If adding try/catch here, always rethrow — never swallow.
    // Silent catch breaks completed_at accuracy (see PHPDoc above).
    global $wpdb;

    // Check if user opted to delete data on uninstall.
    // Multisite: reads each blog's own raplsaich_settings (switch_to_blog sets the context).
    // There is no network-level override — each site controls its own data deletion.
    // Future: if bulk control is needed, add raplsaich_network_delete_data (site_option).
    // Policy: site_option true → force delete on all blogs. Unset → fall back to per-blog setting.
    $settings = get_option('raplsaich_settings', []);
    $delete_data = !empty($settings['delete_data_on_uninstall']);

    // Always clear transients (temporary cache, safe to remove)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_raplsaich_') . '%'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_raplsaich_') . '%'
        )
    );

    // Always clear rate-limit fallback option (single array, no LIKE needed)
    delete_option('raplsaich_rl_fallback');

    // Always clear diagnostic counters (raplsaich_diag_*).
    // These are lightweight, auto-regenerated runtime metrics — not user data.
    // Cleared regardless of delete_data_on_uninstall setting.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('raplsaich_diag_') . '%'
        )
    );

    // Always clear scheduled hooks
    wp_clear_scheduled_hook('raplsaich_crawl_site');
    wp_clear_scheduled_hook('raplsaich_cleanup_old_conversations');
    wp_clear_scheduled_hook('raplsaich_monthly_report');

    // Only delete settings, options, and database tables if user opted in
    if ($delete_data) {
        // Delete options
        delete_option('raplsaich_settings');
        delete_option('raplsaich_version');
        delete_option('raplsaich_db_version');
        delete_option('raplsaich_last_crawl');
        delete_option('raplsaich_last_crawl_results');
        delete_option('raplsaich_session_version');
        delete_option('raplsaich_pro_active');
        delete_option('raplsaich_pro_version');
        delete_option('raplsaich_pro_license');
        delete_option('raplsaich_pro_license_last_valid');
        delete_option('raplsaich_pro_license_type');
        delete_option('raplsaich_pro_license_revoked');
        delete_option('raplsaich_crawl_progress');
        delete_option('raplsaich_knowledge_schema_version');
        delete_option('raplsaich_nohist_msg_counts');

        // Delete database tables.
        // raplsaich_table_suffixes() is defined in rapls-ai-chatbot.php which is NOT
        // loaded during uninstall. Define it here so RAPLSAICH_Activator can use it.
        if (!function_exists('raplsaich_table_suffixes')) {
            function raplsaich_table_suffixes(): array {
                return [
                    'raplsaich_conversations',
                    'raplsaich_messages',
                    'raplsaich_index',
                    'raplsaich_knowledge',
                    'raplsaich_leads',
                    'raplsaich_user_context',
                    'raplsaich_audit_log',
                    'raplsaich_knowledge_versions',
                ];
            }
        }
        if (!function_exists('raplsaich_validated_table')) {
            function raplsaich_validated_table(string $suffix): string {
                global $wpdb;
                if (!in_array($suffix, raplsaich_table_suffixes(), true)) {
                    return '';
                }
                return '`' . $wpdb->prefix . $suffix . '`';
            }
        }
        require_once __DIR__ . '/includes/class-activator.php';
        $allowed_suffixes = RAPLSAICH_Activator::get_table_suffixes();

        foreach ($allowed_suffixes as $suffix) {
            $table = $wpdb->prefix . $suffix;
            // Table name is safe: $wpdb->prefix (WordPress-controlled) + hardcoded suffix.
            // Backtick-quoted as identifier; esc_sql() is not appropriate for identifiers.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}

/**
 * Run raplsaich_uninstall_site() in another blog's context.
 * Centralizes switch_to_blog/restore_current_blog so callers cannot
 * accidentally bypass restore via break/continue/return.
 *
 * MUST: All blog-context switches in this file go through this helper.
 * Do NOT call switch_to_blog() directly elsewhere in uninstall.php.
 *
 * @param int $blog_id Blog ID to switch to.
 */
function raplsaich_uninstall_blog( int $blog_id ) {
    switch_to_blog( $blog_id );
    try {
        raplsaich_uninstall_site();
    } finally {
        restore_current_blog();
    }
}

// Track partial completion: started_at is overwritten on each attempt
// (re-run after failure updates the timestamp). If completed_at is absent
// after uninstall, the process was interrupted (idempotent — safe to re-run).
update_option( 'raplsaich_diag_uninstall_started_at', time(), false );

// Multisite: auto-select snapshot (<10k sites) or batched (>=10k) mode.
// Each site operation is idempotent (safe to re-run if interrupted).
if (is_multisite()) {
    $site_count = (int) get_sites(['count' => true]);
    // Threshold filterable for memory-constrained hosts (default 10000).
    // Clamped to 100–100000; falls back to batch if count=0 (query failure).
    $threshold = (int) apply_filters('raplsaich_uninstall_snapshot_threshold', 10000);
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
            raplsaich_uninstall_blog((int) $site_id);
        }
    } else {
        // Batched: for large networks or count=0 fallback.
        // Batch size filterable (default 100, clamped 20–500).
        // Guide: low-memory/slow-DB → 20–50, standard → 100, fast → 200–500.
        $offset = 0;
        $batch  = (int) apply_filters('raplsaich_uninstall_batch_size', 100);
        $batch  = max(20, min($batch, 500));
        do {
            $sites = get_sites([
                'fields'  => 'ids',
                'number'  => $batch,
                'offset'  => $offset,
                'orderby' => 'id',
                'order'   => 'ASC',
            ]);
            foreach ($sites as $site_id) {
                raplsaich_uninstall_blog((int) $site_id);
            }
            $offset += $batch;
        } while (count($sites) === $batch);
    }
} else {
    raplsaich_uninstall_site();
}

// Network-level cleanup (multisite only)
if (is_multisite()) {
    delete_site_option('raplsaich_ms_activate_errors');
    delete_site_transient('raplsaich_ms_activate_errors_last');
}

// completed_at = "script reached this line" (best-effort).
// It does NOT guarantee every DB operation above succeeded —
// individual DROP/DELETE may have silently failed at the DB level.
// Residual keys are raplsaich_diag_* only (runtime metrics, not functional data).
// They are harmless. If concerned, re-run uninstall (idempotent).
update_option( 'raplsaich_diag_uninstall_completed_at', time(), false );
