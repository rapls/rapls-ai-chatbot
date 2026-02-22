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
define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation handler
 */
function wpaic_activate()
{
    require_once WPAIC_PLUGIN_DIR . 'includes/class-activator.php';
    WPAIC_Activator::activate();
}
register_activation_hook(__FILE__, 'wpaic_activate');

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
