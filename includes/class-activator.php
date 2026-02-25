<?php
/**
 * Plugin activation handler
 *
 * MUST: No load-time side effects (no add_action, no add_filter, no DB writes
 * at file-include time). This file is require_once'd by uninstall.php to read
 * get_table_suffixes() — any side effects here would fire during uninstall.
 *
 * Allowed side effects (methods only, never at include-time):
 *   DB schema (dbDelta, $wpdb), options/transients, WP cron scheduling.
 * Forbidden: posts, users, files, HTTP, hooks, eval, variable functions ($fn()).
 * CI enforces this — see .github/workflows/zip-verify.yml.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Activator {

    /**
     * Plugin table suffixes — delegates to wpaic_table_suffixes() (single source of truth).
     *
     * @return string[] Table suffixes (without $wpdb->prefix).
     */
    public static function get_table_suffixes(): array {
        return wpaic_table_suffixes();
    }

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
     * Run on version upgrade (lighter than activate - no rewrite flush).
     *
     * MUST: Idempotent — DB + options only, no external side effects
     * (no file I/O, no remote API calls, no email). May be re-run after
     * partial failure. Called on every request where version mismatches
     * (via maybe_upgrade), so keep operations lightweight.
     */
    public static function upgrade() {
        // Upgrade lock: prevent concurrent execution (multisite, object cache race, etc.)
        $lock_key = 'wpaic_upgrade_lock';
        if (get_transient($lock_key)) {
            return; // Another process is already upgrading
        }
        set_transient($lock_key, 1, 3 * MINUTE_IN_SECONDS);

        self::create_tables();
        self::upgrade_columns();
        self::set_default_options();
        self::schedule_cron();
        self::migrate_diag_options();

        // Save version
        update_option('wpaic_version', WPAIC_VERSION);
        delete_transient($lock_key);
    }

    /**
     * Create database tables.
     *
     * Convention: all column names and index names MUST be lowercase_snake_case.
     * CI (zip-verify.yml) relies on this to distinguish SQL from PHP identifiers.
     * Note: all versions since v1.0 have used lowercase — no legacy uppercase columns exist.
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
     * Execute an ALTER TABLE query on a known plugin table.
     *
     * Table name is validated against get_table_suffixes() whitelist before execution.
     * The $alter_clause parameter MUST be a hardcoded string literal (no user input).
     * $wpdb->prepare() cannot be used for identifiers (table/column names);
     * safety is ensured by the whitelist check + backtick-quoted table identifier.
     *
     * @param string $table_suffix Plugin table suffix (e.g. 'aichat_messages')
     * @param string $alter_clause ALTER TABLE clause (e.g. 'ADD COLUMN foo INT DEFAULT 0')
     * @param string $desc         Human-readable description for the log
     */
    /**
     * Return backtick-quoted full table name after whitelist validation.
     *
     * Use this for any direct SQL that references plugin tables. Returns the
     * table name in `backtick-quoted` form, ready for SQL interpolation.
     * Returns empty string if the suffix is not in the whitelist.
     *
     * Safety guarantee: table name = $wpdb->prefix (WordPress-controlled)
     * + whitelist-validated suffix. No user input.
     *
     * @param string $suffix Table suffix (e.g. 'aichat_messages').
     * @return string Backtick-quoted table name, or '' if invalid.
     */
    /**
     * Validate a table suffix and return backtick-quoted table name.
     * Delegates to wpaic_validated_table() — see rapls-ai-chatbot.php.
     *
     * @param string $suffix Table suffix (e.g. 'aichat_messages').
     * @return string Backtick-quoted table name, or '' if invalid.
     */
    public static function validated_table(string $suffix): string {
        return wpaic_validated_table($suffix);
    }

    /**
     * Check if a column exists in a whitelist-validated plugin table.
     *
     * @param string $table_suffix Plugin table suffix (e.g. 'aichat_messages')
     * @param string $column_name  Column name to check
     * @return bool True if column exists
     */
    private static function has_column(string $table_suffix, string $column_name): bool {
        $table = self::validated_table($table_suffix);
        if (!$table) {
            return false;
        }
        global $wpdb;
        // Table name is whitelist-validated via validated_table(). Column name is a hardcoded literal.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$column_name}'");
        return !empty($result);
    }

    /**
     * Check if an index exists in a whitelist-validated plugin table.
     *
     * @param string $table_suffix Plugin table suffix (e.g. 'aichat_messages')
     * @param string $index_name   Index key name to check
     * @return bool True if index exists
     */
    private static function has_index(string $table_suffix, string $index_name): bool {
        $table = self::validated_table($table_suffix);
        if (!$table) {
            return false;
        }
        global $wpdb;
        // Table name is whitelist-validated via validated_table(). Index name is a hardcoded literal.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'");
        return !empty($result);
    }

    /**
     * Check if a whitelist-validated plugin table exists.
     *
     * @param string $table_suffix Plugin table suffix
     * @return bool True if table exists
     */
    private static function table_exists(string $table_suffix): bool {
        if (!in_array($table_suffix, self::get_table_suffixes(), true)) {
            return false;
        }
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    /**
     * Get column metadata from a whitelist-validated plugin table.
     *
     * @param string $table_suffix Plugin table suffix
     * @param string $column_name  Column name to check
     * @return object|null Column info row, or null
     */
    private static function get_column_info(string $table_suffix, string $column_name) {
        $table = self::validated_table($table_suffix);
        if (!$table) {
            return null;
        }
        global $wpdb;
        // Table name is whitelist-validated. Column name is a hardcoded literal.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE '{$column_name}'");
    }

    private static function safe_alter(string $table_suffix, string $alter_clause, string $desc = ''): void {
        global $wpdb;

        // Whitelist: only known plugin tables are allowed
        if (!in_array($table_suffix, self::get_table_suffixes(), true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC DB Upgrade: rejected unknown table suffix "' . $table_suffix . '"');
            }
            return;
        }

        $table = $wpdb->prefix . $table_suffix;
        // Table name is safe: $wpdb->prefix (WordPress-controlled) + whitelist-validated suffix.
        // Backtick-quoted as SQL identifier; $wpdb->prepare() does not support identifier placeholders
        // below WP 6.2, and this plugin supports WP 5.8+.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query("ALTER TABLE `{$table}` {$alter_clause}");
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
        self::maybe_add_feedback_index();
        WPAIC_Lead::maybe_create_table();
    }

    /**
     * Add token columns to messages table if not exists
     */
    private static function maybe_add_token_columns() {
        if (!self::table_exists('aichat_messages')) {
            return;
        }

        if (!self::has_column('aichat_messages', 'input_tokens')) {
            self::safe_alter('aichat_messages', 'ADD COLUMN input_tokens INT UNSIGNED DEFAULT 0 AFTER tokens_used', 'add column input_tokens');
        }

        if (!self::has_column('aichat_messages', 'output_tokens')) {
            self::safe_alter('aichat_messages', 'ADD COLUMN output_tokens INT UNSIGNED DEFAULT 0 AFTER input_tokens', 'add column output_tokens');
        }

        if (!self::has_index('aichat_messages', 'ai_model')) {
            self::safe_alter('aichat_messages', 'ADD KEY ai_model (ai_model)', 'add index ai_model');
        }
    }

    /**
     * Add missing columns to knowledge table if not exists
     */
    private static function maybe_add_is_active_column() {
        if (!self::table_exists('aichat_knowledge')) {
            return;
        }

        if (!self::has_column('aichat_knowledge', 'priority')) {
            self::safe_alter('aichat_knowledge', 'ADD COLUMN priority INT(11) DEFAULT 0 AFTER category', 'add column priority');
            self::safe_alter('aichat_knowledge', 'ADD KEY priority (priority)', 'add index priority');
        }

        if (!self::has_column('aichat_knowledge', 'is_active')) {
            self::safe_alter('aichat_knowledge', 'ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER priority', 'add column is_active');
            self::safe_alter('aichat_knowledge', 'ADD KEY is_active (is_active)', 'add index is_active');
        }

        if (!self::has_column('aichat_knowledge', 'status')) {
            self::safe_alter('aichat_knowledge', "ADD COLUMN status VARCHAR(20) DEFAULT 'published' AFTER is_active", 'add column status');
            self::safe_alter('aichat_knowledge', 'ADD KEY status (status)', 'add index status');
        }

        if (!self::has_column('aichat_knowledge', 'type')) {
            self::safe_alter('aichat_knowledge', "ADD COLUMN type VARCHAR(20) DEFAULT 'qa' AFTER status", 'add column type');
            self::safe_alter('aichat_knowledge', 'ADD KEY type (type)', 'add index type');
        }

        self::maybe_add_feedback_column();
    }

    /**
     * Add feedback column to messages table if not exists (Pro feature)
     */
    private static function maybe_add_feedback_column() {
        if (!self::table_exists('aichat_messages')) {
            return;
        }
        if (!self::has_column('aichat_messages', 'feedback')) {
            self::safe_alter('aichat_messages', 'ADD COLUMN feedback TINYINT DEFAULT NULL AFTER ai_model', 'add column feedback');
        }
    }

    /**
     * Add cache_hash and cache_hit columns to messages table if not exists
     */
    public static function maybe_add_cache_columns() {
        if (!self::table_exists('aichat_messages')) {
            return;
        }
        if (!self::has_column('aichat_messages', 'cache_hash')) {
            self::safe_alter('aichat_messages', 'ADD COLUMN cache_hash VARCHAR(64) DEFAULT NULL AFTER feedback', 'add column cache_hash');
            self::safe_alter('aichat_messages', 'ADD INDEX cache_hash (cache_hash)', 'add index cache_hash');
        }
        if (!self::has_column('aichat_messages', 'cache_hit')) {
            self::safe_alter('aichat_messages', 'ADD COLUMN cache_hit TINYINT(1) DEFAULT 0 AFTER cache_hash', 'add column cache_hit');
        }
    }

    /**
     * Add composite index (conversation_id, created_at) to messages table
     */
    private static function maybe_add_message_composite_index() {
        if (!self::table_exists('aichat_messages')) {
            return;
        }
        if (!self::has_index('aichat_messages', 'conv_created')) {
            self::safe_alter('aichat_messages', 'ADD INDEX conv_created (conversation_id, created_at)', 'add composite index conv_created');
        }
    }

    /**
     * Add composite index on (feedback, created_at) for analytics satisfaction queries.
     */
    private static function maybe_add_feedback_index() {
        if (!self::table_exists('aichat_messages')) {
            return;
        }
        if (!self::has_index('aichat_messages', 'feedback_created')) {
            self::safe_alter('aichat_messages', 'ADD INDEX feedback_created (feedback, created_at)', 'add composite index feedback_created');
        }
    }

    /**
     * Create audit_log table if not exists
     */
    public static function maybe_create_audit_log_table() {
        if (self::table_exists('aichat_audit_log')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aichat_audit_log';
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
        if (!self::table_exists('aichat_conversations')) {
            return;
        }
        if (!self::has_column('aichat_conversations', 'converted_at')) {
            self::safe_alter('aichat_conversations', 'ADD COLUMN converted_at DATETIME DEFAULT NULL AFTER status', 'add column converted_at');
            self::safe_alter('aichat_conversations', 'ADD COLUMN conversion_goal VARCHAR(100) DEFAULT NULL AFTER converted_at', 'add column conversion_goal');
        }
    }

    /**
     * Convert ENUM columns to VARCHAR for better dbDelta compatibility
     */
    private static function maybe_convert_enum_to_varchar() {
        // Convert conversations.status ENUM → VARCHAR(20)
        if (self::table_exists('aichat_conversations')) {
            $col_info = self::get_column_info('aichat_conversations', 'status');
            if ($col_info && strpos(strtolower($col_info->Type), 'enum') !== false) {
                self::safe_alter('aichat_conversations', "MODIFY COLUMN status VARCHAR(20) DEFAULT 'active'", 'convert conversations.status ENUM to VARCHAR');
            }
        }

        // Convert messages.role ENUM → VARCHAR(20)
        if (self::table_exists('aichat_messages')) {
            $col_info = self::get_column_info('aichat_messages', 'role');
            if ($col_info && strpos(strtolower($col_info->Type), 'enum') !== false) {
                self::safe_alter('aichat_messages', 'MODIFY COLUMN role VARCHAR(20) NOT NULL', 'convert messages.role ENUM to VARCHAR');
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
     *
     * MUST: Idempotent — safe to re-run.
     */
    private static function migrate_diag_options() {
        $migrations = [
            'wpaic_hash_unexpected_count' => 'wpaic_diag_hash_unexpected',
        ];

        // Remove renamed options (non-additive — just delete old keys).
        $renames = ['wpaic_diag_upgrade_order_issue'];
        foreach ($renames as $old_key) {
            delete_option($old_key);
        }
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
     * Schedule cron jobs (activation-time only).
     *
     * Idempotent: wp_next_scheduled() guards prevent duplicate registration
     * on repeated activation. Deactivation clears hooks via wp_clear_scheduled_hook().
     * If WP-Cron is disabled (DISABLE_WP_CRON), these events won't fire but
     * cause no harm — the plugin works without them (crawl/cleanup are optional).
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
