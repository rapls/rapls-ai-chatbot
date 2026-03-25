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

        $is_pro = RAPLSAICH_Pro_Features::get_instance()->is_pro();

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
     * Sanitize Pro features settings
     */
    private function sanitize_pro_features_settings(array $input, array $existing): array {
        // If Pro features form was not submitted, preserve existing values
        if (empty($input)) {
            return $existing;
        }

        $defaults = RAPLSAICH_Pro_Features::get_default_settings();
        $sanitized = [];

        // Lead capture
        $sanitized['lead_capture_enabled'] = !empty($input['lead_capture_enabled']);
        $sanitized['lead_capture_required'] = !empty($input['lead_capture_required']);

        // Lead fields
        $sanitized['lead_fields'] = [];
        $field_names = ['name', 'email', 'phone', 'company'];
        foreach ($field_names as $field) {
            $field_input = $input['lead_fields'][$field] ?? [];
            $field_existing = $existing['lead_fields'][$field] ?? $defaults['lead_fields'][$field] ?? [];
            $sanitized['lead_fields'][$field] = [
                'enabled' => !empty($field_input['enabled']),
                'required' => !empty($field_input['required']),
                'label' => sanitize_text_field($field_input['label'] ?? ($field_existing['label'] ?? ucfirst($field))),
                'type' => sanitize_key($field_input['type'] ?? ($field_existing['type'] ?? 'text')),
            ];
        }

        $sanitized['lead_form_title'] = sanitize_text_field($input['lead_form_title'] ?? ($existing['lead_form_title'] ?? $defaults['lead_form_title']));
        $sanitized['lead_form_description'] = sanitize_textarea_field($input['lead_form_description'] ?? ($existing['lead_form_description'] ?? $defaults['lead_form_description']));
        $sanitized['lead_notification_enabled'] = !empty($input['lead_notification_enabled']);
        $sanitized['lead_notification_email'] = sanitize_email($input['lead_notification_email'] ?? ($existing['lead_notification_email'] ?? ''));

        // Email subject prefix
        $sanitized['email_subject_prefix'] = sanitize_text_field($input['email_subject_prefix'] ?? ($existing['email_subject_prefix'] ?? $defaults['email_subject_prefix']));

        // White label
        $sanitized['white_label_enabled'] = !empty($input['white_label_enabled']);
        $sanitized['white_label_footer'] = sanitize_text_field($input['white_label_footer'] ?? ($existing['white_label_footer'] ?? ''));
        $sanitized['white_label_footer_url'] = esc_url_raw($input['white_label_footer_url'] ?? ($existing['white_label_footer_url'] ?? ''));
        $sanitized['white_label_footer_target'] = in_array($input['white_label_footer_target'] ?? '', ['_blank', '_self'], true) ? $input['white_label_footer_target'] : '_blank';
        $raw_css = wp_strip_all_tags($input['custom_css'] ?? ($existing['custom_css'] ?? ''));
        // Remove CSS injection vectors
        $raw_css = str_replace('expression(', '', $raw_css);
        $raw_css = preg_replace('/url\s*\(\s*["\']?\s*javascript:/i', 'url(about:blank', $raw_css);
        $sanitized['custom_css'] = $raw_css;

        // Webhook
        $sanitized['webhook_enabled'] = !empty($input['webhook_enabled']);
        $webhook_url_input = esc_url_raw($input['webhook_url'] ?? ($existing['webhook_url'] ?? ''), ['https', 'http']);
        // Validate webhook URL: reject private/internal IPs (SSRF protection)
        if (!empty($webhook_url_input)) {
            $validated = wp_http_validate_url($webhook_url_input);
            if ($validated === false) {
                $webhook_url_input = ''; // Reject invalid/internal URLs
            }
        }
        // Post-resolution DNS check: verify all resolved IPs (A + AAAA) are not private/internal
        if (!empty($webhook_url_input)) {
            $wh_host = wp_parse_url($webhook_url_input, PHP_URL_HOST);
            if (!empty($wh_host) && !filter_var($wh_host, FILTER_VALIDATE_IP)) {
                $wh_ips = [];
                if (function_exists('dns_get_record')) {
                    foreach ((array) @dns_get_record($wh_host, DNS_A) as $r) {
                        if (!empty($r['ip'])) { $wh_ips[] = $r['ip']; }
                    }
                    foreach ((array) @dns_get_record($wh_host, DNS_AAAA) as $r) {
                        if (!empty($r['ipv6'])) { $wh_ips[] = $r['ipv6']; }
                    }
                }
                if (empty($wh_ips)) {
                    $wh_ipv4 = gethostbyname($wh_host);
                    if ($wh_ipv4 !== $wh_host) { $wh_ips[] = $wh_ipv4; }
                }
                foreach ($wh_ips as $wh_ip) {
                    if (!filter_var($wh_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $webhook_url_input = '';
                        add_settings_error(
                            'raplsaich_settings',
                            'webhook_url_private',
                            __('Webhook URL was rejected because it resolves to a private or internal IP address.', 'rapls-ai-chatbot'),
                            'error'
                        );
                        break;
                    }
                }
            }
        }
        $sanitized['webhook_url'] = $webhook_url_input;
        $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? ($existing['webhook_secret'] ?? ''));

        // Webhook events
        $sanitized['webhook_events'] = [];
        $event_names = ['new_conversation', 'new_message', 'lead_captured', 'handoff_requested', 'handoff_resolved', 'offline_message', 'ai_error', 'budget_alert', 'rate_limit_exceeded', 'banned_word_detected', 'recaptcha_failed'];
        foreach ($event_names as $event) {
            $sanitized['webhook_events'][$event] = !empty($input['webhook_events'][$event]);
        }

        // Quick replies
        $sanitized['quick_replies_enabled'] = !empty($input['quick_replies_enabled']);
        $sanitized['quick_replies'] = [];
        if (!empty($input['quick_replies']) && is_array($input['quick_replies'])) {
            foreach ($input['quick_replies'] as $reply) {
                if (!empty($reply['text'])) {
                    $sanitized['quick_replies'][] = [
                        'text' => sanitize_text_field($reply['text']),
                    ];
                }
            }
        }

        // Business hours
        $sanitized['business_hours_enabled'] = !empty($input['business_hours_enabled']);
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $sanitized['business_hours'] = [];
        foreach ($days as $day) {
            $day_input = $input['business_hours'][$day] ?? [];
            $day_existing = $existing['business_hours'][$day] ?? $defaults['business_hours'][$day] ?? [];
            $sanitized['business_hours'][$day] = [
                'enabled' => !empty($day_input['enabled']),
                'start' => sanitize_text_field($day_input['start'] ?? ($day_existing['start'] ?? '09:00')),
                'end' => sanitize_text_field($day_input['end'] ?? ($day_existing['end'] ?? '18:00')),
            ];
        }
        $tz_input = sanitize_text_field($input['business_hours_timezone'] ?? ($existing['business_hours_timezone'] ?? 'Asia/Tokyo'));
        $sanitized['business_hours_timezone'] = in_array($tz_input, timezone_identifiers_list(), true) ? $tz_input : 'Asia/Tokyo';
        $sanitized['outside_hours_message'] = sanitize_textarea_field($input['outside_hours_message'] ?? ($existing['outside_hours_message'] ?? $defaults['outside_hours_message']));

        // Holidays
        $sanitized['holidays_enabled'] = !empty($input['holidays_enabled']);
        $sanitized['holidays'] = [];
        if (!empty($input['holidays']) && is_array($input['holidays'])) {
            foreach ($input['holidays'] as $holiday) {
                if (!empty($holiday['date'])) {
                    $sanitized['holidays'][] = [
                        'date' => sanitize_text_field($holiday['date']),
                        'name' => sanitize_text_field($holiday['name'] ?? ''),
                    ];
                }
            }
        }
        $sanitized['holiday_message'] = sanitize_textarea_field($input['holiday_message'] ?? ($existing['holiday_message'] ?? $defaults['holiday_message']));

        // Banned words
        $sanitized['banned_words_enabled'] = !empty($input['banned_words_enabled']);
        $sanitized['banned_words'] = sanitize_textarea_field($input['banned_words'] ?? ($existing['banned_words'] ?? ''));
        $sanitized['banned_words_message'] = sanitize_text_field($input['banned_words_message'] ?? ($existing['banned_words_message'] ?? $defaults['banned_words_message']));

        // IP blocking
        $sanitized['ip_block_enabled'] = !empty($input['ip_block_enabled']);
        $sanitized['blocked_ips'] = sanitize_textarea_field($input['blocked_ips'] ?? ($existing['blocked_ips'] ?? ''));
        $sanitized['ip_block_message'] = sanitize_text_field($input['ip_block_message'] ?? ($existing['ip_block_message'] ?? $defaults['ip_block_message']));

        // Regenerate button
        $sanitized['show_regenerate_button'] = array_key_exists('show_regenerate_button', $input)
            ? !empty($input['show_regenerate_button'])
            : ($existing['show_regenerate_button'] ?? $defaults['show_regenerate_button']);

        // Related suggestions
        $sanitized['related_suggestions_enabled'] = array_key_exists('related_suggestions_enabled', $input)
            ? !empty($input['related_suggestions_enabled'])
            : ($existing['related_suggestions_enabled'] ?? $defaults['related_suggestions_enabled']);

        // Autocomplete
        $sanitized['autocomplete_enabled'] = array_key_exists('autocomplete_enabled', $input)
            ? !empty($input['autocomplete_enabled'])
            : ($existing['autocomplete_enabled'] ?? $defaults['autocomplete_enabled']);

        // Sentiment analysis
        $sanitized['sentiment_analysis_enabled'] = array_key_exists('sentiment_analysis_enabled', $input)
            ? !empty($input['sentiment_analysis_enabled'])
            : ($existing['sentiment_analysis_enabled'] ?? $defaults['sentiment_analysis_enabled']);

        // Context memory
        $sanitized['context_memory_enabled'] = array_key_exists('context_memory_enabled', $input)
            ? !empty($input['context_memory_enabled'])
            : ($existing['context_memory_enabled'] ?? $defaults['context_memory_enabled']);
        $sanitized['context_memory_days'] = array_key_exists('context_memory_days', $input)
            ? absint($input['context_memory_days'])
            : ($existing['context_memory_days'] ?? $defaults['context_memory_days']);

        // Multimodal (image upload)
        $sanitized['multimodal_enabled'] = array_key_exists('multimodal_enabled', $input)
            ? !empty($input['multimodal_enabled'])
            : ($existing['multimodal_enabled'] ?? $defaults['multimodal_enabled']);
        $sanitized['multimodal_max_size'] = array_key_exists('multimodal_max_size', $input)
            ? absint($input['multimodal_max_size'])
            : ($existing['multimodal_max_size'] ?? $defaults['multimodal_max_size']);

        // Pro prompt settings
        $pro_prompt_keys = [
            'sentiment_prompt',
            'sentiment_tone_frustrated',
            'sentiment_tone_confused',
            'sentiment_tone_urgent',
            'sentiment_tone_positive',
            'sentiment_tone_negative',
            'suggestions_prompt',
            'pro_summary_prompt',
            'context_extraction_prompt',
            'context_memory_prompt',
        ];
        foreach ($pro_prompt_keys as $key) {
            $sanitized[$key] = sanitize_textarea_field($input[$key] ?? ($existing[$key] ?? ($defaults[$key] ?? '')));
        }

        // Role-based access control
        $sanitized['role_access_enabled'] = !empty($input['role_access_enabled']);
        $role_access_default = $input['role_access_default'] ?? ($existing['role_access_default'] ?? 'allow');
        $sanitized['role_access_default'] = in_array($role_access_default, ['allow', 'deny'], true) ? $role_access_default : 'allow';

        $sanitized['role_limits'] = [];
        if (!empty($input['role_limits']) && is_array($input['role_limits'])) {
            foreach ($input['role_limits'] as $role_slug => $values) {
                $safe_slug = sanitize_key($role_slug);
                if (empty($safe_slug) || !is_array($values)) {
                    continue;
                }
                $sanitized['role_limits'][$safe_slug] = [
                    'chat_allowed'  => !empty($values['chat_allowed']),
                    'message_limit' => absint($values['message_limit'] ?? 0),
                ];
            }
        } else {
            $sanitized['role_limits'] = $existing['role_limits'] ?? [];
        }

        // Merge: $existing (preserved DB values) + $sanitized (explicitly sanitized keys).
        // Raw $input is NOT merged — only explicitly sanitized keys are stored.
        // Pro-managed keys (badge_icon_*, scheduling, etc.) are preserved via $existing
        // because Pro writes them via update_option() which stores them in the DB.
        return array_merge($existing, $sanitized);
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
            'isPro'     => RAPLSAICH_Pro_Features::get_instance()->is_pro(),
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

        $pro = RAPLSAICH_Pro_Features::get_instance();
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
        $api_key = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_unslash($_POST['api_key'] ?? '')));
        $use_saved = !empty($_POST['use_saved']);

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
        $api_key = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_unslash($_POST['api_key'] ?? '')));
        $use_saved = !empty(wp_unslash($_POST['use_saved']));
        $force_refresh = !empty(wp_unslash($_POST['force_refresh']));

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

        $pro = RAPLSAICH_Pro_Features::get_instance();
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
        $pro_features = RAPLSAICH_Pro_Features::get_instance();
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

            if (empty($_FILES['file'])) {
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
            $pro_features = RAPLSAICH_Pro_Features::get_instance();
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

            // Pro Features
            'pro_features'          => RAPLSAICH_Pro_Features::get_default_settings(),
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
     * Render upgrade banner + preview wrapper (shared by all preview pages)
     */
    private function render_pro_preview_start(string $title, string $description): void {
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

            <div class="raplsaich-pro-preview-wrapper">
                <div class="raplsaich-pro-preview">
        <?php
    }

    private function render_pro_preview_end(): void {
        ?>
                </div>
            </div>
        </div>
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Generate a smooth cubic bezier SVG path through given points.
     *
     * @param array $pts Array of [x, y] coordinate pairs.
     * @return string SVG path d attribute value.
     */
    private function svg_smooth_curve( array $pts ): string {
        $n = count( $pts );
        if ( $n < 2 ) {
            return '';
        }
        $d = 'M' . $pts[0][0] . ',' . $pts[0][1];
        if ( 2 === $n ) {
            $d .= ' L' . $pts[1][0] . ',' . $pts[1][1];
            return $d;
        }
        $tension = 0.15;
        for ( $i = 0; $i < $n - 1; $i++ ) {
            $p0 = $pts[ max( 0, $i - 1 ) ];
            $p1 = $pts[ $i ];
            $p2 = $pts[ $i + 1 ];
            $p3 = $pts[ min( $n - 1, $i + 2 ) ];
            $cp1x = round( $p1[0] + ( $p2[0] - $p0[0] ) * $tension, 1 );
            $cp1y = round( $p1[1] + ( $p2[1] - $p0[1] ) * $tension, 1 );
            $cp2x = round( $p2[0] - ( $p3[0] - $p1[0] ) * $tension, 1 );
            $cp2y = round( $p2[1] - ( $p3[1] - $p1[1] ) * $tension, 1 );
            $d .= ' C' . $cp1x . ',' . $cp1y . ' ' . $cp2x . ',' . $cp2y . ' ' . $p2[0] . ',' . $p2[1];
        }
        return $d;
    }

    /**
     * Shared CSS for all preview pages
     */
    private function render_pro_preview_styles(): void {
        ?>
        <?php
    }

    /**
     * Site Learning preview
     */
    private function render_crawler_preview(): void {
        $this->render_pro_preview_start(
            __('Site Learning', 'rapls-ai-chatbot'),
            __('Automatically crawl and index your website content so the chatbot can answer questions based on your site information.', 'rapls-ai-chatbot')
        );
        ?>
        <div class="raplsaich-crawler-grid">
        <!-- Status Card -->
        <div class="raplsaich-card raplsaich-card-status">
            <h2><?php esc_html_e('Learning Status', 'rapls-ai-chatbot'); ?></h2>
            <table class="raplsaich-status-table">
                <tr>
                    <td><?php esc_html_e('Site Learning', 'rapls-ai-chatbot'); ?></td>
                    <td><span class="status-badge status-ok"><?php esc_html_e('Enabled', 'rapls-ai-chatbot'); ?></span></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></td>
                    <td><strong>24</strong> <?php esc_html_e('pages', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Last Crawl', 'rapls-ai-chatbot'); ?></td>
                    <td>2026/02/13 09:00</td>
                </tr>
                <tr>
                    <td>WooCommerce</td>
                    <td>
                        <span class="status-badge status-ok"><?php esc_html_e('Detected', 'rapls-ai-chatbot'); ?></span>
                        (<?php
                        /* translators: %s: number of products */
                        printf(esc_html__('%s products', 'rapls-ai-chatbot'), '15');
                        ?>)
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Last Result', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <?php esc_html_e('New:', 'rapls-ai-chatbot'); ?> 5,
                        <?php esc_html_e('Updated:', 'rapls-ai-chatbot'); ?> 3,
                        <?php esc_html_e('Skipped:', 'rapls-ai-chatbot'); ?> 16
                    </td>
                </tr>
            </table>
            <div class="raplsaich-actions">
                <button type="button" class="button button-primary">🔄 <?php esc_html_e('Run Learning Now', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>

        <!-- Vector Embedding Card -->
        <div class="raplsaich-card raplsaich-card-embedding">
            <h2><?php esc_html_e('Vector Embedding', 'rapls-ai-chatbot'); ?></h2>
            <table class="raplsaich-status-table">
                <tr>
                    <td><?php esc_html_e('Embedding', 'rapls-ai-chatbot'); ?></td>
                    <td><span class="status-badge status-ok"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></td>
                    <td>Openai / text-embedding-3-small</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Embedded Chunks', 'rapls-ai-chatbot'); ?></td>
                    <td><strong>42</strong> / 48 (88%)</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top: 8px;">
                        <div style="background: #e0e0e0; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: #667eea; height: 100%; width: 88%; transition: width 0.3s;"></div>
                        </div>
                    </td>
                </tr>
            </table>
            <div class="raplsaich-actions" style="margin-top: 12px;">
                <button type="button" class="button button-primary"><?php esc_html_e('Generate Embeddings', 'rapls-ai-chatbot'); ?></button>
                <button type="button" class="button button-secondary">🗑️ <?php esc_html_e('Clear All Embeddings', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>

        <!-- Settings Card -->
        <div class="raplsaich-card raplsaich-card-settings">
            <h2><?php esc_html_e('Learning Settings', 'rapls-ai-chatbot'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Site Learning', 'rapls-ai-chatbot'); ?></th>
                    <td><label><input type="checkbox" checked> <?php esc_html_e('Auto-learn site content', 'rapls-ai-chatbot'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Target Content', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <label style="display: block; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                            <input type="checkbox" checked>
                            <strong><?php esc_html_e('All Public Content (Recommended)', 'rapls-ai-chatbot'); ?></strong>
                            <p class="description" style="margin-left: 24px; margin-top: 4px;">
                                <?php esc_html_e('Learn all posts, pages, custom post types, and custom fields.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </label>
                        <div style="opacity: 0.5;">
                            <p class="description" style="margin-bottom: 8px;"><?php esc_html_e('Or select individually:', 'rapls-ai-chatbot'); ?></p>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox"> post</label>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox"> page</label>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox"> product</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Auto Learning Interval', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <select>
                            <option><?php esc_html_e('Hourly', 'rapls-ai-chatbot'); ?></option>
                            <option><?php esc_html_e('Twice Daily', 'rapls-ai-chatbot'); ?></option>
                            <option selected><?php esc_html_e('Daily', 'rapls-ai-chatbot'); ?></option>
                            <option><?php esc_html_e('Weekly', 'rapls-ai-chatbot'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Reference Count', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <input type="number" value="3" min="1" max="10" class="small-text">
                        <p class="description"><?php esc_html_e('Maximum pages to reference when answering', 'rapls-ai-chatbot'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Excluded Pages', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">
                                Contact <small>(ID:5)</small> <span style="color: #b32d2e; cursor: default;">&times;</span>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">
                                Privacy Policy <small>(ID:3)</small> <span style="color: #b32d2e; cursor: default;">&times;</span>
                            </span>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <input type="number" min="1" class="small-text" placeholder="ID">
                            <button type="button" class="button button-small"><?php esc_html_e('Add by ID', 'rapls-ai-chatbot'); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e('Pages listed here will be skipped during learning and removed from the index.', 'rapls-ai-chatbot'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enhanced Content Extraction', 'rapls-ai-chatbot'); ?> <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-left: 5px;">PRO</span></th>
                    <td>
                        <label><input type="checkbox"> <?php esc_html_e('Enable enhanced HTML content extraction', 'rapls-ai-chatbot'); ?></label>
                        <p class="description"><?php esc_html_e('Uses DOMDocument to parse HTML and extract structured content from headings, tables, lists, code blocks, and meta tags.', 'rapls-ai-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Post Type Statistics -->
        <div class="raplsaich-list-stats raplsaich-card-full" style="margin-bottom: 20px;">
            <div class="raplsaich-list-stat-card">
                <div class="stat-value">24</div>
                <div class="stat-label"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div class="raplsaich-list-stat-card stat-info">
                <div class="stat-value">12</div>
                <div class="stat-label">post</div>
            </div>
            <div class="raplsaich-list-stat-card stat-warning">
                <div class="stat-value">8</div>
                <div class="stat-label">page</div>
            </div>
            <div class="raplsaich-list-stat-card">
                <div class="stat-value">4</div>
                <div class="stat-label">product</div>
            </div>
        </div>

        <!-- Indexed Pages Table -->
        <div class="raplsaich-card raplsaich-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></h2>
                <button type="button" class="button button-secondary">🗑️ <?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?></button>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'rapls-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Type', 'rapls-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('URL', 'rapls-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Indexed Date', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><?php esc_html_e('Sample Page', 'rapls-ai-chatbot'); ?></td><td>page</td><td>/sample-page/</td><td>2026/02/13 09:00</td><td style="white-space: nowrap;"><button class="button button-small">🗑️</button> <button class="button button-small">🚫</button></td></tr>
                    <tr><td><?php esc_html_e('Hello World', 'rapls-ai-chatbot'); ?></td><td>post</td><td>/hello-world/</td><td>2026/02/13 09:00</td><td style="white-space: nowrap;"><button class="button button-small">🗑️</button> <button class="button button-small">🚫</button></td></tr>
                    <tr><td><?php esc_html_e('About Us', 'rapls-ai-chatbot'); ?></td><td>page</td><td>/about/</td><td>2026/02/12 14:30</td><td style="white-space: nowrap;"><button class="button button-small">🗑️</button> <button class="button button-small">🚫</button></td></tr>
                </tbody>
            </table>
        </div>
        </div><!-- .raplsaich-crawler-grid -->
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .raplsaich-pro-preview -->
            </div><!-- .raplsaich-pro-preview-wrapper -->

            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Scheduled automatic crawling', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Differential crawl (changed pages only)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Enhanced HTML content extraction', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Custom post type support', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('WooCommerce product data crawl', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Vector embedding (RAG)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Page exclusion control', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Reference count control', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Post type statistics', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Crawl progress tracking', 'rapls-ai-chatbot'); ?></li>
                </ul>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <p style="margin: 0 0 8px; color: #1d2327;">
                        <span class="dashicons dashicons-info-outline" style="color: #2271b1; margin-right: 4px;"></span>
                        <?php esc_html_e('Manual content indexing is available in the Free version.', 'rapls-ai-chatbot'); ?>
                    </p>
                    <p style="margin: 0; color: #50575e; font-size: 13px;">
                        <?php esc_html_e('With Pro, your chatbot automatically stays up-to-date — scheduled crawling keeps content fresh daily, and differential crawl re-indexes only changed pages to save resources.', 'rapls-ai-chatbot'); ?>
                    </p>
                </div>
            </div>
        </div><!-- .wrap -->
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Conversations preview
     */
    private function render_conversations_preview(): void {
        $this->render_pro_preview_start(
            __('Conversations', 'rapls-ai-chatbot'),
            __('View and manage all chatbot conversations. Search, filter, and analyze visitor interactions.', 'rapls-ai-chatbot')
        );
        ?>
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #1d2327;">48</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #00a32a;">3</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #50575e;">45</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #2271b1;">5</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Today', 'rapls-ai-chatbot'); ?></div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Search messages', 'rapls-ai-chatbot'); ?>">
            <select>
                <option><?php esc_html_e('All Statuses', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Archived', 'rapls-ai-chatbot'); ?></option>
            </select>
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('From:', 'rapls-ai-chatbot'); ?></label>
            <input type="date">
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('To:', 'rapls-ai-chatbot'); ?></label>
            <input type="date">
            <button class="button"><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Actions Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div style="display: flex; gap: 8px;">
                <button class="button"><?php esc_html_e('Delete Selected', 'rapls-ai-chatbot'); ?></button>
                <button class="button" style="color: #d63638;"><?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?></button>
                <button class="button"><?php esc_html_e('Reset All User Sessions', 'rapls-ai-chatbot'); ?></button>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <select><option><?php esc_html_e('CSV', 'rapls-ai-chatbot'); ?></option><option><?php esc_html_e('JSON', 'rapls-ai-chatbot'); ?></option></select>
                <input type="date">
                <input type="date">
                <button class="button"><?php esc_html_e('Export', 'rapls-ai-chatbot'); ?> <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 10px; margin-left: 4px;">PRO</span></button>
            </div>
        </div>

        <!-- Conversations Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30px;"><input type="checkbox"></th>
                    <th style="width: 50px;">ID</th>
                    <th><?php esc_html_e('Session', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 50px; text-align: center;"><?php esc_html_e('Msgs', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Start Page', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Started', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Last Updated', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="checkbox"></td>
                    <td>1</td>
                    <td><code>abc123...</code></td>
                    <td style="text-align: center;">6</td>
                    <td>Taro Yamada<br><small>taro@example.com</small></td>
                    <td>/</td>
                    <td><span style="background: #e7f5e7; color: #00a32a; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></span></td>
                    <td>2026/02/13 10:00</td>
                    <td>2026/02/13 10:15</td>
                    <td>
                        <button class="button button-small"><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td><input type="checkbox"></td>
                    <td>2</td>
                    <td><code>def456...</code></td>
                    <td style="text-align: center;">4</td>
                    <td>&mdash;</td>
                    <td>/about/</td>
                    <td><span style="background: #f0f0f1; color: #50575e; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></span></td>
                    <td>2026/02/12 15:30</td>
                    <td>2026/02/12 15:45</td>
                    <td>
                        <button class="button button-small"><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td><input type="checkbox"></td>
                    <td>3</td>
                    <td><code>ghi789...</code></td>
                    <td style="text-align: center;">8</td>
                    <td>Hanako Suzuki<br><small>hanako@example.com</small></td>
                    <td>/contact/</td>
                    <td><span style="background: #f0f0f1; color: #50575e; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></span></td>
                    <td>2026/02/11 09:15</td>
                    <td>2026/02/11 09:30</td>
                    <td>
                        <button class="button button-small"><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="tablenav bottom" style="margin-top: 10px;">
            <span style="color: #666;">3 <?php esc_html_e('items', 'rapls-ai-chatbot'); ?></span>
        </div>

        <!-- Conversation Detail Panel -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-top: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Conversation Detail', 'rapls-ai-chatbot'); ?> — #1</h3>
            <div style="display: flex; gap: 20px; margin-bottom: 15px; font-size: 13px; color: #666;">
                <span><strong><?php esc_html_e('Lead:', 'rapls-ai-chatbot'); ?></strong> Taro Yamada</span>
                <span><strong><?php esc_html_e('Page:', 'rapls-ai-chatbot'); ?></strong> /</span>
                <span><strong><?php esc_html_e('Messages:', 'rapls-ai-chatbot'); ?></strong> 6</span>
                <span><strong><?php esc_html_e('Duration:', 'rapls-ai-chatbot'); ?></strong> 5<?php esc_html_e('min', 'rapls-ai-chatbot'); ?></span>
            </div>
            <div style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; max-height: 200px; overflow-y: auto; background: #fafafa;">
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 12px; color: #999; margin-bottom: 3px;"><strong><?php esc_html_e('Visitor', 'rapls-ai-chatbot'); ?></strong> — 10:00</div>
                    <div style="background: #e8f0fe; padding: 8px 12px; border-radius: 8px; display: inline-block; max-width: 70%;"><?php esc_html_e('How do I reset my password?', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div style="margin-bottom: 12px; text-align: right;">
                    <div style="font-size: 12px; color: #999; margin-bottom: 3px;"><strong><?php esc_html_e('Bot', 'rapls-ai-chatbot'); ?></strong> — 10:00</div>
                    <div style="background: #667eea; color: #fff; padding: 8px 12px; border-radius: 8px; display: inline-block; max-width: 70%; text-align: left;">
                        <?php esc_html_e('You can reset your password from the account settings page...', 'rapls-ai-chatbot'); ?>
                        <div style="margin-top: 6px; display: flex; gap: 6px; align-items: center;">
                            <span style="background: rgba(255,255,255,0.2); padding: 1px 6px; border-radius: 3px; font-size: 10px;">👍 1</span>
                            <span style="background: rgba(255,255,255,0.2); padding: 1px 6px; border-radius: 3px; font-size: 10px;">gpt-4o</span>
                            <span style="background: rgba(255,255,255,0.2); padding: 1px 6px; border-radius: 3px; font-size: 10px;">245 tokens</span>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top: 10px;">
                <strong><?php esc_html_e('AI Summary:', 'rapls-ai-chatbot'); ?></strong>
                <span style="color: #666; font-size: 13px;"><?php esc_html_e('User inquired about password reset. Bot provided instructions to use the account settings page. Issue resolved.', 'rapls-ai-chatbot'); ?></span>
            </div>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .raplsaich-pro-preview -->
            </div><!-- .raplsaich-pro-preview-wrapper -->

            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Conversation export (CSV/JSON)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Advanced search & status filter', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('AI conversation summary', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Feedback & AI metadata on messages', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Response improvement suggestions', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Session management & reset', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Human handoff history', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Bulk operations', 'rapls-ai-chatbot'); ?></li>
                </ul>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <p style="margin: 0 0 8px; color: #1d2327; font-weight: 600;">
                        <?php esc_html_e('Export Use Cases:', 'rapls-ai-chatbot'); ?>
                    </p>
                    <ul style="margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('CRM integration via CSV import', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Monthly conversation reports', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Customer support analysis', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Team training data', 'rapls-ai-chatbot'); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div><!-- .wrap -->
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Analytics preview
     */
    private function render_analytics_preview(): void {
        $this->render_pro_preview_start(
            __('Analytics', 'rapls-ai-chatbot'),
            __('Get insights into your chatbot performance, user satisfaction, frequently asked questions, and more.', 'rapls-ai-chatbot')
        );
        ?>
        <!-- Period Selector -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 20px 0; background: #fff; padding: 15px 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
            <div>
                <label><?php esc_html_e('Period:', 'rapls-ai-chatbot'); ?></label>
                <select style="margin-left: 10px;">
                    <option><?php esc_html_e('Last 7 days', 'rapls-ai-chatbot'); ?></option>
                    <option selected><?php esc_html_e('Last 30 days', 'rapls-ai-chatbot'); ?></option>
                    <option><?php esc_html_e('Last 90 days', 'rapls-ai-chatbot'); ?></option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="button">
                    <span class="dashicons dashicons-printer" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Print / PDF', 'rapls-ai-chatbot'); ?>
                </button>
                <button class="button button-primary">
                    <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Download PDF', 'rapls-ai-chatbot'); ?>
                </button>
            </div>
        </div>

        <!-- Stats Cards (8 cards matching Pro layout: icon box + value + label + delta) -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
            <!-- Conversations -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-format-chat" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">128</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Conversations', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #00a32a; margin-top: 3px;">&#9650; 12%</div>
                </div>
            </div>
            <!-- Messages -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-email-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">512</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Messages', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #00a32a; margin-top: 3px;">&#9650; 8%</div>
                </div>
            </div>
            <!-- Avg Messages/Conv -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-chart-line" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">4.0</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Avg Messages/Conv', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #d63638; margin-top: 3px;">&#9660; 5%</div>
                </div>
            </div>
            <!-- Satisfaction Rate -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-thumbs-up" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">85%</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Satisfaction Rate', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #00a32a; margin-top: 3px;">&#9650; 3%</div>
                </div>
            </div>
            <!-- Estimated Cost -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-money-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">$1.24</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Estimated Cost', 'rapls-ai-chatbot'); ?></div>
                    <div style="color: #646970; font-size: 12px; margin-top: 3px;">18,420 <?php esc_html_e('tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>
            <!-- AI Quality Score -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-awards" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">78<span style="font-size: 14px;">/100</span></div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('AI Quality Score', 'rapls-ai-chatbot'); ?></div>
                    <div style="color: #646970; font-size: 11px; margin-top: 3px;"><?php
                        printf(
                            /* translators: 1: feedback rate, 2: response rate */
                            esc_html__('FB: %1$s%% | Resp: %2$s%%', 'rapls-ai-chatbot'),
                            '9.8',
                            '95'
                        );
                    ?></div>
                </div>
            </div>
            <!-- Bounce Rate -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="dashicons dashicons-migrate" style="font-size: 24px; width: 24px; height: 24px;"></span>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1;">15%</div>
                    <div style="color: #646970; font-size: 13px; margin-top: 5px;"><?php esc_html_e('Bounce Rate', 'rapls-ai-chatbot'); ?></div>
                    <div style="color: #646970; font-size: 11px; margin-top: 3px;"><?php
                        printf(
                            /* translators: 1: avg conversation depth, 2: return visitor rate */
                            esc_html__('Depth: %1$s | Return: %2$s%%', 'rapls-ai-chatbot'),
                            '3.2',
                            '24'
                        );
                    ?></div>
                </div>
            </div>
        </div>

        <!-- Conversation Drop-off Distribution (2/3 width like Pro) -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Conversation Drop-off Distribution', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 200px; background: linear-gradient(to bottom, #f8f9fa, #fff); border-radius: 4px; display: flex; align-items: flex-end; justify-content: space-around; padding: 20px 10px 0;">
                    <?php
                    $dropoff = array(40, 25, 15, 10, 5, 3, 2);
                    $dropoff_max = max($dropoff);
                    foreach ($dropoff as $idx => $val):
                        $pct = round($val / $dropoff_max * 100);
                    ?>
                    <div style="width: 10%; height: <?php echo esc_attr($pct); ?>%; background: rgba(239, 68, 68, 0.6); border: 1px solid #ef4444; border-radius: 3px 3px 0 0; position: relative;">
                        <span style="position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #666;"><?php echo esc_html($val); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-around; font-size: 11px; color: #999; margin-top: 5px;">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                    <span><?php echo esc_html($i); ?> <?php esc_html_e('msgs', 'rapls-ai-chatbot'); ?></span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Charts Row: Daily Conversations (2fr) + Hourly Distribution (1fr) -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Daily Conversations', 'rapls-ai-chatbot'); ?></h3>
                <?php
                $daily_vals = array(5, 8, 12, 7, 15, 10, 18, 14, 20, 16, 22, 13, 19, 11);
                $daily_max = max($daily_vals);
                $daily_count = count($daily_vals);
                $svg_w = 560;
                $svg_h = 180;
                $pad_x = 10;
                $pad_y = 10;
                $chart_w = $svg_w - 2 * $pad_x;
                $chart_h = $svg_h - 2 * $pad_y;
                $pts = array();
                foreach ($daily_vals as $i => $v) {
                    $pts[] = array(
                        round($pad_x + ($daily_count > 1 ? $i / ($daily_count - 1) * $chart_w : 0), 1),
                        round($pad_y + $chart_h - ($daily_max > 0 ? $v / $daily_max * $chart_h : 0), 1),
                    );
                }
                $curve_d = $this->svg_smooth_curve($pts);
                $bottom_y = round($pad_y + $chart_h, 1);
                $fill_d = $curve_d . ' L' . $pts[count($pts) - 1][0] . ',' . $bottom_y . ' L' . $pts[0][0] . ',' . $bottom_y . ' Z';
                ?>
                <div style="height: 200px; background: linear-gradient(to bottom, #f8f9fa, #fff); border-radius: 4px; padding: 10px;">
                    <svg viewBox="0 0 <?php echo esc_attr($svg_w); ?> <?php echo esc_attr($svg_h); ?>" style="width: 100%; height: 100%;" preserveAspectRatio="none">
                        <path d="<?php echo esc_attr($fill_d); ?>" fill="rgba(102, 126, 234, 0.1)" />
                        <path d="<?php echo esc_attr($curve_d); ?>" fill="none" stroke="#667eea" stroke-width="2" />
                        <?php foreach ($pts as $pt): ?>
                        <circle cx="<?php echo esc_attr($pt[0]); ?>" cy="<?php echo esc_attr($pt[1]); ?>" r="3" fill="#667eea" />
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #999; margin-top: 5px;">
                    <?php
                    $base_date = strtotime('-' . ($daily_count - 1) . ' days');
                    for ($i = 0; $i < $daily_count; $i++):
                        $d = gmdate('n/j', $base_date + $i * 86400);
                    ?>
                    <span><?php echo esc_html($d); ?></span>
                    <?php endfor; ?>
                </div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Hourly Distribution', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 200px; display: flex; align-items: flex-end; gap: 2px; padding: 10px 0 0;">
                    <?php
                    $hours = array(2, 1, 0, 1, 3, 8, 15, 22, 25, 30, 28, 20, 18, 22, 25, 20, 15, 12, 8, 5, 4, 3, 2, 1);
                    $max_h = max($hours);
                    foreach ($hours as $idx => $count):
                        $pct = $max_h > 0 ? round($count / $max_h * 100) : 0;
                    ?>
                    <div style="flex: 1; height: <?php echo esc_attr($pct); ?>%; background: #764ba2; border-radius: 2px 2px 0 0;" title="<?php echo esc_attr($idx); ?>:00 - <?php echo esc_attr($count); ?> <?php esc_attr_e('conversations', 'rapls-ai-chatbot'); ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 9px; color: #999; margin-top: 5px;">
                    <?php for ($h = 0; $h < 24; $h++): ?>
                    <span><?php echo esc_html($h); ?></span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Two Column: Top Questions + Top Pages -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Frequently Asked Questions', 'rapls-ai-chatbot'); ?></h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;">#</th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Question', 'rapls-ai-chatbot'); ?></th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600; cursor: pointer;"><?php esc_html_e('Count', 'rapls-ai-chatbot'); ?> &#9660;</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">1</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('How do I reset my password?', 'rapls-ai-chatbot'); ?></td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>24</strong></td></tr>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">2</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('What are your business hours?', 'rapls-ai-chatbot'); ?></td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>18</strong></td></tr>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">3</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('How to contact support?', 'rapls-ai-chatbot'); ?></td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>12</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Top Pages', 'rapls-ai-chatbot'); ?></h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;">#</th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Page', 'rapls-ai-chatbot'); ?></th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600; cursor: pointer;"><?php esc_html_e('Conversations', 'rapls-ai-chatbot'); ?> &#9660;</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">1</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">https://example.com/</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>45</strong></td></tr>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">2</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">https://example.com/pricing/</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>32</strong></td></tr>
                        <tr><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">3</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">https://example.com/contact/</td><td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>21</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Device Statistics -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Device Statistics', 'rapls-ai-chatbot'); ?></h3>
                <div style="display: flex; gap: 30px; padding: 20px 0;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-desktop" style="font-size: 32px; width: 32px; height: 32px; color: #667eea;"></span>
                        <span style="color: #646970; font-size: 12px;"><?php esc_html_e('Desktop', 'rapls-ai-chatbot'); ?></span>
                        <span style="font-size: 18px; font-weight: 600; color: #1d2327;">62%</span>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-smartphone" style="font-size: 32px; width: 32px; height: 32px; color: #667eea;"></span>
                        <span style="color: #646970; font-size: 12px;"><?php esc_html_e('Mobile', 'rapls-ai-chatbot'); ?></span>
                        <span style="font-size: 18px; font-weight: 600; color: #1d2327;">32%</span>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-tablet" style="font-size: 32px; width: 32px; height: 32px; color: #667eea;"></span>
                        <span style="color: #646970; font-size: 12px;"><?php esc_html_e('Tablet', 'rapls-ai-chatbot'); ?></span>
                        <span style="font-size: 18px; font-weight: 600; color: #1d2327;">6%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Country Statistics -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;">
                    <span class="dashicons dashicons-admin-site-alt3" style="color: #6366f1; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Country Statistics', 'rapls-ai-chatbot'); ?>
                </h3>
                <div style="padding: 10px 0;">
                    <?php
                    $sample_countries = array(
                        array('flag' => "\xF0\x9F\x87\xAF\xF0\x9F\x87\xB5", 'name' => __('Japan', 'rapls-ai-chatbot'), 'count' => 85, 'pct' => 66.4),
                        array('flag' => "\xF0\x9F\x87\xBA\xF0\x9F\x87\xB8", 'name' => __('United States', 'rapls-ai-chatbot'), 'count' => 22, 'pct' => 17.2),
                        array('flag' => "\xF0\x9F\x87\xAC\xF0\x9F\x87\xA7", 'name' => __('United Kingdom', 'rapls-ai-chatbot'), 'count' => 11, 'pct' => 8.6),
                        array('flag' => "\xF0\x9F\x87\xA9\xF0\x9F\x87\xAA", 'name' => __('Germany', 'rapls-ai-chatbot'), 'count' => 6, 'pct' => 4.7),
                        array('flag' => "\xF0\x9F\x87\xAB\xF0\x9F\x87\xB7", 'name' => __('France', 'rapls-ai-chatbot'), 'count' => 4, 'pct' => 3.1),
                    );
                    foreach ($sample_countries as $country):
                    ?>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 6px 0;">
                        <span style="min-width: 140px; font-size: 13px; color: #1d2327;"><?php echo esc_html($country['flag'] . ' ' . $country['name']); ?></span>
                        <div style="flex: 1; height: 18px; background: #f0f0f1; border-radius: 9px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo esc_attr($country['pct']); ?>%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 9px; min-width: 2px;"></div>
                        </div>
                        <span style="min-width: 90px; text-align: right; font-size: 12px; color: #646970; white-space: nowrap;"><?php echo esc_html($country['count']); ?> (<?php echo esc_html($country['pct']); ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Usage & Cost -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;">
                    <span class="dashicons dashicons-chart-area" style="color: #f59e0b; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Daily Cost Trend', 'rapls-ai-chatbot'); ?>
                </h3>
                <?php
                $cost_vals = array(5, 12, 8, 15, 10, 18, 7, 20, 14, 22, 16, 11, 19, 13);
                $cost_max = max($cost_vals);
                $cost_count = count($cost_vals);
                $csv_w = 560;
                $csv_h = 180;
                $cpad_x = 10;
                $cpad_y = 10;
                $cchart_w = $csv_w - 2 * $cpad_x;
                $cchart_h = $csv_h - 2 * $cpad_y;
                $cpts = array();
                foreach ($cost_vals as $i => $v) {
                    $cpts[] = array(
                        round($cpad_x + ($cost_count > 1 ? $i / ($cost_count - 1) * $cchart_w : 0), 1),
                        round($cpad_y + $cchart_h - ($cost_max > 0 ? $v / $cost_max * $cchart_h : 0), 1),
                    );
                }
                $cost_curve_d = $this->svg_smooth_curve($cpts);
                $cbottom_y = round($cpad_y + $cchart_h, 1);
                $cost_fill_d = $cost_curve_d . ' L' . $cpts[count($cpts) - 1][0] . ',' . $cbottom_y . ' L' . $cpts[0][0] . ',' . $cbottom_y . ' Z';
                ?>
                <div style="height: 200px; background: linear-gradient(to bottom, #f8f9fa, #fff); border-radius: 4px; padding: 10px;">
                    <svg viewBox="0 0 <?php echo esc_attr($csv_w); ?> <?php echo esc_attr($csv_h); ?>" style="width: 100%; height: 100%;" preserveAspectRatio="none">
                        <path d="<?php echo esc_attr($cost_fill_d); ?>" fill="rgba(245, 158, 11, 0.1)" />
                        <path d="<?php echo esc_attr($cost_curve_d); ?>" fill="none" stroke="#f59e0b" stroke-width="2" />
                        <?php foreach ($cpts as $pt): ?>
                        <circle cx="<?php echo esc_attr($pt[0]); ?>" cy="<?php echo esc_attr($pt[1]); ?>" r="3" fill="#f59e0b" />
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #999; margin-top: 5px;">
                    <?php
                    $cost_base = strtotime('-' . ($cost_count - 1) . ' days');
                    for ($i = 0; $i < $cost_count; $i++):
                        $d = gmdate('n/j', $cost_base + $i * 86400);
                    ?>
                    <span><?php echo esc_html($d); ?></span>
                    <?php endfor; ?>
                </div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="display: flex; align-items: center; justify-content: space-between; margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;">
                    <span>
                        <span class="dashicons dashicons-money-alt" style="color: #f59e0b; vertical-align: text-bottom;"></span>
                        <?php esc_html_e('Cost Breakdown by Model', 'rapls-ai-chatbot'); ?>
                    </span>
                    <button class="button button-small">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom; font-size: 16px;"></span>
                        <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?>
                    </button>
                </h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Messages', 'rapls-ai-chatbot'); ?></th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Tokens', 'rapls-ai-chatbot'); ?></th>
                            <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Cost', 'rapls-ai-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><code style="font-size: 12px;">gpt-4o</code></td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">320</td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">12,800</td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>$0.89</strong></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><code style="font-size: 12px;">gpt-4o-mini</code></td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">192</td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">5,620</td>
                            <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>$0.35</strong></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: 700; border-top: 2px solid #c3c4c7;">
                            <td style="padding: 10px 8px; padding-top: 12px; font-size: 13px;"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></td>
                            <td style="padding: 10px 8px; padding-top: 12px; font-size: 13px;">512</td>
                            <td style="padding: 10px 8px; padding-top: 12px; font-size: 13px;">18,420</td>
                            <td style="padding: 10px 8px; padding-top: 12px; font-size: 13px;">$1.24</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Feedback Analytics -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;">
                <span class="dashicons dashicons-thumbs-up" style="color: #667eea; vertical-align: text-bottom;"></span>
                <?php esc_html_e('Feedback Analytics', 'rapls-ai-chatbot'); ?>
            </h3>

            <!-- Feedback Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                <div style="text-align: center; padding: 18px 12px; border-radius: 8px; border: 1px solid #bbf7d0; background: #f0fdf4;">
                    <span style="font-size: 28px; display: block; margin-bottom: 6px;">&#128077;</span>
                    <div style="font-size: 24px; font-weight: 700; color: #1d2327; line-height: 1.2;">42</div>
                    <div style="font-size: 12px; color: #646970; margin-top: 4px;"><?php echo esc_html(_x('Positive', 'feedback', 'rapls-ai-chatbot')); ?></div>
                </div>
                <div style="text-align: center; padding: 18px 12px; border-radius: 8px; border: 1px solid #fecaca; background: #fef2f2;">
                    <span style="font-size: 28px; display: block; margin-bottom: 6px;">&#128078;</span>
                    <div style="font-size: 24px; font-weight: 700; color: #1d2327; line-height: 1.2;">8</div>
                    <div style="font-size: 12px; color: #646970; margin-top: 4px;"><?php echo esc_html(_x('Negative', 'feedback', 'rapls-ai-chatbot')); ?></div>
                </div>
                <div style="text-align: center; padding: 18px 12px; border-radius: 8px; border: 1px solid #bae6fd; background: #f0f9ff;">
                    <span style="font-size: 28px; display: block; margin-bottom: 6px;">&#128172;</span>
                    <div style="font-size: 24px; font-weight: 700; color: #1d2327; line-height: 1.2;">50</div>
                    <div style="font-size: 12px; color: #646970; margin-top: 4px;"><?php esc_html_e('Total Feedback', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div style="text-align: center; padding: 18px 12px; border-radius: 8px; border: 1px solid #e9d5ff; background: #faf5ff;">
                    <span style="font-size: 28px; display: block; margin-bottom: 6px;">&#128200;</span>
                    <div style="font-size: 24px; font-weight: 700; color: #1d2327; line-height: 1.2;">9.8%</div>
                    <div style="font-size: 12px; color: #646970; margin-top: 4px;"><?php esc_html_e('Feedback Rate', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>
            <!-- Satisfaction Bar -->
            <div style="margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    <span>&#128077; 84%</span>
                    <span>&#128078; 16%</span>
                </div>
                <div style="height: 24px; background: #fecaca; border-radius: 12px; overflow: hidden;">
                    <div style="height: 100%; width: 84%; background: linear-gradient(90deg, #22c55e, #4ade80); border-radius: 12px 0 0 12px; min-width: 2px;"></div>
                </div>
            </div>
            <!-- Daily Feedback Trend -->
            <div style="margin-top: 20px;">
                <h4 style="margin: 0 0 10px; font-size: 13px; color: #646970;"><?php esc_html_e('Daily Feedback Trend', 'rapls-ai-chatbot'); ?></h4>
                <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 10px; font-size: 12px;">
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: rgba(34, 197, 94, 0.7); border: 1px solid #22c55e; border-radius: 2px; vertical-align: middle; margin-right: 4px;"></span><?php echo esc_html(_x('Positive', 'feedback', 'rapls-ai-chatbot')); ?></span>
                    <span><span style="display: inline-block; width: 12px; height: 12px; background: rgba(239, 68, 68, 0.7); border: 1px solid #ef4444; border-radius: 2px; vertical-align: middle; margin-right: 4px;"></span><?php echo esc_html(_x('Negative', 'feedback', 'rapls-ai-chatbot')); ?></span>
                </div>
                <div style="height: 150px; display: flex; align-items: flex-end; justify-content: space-around; gap: 4px; padding: 10px 0 0;">
                    <?php
                    $fb_positive = array(3, 5, 4, 6, 3, 7, 5, 4, 6, 3, 5, 4, 7, 5);
                    $fb_negative = array(1, 0, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1, 1, 0);
                    $fb_max = max(array_map(function($p, $n) { return $p + $n; }, $fb_positive, $fb_negative));
                    foreach ($fb_positive as $idx => $pos):
                        $neg = $fb_negative[$idx];
                        $total_pct = $fb_max > 0 ? round(($pos + $neg) / $fb_max * 100) : 0;
                        $pos_pct = ($pos + $neg) > 0 ? round($pos / ($pos + $neg) * 100) : 0;
                    ?>
                    <div style="flex: 1; height: <?php echo esc_attr($total_pct); ?>%; display: flex; flex-direction: column; justify-content: flex-end;">
                        <div style="height: <?php echo esc_attr(100 - $pos_pct); ?>%; background: rgba(239, 68, 68, 0.7); border: 1px solid #ef4444; border-radius: 2px 2px 0 0; min-height: <?php echo $neg > 0 ? '2px' : '0'; ?>;"></div>
                        <div style="height: <?php echo esc_attr($pos_pct); ?>%; background: rgba(34, 197, 94, 0.7); border: 1px solid #22c55e;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #999; margin-top: 5px;">
                    <?php
                    $fb_count = count($fb_positive);
                    $fb_base = strtotime('-' . ($fb_count - 1) . ' days');
                    for ($i = 0; $i < $fb_count; $i++):
                        $d = gmdate('n/j', $fb_base + $i * 86400);
                    ?>
                    <span><?php echo esc_html($d); ?></span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Knowledge Gaps -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;">
                <span class="dashicons dashicons-lightbulb" style="color: #f59e0b; vertical-align: text-bottom;"></span>
                <?php esc_html_e('Knowledge Gaps', 'rapls-ai-chatbot'); ?>
                <span style="font-weight: 400; font-size: 12px; color: #646970; margin-left: 8px;">
                    <?php esc_html_e('Frequently asked questions not covered by your knowledge base', 'rapls-ai-chatbot'); ?>
                </span>
            </h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;">#</th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Question', 'rapls-ai-chatbot'); ?></th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600; width: 80px;"><?php esc_html_e('Times Asked', 'rapls-ai-chatbot'); ?></th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600; width: 120px;"><?php esc_html_e('Action', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">1</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('Do you offer a free trial?', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>8</strong></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><button class="button button-small"><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">2</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('Can I integrate with Slack?', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>5</strong></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><button class="button button-small"><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">3</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('What payment methods do you accept?', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><strong>3</strong></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><button class="button button-small"><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Negative Feedback -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #1d2327;"><?php esc_html_e('Negative Feedback (Needs Improvement)', 'rapls-ai-chatbot'); ?></h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('User Question', 'rapls-ai-chatbot'); ?></th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Bot Answer (excerpt)', 'rapls-ai-chatbot'); ?></th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Page', 'rapls-ai-chatbot'); ?></th>
                        <th style="padding: 10px 8px; text-align: left; border-bottom: 1px solid #f0f0f1; font-size: 13px; background: #f6f7f7; font-weight: 600;"><?php esc_html_e('Date', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('How much does the Pro version cost?', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e("I'm sorry, I don't have specific pricing information...", 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">/pricing/</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">2026-02-13</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('Can I cancel my subscription?', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;"><?php esc_html_e('Based on general practices, most services allow...', 'rapls-ai-chatbot'); ?></td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">/faq/</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #f0f0f1; font-size: 13px;">2026-02-12</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .raplsaich-pro-preview -->
            </div><!-- .raplsaich-pro-preview-wrapper -->

            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Interactive analytics dashboard', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('FAQ ranking & auto-generation', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Feedback analytics & satisfaction tracking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Time & device analysis', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Knowledge gap detection', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Negative feedback review & improvement', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('PDF report download', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Period comparison analytics', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('AI Quality Score', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Churn & bounce analysis', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Monthly email reports', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Conversion tracking analytics', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Country statistics', 'rapls-ai-chatbot'); ?></li>
                </ul>
            </div>
        </div><!-- .wrap -->
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Leads preview
     */
    private function render_leads_preview(): void {
        $this->render_pro_preview_start(
            __('Leads', 'rapls-ai-chatbot'),
            __('Capture and manage visitor information collected through the chatbot. Export leads to CSV/JSON and receive email notifications.', 'rapls-ai-chatbot')
        );
        ?>
        <!-- Export Buttons -->
        <div style="display: flex; gap: 8px; margin-bottom: 15px;">
            <button class="button"><?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?></button>
            <button class="button"><?php esc_html_e('Export JSON', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Filters -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Search by name, email, or company...', 'rapls-ai-chatbot'); ?>">
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('From:', 'rapls-ai-chatbot'); ?></label>
            <input type="date">
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('To:', 'rapls-ai-chatbot'); ?></label>
            <input type="date">
            <button class="button"><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
            <button class="button"><?php esc_html_e('Clear', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #1d2327;">15</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Total Leads', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #667eea;">3</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('Today', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #1d2327;">8</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('This Week', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 15px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #1d2327;">15</div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e('This Month', 'rapls-ai-chatbot'); ?></div>
            </div>
        </div>

        <div style="padding: 10px 0; color: #666;">3 <?php esc_html_e('leads found', 'rapls-ai-chatbot'); ?></div>

        <!-- Leads Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Email', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 110px;"><?php esc_html_e('Phone', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Company', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Type', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Conversation', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('Date', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Taro Yamada</strong></td>
                    <td><a href="#">taro@example.com</a></td>
                    <td><a href="#">090-1234-5678</a></td>
                    <td>Example Inc.</td>
                    <td><span style="background: #e8f0fe; color: #1a73e8; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></span></td>
                    <td><button class="button button-small">#1</button></td>
                    <td>2026/02/13 10:30</td>
                    <td><button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
                <tr>
                    <td><strong>Hanako Suzuki</strong></td>
                    <td><a href="#">hanako@example.com</a></td>
                    <td><a href="#">080-9876-5432</a></td>
                    <td>Test Corp.</td>
                    <td><span style="background: #fef3e2; color: #e65100; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Offline', 'rapls-ai-chatbot'); ?></span></td>
                    <td><button class="button button-small">#2</button></td>
                    <td>2026/02/12 14:20</td>
                    <td><button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
                <tr>
                    <td><strong>Ichiro Tanaka</strong></td>
                    <td><a href="#">ichiro@example.com</a></td>
                    <td>&mdash;</td>
                    <td>&mdash;</td>
                    <td><span style="background: #e8f0fe; color: #1a73e8; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></span></td>
                    <td><button class="button button-small">#3</button></td>
                    <td>2026/02/11 16:45</td>
                    <td><button class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
            </tbody>
        </table>
        <div class="tablenav bottom" style="margin-top: 10px;">
            <span style="color: #666;">3 <?php esc_html_e('items', 'rapls-ai-chatbot'); ?></span>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .raplsaich-pro-preview -->
            </div><!-- .raplsaich-pro-preview-wrapper -->

            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Lead capture form customization', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('CSV/JSON export', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Email notifications for new leads', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Webhook notifications', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Custom fields support', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Lead-conversation linking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Lead status management', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Survey integration', 'rapls-ai-chatbot'); ?></li>
                </ul>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <p style="margin: 0 0 8px; color: #1d2327; font-weight: 600;">
                        <?php esc_html_e('Webhook Integration Examples:', 'rapls-ai-chatbot'); ?>
                    </p>
                    <ul style="margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Slack notifications for new leads', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Email alerts via Zapier / Make', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('CRM auto-registration (HubSpot, etc.)', 'rapls-ai-chatbot'); ?>
                        </li>
                        <li style="display: flex; align-items: center; gap: 6px; color: #50575e; font-size: 13px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size: 16px; width: 16px; height: 16px; color: #667eea;"></span>
                            <?php esc_html_e('Google Sheets logging', 'rapls-ai-chatbot'); ?>
                        </li>
                    </ul>
                    <p style="margin: 8px 0 0; color: #50575e; font-size: 12px;">
                        <?php esc_html_e('All webhooks are HMAC-signed for security.', 'rapls-ai-chatbot'); ?>
                    </p>
                </div>
            </div>
        </div><!-- .wrap -->
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Audit Log preview
     */
    private function render_audit_log_preview(): void {
        $this->render_pro_preview_start(
            __('Audit Log', 'rapls-ai-chatbot'),
            __('Track all administrative actions for compliance and security monitoring.', 'rapls-ai-chatbot')
        );
        ?>
        <p class="description" style="margin-bottom: 15px;"><?php esc_html_e('Track all administrative actions performed on the plugin.', 'rapls-ai-chatbot'); ?></p>

        <!-- Controls -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
            <select>
                <option><?php esc_html_e('All Actions', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Settings Updated', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Knowledge Created', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Conversations Exported', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Lead Exported', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('License Activated', 'rapls-ai-chatbot'); ?></option>
            </select>
            <input type="date">
            <input type="date">
            <input type="search" placeholder="<?php esc_attr_e('Search...', 'rapls-ai-chatbot'); ?>">
            <button class="button"><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
            <button class="button button-secondary" style="margin-left: auto;"><span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Audit Log Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 160px;"><?php esc_html_e('Date', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 180px;"><?php esc_html_e('Action', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('User', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Object', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>2026/02/20 10:30</td>
                    <td><span style="background: #e8f0fe; color: #1967d2; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Settings Updated', 'rapls-ai-chatbot'); ?></span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td><?php esc_html_e('Pro settings saved', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/19 15:20</td>
                    <td><span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Knowledge Created', 'rapls-ai-chatbot'); ?></span></td>
                    <td>admin</td>
                    <td>KB #12</td>
                    <td><?php esc_html_e('FAQ entry added: "How to reset password?"', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/18 09:00</td>
                    <td><span style="background: #fef7e0; color: #b06000; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Conversations Exported', 'rapls-ai-chatbot'); ?></span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td><?php esc_html_e('Exported 48 conversations as CSV', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/17 14:45</td>
                    <td><span style="background: #fef7e0; color: #b06000; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('Lead Exported', 'rapls-ai-chatbot'); ?></span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td><?php esc_html_e('Exported 15 leads as JSON', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/15 11:00</td>
                    <td><span style="background: #e8f0fe; color: #1967d2; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php esc_html_e('License Activated', 'rapls-ai-chatbot'); ?></span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td><?php esc_html_e('License activated successfully', 'rapls-ai-chatbot'); ?></td>
                </tr>
            </tbody>
        </table>
        <div class="tablenav bottom" style="margin-top: 10px;">
            <span style="color: #666;">5 <?php esc_html_e('items', 'rapls-ai-chatbot'); ?></span>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .raplsaich-pro-preview -->
            </div><!-- .raplsaich-pro-preview-wrapper -->

            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <ul>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Complete action audit trail', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('CSV export for compliance', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Action type filtering', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Date range search', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Configurable retention policy', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('User activity tracking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Settings change tracking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Approval workflow', 'rapls-ai-chatbot'); ?></li>
                </ul>
            </div>
        </div><!-- .wrap -->
        <?php
        $this->render_pro_preview_styles();
    }

    /**
     * Render Pro settings preview page with grayed-out features
     */
    private function render_pro_settings_preview(): void {
        ?>
        <div class="wrap raplsaich-admin">
            <h1><?php esc_html_e('Pro Settings', 'rapls-ai-chatbot'); ?></h1>

            <!-- Upgrade Banner -->
            <div class="raplsaich-pro-upgrade-banner">
                <div class="raplsaich-pro-upgrade-content">
                    <span class="dashicons dashicons-star-filled"></span>
                    <div>
                        <strong><?php esc_html_e('Upgrade to Pro', 'rapls-ai-chatbot'); ?></strong>
                        <p><?php esc_html_e('Unlock all Pro features including Lead Capture, Business Hours, Webhook, and more.', 'rapls-ai-chatbot'); ?></p>
                    </div>
                    <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" class="button button-primary">
                        <?php esc_html_e('Get Pro Version', 'rapls-ai-chatbot'); ?>
                    </a>
                </div>
            </div>

            <!-- Grayed-out Preview -->
            <div class="raplsaich-pro-preview-wrapper">
                <div class="raplsaich-pro-preview">
                    <div class="raplsaich-settings-tabs">
                        <!-- Group Tabs -->
                        <nav class="raplsaich-tab-groups-nav">
                            <a href="#" class="raplsaich-tab-group raplsaich-tab-group-active" data-group="customer">
                                <span class="dashicons dashicons-groups"></span>
                                <?php esc_html_e('Customer', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="raplsaich-tab-group" data-group="ai">
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php esc_html_e('AI', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="raplsaich-tab-group" data-group="operations">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php esc_html_e('Operations', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="raplsaich-tab-group" data-group="integrations">
                                <span class="dashicons dashicons-networking"></span>
                                <?php esc_html_e('Integrations', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="raplsaich-tab-group" data-group="management">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php esc_html_e('Management', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="raplsaich-tab-group" data-group="system">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e('System', 'rapls-ai-chatbot'); ?>
                            </a>
                        </nav>
                        <!-- Sub-tabs per group -->
                        <nav class="raplsaich-sub-tabs" data-for="customer">
                            <a href="#tab-lead" class="raplsaich-sub-tab raplsaich-sub-tab-active" data-tab="tab-lead"><?php esc_html_e('Lead Capture', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-offline" class="raplsaich-sub-tab" data-tab="tab-offline"><?php esc_html_e('Offline', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-conversion" class="raplsaich-sub-tab" data-tab="tab-conversion"><?php esc_html_e('Conversion', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-ui" class="raplsaich-sub-tab" data-tab="tab-ui"><?php esc_html_e('UI', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-badge" class="raplsaich-sub-tab" data-tab="tab-badge"><?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-white-label" class="raplsaich-sub-tab" data-tab="tab-white-label"><?php esc_html_e('Footer & CSS', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-chat-features" class="raplsaich-sub-tab" data-tab="tab-chat-features"><?php esc_html_e('Chat Features', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="raplsaich-sub-tabs" data-for="ai" style="display:none;">
                            <a href="#tab-ai" class="raplsaich-sub-tab" data-tab="tab-ai"><?php esc_html_e('AI Enhancement', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-prompts" class="raplsaich-sub-tab" data-tab="tab-prompts"><?php esc_html_e('AI Prompts', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-external-learning" class="raplsaich-sub-tab" data-tab="tab-external-learning"><?php esc_html_e('External Learning', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-test-mode" class="raplsaich-sub-tab" data-tab="tab-test-mode"><?php esc_html_e('Test Mode', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="raplsaich-sub-tabs" data-for="operations" style="display:none;">
                            <a href="#tab-business" class="raplsaich-sub-tab" data-tab="tab-business"><?php esc_html_e('Business Hours', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-handoff" class="raplsaich-sub-tab" data-tab="tab-handoff"><?php esc_html_e('Handoff', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-ai-forms" class="raplsaich-sub-tab" data-tab="tab-ai-forms"><?php esc_html_e('AI Forms', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-content" class="raplsaich-sub-tab" data-tab="tab-content"><?php esc_html_e('Moderation', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-actions" class="raplsaich-sub-tab" data-tab="tab-actions"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-scenarios" class="raplsaich-sub-tab" data-tab="tab-scenarios"><?php esc_html_e('Scenarios', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-bots" class="raplsaich-sub-tab" data-tab="tab-bots"><?php esc_html_e('Chatbots', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="raplsaich-sub-tabs" data-for="integrations" style="display:none;">
                            <a href="#tab-webhook" class="raplsaich-sub-tab" data-tab="tab-webhook"><?php esc_html_e('Webhook', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-line" class="raplsaich-sub-tab" data-tab="tab-line"><?php esc_html_e('LINE', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-integrations" class="raplsaich-sub-tab" data-tab="tab-integrations"><?php esc_html_e('Slack & Sheets', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-booking" class="raplsaich-sub-tab" data-tab="tab-booking"><?php esc_html_e('Booking', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="raplsaich-sub-tabs" data-for="management" style="display:none;">
                            <a href="#tab-budget" class="raplsaich-sub-tab" data-tab="tab-budget"><?php esc_html_e('Usage & Budget', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-roles" class="raplsaich-sub-tab" data-tab="tab-roles"><?php esc_html_e('Role Access', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-maintenance" class="raplsaich-sub-tab" data-tab="tab-maintenance"><?php esc_html_e('Backup', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-change-history" class="raplsaich-sub-tab" data-tab="tab-change-history"><?php esc_html_e('Change History', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-license" class="raplsaich-sub-tab" data-tab="tab-license"><?php esc_html_e('License', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="raplsaich-sub-tabs" data-for="system" style="display:none;">
                            <a href="#tab-cache" class="raplsaich-sub-tab" data-tab="tab-cache"><?php esc_html_e('Cache', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-queue" class="raplsaich-sub-tab" data-tab="tab-queue"><?php esc_html_e('Queue', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-performance" class="raplsaich-sub-tab" data-tab="tab-performance"><?php esc_html_e('Performance', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-security" class="raplsaich-sub-tab" data-tab="tab-security"><?php esc_html_e('Security', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-encryption" class="raplsaich-sub-tab" data-tab="tab-encryption"><?php esc_html_e('Encryption', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-vulnerability" class="raplsaich-sub-tab" data-tab="tab-vulnerability"><?php esc_html_e('Security Scan', 'rapls-ai-chatbot'); ?></a>
                        </nav>

                        <!-- Lead Capture Tab -->
                        <div id="tab-lead" class="tab-content active">
                            <h2><?php esc_html_e('Lead Capture', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Lead Capture', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Show lead capture form before chat', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Require Lead Info', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Require lead information before allowing chat', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Title', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Enter form title...', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Description', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="2"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Capture Fields', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" checked>
                                                <?php esc_html_e('Name', 'rapls-ai-chatbot'); ?>
                                                <select style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" checked>
                                                <?php esc_html_e('Email', 'rapls-ai-chatbot'); ?>
                                                <select style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox">
                                                <?php esc_html_e('Phone', 'rapls-ai-chatbot'); ?>
                                                <select style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox">
                                                <?php esc_html_e('Company', 'rapls-ai-chatbot'); ?>
                                                <select style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Custom Fields', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px; flex-wrap: wrap;">
                                            <input type="text" style="width: 140px;" placeholder="<?php esc_attr_e('Label', 'rapls-ai-chatbot'); ?>">
                                            <select style="width: 110px;">
                                                <option>Text</option>
                                                <option>Email</option>
                                                <option>Tel</option>
                                                <option>Textarea</option>
                                                <option>Select</option>
                                            </select>
                                            <label style="white-space: nowrap;">
                                                <input type="checkbox">
                                                <?php esc_html_e('Required', 'rapls-ai-chatbot'); ?>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Email Notification', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Send email notification for new leads', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <br><br>
                                        <input type="email" class="regular-text" placeholder="admin@example.com">
                                        <p class="description"><?php esc_html_e('Leave empty to use admin email.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Email Subject Prefix', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="Rapls AI Chatbot">
                                        <p class="description"><?php esc_html_e('Prefix used in the subject line of all notification emails. e.g., [Rapls AI Chatbot] New lead captured', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Business Hours Tab -->
                        <div id="tab-business" class="tab-content">
                            <h2><?php esc_html_e('Business Hours', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Business Hours', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Show special message outside business hours', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Timezone', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option>Asia/Tokyo</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Business Hours Schedule', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <table class="raplsaich-schedule-table">
                                            <tr>
                                                <td style="width: 100px;">
                                                    <label><input type="checkbox" checked> <?php esc_html_e('Monday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" style="width: 100px;"> -
                                                    <input type="time" value="18:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked> <?php esc_html_e('Tuesday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" style="width: 100px;"> -
                                                    <input type="time" value="18:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked> <?php esc_html_e('Wednesday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" style="width: 100px;"> -
                                                    <input type="time" value="18:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked> <?php esc_html_e('Thursday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" style="width: 100px;"> -
                                                    <input type="time" value="18:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked> <?php esc_html_e('Friday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" style="width: 100px;"> -
                                                    <input type="time" value="18:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox"> <?php esc_html_e('Saturday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="10:00" style="width: 100px;"> -
                                                    <input type="time" value="15:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox"> <?php esc_html_e('Sunday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="10:00" style="width: 100px;"> -
                                                    <input type="time" value="15:00" style="width: 100px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Outside Hours Message', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="2" placeholder="<?php esc_attr_e('Thank you for your message. We are currently outside of business hours...', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Holidays', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable holiday schedule', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Holiday Dates', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea rows="5" class="large-text" placeholder="2026-01-01&#10;2026-03-21"></textarea>
                                        <p class="description"><?php esc_html_e('One date per line (YYYY-MM-DD format).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Holiday Message', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea rows="2" class="large-text"></textarea>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Moderation Tab -->
                        <div id="tab-content" class="tab-content">
                            <h2><?php esc_html_e('Moderation', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Banned Words', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable banned words filter', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <br><br>
                                        <textarea class="large-text" rows="4" placeholder="<?php esc_attr_e('One word per line', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('IP Blocking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable IP blocking', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <br><br>
                                        <textarea class="large-text" rows="4" placeholder="<?php esc_attr_e('One IP per line (supports CIDR notation)', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Rate Limiting', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable enhanced rate limiting', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Per-minute/per-hour two-tier throttling to prevent spam. Overrides all basic rate limits when enabled.', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p class="description" style="margin-top:4px;padding:8px 12px;background:#f0f0f1;border-radius:4px;">
                                            <strong><?php esc_html_e('Basic rate limit (applied when this is OFF):', 'rapls-ai-chatbot'); ?></strong><br>
                                            <?php esc_html_e('1. Burst: 3 requests / 10 sec (hardcoded)', 'rapls-ai-chatbot'); ?><br>
                                            <?php esc_html_e('2. Sustained: 20 requests / 1 hour per IP (changeable in Settings > Security)', 'rapls-ai-chatbot'); ?><br>
                                            <?php esc_html_e('3. Global IP cap: 2x the sustained limit', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <br>
                                        <label><?php esc_html_e('Max messages per minute', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="5" style="width: 80px;">
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Max messages per hour', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="30" style="width: 80px;">
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Rate limit message', 'rapls-ai-chatbot'); ?></label>
                                        <input type="text" class="large-text" placeholder="<?php esc_attr_e('Too many messages. Please wait a moment before sending again.', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Webhook Tab -->
                        <div id="tab-webhook" class="tab-content">
                            <h2><?php esc_html_e('Webhook', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Webhook', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Send webhook notifications', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="url" class="large-text" placeholder="https://example.com/webhook">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook Secret', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text">
                                        <p class="description"><?php esc_html_e('Used for signature verification (optional).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Events', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('New conversation', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('New message', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Lead captured', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Handoff requested', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Handoff resolved', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Offline message', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('AI API error', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Budget alert', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Rate limit exceeded', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Banned word detected', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('reCAPTCHA failure (frequent)', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Select which events trigger webhook notifications.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"></th>
                                    <td>
                                        <button type="button" class="button"><?php esc_html_e('Test Webhook', 'rapls-ai-chatbot'); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- AI Enhancement Tab -->
                        <div id="tab-ai" class="tab-content">
                            <h2><?php esc_html_e('AI Enhancement', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Related Suggestions', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Show related question suggestions after each response', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Autocomplete', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Show autocomplete suggestions while typing', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Regenerate Button', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" checked>
                                            <?php esc_html_e('Show regenerate button on bot messages', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Sentiment Analysis', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Analyze user emotions and adjust AI response tone', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, AI will detect user sentiment (positive, negative, neutral) and respond appropriately.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Memory', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Remember conversation context across sessions', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, AI will remember previous conversations with the same user.', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <label>
                                            <?php esc_html_e('Memory retention days:', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="30" style="width: 80px;">
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Multimodal Support', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Allow users to upload and analyze images', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, users can upload images for AI analysis. Requires GPT-4 Vision, Claude 3, or Gemini.', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <label>
                                            <?php esc_html_e('Max image size (KB):', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="2048" style="width: 100px;">
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Allowed formats:', 'rapls-ai-chatbot'); ?></label>
                                        <br>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked> JPG
                                        </label>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked> PNG
                                        </label>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked> GIF
                                        </label>
                                        <label>
                                            <input type="checkbox" checked> WebP
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('File Upload', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Allow users to upload files (PDF, Word, etc.)', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <div style="margin-top: 8px;">
                                            <label>
                                                <?php esc_html_e('Max file size (KB):', 'rapls-ai-chatbot'); ?>
                                                <input type="number" value="5120" style="width: 100px;">
                                            </label>
                                        </div>
                                        <div style="margin-top: 8px;">
                                            <label><?php esc_html_e('Allowed file types:', 'rapls-ai-chatbot'); ?></label><br>
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" checked> PDF
                                            </label>
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" checked> DOC
                                            </label>
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" checked> DOCX
                                            </label>
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" checked> TXT
                                            </label>
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" checked> CSV
                                            </label>
                                            <label>
                                                <input type="checkbox"> XLSX
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Voice Input', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable microphone button for speech-to-text input', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Uses browser Web Speech API. Works in Chrome, Edge, and Safari. Not supported in Firefox.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Text-to-Speech', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable TTS toggle button to read bot responses aloud', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Uses browser SpeechSynthesis API. Users can toggle on/off in chat header.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('TTS Language', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="ja">
                                        <p class="description"><?php esc_html_e('Language code for text-to-speech and voice input (e.g., ja, en-US). Leave empty to auto-detect from site language.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('AI Content Generation', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable AI Assistant sidebar in the block editor', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Adds an AI sidebar panel to the WordPress editor for draft generation, text improvement, translation, and SEO metadata.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- AI Prompts Tab -->
                        <div id="tab-prompts" class="tab-content">
                            <h2><?php esc_html_e('AI Prompts', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Customize the AI prompts used by Pro features. Each field has a Reset button to restore defaults.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Sentiment Detection Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3" placeholder="<?php esc_attr_e('Analyze the sentiment...', 'rapls-ai-chatbot'); ?>"></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Prompt for detecting user sentiment.', 'rapls-ai-chatbot'); ?><br>
                                            <code>{message}</code> — <?php esc_html_e('User message', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Tone Adjustment Prompts', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('These prompts are appended to the system prompt based on detected sentiment.', 'rapls-ai-chatbot'); ?></p>
                                        <?php
                                        $tones = [
                                            'Frustrated' => __('Frustrated', 'rapls-ai-chatbot'),
                                            'Confused'   => __('Confused', 'rapls-ai-chatbot'),
                                            'Urgent'     => __('Urgent', 'rapls-ai-chatbot'),
                                            'Positive'   => __('Positive', 'rapls-ai-chatbot'),
                                            'Negative'   => __('Negative', 'rapls-ai-chatbot'),
                                        ];
                                        foreach ($tones as $key => $label): ?>
                                        <div style="margin-bottom: 8px;">
                                            <label style="display: block; font-weight: 600; margin-bottom: 4px;"><?php echo esc_html($label); ?></label>
                                            <textarea class="large-text" rows="2"></textarea>
                                            <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('FAQ Generation Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="5"></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Prompt for generating FAQ answers from knowledge gaps.', 'rapls-ai-chatbot'); ?><br>
                                            <code>{question}</code> — <?php esc_html_e('Unanswered user question', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Related Suggestions Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3"></textarea>
                                        <p class="description"><?php esc_html_e('Prompt for generating related question suggestions. No placeholders needed (the conversation is appended automatically).', 'rapls-ai-chatbot'); ?></p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Conversation Summary Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3"></textarea>
                                        <p class="description"><?php esc_html_e('Prompt for generating conversation summaries. No placeholders needed (the conversation is appended automatically).', 'rapls-ai-chatbot'); ?></p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Extraction Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3"></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Prompt for extracting user context from conversations.', 'rapls-ai-chatbot'); ?><br>
                                            <code>{conversation}</code> — <?php esc_html_e('Full conversation text', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Memory Template', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3"></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Template for injecting user context into system prompt.', 'rapls-ai-chatbot'); ?><br>
                                            <code>{summary}</code> — <?php esc_html_e('Conversation summary', 'rapls-ai-chatbot'); ?>&ensp;
                                            <code>{topics}</code> — <?php esc_html_e('Discussed topics', 'rapls-ai-chatbot'); ?>&ensp;
                                            <code>{preferences}</code> — <?php esc_html_e('User preferences', 'rapls-ai-chatbot'); ?>&ensp;
                                            <code>{last_date}</code> — <?php esc_html_e('Last interaction date', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p><button type="button" class="button button-small"><?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?></button></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Usage & Budget Tab -->
                        <div id="tab-budget" class="tab-content">
                            <h2><?php esc_html_e('Usage & Budget', 'rapls-ai-chatbot'); ?></h2>
                            <!-- Current Month Usage -->
                            <div style="background: #f0f0f1; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <h3 style="margin-top: 0;"><?php esc_html_e('Current Month Usage', 'rapls-ai-chatbot'); ?></h3>
                                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">$12.50</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Estimated Cost (USD)', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">256</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('AI Responses', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">128,000</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Total Tokens', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                        <span><?php esc_html_e('Budget Usage', 'rapls-ai-chatbot'); ?></span>
                                        <span>25%</span>
                                    </div>
                                    <div style="background: #ddd; border-radius: 4px; height: 8px; overflow: hidden;">
                                        <div style="background: #22c55e; height: 100%; width: 25%; border-radius: 4px; transition: width 0.3s;"></div>
                                    </div>
                                </div>
                            </div>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cost Alert', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" checked>
                                            <?php esc_html_e('Send email alert when monthly cost exceeds threshold', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Alert Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" value="admin@example.com">
                                        <p class="description"><?php esc_html_e('Leave empty to use admin email.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Alert Threshold (USD)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" value="10.00" style="width: 120px;">
                                        <p class="description"><?php esc_html_e('Alert sent once per month when cost exceeds this amount.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" colspan="2"><hr></th>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Budget Limit', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" checked>
                                            <?php esc_html_e('Block AI responses when monthly cost exceeds limit', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Limit Amount (USD)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" value="50.00" style="width: 120px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Block Message', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="2"><?php esc_html_e('The AI service is temporarily unavailable due to usage limits. Please try again later.', 'rapls-ai-chatbot'); ?></textarea>
                                        <p class="description"><?php esc_html_e('Message shown to visitors when budget limit is reached.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" colspan="2"><hr></th>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Monthly Report', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" checked>
                                            <?php esc_html_e('Send monthly usage report by email', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Report Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" value="admin@example.com">
                                        <p class="description"><?php esc_html_e('Leave empty to use admin email. Report sent on the 1st of each month.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" colspan="2"><hr></th>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Summary Report', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Send periodic summary report by email', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Frequency', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option value="daily"><?php esc_html_e('Daily', 'rapls-ai-chatbot'); ?></option>
                                            <option value="weekly" selected><?php esc_html_e('Weekly', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Summary Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" value="admin@example.com">
                                        <p class="description"><?php esc_html_e('Daily reports sent at 09:00, weekly reports sent every Monday at 09:00.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Badge Icon Tab -->
                        <div id="tab-badge" class="tab-content">
                            <h2><?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Customize the floating chat button icon displayed on your site.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Icon Type', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio" checked>
                                                <?php esc_html_e('Default (speech bubble)', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio">
                                                <?php esc_html_e('Preset icon', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio">
                                                <?php esc_html_e('Custom image', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio">
                                                <?php esc_html_e('Emoji', 'rapls-ai-chatbot'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Offline Messages Tab -->
                        <div id="tab-offline" class="tab-content">
                            <h2><?php esc_html_e('Offline Messages', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Show a contact form when the chatbot is outside business hours. Messages are saved as leads.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Offline Form', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Show offline message form outside business hours', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Requires Business Hours to be enabled. When outside business hours, visitors will see a contact form instead of the chat.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Title', 'rapls-ai-chatbot'); ?></th>
                                    <td><input type="text" class="regular-text" value="<?php esc_attr_e('We are currently offline', 'rapls-ai-chatbot'); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Description', 'rapls-ai-chatbot'); ?></th>
                                    <td><textarea class="large-text" rows="2"><?php esc_html_e('Please leave a message and we will get back to you.', 'rapls-ai-chatbot'); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Email Notification', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Send email notification when offline message is received', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Notification Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" value="admin@example.com">
                                        <p class="description"><?php esc_html_e('Leave empty to use admin email.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Conversion Tracking Tab -->
                        <div id="tab-conversion" class="tab-content">
                            <h2><?php esc_html_e('Conversion Tracking', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Track conversions from chatbot conversations. When a user navigates to a goal URL after chatting, it is recorded as a conversion.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Tracking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable conversion tracking', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Conversion Goals', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <div>
                                            <div style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                                                <input type="text" value="Purchase" style="width: 200px;">
                                                <input type="text" class="regular-text" value="/thank-you">
                                            </div>
                                            <div style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                                                <input type="text" value="Signup" style="width: 200px;">
                                                <input type="text" class="regular-text" value="/signup-success">
                                            </div>
                                        </div>
                                        <button type="button" class="button button-secondary">+ <?php esc_html_e('Add Goal', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('URL patterns can be plain text (substring match) or regular expressions. Example: /thank-you, /order-complete, ^https://.*\/checkout\/success', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Response Cache Tab -->
                        <div id="tab-cache" class="tab-content">
                            <h2><?php esc_html_e('Response Cache', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Cache AI responses to reduce API costs. When the same question is asked, the cached answer is returned without calling the AI API.', 'rapls-ai-chatbot'); ?></p>
                            <div style="background: #f0f0f1; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <h3 style="margin-top: 0;"><?php esc_html_e('Cache Statistics (Last 30 Days)', 'rapls-ai-chatbot'); ?></h3>
                                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">33%</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Cache Hit Rate', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">8</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Cache Hits', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">12,400</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Saved Tokens', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 24px; font-weight: 700; color: #1d2327;">24</div>
                                        <div style="color: #646970; font-size: 13px;"><?php esc_html_e('Total Requests', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Cache', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Cache AI responses to reduce API costs', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, identical questions will return the cached answer without calling the AI API. Responses with negative feedback are excluded from cache.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cache TTL (Days)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" value="7" style="width: 80px;">
                                        <p class="description"><?php esc_html_e('Number of days to keep cached responses. After this period, a fresh AI response will be generated.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Clear Cache', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <button type="button" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="dashicons dashicons-trash"></span>
                                            <?php esc_html_e('Clear All Cache', 'rapls-ai-chatbot'); ?>
                                        </button>
                                        <p class="description"><?php esc_html_e('Invalidate all cached responses. New AI responses will be generated for all questions.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Backup & Restore Tab -->
                        <div id="tab-maintenance" class="tab-content">
                            <h2><?php esc_html_e('Backup & Restore Settings', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Export or import all plugin settings. API keys are excluded from exports for security.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Export Settings', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <button type="button" class="button"><?php esc_html_e('Download Settings (JSON)', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('Export all plugin settings as a JSON file for backup or migration.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="file" accept=".json">
                                        <br><br>
                                        <button type="button" class="button"><?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('Import settings from a previously exported JSON file.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- License Tab -->
                        <div id="tab-license" class="tab-content">
                            <h2><?php esc_html_e('License', 'rapls-ai-chatbot'); ?></h2>
                            <p style="color: #d63638;">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('License not activated. Pro features are limited.', 'rapls-ai-chatbot'); ?>
                            </p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Email Address', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="email" class="regular-text" placeholder="your@email.com">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('License Key', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="RPLS-XXXX-XXXX-XXXX-XXXX" style="font-family: monospace;">
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="button" class="button button-primary"><?php esc_html_e('Activate License', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- UI Tab -->
                        <div id="tab-ui" class="tab-content">
                            <h2><?php esc_html_e('UI Enhancements', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Fullscreen Mode', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Show fullscreen toggle button in chat header', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Welcome Screen', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Show welcome screen when chat opens', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 8px;">
                                            <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Welcome title', 'rapls-ai-chatbot'); ?>" style="margin-bottom: 4px;"><br>
                                            <textarea class="large-text" rows="2" placeholder="<?php esc_attr_e('Welcome message', 'rapls-ai-chatbot'); ?>"></textarea>
                                        </div>
                                        <p class="description"><?php esc_html_e('Quick start buttons (comma-separated):', 'rapls-ai-chatbot'); ?></p>
                                        <input type="text" class="large-text" placeholder="<?php esc_attr_e('e.g., Product info, Pricing, Support', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Response Delay', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Add typing delay before showing AI response', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 4px;">
                                            <input type="number" value="500" min="100" max="3000" step="100" style="width: 80px;"> ms
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Notification Sound', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Play sound when bot responds', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Tooltips', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Show tooltips on icon buttons', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Custom Font', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('Default (System)', 'rapls-ai-chatbot'); ?></option>
                                            <optgroup label="<?php esc_attr_e('Sans Serif', 'rapls-ai-chatbot'); ?>">
                                                <option>Inter</option>
                                                <option>Roboto</option>
                                                <option>Poppins</option>
                                                <option>Open Sans</option>
                                                <option>Lato</option>
                                                <option>Montserrat</option>
                                                <option>Nunito</option>
                                                <option>Raleway</option>
                                                <option>DM Sans</option>
                                                <option>Source Sans 3</option>
                                            </optgroup>
                                            <optgroup label="<?php esc_attr_e('Rounded', 'rapls-ai-chatbot'); ?>">
                                                <option>Nunito Sans</option>
                                                <option>Quicksand</option>
                                                <option>Varela Round</option>
                                            </optgroup>
                                            <optgroup label="<?php esc_attr_e('Serif', 'rapls-ai-chatbot'); ?>">
                                                <option>Merriweather</option>
                                                <option>Playfair Display</option>
                                                <option>Lora</option>
                                            </optgroup>
                                            <optgroup label="<?php esc_attr_e('Monospace', 'rapls-ai-chatbot'); ?>">
                                                <option>JetBrains Mono</option>
                                                <option>Fira Code</option>
                                            </optgroup>
                                            <optgroup label="<?php esc_attr_e('Japanese', 'rapls-ai-chatbot'); ?>">
                                                <option>Noto Sans JP</option>
                                                <option>M PLUS Rounded 1c</option>
                                                <option>Zen Maru Gothic</option>
                                                <option>Kosugi Maru</option>
                                                <option>Sawarabi Gothic</option>
                                            </optgroup>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Seasonal Theme', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('None', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Spring (Cherry Blossom)', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Summer (Ocean)', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Autumn (Leaves)', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Winter (Snow)', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Christmas', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Halloween', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Footer & CSS Tab -->
                        <div id="tab-white-label" class="tab-content">
                            <h2><?php esc_html_e('Footer & CSS', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Customize footer branding and chatbot appearance with CSS.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Footer Message', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="<?php esc_attr_e('e.g., Powered by Your Company', 'rapls-ai-chatbot'); ?>" style="margin-bottom: 4px;">
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <input type="url" class="regular-text" placeholder="<?php esc_attr_e('Link URL (optional)', 'rapls-ai-chatbot'); ?>">
                                            <select style="width: auto;">
                                                <option><?php esc_html_e('New tab', 'rapls-ai-chatbot'); ?></option>
                                                <option><?php esc_html_e('Same tab', 'rapls-ai-chatbot'); ?></option>
                                            </select>
                                        </div>
                                        <p class="description"><?php esc_html_e('Displayed at the bottom of the chatbot window. Leave empty to hide. URL adds a link to the text.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Custom CSS', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <textarea class="large-text code" rows="8" placeholder="<?php esc_attr_e('e.g., .wp-ai-chatbot .chatbot-header { background: #333; }', 'rapls-ai-chatbot'); ?>"></textarea>
                                        <p class="description"><?php esc_html_e('Add custom CSS to further customize the chatbot appearance.', 'rapls-ai-chatbot'); ?></p>
                                        <details style="margin-top:10px;">
                                            <summary style="cursor:pointer;color:#2271b1;font-size:13px;"><?php esc_html_e('CSS Selector Reference', 'rapls-ai-chatbot'); ?></summary>
                                            <table class="widefat striped" style="margin-top:8px;max-width:700px;">
                                                <thead><tr>
                                                    <th style="width:45%;"><?php esc_html_e('Selector', 'rapls-ai-chatbot'); ?></th>
                                                    <th><?php esc_html_e('Description', 'rapls-ai-chatbot'); ?></th>
                                                </tr></thead>
                                                <tbody>
                                                    <tr><td><code>.wp-ai-chatbot</code></td><td><?php esc_html_e('Root container', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-badge</code></td><td><?php esc_html_e('Floating open button', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-window</code></td><td><?php esc_html_e('Chat window', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-header</code></td><td><?php esc_html_e('Header bar (bot name, avatar)', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-header .bot-name</code></td><td><?php esc_html_e('Bot name text', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-header .bot-avatar</code></td><td><?php esc_html_e('Bot avatar', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-messages</code></td><td><?php esc_html_e('Messages area', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.message.bot</code></td><td><?php esc_html_e('Bot message bubble', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.message.user</code></td><td><?php esc_html_e('User message bubble', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.message .content</code></td><td><?php esc_html_e('Message text content', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-input</code></td><td><?php esc_html_e('Input area (form)', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-input textarea</code></td><td><?php esc_html_e('Text input field', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-input button[type="submit"]</code></td><td><?php esc_html_e('Send button', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-footer-branding</code></td><td><?php esc_html_e('Footer message area', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-welcome-screen</code></td><td><?php esc_html_e('Welcome screen overlay', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-close</code></td><td><?php esc_html_e('Close button', 'rapls-ai-chatbot'); ?></td></tr>
                                                    <tr><td><code>.chatbot-typing</code></td><td><?php esc_html_e('Typing indicator', 'rapls-ai-chatbot'); ?></td></tr>
                                                </tbody>
                                            </table>
                                        </details>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Chat Features Tab -->
                        <div id="tab-chat-features" class="tab-content">
                            <h2><?php esc_html_e('Chat Features', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Bookmarks', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Allow users to bookmark messages', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Users can bookmark important messages for later reference. Bookmarks are stored in the browser.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Search', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable in-chat message search', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Adds a search bar to find messages within the conversation.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Sharing', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Allow users to copy conversation to clipboard', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Adds a button in the chat header to copy the entire conversation as text.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Screen Sharing', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable screen sharing / screenshot capture', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Allow operators to request screenshots from users for better support.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- External Learning Tab -->
                        <div id="tab-external-learning" class="tab-content">
                            <h2><?php esc_html_e('External Learning', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Crawl external URLs for RAG context', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('In addition to your WordPress content, the chatbot will also learn from external web pages.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('URLs', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="4" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                                        <p class="description"><?php esc_html_e('Enter one URL per line. Content will be crawled and indexed during scheduled site learning.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Test Mode Tab -->
                        <div id="tab-test-mode" class="tab-content">
                            <h2><?php esc_html_e('Test Mode', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable test mode', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description" style="color:#d63638;"><?php esc_html_e('When enabled, the chatbot returns a fixed response without calling the AI API. Disable before going live.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Test Response', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3" placeholder="<?php esc_attr_e('This is a test response. The AI API is not being called.', 'rapls-ai-chatbot'); ?>"></textarea>
                                        <p class="description"><?php esc_html_e('The response returned to all users when test mode is active.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Handoff Tab -->
                        <div id="tab-handoff" class="tab-content">
                            <h2><?php esc_html_e('Live Agent Handoff', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('When a visitor requests human support, the AI conversation is escalated to a live operator. Configure keywords, notifications, and auto-close settings.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Handoff', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Enable live agent handoff', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Auto-detect Keywords', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Automatically detect handoff requests from keywords', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Keywords (EN)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" placeholder="talk to human, agent, support, operator">
                                        <p class="description"><?php esc_html_e('Comma-separated keywords that trigger handoff (English).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Keywords (JA)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" placeholder="<?php esc_attr_e('human talk, operator, connect to support, representative', 'rapls-ai-chatbot'); ?>">
                                        <p class="description"><?php esc_html_e('Comma-separated keywords that trigger handoff (Japanese).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Handoff Message', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3" placeholder="<?php esc_attr_e('I understand this may need human assistance. A support representative will contact you soon.', 'rapls-ai-chatbot'); ?>"></textarea>
                                        <p class="description"><?php esc_html_e('Message shown to the visitor when handoff is triggered.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Notification Method', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('Email', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Slack', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Both', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Notification Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" placeholder="admin@example.com">
                                        <p class="description"><?php esc_html_e('Leave blank to use the site admin email.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Slack Webhook URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="url" class="large-text" placeholder="https://hooks.slack.com/services/...">
                                        <p class="description"><?php esc_html_e('Slack Incoming Webhook URL for handoff notifications.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Auto-close (minutes)', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" class="small-text" value="30">
                                        <p class="description"><?php esc_html_e('Automatically close handoff sessions after this many minutes of inactivity. Set to a high number to disable.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- AI Forms Tab -->
                        <div id="tab-ai-forms" class="tab-content">
                            <h2><?php esc_html_e('AI Forms', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable AI Forms', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable AI-powered forms in chat', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                            </table>
                            <p class="description"><?php esc_html_e('Create custom forms that use AI to process user inputs. Build intake forms, surveys, and data collection workflows.', 'rapls-ai-chatbot'); ?></p>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Actions Tab -->
                        <div id="tab-actions" class="tab-content">
                            <h2><?php esc_html_e('Actions / Intent Recognition', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('AI detects user intents in conversation and triggers predefined actions.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Actions', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable action recognition', 'rapls-ai-chatbot'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <div style="border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px; margin-bottom: 12px; background: #f6f7f7;">
                                            <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; align-items: center;">
                                                <input type="text" value="<?php esc_attr_e('Contact Sales', 'rapls-ai-chatbot'); ?>" style="width: 180px;">
                                                <label style="display: inline-flex; align-items: center; gap: 4px;">
                                                    <input type="checkbox" checked>
                                                    <?php esc_html_e('Enabled', 'rapls-ai-chatbot'); ?>
                                                </label>
                                                <button type="button" class="button">&times;</button>
                                            </div>
                                            <div style="margin-bottom: 8px;">
                                                <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #646970;"><?php esc_html_e('AI Description', 'rapls-ai-chatbot'); ?></label>
                                                <input type="text" class="large-text" value="<?php esc_attr_e('User wants to contact the sales team or make a purchase inquiry', 'rapls-ai-chatbot'); ?>">
                                            </div>
                                            <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; align-items: center;">
                                                <label style="font-size: 12px; color: #646970;"><?php esc_html_e('Trigger:', 'rapls-ai-chatbot'); ?></label>
                                                <select>
                                                    <option><?php esc_html_e('Keywords', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('AI Detection', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                                <input type="text" placeholder="<?php esc_attr_e('Keywords (comma-separated)', 'rapls-ai-chatbot'); ?>" style="width: 250px;">
                                            </div>
                                            <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; align-items: center;">
                                                <label style="font-size: 12px; color: #646970;"><?php esc_html_e('Action Type:', 'rapls-ai-chatbot'); ?></label>
                                                <select>
                                                    <option><?php esc_html_e('Redirect URL', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Link Buttons', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Email Notification', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Webhook', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </div>
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <input type="text" placeholder="<?php esc_attr_e('URL (https://...)', 'rapls-ai-chatbot'); ?>" class="regular-text">
                                                <input type="text" placeholder="<?php esc_attr_e('Button label', 'rapls-ai-chatbot'); ?>" style="width: 150px;">
                                            </div>
                                        </div>
                                        <button type="button" class="button button-secondary">+ <?php esc_html_e('Add Action', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('Maximum 10 actions. Each action adds approximately 50 tokens to the system prompt.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Scenarios Tab -->
                        <div id="tab-scenarios" class="tab-content">
                            <h2><?php esc_html_e('Conversation Scenarios', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Create guided conversation flows that walk users through structured multi-step interactions such as bookings, surveys, or lead qualification.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Scenarios', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable conversation scenarios', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Guide users through structured conversation flows triggered by keywords, AI detection, or quick reply buttons.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Scenarios', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <div style="border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; margin-bottom: 16px; background: #f6f7f7;">
                                            <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; align-items: center;">
                                                <input type="text" value="<?php esc_attr_e('Booking Flow', 'rapls-ai-chatbot'); ?>" style="width: 200px; font-weight: 600;">
                                                <label style="display: inline-flex; align-items: center; gap: 4px;">
                                                    <input type="checkbox" checked>
                                                    <?php esc_html_e('Enabled', 'rapls-ai-chatbot'); ?>
                                                </label>
                                                <button type="button" class="button">&times;</button>
                                            </div>
                                            <div style="margin-bottom: 10px;">
                                                <label style="display: block; margin-bottom: 4px; font-size: 12px; color: #646970;"><?php esc_html_e('Description', 'rapls-ai-chatbot'); ?></label>
                                                <input type="text" class="large-text" value="<?php esc_attr_e('Guide users through a booking process', 'rapls-ai-chatbot'); ?>">
                                            </div>
                                            <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;">
                                                <label style="font-size: 12px; color: #646970;"><?php esc_html_e('Trigger:', 'rapls-ai-chatbot'); ?></label>
                                                <select>
                                                    <option><?php esc_html_e('Keyword', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('AI Detection', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Quick Reply', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </div>
                                            <label style="display: block; margin-bottom: 6px; font-size: 12px; color: #646970; font-weight: 600;"><?php esc_html_e('Steps', 'rapls-ai-chatbot'); ?></label>
                                            <div style="border: 1px solid #dcdcde; border-radius: 3px; padding: 10px; margin-bottom: 8px; background: #fff;">
                                                <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                                                    <span style="font-weight: 600; color: #646970; min-width: 20px;">1.</span>
                                                    <select>
                                                        <option><?php esc_html_e('Message', 'rapls-ai-chatbot'); ?></option>
                                                        <option><?php esc_html_e('Input', 'rapls-ai-chatbot'); ?></option>
                                                        <option><?php esc_html_e('Condition', 'rapls-ai-chatbot'); ?></option>
                                                        <option><?php esc_html_e('Action', 'rapls-ai-chatbot'); ?></option>
                                                    </select>
                                                </div>
                                                <textarea rows="2" class="large-text" placeholder="<?php esc_attr_e('Message text (supports {field_name} placeholders)', 'rapls-ai-chatbot'); ?>"></textarea>
                                            </div>
                                            <button type="button" class="button button-small">+ <?php esc_html_e('Add Step', 'rapls-ai-chatbot'); ?></button>
                                        </div>
                                        <button type="button" class="button button-secondary">+ <?php esc_html_e('Add Scenario', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('Maximum 10 scenarios with up to 20 steps each.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Chatbots Tab -->
                        <div id="tab-bots" class="tab-content">
                            <h2><?php esc_html_e('Multi-Bot Management', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Create purpose-specific chatbots (e.g., Sales, Support, FAQ) with individual AI settings, prompts, and page assignments.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Multi-Bot', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable multiple chatbot configurations', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('When, only the default global chatbot is used.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <h3><?php esc_html_e('Chatbot List', 'rapls-ai-chatbot'); ?></h3>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:20%;"><?php esc_html_e('Name', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width:12%;"><?php esc_html_e('Slug', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width:12%;"><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width:15%;"><?php esc_html_e('Pages', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width:10%;"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width:20%;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><?php esc_html_e('Default Bot', 'rapls-ai-chatbot'); ?></strong></td>
                                        <td><code>default</code></td>
                                        <td>OpenAI</td>
                                        <td><?php esc_html_e('All Pages', 'rapls-ai-chatbot'); ?></td>
                                        <td><span style="color: #00a32a;">&#9679; <?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></span></td>
                                        <td>
                                            <button type="button" class="button button-small"><?php esc_html_e('Edit', 'rapls-ai-chatbot'); ?></button>
                                            <button type="button" class="button button-small"><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="margin-top: 12px;">
                                <button type="button" class="button button-primary">+ <?php esc_html_e('Add New Bot', 'rapls-ai-chatbot'); ?></button>
                            </p>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- LINE Tab -->
                        <div id="tab-line" class="tab-content">
                            <h2><?php esc_html_e('LINE Messaging API', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable LINE', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox">
                                            <?php esc_html_e('Receive and reply to LINE messages via AI', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Channel Secret', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" placeholder="<?php esc_attr_e('From LINE Developers Console', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Channel Access Token', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" placeholder="<?php esc_attr_e('Long-lived channel access token', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <code>https://example.com/wp-json/rapls-ai-chatbot/v1/line-webhook</code>
                                        <p class="description"><?php esc_html_e('Set this URL in your LINE Developers Console > Messaging API > Webhook URL.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Slack & Sheets Tab -->
                        <div id="tab-integrations" class="tab-content">
                            <h2><?php esc_html_e('Slack & Sheets', 'rapls-ai-chatbot'); ?></h2>

                            <h3><?php esc_html_e('Slack', 'rapls-ai-chatbot'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Slack', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Send notifications to Slack', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Slack Webhook URL', 'rapls-ai-chatbot'); ?></th>
                                    <td><input type="url" class="large-text" placeholder="https://hooks.slack.com/services/..."></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Slack Events', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('New conversation', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('New message', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Lead captured', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Handoff requested', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Handoff resolved', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Offline message', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('AI API error', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Budget alert', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Rate limit exceeded', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('Banned word detected', 'rapls-ai-chatbot'); ?></label><br>
                                        <label><input type="checkbox"> <?php esc_html_e('reCAPTCHA failure (frequent)', 'rapls-ai-chatbot'); ?></label>
                                    </td>
                                </tr>
                            </table>

                            <h3><?php esc_html_e('Google Sheets', 'rapls-ai-chatbot'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Google Sheets', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Export lead data to Google Sheets via Apps Script webhook', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Apps Script Web App URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="url" class="large-text" placeholder="https://script.google.com/macros/s/.../exec">
                                        <p class="description"><?php esc_html_e('Deploy a Google Apps Script as web app to receive lead data via POST.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Booking Tab -->
                        <div id="tab-booking" class="tab-content">
                            <h2><?php esc_html_e('Booking Integration', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Connect with booking/calendar systems to enable appointment scheduling via chatbot.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable booking integration', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('Select provider...', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Booking Page URL', 'rapls-ai-chatbot'); ?></option>
                                            <option>Calendly</option>
                                            <option>Cal.com</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Booking URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="url" class="regular-text" placeholder="https://calendly.com/...">
                                        <p class="description"><?php esc_html_e('URL to your booking page. Shown when booking intent is detected.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Trigger Keywords', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" placeholder="book,appointment,schedule,reserve,予約">
                                        <p class="description"><?php esc_html_e('Comma-separated keywords that trigger booking suggestions.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Role Access Tab -->
                        <div id="tab-roles" class="tab-content">
                            <h2><?php esc_html_e('Role-Based Access Control', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Control chat access and message limits per WordPress user role.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Role Access', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable role-based access control', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Default Policy', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('Allow', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Deny', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Applied to guest (not logged-in) users and roles not listed below.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <h3><?php esc_html_e('Role Settings', 'rapls-ai-chatbot'); ?></h3>
                            <table class="widefat striped" style="max-width: 700px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Role', 'rapls-ai-chatbot'); ?></th>
                                        <th style="text-align: center;"><?php esc_html_e('Chat Allowed', 'rapls-ai-chatbot'); ?></th>
                                        <th style="text-align: center;"><?php esc_html_e('Monthly Limit', 'rapls-ai-chatbot'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><?php esc_html_e('Administrator', 'rapls-ai-chatbot'); ?></strong> <code style="font-size: 11px; color: #888; margin-left: 4px;">administrator</code></td>
                                        <td style="text-align: center;"><input type="checkbox" checked></td>
                                        <td style="text-align: center;"><input type="number" value="0" style="width: 80px; text-align: center;" placeholder="0"></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e('Editor', 'rapls-ai-chatbot'); ?></strong> <code style="font-size: 11px; color: #888; margin-left: 4px;">editor</code></td>
                                        <td style="text-align: center;"><input type="checkbox" checked></td>
                                        <td style="text-align: center;"><input type="number" value="500" style="width: 80px; text-align: center;" placeholder="0"></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e('Subscriber', 'rapls-ai-chatbot'); ?></strong> <code style="font-size: 11px; color: #888; margin-left: 4px;">subscriber</code></td>
                                        <td style="text-align: center;"><input type="checkbox" checked></td>
                                        <td style="text-align: center;"><input type="number" value="100" style="width: 80px; text-align: center;" placeholder="0"></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e('Guest', 'rapls-ai-chatbot'); ?></strong> <code style="font-size: 11px; color: #888; margin-left: 4px;">guest</code></td>
                                        <td style="text-align: center;" colspan="2"><em><?php esc_html_e('Default policy applies', 'rapls-ai-chatbot'); ?></em></td>
                                    </tr>
                                </tbody>
                            </table>

                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Change History Tab -->
                        <div id="tab-change-history" class="tab-content">
                            <h2><?php esc_html_e('Change History & Staging', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Change History', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Record settings change history', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Max History Entries', 'rapls-ai-chatbot'); ?></th>
                                    <td><input type="number" value="50" class="small-text" min="10" max="200"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Rollback', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Allow rollback to previous settings versions', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Staging Mode', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable staging mode (save changes for review before publishing)', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Approval Workflow', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Require admin approval for settings changes (non-admin users)', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Approval Email', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="email" class="regular-text" placeholder="admin@example.com">
                                        <p class="description"><?php esc_html_e('Email to notify when changes are pending approval.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Multisite', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable multisite support', 'rapls-ai-chatbot'); ?></label>
                                        <br>
                                        <label><input type="checkbox"> <?php esc_html_e('Use network-wide settings (overrides per-site settings)', 'rapls-ai-chatbot'); ?></label>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Queue Tab -->
                        <div id="tab-queue" class="tab-content">
                            <h2><?php esc_html_e('Queue Management', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Control concurrent AI requests to prevent overload during high traffic.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable request queue', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Max Concurrent', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" value="5" class="small-text" min="1" max="50">
                                        <p class="description"><?php esc_html_e('Maximum number of simultaneous AI requests.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Priority for Logged-in Users', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Give logged-in users priority in queue', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Queue Status', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p><?php
                                            /* translators: 1: processing count, 2: max count, 3: pending count */
                                            printf(esc_html__('Processing: %1$d / %2$d | Pending: %3$d', 'rapls-ai-chatbot'), 2, 5, 0);
                                        ?></p>
                                        <button type="button" class="button button-secondary"><?php esc_html_e('Reset Queue', 'rapls-ai-chatbot'); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Performance Tab -->
                        <div id="tab-performance" class="tab-content">
                            <h2><?php esc_html_e('Performance', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Similar Cache', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable similar question caching', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Cache responses for similar questions (normalized matching). Requires response caching to be enabled.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Batch Processing', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable batch embedding processing', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Process knowledge base embeddings in batch for improved performance.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Performance Monitoring', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Track API response times and performance metrics', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top:10px;padding:12px;background:#f6f7f7;border-radius:6px;">
                                            <strong><?php esc_html_e('Current Stats', 'rapls-ai-chatbot'); ?></strong>
                                            <table style="margin-top:6px;font-size:13px;">
                                                <tr><td style="padding:2px 12px 2px 0;color:#646970;"><?php esc_html_e('Total API Requests', 'rapls-ai-chatbot'); ?></td><td>0</td></tr>
                                                <tr><td style="padding:2px 12px 2px 0;color:#646970;"><?php esc_html_e('Avg Response Time', 'rapls-ai-chatbot'); ?></td><td>0 ms</td></tr>
                                                <tr><td style="padding:2px 12px 2px 0;color:#646970;"><?php esc_html_e('Max Response Time', 'rapls-ai-chatbot'); ?></td><td>0 ms</td></tr>
                                                <tr><td style="padding:2px 12px 2px 0;color:#646970;"><?php esc_html_e('Min Response Time', 'rapls-ai-chatbot'); ?></td><td>0 ms</td></tr>
                                                <tr><td style="padding:2px 12px 2px 0;color:#646970;"><?php esc_html_e('Last Updated', 'rapls-ai-chatbot'); ?></td><td>-</td></tr>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Security Tab -->
                        <div id="tab-security" class="tab-content">
                            <h2><?php esc_html_e('Security', 'rapls-ai-chatbot'); ?></h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Country Blocking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Block chat access by country', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 4px;">
                                            <textarea class="regular-text" rows="2" placeholder="<?php esc_attr_e('Country codes, one per line (e.g., CN, RU)', 'rapls-ai-chatbot'); ?>"></textarea>
                                        </div>
                                        <input type="text" class="large-text" placeholder="<?php esc_attr_e('Block message', 'rapls-ai-chatbot'); ?>" style="margin-top: 4px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('IP Whitelist', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Only allow whitelisted IPs', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 4px;">
                                            <textarea class="regular-text" rows="3" placeholder="<?php esc_attr_e('Allowed IPs, one per line (supports CIDR)', 'rapls-ai-chatbot'); ?>"></textarea>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('PII Masking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Mask personal information in stored messages', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Masks email addresses, phone numbers, and credit card numbers in conversation logs.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Data Retention', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Automatically delete old conversations', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 4px;">
                                            <input type="number" value="365" min="30" max="3650" style="width: 80px;"> <?php esc_html_e('days', 'rapls-ai-chatbot'); ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Security Headers', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Add security headers to REST API responses', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Adds X-Content-Type-Options, X-Frame-Options, and Referrer-Policy headers.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Spam Detection', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable spam message detection', 'rapls-ai-chatbot'); ?></label>
                                        <p class="description"><?php esc_html_e('Scores messages based on repetition, link density, and suspicious patterns.', 'rapls-ai-chatbot'); ?></p>
                                        <div style="margin-top: 4px;">
                                            <label><?php esc_html_e('Threshold:', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="3" min="1" max="10" style="width: 60px;">
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Allowed Extra Origins', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <textarea class="large-text" rows="3" placeholder="https://example.com"></textarea>
                                        <p class="description"><?php esc_html_e('One origin per line. These origins will be allowed for cross-origin API requests.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Encryption Tab -->
                        <div id="tab-encryption" class="tab-content">
                            <h2><?php esc_html_e('Encryption', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Encrypt sensitive data stored in the database for enhanced security.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable data encryption at rest', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Fields to Encrypt', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="text" value="messages,leads" class="regular-text">
                                        <p class="description"><?php esc_html_e('Comma-separated: messages, leads, context', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Encryption Method', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p><strong>AES-256-GCM</strong> — <?php esc_html_e('Authenticated encryption with tamper detection', 'rapls-ai-chatbot'); ?></p>
                                        <p class="description">
                                            <?php esc_html_e('Encryption key is derived from WordPress auth salt. Ensure wp-config.php salts are backed up.', 'rapls-ai-chatbot'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Migration', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Encrypt or decrypt all existing messages in the database. This processes messages in batches.', 'rapls-ai-chatbot'); ?></p>
                                        <button type="button" class="button"><?php esc_html_e('Encrypt All Messages', 'rapls-ai-chatbot'); ?></button>
                                        <button type="button" class="button" style="margin-left:8px;"><?php esc_html_e('Decrypt All Messages', 'rapls-ai-chatbot'); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>

                        <!-- Security Scan Tab -->
                        <div id="tab-vulnerability" class="tab-content">
                            <h2><?php esc_html_e('Security Scan', 'rapls-ai-chatbot'); ?></h2>
                            <p class="description"><?php esc_html_e('Scan plugin settings for potential security vulnerabilities.', 'rapls-ai-chatbot'); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Scheduled Scan', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable scheduled vulnerability scanning', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Schedule', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <select>
                                            <option><?php esc_html_e('Daily', 'rapls-ai-chatbot'); ?></option>
                                            <option selected><?php esc_html_e('Weekly', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Monthly', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Run Scan Now', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <button type="button" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="dashicons dashicons-shield"></span>
                                            <?php esc_html_e('Run Security Scan', 'rapls-ai-chatbot'); ?>
                                        </button>
                                    </td>
                                </tr>
                            </table>

                            <!-- Knowledge Advanced Features -->
                            <hr>
                            <h3><?php esc_html_e('Knowledge: Advanced Features', 'rapls-ai-chatbot'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Knowledge Versioning', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Track version history for knowledge entries', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Knowledge Expiration', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox"> <?php esc_html_e('Enable automatic expiration of knowledge entries', 'rapls-ai-chatbot'); ?></label>
                                        <div style="margin-top: 4px;">
                                            <label><?php esc_html_e('Expire after:', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="90" min="7" max="3650" style="width: 80px;"> <?php esc_html_e('days', 'rapls-ai-chatbot'); ?>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Auto Priority', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Automatically adjust knowledge priority based on usage frequency', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Related Links', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Show related knowledge links in AI responses', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Intent Classification', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Classify user intent to improve knowledge retrieval accuracy', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Custom Intents', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea rows="5" class="large-text code" placeholder='{"purchase": ["buy", "price", "購入"], "support": ["help", "問題"]}'></textarea>
                                        <p class="description">
                                            <?php esc_html_e('JSON format: {"intent_name": ["keyword1", "keyword2"]}. Custom intents take priority over built-in patterns.', 'rapls-ai-chatbot'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Similar Question Merge -->
                            <hr>
                            <h3><?php esc_html_e('Knowledge: Similar Question Merge', 'rapls-ai-chatbot'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable similar question detection and merge', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Similarity Threshold', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="number" value="80" min="50" max="100" class="small-text">
                                        <span>%</span>
                                        <p class="description"><?php esc_html_e('Minimum similarity percentage to suggest merge (50-100%).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Multi-bot Coordination -->
                            <hr>
                            <h3><?php esc_html_e('Multi-bot Coordination', 'rapls-ai-chatbot'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox"> <?php esc_html_e('Enable coordination between multiple chatbots', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Routing Mode', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <select>
                                            <option selected><?php esc_html_e('Manual (page rules)', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('AI Intent-based routing', 'rapls-ai-chatbot'); ?></option>
                                            <option><?php esc_html_e('Round-robin', 'rapls-ai-chatbot'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('How to route conversations to different bots.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit"><button type="button" class="button button-primary"><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pro Features List -->
            <div class="raplsaich-pro-features-list">
                <h3><?php esc_html_e('Pro Features Include:', 'rapls-ai-chatbot'); ?></h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 24px;">
                <ul style="margin: 0;">
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Lead Capture Forms & Custom Fields', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Offline Messages & Contact Form', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Conversion Tracking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Fullscreen Mode, Welcome Screen & Custom Fonts', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('AI Enhancement (Suggestions, Autocomplete, Sentiment)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Context Memory (Cross-session)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Multimodal Support (Image Upload & Analysis)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Voice Input (STT) & Text-to-Speech (TTS)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Customizable AI Prompts', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Response Edit Suggestions & AI Quality Score', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Business Hours & Holidays', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Human Handoff & Operator Mode', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Webhook Integration & LINE Messaging', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('AI Forms Builder & Conversation Scenarios', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('WooCommerce Integration & Product Cards', 'rapls-ai-chatbot'); ?></li>
                </ul>
                <ul style="margin: 0;">
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Moderation (Banned Words, IP/Country Blocking, Spam)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Response Cache (API Cost Reduction)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Multiple Chatbots (per-page) & Multi-bot Coordination', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Booking Integration (Calendly, Cal.com)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Screen Sharing & File Upload', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Notification Sounds & Seasonal Themes', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('API Cost Alerts & Budget Caps', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Monthly Email Reports & PDF Export', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Settings Backup & Restore (JSON)', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Change History, Rollback & Staging Mode', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Data Encryption (AES-256-GCM) & PII Masking', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Knowledge Versioning & Intent Classification', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Analytics Dashboard & Lead Management', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Audit Log & Compliance', 'rapls-ai-chatbot'); ?></li>
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Pro Themes (10) & Dark Mode', 'rapls-ai-chatbot'); ?></li>
                </ul>
                </div>
            </div>
        </div>

        <!-- Pro preview styles loaded via wp_enqueue_style("raplsaich-pro-preview") -->

        <!-- Tab groups JS loaded via wp_enqueue_script('raplsaich-tab-groups') -->
        <?php
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
