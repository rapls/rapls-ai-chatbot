<?php
/**
 * Admin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Admin {

    /**
     * Log a diagnostic event code for display in Security Diagnostics.
     * Stores last 10 events in a transient (codes only, no sensitive data).
     *
     * @param string $code Short event code (e.g. 'api_test_failed', 'import_parse_error').
     */
    public static function log_diagnostic_event(string $code): void {
        $key = 'wpaic_diag_events';
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
        return (string) apply_filters('wpaic_manage_cap', 'manage_options');
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
            'wpaic-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-format-chat',
            30
        );

        // Dashboard
        add_submenu_page(
            'wpaic-dashboard',
            __('Dashboard', 'rapls-ai-chatbot'),
            __('Dashboard', 'rapls-ai-chatbot'),
            $cap,
            'wpaic-dashboard',
            [$this, 'render_dashboard_page']
        );

        // Settings
        add_submenu_page(
            'wpaic-dashboard',
            __('Settings', 'rapls-ai-chatbot'),
            __('Settings', 'rapls-ai-chatbot'),
            $cap,
            'wpaic-settings',
            [$this, 'render_settings_page']
        );

        // Knowledge
        add_submenu_page(
            'wpaic-dashboard',
            __('Knowledge', 'rapls-ai-chatbot'),
            __('Knowledge', 'rapls-ai-chatbot'),
            $cap,
            'wpaic-knowledge',
            [$this, 'render_knowledge_page']
        );

        $is_pro = WPAIC_Pro_Features::get_instance()->is_pro();

        // Pro menus - show as locked when Pro is not active
        // When Pro is active, the Pro plugin adds its own menus
        if (!$is_pro) {
            add_submenu_page(
                'wpaic-dashboard',
                __('Pro Settings', 'rapls-ai-chatbot'),
                __('Pro Settings', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-pro-settings',
                [$this, 'render_pro_upsell_page']
            );
        }

        // Site Learning — Pro only
        if ($is_pro) {
            add_submenu_page(
                'wpaic-dashboard',
                __('Site Learning', 'rapls-ai-chatbot'),
                __('Site Learning', 'rapls-ai-chatbot'),
                $cap,
                'wpaic-crawler',
                [$this, 'render_crawler_page']
            );
        } else {
            add_submenu_page(
                'wpaic-dashboard',
                __('Site Learning', 'rapls-ai-chatbot'),
                __('Site Learning', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-crawler',
                [$this, 'render_pro_upsell_page']
            );
        }

        // Conversation History — Pro only
        if ($is_pro) {
            add_submenu_page(
                'wpaic-dashboard',
                __('Conversations', 'rapls-ai-chatbot'),
                __('Conversations', 'rapls-ai-chatbot'),
                $cap,
                'wpaic-conversations',
                [$this, 'render_conversations_page']
            );
        } else {
            add_submenu_page(
                'wpaic-dashboard',
                __('Conversations', 'rapls-ai-chatbot'),
                __('Conversations', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-conversations',
                [$this, 'render_pro_upsell_page']
            );
        }

        if (!$is_pro) {
            add_submenu_page(
                'wpaic-dashboard',
                __('Analytics', 'rapls-ai-chatbot'),
                __('Analytics', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-analytics',
                [$this, 'render_pro_upsell_page']
            );

            add_submenu_page(
                'wpaic-dashboard',
                __('Leads', 'rapls-ai-chatbot'),
                __('Leads', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-leads',
                [$this, 'render_pro_upsell_page']
            );

            add_submenu_page(
                'wpaic-dashboard',
                __('Audit Log', 'rapls-ai-chatbot'),
                __('Audit Log', 'rapls-ai-chatbot') . ' <span class="wpaic-pro-menu-badge">PRO</span>',
                $cap,
                'wpaic-audit-log',
                [$this, 'render_pro_upsell_page']
            );
        }
    }

    /**
     * Show admin notice when message limit is reached
     */
    public function message_limit_notice(): void {
        $pro = WPAIC_Pro_Features::get_instance();
        if ($pro->get_message_limit() === PHP_INT_MAX) {
            return;
        }
        if ($pro->get_remaining_messages() > 0) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('AI Chatbot:', 'rapls-ai-chatbot'); ?></strong>
                <?php esc_html_e('Monthly AI response limit reached. The chatbot can no longer generate AI responses this month.', 'rapls-ai-chatbot'); ?>
                <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank">
                    <?php esc_html_e('Upgrade to Pro for unlimited responses', 'rapls-ai-chatbot'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('wpaic_settings_group', 'wpaic_settings', [
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
            $settings['max_tokens'] = max(1, min(16384, absint($settings['max_tokens'])));
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
            $settings['rate_limit_window'] = max(60, absint($settings['rate_limit_window']));
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
        $text_fields = ['system_prompt', 'quota_error_message', 'welcome_message', 'placeholder_text', 'chatbot_title'];
        foreach ($text_fields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = sanitize_textarea_field($settings[$field]);
            }
        }

        // Boolean fields
        $bool_fields = ['show_on_mobile', 'dark_mode', 'markdown_enabled', 'save_history', 'show_feedback_buttons', 'crawler_enabled', 'consent_strict_mode', 'embedding_enabled', 'web_search_enabled'];
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

        return $settings;
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings(array $input): array {
        // Load existing settings to preserve values not in the form
        $existing = get_option('wpaic_settings', []);
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
                    'wpaic_settings',
                    'api_key_prefix_' . $kf,
                    /* translators: 1: provider name, 2: expected prefix */
                    sprintf(__('%1$s API key does not start with the expected prefix (%2$s). The key has been saved, but please verify it is correct.', 'rapls-ai-chatbot'), $meta['label'], implode(' / ', $meta['prefixes'])),
                    'warning'
                );
            }
        }

        $sanitized['openai_model'] = sanitize_text_field($input['openai_model'] ?? ($existing['openai_model'] ?? 'gpt-4o-mini'));
        $sanitized['claude_model'] = sanitize_text_field($input['claude_model'] ?? ($existing['claude_model'] ?? 'claude-haiku-4-5-20251001'));
        $sanitized['gemini_model'] = sanitize_text_field($input['gemini_model'] ?? ($existing['gemini_model'] ?? 'gemini-2.0-flash-exp'));
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
        $valid_langs = ['', 'auto', 'en', 'ja', 'zh', 'ko', 'es', 'fr', 'de', 'pt'];
        $raw_lang = $input['response_language'] ?? ($existing['response_language'] ?? '');
        $sanitized['response_language'] = in_array($raw_lang, $valid_langs, true) ? $raw_lang : '';
        $sanitized['quota_error_message'] = sanitize_text_field($input['quota_error_message'] ?? ($existing['quota_error_message'] ?? ''));
        $sanitized['max_tokens'] = max(1, min(16384, absint($input['max_tokens'] ?? ($existing['max_tokens'] ?? 1000))));
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
        $sanitized['recaptcha_threshold'] = floatval($input['recaptcha_threshold'] ?? ($existing['recaptcha_threshold'] ?? 0.5));
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
        $sanitized['retention_days'] = absint($input['retention_days'] ?? ($existing['retention_days'] ?? 90));

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
        $sanitized['rate_limit_window'] = max(60, absint($input['rate_limit_window'] ?? ($existing['rate_limit_window'] ?? 3600)));

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
            : ($existing['crawler_post_types'] ?? ['post', 'page']);
        $crawler_interval_raw = sanitize_text_field($input['crawler_interval'] ?? ($existing['crawler_interval'] ?? 'daily'));
        $sanitized['crawler_interval'] = in_array($crawler_interval_raw, ['hourly', 'twicedaily', 'daily', 'weekly', 'monthly'], true)
            ? $crawler_interval_raw
            : 'daily';
        $sanitized['crawler_chunk_size'] = absint($input['crawler_chunk_size'] ?? ($existing['crawler_chunk_size'] ?? 1000));
        $sanitized['crawler_max_results'] = absint($input['crawler_max_results'] ?? ($existing['crawler_max_results'] ?? 3));
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
            $sanitized['show_feedback_buttons'] = $existing['show_feedback_buttons'] ?? false;
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
            ? $input['mcp_api_key_hash']
            : ($existing['mcp_api_key_hash'] ?? '');

        // Pro features settings
        $sanitized['pro_features'] = $this->sanitize_pro_features_settings(
            $input['pro_features'] ?? [],
            $existing['pro_features'] ?? []
        );

        // Enhanced content extraction checkbox (on Crawler page, outside pro_features form)
        $crawler_form_submitted = array_key_exists('crawler_interval', $input);
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

        $defaults = WPAIC_Pro_Features::get_default_settings();
        $sanitized = [];

        // Message limit
        $sanitized['free_message_limit'] = absint($input['free_message_limit'] ?? ($existing['free_message_limit'] ?? $defaults['free_message_limit']));

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
            ];
        }

        $sanitized['lead_form_title'] = sanitize_text_field($input['lead_form_title'] ?? ($existing['lead_form_title'] ?? $defaults['lead_form_title']));
        $sanitized['lead_form_description'] = sanitize_textarea_field($input['lead_form_description'] ?? ($existing['lead_form_description'] ?? $defaults['lead_form_description']));
        $sanitized['lead_notification_enabled'] = !empty($input['lead_notification_enabled']);
        $sanitized['lead_notification_email'] = sanitize_email($input['lead_notification_email'] ?? ($existing['lead_notification_email'] ?? ''));

        // White label
        $sanitized['white_label_enabled'] = !empty($input['white_label_enabled']);
        $sanitized['hide_powered_by'] = !empty($input['hide_powered_by']);
        $sanitized['white_label_footer'] = sanitize_text_field($input['white_label_footer'] ?? ($existing['white_label_footer'] ?? ''));
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
                            'wpaic_settings',
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
        $event_names = ['new_conversation', 'new_message', 'lead_captured'];
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
        $sanitized['business_hours_timezone'] = sanitize_text_field($input['business_hours_timezone'] ?? ($existing['business_hours_timezone'] ?? 'Asia/Tokyo'));
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

        // Preserve Pro-only keys not handled by Free sanitizer (e.g. badge_icon_*, scheduling, etc.)
        // 1. $existing: base from DB (preserves keys not in current form submission)
        // 2. $input: pass-through for Pro-managed keys (badge_icon_*, etc.) added via update_option()
        // 3. $sanitized: explicitly sanitized keys take final priority
        return array_merge($existing, $input, $sanitized);
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

        // Only encrypt recognized API key formats
        $is_api_key = strpos($key, 'sk-') === 0
                   || strpos($key, 'sk-ant-') === 0
                   || strpos($key, 'AIza') === 0;

        if (!$is_api_key) {
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
        return 'wpaic_' . parse_url(get_site_url(), PHP_URL_HOST);
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles(string $hook): void {
        // Menu badge styles on all admin pages
        wp_enqueue_style(
            'wpaic-admin-menu',
            WPAIC_PLUGIN_URL . 'assets/css/admin-menu.css',
            [],
            WPAIC_VERSION
        );

        if (strpos($hook, 'wpaic') === false) {
            return;
        }

        wp_enqueue_style(
            'wpaic-admin',
            WPAIC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPAIC_VERSION
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts(string $hook): void {
        if (strpos($hook, 'wpaic') === false) {
            return;
        }

        // Enqueue media uploader and color picker for settings page
        if (strpos($hook, 'wpaic-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
        }

        // Enqueue Chart.js for dashboard
        if (strpos($hook, 'toplevel_page_wpaic') !== false || strpos($hook, 'page_wpaic-dashboard') !== false || $hook === 'toplevel_page_wpaic-dashboard') {
            wp_enqueue_script(
                'wpaic-chartjs',
                WPAIC_PLUGIN_URL . 'assets/vendor/chart.js/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
        }

        wp_enqueue_script(
            'wpaic-admin',
            WPAIC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            WPAIC_VERSION,
            true
        );

        wp_localize_script('wpaic-admin', 'wpaicAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wpaic_admin_nonce'),
            'isPro'   => WPAIC_Pro_Features::get_instance()->is_pro(),
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
            ],
        ]);
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        $stats = $this->get_dashboard_stats();
        $usage_stats = WPAIC_Cost_Calculator::get_usage_stats(30);
        $chart_data = WPAIC_Cost_Calculator::get_chart_data(30);

        $pro = WPAIC_Pro_Features::get_instance();
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

        include WPAIC_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        $settings = get_option('wpaic_settings', []);

        // Auto-migrate legacy enc: (CBC) keys to encg: (GCM) on settings page load
        $this->maybe_migrate_legacy_keys($settings);

        $openai_provider = new WPAIC_OpenAI_Provider();
        $claude_provider = new WPAIC_Claude_Provider();
        $gemini_provider = new WPAIC_Gemini_Provider();
        $openrouter_provider = new WPAIC_OpenRouter_Provider();
        include WPAIC_PLUGIN_DIR . 'templates/admin/settings.php';
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
            if (empty($value) || strpos($value, 'enc:') !== 0) {
                continue; // Not legacy CBC format
            }

            $decrypted = ($field === 'recaptcha_secret_key')
                ? self::decrypt_secret_static($value)
                : $this->decrypt_api_key($value);

            if (empty($decrypted)) {
                continue; // Decryption failed, leave as-is
            }

            // Re-encrypt with GCM
            $re_encrypted = ($field === 'recaptcha_secret_key')
                ? $this->encrypt_secret($decrypted)
                : $this->maybe_encrypt_api_key($decrypted);

            if ($re_encrypted !== $value && strpos($re_encrypted, 'encg:') === 0) {
                $settings[$field] = $re_encrypted;
                $migrated = true;
            }
        }

        if ($migrated) {
            update_option('wpaic_settings', $settings);
        }
    }

    /**
     * Render crawler page
     */
    public function render_crawler_page(): void {
        $crawler = new WPAIC_Site_Crawler();
        $status = $crawler->get_status();
        $is_pro_active = get_option('wpaic_pro_active');

        // Sort parameters
        $allowed_orderby = ['title', 'post_type', 'indexed_at'];
        $orderby = isset($_GET['orderby']) && in_array(sanitize_text_field(wp_unslash($_GET['orderby'])), $allowed_orderby, true) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'indexed_at';
        $order = isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';

        $indexed_list = WPAIC_Content_Index::get_list([
            'per_page' => 50,
            'orderby'  => $orderby,
            'order'    => $order,
        ]);

        // Post type statistics
        $post_type_counts = WPAIC_Content_Index::get_post_type_counts();

        include WPAIC_PLUGIN_DIR . 'templates/admin/crawler.php';
    }

    /**
     * Render conversations page
     */
    public function render_conversations_page(): void {
        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;

        // Sort parameters
        $allowed_orderby = ['id', 'status', 'created_at', 'updated_at'];
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

        $conversations = WPAIC_Conversation::get_list($list_args);
        $has_filters = $search !== '' || $status_filter !== '' || $date_from !== '' || $date_to !== '';
        $total = $has_filters ? WPAIC_Conversation::get_filtered_count($list_args) : WPAIC_Conversation::get_count();

        // Statistics
        $conv_stats = [
            'total'  => WPAIC_Conversation::get_count(),
            'active' => WPAIC_Conversation::get_count('active'),
            'closed' => WPAIC_Conversation::get_count('closed'),
            'today'  => WPAIC_Conversation::get_today_count(),
        ];

        include WPAIC_PLUGIN_DIR . 'templates/admin/conversations.php';
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

        $knowledge_list = WPAIC_Knowledge::get_list($list_args);
        $total = WPAIC_Knowledge::get_count($category, null, $status_filter);
        $categories = WPAIC_Knowledge::get_categories();
        $draft_count = WPAIC_Knowledge::get_draft_count();

        // Statistics
        $knowledge_stats = [
            'total'      => WPAIC_Knowledge::get_count(),
            'active'     => WPAIC_Knowledge::get_count('', 1),
            'inactive'   => WPAIC_Knowledge::get_count('', 0),
            'categories' => count($categories),
        ];

        include WPAIC_PLUGIN_DIR . 'templates/admin/knowledge.php';
    }

    /**
     * Get dashboard stats
     */
    private function get_dashboard_stats(): array {
        return [
            'total_conversations' => WPAIC_Conversation::get_count(),
            'today_messages'      => WPAIC_Message::get_today_count(),
            'indexed_pages'       => WPAIC_Content_Index::get_count(),
            'knowledge_count'     => WPAIC_Knowledge::get_count('', 1),
            'total_tokens'        => WPAIC_Message::get_total_tokens(30),
        ];
    }

    /**
     * Manual crawl AJAX
     */
    public function ajax_manual_crawl(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $crawler = new WPAIC_Site_Crawler();
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = (int) wp_unslash($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = WPAIC_Content_Index::delete_by_post_id($post_id);

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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('delete_all_index')) {
            return;
        }

        $result = WPAIC_Content_Index::truncate();

        // Update last crawl status
        update_option('wpaic_last_crawl', null);
        update_option('wpaic_last_crawl_results', null);

        wp_send_json_success([
            'message' => __('All index data deleted.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * Exclude a post from crawler AJAX
     */
    public function ajax_crawler_exclude_post(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = absint(wp_unslash($_POST['post_id'] ?? 0));
        if (!$post_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $settings = get_option('wpaic_settings', []);
        $exclude_ids = $settings['crawler_exclude_ids'] ?? [];

        if (!in_array($post_id, $exclude_ids, true)) {
            $exclude_ids[] = $post_id;
            $settings['crawler_exclude_ids'] = array_values($exclude_ids);
            update_option('wpaic_settings', $settings);
        }

        // Remove from index
        WPAIC_Content_Index::delete_by_post_id($post_id);

        wp_send_json_success([
            /* translators: notification after excluding a page from learning */
            'message' => __('Excluded from learning.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * Re-include a post in crawler AJAX
     */
    public function ajax_crawler_include_post(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $post_id = absint(wp_unslash($_POST['post_id'] ?? 0));
        if (!$post_id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $settings = get_option('wpaic_settings', []);
        $exclude_ids = $settings['crawler_exclude_ids'] ?? [];
        $exclude_ids = array_values(array_diff($exclude_ids, [$post_id]));
        $settings['crawler_exclude_ids'] = $exclude_ids;
        update_option('wpaic_settings', $settings);

        wp_send_json_success([
            /* translators: notification after removing a page from the exclusion list */
            'message' => __('Exclusion removed.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * API connection test AJAX
     */
    public function ajax_test_api(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

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
            $settings = get_option('wpaic_settings', []);
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
                $ai = new WPAIC_Claude_Provider();
            } elseif ($provider === 'gemini') {
                $ai = new WPAIC_Gemini_Provider();
            } elseif ($provider === 'openrouter') {
                $ai = new WPAIC_OpenRouter_Provider();
            } else {
                $ai = new WPAIC_OpenAI_Provider();
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
                error_log('WPAIC ajax_test_api: ' . $e->getMessage());
            }
            self::log_diagnostic_event('api_test_failed');
            wp_send_json_error(__('API request failed. Please check your API key and try again.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Fetch models from API via AJAX
     */
    public function ajax_fetch_models(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'openai'));
        $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
        $use_saved = !empty(wp_unslash($_POST['use_saved']));
        $force_refresh = !empty(wp_unslash($_POST['force_refresh']));

        // Use saved API key if requested
        if ($use_saved || empty($api_key)) {
            $settings = get_option('wpaic_settings', []);
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
                $ai = new WPAIC_Claude_Provider();
                break;
            case 'gemini':
                $ai = new WPAIC_Gemini_Provider();
                break;
            case 'openrouter':
                $ai = new WPAIC_OpenRouter_Provider();
                break;
            default:
                $ai = new WPAIC_OpenAI_Provider();
                $provider = 'openai';
                break;
        }

        $ai->set_api_key($api_key);

        // Delete cache if force refresh
        if ($force_refresh) {
            $cache_key = 'wpaic_models_' . $provider . '_v2_' . md5($api_key);
            delete_transient($cache_key);
            // Also delete old cache key format
            delete_transient('wpaic_models_' . $provider . '_' . md5($api_key));
        }

        // Try API fetch
        $models = $ai->fetch_models_from_api();
        $source = 'api';

        if (empty($models)) {
            // Fallback to hardcoded list
            $models = $ai->get_available_models();
            $source = 'hardcoded';
        } else {
            // Check if from cache
            $cache_key = 'wpaic_models_' . $provider . '_' . md5($api_key);
            if (get_transient($cache_key) !== false && !$force_refresh) {
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
     * Decrypt API key
     */
    private function decrypt_api_key(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }

        // Return as-is if not encrypted (check known API key prefixes)
        if (strpos($encrypted, 'sk-') === 0 || strpos($encrypted, 'sk-ant-') === 0 || strpos($encrypted, 'AIza') === 0 || strpos($encrypted, 'sk-or-') === 0) {
            return $encrypted;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $new_key = self::get_encryption_key();
        $aad = self::get_encryption_aad();
        $old_key = wp_salt('auth'); // Legacy: raw salt string

        // AES-256-GCM (new format with tamper detection)
        if (strpos($encrypted, 'encg:') === 0) {
            $data = base64_decode(substr($encrypted, 5), true);
            if ($data === false || strlen($data) <= 28) { // 12 (IV) + 16 (tag) = 28 minimum
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (invalid GCM data). Key may need to be re-entered.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return '';
            }

            $iv  = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $encrypted_data = substr($data, 28);

            // Try normalized key + AAD first (current format)
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);

            // Fallback: normalized key without AAD
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag);
            }

            // Fallback: legacy raw salt key without AAD
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $old_key, OPENSSL_RAW_DATA, $iv, $tag);
            }

            if ($decrypted === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (GCM auth failed). Please re-enter your API key in settings.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                if (!get_transient('wpaic_api_key_decryption_failed')) {
                    set_transient('wpaic_api_key_decryption_failed', true, HOUR_IN_SECONDS);
                }
                return '';
            }

            return $decrypted;
        }

        // AES-256-CBC (legacy format, no tamper detection)
        $raw = $encrypted;
        if (strpos($raw, 'enc:') === 0) {
            $raw = substr($raw, 4);
        }

        $data = base64_decode($raw, true);

        if ($data === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (invalid base64). Key may need to be re-entered.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($data) <= $iv_length) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (data too short). Key may need to be re-entered.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted_data = substr($data, $iv_length);

        // Try normalized key first, then legacy key
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $new_key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $old_key, OPENSSL_RAW_DATA, $iv);
        }

        if ($decrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (salt may have changed). Please re-enter your API key in settings.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (!get_transient('wpaic_api_key_decryption_failed')) {
                set_transient('wpaic_api_key_decryption_failed', true, HOUR_IN_SECONDS);
            }
            return '';
        }

        return $decrypted;
    }

    /**
     * Show admin notice when API key decryption fails
     */
    public function api_key_decryption_notice(): void {
        if (!get_transient('wpaic_api_key_decryption_failed')) {
            return;
        }
        if (!current_user_can(self::get_manage_cap())) {
            return;
        }
        $settings_url = admin_url('admin.php?page=wpaic-settings');
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Rapls AI Chatbot:', 'rapls-ai-chatbot'); ?></strong>
                <?php esc_html_e('API key decryption failed. This may happen after a site migration or when WordPress security salts are changed. Please re-enter your API key in', 'rapls-ai-chatbot'); ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Settings', 'rapls-ai-chatbot'); ?></a>.
            </p>
        </div>
        <?php
        // Clear the transient once shown
        delete_transient('wpaic_api_key_decryption_failed');
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
        if (strpos($page, 'wpaic') !== 0) {
            return $text;
        }
        $build = defined('WPAIC_BUILD') ? WPAIC_BUILD : '';
        if ($build && strpos($build, 'Format') === false) {
            $text .= ' | Rapls AI Chatbot v' . esc_html(WPAIC_VERSION) . ' build ' . esc_html($build);
        } else {
            $text .= ' | Rapls AI Chatbot v' . esc_html(WPAIC_VERSION) . ' (dev)';
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

        $settings = get_option('wpaic_settings', []);
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
        $samesite = apply_filters('wpaic_cookie_samesite', 'Lax');
        if ($samesite === 'None' && !is_ssl()) {
            $errors[] = __('SameSite=None is set via the wpaic_cookie_samesite filter, but this site does not use HTTPS. Browsers require Secure cookies for SameSite=None, so the session cookie will not be sent. Sessions will reset on every page load. Remove the filter or switch to HTTPS.', 'rapls-ai-chatbot');
        }

        // Proxy trust without trusted proxies configured
        if (!empty($settings['trust_proxy_ip'])) {
            $raw_proxies = (array) apply_filters('wpaic_trusted_proxies', []);
            if (empty($raw_proxies) && empty($settings['trust_cloudflare_ip'])) {
                $warnings[] = __('Reverse proxy trust is enabled but no trusted proxy IPs are configured via the wpaic_trusted_proxies filter. X-Forwarded-For header will only be trusted from private/loopback IPs. Add your proxy IPs via the filter for correct IP detection.', 'rapls-ai-chatbot');
            }
        }

        $settings_url = admin_url('admin.php?page=wpaic-settings');

        // Critical errors (red, not dismissible, shown on all admin pages)
        if (!empty($errors)) {
            ?>
            <div class="notice notice-error" id="wpaic-security-error-notice">
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
        if (strpos($page, 'wpaic') !== 0) {
            return;
        }

        // Allow dismissing for 30 days
        if (get_transient('wpaic_security_notice_dismissed')) {
            return;
        }

        $dismiss_nonce = wp_create_nonce('wpaic_dismiss_security_notice');
        ?>
        <div class="notice notice-warning is-dismissible" id="wpaic-security-notice">
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
                <a href="#" id="wpaic-dismiss-security" style="color: #999;"><?php esc_html_e('Dismiss for 30 days', 'rapls-ai-chatbot'); ?></a>
            </p>
        </div>
        <script>
        document.getElementById('wpaic-dismiss-security').addEventListener('click', function(e) {
            e.preventDefault();
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var notice = document.getElementById('wpaic-security-notice');
                if (notice) notice.remove();
            };
            xhr.send('action=wpaic_dismiss_security_notice&_wpnonce=<?php echo esc_js($dismiss_nonce); ?>');
        });
        </script>
        <?php
    }

    /**
     * Encrypt a secret value (general purpose, no prefix check)
     */
    private function encrypt_secret(string $value): string {
        if (empty($value) || !function_exists('openssl_encrypt')) {
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
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: Secret decryption failed (invalid GCM data).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: Secret decryption failed (GCM auth failed).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return '';
            }

            return $decrypted;
        }

        // AES-256-CBC (legacy format, no tamper detection)
        $data = base64_decode(substr($value, 4), true);

        if ($data === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: Secret decryption failed (invalid base64).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: Secret decryption failed (salt may have changed).'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));

        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $messages = WPAIC_Message::get_by_conversation($conversation_id);

        $formatted = array_map(function($msg) {
            $data = [
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

        wp_send_json_success($formatted);
    }

    /**
     * Delete conversation AJAX
     */
    public function ajax_delete_conversation(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));

        if (!$conversation_id) {
            wp_send_json_error(__('Conversation ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = WPAIC_Conversation::delete($conversation_id);

        if ($result) {
            if (class_exists('WPAIC_Audit_Logger')) {
                WPAIC_Audit_Logger::log('conversation_deleted', 'conversation', $conversation_id);
            }
            wp_send_json_success(__('Conversation deleted.', 'rapls-ai-chatbot'));
        } else {
            wp_send_json_error(__('Failed to delete.', 'rapls-ai-chatbot'));
        }
    }

    /**
     * Bulk delete conversations AJAX
     */
    public function ajax_delete_conversations_bulk(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint sanitizes each element
        $ids = isset($_POST['conversation_ids']) ? array_map('absint', wp_unslash((array) $_POST['conversation_ids'])) : [];

        if (empty($ids)) {
            wp_send_json_error(__('No conversations selected for deletion.', 'rapls-ai-chatbot'));
        }

        $deleted = WPAIC_Conversation::delete_multiple($ids);

        /* translators: %d: number of deleted conversations */
        wp_send_json_success(sprintf(__('%d conversations deleted.', 'rapls-ai-chatbot'), $deleted));
    }

    /**
     * Delete all conversations AJAX
     */
    public function ajax_delete_all_conversations(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('delete_all_conversations')) {
            return;
        }

        WPAIC_Conversation::delete_all();

        wp_send_json_success(__('All conversation history deleted.', 'rapls-ai-chatbot'));
    }

    /**
     * Add knowledge AJAX
     */
    public function ajax_add_knowledge(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $priority = absint(wp_unslash($_POST['priority'] ?? 0));
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'qa'));

        if (empty($title) || empty($content)) {
            wp_send_json_error(__('Title and content are required.', 'rapls-ai-chatbot'));
        }

        // Check FAQ limit
        $pro_features = WPAIC_Pro_Features::get_instance();
        if (!$pro_features->can_add_faq()) {
            wp_send_json_error(sprintf(
                /* translators: %d: FAQ limit number */
                __('FAQ limit reached (%d items). Upgrade to Pro for unlimited entries.', 'rapls-ai-chatbot'),
                $pro_features->get_faq_limit()
            ));
        }

        $result = WPAIC_Knowledge::create([
            'title'    => $title,
            'content'  => $content,
            'category' => $category,
            'priority' => $priority,
            'type'     => $type,
        ]);

        if ($result) {
            if (class_exists('WPAIC_Audit_Logger')) {
                WPAIC_Audit_Logger::log('knowledge_created', 'knowledge', (int) $result['id'], ['title' => $title]);
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
            check_ajax_referer('wpaic_admin_nonce', 'nonce');

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

            // Check FAQ limit
            $pro_features = WPAIC_Pro_Features::get_instance();
            if (!$pro_features->can_add_faq()) {
                wp_send_json_error(sprintf(
                    /* translators: %d: FAQ limit number */
                    __('FAQ limit reached (%d items). Upgrade to Pro for unlimited entries.', 'rapls-ai-chatbot'),
                    $pro_features->get_faq_limit()
                ));
            }

            $result = WPAIC_Knowledge::import_from_file($file, $category);

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
                    error_log('WPAIC import error [' . $code . ']: ' . $result->get_error_message());
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $knowledge = WPAIC_Knowledge::get_by_id($id);

        if (!$knowledge) {
            wp_send_json_error(__('Knowledge not found.', 'rapls-ai-chatbot'));
        }

        wp_send_json_success($knowledge);
    }

    /**
     * Update knowledge AJAX
     */
    public function ajax_update_knowledge(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $priority = absint(wp_unslash($_POST['priority'] ?? 0));

        if (!$id || empty($title) || empty($content)) {
            wp_send_json_error(__('Required fields are missing.', 'rapls-ai-chatbot'));
        }

        $result = WPAIC_Knowledge::update($id, [
            'title'    => $title,
            'content'  => $content,
            'category' => $category,
            'priority' => $priority,
        ]);

        if ($result !== false) {
            if (class_exists('WPAIC_Audit_Logger')) {
                WPAIC_Audit_Logger::log('knowledge_updated', 'knowledge', $id, ['title' => $title]);
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = WPAIC_Knowledge::delete($id);

        if ($result) {
            if (class_exists('WPAIC_Audit_Logger')) {
                WPAIC_Audit_Logger::log('knowledge_deleted', 'knowledge', $id);
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $is_active = isset($_POST['is_active']) ? absint(wp_unslash($_POST['is_active'])) : 1;

        if (!$id) {
            wp_send_json_error(__('ID not specified.', 'rapls-ai-chatbot'));
        }

        $result = WPAIC_Knowledge::update($id, ['is_active' => $is_active]);

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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

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

        $result = WPAIC_Knowledge::update($id, ['priority' => $priority]);

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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $include_knowledge = !empty(wp_unslash($_POST['include_knowledge']));

        $export_data = [
            'version' => WPAIC_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => get_option('wpaic_settings', []),
        ];

        // Exclude API keys for security
        $sensitive_keys = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'recaptcha_secret_key'];
        foreach ($sensitive_keys as $key) {
            if (isset($export_data['settings'][$key])) {
                $export_data['settings'][$key] = '';
            }
        }

        // Include knowledge data if requested
        if ($include_knowledge) {
            $knowledge_list = WPAIC_Knowledge::get_list(['per_page' => 9999]);
            $export_data['knowledge'] = $knowledge_list;
        }

        wp_send_json_success($export_data);
    }

    /**
     * Import settings AJAX
     */
    public function ajax_import_settings(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

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
            $current_settings = get_option('wpaic_settings', []);
            $allowed_keys = array_keys(self::get_all_defaults());

            // Filter to only allowed keys
            $filtered_settings = array_intersect_key($import_data['settings'], array_flip($allowed_keys));

            // Keep current API keys (never overwrite with empty)
            $sensitive_keys = ['openai_api_key', 'claude_api_key', 'gemini_api_key', 'recaptcha_secret_key'];
            foreach ($sensitive_keys as $key) {
                if (!empty($current_settings[$key]) && empty($filtered_settings[$key])) {
                    $filtered_settings[$key] = $current_settings[$key];
                }
            }

            // Merge with current settings (preserve keys not in import)
            $merged_settings = array_merge($current_settings, $filtered_settings);

            // Pass through sanitize_settings() to ensure imported values are valid
            // (clamps out-of-range numbers, sanitizes strings, etc.)
            $merged_settings = $this->sanitize_settings_values($merged_settings, $current_settings);

            update_option('wpaic_settings', $merged_settings);
        }

        // Import knowledge data (respects FAQ limit for Free users)
        $knowledge_count = 0;
        if (isset($import_data['knowledge']) && is_array($import_data['knowledge'])) {
            $pro = WPAIC_Pro_Features::get_instance();
            foreach ($import_data['knowledge'] as $item) {
                if (!$pro->can_add_faq()) {
                    break; // FAQ limit reached — stop importing
                }
                $result = WPAIC_Knowledge::create([
                    'title'     => $item['title'] ?? '',
                    'content'   => $item['content'] ?? '',
                    'category'  => $item['category'] ?? '',
                    'is_active' => $item['is_active'] ?? 1,
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
            'openai_model'          => 'gpt-4o',
            'claude_api_key'        => '',
            'claude_model'          => 'claude-sonnet-4-20250514',
            'gemini_api_key'        => '',
            'gemini_model'          => 'gemini-2.0-flash-exp',
            'openrouter_api_key'    => '',
            'openrouter_model'      => 'openrouter/auto',

            // Chatbot Settings
            'bot_name'              => 'Assistant',
            'bot_avatar'            => '🤖',
            'welcome_message'       => 'Hello! How can I help you today?',
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
            'badge_margin_right'    => 20,
            'badge_margin_bottom'   => 20,
            'primary_color'         => '#007bff',
            'show_on_mobile'        => true,
            'widget_theme'          => 'default',
            'dark_mode'             => false,
            'markdown_enabled'      => true,

            // Page Visibility
            'badge_show_on_home'    => true,
            'badge_show_on_posts'   => true,
            'badge_show_on_pages'   => true,
            'badge_show_on_archives' => true,
            'badge_include_ids'     => '',
            'badge_exclude_ids'     => '',

            // History Settings
            'save_history'          => true,
            'retention_days'        => 90,

            // Privacy / Consent
            'consent_strict_mode'   => false,

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

            // Pro Features
            'pro_features'          => WPAIC_Pro_Features::get_default_settings(),
        ];
    }

    /**
     * Reset settings AJAX
     */
    public function ajax_reset_settings(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('reset_settings')) {
            return;
        }

        // Default settings
        $default_settings = [
            'ai_provider'           => 'openai',
            'openai_api_key'        => '',
            'openai_model'          => 'gpt-4o-mini',
            'claude_api_key'        => '',
            'claude_model'          => 'claude-sonnet-4-20250514',
            'gemini_api_key'        => '',
            'gemini_model'          => 'gemini-2.0-flash-exp',
            'system_prompt'         => "You are a knowledgeable assistant for this website. Follow these rules:\n\n1. ACCURACY: When reference information is provided, treat it as the primary and most reliable source. Base your answers on this information first.\n2. HONESTY: If the provided information does not cover the user's question, clearly state that you don't have specific information about it, then offer general guidance if appropriate.\n3. NO FABRICATION: Never invent facts, URLs, prices, dates, or specific details that are not in the provided reference information.\n4. CONCISENESS: Provide clear, focused answers. Avoid unnecessary repetition or filler.\n5. LANGUAGE: Always respond in the same language the user writes in.\n6. TONE: Be professional, friendly, and helpful.",
            'quota_error_message'   => 'Currently recharging. Please try again later.',
            'max_tokens'            => 1000,
            'temperature'           => 0.7,
            'message_history_count' => 10,
            'rate_limit'            => 20,
            'rate_limit_window'     => 3600,
            'crawler_enabled'       => false,
            'crawler_post_types'    => ['post', 'page'],
            'crawler_interval'      => 'daily',
            'crawler_chunk_size'    => 1000,
            'crawler_max_results'   => 3,
            'bot_name'              => 'Assistant',
            'bot_avatar'            => '🤖',
            'welcome_message'       => 'Hello! How can I help you today?',
            'knowledge_exact_prompt' => "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===",
            'knowledge_qa_prompt'   => "=== CRITICAL INSTRUCTIONS ===\nBelow is a FAQ database. When the user asks a question:\n1. FIRST, look for [BEST MATCH] - this is the most relevant Q&A for the user's question\n2. If [BEST MATCH] exists, use that Answer to respond\n3. If no [BEST MATCH], find the Question that matches or is similar to the user's question\n4. Return the corresponding Answer from the FAQ\n5. DO NOT make up answers - ONLY use the information provided below\n\nIMPORTANT: The Answer after [BEST MATCH] is your primary response source.\n\n=== FAQ DATABASE ===\n{context}\n=== END FAQ DATABASE ===",
            'site_context_prompt'   => "[IMPORTANT: Reference Information]\nYou MUST use the following information as the primary source when answering. If the answer can be found in this information, use it directly.\nIf the reference information does NOT contain the answer, clearly state that you don't have specific information about it. Do NOT guess or fabricate details.\n\n{context}",
            'regenerate_prompt'     => '[REGENERATION REQUEST #{variation_number}]: The user wants a DIFFERENT answer. FORBIDDEN: Do not start with "{forbidden_start}". {style}. Create a completely new response with different wording. IMPORTANT: Do NOT use headings, labels, or section markers like【】or brackets. Write in natural flowing paragraphs. Complete all sentences fully.',
            'feedback_good_header'  => "[LEARNING FROM USER FEEDBACK - GOOD EXAMPLES]\nThe following responses received positive feedback. Use these as examples of good responses:",
            'feedback_bad_header'   => "[LEARNING FROM USER FEEDBACK - AVOID THESE PATTERNS]\nThe following responses received negative feedback. AVOID responding in similar ways:",
            'summary_prompt'        => 'Please summarize the following conversation in 2-3 sentences, highlighting the main topics discussed and any conclusions reached:',
            'badge_show_on_home'    => true,
            'badge_show_on_posts'   => true,
            'badge_show_on_pages'   => true,
            'badge_show_on_archives' => true,
            'badge_include_ids'     => '',
            'badge_exclude_ids'     => '',
            'display_pages'         => 'all',
            'badge_position'        => 'bottom-right',
            'recaptcha_enabled'     => false,
            'recaptcha_site_key'    => '',
            'recaptcha_secret_key'  => '',
            'recaptcha_threshold'   => 0.5,
        ];

        update_option('wpaic_settings', $default_settings);

        wp_send_json_success(__('Settings have been reset.', 'rapls-ai-chatbot'));
    }

    /**
     * Reset usage statistics AJAX
     */
    public function ajax_reset_usage(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        if (!$this->verify_destructive_token('reset_usage')) {
            return;
        }

        $result = WPAIC_Cost_Calculator::reset_usage_stats();

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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $generator = new WPAIC_Embedding_Generator();
        if (!$generator->is_configured()) {
            wp_send_json_error(__('Embedding provider is not configured. Please check your API key settings.', 'rapls-ai-chatbot'));
        }

        $source = sanitize_text_field(wp_unslash($_POST['source'] ?? 'index'));

        if ($source === 'knowledge') {
            $pending = WPAIC_Knowledge::get_unembedded_entries(50);
        } else {
            $pending = WPAIC_Content_Index::get_unembedded_chunks(50);
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
                $packed = WPAIC_Vector_Search::pack_embedding($emb);
                if ($source === 'knowledge') {
                    WPAIC_Knowledge::update_embedding($ids[$i], $packed, $generator->get_model());
                } else {
                    WPAIC_Content_Index::update_embedding($ids[$i], $packed, $generator->get_model());
                }
                $processed++;
            }
        }

        // Count remaining
        if ($source === 'knowledge') {
            $remaining = count(WPAIC_Knowledge::get_unembedded_entries(1));
        } else {
            $remaining = count(WPAIC_Content_Index::get_unembedded_chunks(1));
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
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        WPAIC_Content_Index::clear_all_embeddings();
        WPAIC_Knowledge::clear_all_embeddings();

        wp_send_json_success(['message' => __('All embeddings have been cleared.', 'rapls-ai-chatbot')]);
    }

    /**
     * AJAX: Get embedding status
     */
    public function ajax_embedding_status(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        $index_stats = WPAIC_Content_Index::get_embedding_stats();
        $knowledge_stats = WPAIC_Knowledge::get_embedding_stats();

        $generator = new WPAIC_Embedding_Generator();

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
        check_ajax_referer('wpaic_dismiss_security_notice', '_wpnonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        set_transient('wpaic_security_notice_dismissed', true, 30 * DAY_IN_SECONDS);
        wp_send_json_success();
    }

    /**
     * AJAX: Generate a new MCP API key.
     * Stores hashed key, returns raw key (shown once only).
     */
    public function ajax_generate_mcp_key(): void {
        check_ajax_referer('wpaic_generate_mcp_key', '_wpnonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // Generate a 40-character alphanumeric key
        $raw_key = wp_generate_password(40, false);

        // Store hashed version
        // Bypass sanitize_settings callback to prevent it from overwriting
        // the hash with the stale $existing value (update_option triggers
        // sanitize_option_{option} which calls sanitize_settings).
        $settings = get_option('wpaic_settings', []);
        $settings['mcp_api_key_hash'] = wp_hash_password($raw_key);
        remove_all_filters('sanitize_option_wpaic_settings');
        update_option('wpaic_settings', $settings);
        // Re-register so subsequent form saves still sanitize
        register_setting('wpaic_settings_group', 'wpaic_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        wp_send_json_success([
            'api_key'  => $raw_key,
            'endpoint' => rest_url('wp-ai-chatbot/v1/mcp'),
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
        $transient_key = 'wpaic_confirm_' . $action . '_' . get_current_user_id();

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
            'wpaic-pro-settings'  => 'render_pro_settings_preview',
            'wpaic-crawler'       => 'render_crawler_preview',
            'wpaic-conversations' => 'render_conversations_preview',
            'wpaic-analytics'     => 'render_analytics_preview',
            'wpaic-leads'         => 'render_leads_preview',
            'wpaic-audit-log'     => 'render_audit_log_preview',
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
        <div class="wrap wpaic-admin">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="wpaic-pro-upgrade-banner">
                <div class="wpaic-pro-upgrade-content">
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

            <div class="wpaic-pro-preview-wrapper">
                <div class="wpaic-pro-preview">
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
     * Shared CSS for all preview pages
     */
    private function render_pro_preview_styles(): void {
        ?>
        <style>
        .wpaic-pro-upgrade-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .wpaic-pro-upgrade-content {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #fff;
        }
        .wpaic-pro-upgrade-content .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
        }
        .wpaic-pro-upgrade-content div {
            flex: 1;
        }
        .wpaic-pro-upgrade-content strong {
            font-size: 16px;
        }
        .wpaic-pro-upgrade-content p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .wpaic-pro-upgrade-content .button {
            background: #fff;
            color: #667eea;
            border: none;
        }
        .wpaic-pro-upgrade-content .button:hover {
            background: #f0f0f0;
            color: #764ba2;
        }

        .wpaic-pro-preview-wrapper {
            position: relative;
            margin: 20px 0;
        }
        .wpaic-pro-preview {
            opacity: 0.55;
            pointer-events: none;
            user-select: none;
        }
        .wpaic-pro-preview input:disabled,
        .wpaic-pro-preview select:disabled,
        .wpaic-pro-preview textarea:disabled,
        .wpaic-pro-preview button:disabled {
            cursor: not-allowed;
        }

        .wpaic-pro-features-list {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px 30px;
            margin-top: 20px;
        }
        .wpaic-pro-features-list h3 {
            margin-top: 0;
        }
        .wpaic-pro-features-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .wpaic-pro-features-list li {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .wpaic-pro-features-list .dashicons-yes {
            color: #00a32a;
        }
        </style>
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
        <div class="wpaic-crawler-grid">
        <!-- Status Card -->
        <div class="wpaic-card wpaic-card-status">
            <h2><?php esc_html_e('Learning Status', 'rapls-ai-chatbot'); ?></h2>
            <table class="wpaic-status-table">
                <tr>
                    <td><?php esc_html_e('Learning Feature', 'rapls-ai-chatbot'); ?></td>
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
            <div class="wpaic-actions">
                <button type="button" class="button button-primary" disabled>🔄 <?php esc_html_e('Run Learning Now', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>

        <!-- Vector Embedding Card -->
        <div class="wpaic-card wpaic-card-embedding">
            <h2><?php esc_html_e('Vector Embedding', 'rapls-ai-chatbot'); ?></h2>
            <table class="wpaic-status-table">
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
            <div class="wpaic-actions" style="margin-top: 12px;">
                <button type="button" class="button button-primary" disabled><?php esc_html_e('Generate Embeddings', 'rapls-ai-chatbot'); ?></button>
                <button type="button" class="button button-secondary" disabled>🗑️ <?php esc_html_e('Clear All Embeddings', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>

        <!-- Settings Card -->
        <div class="wpaic-card wpaic-card-settings">
            <h2><?php esc_html_e('Learning Settings', 'rapls-ai-chatbot'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Learning Feature', 'rapls-ai-chatbot'); ?></th>
                    <td><label><input type="checkbox" checked disabled> <?php esc_html_e('Auto-learn site content', 'rapls-ai-chatbot'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Target Content', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <label style="display: block; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                            <input type="checkbox" checked disabled>
                            <strong><?php esc_html_e('All Public Content (Recommended)', 'rapls-ai-chatbot'); ?></strong>
                            <p class="description" style="margin-left: 24px; margin-top: 4px;">
                                <?php esc_html_e('Learn all posts, pages, custom post types, and custom fields.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </label>
                        <div style="opacity: 0.5;">
                            <p class="description" style="margin-bottom: 8px;"><?php esc_html_e('Or select individually:', 'rapls-ai-chatbot'); ?></p>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox" disabled> post</label>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox" disabled> page</label>
                            <label style="display: block; margin-bottom: 5px;"><input type="checkbox" disabled> product</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Auto Learning Interval', 'rapls-ai-chatbot'); ?></th>
                    <td>
                        <select disabled>
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
                        <input type="number" value="3" min="1" max="10" disabled class="small-text">
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
                            <input type="number" min="1" class="small-text" placeholder="ID" disabled>
                            <button type="button" class="button button-small" disabled><?php esc_html_e('Add by ID', 'rapls-ai-chatbot'); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e('Pages listed here will be skipped during learning and removed from the index.', 'rapls-ai-chatbot'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enhanced Content Extraction', 'rapls-ai-chatbot'); ?> <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-left: 5px;">PRO</span></th>
                    <td>
                        <label><input type="checkbox" disabled> <?php esc_html_e('Enable enhanced HTML content extraction', 'rapls-ai-chatbot'); ?></label>
                        <p class="description"><?php esc_html_e('Uses DOMDocument to parse HTML and extract structured content from headings, tables, lists, code blocks, and meta tags.', 'rapls-ai-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Post Type Statistics -->
        <div class="wpaic-list-stats wpaic-card-full" style="margin-bottom: 20px;">
            <div class="wpaic-list-stat-card">
                <div class="stat-value">24</div>
                <div class="stat-label"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
            </div>
            <div class="wpaic-list-stat-card stat-info">
                <div class="stat-value">12</div>
                <div class="stat-label">post</div>
            </div>
            <div class="wpaic-list-stat-card stat-warning">
                <div class="stat-value">8</div>
                <div class="stat-label">page</div>
            </div>
            <div class="wpaic-list-stat-card">
                <div class="stat-value">4</div>
                <div class="stat-label">product</div>
            </div>
        </div>

        <!-- Indexed Pages Table -->
        <div class="wpaic-card wpaic-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></h2>
                <button type="button" class="button button-secondary" disabled>🗑️ <?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?></button>
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
                    <tr><td>Sample Page</td><td>page</td><td>/sample-page/</td><td>2026/02/13 09:00</td><td style="white-space: nowrap;"><button class="button button-small" disabled>🗑️</button> <button class="button button-small" disabled>🚫</button></td></tr>
                    <tr><td>Hello World</td><td>post</td><td>/hello-world/</td><td>2026/02/13 09:00</td><td style="white-space: nowrap;"><button class="button button-small" disabled>🗑️</button> <button class="button button-small" disabled>🚫</button></td></tr>
                    <tr><td>About Us</td><td>page</td><td>/about/</td><td>2026/02/12 14:30</td><td style="white-space: nowrap;"><button class="button button-small" disabled>🗑️</button> <button class="button button-small" disabled>🚫</button></td></tr>
                </tbody>
            </table>
        </div>
        </div><!-- .wpaic-crawler-grid -->
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .wpaic-pro-preview -->
            </div><!-- .wpaic-pro-preview-wrapper -->

            <div class="wpaic-pro-features-list">
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
            <input type="text" class="regular-text" disabled placeholder="<?php esc_attr_e('Search messages', 'rapls-ai-chatbot'); ?>">
            <select disabled>
                <option><?php esc_html_e('All Statuses', 'rapls-ai-chatbot'); ?></option>
                <option>Active</option>
                <option>Closed</option>
                <option>Archived</option>
            </select>
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('From:', 'rapls-ai-chatbot'); ?></label>
            <input type="date" disabled>
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('To:', 'rapls-ai-chatbot'); ?></label>
            <input type="date" disabled>
            <button class="button" disabled><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Actions Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div style="display: flex; gap: 8px;">
                <button class="button" disabled><?php esc_html_e('Delete Selected', 'rapls-ai-chatbot'); ?></button>
                <button class="button" disabled style="color: #d63638;"><?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?></button>
                <button class="button" disabled><?php esc_html_e('Reset All User Sessions', 'rapls-ai-chatbot'); ?></button>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <select disabled><option>CSV</option><option>JSON</option></select>
                <input type="date" disabled>
                <input type="date" disabled>
                <button class="button" disabled><?php esc_html_e('Export', 'rapls-ai-chatbot'); ?> <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 10px; margin-left: 4px;">PRO</span></button>
            </div>
        </div>

        <!-- Conversations Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30px;"><input type="checkbox" disabled></th>
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
                    <td><input type="checkbox" disabled></td>
                    <td>1</td>
                    <td><code>abc123...</code></td>
                    <td style="text-align: center;">6</td>
                    <td>Taro Yamada<br><small>taro@example.com</small></td>
                    <td>/</td>
                    <td><span style="background: #e7f5e7; color: #00a32a; padding: 2px 8px; border-radius: 3px; font-size: 12px;">active</span></td>
                    <td>2026/02/13 10:00</td>
                    <td>2026/02/13 10:15</td>
                    <td>
                        <button class="button button-small" disabled><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td><input type="checkbox" disabled></td>
                    <td>2</td>
                    <td><code>def456...</code></td>
                    <td style="text-align: center;">4</td>
                    <td>&mdash;</td>
                    <td>/about/</td>
                    <td><span style="background: #f0f0f1; color: #50575e; padding: 2px 8px; border-radius: 3px; font-size: 12px;">closed</span></td>
                    <td>2026/02/12 15:30</td>
                    <td>2026/02/12 15:45</td>
                    <td>
                        <button class="button button-small" disabled><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td><input type="checkbox" disabled></td>
                    <td>3</td>
                    <td><code>ghi789...</code></td>
                    <td style="text-align: center;">8</td>
                    <td>Hanako Suzuki<br><small>hanako@example.com</small></td>
                    <td>/contact/</td>
                    <td><span style="background: #f0f0f1; color: #50575e; padding: 2px 8px; border-radius: 3px; font-size: 12px;">closed</span></td>
                    <td>2026/02/11 09:15</td>
                    <td>2026/02/11 09:30</td>
                    <td>
                        <button class="button button-small" disabled><?php esc_html_e('Details', 'rapls-ai-chatbot'); ?></button>
                        <button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button>
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
                </div><!-- .wpaic-pro-preview -->
            </div><!-- .wpaic-pro-preview-wrapper -->

            <div class="wpaic-pro-features-list">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <select disabled>
                <option><?php esc_html_e('Last 7 days', 'rapls-ai-chatbot'); ?></option>
                <option selected><?php esc_html_e('Last 30 days', 'rapls-ai-chatbot'); ?></option>
                <option><?php esc_html_e('Last 90 days', 'rapls-ai-chatbot'); ?></option>
            </select>
            <div style="display: flex; gap: 8px;">
                <button class="button" disabled><span class="dashicons dashicons-printer" style="vertical-align: middle;"></span> <?php esc_html_e('Print / PDF', 'rapls-ai-chatbot'); ?></button>
                <button class="button button-primary" disabled><span class="dashicons dashicons-download" style="vertical-align: middle;"></span> <?php esc_html_e('Download PDF', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;">128</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Conversations', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #00a32a; margin-top: 4px;">▲ 12%</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #00a32a;">512</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Messages', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #00a32a; margin-top: 4px;">▲ 8%</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #dba617;">4.0</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Avg Messages', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #d63638; margin-top: 4px;">▼ 5%</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #00a32a;">85%</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Satisfaction', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #00a32a; margin-top: 4px;">▲ 3%</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #667eea;">2.4%</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Conversion Rate', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #00a32a; margin-top: 4px;">▲ 0.5%</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #1d2327;">$1.24</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('Estimated Cost', 'rapls-ai-chatbot'); ?></div>
                <div style="font-size: 11px; color: #666; margin-top: 4px;">18,420 tokens</div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #00a32a;">78</div>
                <div style="color: #666; font-size: 13px;"><?php esc_html_e('AI Quality Score', 'rapls-ai-chatbot'); ?> /100</div>
                <div style="font-size: 11px; color: #00a32a; margin-top: 4px;">▲ 5</div>
            </div>
        </div>

        <!-- Chart Placeholder -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Daily Conversations', 'rapls-ai-chatbot'); ?></h3>
            <div style="height: 200px; background: linear-gradient(to bottom, #f8f9fa, #fff); border-radius: 4px; display: flex; align-items: flex-end; justify-content: space-around; padding: 20px 10px 0;">
                <?php for ($i = 0; $i < 14; $i++): $h = rand(20, 100); ?>
                <div style="width: 5%; height: <?php echo esc_attr($h); ?>%; background: linear-gradient(to top, #667eea, #a8b5f0); border-radius: 3px 3px 0 0;"></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Satisfaction Trend + Hourly Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Satisfaction Trend', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 150px; display: flex; align-items: flex-end; justify-content: space-around; padding: 10px 5px 0;">
                    <?php
                    $satisfaction_values = array(78, 82, 80, 85, 83, 88, 85);
                    foreach ($satisfaction_values as $val): ?>
                    <div style="width: 10%; height: <?php echo esc_attr($val); ?>%; background: linear-gradient(to top, #00a32a, #72d572); border-radius: 3px 3px 0 0; position: relative;">
                        <span style="position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #666;"><?php echo esc_html($val); ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-around; font-size: 11px; color: #999; margin-top: 5px;">
                    <?php
                    $days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
                    foreach ($days as $day): ?>
                    <span><?php echo esc_html($day); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Hourly Activity', 'rapls-ai-chatbot'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 3px;">
                    <?php
                    $hours = array(2, 1, 0, 1, 3, 8, 15, 22, 25, 30, 28, 20, 18, 22, 25, 20, 15, 12, 8, 5, 4, 3, 2, 1);
                    $max_h = max($hours);
                    foreach ($hours as $idx => $count):
                        $intensity = $max_h > 0 ? round($count / $max_h * 100) : 0;
                        $bg = $intensity > 70 ? '#667eea' : ($intensity > 40 ? '#a8b5f0' : ($intensity > 10 ? '#d4dbf9' : '#f0f0f1'));
                    ?>
                    <div style="height: 24px; background: <?php echo esc_attr($bg); ?>; border-radius: 3px;" title="<?php echo esc_attr($idx); ?>:00 - <?php echo esc_attr($count); ?> <?php esc_attr_e('conversations', 'rapls-ai-chatbot'); ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #999; margin-top: 5px;">
                    <span>0:00</span><span>6:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
                </div>
            </div>
        </div>

        <!-- Two Column: Top Questions + Top Pages -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Frequently Asked Questions', 'rapls-ai-chatbot'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>#</th><th><?php esc_html_e('Question', 'rapls-ai-chatbot'); ?></th><th style="width: 60px;"><?php esc_html_e('Count', 'rapls-ai-chatbot'); ?></th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td>How do I reset my password?</td><td>24</td></tr>
                        <tr><td>2</td><td>What are your business hours?</td><td>18</td></tr>
                        <tr><td>3</td><td>How to contact support?</td><td>12</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Top Pages', 'rapls-ai-chatbot'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>#</th><th><?php esc_html_e('Page', 'rapls-ai-chatbot'); ?></th><th style="width: 60px;"><?php esc_html_e('Count', 'rapls-ai-chatbot'); ?></th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td>/</td><td>45</td></tr>
                        <tr><td>2</td><td>/pricing/</td><td>32</td></tr>
                        <tr><td>3</td><td>/contact/</td><td>21</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Feedback Analytics -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Feedback Analytics', 'rapls-ai-chatbot'); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 15px;">
                <div style="background: #edf7ed; border-radius: 8px; padding: 15px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;">👍 42</div>
                    <div style="color: #666; font-size: 12px;"><?php esc_html_e('Positive', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div style="background: #fce8e6; border-radius: 8px; padding: 15px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #d63638;">👎 8</div>
                    <div style="color: #666; font-size: 12px;"><?php esc_html_e('Negative', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div style="background: #f0f6fc; border-radius: 8px; padding: 15px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #2271b1;">💬 50</div>
                    <div style="color: #666; font-size: 12px;"><?php esc_html_e('Total Feedback', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div style="background: #fef8ee; border-radius: 8px; padding: 15px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #dba617;">📈 9.8%</div>
                    <div style="color: #666; font-size: 12px;"><?php esc_html_e('Feedback Rate', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>
            <!-- Satisfaction Bar -->
            <div style="height: 24px; border-radius: 12px; overflow: hidden; display: flex; margin-bottom: 10px;">
                <div style="width: 84%; background: #00a32a; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: bold;">84%</div>
                <div style="width: 16%; background: #d63638; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: bold;">16%</div>
            </div>
        </div>

        <!-- Usage & Cost -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Daily Cost Trend', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 150px; background: linear-gradient(to bottom, #f8f9fa, #fff); border-radius: 4px; display: flex; align-items: flex-end; justify-content: space-around; padding: 20px 10px 0;">
                    <?php for ($i = 0; $i < 14; $i++): $h = rand(10, 80); ?>
                    <div style="width: 5%; height: <?php echo esc_attr($h); ?>%; background: linear-gradient(to top, #dba617, #f0d060); border-radius: 3px 3px 0 0;"></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;"><?php esc_html_e('Cost Breakdown by Model', 'rapls-ai-chatbot'); ?></h3>
                    <button class="button button-small" disabled><?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?></button>
                </div>
                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                    <thead><tr><th><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th><th style="width: 50px;"><?php esc_html_e('Msgs', 'rapls-ai-chatbot'); ?></th><th style="width: 70px;"><?php esc_html_e('Tokens', 'rapls-ai-chatbot'); ?></th><th style="width: 60px;"><?php esc_html_e('Cost', 'rapls-ai-chatbot'); ?></th></tr></thead>
                    <tbody>
                        <tr><td>gpt-4o</td><td>320</td><td>12,800</td><td>$0.89</td></tr>
                        <tr><td>gpt-4o-mini</td><td>192</td><td>5,620</td><td>$0.35</td></tr>
                    </tbody>
                    <tfoot><tr style="font-weight: bold;"><td><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></td><td>512</td><td>18,420</td><td>$1.24</td></tr></tfoot>
                </table>
            </div>
        </div>

        <!-- Knowledge Gaps -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">
                <?php esc_html_e('Knowledge Gaps', 'rapls-ai-chatbot'); ?>
                <span style="background: #fce8e6; color: #c5221f; padding: 2px 10px; border-radius: 10px; font-size: 13px; margin-left: 8px;">5</span>
            </h3>
            <p class="description"><?php esc_html_e('Frequently asked questions not covered by your knowledge base', 'rapls-ai-chatbot'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Question', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Times Asked', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Do you offer a free trial?</td>
                        <td>8</td>
                        <td><button class="button button-small" disabled><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                    <tr>
                        <td>Can I integrate with Slack?</td>
                        <td>5</td>
                        <td><button class="button button-small" disabled><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                    <tr>
                        <td>What payment methods do you accept?</td>
                        <td>3</td>
                        <td><button class="button button-small" disabled><?php esc_html_e('Add to KB', 'rapls-ai-chatbot'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Negative Feedback -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Negative Feedback (Needs Improvement)', 'rapls-ai-chatbot'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User Question', 'rapls-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Bot Answer (excerpt)', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Date', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>How much does the Pro version cost?</td>
                        <td style="color: #666;">I'm sorry, I don't have specific pricing information...</td>
                        <td>2026-02-13</td>
                    </tr>
                    <tr>
                        <td>Can I cancel my subscription?</td>
                        <td style="color: #666;">Based on general practices, most services allow...</td>
                        <td>2026-02-12</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Device Stats -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Device Statistics', 'rapls-ai-chatbot'); ?></h3>
            <div style="display: flex; gap: 30px;">
                <div style="text-align: center;">
                    <span class="dashicons dashicons-desktop" style="font-size: 32px; width: 32px; height: 32px; color: #2271b1;"></span>
                    <div style="font-size: 20px; font-weight: bold;">62%</div>
                    <div style="color: #666; font-size: 13px;">Desktop</div>
                </div>
                <div style="text-align: center;">
                    <span class="dashicons dashicons-smartphone" style="font-size: 32px; width: 32px; height: 32px; color: #00a32a;"></span>
                    <div style="font-size: 20px; font-weight: bold;">32%</div>
                    <div style="color: #666; font-size: 13px;">Mobile</div>
                </div>
                <div style="text-align: center;">
                    <span class="dashicons dashicons-tablet" style="font-size: 32px; width: 32px; height: 32px; color: #dba617;"></span>
                    <div style="font-size: 20px; font-weight: bold;">6%</div>
                    <div style="color: #666; font-size: 13px;">Tablet</div>
                </div>
            </div>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .wpaic-pro-preview -->
            </div><!-- .wpaic-pro-preview-wrapper -->

            <div class="wpaic-pro-features-list">
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
                </ul>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div style="background: #f0f6fc; border-radius: 6px; padding: 12px; text-align: center;">
                        <span class="dashicons dashicons-format-chat" style="font-size: 24px; width: 24px; height: 24px; color: #2271b1;"></span>
                        <div style="font-size: 20px; font-weight: 700; color: #1d2327; margin-top: 4px;">128</div>
                        <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Conversations', 'rapls-ai-chatbot'); ?></div>
                    </div>
                    <div style="background: #edf7ed; border-radius: 6px; padding: 12px; text-align: center;">
                        <span class="dashicons dashicons-thumbs-up" style="font-size: 24px; width: 24px; height: 24px; color: #00a32a;"></span>
                        <div style="font-size: 20px; font-weight: 700; color: #1d2327; margin-top: 4px;">85%</div>
                        <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Satisfaction', 'rapls-ai-chatbot'); ?></div>
                    </div>
                    <div style="background: #fef8ee; border-radius: 6px; padding: 12px; text-align: center;">
                        <span class="dashicons dashicons-chart-bar" style="font-size: 24px; width: 24px; height: 24px; color: #dba617;"></span>
                        <div style="font-size: 20px; font-weight: 700; color: #1d2327; margin-top: 4px;">4.0</div>
                        <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Avg Messages', 'rapls-ai-chatbot'); ?></div>
                    </div>
                </div>
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
            <button class="button" disabled><?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?></button>
            <button class="button" disabled><?php esc_html_e('Export JSON', 'rapls-ai-chatbot'); ?></button>
        </div>

        <!-- Filters -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" class="regular-text" disabled placeholder="<?php esc_attr_e('Search by name, email, or company...', 'rapls-ai-chatbot'); ?>">
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('From:', 'rapls-ai-chatbot'); ?></label>
            <input type="date" disabled>
            <label style="font-size: 13px; color: #666;"><?php esc_html_e('To:', 'rapls-ai-chatbot'); ?></label>
            <input type="date" disabled>
            <button class="button" disabled><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
            <button class="button" disabled><?php esc_html_e('Clear', 'rapls-ai-chatbot'); ?></button>
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
                    <td><button class="button button-small" disabled>#1</button></td>
                    <td>2026/02/13 10:30</td>
                    <td><button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
                <tr>
                    <td><strong>Hanako Suzuki</strong></td>
                    <td><a href="#">hanako@example.com</a></td>
                    <td><a href="#">080-9876-5432</a></td>
                    <td>Test Corp.</td>
                    <td><button class="button button-small" disabled>#2</button></td>
                    <td>2026/02/12 14:20</td>
                    <td><button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
                <tr>
                    <td><strong>Ichiro Tanaka</strong></td>
                    <td><a href="#">ichiro@example.com</a></td>
                    <td>&mdash;</td>
                    <td>&mdash;</td>
                    <td><button class="button button-small" disabled>#3</button></td>
                    <td>2026/02/11 16:45</td>
                    <td><button class="button button-small" disabled><?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?></button></td>
                </tr>
            </tbody>
        </table>
        <div class="tablenav bottom" style="margin-top: 10px;">
            <span style="color: #666;">3 <?php esc_html_e('items', 'rapls-ai-chatbot'); ?></span>
        </div>
        <?php
        // Close preview and wrapper divs manually to insert features list outside the faded area
        ?>
                </div><!-- .wpaic-pro-preview -->
            </div><!-- .wpaic-pro-preview-wrapper -->

            <div class="wpaic-pro-features-list">
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
            <select disabled>
                <option><?php esc_html_e('All Actions', 'rapls-ai-chatbot'); ?></option>
                <option>Settings Updated</option>
                <option>Knowledge Created</option>
                <option>Conversations Exported</option>
                <option>Lead Exported</option>
                <option>License Activated</option>
            </select>
            <input type="date" disabled>
            <input type="date" disabled>
            <input type="search" disabled placeholder="<?php esc_attr_e('Search...', 'rapls-ai-chatbot'); ?>">
            <button class="button" disabled><?php esc_html_e('Filter', 'rapls-ai-chatbot'); ?></button>
            <button class="button button-secondary" disabled style="margin-left: auto;"><span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?></button>
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
                    <td><span style="background: #e8f0fe; color: #1967d2; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Settings Updated</span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td><?php esc_html_e('Pro settings saved', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/19 15:20</td>
                    <td><span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Knowledge Created</span></td>
                    <td>admin</td>
                    <td>KB #12</td>
                    <td><?php esc_html_e('FAQ entry added: "How to reset password?"', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td>2026/02/18 09:00</td>
                    <td><span style="background: #fef7e0; color: #b06000; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Conversations Exported</span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td>format: CSV, count: 48</td>
                </tr>
                <tr>
                    <td>2026/02/17 14:45</td>
                    <td><span style="background: #fef7e0; color: #b06000; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Lead Exported</span></td>
                    <td>admin</td>
                    <td>&mdash;</td>
                    <td>format: JSON, count: 15</td>
                </tr>
                <tr>
                    <td>2026/02/15 11:00</td>
                    <td><span style="background: #e8f0fe; color: #1967d2; padding: 2px 8px; border-radius: 3px; font-size: 12px;">License Activated</span></td>
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
                </div><!-- .wpaic-pro-preview -->
            </div><!-- .wpaic-pro-preview-wrapper -->

            <div class="wpaic-pro-features-list">
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
        <div class="wrap wpaic-admin">
            <h1><?php esc_html_e('Pro Settings', 'rapls-ai-chatbot'); ?></h1>

            <!-- Upgrade Banner -->
            <div class="wpaic-pro-upgrade-banner">
                <div class="wpaic-pro-upgrade-content">
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
            <div class="wpaic-pro-preview-wrapper">
                <div class="wpaic-pro-preview">
                    <div class="wpaic-settings-tabs">
                        <!-- Group Tabs -->
                        <nav class="wpaic-tab-groups-nav">
                            <a href="#" class="wpaic-tab-group wpaic-tab-group-active" data-group="customer">
                                <span class="dashicons dashicons-groups"></span>
                                <?php esc_html_e('Customer', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="wpaic-tab-group" data-group="ai">
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php esc_html_e('AI', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="wpaic-tab-group" data-group="operations">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php esc_html_e('Operations', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="wpaic-tab-group" data-group="integrations">
                                <span class="dashicons dashicons-networking"></span>
                                <?php esc_html_e('Integrations', 'rapls-ai-chatbot'); ?>
                            </a>
                            <a href="#" class="wpaic-tab-group" data-group="management">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php esc_html_e('Management', 'rapls-ai-chatbot'); ?>
                            </a>
                        </nav>
                        <!-- Sub-tabs per group -->
                        <nav class="wpaic-sub-tabs" data-for="customer">
                            <a href="#tab-lead" class="wpaic-sub-tab wpaic-sub-tab-active" data-tab="tab-lead"><?php esc_html_e('Lead Capture', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-offline" class="wpaic-sub-tab" data-tab="tab-offline"><?php esc_html_e('Offline', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-conversion" class="wpaic-sub-tab" data-tab="tab-conversion"><?php esc_html_e('Conversion', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-ui" class="wpaic-sub-tab" data-tab="tab-ui"><?php esc_html_e('UI', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="wpaic-sub-tabs" data-for="ai" style="display:none;">
                            <a href="#tab-ai" class="wpaic-sub-tab" data-tab="tab-ai"><?php esc_html_e('AI Enhancement', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-prompts" class="wpaic-sub-tab" data-tab="tab-prompts"><?php esc_html_e('AI Prompts', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-content" class="wpaic-sub-tab" data-tab="tab-content"><?php esc_html_e('Content Filters', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="wpaic-sub-tabs" data-for="operations" style="display:none;">
                            <a href="#tab-business" class="wpaic-sub-tab" data-tab="tab-business"><?php esc_html_e('Business Hours', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-handoff" class="wpaic-sub-tab" data-tab="tab-handoff"><?php esc_html_e('Handoff', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-ai-forms" class="wpaic-sub-tab" data-tab="tab-ai-forms"><?php esc_html_e('AI Forms', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-badge" class="wpaic-sub-tab" data-tab="tab-badge"><?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-actions" class="wpaic-sub-tab" data-tab="tab-actions"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-scenarios" class="wpaic-sub-tab" data-tab="tab-scenarios"><?php esc_html_e('Scenarios', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-chatbots" class="wpaic-sub-tab" data-tab="tab-chatbots"><?php esc_html_e('Chatbots', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-screen-sharing" class="wpaic-sub-tab" data-tab="tab-screen-sharing"><?php esc_html_e('Screen Sharing', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="wpaic-sub-tabs" data-for="integrations" style="display:none;">
                            <a href="#tab-webhook" class="wpaic-sub-tab" data-tab="tab-webhook"><?php esc_html_e('Webhook', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-line" class="wpaic-sub-tab" data-tab="tab-line"><?php esc_html_e('LINE', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-integrations" class="wpaic-sub-tab" data-tab="tab-integrations"><?php esc_html_e('Integrations', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-booking" class="wpaic-sub-tab" data-tab="tab-booking"><?php esc_html_e('Booking', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-cache" class="wpaic-sub-tab" data-tab="tab-cache"><?php esc_html_e('Cache', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-queue" class="wpaic-sub-tab" data-tab="tab-queue"><?php esc_html_e('Queue', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-security" class="wpaic-sub-tab" data-tab="tab-security"><?php esc_html_e('Security', 'rapls-ai-chatbot'); ?></a>
                        </nav>
                        <nav class="wpaic-sub-tabs" data-for="management" style="display:none;">
                            <a href="#tab-budget" class="wpaic-sub-tab" data-tab="tab-budget"><?php esc_html_e('Usage & Budget', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-role-access" class="wpaic-sub-tab" data-tab="tab-role-access"><?php esc_html_e('Role Access', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-maintenance" class="wpaic-sub-tab" data-tab="tab-maintenance"><?php esc_html_e('Backup', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-history" class="wpaic-sub-tab" data-tab="tab-history"><?php esc_html_e('Change History', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-encryption" class="wpaic-sub-tab" data-tab="tab-encryption"><?php esc_html_e('Encryption', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-security-scan" class="wpaic-sub-tab" data-tab="tab-security-scan"><?php esc_html_e('Security Scan', 'rapls-ai-chatbot'); ?></a>
                            <a href="#tab-license" class="wpaic-sub-tab" data-tab="tab-license"><?php esc_html_e('License', 'rapls-ai-chatbot'); ?></a>
                        </nav>

                        <!-- Lead Capture Tab -->
                        <div id="tab-lead" class="tab-content active">
                            <h2><?php esc_html_e('Lead Capture', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Lead Capture', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show lead capture form before chat', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Require Lead Info', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Require lead information before allowing chat', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Title', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" disabled placeholder="<?php esc_attr_e('Enter form title...', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Capture Fields', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" checked disabled>
                                                <?php esc_html_e('Name', 'rapls-ai-chatbot'); ?>
                                                <select disabled style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" checked disabled>
                                                <?php esc_html_e('Email', 'rapls-ai-chatbot'); ?>
                                                <select disabled style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" disabled>
                                                <?php esc_html_e('Phone', 'rapls-ai-chatbot'); ?>
                                                <select disabled style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" disabled>
                                                <?php esc_html_e('Company', 'rapls-ai-chatbot'); ?>
                                                <select disabled style="margin-left: 10px;">
                                                    <option><?php esc_html_e('Optional', 'rapls-ai-chatbot'); ?></option>
                                                    <option><?php esc_html_e('Required', 'rapls-ai-chatbot'); ?></option>
                                                </select>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Email Notification', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Send email notification for new leads', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
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
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show special message outside business hours', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Timezone', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <select disabled>
                                            <option>Asia/Tokyo</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Business Hours Schedule', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <table class="wpaic-schedule-table">
                                            <tr>
                                                <td style="width: 100px;">
                                                    <label><input type="checkbox" checked disabled> <?php esc_html_e('Monday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="18:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked disabled> <?php esc_html_e('Tuesday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="18:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked disabled> <?php esc_html_e('Wednesday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="18:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked disabled> <?php esc_html_e('Thursday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="18:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" checked disabled> <?php esc_html_e('Friday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="09:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="18:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" disabled> <?php esc_html_e('Saturday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="10:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="15:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label><input type="checkbox" disabled> <?php esc_html_e('Sunday', 'rapls-ai-chatbot'); ?></label>
                                                </td>
                                                <td>
                                                    <input type="time" value="10:00" disabled style="width: 100px;"> -
                                                    <input type="time" value="15:00" disabled style="width: 100px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Outside Hours Message', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="2" disabled placeholder="<?php esc_attr_e('Thank you for your message. We are currently outside of business hours...', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Content Filters Tab -->
                        <div id="tab-content" class="tab-content">
                            <h2><?php esc_html_e('Content Filters', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Banned Words', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Enable banned words filter', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <br><br>
                                        <textarea class="large-text" rows="4" disabled placeholder="<?php esc_attr_e('One word per line', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('IP Blocking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Enable IP blocking', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <br><br>
                                        <textarea class="large-text" rows="4" disabled placeholder="<?php esc_attr_e('One IP per line (supports CIDR notation)', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Rate Limiting', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Enable enhanced rate limiting', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Two-tier throttling (burst + sustained) to prevent spam.', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <label><?php esc_html_e('Max messages per minute', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="5" disabled style="width: 80px;">
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Max messages per hour', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="30" disabled style="width: 80px;">
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Rate limit message', 'rapls-ai-chatbot'); ?></label>
                                        <input type="text" class="large-text" disabled placeholder="<?php esc_attr_e('Too many messages. Please wait a moment before sending again.', 'rapls-ai-chatbot'); ?>">
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
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
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Send webhook notifications', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook URL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="url" class="large-text" disabled placeholder="https://example.com/webhook">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook Secret', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" disabled>
                                        <p class="description"><?php esc_html_e('Used for signature verification (optional).', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"></th>
                                    <td>
                                        <button type="button" class="button" disabled><?php esc_html_e('Test Webhook', 'rapls-ai-chatbot'); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- AI Enhancement Tab -->
                        <div id="tab-ai" class="tab-content">
                            <h2><?php esc_html_e('AI Enhancement', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Feedback Buttons', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show feedback buttons (thumbs up/down) on bot messages', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Related Suggestions', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show related question suggestions after each response', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Autocomplete', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show autocomplete suggestions while typing', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Sentiment Analysis', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Analyze user emotions and adjust AI response tone', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, AI will detect user sentiment (positive, negative, neutral) and respond appropriately.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Memory', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Remember conversation context across sessions', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, AI will remember previous conversations with the same user.', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <label>
                                            <?php esc_html_e('Memory retention days:', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="30" style="width: 80px;" disabled>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Multimodal Support', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Allow users to upload and analyze images', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, users can upload images for AI analysis. Requires GPT-4 Vision, Claude 3, or Gemini.', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <label>
                                            <?php esc_html_e('Max image size (KB):', 'rapls-ai-chatbot'); ?>
                                            <input type="number" value="2048" style="width: 100px;" disabled>
                                        </label>
                                        <br><br>
                                        <label><?php esc_html_e('Allowed formats:', 'rapls-ai-chatbot'); ?></label>
                                        <br>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked disabled> JPG
                                        </label>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked disabled> PNG
                                        </label>
                                        <label style="margin-right: 15px;">
                                            <input type="checkbox" checked disabled> GIF
                                        </label>
                                        <label>
                                            <input type="checkbox" checked disabled> WebP
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
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
                                        <textarea class="large-text" rows="3" disabled placeholder="Analyze the sentiment..."></textarea>
                                        <p class="description"><?php esc_html_e('Prompt for detecting user sentiment. Use {message} as placeholder for user message.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Tone Adjustment Prompts', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('These prompts are appended to the system prompt based on detected sentiment.', 'rapls-ai-chatbot'); ?></p>
                                        <?php
                                        $tones = ['Frustrated', 'Confused', 'Urgent', 'Positive', 'Negative'];
                                        foreach ($tones as $tone): ?>
                                        <div style="margin-bottom: 8px;">
                                            <label style="display: block; font-weight: 600; margin-bottom: 4px;"><?php echo esc_html($tone); ?></label>
                                            <textarea class="large-text" rows="2" disabled></textarea>
                                        </div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Related Suggestions Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td><textarea class="large-text" rows="3" disabled></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Conversation Summary Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td><textarea class="large-text" rows="3" disabled></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Extraction Prompt', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3" disabled></textarea>
                                        <p class="description"><?php esc_html_e('Prompt for extracting user context from conversations. Use {conversation} as placeholder.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Context Memory Template', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <textarea class="large-text" rows="3" disabled></textarea>
                                        <p class="description"><?php esc_html_e('Template for injecting user context into system prompt. Placeholders: {summary}, {topics}, {preferences}, {last_date}', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Usage & Budget Tab -->
                        <div id="tab-budget" class="tab-content">
                            <h2><?php esc_html_e('Usage & Budget', 'rapls-ai-chatbot'); ?></h2>
                            <!-- Current Month Usage -->
                            <div style="background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <h3 style="margin-top: 0;"><?php esc_html_e('Current Month Usage', 'rapls-ai-chatbot'); ?></h3>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; font-weight: bold; color: #2271b1;">$12.50</div>
                                        <div style="color: #666; font-size: 13px;"><?php esc_html_e('Estimated Cost', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; font-weight: bold; color: #00a32a;">256</div>
                                        <div style="color: #666; font-size: 13px;"><?php esc_html_e('AI Responses', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 24px; font-weight: bold; color: #dba617;">128K</div>
                                        <div style="color: #666; font-size: 13px;"><?php esc_html_e('Total Tokens', 'rapls-ai-chatbot'); ?></div>
                                    </div>
                                </div>
                                <div style="background: #fff; border-radius: 4px; height: 24px; overflow: hidden;">
                                    <div style="background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; width: 25%; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; font-weight: bold;">25%</div>
                                </div>
                                <p style="font-size: 12px; color: #666; margin: 5px 0 0;">$12.50 / $50.00 <?php esc_html_e('budget used', 'rapls-ai-chatbot'); ?></p>
                            </div>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cost Alert', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox" checked disabled> <?php esc_html_e('Send alert when cost exceeds threshold', 'rapls-ai-chatbot'); ?></label>
                                        <br><br>
                                        <input type="number" value="30" disabled style="width: 100px;"> USD
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Budget Limit', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox" checked disabled> <?php esc_html_e('Block AI responses when budget exceeded', 'rapls-ai-chatbot'); ?></label>
                                        <br><br>
                                        <input type="number" value="50" disabled style="width: 100px;"> USD
                                        <br><br>
                                        <textarea class="large-text" rows="2" disabled placeholder="<?php esc_attr_e('Message shown when budget is exceeded...', 'rapls-ai-chatbot'); ?>"></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Monthly Report', 'rapls-ai-chatbot'); ?></th>
                                    <td><label><input type="checkbox" checked disabled> <?php esc_html_e('Send monthly usage report via email', 'rapls-ai-chatbot'); ?></label></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Badge Icon Tab -->
                        <div id="tab-badge" class="tab-content">
                            <h2><?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Icon Type', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio" checked disabled>
                                                <?php esc_html_e('Default', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio" disabled>
                                                <?php esc_html_e('Preset', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio" disabled>
                                                <?php esc_html_e('Custom Image', 'rapls-ai-chatbot'); ?>
                                            </label>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="radio" disabled>
                                                <?php esc_html_e('Emoji', 'rapls-ai-chatbot'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Offline Messages Tab -->
                        <div id="tab-offline" class="tab-content">
                            <h2><?php esc_html_e('Offline Messages', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Offline Messages', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Show contact form when outside business hours', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Title', 'rapls-ai-chatbot'); ?></th>
                                    <td><input type="text" class="regular-text" disabled placeholder="<?php esc_attr_e('We are currently offline', 'rapls-ai-chatbot'); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Form Description', 'rapls-ai-chatbot'); ?></th>
                                    <td><textarea class="large-text" rows="2" disabled placeholder="<?php esc_attr_e('Please leave a message and we will get back to you.', 'rapls-ai-chatbot'); ?>"></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Email Notification', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label><input type="checkbox" disabled> <?php esc_html_e('Send email notification for offline messages', 'rapls-ai-chatbot'); ?></label>
                                        <br><br>
                                        <input type="email" class="regular-text" disabled placeholder="admin@example.com">
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Conversion Tracking Tab -->
                        <div id="tab-conversion" class="tab-content">
                            <h2><?php esc_html_e('Conversion Tracking', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Conversion Tracking', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Track conversions from chatbot conversations', 'rapls-ai-chatbot'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Conversion Goals', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <p class="description"><?php esc_html_e('Define URL patterns that count as conversions (e.g., /thank-you, /order-complete).', 'rapls-ai-chatbot'); ?></p>
                                        <br>
                                        <textarea class="large-text" rows="3" disabled placeholder="/thank-you&#10;/order-complete&#10;/signup-success"></textarea>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Response Cache Tab -->
                        <div id="tab-cache" class="tab-content">
                            <h2><?php esc_html_e('Response Cache', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Response Cache', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" disabled>
                                            <?php esc_html_e('Cache AI responses to reduce API costs', 'rapls-ai-chatbot'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Similar questions will receive cached responses instead of calling the AI API.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cache TTL', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="number" value="7" disabled style="width: 80px;"> <?php esc_html_e('days', 'rapls-ai-chatbot'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cache Statistics', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-width: 400px;">
                                            <div style="background: #f0f6fc; border-radius: 6px; padding: 10px; text-align: center;">
                                                <div style="font-size: 20px; font-weight: bold;">24</div>
                                                <div style="font-size: 12px; color: #666;"><?php esc_html_e('Cached', 'rapls-ai-chatbot'); ?></div>
                                            </div>
                                            <div style="background: #edf7ed; border-radius: 6px; padding: 10px; text-align: center;">
                                                <div style="font-size: 20px; font-weight: bold;">8</div>
                                                <div style="font-size: 12px; color: #666;"><?php esc_html_e('Hits', 'rapls-ai-chatbot'); ?></div>
                                            </div>
                                            <div style="background: #fef8ee; border-radius: 6px; padding: 10px; text-align: center;">
                                                <div style="font-size: 20px; font-weight: bold;">33%</div>
                                                <div style="font-size: 12px; color: #666;"><?php esc_html_e('Hit Rate', 'rapls-ai-chatbot'); ?></div>
                                            </div>
                                        </div>
                                        <br>
                                        <button type="button" class="button" disabled><?php esc_html_e('Clear Cache', 'rapls-ai-chatbot'); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Save Settings', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>

                        <!-- Backup & Restore Tab -->
                        <div id="tab-maintenance" class="tab-content">
                            <h2><?php esc_html_e('Backup & Restore Settings', 'rapls-ai-chatbot'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Export Settings', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <button type="button" class="button" disabled><?php esc_html_e('Download Settings (JSON)', 'rapls-ai-chatbot'); ?></button>
                                        <p class="description"><?php esc_html_e('Export all plugin settings as a JSON file for backup or migration.', 'rapls-ai-chatbot'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?></th>
                                    <td>
                                        <input type="file" disabled accept=".json">
                                        <br><br>
                                        <button type="button" class="button" disabled><?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?></button>
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
                                        <input type="email" class="regular-text" disabled placeholder="your@email.com">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('License Key', 'rapls-ai-chatbot'); ?></label></th>
                                    <td>
                                        <input type="text" class="regular-text" disabled placeholder="RPLS-XXXX-XXXX-XXXX-XXXX" style="font-family: monospace;">
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('Activate License', 'rapls-ai-chatbot'); ?></button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pro Features List -->
            <div class="wpaic-pro-features-list">
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
                    <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Content Filters (Banned Words, IP/Country Blocking, Spam)', 'rapls-ai-chatbot'); ?></li>
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

        <style>
        .wpaic-pro-upgrade-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .wpaic-pro-upgrade-content {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #fff;
        }
        .wpaic-pro-upgrade-content .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
        }
        .wpaic-pro-upgrade-content div {
            flex: 1;
        }
        .wpaic-pro-upgrade-content strong {
            font-size: 16px;
        }
        .wpaic-pro-upgrade-content p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .wpaic-pro-upgrade-content .button {
            background: #fff;
            color: #667eea;
            border: none;
        }
        .wpaic-pro-upgrade-content .button:hover {
            background: #f0f0f0;
            color: #764ba2;
        }

        .wpaic-pro-preview-wrapper {
            position: relative;
            margin: 20px 0;
        }
        .wpaic-tab-groups-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 0;
            border-bottom: 2px solid #667eea;
            padding: 0;
        }
        .wpaic-tab-group {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            text-decoration: none;
            color: #50575e;
            font-weight: 500;
            cursor: pointer;
        }
        .wpaic-tab-group:hover {
            background: #e8e8e8;
            color: #1d2327;
        }
        .wpaic-tab-group-active {
            background: #667eea;
            color: #fff;
            border-color: #667eea;
        }
        .wpaic-tab-group-active:hover {
            background: #5a6fd6;
            color: #fff;
        }
        .wpaic-tab-group .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .wpaic-sub-tabs {
            display: flex;
            gap: 0;
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 0 10px;
        }
        .wpaic-sub-tab {
            padding: 8px 16px;
            text-decoration: none;
            color: #50575e;
            font-size: 13px;
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .wpaic-sub-tab:visited {
            color: #50575e;
        }
        .wpaic-sub-tab:hover {
            color: #1d2327;
            background: #eaeaea;
        }
        .wpaic-sub-tab:focus {
            color: #50575e;
            box-shadow: none;
            outline: none;
        }
        .wpaic-sub-tab-active,
        .wpaic-sub-tab-active:visited,
        .wpaic-sub-tab-active:focus,
        .wpaic-sub-tab-active:active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: 600;
            box-shadow: none;
            outline: none;
        }
        .wpaic-sub-tab-active:hover {
            color: #5a6fd6;
        }
        .wpaic-pro-preview .wpaic-settings-tabs .tab-content {
            display: none;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 20px;
        }
        .wpaic-pro-preview .wpaic-settings-tabs .tab-content.active {
            display: block;
        }
        .wpaic-pro-preview .tab-content {
            opacity: 0.6;
        }
        .wpaic-pro-preview input:disabled,
        .wpaic-pro-preview select:disabled,
        .wpaic-pro-preview textarea:disabled,
        .wpaic-pro-preview button:disabled {
            cursor: not-allowed;
        }

        .wpaic-pro-features-list {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px 30px;
            max-width: 600px;
        }
        .wpaic-pro-features-list h3 {
            margin-top: 0;
        }
        .wpaic-pro-features-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .wpaic-pro-features-list li {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .wpaic-pro-features-list .dashicons-yes {
            color: #00a32a;
        }

        .wpaic-schedule-table {
            border-collapse: collapse;
        }
        .wpaic-schedule-table tr {
            border-bottom: 1px solid #e0e0e0;
        }
        .wpaic-schedule-table tr:last-child {
            border-bottom: none;
        }
        .wpaic-schedule-table td {
            padding: 8px 0;
        }
        .wpaic-schedule-table label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Group tab switching
            $('.wpaic-tab-group').on('click', function(e) {
                e.preventDefault();
                var group = $(this).data('group');

                // Update group tabs
                $('.wpaic-tab-group').removeClass('wpaic-tab-group-active');
                $(this).addClass('wpaic-tab-group-active');

                // Show/hide sub-tabs
                $('.wpaic-sub-tabs').hide();
                $('.wpaic-sub-tabs[data-for="' + group + '"]').show();

                // Activate first sub-tab in group
                var $activeSubTab = $('.wpaic-sub-tabs[data-for="' + group + '"] .wpaic-sub-tab-active');
                if (!$activeSubTab.length) {
                    $activeSubTab = $('.wpaic-sub-tabs[data-for="' + group + '"] .wpaic-sub-tab:first');
                }
                $activeSubTab.trigger('click');
            });

            // Sub-tab switching
            $('.wpaic-sub-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).data('tab');

                // Update sub-tab active state
                $(this).closest('.wpaic-sub-tabs').find('.wpaic-sub-tab').removeClass('wpaic-sub-tab-active');
                $(this).addClass('wpaic-sub-tab-active');

                // Update tab content
                $('.wpaic-pro-preview .tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
            });
        });
        </script>
        <?php
    }

    /**
     * Reset all user sessions AJAX
     */
    public function ajax_reset_sessions(): void {
        check_ajax_referer('wpaic_admin_nonce', 'nonce');

        if (!current_user_can(self::get_manage_cap())) {
            wp_send_json_error(__('Permission denied.', 'rapls-ai-chatbot'));
        }

        // Increment session version - this will invalidate all client sessions
        $current_version = get_option('wpaic_session_version', 1);
        $new_version = $current_version + 1;
        update_option('wpaic_session_version', $new_version);

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

        $class = $is_current ? 'wpaic-sorted' : 'wpaic-sortable';
        $indicator = '';
        if ($is_current) {
            $indicator = ' <span class="wpaic-sort-indicator">' . ($current_order === 'ASC' ? '&#9650;' : '&#9660;') . '</span>';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . $indicator . '</a>';
    }
}
