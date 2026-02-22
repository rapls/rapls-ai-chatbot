<?php
/**
 * Plugin deactivation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Deactivator {

    /**
     * Run on deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wpaic_crawl_site');
        wp_clear_scheduled_hook('wpaic_cleanup_old_conversations');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
