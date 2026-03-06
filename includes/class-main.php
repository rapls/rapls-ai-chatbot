<?php
/**
 * Main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Main {

    /**
     * Loader instance
     */
    protected $loader;

    /**
     * Plugin version
     */
    protected $version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->version = WPAIC_VERSION;
        $this->load_textdomain();
        $this->load_dependencies();
        $this->maybe_upgrade();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Load plugin textdomain for translations (WordPress 6.7+ requires init or later)
     */
    private function load_textdomain() {
        add_action('init', function () {
            load_plugin_textdomain(
                'rapls-ai-chatbot',
                false,
                dirname(WPAIC_PLUGIN_BASENAME) . '/languages'
            );
        });
    }

    /**
     * Handle version upgrade process.
     *
     * Runs on every request via constructor (not just activation), so
     * upgrades apply even when files are replaced without activation hook.
     * MUST: upgrade() must stay lightweight — only version mismatch triggers it,
     * but it blocks the current request until complete.
     */
    private function maybe_upgrade() {
        $current_version = get_option('wpaic_version', '0');

        if (version_compare($current_version, $this->version, '<')) {
            // Run lightweight upgrade (no rewrite flush).
            // Activator is require_once'd here; all model classes are already
            // available via load_dependencies() which runs before this method.
            // Upgrade must only use early WP APIs ($wpdb, options, transients).
            // Sentinel: WPAIC_PLUGIN_DIR must be defined by plugin entry point.
            // MU-plugin/autoloader may trigger this benignly (not fatal).
            // Triage: single occurrence in MU setup = safe to ignore;
            // continuous updates in standard setup = investigate load order.
            // Static guard limits DB writes to 1/process.
            if (defined('WP_DEBUG') && WP_DEBUG && !defined('WPAIC_PLUGIN_DIR')) {
                static $bootstrap_warned = false;
                if (!$bootstrap_warned) {
                    $bootstrap_warned = true;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('WPAIC: maybe_upgrade() called before plugin bootstrap — constants missing');
                    $now     = time();
                    $last_ts = (int) get_option('wpaic_diag_boot_order_last_seen', 0);
                    // Throttle: update at most once per 60s across all workers.
                    // Note: last_seen may lag up to 60s behind actual occurrence.
                    if (($now - $last_ts) >= 60) {
                        // _first_seen: set only once. Strict false check avoids
                        // treating stored "0" as unset.
                        if (false === get_option('wpaic_diag_boot_order_first_seen', false)) {
                            update_option('wpaic_diag_boot_order_first_seen', $now, false);
                        }
                        update_option('wpaic_diag_boot_order_last_seen', $now, false);
                    }
                }
            }
            require_once WPAIC_PLUGIN_DIR . 'includes/class-activator.php';
            WPAIC_Activator::upgrade();
        }
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Pro features stubs (for compatibility — skipped if Pro already loaded)
        if (!class_exists('WPAIC_Pro_Features', false)) {
            require_once WPAIC_PLUGIN_DIR . 'includes/class-pro-features.php';
        }

        // Parsers
        require_once WPAIC_PLUGIN_DIR . 'includes/parsers/class-pdf-parser.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/parsers/class-docx-parser.php';

        // Models
        require_once WPAIC_PLUGIN_DIR . 'includes/models/class-conversation.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/models/class-message.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/models/class-content-index.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/models/class-knowledge.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/models/class-lead.php';

        // Utilities
        require_once WPAIC_PLUGIN_DIR . 'includes/class-cost-calculator.php';

        // Exceptions
        require_once WPAIC_PLUGIN_DIR . 'includes/exceptions/class-quota-exceeded-exception.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/exceptions/class-communication-exception.php';

        // AI Providers
        require_once WPAIC_PLUGIN_DIR . 'includes/ai-providers/interface-ai-provider.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/ai-providers/class-openai-provider.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/ai-providers/class-claude-provider.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/ai-providers/class-gemini-provider.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/ai-providers/class-openrouter-provider.php';

        // Crawler
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-content-extractor.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-content-chunker.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-embedding-generator.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-vector-search.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-search-engine.php';
        require_once WPAIC_PLUGIN_DIR . 'includes/crawler/class-site-crawler.php';

        // API
        require_once WPAIC_PLUGIN_DIR . 'includes/api/class-rest-controller.php';

        // Admin
        require_once WPAIC_PLUGIN_DIR . 'includes/admin/class-admin.php';

        // Frontend
        require_once WPAIC_PLUGIN_DIR . 'includes/frontend/class-chatbot-widget.php';

        $this->loader = new WPAIC_Loader();
    }

    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        $admin = new WPAIC_Admin();

        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->loader->add_action('admin_notices', $admin, 'message_limit_notice');
        $this->loader->add_action('admin_notices', $admin, 'api_key_decryption_notice');
        $this->loader->add_action('admin_notices', $admin, 'security_settings_notice');
        $this->loader->add_action('admin_init', $admin, 'register_settings');
        $this->loader->add_action('admin_init', $this, 'add_privacy_policy_content');
        $this->loader->add_action('admin_init', $this, 'maybe_upgrade_database');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->loader->add_filter('update_footer', $admin, 'admin_footer_build_info', 20);

        // AJAX
        $this->loader->add_action('wp_ajax_wpaic_manual_crawl', $admin, 'ajax_manual_crawl');
        $this->loader->add_action('wp_ajax_wpaic_delete_index', $admin, 'ajax_delete_index');
        $this->loader->add_action('wp_ajax_wpaic_delete_all_index', $admin, 'ajax_delete_all_index');
        $this->loader->add_action('wp_ajax_wpaic_crawler_exclude_post', $admin, 'ajax_crawler_exclude_post');
        $this->loader->add_action('wp_ajax_wpaic_crawler_include_post', $admin, 'ajax_crawler_include_post');
        $this->loader->add_action('wp_ajax_wpaic_test_api', $admin, 'ajax_test_api');
        $this->loader->add_action('wp_ajax_wpaic_get_conversation_messages', $admin, 'ajax_get_conversation_messages');
        $this->loader->add_action('wp_ajax_wpaic_delete_conversation', $admin, 'ajax_delete_conversation');
        $this->loader->add_action('wp_ajax_wpaic_delete_conversations_bulk', $admin, 'ajax_delete_conversations_bulk');
        $this->loader->add_action('wp_ajax_wpaic_delete_all_conversations', $admin, 'ajax_delete_all_conversations');

        // Knowledge AJAX
        $this->loader->add_action('wp_ajax_wpaic_add_knowledge', $admin, 'ajax_add_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_import_knowledge', $admin, 'ajax_import_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_get_knowledge', $admin, 'ajax_get_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_update_knowledge', $admin, 'ajax_update_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_delete_knowledge', $admin, 'ajax_delete_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_toggle_knowledge', $admin, 'ajax_toggle_knowledge');
        $this->loader->add_action('wp_ajax_wpaic_update_priority', $admin, 'ajax_update_priority');

        // Embedding AJAX
        $this->loader->add_action('wp_ajax_wpaic_generate_embeddings', $admin, 'ajax_generate_embeddings');
        $this->loader->add_action('wp_ajax_wpaic_clear_embeddings', $admin, 'ajax_clear_embeddings');
        $this->loader->add_action('wp_ajax_wpaic_embedding_status', $admin, 'ajax_embedding_status');

        // Settings import/export/reset AJAX
        $this->loader->add_action('wp_ajax_wpaic_export_settings', $admin, 'ajax_export_settings');
        $this->loader->add_action('wp_ajax_wpaic_import_settings', $admin, 'ajax_import_settings');
        $this->loader->add_action('wp_ajax_wpaic_reset_settings', $admin, 'ajax_reset_settings');
        $this->loader->add_action('wp_ajax_wpaic_reset_usage', $admin, 'ajax_reset_usage');

        // Session reset AJAX
        $this->loader->add_action('wp_ajax_wpaic_reset_sessions', $admin, 'ajax_reset_sessions');

        // Model fetch AJAX
        $this->loader->add_action('wp_ajax_wpaic_fetch_models', $admin, 'ajax_fetch_models');

        // Dismiss security notice
        $this->loader->add_action('wp_ajax_wpaic_dismiss_security_notice', $admin, 'ajax_dismiss_security_notice');

        // MCP API key generation
        $this->loader->add_action('wp_ajax_wpaic_generate_mcp_key', $admin, 'ajax_generate_mcp_key');
    }

    /**
     * Define public hooks
     */
    private function define_public_hooks() {
        $widget = new WPAIC_Chatbot_Widget();

        $this->loader->add_action('wp_enqueue_scripts', $widget, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $widget, 'enqueue_scripts');
        $this->loader->add_action('wp_footer', $widget, 'render_widget');

        // Shortcode [rapls_chatbot] and [wpaic_chatbot] for inline embedding
        add_shortcode('rapls_chatbot', [$widget, 'render_shortcode']);
        add_shortcode('wpaic_chatbot', [$widget, 'render_shortcode']);

        // Gutenberg block (server-side rendered, wraps the shortcode)
        add_action('init', function () {
            register_block_type(WPAIC_PLUGIN_DIR . 'includes/block');
            wp_set_script_translations(
                'rapls-ai-chatbot-chatbot-editor-script',
                'rapls-ai-chatbot',
                WPAIC_PLUGIN_DIR . 'languages'
            );
        });

        // Cross-site embed endpoint: ?wpaic_embed=1
        add_filter('query_vars', function ($vars) {
            $vars[] = 'wpaic_embed';
            return $vars;
        });
        $this->loader->add_action('template_redirect', $widget, 'maybe_render_embed_page');
    }

    /**
     * Define REST API hooks
     */
    private function define_api_hooks() {
        $api = new WPAIC_REST_Controller();

        $this->loader->add_action('rest_api_init', $api, 'register_routes');

        // MCP Server (conditional on setting)
        $settings = get_option('wpaic_settings', []);
        if (!empty($settings['mcp_enabled'])) {
            require_once WPAIC_PLUGIN_DIR . 'includes/mcp/class-mcp-tool-registry.php';
            require_once WPAIC_PLUGIN_DIR . 'includes/mcp/class-mcp-server.php';
            $mcp = new WPAIC_MCP_Server();
            $this->loader->add_action('rest_api_init', $mcp, 'register_routes');
        }
    }

    /**
     * Register custom cron schedules (monthly).
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public function add_cron_schedules($schedules) {
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Monthly', 'rapls-ai-chatbot'),
            ];
        }
        return $schedules;
    }

    /**
     * Define cron hooks
     */
    private function define_cron_hooks() {
        // Register custom schedules
        $this->loader->add_filter('cron_schedules', $this, 'add_cron_schedules');

        // Cleanup is always registered
        $this->loader->add_action('wpaic_cleanup_old_conversations', $this, 'cleanup_old_conversations');

        // Crawler hooks deferred to init so Pro singleton is available
        $this->loader->add_action('init', $this, 'maybe_register_crawler_hooks');
    }

    /**
     * Register crawler hooks if Pro is active (Site Learning is Pro-only)
     */
    public function maybe_register_crawler_hooks() {
        if (WPAIC_Pro_Features::get_instance()->is_pro()) {
            $crawler = new WPAIC_Site_Crawler();
            add_action('wpaic_crawl_site', [$crawler, 'crawl_all']);
            add_action('save_post', [$crawler, 'on_save_post'], 10, 2);
            add_action('delete_post', [$crawler, 'on_delete_post']);
        }
    }

    /**
     * Delete old conversations
     */
    public function cleanup_old_conversations() {
        global $wpdb;

        $settings = get_option('wpaic_settings', []);
        $retention_days = isset($settings['retention_days']) ? (int) $settings['retention_days'] : 90;

        if ($retention_days <= 0) {
            return;
        }

        // Whitelist-validated table names for raw SQL.
        $table_conversations = wpaic_require_table('aichat_conversations', 'cleanup_old_conversations');
        $table_messages = wpaic_require_table('aichat_messages', 'cleanup_old_conversations');
        if (!$table_conversations || !$table_messages) {
            return;
        }

        // Get old conversation IDs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $old_conversations = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table_conversations} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        if (!empty($old_conversations)) {
            $batch_size = 1000;
            $batches = array_chunk($old_conversations, $batch_size);

            foreach ($batches as $batch) {
                $ids_placeholder = implode(',', array_fill(0, count($batch), '%d'));

                // Delete messages
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_messages} WHERE conversation_id IN ({$ids_placeholder})",
                    ...$batch
                ));

                // Delete conversations
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_conversations} WHERE id IN ({$ids_placeholder})",
                    ...$batch
                ));
            }
        }

        /**
         * Allow Pro plugin to extend cleanup to additional tables (leads, audit_log, etc.).
         *
         * @param int $retention_days Number of days to retain data.
         */
        do_action('wpaic_after_cleanup', $retention_days);
    }

    /**
     * Add privacy policy content
     */
    public function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = sprintf(
            '<h2>%s</h2>
            <p>%s</p>
            <h3>%s</h3>
            <ul>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
            </ul>
            <h3>%s</h3>
            <ul>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
            </ul>
            <h3>%s</h3>
            <p>%s</p>
            <h3>%s</h3>
            <p>%s</p>',
            esc_html__( 'Rapls AI Chatbot', 'rapls-ai-chatbot' ),
            esc_html__( 'This plugin provides AI-powered chat functionality. When chat history saving is enabled, the following data may be collected and stored:', 'rapls-ai-chatbot' ),
            esc_html__( 'Data Collected', 'rapls-ai-chatbot' ),
            esc_html__( 'Chat messages (user questions and AI responses)', 'rapls-ai-chatbot' ),
            esc_html__( 'Session identifiers', 'rapls-ai-chatbot' ),
            esc_html__( 'Page URLs where conversations occurred', 'rapls-ai-chatbot' ),
            esc_html__( 'Hashed IP addresses (SHA-256, for rate limiting)', 'rapls-ai-chatbot' ),
            esc_html__( 'External Services', 'rapls-ai-chatbot' ),
            esc_html__( 'OpenAI API (api.openai.com) - for GPT model responses', 'rapls-ai-chatbot' ),
            esc_html__( 'Anthropic API (api.anthropic.com) - for Claude model responses', 'rapls-ai-chatbot' ),
            esc_html__( 'Google AI API (generativelanguage.googleapis.com) - for Gemini model responses', 'rapls-ai-chatbot' ),
            esc_html__( 'OpenRouter API (openrouter.ai) - for multi-provider model access', 'rapls-ai-chatbot' ),
            esc_html__( 'Data Retention', 'rapls-ai-chatbot' ),
            esc_html__( 'Conversation data is automatically deleted after the configured retention period. Administrators can also manually delete conversations at any time.', 'rapls-ai-chatbot' ),
            esc_html__( 'User Controls', 'rapls-ai-chatbot' ),
            esc_html__( 'Chat history saving can be disabled in the plugin settings. When disabled, no conversation data is stored.', 'rapls-ai-chatbot' )
        );

        wp_add_privacy_policy_content(
            'Rapls AI Chatbot',
            wp_kses_post( $content )
        );
    }

    /**
     * Check and upgrade database if needed
     */
    public function maybe_upgrade_database() {
        $db_version = get_option('wpaic_db_version', '0');
        $current_version = '1.3.1';

        if (version_compare($db_version, $current_version, '<')) {
            $this->upgrade_database();
            update_option('wpaic_db_version', $current_version);
        }
    }

    /**
     * Upgrade database schema (delegates to Activator to avoid duplication)
     */
    private function upgrade_database() {
        require_once WPAIC_PLUGIN_DIR . 'includes/class-activator.php';
        WPAIC_Activator::upgrade_columns();
    }

    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get version
     */
    public function get_version() {
        return $this->version;
    }
}
