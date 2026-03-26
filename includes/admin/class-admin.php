<?php
/**
 * Admin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Admin {

    /**
     * Log a diagnostic event code for display in Security Diagnostics.
     * Stores last 10 events in a transient (codes only, no sensitive data).
     *
     * @param string $code Short event code (e.g. 'api_test_failed', 'import_parse_error').
     */
    public static function log_diagnostic_event(string $code): void {
        $key = 'raplsaich_diag_events';
        $events = get_transient($key);
        if (!is_array($events)) {
            $events = [];
        }
        $events[] = [
            'code' => sanitize_key($code),
            'time' => time(),
        ];
        // Keep last 10 entries
        if (count($events) > 10) {
            $events = array_slice($events, -10);
        }
        set_transient($key, $events, DAY_IN_SECONDS);
    }

    /**
     * Get the required capability for managing the plugin.
     * Filterable to allow shop_manager or other roles access.
     *
     * @return string WordPress capability string.
     */
    public static function get_manage_cap(): string {
        return (string) apply_filters('raplsaich_manage_cap', 'manage_options');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        $cap = self::get_manage_cap();

        // Main menu
        add_menu_page(
            __('AI Chatbot', 'rapls-ai-chatbot'),
            __('AI Chatbot', 'rapls-ai-chatbot'),
            $cap,
            'raplsaich-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-format-chat',
            30
        );

        // Dashboard
        add_submenu_page(
            'raplsaich-dashboard',
            __('Dashboard', 'rapls-ai-chatbot'),
            __('Dashboard', 'rapls-ai-chatbot'),
            $cap,
            'raplsaich-dashboard',
            [$this, 'render_dashboard_page']
        );

        // Settings
        add_submenu_page(
            'raplsaich-dashboard',
            __('Settings', 'rapls-ai-chatbot'),
            __('Settings', 'rapls-ai-chatbot'),
            $cap,
            'raplsaich-settings',
            [$this, 'render_settings_page']
        );

        // Knowledge
        add_submenu_page(
            'raplsaich-dashboard',
            __('Knowledge', 'rapls-ai-chatbot'),
            __('Knowledge', 'rapls-ai-chatbot'),
            $cap,
            'raplsaich-knowledge',
            [$this, 'render_knowledge_page']
        );

        $is_pro = get_option('raplsaich_pro_active');

        // Pro menus - show as locked when Pro is not active
        // When Pro is active, the Pro plugin adds its own menus
        if (!$is_pro) {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Pro Settings', 'rapls-ai-chatbot'),
                __('Pro Settings', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-pro-settings',
                [$this, 'render_pro_upsell_page']
            );
        }

        // Site Learning — Pro only
        if ($is_pro) {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Site Learning', 'rapls-ai-chatbot'),
                __('Site Learning', 'rapls-ai-chatbot'),
                $cap,
                'raplsaich-crawler',
                [$this, 'render_crawler_page']
            );
        } else {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Site Learning', 'rapls-ai-chatbot'),
                __('Site Learning', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-crawler',
                [$this, 'render_pro_upsell_page']
            );
        }

        // Conversation History — Pro only
        if ($is_pro) {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Conversations', 'rapls-ai-chatbot'),
                __('Conversations', 'rapls-ai-chatbot'),
                $cap,
                'raplsaich-conversations',
                [$this, 'render_conversations_page']
            );
        } else {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Conversations', 'rapls-ai-chatbot'),
                __('Conversations', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-conversations',
                [$this, 'render_pro_upsell_page']
            );
        }

        if (!$is_pro) {
            add_submenu_page(
                'raplsaich-dashboard',
                __('Analytics', 'rapls-ai-chatbot'),
                __('Analytics', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-analytics',
                [$this, 'render_pro_upsell_page']
            );

            add_submenu_page(
                'raplsaich-dashboard',
                __('Leads', 'rapls-ai-chatbot'),
                __('Leads', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-leads',
                [$this, 'render_pro_upsell_page']
            );

            add_submenu_page(
                'raplsaich-dashboard',
                __('Audit Log', 'rapls-ai-chatbot'),
                __('Audit Log', 'rapls-ai-chatbot') . ' <span class="raplsaich-pro-menu-badge">PRO</span>',
                $cap,
                'raplsaich-audit-log',
                [$this, 'render_pro_upsell_page']
            );
        }
    }

    /**
     * Show admin notice when message limit is reached
     */
    public function message_limit_notice(): void {
        // No artificial limits — users pay their own API costs.
    }

    /**
     * Show admin notice when handoff requests are pending
     */
    public function handoff_pending_notice(): void {
        $settings = get_option('raplsaich_settings', []);
        $pro_settings = $settings['pro_features'] ?? [];
        if (empty($pro_settings['human_handoff_enabled'])) {
            return;
        }

        $count = RAPLSAICH_Conversation::get_handoff_count();
        if ($count <= 0) {
            return;
        }

        $conversations_url = admin_url('admin.php?page=raplsaich-conversations');
        ?>
        <div class="notice notice-warning is-dismissible" style="border-left-color: #e65100;">
            <p>
                <strong style="color: #e65100;">&#x1f6a8; <?php esc_html_e('AI Chatbot — Handoff:', 'rapls-ai-chatbot'); ?></strong>
                <?php
                printf(
                    wp_kses(
                        /* translators: %1$d: number of pending handoff conversations, %2$s: link open tag, %3$s: link close tag */
                        _n(
                            '%1$d conversation is waiting for support. %2$sView conversations%3$s',
                            '%1$d conversations are waiting for support. %2$sView conversations%3$s',
                            $count,
                            'rapls-ai-chatbot'
                        ),
                        ['a' => ['href' => []]]
                    ),
                    (int) $count,
                    '<a href="' . esc_url($conversations_url) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('raplsaich_settings_group', 'raplsaich_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Sanitize imported/merged settings values (clamp numerics, sanitize strings).
     *
     * Unlike sanitize_settings() which handles form submission (tab detection, checkboxes),
     * this method sanitizes an already-merged settings array — suitable for imports.
     *
     * @param array $settings Merged settings array
     * @param array $existing Current stored settings (for fallback values)
     * @return array Sanitized settings
     */
    public function sanitize_settings_values(array $settings, array $existing = []): array {
        // Numeric clamps (same ranges as sanitize_settings and REST controller)
        if (isset($settings['max_tokens'])) {
            $settings['max_tokens'] = max(100, min(16384, absint($settings['max_tokens'])));
        }
        if (isset($settings['temperature'])) {
            $settings['temperature'] = max(0.0, min(2.0, floatval($settings['temperature'])));
        }
        if (isset($settings['message_history_count'])) {
            $settings['message_history_count'] = max(1, min(50, absint($settings['message_history_count'])));
        }
        if (isset($settings['rate_limit'])) {
            $settings['rate_limit'] = absint($settings['rate_limit']);
        }
        if (isset($settings['rate_limit_window'])) {
            $valid_windows = [60, 300, 600, 1800, 3600, 10800, 21600, 43200, 86400];
            $val = absint($settings['rate_limit_window']);
            $settings['rate_limit_window'] = in_array($val, $valid_windows, true) ? $val : ($existing['rate_limit_window'] ?? 3600);
        }
        if (isset($settings['crawler_max_results'])) {
            $settings['crawler_max_results'] = max(1, min(20, absint($settings['crawler_max_results'])));
        }
        if (isset($settings['badge_margin_right'])) {
            $settings['badge_margin_right'] = absint($settings['badge_margin_right']);
        }
        if (isset($settings['badge_margin_bottom'])) {
            $settings['badge_margin_bottom'] = absint($settings['badge_margin_bottom']);
        }

        // Color validation
        if (isset($settings['primary_color'])) {
            $color = sanitize_hex_color($settings['primary_color']);
            $settings['primary_color'] = $color ?: '#007bff';
        }

        // String sanitization for key text fields
        $text_fields = ['system_prompt', 'quota_error_message', 'welcome_message'];
        foreach ($text_fields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = sanitize_textarea_field($settings[$field]);
            }
        }

        // Response language allowlist
        if (isset($settings['response_language'])) {
            $valid_langs = ['', 'auto', 'en', 'ja', 'zh', 'ko', 'es', 'fr', 'de', 'pt', 'it', 'ru', 'ar', 'th', 'vi'];
            if (!in_array($settings['response_language'], $valid_langs, true)) {
                $settings['response_language'] = $existing['response_language'] ?? '';
            }
        }

        // Boolean fields
        $bool_fields = ['show_on_mobile', 'dark_mode', 'markdown_enabled', 'save_history', 'show_feedback_buttons', 'crawler_enabled', 'consent_strict_mode', 'embedding_enabled', 'web_search_enabled', 'mcp_enabled', 'recaptcha_enabled', 'trust_cloudflare_ip', 'trust_proxy_ip', 'delete_data_on_uninstall'];
        foreach ($bool_fields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = (bool) $settings[$field];
            }
        }

        // Widget theme allowlist
        if (isset($settings['widget_theme'])) {
            $valid_themes = ['default', 'simple', 'classic', 'light', 'minimal', 'flat', 'modern', 'gradient', 'dark', 'glass', 'rounded', 'ocean', 'sunset', 'forest', 'neon', 'elegant'];
            if (!in_array($settings['widget_theme'], $valid_themes, true)) {
                $settings['widget_theme'] = $existing['widget_theme'] ?? 'default';
            }
        }

        // AI provider allowlist
        if (isset($settings['ai_provider'])) {
            $valid_providers = ['openai', 'claude', 'gemini', 'openrouter'];
            if (!in_array($settings['ai_provider'], $valid_providers, true)) {
                $settings['ai_provider'] = $existing['ai_provider'] ?? 'openai';
            }
        }

        // Embedding provider allowlist
        if (isset($settings['embedding_provider'])) {
            $valid_emb_providers = ['auto', 'openai', 'gemini'];
            if (!in_array($settings['embedding_provider'], $valid_emb_providers, true)) {
                $settings['embedding_provider'] = $existing['embedding_provider'] ?? 'auto';
            }
        }

        // Additional numeric clamps
        if (isset($settings['retention_days'])) {
            $settings['retention_days'] = max(0, min(3650, absint($settings['retention_days'])));
        }
        if (isset($settings['crawler_chunk_size'])) {
            $settings['crawler_chunk_size'] = max(100, min(10000, absint($settings['crawler_chunk_size'])));
        }
        if (isset($settings['recaptcha_threshold'])) {
            $settings['recaptcha_threshold'] = max(0.1, min(1.0, floatval($settings['recaptcha_threshold'])));
        }

        // Additional allowlists
        if (isset($settings['sources_display_mode'])) {
            if (!in_array($settings['sources_display_mode'], ['none', 'matched', 'all'], true)) {
                $settings['sources_display_mode'] = $existing['sources_display_mode'] ?? 'none';
            }
        }
        if (isset($settings['recaptcha_fail_mode'])) {
            if (!in_array($settings['recaptcha_fail_mode'], ['open', 'closed'], true)) {
                $settings['recaptcha_fail_mode'] = $existing['recaptcha_fail_mode'] ?? 'open';
            }
        }
        if (isset($settings['badge_position'])) {
            if (!in_array($settings['badge_position'], ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true)) {
                $settings['badge_position'] = $existing['badge_position'] ?? 'bottom-right';
            }
        }
        if (isset($settings['crawler_interval'])) {
            if (!in_array($settings['crawler_interval'], ['hourly', 'twicedaily', 'daily', 'weekly', 'monthly'], true)) {
                $settings['crawler_interval'] = $existing['crawler_interval'] ?? 'daily';
            }
        }

        return $settings;
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings(array $input): array {
        // Load existing settings to preserve values not in the form
        $existing = get_option('raplsaich_settings', []);
        $sanitized = [];

        // AI settings
        $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider'] ?? ($existing['ai_provider'] ?? 'openai'));

        // Encrypt API keys:
        //  - New value submitted → encrypt and save
        //  - Empty value → keep existing (value is never output to HTML)
        //  - Explicit delete flag → clear the key
        foreach (['openai_api_key', 'claude_api_key', 'gemini_api_key', 'openrouter_api_key'] as $key_field) {
            $delete_flag = 'delete_' . $key_field;
            if (!empty($input[$delete_flag])) {
                // Explicit deletion requested via hidden field
                $sanitized[$key_field] = '';
            } elseif (array_key_exists($key_field, $input) && $input[$key_field] !== '') {
                // New key provided
                $sanitized[$key_field] = $this->maybe_encrypt_api_key($input[$key_field]);
            } else {
                // Empty or not submitted → preserve existing
                $sanitized[$key_field] = $existing[$key_field] ?? '';
            }
        }

        // Non-blocking prefix/length validation warnings for API keys
        $key_prefixes = [
            'openai_api_key'  => ['prefixes' => ['sk-'], 'label' => 'OpenAI'],
            'claude_api_key'  => ['prefixes' => ['sk-ant-'], 'label' => 'Claude'],
            'gemini_api_key'  => ['prefixes' => ['AIza'], 'label' => 'Gemini'],
            'openrouter_api_key' => ['prefixes' => ['sk-or-'], 'label' => 'OpenRouter'],
        ];
        foreach ($key_prefixes as $kf => $meta) {
            $raw = $input[$kf] ?? '';
            if ($raw === '' || !empty($input['delete_' . $kf])) {
                continue;
            }
            $matches_prefix = false;
            foreach ($meta['prefixes'] as $pfx) {
                if (strpos($raw, $pfx) === 0) {
                    $matches_prefix = true;
                    break;
                }
            }
            if (!$matches_prefix) {
                add_settings_error(
                    'raplsaich_settings',
                    'api_key_prefix_' . $kf,
                    /* translators: 1: provider name, 2: expected prefix */
                    sprintf(__('%1$s API key does not start with the expected prefix (%2$s). The key has been saved, but please verify it is correct.', 'rapls-ai-chatbot'), $meta['label'], implode(' / ', $meta['prefixes'])),
                    'warning'
                );
            }
        }

        $sanitized['openai_model'] = sanitize_text_field($input['openai_model'] ?? ($existing['openai_model'] ?? 'gpt-4o-mini'));
        $sanitized['claude_model'] = sanitize_text_field($input['claude_model'] ?? ($existing['claude_model'] ?? 'claude-haiku-4-5-20251001'));
        $sanitized['gemini_model'] = sanitize_text_field($input['gemini_model'] ?? ($existing['gemini_model'] ?? 'gemini-2.0-flash'));
        $sanitized['openrouter_model'] = sanitize_text_field($input['openrouter_model'] ?? ($existing['openrouter_model'] ?? 'openrouter/auto'));

        // Chatbot settings
        $sanitized['bot_name'] = sanitize_text_field($input['bot_name'] ?? ($existing['bot_name'] ?? 'Assistant'));
        // Avatar can be emoji or image URL
        $avatar_input = $input['bot_avatar'] ?? ($existing['bot_avatar'] ?? '🤖');
        if (filter_var($avatar_input, FILTER_VALIDATE_URL) || preg_match('/^\//', $avatar_input)) {
            $sanitized['bot_avatar'] = esc_url_raw($avatar_input);
        } else {
            $sanitized['bot_avatar'] = sanitize_text_field($avatar_input);
        }
        $sanitized['welcome_message'] = sanitize_textarea_field($input['welcome_message'] ?? ($existing['welcome_message'] ?? ''));

        // Per-language welcome messages
        $allowed_welcome_langs = ['en', 'ja', 'zh', 'ko', 'es', 'fr', 'de', 'pt', 'it', 'ru', 'ar', 'th', 'vi'];
        $sanitized['welcome_messages'] = [];
        $input_welcome = $input['welcome_messages'] ?? ($existing['welcome_messages'] ?? []);
        if (is_array($input_welcome)) {
            foreach ($input_welcome as $lang => $msg) {
                if (in_array($lang, $allowed_welcome_langs, true)) {
                    $sanitized['welcome_messages'][$lang] = sanitize_textarea_field($msg);
                }
            }
        }

        $sanitized['system_prompt'] = sanitize_textarea_field($input['system_prompt'] ?? ($existing['system_prompt'] ?? ''));
        $valid_langs = ['', 'auto', 'en', 'ja', 'zh', 'ko', 'es', 'fr', 'de', 'pt', 'it', 'ru', 'ar', 'th', 'vi'];
        $raw_lang = $input['response_language'] ?? ($existing['response_language'] ?? '');
        $sanitized['response_language'] = in_array($raw_lang, $valid_langs, true) ? $raw_lang : '';
        $sanitized['quota_error_message'] = sanitize_text_field($input['quota_error_message'] ?? ($existing['quota_error_message'] ?? ''));
        $sanitized['max_tokens'] = max(100, min(16384, absint($input['max_tokens'] ?? ($existing['max_tokens'] ?? 1000))));
        $sanitized['temperature'] = max(0.0, min(2.0, floatval($input['temperature'] ?? ($existing['temperature'] ?? 0.7))));
        $sanitized['message_history_count'] = max(1, min(50, absint($input['message_history_count'] ?? ($existing['message_history_count'] ?? 10))));

        // Context prompt settings
        $sanitized['knowledge_exact_prompt'] = sanitize_textarea_field($input['knowledge_exact_prompt'] ?? ($existing['knowledge_exact_prompt'] ?? ''));
        $sanitized['knowledge_qa_prompt'] = sanitize_textarea_field($input['knowledge_qa_prompt'] ?? ($existing['knowledge_qa_prompt'] ?? ''));
        $sanitized['site_context_prompt'] = sanitize_textarea_field($input['site_context_prompt'] ?? ($existing['site_context_prompt'] ?? ''));

        // Feature prompt settings
        $sanitized['regenerate_prompt'] = sanitize_textarea_field($input['regenerate_prompt'] ?? ($existing['regenerate_prompt'] ?? ''));
        $sanitized['feedback_good_header'] = sanitize_textarea_field($input['feedback_good_header'] ?? ($existing['feedback_good_header'] ?? ''));
        $sanitized['feedback_bad_header'] = sanitize_textarea_field($input['feedback_bad_header'] ?? ($existing['feedback_bad_header'] ?? ''));
        $sanitized['summary_prompt'] = sanitize_textarea_field($input['summary_prompt'] ?? ($existing['summary_prompt'] ?? ''));

        // Restore defaults for prompt fields when saved empty
        $defaults = self::get_all_defaults();
        $prompt_fields = [
            'welcome_message', 'system_prompt', 'quota_error_message',
            'knowledge_exact_prompt', 'knowledge_qa_prompt', 'site_context_prompt',
            'regenerate_prompt', 'feedback_good_header', 'feedback_bad_header', 'summary_prompt',
        ];
        foreach ($prompt_fields as $field) {
            if (isset($sanitized[$field]) && trim($sanitized[$field]) === '' && !empty($defaults[$field])) {
                $sanitized[$field] = $defaults[$field];
            }
        }

        // Display settings (use array_key_exists for numeric values that could be 0)
        $valid_positions = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
        $sanitized['badge_position'] = in_array($input['badge_position'] ?? '', $valid_positions)
            ? sanitize_text_field($input['badge_position'])
            : ($existing['badge_position'] ?? 'bottom-right');
        $sanitized['badge_margin_right'] = array_key_exists('badge_margin_right', $input)
            ? absint($input['badge_margin_right'])
            : ($existing['badge_margin_right'] ?? 20);
        $sanitized['badge_margin_bottom'] = array_key_exists('badge_margin_bottom', $input)
            ? absint($input['badge_margin_bottom'])
            : ($existing['badge_margin_bottom'] ?? 20);
        $sanitized['primary_color'] = sanitize_hex_color($input['primary_color'] ?? ($existing['primary_color'] ?? '#007bff')) ?: '#007bff';

        // Widget theme
        $valid_themes = ['default', 'simple', 'classic', 'light', 'minimal', 'flat', 'modern', 'gradient', 'dark', 'glass', 'rounded', 'ocean', 'sunset', 'forest', 'neon', 'elegant'];
        $sanitized['widget_theme'] = in_array($input['widget_theme'] ?? '', $valid_themes)
            ? sanitize_text_field($input['widget_theme'])
            : ($existing['widget_theme'] ?? 'default');

        // Dark mode (check if Display Settings form was submitted by checking for badge_margin_right)
        $display_form_submitted = array_key_exists('badge_margin_right', $input);
        if ($display_form_submitted) {
            $sanitized['dark_mode'] = !empty($input['dark_mode']);
        } else {
            $sanitized['dark_mode'] = $existing['dark_mode'] ?? false;
        }

        if ($display_form_submitted) {
            $sanitized['show_on_mobile'] = !empty($input['show_on_mobile']);
        } else {
            $sanitized['show_on_mobile'] = $existing['show_on_mobile'] ?? true;
        }

        // Page visibility settings (checkboxes: unchecked = not present when Display form submitted)
        if ($display_form_submitted) {
            $sanitized['badge_show_on_home'] = !empty($input['badge_show_on_home']);
            $sanitized['badge_show_on_posts'] = !empty($input['badge_show_on_posts']);
            $sanitized['badge_show_on_pages'] = !empty($input['badge_show_on_pages']);
            $sanitized['badge_show_on_archives'] = !empty($input['badge_show_on_archives']);
        } else {
            $sanitized['badge_show_on_home'] = $existing['badge_show_on_home'] ?? true;
            $sanitized['badge_show_on_posts'] = $existing['badge_show_on_posts'] ?? true;
            $sanitized['badge_show_on_pages'] = $existing['badge_show_on_pages'] ?? true;
            $sanitized['badge_show_on_archives'] = $existing['badge_show_on_archives'] ?? true;
        }
        $sanitized['badge_include_ids'] = sanitize_text_field($input['badge_include_ids'] ?? ($existing['badge_include_ids'] ?? ''));
        $sanitized['badge_exclude_ids'] = sanitize_text_field($input['badge_exclude_ids'] ?? ($existing['badge_exclude_ids'] ?? ''));

        // Handle excluded_pages - if the form was submitted (indicated by excluded_pages_submitted flag)
        // but no pages are selected, clear the array
        if (array_key_exists('excluded_pages_submitted', $input)) {
            $sanitized['excluded_pages'] = array_key_exists('excluded_pages', $input)
                ? array_map('absint', (array) $input['excluded_pages'])
                : [];
        } else {
            $sanitized['excluded_pages'] = $existing['excluded_pages'] ?? [];
        }

        // Sentinel: settings.php includes _settings_page hidden field; crawler.php does not
        $settings_page_submitted = !empty($input['_settings_page']);

        // reCAPTCHA settings — trim keys to prevent whitespace-only values passing empty checks
        if ($settings_page_submitted) {
            $sanitized['recaptcha_enabled'] = !empty($input['recaptcha_enabled']);
        } else {
            $sanitized['recaptcha_enabled'] = $existing['recaptcha_enabled'] ?? false;
        }
        $sanitized['recaptcha_site_key'] = trim(sanitize_text_field($input['recaptcha_site_key'] ?? ($existing['recaptcha_site_key'] ?? '')));
        // Encrypt reCAPTCHA secret key (preserve existing if field submitted empty)
        // Use lighter sanitization — only trim + control char removal (sanitize_text_field
        // can strip characters that may appear in reCAPTCHA secret keys).
        if (array_key_exists('recaptcha_secret_key', $input) && trim($input['recaptcha_secret_key']) !== '') {
            $secret = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input['recaptcha_secret_key']));
            $sanitized['recaptcha_secret_key'] = $this->encrypt_secret($secret);
        } else {
            $sanitized['recaptcha_secret_key'] = $existing['recaptcha_secret_key'] ?? '';
        }
        $sanitized['recaptcha_threshold'] = max(0.1, min(1.0, floatval($input['recaptcha_threshold'] ?? ($existing['recaptcha_threshold'] ?? 0.5))));
        if ($settings_page_submitted) {
            $sanitized['recaptcha_use_existing'] = !empty($input['recaptcha_use_existing']);
        } else {
            $sanitized['recaptcha_use_existing'] = $existing['recaptcha_use_existing'] ?? false;
        }

        // History settings (save_history has hidden input in crawler.php, but guard for safety)
        if ($settings_page_submitted || array_key_exists('save_history', $input)) {
            $sanitized['save_history'] = !empty($input['save_history']);
        } else {
            $sanitized['save_history'] = $existing['save_history'] ?? true;
        }
        $sanitized['retention_days'] = max(0, min(3650, absint($input['retention_days'] ?? ($existing['retention_days'] ?? 90))));

        // Web search setting (AI Settings tab)
        if ($settings_page_submitted) {
            $sanitized['web_search_enabled'] = !empty($input['web_search_enabled']);
        } else {
            $sanitized['web_search_enabled'] = $existing['web_search_enabled'] ?? false;
        }

        // Uninstall settings
        if ($settings_page_submitted) {
            $sanitized['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']);
        } else {
            $sanitized['delete_data_on_uninstall'] = $existing['delete_data_on_uninstall'] ?? false;
        }

        // WP Consent API: strict mode (require Consent API for storage/tracking)
        if ($settings_page_submitted) {
            $sanitized['consent_strict_mode'] = !empty($input['consent_strict_mode']);
        } else {
            $sanitized['consent_strict_mode'] = $existing['consent_strict_mode'] ?? false;
        }

        // Rate limiting
        $sanitized['rate_limit'] = absint($input['rate_limit'] ?? ($existing['rate_limit'] ?? 20));
        $valid_windows = [60, 300, 600, 1800, 3600, 10800, 21600, 43200, 86400];
        $raw_window = absint($input['rate_limit_window'] ?? ($existing['rate_limit_window'] ?? 3600));
        $sanitized['rate_limit_window'] = in_array($raw_window, $valid_windows, true) ? $raw_window : ($existing['rate_limit_window'] ?? 3600);

        // Cloudflare IP trust
        if ($settings_page_submitted) {
            $sanitized['trust_cloudflare_ip'] = !empty($input['trust_cloudflare_ip']);
            $sanitized['trust_proxy_ip'] = !empty($input['trust_proxy_ip']);
        } else {
            $sanitized['trust_cloudflare_ip'] = $existing['trust_cloudflare_ip'] ?? false;
            $sanitized['trust_proxy_ip'] = $existing['trust_proxy_ip'] ?? false;
        }

        // reCAPTCHA failure mode
        $sanitized['recaptcha_fail_mode'] = in_array($input['recaptcha_fail_mode'] ?? '', ['open', 'closed'], true)
            ? $input['recaptcha_fail_mode']
            : ($existing['recaptcha_fail_mode'] ?? 'open');

        // Crawler settings
        // Check if crawler form was submitted (crawler_interval is always present in crawler form)
        $crawler_form_submitted = array_key_exists('crawler_interval', $input);
        if ($crawler_form_submitted) {
            // Crawler form: checkbox unchecked = not in input = false
            $sanitized['crawler_enabled'] = !empty($input['crawler_enabled']);
        } else {
            // Other form: preserve existing value
            $sanitized['crawler_enabled'] = $existing['crawler_enabled'] ?? false;
        }
        $sanitized['crawler_post_types'] = array_key_exists('crawler_post_types', $input)
            ? array_map('sanitize_text_field', $input['crawler_post_types'])
            : ($existing['crawler_post_types'] ?? ['all']);
        $crawler_interval_raw = sanitize_text_field($input['crawler_interval'] ?? ($existing['crawler_interval'] ?? 'daily'));
        $sanitized['crawler_interval'] = in_array($crawler_interval_raw, ['hourly', 'twicedaily', 'daily', 'weekly', 'monthly'], true)
            ? $crawler_interval_raw
            : 'daily';
        $sanitized['crawler_chunk_size'] = max(100, min(10000, absint($input['crawler_chunk_size'] ?? ($existing['crawler_chunk_size'] ?? 1000))));
        $sanitized['crawler_max_results'] = max(1, min(20, absint($input['crawler_max_results'] ?? ($existing['crawler_max_results'] ?? 3))));
        $sources_mode = $input['sources_display_mode'] ?? ($existing['sources_display_mode'] ?? 'matched');
        $sanitized['sources_display_mode'] = in_array($sources_mode, ['none', 'matched', 'all'], true) ? $sources_mode : 'matched';
        $sanitized['crawler_exclude_ids'] = array_values(array_unique(array_map(
            'absint',
            array_filter($input['crawler_exclude_ids'] ?? ($existing['crawler_exclude_ids'] ?? []))
        )));

        // Embedding settings (on Crawler form or Settings form)
        if ($crawler_form_submitted || array_key_exists('embedding_enabled', $input)) {
            $sanitized['embedding_enabled'] = !empty($input['embedding_enabled']);
        } else {
            $sanitized['embedding_enabled'] = $existing['embedding_enabled'] ?? false;
        }
        $valid_emb_providers = ['auto', 'openai', 'gemini'];
        $sanitized['embedding_provider'] = in_array($input['embedding_provider'] ?? '', $valid_emb_providers, true)
            ? $input['embedding_provider']
            : ($existing['embedding_provider'] ?? 'auto');

        // Markdown rendering setting (Display Settings form)
        if ($display_form_submitted) {
            $sanitized['markdown_enabled'] = !empty($input['markdown_enabled']);
        } else {
            $sanitized['markdown_enabled'] = $existing['markdown_enabled'] ?? true;
        }

        // Feedback buttons setting (Chat Settings tab)
        if ($settings_page_submitted) {
            $sanitized['show_feedback_buttons'] = !empty($input['show_feedback_buttons']);
        } else {
            $sanitized['show_feedback_buttons'] = $existing['show_feedback_buttons'] ?? true;
        }

        // MCP settings (AI Settings form — use _settings_page sentinel, not ai_provider which is also in crawler)
        if ($settings_page_submitted) {
            $sanitized['mcp_enabled'] = !empty($input['mcp_enabled']);
        } else {
            $sanitized['mcp_enabled'] = $existing['mcp_enabled'] ?? false;
        }
        // Preserve MCP API key hash (managed via AJAX, not form submission)
        // When called via update_option (AJAX key generation), $input may contain the new hash.
        $sanitized['mcp_api_key_hash'] = !empty($input['mcp_api_key_hash'])
            ? sanitize_text_field($input['mcp_api_key_hash'])
            : ($existing['mcp_api_key_hash'] ?? '');

        // Pro features settings
        $sanitized['pro_features'] = $this->sanitize_pro_features_settings(
            $input['pro_features'] ?? [],
            $existing['pro_features'] ?? []
        );

        // Enhanced content extraction checkbox (on Crawler page, outside pro_features form)
        if ($crawler_form_submitted) {
            $sanitized['pro_features']['enhanced_content_extraction'] = !empty($input['enhanced_content_extraction']);
        }

        return $sanitized;
    }
    /**
     * Sanitize Pro features settings.
     * Free returns existing values unchanged; Pro handles sanitization via filter.
     */
    private function sanitize_pro_features_settings(array $input, array $existing): array {
        if (empty($input)) {
            return $existing;
        }
        // Pro plugin sanitizes its own settings via this filter
        return (array) apply_filters('raplsaich_sanitize_pro_settings', $existing, $input);
    }


    /**
     * Encrypt API key if needed
     */
    private function maybe_encrypt_api_key(string $key): string {
        if (empty($key)) {
            return $key;
        }

        // Already encrypted (GCM or CBC)
        if (strpos($key, 'encg:') === 0 || strpos($key, 'enc:') === 0) {
            return $key;
        }

        if (!function_exists('openssl_encrypt')) {
            return $key;
        }

        // Use AES-256-GCM (authenticated encryption with tamper detection)
        $encryption_key = self::get_encryption_key();
        $aad = self::get_encryption_aad();
        $iv = openssl_random_pseudo_bytes(12); // 12 bytes recommended for GCM
        $tag = '';
        $encrypted = openssl_encrypt($key, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);

        if ($encrypted === false) {
            return $key;
        }

        // Format: encg: + base64(iv[12] + tag[16] + ciphertext)
        return 'encg:' . base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Derive a fixed-length 32-byte encryption key from wp_salt.
     * Normalizes the key material to avoid OpenSSL truncation/padding issues.
     */
    private static function get_encryption_key(): string {
        return hash('sha256', wp_salt('auth'), true);
    }

    /**
     * Get Additional Authenticated Data (AAD) for GCM encryption.
     * Prevents ciphertext reuse across different sites or contexts.
     */
    private static function get_encryption_aad(): string {
        return 'raplsaich_' . wp_parse_url(get_site_url(), PHP_URL_HOST);
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles(string $hook): void {
        // Menu badge styles on all admin pages
        wp_enqueue_style(
            'raplsaich-admin-menu',
            RAPLSAICH_PLUGIN_URL . 'assets/css/admin-menu.css',
            [],
            RAPLSAICH_VERSION
        );

        if (strpos($hook, 'raplsaich') === false) {
            return;
        }

        wp_enqueue_style(
            'raplsaich-admin',
            RAPLSAICH_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RAPLSAICH_VERSION
        );

        // Page-specific CSS (extracted from inline <style> blocks)
        if (strpos($hook, 'raplsaich-settings') !== false) {
            wp_enqueue_style('raplsaich-badge-position', RAPLSAICH_PLUGIN_URL . 'assets/css/admin-badge-position.css', ['raplsaich-admin'], RAPLSAICH_VERSION);
        }
        if (strpos($hook, 'raplsaich-conversations') !== false) {
            wp_enqueue_style('raplsaich-conversations', RAPLSAICH_PLUGIN_URL . 'assets/css/admin-conversations.css', ['raplsaich-admin'], RAPLSAICH_VERSION);
        }
        if (strpos($hook, 'raplsaich-knowledge') !== false) {
            wp_enqueue_style('raplsaich-knowledge', RAPLSAICH_PLUGIN_URL . 'assets/css/admin-knowledge.css', ['raplsaich-admin'], RAPLSAICH_VERSION);
        }
        // Pro preview styles (upsell pages — only on pages that show Pro previews)
        if (strpos($hook, 'raplsaich-pro-settings') !== false || strpos($hook, 'raplsaich-analytics') !== false ||
            strpos($hook, 'raplsaich-leads') !== false || strpos($hook, 'raplsaich-audit-log') !== false ||
            strpos($hook, 'raplsaich-crawler') !== false || strpos($hook, 'raplsaich-conversations') !== false) {
            wp_enqueue_style('raplsaich-pro-preview', RAPLSAICH_PLUGIN_URL . 'assets/css/admin-pro-preview.css', ['raplsaich-admin'], RAPLSAICH_VERSION);
        }

        // Settings page JS (export/import/reset, avatar, multimodal checks)
        if (strpos($hook, 'raplsaich-settings') !== false) {
            $pro_settings = get_option('raplsaich_settings', []);
            $pro_feat = $pro_settings['pro_features'] ?? [];
            wp_enqueue_script('raplsaich-admin-settings', RAPLSAICH_PLUGIN_URL . 'assets/js/admin-settings.js', ['jquery', 'raplsaich-admin'], RAPLSAICH_VERSION, true);
            // Pass multimodal flag to JS
            wp_localize_script('raplsaich-admin-settings', 'raplsaichSettingsData', [
                'multimodalEnabled' => !empty($pro_feat['multimodal_enabled']),
            ]);
        }

        // Page-specific JS (extracted from inline <script> blocks)
        if (strpos($hook, 'raplsaich-crawler') !== false) {
            wp_enqueue_script('raplsaich-crawler-types', RAPLSAICH_PLUGIN_URL . 'assets/js/admin-crawler-types.js', [], RAPLSAICH_VERSION, true);
        }
        // Tab groups JS for Pro settings preview
        if (strpos($hook, 'raplsaich-pro-settings') !== false) {
            wp_enqueue_script('raplsaich-tab-groups', RAPLSAICH_PLUGIN_URL . 'assets/js/admin-tab-groups.js', ['jquery'], RAPLSAICH_VERSION, true);
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts(string $hook): void {
        if (strpos($hook, 'raplsaich') === false) {
            return;
        }

        // Enqueue media uploader and color picker for settings page
        if (strpos($hook, 'raplsaich-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
        }

        // Enqueue Chart.js for dashboard
        if (strpos($hook, 'toplevel_page_raplsaich') !== false || strpos($hook, 'page_raplsaich-dashboard') !== false || $hook === 'toplevel_page_raplsaich-dashboard') {
            wp_enqueue_script(
                'raplsaich-chartjs',
                RAPLSAICH_PLUGIN_URL . 'assets/vendor/chart.js/chart.umd.min.js',
                [],
                '4.5.1',
                true
            );
        }

        wp_enqueue_script(
            'raplsaich-admin',
            RAPLSAICH_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            RAPLSAICH_VERSION,
            true
        );

        wp_localize_script('raplsaich-admin', 'raplsaichAdmin', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('raplsaich_admin_nonce'),
            'restUrl'   => esc_url_raw(rest_url()),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isPro'     => get_option('raplsaich_pro_active'),
            'defaults' => self::get_all_defaults(),
            'i18n'    => [
                'confirmDelete' => __('Are you sure you want to delete?', 'rapls-ai-chatbot'),
                'confirmDeleteAll' => __('Are you sure you want to delete all?', 'rapls-ai-chatbot'),
                'processing' => __('Processing...', 'rapls-ai-chatbot'),
                'success' => __('Success', 'rapls-ai-chatbot'),
                'error' => __('Error', 'rapls-ai-chatbot'),
                'selectFile' => __('Please select a file.', 'rapls-ai-chatbot'),
                'invalidJson' => __('Please select a JSON file.', 'rapls-ai-chatbot'),
                'confirmOverwrite' => __('Current settings will be overwritten. Continue?', 'rapls-ai-chatbot'),
                'importing' => __('Importing...', 'rapls-ai-chatbot'),
                'importFailed' => __('Import failed.', 'rapls-ai-chatbot'),
                'exporting' => __('Exporting...', 'rapls-ai-chatbot'),
                'exportFailed' => __('Export failed.', 'rapls-ai-chatbot'),
                'resetConfirm' => __('All settings will be reset. API keys will also be deleted.\n\nThis action cannot be undone.\n\nTo reset, type "reset":', 'rapls-ai-chatbot'),
                'resetTypeError' => __('Please type "reset".', 'rapls-ai-chatbot'),
                'resetting' => __('Resetting...', 'rapls-ai-chatbot'),
                'resetFailed' => __('Reset failed.', 'rapls-ai-chatbot'),
                'confirmResetTab' => __('Reset all settings in this tab to defaults?', 'rapls-ai-chatbot'),
                'confirmResetField' => __('Reset this field to default?', 'rapls-ai-chatbot'),
                'loadingModels' => __('Loading models...', 'rapls-ai-chatbot'),
                'noModels' => __('No models available', 'rapls-ai-chatbot'),
                'refreshModels' => __('Refresh model list', 'rapls-ai-chatbot'),
                'modelSaved' => __('(saved)', 'rapls-ai-chatbot'),
                'rightLabel' => __('Right:', 'rapls-ai-chatbot'),
                'leftLabel' => __('Left:', 'rapls-ai-chatbot'),
                'bottomLabel' => __('Bottom:', 'rapls-ai-chatbot'),
                'topLabel' => __('Top:', 'rapls-ai-chatbot'),
                'confirmDeleteApiKey' => __('Delete this API key?\nPlease save settings after deletion.', 'rapls-ai-chatbot'),
                'keyUnset' => __('Not set (will be deleted on save)', 'rapls-ai-chatbot'),
                'enterApiKey' => __('Please enter an API key.', 'rapls-ai-chatbot'),
                'testing' => __('Testing...', 'rapls-ai-chatbot'),
                'connectionTest' => __('Connection test', 'rapls-ai-chatbot'),
                'confirmCrawl' => __('Run site-wide learning?\nThis may take a while depending on the number of pages.', 'rapls-ai-chatbot'),
                'crawling' => __('Learning...', 'rapls-ai-chatbot'),
                'exportConversations' => __('Export', 'rapls-ai-chatbot'),
                'quickReplyPlaceholder' => __('e.g., What are your business hours?', 'rapls-ai-chatbot'),
                'holidayNamePlaceholder' => __('Holiday name (optional)', 'rapls-ai-chatbot'),
                'templateNamePlaceholder' => __('Template name', 'rapls-ai-chatbot'),
                'templatePromptPlaceholder' => __('System prompt for this template...', 'rapls-ai-chatbot'),
                'exportSettings' => __('Export Settings', 'rapls-ai-chatbot'),
                'importSettings' => __('Import Settings', 'rapls-ai-chatbot'),
                'resetSettings' => __('Reset Settings', 'rapls-ai-chatbot'),
                'selectAvatar' => __('Select Avatar Image', 'rapls-ai-chatbot'),
                'useAsAvatar' => __('Use as Avatar', 'rapls-ai-chatbot'),
                'multimodalVision' => __('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot'),
                'noVisionSupport' => __('No vision support', 'rapls-ai-chatbot'),
                'copied' => __('Copied!', 'rapls-ai-chatbot'),
            ],
        ]);
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        $stats = $this->get_dashboard_stats();
        $usage_stats = RAPLSAICH_Cost_Calculator::get_usage_stats(30);
        $chart_data = RAPLSAICH_Cost_Calculator::get_chart_data(30);

        $pro = RAPLSAICH_Extensions::get_instance();
        $message_limit = $pro->get_message_limit();
        $is_unlimited = ($message_limit === PHP_INT_MAX);
        $remaining_messages = $is_unlimited ? null : $pro->get_remaining_messages();
        $used_messages = $is_unlimited ? null : ($message_limit - $remaining_messages);

        // Sort model_totals in-memory
        $allowed_model_orderby = ['ai_model', 'input_tokens', 'output_tokens', 'total_tokens', 'cost'];
        $model_orderby = isset($_GET['model_orderby']) && in_array(sanitize_text_field(wp_unslash($_GET['model_orderby'])), $allowed_model_orderby, true) ? sanitize_text_field(wp_unslash($_GET['model_orderby'])) : 'total_tokens';
        $model_order = isset($_GET['model_order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['model_order']))) === 'ASC' ? 'ASC' : 'DESC';

        if (!empty($usage_stats['model_totals'])) {
            usort($usage_stats['model_totals'], function ($a, $b) use ($model_orderby, $model_order) {
                $va = $a[$model_orderby] ?? 0;
                $vb = $b[$model_orderby] ?? 0;
                if (is_string($va)) {
                    $cmp = strcasecmp($va, $vb);
                } else {
                    $cmp = $va <=> $vb;
                }
                return $model_order === 'ASC' ? $cmp : -$cmp;
            });
        }

        include RAPLSAICH_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        $settings = get_option('raplsaich_settings', []);

        // Auto-migrate legacy enc: (CBC) keys to encg: (GCM) on settings page load
        $this->maybe_migrate_legacy_keys($settings);

        // Auto-encrypt any plaintext API keys (always, regardless of data encryption toggle)
        $this->maybe_encrypt_plaintext_keys($settings);

        $openai_provider = new RAPLSAICH_OpenAI_Provider();
        $claude_provider = new RAPLSAICH_Claude_Provider();
        $gemini_provider = new RAPLSAICH_Gemini_Provider();
        $openrouter_provider = new RAPLSAICH_OpenRouter_Provider();
        include RAPLSAICH_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Migrate legacy CBC-encrypted keys to GCM format.
     * Called on settings page load so migration happens when an admin visits settings.
     */
    private function maybe_migrate_legacy_keys(array &$settings): void {
        $key_fields = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'openrouter_api_key', 'recaptcha_secret_key'];
        $migrated = false;

        foreach ($key_fields as $field) {
            $value = $settings[$field] ?? '';
            if (empty($value)) {
                continue;
            }

            $is_encrypted = strpos($value, 'enc:') === 0 || strpos($value, 'encg:') === 0;
            if (!$is_encrypted) {
                continue;
            }

            // Decrypt fully (handles double/triple encryption from older sanitize_settings bug)
            $decrypted = $value;
            for ($i = 0; $i < 3 && (strpos($decrypted, 'encg:') === 0 || strpos($decrypted, 'enc:') === 0); $i++) {
                $inner = ($field === 'recaptcha_secret_key')
                    ? self::decrypt_secret_static($decrypted)
                    : $this->decrypt_api_key($decrypted);
                if (empty($inner)) {
                    break; // Decryption failed, stop
                }
                $decrypted = $inner;
            }

            if (empty($decrypted) || $decrypted === $value) {
                continue; // Decryption failed entirely, leave as-is
            }

            // Re-encrypt with single-layer GCM
            $re_encrypted = ($field === 'recaptcha_secret_key')
                ? $this->encrypt_secret($decrypted)
                : $this->maybe_encrypt_api_key($decrypted);

            if ($re_encrypted !== $value && strpos($re_encrypted, 'encg:') === 0) {
                $settings[$field] = $re_encrypted;
                $migrated = true;
            }
        }

        if ($migrated) {
            update_option('raplsaich_settings', $settings);
        }
    }

    /**
     * admin_init callback: auto-encrypt plaintext API keys on any admin page load.
     * Skips quickly if all keys are already encrypted (transient check).
     */
    public function maybe_encrypt_plaintext_keys_on_init(): void {
        // Quick skip: if we encrypted recently, don't re-check every request
        if (get_transient('raplsaich_keys_encrypted')) {
            return;
        }
        $settings = get_option('raplsaich_settings', []);
        $this->maybe_encrypt_plaintext_keys($settings);
        // Cache for 1 hour — re-check after that in case keys were changed externally
        set_transient('raplsaich_keys_encrypted', 1, HOUR_IN_SECONDS);
    }

    /**
     * Auto-encrypt plaintext API keys.
     * API keys are always encrypted regardless of the "data encryption" toggle
     * (which controls message encryption).
     */
    private function maybe_encrypt_plaintext_keys(array &$settings): void {
        $key_fields = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'openrouter_api_key', 'recaptcha_secret_key'];
        $changed = false;

        foreach ($key_fields as $field) {
            $value = $settings[$field] ?? '';
            if (empty($value)) {
                continue;
            }
            // Already encrypted
            if (strpos($value, 'enc:') === 0 || strpos($value, 'encg:') === 0) {
                continue;
            }
            // Encrypt unconditionally (encrypt_secret has no prefix check, unlike maybe_encrypt_api_key)
            $encrypted = $this->encrypt_secret($value);
            if ($encrypted !== $value && strpos($encrypted, 'encg:') === 0) {
                $settings[$field] = $encrypted;
                $changed = true;
            }
        }

        if ($changed) {
            // Bypass sanitize filter to avoid re-processing
            global $wp_filter;
            $saved = isset($wp_filter['sanitize_option_raplsaich_settings']) ? $wp_filter['sanitize_option_raplsaich_settings'] : null;
            remove_all_filters('sanitize_option_raplsaich_settings');
            update_option('raplsaich_settings', $settings);
            if ($saved !== null) {
                $wp_filter['sanitize_option_raplsaich_settings'] = $saved;
            }
        }
    }

    /**
     * Render crawler page
     */
    public function render_crawler_page(): void {
        $crawler = new RAPLSAICH_Site_Crawler();
        $status = $crawler->get_status();
        $is_pro_active = get_option('raplsaich_pro_active');

        // Sort parameters
        $allowed_orderby = ['title', 'post_type', 'indexed_at'];
        $orderby = isset($_GET['orderby']) && in_array(sanitize_text_field(wp_unslash($_GET['orderby'])), $allowed_orderby, true) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'indexed_at';
        $order = isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';

        $indexed_list = RAPLSAICH_Content_Index::get_list([
            'per_page' => 50,
            'orderby'  => $orderby,
            'order'    => $order,
        ]);

        // Post type statistics
        $post_type_counts = RAPLSAICH_Content_Index::get_post_type_counts();

        include RAPLSAICH_PLUGIN_DIR . 'templates/admin/crawler.php';
    }

    /**
     * Auto-close conversations inactive for 30+ minutes.
     * Called on conversations page render to supplement cron.
     */
    private function auto_close_stale_conversations(): void {
        $table = raplsaich_require_table('raplsaich_conversations', __METHOD__);
        if (!$table) {
            return;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query(
            "UPDATE {$table} SET status = 'closed' WHERE status = 'active' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
    }

    /**
     * Render conversations page
     */
    public function render_conversations_page(): void {
        // Auto-close stale conversations on page view (supplements cron for environments like Local by Flywheel)
        $this->auto_close_stale_conversations();

        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;

        // Sort parameters
        $allowed_orderby = ['id', 'session_id', 'message_count', 'page_url', 'status', 'handoff_status', 'created_at', 'updated_at'];
        $orderby = isset($_GET['orderby']) && in_array(sanitize_text_field(wp_unslash($_GET['orderby'])), $allowed_orderby, true) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at';
        $order = isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';

        // Filter parameters
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $list_args = [
            'page'      => $page,
            'per_page'  => 20,
            'orderby'   => $orderby,
            'order'     => $order,
            'search'    => $search,
            'status'    => $status_filter,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ];

        $conversations = RAPLSAICH_Conversation::get_list($list_args);
        $has_filters = $search !== '' || $status_filter !== '' || $date_from !== '' || $date_to !== '';
        $total = $has_filters ? RAPLSAICH_Conversation::get_filtered_count($list_args) : RAPLSAICH_Conversation::get_count();

        // Statistics
        $conv_stats = [
            'total'    => RAPLSAICH_Conversation::get_count(),
            'active'   => RAPLSAICH_Conversation::get_count('active'),
            'closed'   => RAPLSAICH_Conversation::get_count('closed'),
            'archived' => RAPLSAICH_Conversation::get_count('archived'),
            'today'    => RAPLSAICH_Conversation::get_today_count(),
            'handoff'  => RAPLSAICH_Conversation::get_handoff_count(),
        ];

        include RAPLSAICH_PLUGIN_DIR . 'templates/admin/conversations.php';
    }

    /**
     * Render knowledge page
     */
    public function render_knowledge_page(): void {
        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        $category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        // Sort parameters
        $allowed_orderby = ['id', 'title', 'category', 'priority', 'created_at'];
        $orderby = isset($_GET['orderby']) && in_array(sanitize_text_field(wp_unslash($_GET['orderby'])), $allowed_orderby, true) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'priority';
        $order = isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';

        $list_args = [
            'page'     => $page,
            'per_page' => 20,
            'category' => $category,
            'orderby'  => $orderby,
            'order'    => $order,
        ];
        if (!empty($status_filter)) {
            $list_args['status'] = $status_filter;
        }

        $knowledge_list = RAPLSAICH_Knowledge::get_list($list_args);
        $total = RAPLSAICH_Knowledge::get_count($category, null, $status_filter);
        $categories = RAPLSAICH_Knowledge::get_categories();
        $draft_count = RAPLSAICH_Knowledge::get_draft_count();

        // Statistics
        $knowledge_stats = [
            'total'      => RAPLSAICH_Knowledge::get_count(),
            'active'     => RAPLSAICH_Knowledge::get_count('', 1),
            'inactive'   => RAPLSAICH_Knowledge::get_count('', 0),
            'categories' => count($categories),
        ];

        include RAPLSAICH_PLUGIN_DIR . 'templates/admin/knowledge.php';
    }

    /**
     * Get dashboard stats
     */
    private function get_dashboard_stats(): array {
        return [
            'total_conversations' => RAPLSAICH_Conversation::get_count(),
            'today_messages'      => RAPLSAICH_Message::get_today_count(),
            'indexed_pages'       => RAPLSAICH_Content_Index::get_count(),
            'knowledge_count'     => RAPLSAICH_Knowledge::get_count('', 1),
            'total_tokens'        => RAPLSAICH_Message::get_total_tokens(30),
        ];
    }

    /**
     * Manual crawl AJAX
     */
    public function ajax_manual_crawl(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $crawler = new RAPLSAICH_Site_Crawler();
        $results = $crawler->crawl_all_manual();

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: number of indexed items, 2: number of updated items, 3: number of skipped items */
                __('Crawl complete: %1$d indexed, %2$d updated, %3$d skipped', 'rapls-ai-chatbot'),
                $results['indexed'],
                $results['updated'],
                $results['skipped']
            ),
            'results' => $results,
        ]);
    }

    /**
     * Delete index by post_id AJAX
     */
    public function ajax_delete_index(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = absint(wp_unslash($_POST['post_id'] ?? 0));
        $index_id = absint(wp_unslash($_POST['index_id'] ?? 0));

        if (!$post_id && !$index_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        // Delete by primary key (index_id) for external URLs (post_id=0), or by post_id for WP content
        $result = $index_id
            ? RAPLSAICH_Content_Index::delete_by_id($index_id)
            : RAPLSAICH_Content_Index::delete_by_post_id($post_id);

        if ($result !== false) {
            wp_send_json_success([
                'message' => __('Index deleted.', 'rapls-ai-chatbot'),
            ]);
        } else {
            wp_send_json_error(__('Failed to delete.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Delete all index AJAX
     */
    public function ajax_delete_all_index(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('delete_all_index')) {
            return;
        }

        $result = RAPLSAICH_Content_Index::truncate();

        // Clear last crawl status
        delete_option('raplsaich_last_crawl');
        delete_option('raplsaich_last_crawl_results');

        wp_send_json_success([
            'message' => __('All index data deleted.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * Exclude a post from crawler AJAX
     */
    public function ajax_crawler_exclude_post(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = absint(wp_unslash($_POST['post_id'] ?? 0));
        if (!$post_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $settings = get_option('raplsaich_settings', []);
        $exclude_ids = $settings['crawler_exclude_ids'] ?? [];

        if (!in_array($post_id, $exclude_ids, true)) {
            $exclude_ids[] = $post_id;
            $settings['crawler_exclude_ids'] = array_values($exclude_ids);
            update_option('raplsaich_settings', $settings);
        }

        // Remove from index
        RAPLSAICH_Content_Index::delete_by_post_id($post_id);

        wp_send_json_success([
            /* translators: notification after excluding a page from learning */
            'message' => __('Excluded from learning.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * Re-include a post in crawler AJAX
     */
    public function ajax_crawler_include_post(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = absint(wp_unslash($_POST['post_id'] ?? 0));
        if (!$post_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $settings = get_option('raplsaich_settings', []);
        $exclude_ids = $settings['crawler_exclude_ids'] ?? [];
        $exclude_ids = array_values(array_diff($exclude_ids, [$post_id]));
        $settings['crawler_exclude_ids'] = $exclude_ids;
        update_option('raplsaich_settings', $settings);

        wp_send_json_success([
            /* translators: notification after removing a page from the exclusion list */
            'message' => __('Exclusion removed.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * API connection test AJAX
     */
    public function ajax_test_api(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'openai'));
        // Use lighter sanitization for API keys — sanitize_text_field strips characters
        // that some providers may use in key formats. Only remove control chars and trim.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API keys contain special chars that sanitize_text_field strips
        $api_key = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_unslash($_POST['api_key'] ?? '')));
        $use_saved = !empty(wp_unslash($_POST['use_saved'] ?? ''));

        // If no key entered but use_saved flag set, decrypt the saved key
        if (empty($api_key) && $use_saved) {
            $settings = get_option('raplsaich_settings', []);
            $key_field = $provider . '_api_key';
            $saved = $settings[$key_field] ?? '';
            if (!empty($saved)) {
                $api_key = $this->decrypt_api_key($saved);
            }
        }

        if (empty($api_key)) {
            wp_send_json_error(__('Please enter an API key.', 'rapls-ai-chatbot'));
        }

        try {
            if ($provider === 'claude') {
                $ai = new RAPLSAICH_Claude_Provider();
            } elseif ($provider === 'gemini') {
                $ai = new RAPLSAICH_Gemini_Provider();
            } elseif ($provider === 'openrouter') {
                $ai = new RAPLSAICH_OpenRouter_Provider();
            } else {
                $ai = new RAPLSAICH_OpenAI_Provider();
            }

            $ai->set_api_key($api_key);

            if ($ai->validate_api_key()) {
                wp_send_json_success(__('Connection successful! API key is valid.', 'rapls-ai-chatbot'));
            } else {
                wp_send_json_error(__('Invalid API key.', 'rapls-ai-chatbot'));
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH ajax_test_api: ' . $e->getMessage());
            }
            self::log_diagnostic_event('api_test_failed');
            wp_send_json_error(__('API request failed. Please check your API key and try again.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Fetch models from API via AJAX
     */
    public function ajax_fetch_models(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'openai'));
        // Use lighter sanitization for API keys — sanitize_text_field strips characters
        // that some providers may use in key formats. Only remove control chars and trim.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API keys contain special chars that sanitize_text_field strips
        $api_key = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_unslash($_POST['api_key'] ?? '')));
        $use_saved = !empty(wp_unslash($_POST['use_saved'] ?? ''));
        $force_refresh = !empty(wp_unslash($_POST['force_refresh'] ?? ''));

        // Use saved API key if requested
        if ($use_saved || empty($api_key)) {
            $settings = get_option('raplsaich_settings', []);
            $key_field = $provider . '_api_key';
            $saved_key = $settings[$key_field] ?? '';
            if (!empty($saved_key)) {
                $api_key = $this->decrypt_api_key($saved_key);
            }
        }

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required.', 'rapls-ai-chatbot'));
        }

        // Create provider instance
        switch ($provider) {
            case 'claude':
                $ai = new RAPLSAICH_Claude_Provider();
                break;
            case 'gemini':
                $ai = new RAPLSAICH_Gemini_Provider();
                break;
            case 'openrouter':
                $ai = new RAPLSAICH_OpenRouter_Provider();
                break;
            default:
                $ai = new RAPLSAICH_OpenAI_Provider();
                $provider = 'openai';
                break;
        }

        $ai->set_api_key($api_key);

        // Delete cache if force refresh
        if ($force_refresh) {
            $cache_key = 'raplsaich_models_' . $provider . '_v2_' . md5($api_key);
            delete_transient($cache_key);
            // Also delete old cache key format
            delete_transient('raplsaich_models_' . $provider . '_' . md5($api_key));
        }

        // Try API fetch
        $models = $ai->fetch_models_from_api();
        $source = 'api';

        if (empty($models)) {
            // Fallback to hardcoded list
            $models = $ai->get_available_models();
            $source = 'hardcoded';
        } else {
            // Check if from cache (use v2 key format to match provider cache)
            $cache_key_v2 = 'raplsaich_models_' . $provider . '_v2_' . md5($api_key);
            if (get_transient($cache_key_v2) !== false && !$force_refresh) {
                $source = 'cached';
            }
        }

        // Build vision models list
        $vision_models = [];
        if ($provider === 'openai') {
            foreach (array_keys($models) as $model_id) {
                if ($ai->is_vision_model($model_id)) {
                    $vision_models[] = $model_id;
                }
            }
        } elseif ($provider === 'claude') {
            $vision_models = $ai->get_vision_models();
        } elseif ($provider === 'gemini') {
            // All Gemini 1.5+/2.0 models support vision
            foreach (array_keys($models) as $model_id) {
                if (strpos($model_id, 'gemini-1.5') !== false ||
                    strpos($model_id, 'gemini-2') !== false) {
                    $vision_models[] = $model_id;
                }
            }
        }

        wp_send_json_success([
            'models'        => $models,
            'vision_models' => $vision_models,
            'source'        => $source,
        ]);
    }

    /**
     * Decrypt API key.
     *
     * Delegates to the global raplsaich_decrypt_api_key() helper and sets
     * a transient on failure so the admin notice can be displayed.
     */
    private function decrypt_api_key(string $encrypted): string {
        $decrypted = raplsaich_decrypt_api_key($encrypted);

        // Set transient on failure so admin notice is shown
        if ($decrypted === '' && !empty($encrypted) && strpos($encrypted, 'sk-') !== 0 && strpos($encrypted, 'AIza') !== 0) {
            if (!get_transient('raplsaich_api_key_decryption_failed')) {
                set_transient('raplsaich_api_key_decryption_failed', true, HOUR_IN_SECONDS);
            }
        }

        return $decrypted;
    }

    /**
     * Show admin notice when API key decryption fails
     */
    public function api_key_decryption_notice(): void {
        if (!get_transient('raplsaich_api_key_decryption_failed')) {
            return;
        }
        if (!current_user_can(self::get_manage_cap())) {
            return;
        }
        $settings_url = admin_url('admin.php?page=raplsaich-settings');
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Rapls AI Chatbot:', 'rapls-ai-chatbot'); ?></strong>
                <?php
                printf(
                    /* translators: %s: link to settings page */
                    esc_html__('API key decryption failed. This may happen after a site migration or when WordPress security salts are changed. Please re-enter your API key in %s.', 'rapls-ai-chatbot'),
                    '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'rapls-ai-chatbot') . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
        // Clear the transient once shown
        delete_transient('raplsaich_api_key_decryption_failed');
    }

    /**
     * Show build identifier in admin footer on plugin pages.
     *
     * @param string $text Existing footer text.
     * @return string Modified footer text.
     */
    public function admin_footer_build_info($text) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'raplsaich') !== 0) {
            return $text;
        }
        $build = defined('RAPLSAICH_BUILD') ? RAPLSAICH_BUILD : '';
        if ($build && strpos($build, 'Format') === false) {
            $text .= ' | Rapls AI Chatbot v' . esc_html(RAPLSAICH_VERSION) . ' build ' . esc_html($build);
        } else {
            $text .= ' | Rapls AI Chatbot v' . esc_html(RAPLSAICH_VERSION);
        }
        return $text;
    }

    /**
     * Show admin notice when public API defense settings are weak
     */
    public function security_settings_notice(): void {
        if (!current_user_can(self::get_manage_cap())) {
            return;
        }

        $settings = get_option('raplsaich_settings', []);
        $errors   = [];
        $warnings = [];

        // Check reCAPTCHA misconfiguration (critical — chat is broken)
        $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
        $recaptcha_site_key_set = !empty($settings['recaptcha_site_key']);
        $recaptcha_secret_set = !empty($settings['recaptcha_secret_key']);

        if ($recaptcha_enabled && (!$recaptcha_site_key_set || !$recaptcha_secret_set)) {
            $errors[] = __('reCAPTCHA is enabled but not fully configured (missing site key or secret key). Chat, lead, and offline endpoints will reject all requests until configured.', 'rapls-ai-chatbot');
        } elseif (!$recaptcha_enabled) {
            $warnings[] = __('reCAPTCHA is not enabled. Public chat and lead form endpoints are unprotected against bots.', 'rapls-ai-chatbot');
        }

        // Check rate limit
        $rate_limit = intval($settings['rate_limit'] ?? 20);
        if ($rate_limit <= 0) {
            $warnings[] = __('Rate limiting is disabled. Public API endpoints have no request frequency restrictions.', 'rapls-ai-chatbot');
        }

        // Check WP-Cron (informational — chat works without it, but crawl/cleanup won't run)
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $warnings[] = __('WP-Cron is disabled (DISABLE_WP_CRON). Scheduled tasks (site crawl, conversation cleanup) will not run automatically. Chat functionality is not affected. If you use a server-side cron (crontab) to trigger wp-cron.php, you can safely ignore this notice.', 'rapls-ai-chatbot');
        }

        // M-1: Check SameSite=None on non-SSL site (cookie will silently fail in browsers)
        $samesite = apply_filters('raplsaich_cookie_samesite', 'Lax');
        if ($samesite === 'None' && !is_ssl()) {
            $errors[] = __('SameSite=None is set via the raplsaich_cookie_samesite filter, but this site does not use HTTPS. Browsers require Secure cookies for SameSite=None, so the session cookie will not be sent. Sessions will reset on every page load. Remove the filter or switch to HTTPS.', 'rapls-ai-chatbot');
        }

        // Proxy trust without trusted proxies configured
        if (!empty($settings['trust_proxy_ip'])) {
            $raw_proxies = (array) apply_filters('raplsaich_trusted_proxies', []);
            if (empty($raw_proxies) && empty($settings['trust_cloudflare_ip'])) {
                $warnings[] = __('Reverse proxy trust is enabled but no trusted proxy IPs are configured via the raplsaich_trusted_proxies filter. X-Forwarded-For header will only be trusted from private/loopback IPs. Add your proxy IPs via the filter for correct IP detection.', 'rapls-ai-chatbot');
            }
        }

        $settings_url = admin_url('admin.php?page=raplsaich-settings');

        // Critical errors (red, not dismissible, shown on all admin pages)
        if (!empty($errors)) {
            ?>
            <div class="notice notice-error" id="raplsaich-security-error-notice">
                <p>
                    <strong><?php esc_html_e('Rapls AI Chatbot - Configuration Error:', 'rapls-ai-chatbot'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Go to Settings', 'rapls-ai-chatbot'); ?></a>
                </p>
            </div>
            <?php
        }

        // Warnings (yellow, dismissible, only on plugin pages)
        if (empty($warnings)) {
            return;
        }

        // Only show warnings on our plugin pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'raplsaich') !== 0) {
            return;
        }

        // Allow dismissing for 30 days
        if (get_transient('raplsaich_security_notice_dismissed')) {
            return;
        }

        $dismiss_nonce = wp_create_nonce('raplsaich_dismiss_security_notice');
        ?>
        <div class="notice notice-warning is-dismissible" id="raplsaich-security-notice">
            <p>
                <strong><?php esc_html_e('Rapls AI Chatbot - Security:', 'rapls-ai-chatbot'); ?></strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($warnings as $warning) : ?>
                    <li><?php echo esc_html($warning); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Go to Settings', 'rapls-ai-chatbot'); ?></a>
                &nbsp;|&nbsp;
                <a href="#" id="raplsaich-dismiss-security" style="color: #999;"><?php esc_html_e('Dismiss for 30 days', 'rapls-ai-chatbot'); ?></a>
            </p>
        </div>
        <?php
        $dismiss_js = sprintf(
            'document.getElementById("raplsaich-dismiss-security").addEventListener("click",function(e){' .
            'e.preventDefault();var x=new XMLHttpRequest();' .
            'x.open("POST","%s");x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");' .
            'x.onload=function(){var n=document.getElementById("raplsaich-security-notice");if(n)n.remove();};' .
            'x.send("action=raplsaich_dismiss_security_notice&_wpnonce=%s");});',
            esc_url(admin_url('admin-ajax.php')),
            esc_js($dismiss_nonce)
        );
        wp_add_inline_script('raplsaich-admin', $dismiss_js);
    }

    /**
     * Encrypt a secret value (general purpose, idempotent — skips if already encrypted)
     */
    private function encrypt_secret(string $value): string {
        if (empty($value) || !function_exists('openssl_encrypt')) {
            return $value;
        }

        // Already encrypted (GCM or legacy CBC) — prevent double encryption
        if (strpos($value, 'encg:') === 0 || strpos($value, 'enc:') === 0) {
            return $value;
        }

        // Use AES-256-GCM (authenticated encryption with tamper detection)
        $encryption_key = self::get_encryption_key();
        $aad = self::get_encryption_aad();
        $iv = openssl_random_pseudo_bytes(12); // 12 bytes recommended for GCM
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);

        if ($encrypted === false) {
            return $value;
        }

        // Format: encg: + base64(iv[12] + tag[16] + ciphertext)
        return 'encg:' . base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt a secret value encrypted by encrypt_secret()
     */
    public static function decrypt_secret_static(string $value): string {
        if (empty($value)) {
            return $value;
        }

        // Must have enc: or encg: prefix to be treated as encrypted
        $is_gcm = strpos($value, 'encg:') === 0;
        $is_cbc = strpos($value, 'enc:') === 0;

        if (!$is_gcm && !$is_cbc) {
            return $value;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $new_key = self::get_encryption_key();
        $aad = self::get_encryption_aad();
        $old_key = wp_salt('auth');

        // AES-256-GCM (new format with tamper detection)
        if ($is_gcm) {
            $data = base64_decode(substr($value, 5), true);
            if ($data === false || strlen($data) <= 28) { // 12 (IV) + 16 (tag) = 28 minimum
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('RAPLSAICH: Secret decryption failed (invalid GCM data).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return '';
            }

            $iv  = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $encrypted_data = substr($data, 28);

            // Try normalized key + AAD → normalized key → legacy key
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag);
            }
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $old_key, OPENSSL_RAW_DATA, $iv, $tag);
            }

            if ($decrypted === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('RAPLSAICH: Secret decryption failed (GCM auth failed).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return '';
            }

            return $decrypted;
        }

        // AES-256-CBC (legacy format, no tamper detection)
        $data = base64_decode(substr($value, 4), true);

        if ($data === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('RAPLSAICH: Secret decryption failed (invalid base64).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($data) <= $iv_length) {
            return '';
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted_data = substr($data, $iv_length);

        // Try hash-normalized key first, then legacy raw salt
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $new_key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $old_key, OPENSSL_RAW_DATA, $iv);
        }

        if ($decrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('RAPLSAICH: Secret decryption failed (salt may have changed).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        return $decrypted;
    }

    /**
     * Decrypt a secret value encrypted by encrypt_secret() (instance method)
     */
    private function decrypt_secret(string $value): string {
        return self::decrypt_secret_static($value);
    }

    /**
     * Get conversation messages AJAX
     */
    public function ajax_get_conversation_messages(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));

        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $messages = RAPLSAICH_Message::get_by_conversation($conversation_id);
        $conversation = RAPLSAICH_Conversation::get_by_id($conversation_id);

        $formatted = array_map(function($msg) {
            $data = [
                'id'         => (int) $msg['id'],
                'role'       => $msg['role'],
                'content'    => $msg['content'],
                'created_at' => mysql2date('Y/m/d H:i:s', $msg['created_at']),
            ];
            // Add metadata for assistant messages
            if ($msg['role'] === 'assistant') {
                if (isset($msg['feedback'])) {
                    $data['feedback'] = (int) $msg['feedback'];
                }
                if (!empty($msg['ai_model'])) {
                    $data['ai_model'] = $msg['ai_model'];
                }
                if (!empty($msg['tokens_used'])) {
                    $data['tokens'] = (int) $msg['tokens_used'];
                }
                if (!empty($msg['cache_hit'])) {
                    $data['cache_hit'] = true;
                }
            }
            return $data;
        }, $messages);

        wp_send_json_success([
            'messages'       => $formatted,
            'conversation_id' => $conversation_id,
            'handoff_status' => $conversation['handoff_status'] ?? null,
        ]);
    }

    /**
     * Delete conversation AJAX
     */
    public function ajax_delete_conversation(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));

        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Conversation::delete($conversation_id);

        if ($result) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('conversation_deleted', 'conversation', $conversation_id);
            }
            wp_send_json_success(__('Conversation deleted.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Archive conversation AJAX
     */
    public function ajax_archive_conversation(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));
        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Conversation::update_status($conversation_id, 'archived');
        if ($result) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('conversation_archived', 'conversation', $conversation_id);
            }
            wp_send_json_success(__('Conversation archived.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to archive.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Unarchive (restore) conversation AJAX
     */
    public function ajax_unarchive_conversation(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));
        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Conversation::update_status($conversation_id, 'closed');
        if ($result) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('conversation_unarchived', 'conversation', $conversation_id);
            }
            wp_send_json_success(__('Conversation restored.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to restore.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Bulk delete conversations AJAX
     */
    public function ajax_delete_conversations_bulk(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint sanitizes each element
        $ids = isset($_POST['conversation_ids']) ? array_map('absint', wp_unslash((array) $_POST['conversation_ids'])) : [];

        if (empty($ids)) {
            wp_send_json_error(__('No conversations selected for deletion.', 'rapls-ai-chatbot'));
        }

        $deleted = RAPLSAICH_Conversation::delete_multiple($ids);

        /* translators: %d: number of deleted conversations */
        wp_send_json_success(sprintf(__('%d conversations deleted.', 'rapls-ai-chatbot'), $deleted));
    }

    /**
     * Delete all conversations AJAX
     */
    public function ajax_delete_all_conversations(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('delete_all_conversations')) {
            return;
        }

        RAPLSAICH_Conversation::delete_all();

        wp_send_json_success(__('All conversation history deleted.', 'rapls-ai-chatbot'));
    }

    /**
     * Reset handoff status AJAX
     */
    public function ajax_reset_handoff(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = isset($_POST['conversation_id']) ? absint(wp_unslash($_POST['conversation_id'])) : 0;
        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID.', 'rapls-ai-chatbot'));
        }

        $pro = RAPLSAICH_Extensions::get_instance();
        $pro->cancel_handoff($conversation_id);

        wp_send_json_success(__('Handoff status reset.', 'rapls-ai-chatbot'));
    }

    /**
     * Add knowledge AJAX
     */
    public function ajax_add_knowledge(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $priority = min(100, max(0, absint(wp_unslash($_POST['priority'] ?? 0))));
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'qa'));
        if (!in_array($type, ['qa', 'template'], true)) {
            $type = 'qa';
        }

        if (empty($title) || empty($content)) {
            wp_send_json_error(__('Title and content are required.', 'rapls-ai-chatbot'));
        }

        // Check FAQ limit (always passes in Free — no artificial limits)
        $pro_features = RAPLSAICH_Extensions::get_instance();
        if (!$pro_features->can_add_faq()) {
            wp_send_json_error(__('Unable to add knowledge entry.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Knowledge::create([
            'title'    => $title,
            'content'  => $content,
            'category' => $category,
            'priority' => $priority,
            'type'     => $type,
        ]);

        if ($result) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('knowledge_created', 'knowledge', (int) $result['id'], ['title' => $title]);
            }
            wp_send_json_success([
                'message' => __('Knowledge added.', 'rapls-ai-chatbot'),
                'id'      => $result['id'],
            ]);
        } else {
            wp_send_json_error(__('Failed to add.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Import knowledge from file AJAX
     */
    public function ajax_import_knowledge(): void {
        try {
            check_ajax_referer('raplsaich_admin_nonce', 'nonce');

            if (!current_user_can(self::get_manage_cap())) {
                wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
            }

            if (empty($_FILES['file'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload handled by wp_handle_upload
                wp_send_json_error(__('No file uploaded.', 'rapls-ai-chatbot'));
            }

            $file = $_FILES['file'];
            $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));

            // Check file upload error
            if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE   => __('File size too large (php.ini limit)', 'rapls-ai-chatbot'),
                    UPLOAD_ERR_FORM_SIZE  => __('File size too large (form limit)', 'rapls-ai-chatbot'),
                    UPLOAD_ERR_PARTIAL    => __('File was only partially uploaded', 'rapls-ai-chatbot'),
                    UPLOAD_ERR_NO_FILE    => __('No file was uploaded', 'rapls-ai-chatbot'),
                    UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder', 'rapls-ai-chatbot'),
                    UPLOAD_ERR_CANT_WRITE => __('Failed to write to disk', 'rapls-ai-chatbot'),
                ];
                $error_msg = $upload_errors[$file['error']] ?? __('Upload failed due to a server error.', 'rapls-ai-chatbot');
                wp_send_json_error($error_msg);
            }

            // Check file size (5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                wp_send_json_error(__('File size must be 5MB or less.', 'rapls-ai-chatbot'));
            }

            // Check FAQ limit (always passes in Free — no artificial limits)
            $pro_features = RAPLSAICH_Extensions::get_instance();
            if (!$pro_features->can_add_faq()) {
                wp_send_json_error(__('Unable to import knowledge file.', 'rapls-ai-chatbot'));
            }

            $result = RAPLSAICH_Knowledge::import_from_file($file, $category);

            if (is_wp_error($result)) {
                // Return the error code (safe, fixed string) rather than potentially dynamic message
                $safe_messages = [
                    'invalid_file_type' => __('Invalid file type. Please upload a CSV or JSON file.', 'rapls-ai-chatbot'),
                    'empty_file'        => __('The uploaded file is empty.', 'rapls-ai-chatbot'),
                    'parse_error'       => __('Could not parse the file. Please check the format.', 'rapls-ai-chatbot'),
                ];
                $code = $result->get_error_code();
                $msg = $safe_messages[$code] ?? __('Import failed. Please check the file format and try again.', 'rapls-ai-chatbot');
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('RAPLSAICH import error [' . $code . ']: ' . $result->get_error_message());
                }
                self::log_diagnostic_event('import_' . $code);
                wp_send_json_error($msg);
            }

            if (empty($result) || !is_array($result)) {
                wp_send_json_error(__('Failed to save data.', 'rapls-ai-chatbot'));
            }

            wp_send_json_success([
                'message' => __('File imported.', 'rapls-ai-chatbot'),
                'id'      => $result['id'] ?? 0,
                'title'   => $result['title'] ?? '',
            ]);
        } catch (Exception $e) {
            self::log_diagnostic_event('import_exception');
            wp_send_json_error(__('An error occurred.', 'rapls-ai-chatbot'));
        } catch (Error $e) {
            self::log_diagnostic_event('import_fatal');
            wp_send_json_error(__('An error occurred.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Get knowledge AJAX
     */
    public function ajax_get_knowledge(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $knowledge = RAPLSAICH_Knowledge::get_by_id($id);

        if (!$knowledge) {
            wp_send_json_error(__('Knowledge not found.', 'rapls-ai-chatbot'));
        }

        wp_send_json_success($knowledge);
    }

    /**
     * Update knowledge AJAX
     */
    public function ajax_update_knowledge(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $priority = min(100, max(0, absint(wp_unslash($_POST['priority'] ?? 0))));

        if (!$id || empty($title) || empty($content)) {
            wp_send_json_error(__('Required fields are missing.', 'rapls-ai-chatbot'));
        }

        $update_data = [
            'title'    => $title,
            'content'  => $content,
            'category' => $category,
            'priority' => $priority,
        ];

        if (!empty($type) && in_array($type, ['qa', 'template'], true)) {
            $update_data['type'] = $type;
        }

        $result = RAPLSAICH_Knowledge::update($id, $update_data);

        if ($result !== false) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('knowledge_updated', 'knowledge', $id, ['title' => $title]);
            }
            wp_send_json_success(__('Knowledge updated.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to update.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Delete knowledge AJAX
     */
    public function ajax_delete_knowledge(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Knowledge::delete($id);

        if ($result) {
            if (class_exists('RAPLSAICH_Audit_Logger')) {
                RAPLSAICH_Audit_Logger::log('knowledge_deleted', 'knowledge', $id);
            }
            wp_send_json_success(__('Knowledge deleted.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Toggle knowledge active status AJAX
     */
    public function ajax_toggle_knowledge(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $is_active = isset($_POST['is_active']) ? absint(wp_unslash($_POST['is_active'])) : 1;

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = RAPLSAICH_Knowledge::update($id, ['is_active' => $is_active]);

        if ($result !== false) {
            $status = $is_active ? __('enabled', 'rapls-ai-chatbot') : __('disabled', 'rapls-ai-chatbot');
            /* translators: %s: status (enabled/disabled) */
            wp_send_json_success(sprintf(__('Knowledge %s.', 'rapls-ai-chatbot'), $status));
        } else {
            wp_send_json_error(__('Failed to update.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Update knowledge priority AJAX
     */
    public function ajax_update_priority(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $priority = absint(wp_unslash($_POST['priority'] ?? 0));

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        // Clamp priority to 0-100
        $priority = min(100, max(0, $priority));

        $result = RAPLSAICH_Knowledge::update($id, ['priority' => $priority]);

        if ($result !== false) {
            wp_send_json_success(__('Priority updated.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to update.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Export settings AJAX
     */
    public function ajax_export_settings(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $include_knowledge = !empty($_POST['include_knowledge']);

        $export_data = [
            'version' => RAPLSAICH_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => get_option('raplsaich_settings', []),
        ];

        // Exclude API keys and secrets for security
        $sensitive_keys = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'openrouter_api_key', 'recaptcha_secret_key', 'mcp_api_key_hash'];
        foreach ($sensitive_keys as $key) {
            if (isset($export_data['settings'][$key])) {
                $export_data['settings'][$key] = '';
            }
        }
        // Also strip secrets from nested pro_features
        $pro_sensitive = ['webhook_secret', 'line_channel_secret', 'line_channel_access_token', 'slack_webhook_url', 'google_sheets_url'];
        if (isset($export_data['settings']['pro_features']) && is_array($export_data['settings']['pro_features'])) {
            foreach ($pro_sensitive as $key) {
                if (isset($export_data['settings']['pro_features'][$key])) {
                    $export_data['settings']['pro_features'][$key] = '';
                }
            }
        }

        // Include knowledge data if requested
        if ($include_knowledge) {
            $knowledge_list = RAPLSAICH_Knowledge::get_list(['per_page' => 9999]);
            $export_data['knowledge'] = $knowledge_list;
        }

        wp_send_json_success($export_data);
    }

    /**
     * Import settings AJAX
     */
    public function ajax_import_settings(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below
        $import_json = wp_unslash($_POST['import_data'] ?? '');
        if (empty($import_json)) {
            wp_send_json_error(__('No import data provided.', 'rapls-ai-chatbot'));
        }

        // Size limit: 200KB max
        if (strlen($import_json) > 200 * 1024) {
            wp_send_json_error(__('Import data is too large. Maximum 200KB.', 'rapls-ai-chatbot'));
        }

        $import_data = json_decode($import_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log_diagnostic_event('settings_import_invalid_json');
            wp_send_json_error(__('Invalid JSON data.', 'rapls-ai-chatbot'));
        }

        // Import settings (allowlist-filtered)
        if (isset($import_data['settings']) && is_array($import_data['settings'])) {
            $current_settings = get_option('raplsaich_settings', []);
            $allowed_keys = array_keys(self::get_all_defaults());

            // Filter to only allowed keys
            $filtered_settings = array_intersect_key($import_data['settings'], array_flip($allowed_keys));

            // Keep current API keys (never overwrite with empty)
            $sensitive_keys = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'openrouter_api_key', 'recaptcha_secret_key', 'mcp_api_key_hash'];
            foreach ($sensitive_keys as $key) {
                if (!empty($current_settings[$key]) && empty($filtered_settings[$key])) {
                    $filtered_settings[$key] = $current_settings[$key];
                }
            }
            // Keep current Pro secrets (never overwrite with empty)
            $pro_sensitive = ['webhook_secret', 'line_channel_secret', 'line_channel_access_token', 'slack_webhook_url', 'google_sheets_url'];
            $current_pro = $current_settings['pro_features'] ?? [];
            if (isset($filtered_settings['pro_features']) && is_array($filtered_settings['pro_features'])) {
                foreach ($pro_sensitive as $key) {
                    if (!empty($current_pro[$key]) && empty($filtered_settings['pro_features'][$key])) {
                        $filtered_settings['pro_features'][$key] = $current_pro[$key];
                    }
                }
            }

            // Merge with current settings (preserve keys not in import)
            $merged_settings = array_merge($current_settings, $filtered_settings);

            // Pass through sanitize_settings() to ensure imported values are valid
            // (clamps out-of-range numbers, sanitizes strings, etc.)
            $merged_settings = $this->sanitize_settings_values($merged_settings, $current_settings);

            update_option('raplsaich_settings', $merged_settings);
        }

        // Import knowledge data
        $knowledge_count = 0;
        if (isset($import_data['knowledge']) && is_array($import_data['knowledge'])) {
            foreach ($import_data['knowledge'] as $item) {
                $result = RAPLSAICH_Knowledge::create([
                    'title'     => sanitize_text_field($item['title'] ?? ''),
                    'content'   => wp_kses_post($item['content'] ?? ''),
                    'category'  => sanitize_text_field($item['category'] ?? ''),
                    'is_active' => absint($item['is_active'] ?? 1),
                ]);
                if ($result && !is_wp_error($result)) {
                    $knowledge_count++;
                }
            }
        }

        $message = __('Settings imported.', 'rapls-ai-chatbot');
        if ($knowledge_count > 0) {
            /* translators: %d: number of knowledge items */
            $message .= ' ' . sprintf(__('Knowledge: %d items', 'rapls-ai-chatbot'), $knowledge_count);
        }

        wp_send_json_success($message);
    }


    /**
     * Get all default settings
     */
    public static function get_all_defaults(): array {
        return [
            // AI Provider
            'ai_provider'           => 'openai',
            'openai_api_key'        => '',
            'openai_model'          => 'gpt-4o-mini',
            'claude_api_key'        => '',
            'claude_model'          => 'claude-haiku-4-5-20251001',
            'gemini_api_key'        => '',
            'gemini_model'          => 'gemini-2.0-flash',
            'openrouter_api_key'    => '',
            'openrouter_model'      => 'openrouter/auto',

            // Chatbot Settings
            'bot_name'              => 'Assistant',
            'bot_avatar'            => '🤖',
            'welcome_message'       => 'Hello! How can I help you today?',
            'welcome_messages'      => [],
            'system_prompt'         => "You are a knowledgeable assistant for this website. Follow these rules:\n\n1. ACCURACY: When reference information is provided, treat it as the primary and most reliable source. Base your answers on this information first.\n2. HONESTY: If the provided information does not cover the user's question, clearly state that you don't have specific information about it, then offer general guidance if appropriate.\n3. NO FABRICATION: Never invent facts, URLs, prices, dates, or specific details that are not in the provided reference information.\n4. CONCISENESS: Provide clear, focused answers. Avoid unnecessary repetition or filler.\n5. LANGUAGE: Always respond in the same language the user writes in.\n6. TONE: Be professional, friendly, and helpful.",
            'quota_error_message'   => 'Currently recharging. Please try again later.',
            'max_tokens'            => 1000,
            'temperature'           => 0.7,
            'message_history_count' => 10,

            // Context Prompts
            'knowledge_exact_prompt' => "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===",
            'knowledge_qa_prompt'   => "=== CRITICAL INSTRUCTIONS ===\nBelow is a FAQ database. When the user asks a question:\n1. FIRST, look for [BEST MATCH] - this is the most relevant Q&A for the user's question\n2. If [BEST MATCH] exists, use that Answer to respond\n3. If no [BEST MATCH], find the Question that matches or is similar to the user's question\n4. Return the corresponding Answer from the FAQ\n5. DO NOT make up answers - ONLY use the information provided below\n\nIMPORTANT: The Answer after [BEST MATCH] is your primary response source.\n\n=== FAQ DATABASE ===\n{context}\n=== END FAQ DATABASE ===",
            'site_context_prompt'   => "[IMPORTANT: Reference Information]\nYou MUST use the following information as the primary source when answering. If the answer can be found in this information, use it directly.\nIf the reference information does NOT contain the answer, clearly state that you don't have specific information about it. Do NOT guess or fabricate details.\n\n{context}",

            // Feature Prompts
            'regenerate_prompt'     => '[REGENERATION REQUEST #{variation_number}]: The user wants a DIFFERENT answer. FORBIDDEN: Do not start with "{forbidden_start}". {style}. Create a completely new response with different wording. IMPORTANT: Do NOT use headings, labels, or section markers like【】or brackets. Write in natural flowing paragraphs. Complete all sentences fully.',
            'feedback_good_header'  => "[LEARNING FROM USER FEEDBACK - GOOD EXAMPLES]\nThe following responses received positive feedback. Use these as examples of good responses:",
            'feedback_bad_header'   => "[LEARNING FROM USER FEEDBACK - AVOID THESE PATTERNS]\nThe following responses received negative feedback. AVOID responding in similar ways:",
            'summary_prompt'        => 'Please summarize the following conversation in 2-3 sentences, highlighting the main topics discussed and any conclusions reached:',

            // Display Settings
            'badge_position'        => 'bottom-right',
            'badge_margin_right'    => 20,
            'badge_margin_bottom'   => 20,
            'primary_color'         => '#007bff',
            'show_on_mobile'        => true,
            'widget_theme'          => 'default',
            'dark_mode'             => false,
            'markdown_enabled'      => true,
            'show_feedback_buttons' => true,
            'sources_display_mode'  => 'matched',

            // Page Visibility
            'badge_show_on_home'    => true,
            'badge_show_on_posts'   => true,
            'badge_show_on_pages'   => true,
            'badge_show_on_archives' => true,
            'badge_include_ids'     => '',
            'badge_exclude_ids'     => '',
            'excluded_pages'        => [],

            // History Settings
            'save_history'          => true,
            'retention_days'        => 90,

            // Chatbot Language
            'response_language'     => '',

            // Privacy / Consent
            'consent_strict_mode'   => false,
            'delete_data_on_uninstall' => false,

            // reCAPTCHA
            'recaptcha_enabled'     => false,
            'recaptcha_site_key'    => '',
            'recaptcha_secret_key'  => '',
            'recaptcha_threshold'   => 0.5,
            'recaptcha_use_existing' => false,
            'recaptcha_fail_mode'   => 'open',

            // IP Trust
            'trust_cloudflare_ip'   => false,
            'trust_proxy_ip'        => false,

            // Rate Limiting
            'rate_limit'            => 20,
            'rate_limit_window'     => 3600,

            // Crawler Settings
            'crawler_enabled'       => true,
            'crawler_post_types'    => ['all'],
            'crawler_interval'      => 'daily',
            'crawler_chunk_size'    => 1000,
            'crawler_max_results'   => 3,
            'crawler_exclude_ids'   => [],

            // MCP
            'mcp_enabled'           => false,
            'mcp_api_key_hash'      => '',

            // Web Search & Embedding
            'web_search_enabled'    => false,
            'embedding_enabled'     => false,
            'embedding_provider'    => 'auto',

            // Pro features are managed by the Pro plugin via filters.
            // This key preserves existing values during settings operations.
            'pro_features'          => [],
        ];
    }

    /**
     * Reset settings AJAX
     */
    public function ajax_reset_settings(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('reset_settings')) {
            return;
        }

        // Use canonical defaults to ensure all keys are included
        $default_settings = self::get_all_defaults();

        update_option('raplsaich_settings', $default_settings);

        wp_send_json_success(__('Settings have been reset.', 'rapls-ai-chatbot'));
    }

    /**
     * Reset usage statistics AJAX
     */
    public function ajax_reset_usage(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('reset_usage')) {
            return;
        }

        $result = RAPLSAICH_Cost_Calculator::reset_usage_stats();

        if ($result) {
            wp_send_json_success(__('Usage statistics have been reset.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to reset.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * AJAX: Generate embeddings for unembedded chunks (batch processing)
     */
    public function ajax_generate_embeddings(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $generator = new RAPLSAICH_Embedding_Generator();
        if (!$generator->is_configured()) {
            wp_send_json_error(__('Embedding provider is not configured. Please check your API key settings.', 'rapls-ai-chatbot'));
        }

        $source = sanitize_text_field(wp_unslash($_POST['source'] ?? 'index'));

        if ($source === 'knowledge') {
            $pending = RAPLSAICH_Knowledge::get_unembedded_entries(50);
        } else {
            $pending = RAPLSAICH_Content_Index::get_unembedded_chunks(50);
        }

        if (empty($pending)) {
            wp_send_json_success([
                'processed' => 0,
                'remaining' => 0,
                /* translators: embedding generation complete */
                'message'   => __('All embeddings are up to date.', 'rapls-ai-chatbot'),
            ]);
        }

        $texts = [];
        $ids = [];
        foreach ($pending as $row) {
            $texts[] = ($row['title'] ?? '') . "\n" . ($row['content'] ?? '');
            $ids[]   = (int) $row['id'];
        }

        $embeddings = $generator->generate_batch($texts);

        $processed = 0;
        foreach ($embeddings as $i => $emb) {
            if ($emb && isset($ids[$i])) {
                $packed = RAPLSAICH_Vector_Search::pack_embedding($emb);
                if ($source === 'knowledge') {
                    RAPLSAICH_Knowledge::update_embedding($ids[$i], $packed, $generator->get_model());
                } else {
                    RAPLSAICH_Content_Index::update_embedding($ids[$i], $packed, $generator->get_model());
                }
                $processed++;
            }
        }

        // Count remaining
        if ($source === 'knowledge') {
            $remaining = count(RAPLSAICH_Knowledge::get_unembedded_entries(1));
        } else {
            $remaining = count(RAPLSAICH_Content_Index::get_unembedded_chunks(1));
        }

        wp_send_json_success([
            'processed' => $processed,
            'remaining' => $remaining,
            /* translators: 1: number processed, 2: number remaining */
            'message'   => sprintf(__('Processed %1$d embeddings. %2$d remaining.', 'rapls-ai-chatbot'), $processed, $remaining),
        ]);
    }

    /**
     * AJAX: Clear all embeddings
     */
    public function ajax_clear_embeddings(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        RAPLSAICH_Content_Index::clear_all_embeddings();
        RAPLSAICH_Knowledge::clear_all_embeddings();

        wp_send_json_success(['message' => __('All embeddings have been cleared.', 'rapls-ai-chatbot')]);
    }

    /**
     * AJAX: Get embedding status
     */
    public function ajax_embedding_status(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $index_stats = RAPLSAICH_Content_Index::get_embedding_stats();
        $knowledge_stats = RAPLSAICH_Knowledge::get_embedding_stats();

        $generator = new RAPLSAICH_Embedding_Generator();

        wp_send_json_success([
            'configured'       => $generator->is_configured(),
            'provider'         => $generator->get_provider(),
            'model'            => $generator->get_model(),
            'index_total'      => $index_stats['total_chunks'],
            'index_embedded'   => $index_stats['embedded_chunks'],
            'knowledge_total'  => $knowledge_stats['total'],
            'knowledge_embedded' => $knowledge_stats['embedded'],
        ]);
    }

    /**
     * Dismiss security notice for 30 days
     */
    public function ajax_dismiss_security_notice(): void {
        check_ajax_referer('raplsaich_dismiss_security_notice', '_wpnonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        set_transient('raplsaich_security_notice_dismissed', true, 30 * DAY_IN_SECONDS);
        wp_send_json_success();
    }

    /**
     * AJAX: Generate a new MCP API key.
     * Stores hashed key, returns raw key (shown once only).
     */
    public function ajax_generate_mcp_key(): void {
        check_ajax_referer('raplsaich_generate_mcp_key', '_wpnonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // Generate a 40-character alphanumeric key
        $raw_key = wp_generate_password(40, false);

        // Store hashed version
        // Bypass sanitize_settings callback to prevent it from overwriting
        // the hash with the stale $existing value (update_option triggers
        // sanitize_option_{option} which calls sanitize_settings).
        $settings = get_option('raplsaich_settings', []);
        $settings['mcp_api_key_hash'] = wp_hash_password($raw_key);
        global $wp_filter;
        $saved = isset($wp_filter['sanitize_option_raplsaich_settings']) ? $wp_filter['sanitize_option_raplsaich_settings'] : null;
        remove_all_filters('sanitize_option_raplsaich_settings');
        try {
            update_option('raplsaich_settings', $settings);
        } finally {
            if ($saved !== null) {
                $wp_filter['sanitize_option_raplsaich_settings'] = $saved;
            }
        }

        wp_send_json_success([
            'api_key'  => $raw_key,
            'endpoint' => rest_url('rapls-ai-chatbot/v1/mcp'),
        ]);
    }

    /**
     * Verify or issue a confirmation token for destructive operations.
     *
     * First call (no token): issues a short-lived token and returns false.
     * Second call (with token): validates and consumes the token, returns true.
     *
     * @param string $action Unique action key (e.g. 'delete_all_conversations').
     * @return bool True if confirmed, false if token was issued (caller should return).
     */
    private function verify_destructive_token(string $action): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in the calling AJAX handler before this method is invoked
        $token = sanitize_text_field(wp_unslash($_POST['confirm_token'] ?? ''));
        $transient_key = 'raplsaich_confirm_' . $action . '_' . get_current_user_id();

        if (!empty($token)) {
            $stored = get_transient($transient_key);
            if ($stored && hash_equals($stored, $token)) {
                delete_transient($transient_key);
                return true;
            }
            wp_send_json_error(__('Confirmation expired or invalid. Please try again.', 'rapls-ai-chatbot'));
            return false; // unreachable, but for clarity
        }

        // Issue new token (10 min TTL)
        $new_token = wp_generate_password(32, false);
        set_transient($transient_key, $new_token, 10 * MINUTE_IN_SECONDS);
        wp_send_json_success([
            'confirm_required' => true,
            'confirm_token'    => $new_token,
            'message'          => __('Are you sure? This action cannot be undone. Click again to confirm.', 'rapls-ai-chatbot'),
        ]);
        return false; // unreachable
    }

    /**
     * Render Pro upsell page (for Leads and Analytics)
     */
    public function render_pro_upsell_page(): void {
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        $previews = [
            'raplsaich-pro-settings'  => 'render_pro_settings_preview',
            'raplsaich-crawler'       => 'render_crawler_preview',
            'raplsaich-conversations' => 'render_conversations_preview',
            'raplsaich-analytics'     => 'render_analytics_preview',
            'raplsaich-leads'         => 'render_leads_preview',
            'raplsaich-audit-log'     => 'render_audit_log_preview',
        ];

        if (isset($previews[$current_page])) {
            $this->{$previews[$current_page]}();
            return;
        }
    }

    /**
     * Render upgrade banner (shared by all upsell pages)
     */
    private function render_pro_upgrade_banner(string $title, string $description, array $features = []): void {
        ?>
        <div class="wrap raplsaich-admin">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="raplsaich-pro-upgrade-banner">
                <div class="raplsaich-pro-upgrade-content">
                    <span class="dashicons dashicons-star-filled"></span>
                    <div>
                        <strong><?php esc_html_e('Upgrade to Pro', 'rapls-ai-chatbot'); ?></strong>
                        <p><?php echo esc_html($description); ?></p>
                    </div>
                    <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" class="button button-primary">
                        <?php esc_html_e('Get Pro Version', 'rapls-ai-chatbot'); ?>
                    </a>
                </div>
            </div>

            <?php if (!empty($features)) : ?>
            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <?php foreach ($features as $feature) : ?>
                    <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Site Learning preview — text-only upgrade notice
     */
    private function render_crawler_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Site Learning', 'rapls-ai-chatbot'),
            __('Enhance your AI chatbot with site-wide content indexing and vector embeddings.', 'rapls-ai-chatbot'),
            [
                __('Auto-learn site content on schedule', 'rapls-ai-chatbot'),
                __('Vector embedding for semantic search', 'rapls-ai-chatbot'),
                __('Enhanced HTML content extraction', 'rapls-ai-chatbot'),
                __('WooCommerce product data auto-crawl', 'rapls-ai-chatbot'),
                __('Differential crawl (index changes only)', 'rapls-ai-chatbot'),
                __('Exclude specific pages from indexing', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Conversations preview — text-only upgrade notice
     */
    private function render_conversations_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Conversations', 'rapls-ai-chatbot'),
            __('View, manage, and export all chatbot conversations.', 'rapls-ai-chatbot'),
            [
                __('Full conversation history with search', 'rapls-ai-chatbot'),
                __('Export conversations (CSV/JSON)', 'rapls-ai-chatbot'),
                __('Operator mode for live chat takeover', 'rapls-ai-chatbot'),
                __('Conversation tags and archiving', 'rapls-ai-chatbot'),
                __('AI-powered response improvement suggestions', 'rapls-ai-chatbot'),
                __('Sentiment analysis per conversation', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Analytics preview — text-only upgrade notice
     */
    private function render_analytics_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Analytics', 'rapls-ai-chatbot'),
            __('Track chatbot performance with detailed analytics and reports.', 'rapls-ai-chatbot'),
            [
                __('Conversation and message statistics', 'rapls-ai-chatbot'),
                __('User satisfaction scores', 'rapls-ai-chatbot'),
                __('FAQ ranking and unresolved questions', 'rapls-ai-chatbot'),
                __('API cost tracking per model', 'rapls-ai-chatbot'),
                __('Daily/hourly activity charts', 'rapls-ai-chatbot'),
                __('PDF report export', 'rapls-ai-chatbot'),
                __('Monthly email reports', 'rapls-ai-chatbot'),
                __('Knowledge gap detection', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Leads preview — text-only upgrade notice
     */
    private function render_leads_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Leads', 'rapls-ai-chatbot'),
            __('Capture and manage leads from chatbot conversations.', 'rapls-ai-chatbot'),
            [
                __('Customizable lead capture forms', 'rapls-ai-chatbot'),
                __('Export leads (CSV/JSON)', 'rapls-ai-chatbot'),
                __('Email notifications on new leads', 'rapls-ai-chatbot'),
                __('Webhook integration (Slack, Zapier, CRM)', 'rapls-ai-chatbot'),
                __('Custom fields support', 'rapls-ai-chatbot'),
                __('Lead-to-conversation linking', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Audit Log preview — text-only upgrade notice
     */
    private function render_audit_log_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Audit Log', 'rapls-ai-chatbot'),
            __('Track administrative actions for compliance and security.', 'rapls-ai-chatbot'),
            [
                __('Detailed admin action history', 'rapls-ai-chatbot'),
                __('Export audit log (CSV)', 'rapls-ai-chatbot'),
                __('Configurable retention policy', 'rapls-ai-chatbot'),
                __('User and action type filtering', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Pro Settings preview — text-only upgrade notice
     */
    private function render_pro_settings_preview(): void {
        $this->render_pro_upgrade_banner(
            __('Pro Settings', 'rapls-ai-chatbot'),
            __('Unlock all Pro features including lead capture, business hours, webhooks, and more.', 'rapls-ai-chatbot'),
            [
                __('Lead capture & custom forms', 'rapls-ai-chatbot'),
                __('Business hours & holiday schedules', 'rapls-ai-chatbot'),
                __('Webhook & LINE integration', 'rapls-ai-chatbot'),
                __('Human handoff & operator mode', 'rapls-ai-chatbot'),
                __('AI prompts & templates', 'rapls-ai-chatbot'),
                __('Conversation scenarios & actions', 'rapls-ai-chatbot'),
                __('Multiple chatbots per page', 'rapls-ai-chatbot'),
                __('Budget alerts & cost caps', 'rapls-ai-chatbot'),
                __('Role-based access control', 'rapls-ai-chatbot'),
                __('Voice input & text-to-speech', 'rapls-ai-chatbot'),
                __('Response caching & performance tools', 'rapls-ai-chatbot'),
                __('Settings backup, rollback & staging', 'rapls-ai-chatbot'),
            ]
        );
    }

    /**
     * Reset all user sessions AJAX
     */
    public function ajax_reset_sessions(): void {
        check_ajax_referer('raplsaich_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // Increment session version - this will invalidate all client sessions
        $current_version = get_option('raplsaich_session_version', 1);
        $new_version = $current_version + 1;
        update_option('raplsaich_session_version', $new_version);

        wp_send_json_success(__('All user sessions have been reset. Users will start new sessions on their next visit.', 'rapls-ai-chatbot'));
    }

    /**
     * Generate a sortable column header link
     *
     * @param string $column     Column key for orderby param
     * @param string $label      Display label for the column
     * @param string $current_orderby Currently active orderby value
     * @param string $current_order   Currently active order (ASC/DESC)
     * @param string $default_order   Default sort direction when first clicking
     * @param string $param_prefix    Optional prefix for query params (e.g. 'model_')
     * @return string HTML link
     */
    public static function sortable_column_header($column, $label, $current_orderby, $current_order, $default_order = 'ASC', $param_prefix = '') {
        $is_current = ($current_orderby === $column);
        $new_order = $is_current ? ($current_order === 'ASC' ? 'DESC' : 'ASC') : $default_order;

        $orderby_param = $param_prefix . 'orderby';
        $order_param = $param_prefix . 'order';

        $url = remove_query_arg('paged');
        $url = add_query_arg([
            $orderby_param => $column,
            $order_param   => $new_order,
        ], $url);

        $class = $is_current ? 'raplsaich-sorted' : 'raplsaich-sortable';
        $indicator = '';
        if ($is_current) {
            $indicator = ' <span class="raplsaich-sort-indicator">' . ($current_order === 'ASC' ? '&#9650;' : '&#9660;') . '</span>';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . $indicator . '</a>';
    }
}
