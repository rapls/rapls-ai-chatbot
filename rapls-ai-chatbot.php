<?php

/**
 * Plugin Name:       Rapls AI Chatbot
 * Plugin URI:        https://raplsworks.com/rapls-ai-chatbot/
 * Description:       AI Chatbot plugin with OpenAI/Claude/Google support and automatic site content learning.
 * Version:           1.3.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rapls Works
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

// Plugin constants
define('WPAIC_VERSION', '1.3.1');
define('WPAIC_BUILD', '$Format:%h$'); // Auto-replaced by git archive (export-subst)
define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation handler
 *
 * @param bool $network_wide True when Network Activated on multisite.
 */
function wpaic_activate($network_wide = false)
{
    require_once WPAIC_PLUGIN_DIR . 'includes/class-activator.php';

    if (is_multisite() && $network_wide) {
        // Network Activate: create tables/options on every existing subsite.
        // New subsites created later are handled by wpaic_on_new_blog().
        $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            WPAIC_Activator::activate();
            restore_current_blog();
        }
    } else {
        WPAIC_Activator::activate();
    }
}
register_activation_hook(__FILE__, 'wpaic_activate');

/**
 * Provision new subsites created after Network Activate.
 *
 * @param WP_Site|int $new_site New site object (WP 5.1+) or blog_id.
 */
function wpaic_on_new_blog($new_site) {
    if (!function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active_for_network(plugin_basename(__FILE__))) {
        return;
    }
    $blog_id = is_object($new_site) ? (int) $new_site->blog_id : (int) $new_site;
    switch_to_blog($blog_id);
    require_once WPAIC_PLUGIN_DIR . 'includes/class-activator.php';
    WPAIC_Activator::activate();
    restore_current_blog();
}
add_action('wp_initialize_site', 'wpaic_on_new_blog', 200);

/**
 * Plugin deactivation handler
 */
function wpaic_deactivate()
{
    require_once WPAIC_PLUGIN_DIR . 'includes/class-deactivator.php';
    WPAIC_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'wpaic_deactivate');


/**
 * Multibyte-safe string helpers with fallback for environments without mbstring.
 *
 * WordPress does not require mbstring, so these wrappers ensure the plugin
 * degrades gracefully (ASCII-only behaviour) instead of triggering a Fatal.
 */
if (!function_exists('wpaic_mb_strtolower')) {
    function wpaic_mb_strtolower(string $s): string {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }
}
if (!function_exists('wpaic_mb_strlen')) {
    function wpaic_mb_strlen(string $s): int {
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }
}
if (!function_exists('wpaic_mb_strpos')) {
    /**
     * @return int|false
     */
    function wpaic_mb_strpos(string $haystack, string $needle, int $offset = 0) {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle, $offset) : strpos($haystack, $needle, $offset);
    }
}
if (!function_exists('wpaic_mb_substr')) {
    function wpaic_mb_substr(string $s, int $start, ?int $length = null): string {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($s, $start) : mb_substr($s, $start, $length);
        }
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}
if (!function_exists('wpaic_mb_substr_count')) {
    function wpaic_mb_substr_count(string $haystack, string $needle): int {
        return function_exists('mb_substr_count') ? mb_substr_count($haystack, $needle) : substr_count($haystack, $needle);
    }
}
if (!function_exists('wpaic_mb_convert_encoding')) {
    /**
     * Multibyte-safe encoding conversion with graceful fallback.
     *
     * @param string $s        Input string.
     * @param string $to       Target encoding.
     * @param string $from     Source encoding (optional).
     * @return string Converted string, or original if mbstring unavailable.
     */
    function wpaic_mb_convert_encoding(string $s, string $to, string $from = ''): string {
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
if (!function_exists('wpaic_get_text')) {
    function wpaic_get_text(array $src, string $key, string $default = ''): string {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : $default;
    }
}
if (!function_exists('wpaic_get_int')) {
    function wpaic_get_int(array $src, string $key, int $default = 0): int {
        return isset($src[$key]) ? absint(wp_unslash($src[$key])) : $default;
    }
}
if (!function_exists('wpaic_get_email')) {
    function wpaic_get_email(array $src, string $key, string $default = ''): string {
        return isset($src[$key]) ? sanitize_email(wp_unslash($src[$key])) : $default;
    }
}

/**
 * Log DB errors for observability (WP_DEBUG only).
 *
 * Call after critical $wpdb->insert() / $wpdb->update() operations.
 *
 * @param string $context Description of the operation (e.g. 'Message::create').
 */
if (!function_exists('wpaic_log_db_error')) {
    function wpaic_log_db_error(string $context): void {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('WPAIC DB error [%s]: %s', $context, $wpdb->last_error));
        }
    }
}

/**
 * Load and run the main plugin class
 */
function wpaic_run()
{
    require_once WPAIC_PLUGIN_DIR . 'includes/class-loader.php';
    require_once WPAIC_PLUGIN_DIR . 'includes/class-main.php';

    $plugin = new WPAIC_Main();
    $plugin->run();
}
wpaic_run();

/**
 * WP Consent API: declare that this plugin is compatible.
 * When the WP Consent API plugin is active, this tells consent management
 * plugins that we respect consent categories for localStorage and tracking.
 */
$wpaic_plugin_basename = plugin_basename(__FILE__);
add_filter("wp_consent_api_registered_{$wpaic_plugin_basename}", '__return_true');

/**
 * Modify plugin action links
 */
function wpaic_plugin_action_links($actions)
{
    // Add settings link
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wpaic-dashboard')) . '">' .
        esc_html__('Settings', 'rapls-ai-chatbot') . '</a>';
    array_unshift($actions, $settings_link);

    return $actions;
}
add_filter('plugin_action_links_' . WPAIC_PLUGIN_BASENAME, 'wpaic_plugin_action_links', 20);

/**
 * Add row meta with Pro dependency notice
 */
function wpaic_plugin_row_meta($plugin_meta, $plugin_file)
{
    if ($plugin_file === WPAIC_PLUGIN_BASENAME && get_option('wpaic_pro_active')) {
        $last_key = array_key_last($plugin_meta);
        if ($last_key !== null) {
            $plugin_meta[$last_key] .= '<br><span style="color: #d63638;">' .
                esc_html__('Pro version is active. Deactivating this plugin will disable all Pro features.', 'rapls-ai-chatbot') . '</span>';
        }
    }
    return $plugin_meta;
}
add_filter('plugin_row_meta', 'wpaic_plugin_row_meta', 10, 2);
