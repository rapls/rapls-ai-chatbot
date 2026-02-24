<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Activator {

    /**
     * Run on activation
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::schedule_cron();

        // Save version
        update_option('wpaic_version', WPAIC_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Run on version upgrade (lighter than activate - no rewrite flush)
     */
    public static function upgrade() {
        self::create_tables();
        self::upgrade_columns();
        self::set_default_options();
        self::schedule_cron();
        self::migrate_diag_options();

        // Clear transient diag flags that indicate resolved issues.
        delete_option('wpaic_diag_upgrade_order_issue');

        // Save version
        update_option('wpaic_version', WPAIC_VERSION);
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Conversations table
        $table_conversations = $wpdb->prefix . 'aichat_conversations';
        $sql_conversations = "CREATE TABLE {$table_conversations} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            visitor_ip VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash of IP+UA+salt (privacy fingerprint, not raw IP)',
            page_url TEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_conversations);

        // Messages table
        $table_messages = $wpdb->prefix . 'aichat_messages';
        $sql_messages = "CREATE TABLE {$table_messages} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            tokens_used INT UNSIGNED DEFAULT 0,
            input_tokens INT UNSIGNED DEFAULT 0,
            output_tokens INT UNSIGNED DEFAULT 0,
            ai_provider VARCHAR(32) DEFAULT NULL,
            ai_model VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY role (role),
            KEY created_at (created_at),
            KEY ai_model (ai_model)
        ) {$charset_collate};";

        dbDelta($sql_messages);

        // Add token columns if not exists
        self::maybe_add_token_columns();

        // Content index table
        $table_index = $wpdb->prefix . 'aichat_index';
        $sql_index = "CREATE TABLE {$table_index} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            chunk_index INT UNSIGNED DEFAULT 0,
            url TEXT NOT NULL,
            indexed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY content_hash (content_hash),
            FULLTEXT KEY content_fulltext (title, content)
        ) {$charset_collate};";

        dbDelta($sql_index);

        // Knowledge base table
        $table_knowledge = $wpdb->prefix . 'aichat_knowledge';
        $sql_knowledge = "CREATE TABLE {$table_knowledge} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active),
            FULLTEXT KEY knowledge_fulltext (title, content)
        ) {$charset_collate};";

        dbDelta($sql_knowledge);

        // Leads table (Pro feature)
        $table_leads = $wpdb->prefix . 'aichat_leads';
        $sql_leads = "CREATE TABLE {$table_leads} (
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

        dbDelta($sql_leads);

        // Add is_active column if not exists
        self::maybe_add_is_active_column();
    }

    /**
     * Execute an ALTER TABLE query and log errors under WP_DEBUG.
     *
     * @param string $sql   The ALTER TABLE SQL statement
     * @param string $desc  Human-readable description for the log (e.g. "add column input_tokens")
     */
    private static function safe_alter(string $sql, string $desc = ''): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->query($sql);
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            $context = $desc ? " ({$desc})" : '';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('WPAIC DB Upgrade' . $context . ': ' . $wpdb->last_error);
        }
    }

    /**
     * Public entry point for column upgrades (called from WPAIC_Main)
     */
    public static function upgrade_columns() {
        self::maybe_add_token_columns();
        self::maybe_add_is_active_column();
        self::maybe_add_cache_columns();
        self::maybe_create_audit_log_table();
        self::maybe_add_conversion_columns();
        self::maybe_convert_enum_to_varchar();
        self::maybe_add_message_composite_index();
        WPAIC_Lead::maybe_create_table();
    }

    /**
     * Add token columns to messages table if not exists
     */
    private static function maybe_add_token_columns() {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aichat_messages';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_messages));

        if (!$table_exists) {
            return;
        }

        // Check if input_tokens column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $input_tokens_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_messages} LIKE 'input_tokens'");

        if (empty($input_tokens_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD COLUMN input_tokens INT UNSIGNED DEFAULT 0 AFTER tokens_used", 'add column input_tokens');
        }

        // Check if output_tokens column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $output_tokens_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_messages} LIKE 'output_tokens'");

        if (empty($output_tokens_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD COLUMN output_tokens INT UNSIGNED DEFAULT 0 AFTER input_tokens", 'add column output_tokens');
        }

        // Check if ai_model index exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table_messages} WHERE Key_name = 'ai_model'");

        if (empty($index_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD KEY ai_model (ai_model)", 'add index ai_model');
        }
    }

    /**
     * Add missing columns to knowledge table if not exists
     */
    private static function maybe_add_is_active_column() {
        global $wpdb;
        $table_knowledge = $wpdb->prefix . 'aichat_knowledge';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_knowledge));

        if (!$table_exists) {
            return;
        }

        // priority column
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $has_priority = $wpdb->get_results("SHOW COLUMNS FROM {$table_knowledge} LIKE 'priority'");
        if (empty($has_priority)) {
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD COLUMN priority INT(11) DEFAULT 0 AFTER category", 'add column priority');
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD KEY priority (priority)", 'add index priority');
        }

        // is_active column
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_knowledge} LIKE 'is_active'");
        if (empty($column_exists)) {
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER priority", 'add column is_active');
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD KEY is_active (is_active)", 'add index is_active');
        }

        // status column
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $has_status = $wpdb->get_results("SHOW COLUMNS FROM {$table_knowledge} LIKE 'status'");
        if (empty($has_status)) {
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD COLUMN status VARCHAR(20) DEFAULT 'published' AFTER is_active", 'add column status');
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD KEY status (status)", 'add index status');
        }

        // type column
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $has_type = $wpdb->get_results("SHOW COLUMNS FROM {$table_knowledge} LIKE 'type'");
        if (empty($has_type)) {
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD COLUMN type VARCHAR(20) DEFAULT 'qa' AFTER status", 'add column type');
            self::safe_alter("ALTER TABLE {$table_knowledge} ADD KEY type (type)", 'add index type');
        }

        // Add feedback column to messages
        self::maybe_add_feedback_column();
    }

    /**
     * Add feedback column to messages table if not exists (Pro feature)
     */
    private static function maybe_add_feedback_column() {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aichat_messages';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_messages));

        if (!$table_exists) {
            return;
        }

        // Check if feedback column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_messages} LIKE 'feedback'");

        if (empty($column_exists)) {
            // Add feedback column: 1 = positive, -1 = negative, 0 or NULL = no feedback
            self::safe_alter("ALTER TABLE {$table_messages} ADD COLUMN feedback TINYINT DEFAULT NULL AFTER ai_model", 'add column feedback');
        }
    }

    /**
     * Add cache_hash and cache_hit columns to messages table if not exists
     */
    public static function maybe_add_cache_columns() {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aichat_messages';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_messages));

        if (!$table_exists) {
            return;
        }

        // Check if cache_hash column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hash_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_messages} LIKE 'cache_hash'");

        if (empty($hash_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD COLUMN cache_hash VARCHAR(64) DEFAULT NULL AFTER feedback", 'add column cache_hash');
            self::safe_alter("ALTER TABLE {$table_messages} ADD INDEX cache_hash (cache_hash)", 'add index cache_hash');
        }

        // Check if cache_hit column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hit_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_messages} LIKE 'cache_hit'");

        if (empty($hit_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD COLUMN cache_hit TINYINT(1) DEFAULT 0 AFTER cache_hash", 'add column cache_hit');
        }
    }

    /**
     * Add composite index (conversation_id, created_at) to messages table
     * for efficient conversation history queries with ordering.
     */
    private static function maybe_add_message_composite_index() {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aichat_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_messages));

        if (!$table_exists) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table_messages} WHERE Key_name = 'conv_created'");

        if (empty($index_exists)) {
            self::safe_alter("ALTER TABLE {$table_messages} ADD INDEX conv_created (conversation_id, created_at)", 'add composite index conv_created');
        }
    }

    /**
     * Create audit_log table if not exists
     */
    public static function maybe_create_audit_log_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_audit_log';

        // Check if table already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($table_exists) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_name VARCHAR(100) DEFAULT NULL,
            object_type VARCHAR(50) DEFAULT NULL,
            object_id BIGINT(20) UNSIGNED DEFAULT NULL,
            details LONGTEXT DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY user_id (user_id),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Add conversion tracking columns to conversations table
     */
    public static function maybe_add_conversion_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_conversations';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if (!$table_exists) {
            return;
        }

        // Check if converted_at column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $col_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'converted_at'");

        if (empty($col_exists)) {
            self::safe_alter("ALTER TABLE {$table} ADD COLUMN converted_at DATETIME DEFAULT NULL AFTER status", 'add column converted_at');
            self::safe_alter("ALTER TABLE {$table} ADD COLUMN conversion_goal VARCHAR(100) DEFAULT NULL AFTER converted_at", 'add column conversion_goal');
        }
    }

    /**
     * Convert ENUM columns to VARCHAR for better dbDelta compatibility
     */
    private static function maybe_convert_enum_to_varchar() {
        global $wpdb;

        // Convert conversations.status ENUM → VARCHAR(20)
        $table_conv = $wpdb->prefix . 'aichat_conversations';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_conv));
        if ($table_exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $col_info = $wpdb->get_row("SHOW COLUMNS FROM {$table_conv} LIKE 'status'");
            if ($col_info && strpos(strtolower($col_info->Type), 'enum') !== false) {
                self::safe_alter("ALTER TABLE {$table_conv} MODIFY COLUMN status VARCHAR(20) DEFAULT 'active'", 'convert conversations.status ENUM to VARCHAR');
            }
        }

        // Convert messages.role ENUM → VARCHAR(20)
        $table_msg = $wpdb->prefix . 'aichat_messages';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_msg));
        if ($table_exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $col_info = $wpdb->get_row("SHOW COLUMNS FROM {$table_msg} LIKE 'role'");
            if ($col_info && strpos(strtolower($col_info->Type), 'enum') !== false) {
                self::safe_alter("ALTER TABLE {$table_msg} MODIFY COLUMN role VARCHAR(20) NOT NULL", 'convert messages.role ENUM to VARCHAR');
            }
        }
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_settings = [
            // AI settings
            'ai_provider'     => 'openai',
            'openai_api_key'  => '',
            'openai_model'    => 'gpt-4o',
            'claude_api_key'  => '',
            'claude_model'    => 'claude-sonnet-4-20250514',
            'gemini_api_key'  => '',
            'gemini_model'    => 'gemini-2.0-flash-exp',

            // Chatbot settings
            'bot_name'        => 'Assistant',
            'bot_avatar'      => '🤖',
            'welcome_message' => 'Hello! How can I help you today?',
            'system_prompt'   => 'You are a helpful assistant. Please answer user questions politely.',
            'quota_error_message' => 'Currently recharging. Please try again later.',
            'max_tokens'      => 1000,
            'temperature'     => 0.7,

            // Context prompts
            'knowledge_exact_prompt' => "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===",
            'knowledge_qa_prompt' => "=== CRITICAL INSTRUCTIONS ===\nBelow is a FAQ database. When the user asks a question:\n1. FIRST, look for [BEST MATCH] - this is the most relevant Q&A for the user's question\n2. If [BEST MATCH] exists, use that Answer to respond\n3. If no [BEST MATCH], find the Question that matches or is similar to the user's question\n4. Return the corresponding Answer from the FAQ\n5. DO NOT make up answers - ONLY use the information provided below\n\nIMPORTANT: The Answer after [BEST MATCH] is your primary response source.\n\n=== FAQ DATABASE ===\n{context}\n=== END FAQ DATABASE ===",
            'site_context_prompt' => "[IMPORTANT: Reference Information]\nYou MUST use the following information as the primary source when answering. If the answer can be found in this information, use it. Only use your general knowledge if the reference information does not cover the topic.\n\n{context}",

            // Display settings
            'badge_margin_right'  => 20,
            'badge_margin_bottom' => 20,
            'primary_color'   => '#007bff',
            'show_on_mobile'  => true,
            'excluded_pages'  => [],

            // History settings
            'save_history'    => true,
            'retention_days'  => 90,

            // Rate limiting
            'rate_limit'      => 20,
            'rate_limit_window' => 3600,

            // Crawler settings
            'crawler_enabled' => true,
            'crawler_post_types' => ['all'],
            'crawler_interval' => 'daily',
            'crawler_chunk_size' => 1000,
            'crawler_max_results' => 3,

            // Uninstall settings
            'delete_data_on_uninstall' => false,
        ];

        // Set default if no existing settings
        if (!get_option('wpaic_settings')) {
            update_option('wpaic_settings', $default_settings);
        }
    }

    /**
     * Migrate renamed diagnostic options (one-time on upgrade).
     * Merges old key value into new key and removes old key.
     */
    private static function migrate_diag_options() {
        $migrations = [
            'wpaic_hash_unexpected_count' => 'wpaic_diag_hash_unexpected',
        ];
        foreach ($migrations as $old_key => $new_key) {
            $old_val = get_option($old_key);
            if ($old_val !== false) {
                $new_val = (int) get_option($new_key, 0);
                update_option($new_key, $new_val + (int) $old_val, false);
                delete_option($old_key);
            }
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron() {
        if (!wp_next_scheduled('wpaic_crawl_site')) {
            wp_schedule_event(time(), 'daily', 'wpaic_crawl_site');
        }

        if (!wp_next_scheduled('wpaic_cleanup_old_conversations')) {
            wp_schedule_event(time(), 'daily', 'wpaic_cleanup_old_conversations');
        }
    }
}
