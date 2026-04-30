<?php

/**
 * Plugin Name:       Rapls AI Chatbot
 * Plugin URI:        https://raplsworks.com/plugins/rapls-ai-chatbot/
 * Description:       AI Chatbot plugin with OpenAI/Claude/Google support and automatic site content learning.
 * Version:           1.6.1
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Rapls
 * Author URI:        https://raplsworks.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rapls-ai-chatbot
 * Domain Path:       /languages
 */

/*
Rapls AI Chatbot is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Rapls AI Chatbot is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Rapls AI Chatbot. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Version: single source of truth in includes/version.php
require_once __DIR__ . '/includes/version.php';
define('RAPLSAICH_BUILD', '$Format:%h$'); // Auto-replaced by git archive (export-subst)
define('RAPLSAICH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAPLSAICH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAPLSAICH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation handler
 *
 * @param bool $network_wide True when Network Activated on multisite.
 */
function raplsaich_activate($network_wide = false)
{
    require_once RAPLSAICH_PLUGIN_DIR . 'includes/class-activator.php';

    if (is_multisite() && $network_wide) {
        // Network Activate: create tables/options on every existing subsite.
        // New subsites created later are handled by raplsaich_on_new_blog().
        $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
        $failed_sites = [];
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            try {
                RAPLSAICH_Activator::activate();
            } catch (\Throwable $e) {
                $failed_sites[(int) $site_id] = $e->getMessage();
            }
            restore_current_blog();
        }
        // Store partial failure log for admin notice (network-level).
        // Compact both site_option and transient to prevent oversized payloads
        // on large networks. Full error details are in error_log (WP_DEBUG only).
        if (!empty($failed_sites)) {
            $compact = [];
            foreach ($failed_sites as $blog_id => $msg) {
                $compact[(int) $blog_id] = substr((string) $msg, 0, 120);
            }
            if (count($compact) > 50) {
                $overflow = count($failed_sites) - 50;
                $compact = array_slice($compact, 0, 50, true);
                $compact['_truncated'] = $overflow;
            }
            update_site_option('raplsaich_ms_activate_errors', $compact);
            // 24h copy for post-incident investigation (survives notice dismissal)
            set_site_transient('raplsaich_ms_activate_errors_last', $compact, DAY_IN_SECONDS);
        } else {
            delete_site_option('raplsaich_ms_activate_errors');
        }
    } else {
        RAPLSAICH_Activator::activate();
    }
}
register_activation_hook(__FILE__, 'raplsaich_activate');

/**
 * Provision new subsites created after Network Activate.
 *
 * @param WP_Site|int $new_site New site object (WP 5.1+) or blog_id.
 */
function raplsaich_on_new_blog($new_site) {
    if (!function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active_for_network(plugin_basename(__FILE__))) {
        return;
    }
    // Ensure activator is loaded (wp_initialize_site fires early)
    if (!class_exists('RAPLSAICH_Activator', false)) {
        if (!defined('RAPLSAICH_PLUGIN_DIR')) {
            return; // Plugin bootstrap incomplete — maybe_upgrade() will handle later
        }
        require_once RAPLSAICH_PLUGIN_DIR . 'includes/class-activator.php';
    }
    $blog_id = is_object($new_site) ? (int) $new_site->blog_id : (int) $new_site;
    switch_to_blog($blog_id);
    try {
        RAPLSAICH_Activator::activate();
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('RAPLSAICH: new subsite activation failed for blog ' . $blog_id . ': ' . $e->getMessage());
        }
        // Fallback: maybe_upgrade() will retry on first request to this subsite
    }
    restore_current_blog();
}
add_action('wp_initialize_site', 'raplsaich_on_new_blog', 200);

/**
 * Suggest a recovery action based on error message keywords.
 * Used by the MS activation failure notice to guide admins
 * without relying on WP_DEBUG/error_log.
 *
 * @param string $msg Truncated error message from activation.
 * @return string Recovery hint, or '' if no match.
 */
function raplsaich_ms_recovery_hint(string $msg): string {
    $lower = strtolower($msg);
    // High-confidence patterns only — MySQL error strings are stable English.
    if (strpos($lower, 'access denied') !== false) {
        return __('Action: check database user privileges.', 'rapls-ai-chatbot');
    }
    if (strpos($lower, "doesn't exist") !== false || strpos($lower, 'does not exist') !== false) {
        return __('Action: deactivate and reactivate the plugin on this site.', 'rapls-ai-chatbot');
    }
    // Fallback for any other error — safe generic guidance.
    return __('Action: check server error logs and database permissions.', 'rapls-ai-chatbot');
}

/**
 * Show admin notice if Network Activate had partial failures.
 */
function raplsaich_ms_activate_error_notice() {
    if (!is_network_admin()) {
        return;
    }
    $errors = get_site_option('raplsaich_ms_activate_errors');
    if (empty($errors) || !is_array($errors)) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>Rapls AI Chatbot:</strong> ';
    echo esc_html(sprintf(
        /* translators: %d: number of failed subsites */
        __('Network activation partially failed on %d site(s). These sites will self-repair on first visit (via auto-upgrade).', 'rapls-ai-chatbot'),
        count($errors)
    ));
    echo ' <details><summary>' . esc_html__('Details', 'rapls-ai-chatbot') . '</summary><ul>';
    foreach ($errors as $blog_id => $msg) {
        if ($blog_id === '_truncated') {
            echo '<li><em>' . esc_html(sprintf(
                /* translators: %d: number of omitted sites */
                __('… and %d more site(s) omitted.', 'rapls-ai-chatbot'),
                (int) $msg
            )) . '</em></li>';
            continue;
        }
        $action = raplsaich_ms_recovery_hint((string) $msg);
        echo '<li>Site #' . (int) $blog_id . ': <code>' . esc_html($msg) . '</code>';
        if ($action) {
            echo ' — <strong>' . esc_html($action) . '</strong>';
        }
        echo '</li>';
    }
    echo '</ul></details>';
    echo '</p></div>';
    // Persist to error_log for post-incident investigation (WP_DEBUG only)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        foreach ($errors as $blog_id => $msg) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('RAPLSAICH MS activate error — site #' . (int) $blog_id . ': ' . $msg);
        }
    }
    // Clear after showing once
    delete_site_option('raplsaich_ms_activate_errors');
}
add_action('network_admin_notices', 'raplsaich_ms_activate_error_notice');

/**
 * Plugin deactivation handler
 */
function raplsaich_deactivate()
{
    require_once RAPLSAICH_PLUGIN_DIR . 'includes/class-deactivator.php';
    RAPLSAICH_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'raplsaich_deactivate');


/**
 * Multibyte-safe string helpers with fallback for environments without mbstring.
 *
 * WordPress does not require mbstring, so these wrappers ensure the plugin
 * degrades gracefully (ASCII-only behaviour) instead of triggering a Fatal.
 */
if (!function_exists('raplsaich_mb_strtolower')) {
    function raplsaich_mb_strtolower(string $s): string {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }
}
if (!function_exists('raplsaich_mb_strlen')) {
    function raplsaich_mb_strlen(string $s): int {
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }
}
if (!function_exists('raplsaich_mb_strpos')) {
    /**
     * @return int|false
     */
    function raplsaich_mb_strpos(string $haystack, string $needle, int $offset = 0) {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle, $offset) : strpos($haystack, $needle, $offset);
    }
}
if (!function_exists('raplsaich_mb_substr')) {
    function raplsaich_mb_substr(string $s, int $start, ?int $length = null): string {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($s, $start) : mb_substr($s, $start, $length);
        }
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}
if (!function_exists('raplsaich_mb_substr_count')) {
    function raplsaich_mb_substr_count(string $haystack, string $needle): int {
        return function_exists('mb_substr_count') ? mb_substr_count($haystack, $needle) : substr_count($haystack, $needle);
    }
}
if (!function_exists('raplsaich_mb_convert_encoding')) {
    /**
     * Multibyte-safe encoding conversion with graceful fallback.
     *
     * @param string $s        Input string.
     * @param string $to       Target encoding.
     * @param string $from     Source encoding (optional).
     * @return string Converted string, or original if mbstring unavailable.
     */
    function raplsaich_mb_convert_encoding(string $s, string $to, string $from = ''): string {
        if (function_exists('mb_convert_encoding')) {
            return $from ? mb_convert_encoding($s, $to, $from) : mb_convert_encoding($s, $to);
        }
        return $s;
    }
}

/**
 * Input sanitization helpers.
 *
 * Enforce the wp_unslash() → sanitize pattern in a single call,
 * preventing the common mistake of omitting wp_unslash().
 */
if (!function_exists('raplsaich_get_text')) {
    function raplsaich_get_text(array $src, string $key, string $default = ''): string {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : $default;
    }
}
if (!function_exists('raplsaich_get_int')) {
    function raplsaich_get_int(array $src, string $key, int $default = 0): int {
        return isset($src[$key]) ? absint(wp_unslash($src[$key])) : $default;
    }
}
if (!function_exists('raplsaich_get_email')) {
    function raplsaich_get_email(array $src, string $key, string $default = ''): string {
        return isset($src[$key]) ? sanitize_email(wp_unslash($src[$key])) : $default;
    }
}

/**
 * Plugin table suffix whitelist — single source of truth for runtime validation.
 * RAPLSAICH_Activator::get_table_suffixes() delegates to this for consistency.
 *
 * @return string[] Table suffixes (without $wpdb->prefix).
 */
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

/**
 * Return a whitelist-validated plugin table name for safe SQL interpolation.
 * Returns backtick-quoted name ready for raw SQL, or '' if suffix is not in whitelist.
 *
 * @param string $suffix Table suffix (e.g. 'raplsaich_messages').
 * @return string Backtick-quoted table name, or '' if invalid.
 */
if (!function_exists('raplsaich_validated_table')) {
    function raplsaich_validated_table(string $suffix): string {
        if (!in_array($suffix, raplsaich_table_suffixes(), true)) {
            return '';
        }
        global $wpdb;
        return '`' . $wpdb->prefix . $suffix . '`';
    }
}

/**
 * Validate table suffix and return backtick-quoted name, or '' with error log on failure.
 * Use at raw SQL entry points: if the return is '', abort the operation.
 *
 * @param string $suffix Table suffix (e.g. 'raplsaich_messages').
 * @param string $caller Calling context for error log (e.g. 'cleanup_old_conversations').
 * @return string Backtick-quoted table name, or '' if invalid.
 */
if (!function_exists('raplsaich_require_table')) {
    function raplsaich_require_table(string $suffix, string $caller = ''): string {
        $table = raplsaich_validated_table($suffix);
        if ($table === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf('RAPLSAICH: invalid table suffix "%s" in %s', $suffix, $caller ?: 'unknown'));
            }
        }
        return $table;
    }
}

/**
 * Validate table suffix for REST/admin contexts where errors must be visible.
 * Returns WP_Error on invalid suffix (for REST responses / wp_send_json_error).
 *
 * IMPORTANT: Return type is string|WP_Error — callers MUST check is_wp_error()
 * before using the value in SQL. Passing the return directly to $wpdb methods
 * without checking will silently corrupt the query.
 *
 * Usage pattern (REST handler):
 *   $table = raplsaich_require_table_or_error('raplsaich_messages', __METHOD__);
 *   if (is_wp_error($table)) { return $table; }
 *   // $table is now a safe backtick-quoted string
 *
 * Calling convention for table validation helpers:
 *   - raplsaich_validated_table()         → raw return (''/quoted), caller checks
 *   - raplsaich_require_table()           → logs on empty, caller early-returns safely
 *   - raplsaich_require_table_or_error()  → returns WP_Error, use in REST/AJAX handlers
 *   - raplsaich_with_table()              → callback pattern, is_wp_error() handled internally (preferred for REST)
 *
 * @param string $suffix Table suffix (e.g. 'raplsaich_messages').
 * @param string $caller Calling context for error message.
 * @return string|WP_Error Backtick-quoted table name, or WP_Error if invalid.
 */
if (!function_exists('raplsaich_require_table_or_error')) {
    function raplsaich_require_table_or_error(string $suffix, string $caller = '') {
        $table = raplsaich_require_table($suffix, $caller);
        if ($table === '') {
            return new WP_Error(
                'raplsaich_table_error',
                sprintf(
                    /* translators: %s: calling context */
                    __('Internal configuration error in %s. Please contact the site administrator.', 'rapls-ai-chatbot'),
                    $caller ?: 'unknown'
                ),
                ['status' => 500]
            );
        }
        return $table;
    }
}

/**
 * Execute a callback with a validated table name, or return WP_Error.
 *
 * Preferred pattern for REST handlers — eliminates the risk of
 * forgetting is_wp_error() on the return value of raplsaich_require_table_or_error().
 *
 * The callback MAY itself return WP_Error (e.g. on $wpdb failure); the caller
 * should pass the return value through to WordPress (REST returns it as HTTP error).
 * For admin-ajax HTML endpoints, prefer raplsaich_require_table() with early return
 * instead of this helper, since wp_send_json_error() is the expected pattern there.
 *
 * Keep callbacks short — SQL execution only. Formatting, validation, and business
 * logic belong outside the closure to avoid deeply nested callback chains.
 *
 * Usage:
 *   return raplsaich_with_table('raplsaich_messages', __METHOD__, function ($table) {
 *       global $wpdb;
 *       return $wpdb->get_results("SELECT * FROM {$table} LIMIT 10");
 *   });
 *
 * @param string   $suffix Table suffix (e.g. 'raplsaich_messages').
 * @param string   $caller Calling context for error message.
 * @param callable $fn     Receives the backtick-quoted table name; its return value is passed through.
 * @return mixed|WP_Error  Return value of $fn, or WP_Error if table validation fails.
 */
if (!function_exists('raplsaich_with_table')) {
    function raplsaich_with_table(string $suffix, string $caller, callable $fn) {
        $table = raplsaich_require_table_or_error($suffix, $caller);
        if (is_wp_error($table)) {
            return $table;
        }
        return $fn($table);
    }
}

/**
 * Log DB errors for observability (WP_DEBUG only).
 *
 * Call after critical $wpdb->insert() / $wpdb->update() operations.
 *
 * @param string $context Description of the operation (e.g. 'Message::create').
 */
if (!function_exists('raplsaich_log_db_error')) {
    function raplsaich_log_db_error(string $context): void {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('RAPLSAICH DB error [%s]: %s', $context, $wpdb->last_error));
        }
    }
}

/**
 * Rate-limited error_log to prevent log flooding under attack or API outages.
 *
 * Allows at most 1 log per $key per $interval seconds. Uses transients (object cache
 * when available, DB otherwise). WP_DEBUG-only by default.
 *
 * @param string $key      Unique throttle key (e.g. 'raplsaich_log_chat_error').
 * @param string $message  Message to log.
 * @param int    $interval Minimum seconds between logs for this key (default: 180).
 */
if (!function_exists('raplsaich_rate_limited_log')) {
    function raplsaich_rate_limited_log(string $key, string $message, int $interval = 180): void {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        /** @filter raplsaich_rate_limited_log_interval Adjust per-key throttle (seconds). Min 10, recommended 180–600. */
        $interval = max(10, (int) apply_filters('raplsaich_rate_limited_log_interval', $interval, $key));
        $transient_key = 'raplsaich_rl_log_' . substr(md5($key), 0, 12);
        if (get_transient($transient_key)) {
            return; // Already logged recently
        }
        set_transient($transient_key, 1, $interval);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($message);
    }
}

/**
 * Load and run the main plugin class
 */
function raplsaich_run()
{
    require_once RAPLSAICH_PLUGIN_DIR . 'includes/helpers.php';
    require_once RAPLSAICH_PLUGIN_DIR . 'includes/class-loader.php';
    require_once RAPLSAICH_PLUGIN_DIR . 'includes/class-main.php';

    $plugin = new RAPLSAICH_Main();
    $plugin->run();

    // Class aliases for Pro backward compatibility are registered by Pro plugin itself.
}
raplsaich_run();

/**
 * WP Consent API: declare that this plugin is compatible.
 * When the WP Consent API plugin is active, this tells consent management
 * plugins that we respect consent categories for localStorage and tracking.
 */
$raplsaich_plugin_basename = plugin_basename(__FILE__);
add_filter("wp_consent_api_registered_{$raplsaich_plugin_basename}", '__return_true');

/**
 * Overlay bundled translations on top of WordPress's just-in-time loader.
 *
 * When WP auto-loads a stale `WP_LANG_DIR/plugins/rapls-ai-chatbot-{locale}.mo`
 * (e.g., auto-downloaded from translate.wordpress.org before new strings were
 * translated), it skips the plugin's bundled .mo entirely. This hook merges
 * the bundled file after auto-load so newly added strings resolve in-locale.
 */
add_action('init', function () {
    $locale = determine_locale();
    $bundled = RAPLSAICH_PLUGIN_DIR . 'languages/rapls-ai-chatbot-' . $locale . '.mo';
    if (is_readable($bundled)) {
        load_textdomain('rapls-ai-chatbot', $bundled);
    }
}, 20);

/**
 * Modify plugin action links
 */
function raplsaich_plugin_action_links($actions)
{
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=raplsaich-dashboard')) . '">' .
        esc_html__('Settings', 'rapls-ai-chatbot') . '</a>';
    array_unshift($actions, $settings_link);

    return $actions;
}
add_filter('plugin_action_links_' . RAPLSAICH_PLUGIN_BASENAME, 'raplsaich_plugin_action_links', 20);

/**
 * Add row meta with Pro dependency notice
 */
function raplsaich_plugin_row_meta($plugin_meta, $plugin_file)
{
    if ($plugin_file === RAPLSAICH_PLUGIN_BASENAME && raplsaich_is_pro_active()) {
        $last_key = array_key_last($plugin_meta);
        if ($last_key !== null) {
            $plugin_meta[$last_key] .= '<br><span style="color: #d63638;">' .
                esc_html__('Pro version is active. Deactivating this plugin will disable all Pro features.', 'rapls-ai-chatbot') . '</span>';
        }
    }
    return $plugin_meta;
}
add_filter('plugin_row_meta', 'raplsaich_plugin_row_meta', 10, 2);

/**
 * AJAX: dismiss review notice permanently
 */
function raplsaich_dismiss_review()
{
    check_ajax_referer('raplsaich_dismiss_review', 'nonce');
    update_option('raplsaich_review_dismissed', true, false);
    wp_send_json_success();
}
add_action('wp_ajax_raplsaich_dismiss_review', 'raplsaich_dismiss_review');
