<?php
/**
 * Chatbot widget class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Chatbot_Widget {

    /**
     * Whether currently rendering an inline shortcode
     */
    private bool $is_inline = false;

    /**
     * Render shortcode [rapls_chatbot]
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode($atts): string {
        // Don't render in admin
        if (is_admin()) {
            return '';
        }

        $atts = shortcode_atts([
            'height' => '500px',
            'theme'  => '',
            'bot'    => '',
        ], $atts, 'rapls_chatbot');

        $this->is_inline = true;

        // Ensure scripts/styles are enqueued (shortcode may appear before wp_enqueue_scripts)
        if (!wp_script_is('wpaic-chatbot', 'enqueued')) {
            $this->enqueue_styles();
            $this->enqueue_scripts();
        }

        // Tell JS to use inline mode
        wp_add_inline_script('wpaic-chatbot',
            'if(window.wpAiChatbotConfig) wpAiChatbotConfig.inlineMode = true;',
            'before'
        );

        $settings = get_option('wpaic_settings', []);
        $bot_name = esc_attr($settings['bot_name'] ?? 'Assistant');
        $bot_avatar_raw = $settings['bot_avatar'] ?? '🤖';
        $bot_avatar_is_image = filter_var($bot_avatar_raw, FILTER_VALIDATE_URL) || preg_match('/^\//', $bot_avatar_raw) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $bot_avatar_raw);
        $bot_avatar = $bot_avatar_is_image ? esc_url($bot_avatar_raw) : esc_html($bot_avatar_raw);

        $widget_theme = $settings['widget_theme'] ?? 'default';
        $free_themes = ['default', 'simple', 'classic', 'light', 'minimal', 'flat'];
        $is_pro_active = get_option('wpaic_pro_active');

        if (!$is_pro_active && !in_array($widget_theme, $free_themes)) {
            $widget_theme = 'default';
        }

        // Allow shortcode theme override
        if (!empty($atts['theme'])) {
            $override = sanitize_key($atts['theme']);
            if ($is_pro_active || in_array($override, $free_themes)) {
                $widget_theme = $override;
            }
        }

        $theme_class = $widget_theme !== 'default' ? 'theme-' . $widget_theme : '';
        if ($is_pro_active && !empty($settings['dark_mode'])) {
            $theme_class .= ' dark-mode';
        }
        $theme_class = trim($theme_class);

        $badge_position = $settings['badge_position'] ?? 'bottom-right';
        $pro_features = $settings['pro_features'] ?? [];
        $badge_icon_type = $pro_features['badge_icon_type'] ?? 'default';
        $badge_icon_preset = $pro_features['badge_icon_preset'] ?? '';
        $badge_icon_image = $pro_features['badge_icon_image'] ?? '';
        $badge_icon_emoji = $pro_features['badge_icon_emoji'] ?? '';

        // Multi-bot: shortcode bot attribute overrides widget settings (Pro)
        $shortcode_bot_id = '';
        if (!empty($atts['bot'])) {
            $shortcode_bot_id = sanitize_key($atts['bot']);
            $sc_bot_config = WPAIC_Pro_Features::get_instance()->resolve_bot_config($shortcode_bot_id);
            if ($sc_bot_config) {
                if (!empty($sc_bot_config['name'])) {
                    $bot_name = esc_attr($sc_bot_config['name']);
                }
                if (!empty($sc_bot_config['avatar'])) {
                    $avatar_raw = $sc_bot_config['avatar'];
                    $bot_avatar_is_image = filter_var($avatar_raw, FILTER_VALIDATE_URL) || preg_match('/^\//', $avatar_raw) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $avatar_raw);
                    $bot_avatar = $bot_avatar_is_image ? esc_url($avatar_raw) : esc_html($avatar_raw);
                }
                if (!empty($sc_bot_config['theme'])) {
                    $override_theme = sanitize_key($sc_bot_config['theme']);
                    if ($is_pro_active || in_array($override_theme, $free_themes)) {
                        $widget_theme = $override_theme;
                        $theme_class = $widget_theme !== 'default' ? 'theme-' . $widget_theme : '';
                        if ($is_pro_active && !empty($settings['dark_mode'])) {
                            $theme_class .= ' dark-mode';
                        }
                        $theme_class = trim($theme_class);
                    }
                }
                // Bot-specific primary color override
                if (!empty($sc_bot_config['primary_color'])) {
                    $primary_color = sanitize_hex_color($sc_bot_config['primary_color']);
                }
                // Bot-specific badge override
                if (!empty($sc_bot_config['badge_icon_type']) && $sc_bot_config['badge_icon_type'] !== 'default') {
                    $settings['pro_features']['badge_icon_type'] = $sc_bot_config['badge_icon_type'];
                    $settings['pro_features']['badge_icon_image'] = $sc_bot_config['badge_icon_image'] ?? '';
                    $settings['pro_features']['badge_icon_emoji'] = $sc_bot_config['badge_icon_emoji'] ?? '';
                }
                // Set bot_id in JS config via inline script
                wp_add_inline_script('wpaic-chatbot',
                    'if(window.wpAiChatbotConfig){wpAiChatbotConfig.bot_id=' . wp_json_encode($shortcode_bot_id) . ';' .
                    (!empty($sc_bot_config['welcome_message']) ? 'wpAiChatbotConfig.welcome_message=' . wp_json_encode($sc_bot_config['welcome_message']) . ';' : '') .
                    (!empty($sc_bot_config['name']) ? 'wpAiChatbotConfig.bot_name=' . wp_json_encode($sc_bot_config['name']) . ';' : '') .
                    '}',
                    'before'
                );
            }
        }

        // Sanitize height attribute
        $height = esc_attr($atts['height']);
        if (preg_match('/^\d+$/', $height)) {
            $height .= 'px';
        }

        ob_start();
        echo '<div class="wpaic-inline" style="height:' . esc_attr($height) . '">';
        include WPAIC_PLUGIN_DIR . 'templates/frontend/chatbot-widget.php';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles(): void {
        if (!$this->is_inline && !$this->should_display()) {
            return;
        }

        wp_enqueue_style(
            'wpaic-chatbot',
            WPAIC_PLUGIN_URL . 'assets/css/chatbot.css',
            [],
            WPAIC_VERSION
        );

        // Apply custom colors
        $settings = get_option('wpaic_settings', []);
        $primary_color = $settings['primary_color'] ?? '#007bff';
        if (empty($primary_color) || !preg_match('/^#[0-9a-fA-F]{3,6}$/', $primary_color)) {
            $primary_color = '#007bff';
        }
        $badge_position = $settings['badge_position'] ?? 'bottom-right';
        $margin_h = absint($settings['badge_margin_right'] ?? 20);
        $margin_v = absint($settings['badge_margin_bottom'] ?? 20);

        // Build position CSS based on badge_position setting
        switch ($badge_position) {
            case 'bottom-left':
                $position_css = "left: {$margin_h}px; bottom: {$margin_v}px; right: auto;";
                break;
            case 'top-right':
                $position_css = "right: {$margin_h}px; top: {$margin_v}px; bottom: auto;";
                break;
            case 'top-left':
                $position_css = "left: {$margin_h}px; top: {$margin_v}px; right: auto; bottom: auto;";
                break;
            default: // bottom-right
                $position_css = "right: {$margin_h}px; bottom: {$margin_v}px;";
                break;
        }

        $custom_css = "
            :root {
                --wpaic-primary: {$primary_color};
                --wpaic-primary-dark: " . $this->darken_color($primary_color, 20) . ";
            }
            .wp-ai-chatbot {
                {$position_css}
            }
        ";

        // Powered by footer is now conditionally rendered in the template (not hidden via CSS)
        $pro_settings = $settings['pro_features'] ?? [];
        $pro = WPAIC_Pro_Features::get_instance();

        // White label: custom CSS (strip dangerous strings to prevent style breakout)
        if ($pro->is_pro() && !empty($pro_settings['custom_css'])) {
            $safe_css = $pro_settings['custom_css'];
            $safe_css = preg_replace('/<\/?style[^>]*>/i', '', $safe_css);
            $safe_css = preg_replace('/<\/?script[^>]*>/i', '', $safe_css);
            $safe_css = str_replace('expression(', '', $safe_css);
            $safe_css = preg_replace('/url\s*\(\s*["\']?\s*javascript:/i', 'url(about:blank', $safe_css);
            $custom_css .= "\n" . $safe_css;
        }

        wp_add_inline_style('wpaic-chatbot', $custom_css);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts(): void {
        if (!$this->is_inline && !$this->should_display()) {
            return;
        }

        $settings = get_option('wpaic_settings', []);

        // Load reCAPTCHA script (only if enabled and not using existing)
        $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
        $recaptcha_site_key = $settings['recaptcha_site_key'] ?? '';
        $recaptcha_use_existing = !empty($settings['recaptcha_use_existing']);

        if ($recaptcha_enabled && !empty($recaptcha_site_key) && !$recaptcha_use_existing) {
            // Check if reCAPTCHA is already loaded (by us or another plugin)
            if (!wp_script_is('wpaic-recaptcha', 'enqueued') &&
                !wp_script_is('google-recaptcha', 'enqueued') && !wp_script_is('google-recaptcha', 'registered')) {
                wp_enqueue_script(
                    'wpaic-recaptcha',
                    'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
                    [],
                    '3.0', // reCAPTCHA v3
                    true
                );
            }
        }

        $chatbot_js_ver = WPAIC_VERSION . '.' . filemtime(WPAIC_PLUGIN_DIR . 'assets/js/chatbot.js');
        wp_enqueue_script(
            'wpaic-chatbot',
            WPAIC_PLUGIN_URL . 'assets/js/chatbot.js',
            [],
            $chatbot_js_ver,
            true
        );

        $bot_avatar = $settings['bot_avatar'] ?? '🤖';
        $bot_avatar_is_image = filter_var($bot_avatar, FILTER_VALIDATE_URL) || preg_match('/^\//', $bot_avatar) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $bot_avatar);

        // Get pro_features settings
        $pro_features = $settings['pro_features'] ?? [];
        $show_regenerate = $pro_features['show_regenerate_button'] ?? true;
        $related_suggestions = !empty($pro_features['related_suggestions_enabled']);
        $autocomplete = !empty($pro_features['autocomplete_enabled']);
        $multimodal_enabled = !empty($pro_features['multimodal_enabled']);
        $multimodal_max_size = (int) ($pro_features['multimodal_max_size'] ?? 2048);

        wp_localize_script('wpaic-chatbot', 'wpAiChatbotConfig', [
            'restUrl'             => rest_url('wp-ai-chatbot/v1/'),
            'api_base'            => rest_url('wp-ai-chatbot/v1'),
            'nonce'               => wp_create_nonce('wp_rest'),
            'bot_id'              => 'default',
            'bot_name'            => $settings['bot_name'] ?? 'Assistant',
            'bot_avatar'          => $bot_avatar,
            'bot_avatar_is_image' => $bot_avatar_is_image,
            'welcome_message'     => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'welcome_messages'    => !empty($settings['welcome_messages']) ? $settings['welcome_messages'] : new \stdClass(),
            'response_language'   => $settings['response_language'] ?? '',
            'recaptcha_enabled'   => $recaptcha_enabled,
            'recaptcha_site_key'  => $recaptcha_site_key,
            'is_pro'              => (bool) get_option('wpaic_pro_active'),
            'session_version'     => get_option('wpaic_session_version', 1),
            'markdown_enabled'    => $settings['markdown_enabled'] ?? true,
            'show_feedback'       => !empty($settings['show_feedback_buttons']),
            'show_regenerate'     => (bool) $show_regenerate,
            'related_suggestions' => $related_suggestions,
            'autocomplete'        => $autocomplete,
            'multimodal_enabled'  => $multimodal_enabled,
            'multimodal_max_size' => $multimodal_max_size,
            'file_upload_enabled' => !empty($pro_features['file_upload_enabled']),
            'file_upload_max_size' => (int) ($pro_features['file_upload_max_size'] ?? 5120),
            'file_upload_types'   => $pro_features['file_upload_types'] ?? ['pdf', 'doc', 'docx', 'txt', 'csv'],
            'screenshot_enabled'  => !empty($pro_features['screen_sharing_enabled']),
            'voice_input_enabled' => !empty($pro_features['voice_input_enabled']),
            'tts_enabled'         => !empty($pro_features['tts_enabled']),
            'fullscreen_mode'     => !empty($pro_features['fullscreen_mode']),
            'welcome_screen_enabled' => !empty($pro_features['welcome_screen_enabled']),
            'welcome_screen_title'   => $pro_features['welcome_screen_title'] ?? '',
            'welcome_screen_message' => $pro_features['welcome_screen_message'] ?? '',
            'welcome_screen_buttons' => !empty($pro_features['welcome_screen_buttons']) ? $pro_features['welcome_screen_buttons'] : [],
            'response_delay_enabled' => !empty($pro_features['response_delay_enabled']),
            'response_delay_ms'      => (int) ($pro_features['response_delay_ms'] ?? 500),
            'notification_sound_enabled' => !empty($pro_features['notification_sound_enabled']),
            'tooltips_enabled'     => !empty($pro_features['tooltips_enabled']),
            'conversion_tracking'  => !empty($pro_features['conversion_tracking_enabled']),
            'conversion_goals'     => !empty($pro_features['conversion_tracking_enabled']) ? ($pro_features['conversion_goals'] ?? []) : [],
            'offline_message'      => $this->get_offline_config($pro_features),
            'save_history'         => !empty($settings['save_history']),
            'context_memory'       => !empty($pro_features['context_memory_enabled']),
            'consent_strict_mode'  => !empty($settings['consent_strict_mode']),
            // wpaic_frontend_debug filter: always include a capability check in callbacks.
            // Logged-in guard prevents accidental exposure to anonymous visitors.
            // Minimum cap (default edit_posts) required even when filter overrides,
            // to prevent misuse granting debug to all logged-in subscribers.
            // Use wpaic_frontend_debug_min_cap filter to change the minimum capability.
            'debug'                => is_user_logged_in() && current_user_can($this->get_debug_min_cap()) && (bool) apply_filters('wpaic_frontend_debug', defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')),
            // Lightweight admin flag (no WP_DEBUG requirement) for dev-aid warnings
            // like unmapped error_code console.warn — works on production too.
            'is_plugin_admin'      => is_user_logged_in() && current_user_can(WPAIC_Admin::get_manage_cap()),
            'strings'              => [
                'error_occurred'         => __('An error occurred.', 'rapls-ai-chatbot'),
                'error_rate_limit'       => __('Too many requests. Please try again in a moment.', 'rapls-ai-chatbot'),
                'error_unavailable'      => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                'error_server'           => __('A temporary error occurred. Please try again later, or contact the site administrator.', 'rapls-ai-chatbot'),
                // error_code → user-facing message map (single source of truth for JS).
                // When adding new error_codes to REST responses, add the mapping here.
                // JS reads this map via config.strings.error_code_messages.
                'error_code_messages'    => [
                    'session_expired'        => __('Your session has expired. Please reload the page.', 'rapls-ai-chatbot'),
                    'pro_required'           => __('This feature requires the Pro version.', 'rapls-ai-chatbot'),
                    'timing_failed'          => __('Please wait a moment and try again.', 'rapls-ai-chatbot'),
                    'rate_limited'           => __('Too many requests. Please try again in a moment.', 'rapls-ai-chatbot'),
                    'recaptcha_required'     => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                    'recaptcha_misconfigured' => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                    'origin_mismatch'        => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                    'honeypot_triggered'     => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                ],
                'recaptcha_loading'      => __('Security verification loading. Please try again in a moment.', 'rapls-ai-chatbot'),
                'sources_title'          => __('Reference pages:', 'rapls-ai-chatbot'),
                'suggestions_title'      => __('You might also ask:', 'rapls-ai-chatbot'),
                'good_response'          => __('Good response', 'rapls-ai-chatbot'),
                'bad_response'           => __('Bad response', 'rapls-ai-chatbot'),
                'regenerate'             => __('Regenerate response', 'rapls-ai-chatbot'),
                'server_error'           => __('A server error occurred.', 'rapls-ai-chatbot'),
                'send_failed'            => __('Failed to send.', 'rapls-ai-chatbot'),
                'send_failed_retry'      => __('Failed to send. Please try again.', 'rapls-ai-chatbot'),
                'sending'                => __('Sending...', 'rapls-ai-chatbot'),
                'processing'             => __('Processing...', 'rapls-ai-chatbot'),
                'message_sent'           => __('Message sent!', 'rapls-ai-chatbot'),
                'required_fields'        => __('Please fill in all required fields.', 'rapls-ai-chatbot'),
                'start_chat'             => __('Start chat', 'rapls-ai-chatbot'),
                /* translators: %s: maximum image size in KB */
                'image_too_large'        => __('Image is too large. Please select an image under %sKB.', 'rapls-ai-chatbot'),
                'image_invalid_format'   => __('Unsupported image format. Please select JPEG, PNG, GIF, or WebP.', 'rapls-ai-chatbot'),
                'offline_name'           => __('Name', 'rapls-ai-chatbot'),
                'offline_email'          => __('Email', 'rapls-ai-chatbot'),
                'offline_message'        => __('Message', 'rapls-ai-chatbot'),
                'offline_send'           => __('Send Message', 'rapls-ai-chatbot'),
                'offline_reload_request' => __('Could not complete the request. Please reload the page and try again.', 'rapls-ai-chatbot'),
                'sentiment_frustrated'   => __('Frustrated', 'rapls-ai-chatbot'),
                'sentiment_confused'     => __('Confused', 'rapls-ai-chatbot'),
                'sentiment_urgent'       => __('Urgent', 'rapls-ai-chatbot'),
                'sentiment_positive'     => __('Positive', 'rapls-ai-chatbot'),
                'sentiment_negative'     => __('Negative', 'rapls-ai-chatbot'),
                'out_of_stock'           => __('Out of stock', 'rapls-ai-chatbot'),
                'handoff_waiting'        => __('Waiting for support representative...', 'rapls-ai-chatbot'),
                'handoff_pending'        => __('A support representative has been notified. Please wait...', 'rapls-ai-chatbot'),
                'handoff_active'         => __('Connected with support', 'rapls-ai-chatbot'),
                'handoff_resolved'       => __('Support session ended. You are now chatting with AI again.', 'rapls-ai-chatbot'),
                'handoff_cancel'         => __('Back to AI', 'rapls-ai-chatbot'),
                'handoff_cancelled'      => __('Returned to AI chat.', 'rapls-ai-chatbot'),
                'operator_label'         => __('Support', 'rapls-ai-chatbot'),
            ],
        ]);
    }

    /**
     * Render widget
     */
    public function render_widget(): void {
        // Don't render floating widget when inline shortcode was used
        if ($this->is_inline) {
            return;
        }

        if (!$this->should_display()) {
            return;
        }

        $settings = get_option('wpaic_settings', []);
        $bot_name = esc_attr($settings['bot_name'] ?? 'Assistant');
        $bot_avatar_raw = $settings['bot_avatar'] ?? '🤖';
        $bot_avatar_is_image = filter_var($bot_avatar_raw, FILTER_VALIDATE_URL) || preg_match('/^\//', $bot_avatar_raw) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $bot_avatar_raw);
        $bot_avatar = $bot_avatar_is_image ? esc_url($bot_avatar_raw) : esc_html($bot_avatar_raw);

        // Get widget theme
        $widget_theme = $settings['widget_theme'] ?? 'default';
        $free_themes = ['default', 'simple', 'classic', 'light', 'minimal', 'flat'];
        $is_pro_active = get_option('wpaic_pro_active');

        // Only allow free themes if Pro is not active
        if (!$is_pro_active && !in_array($widget_theme, $free_themes)) {
            $widget_theme = 'default';
        }

        $theme_class = $widget_theme !== 'default' ? 'theme-' . $widget_theme : '';

        // Dark mode (Pro only)
        if ($is_pro_active && !empty($settings['dark_mode'])) {
            $theme_class .= ' dark-mode';
        }

        $theme_class = trim($theme_class);

        // Badge icon settings
        $badge_position = $settings['badge_position'] ?? 'bottom-right';
        $pro_features = $settings['pro_features'] ?? [];
        $badge_icon_type = $pro_features['badge_icon_type'] ?? 'default';
        $badge_icon_preset = $pro_features['badge_icon_preset'] ?? '';
        $badge_icon_image = $pro_features['badge_icon_image'] ?? '';
        $badge_icon_emoji = $pro_features['badge_icon_emoji'] ?? '';

        // Multi-bot: check page rules for bot assignment (Pro)
        $page_id = get_queried_object_id();
        if ($page_id) {
            $page_bot_id = WPAIC_Pro_Features::get_instance()->get_bot_for_page($page_id);
            if ($page_bot_id !== 'default') {
                $page_bot_config = WPAIC_Pro_Features::get_instance()->resolve_bot_config($page_bot_id);
                if ($page_bot_config) {
                    if (!empty($page_bot_config['name'])) {
                        $bot_name = esc_attr($page_bot_config['name']);
                    }
                    if (!empty($page_bot_config['avatar'])) {
                        $avatar_raw = $page_bot_config['avatar'];
                        $bot_avatar_is_image = filter_var($avatar_raw, FILTER_VALIDATE_URL) || preg_match('/^\//', $avatar_raw) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $avatar_raw);
                        $bot_avatar = $bot_avatar_is_image ? esc_url($avatar_raw) : esc_html($avatar_raw);
                    }
                    if (!empty($page_bot_config['theme'])) {
                        $override_theme = sanitize_key($page_bot_config['theme']);
                        if ($is_pro_active || in_array($override_theme, $free_themes)) {
                            $widget_theme = $override_theme;
                            $theme_class = $widget_theme !== 'default' ? 'theme-' . $widget_theme : '';
                            if ($is_pro_active && !empty($settings['dark_mode'])) {
                                $theme_class .= ' dark-mode';
                            }
                            $theme_class = trim($theme_class);
                        }
                    }
                    // Bot-specific primary color override
                    if (!empty($page_bot_config['primary_color'])) {
                        $primary_color = sanitize_hex_color($page_bot_config['primary_color']);
                    }
                    // Bot-specific badge override
                    if (!empty($page_bot_config['badge_icon_type']) && $page_bot_config['badge_icon_type'] !== 'default') {
                        $settings['pro_features']['badge_icon_type'] = $page_bot_config['badge_icon_type'];
                        $settings['pro_features']['badge_icon_image'] = $page_bot_config['badge_icon_image'] ?? '';
                        $settings['pro_features']['badge_icon_emoji'] = $page_bot_config['badge_icon_emoji'] ?? '';
                    }
                    // Override JS config for page-rule bot
                    wp_add_inline_script('wpaic-chatbot',
                        'if(window.wpAiChatbotConfig){wpAiChatbotConfig.bot_id=' . wp_json_encode($page_bot_id) . ';' .
                        (!empty($page_bot_config['welcome_message']) ? 'wpAiChatbotConfig.welcome_message=' . wp_json_encode($page_bot_config['welcome_message']) . ';' : '') .
                        (!empty($page_bot_config['name']) ? 'wpAiChatbotConfig.bot_name=' . wp_json_encode($page_bot_config['name']) . ';' : '') .
                        '}',
                        'before'
                    );
                }
            }
        }

        include WPAIC_PLUGIN_DIR . 'templates/frontend/chatbot-widget.php';
    }

    /**
     * Render embed page for cross-site iframe embedding.
     * Outputs minimal HTML (no theme) with chatbot only, then exits.
     */
    public function maybe_render_embed_page(): void {
        if (!get_query_var('wpaic_embed')) {
            return;
        }

        // Remove X-Frame-Options to allow iframe embedding and use CSP instead
        header_remove('X-Frame-Options');

        /**
         * Filter the allowed origins for iframe embedding.
         *
         * @param string[] $origins List of allowed origin URLs, or ['*'] for any.
         */
        $allowed = apply_filters('wpaic_embed_allowed_origins', ['*']);
        $origins = implode(' ', array_map(function ($o) {
            return $o === '*' ? '*' : esc_url($o);
        }, $allowed));
        header('Content-Security-Policy: frame-ancestors ' . $origins);

        $settings = get_option('wpaic_settings', []);
        $bot_name = esc_attr($settings['bot_name'] ?? 'Assistant');
        $bot_avatar_raw = $settings['bot_avatar'] ?? "\xF0\x9F\xA4\x96";
        $bot_avatar_is_image = filter_var($bot_avatar_raw, FILTER_VALIDATE_URL) || preg_match('/^\//', $bot_avatar_raw) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $bot_avatar_raw);
        $bot_avatar = $bot_avatar_is_image ? esc_url($bot_avatar_raw) : esc_html($bot_avatar_raw);

        $widget_theme = $settings['widget_theme'] ?? 'default';
        $free_themes = ['default', 'simple', 'classic', 'light', 'minimal', 'flat'];
        $is_pro_active = get_option('wpaic_pro_active');

        if (!$is_pro_active && !in_array($widget_theme, $free_themes)) {
            $widget_theme = 'default';
        }

        $theme_class = $widget_theme !== 'default' ? 'theme-' . $widget_theme : '';
        if ($is_pro_active && !empty($settings['dark_mode'])) {
            $theme_class .= ' dark-mode';
        }
        $theme_class = trim($theme_class);

        $badge_position = $settings['badge_position'] ?? 'bottom-right';
        $pro_features = $settings['pro_features'] ?? [];
        $badge_icon_type = $pro_features['badge_icon_type'] ?? 'default';
        $badge_icon_preset = $pro_features['badge_icon_preset'] ?? '';
        $badge_icon_image = $pro_features['badge_icon_image'] ?? '';
        $badge_icon_emoji = $pro_features['badge_icon_emoji'] ?? '';

        // Enqueue styles/scripts (needed for wp_head output)
        $this->is_inline = true;
        $this->enqueue_styles();
        $this->enqueue_scripts();

        // Add embed-specific inline config
        wp_add_inline_script('wpaic-chatbot',
            'if(window.wpAiChatbotConfig){wpAiChatbotConfig.inlineMode=true;wpAiChatbotConfig.embedMode=true;}',
            'before'
        );

        // Add embed-specific CSS
        wp_add_inline_style('wpaic-chatbot', '
            html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: transparent; }
            .wpaic-inline { height: 100vh; width: 100%; }
            .wpaic-inline .wp-ai-chatbot { position: relative; width: 100%; height: 100%; }
            .wpaic-inline .chatbot-badge { display: none !important; }
            .wpaic-inline .chatbot-window { display: flex !important; position: relative; width: 100%; height: 100%;
                border-radius: 0; box-shadow: none; max-height: none; }
        ');

        // Output minimal HTML
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
</head>
<body class="wpaic-embed-body">
<div class="wpaic-inline wpaic-embed" style="height:100vh">
<?php include WPAIC_PLUGIN_DIR . 'templates/frontend/chatbot-widget.php'; ?>
</div>
<?php wp_footer(); ?>
<script>
(function(){
    // Notify parent frame that embed is ready
    if(window.parent!==window){
        window.parent.postMessage({type:'wpaic:ready'},'*');
    }
    // Listen for close button and notify parent
    document.addEventListener('click',function(e){
        if(e.target.closest('.chatbot-close')){
            e.preventDefault();
            if(window.parent!==window){
                window.parent.postMessage({type:'wpaic:close'},'*');
            }
        }
    });
})();
</script>
</body>
</html><?php
        exit;
    }

    /**
     * Check if widget should be displayed
     */
    private function should_display(): bool {
        // Don't display in admin
        if (is_admin()) {
            return false;
        }

        $settings = get_option('wpaic_settings', []);

        // Allow developers to override via filter
        $enabled = apply_filters('wpaic_chatbot_enabled', true);
        if (!$enabled) {
            return false;
        }

        // Mobile display setting
        if (wp_is_mobile() && empty($settings['show_on_mobile'])) {
            return false;
        }

        $current_page_id = get_the_ID();

        // Include IDs whitelist (if set, overrides page type settings)
        $include_ids = $this->parse_id_list($settings['badge_include_ids'] ?? '');
        if (!empty($include_ids)) {
            return $current_page_id && in_array($current_page_id, $include_ids, true);
        }

        // Page type display settings (default to true if key not yet saved)
        if ((is_front_page() || is_home()) && !($settings['badge_show_on_home'] ?? true)) {
            return false;
        }
        if (is_singular('post') && !($settings['badge_show_on_posts'] ?? true)) {
            return false;
        }
        if (is_page() && !is_front_page() && !($settings['badge_show_on_pages'] ?? true)) {
            return false;
        }
        if ((is_archive() || is_search()) && !($settings['badge_show_on_archives'] ?? true)) {
            return false;
        }

        // Exclude IDs blacklist
        $exclude_ids = $this->parse_id_list($settings['badge_exclude_ids'] ?? '');
        if (!empty($exclude_ids) && $current_page_id && in_array($current_page_id, $exclude_ids, true)) {
            return false;
        }

        // Legacy excluded pages (dropdown-based)
        if (!empty($settings['excluded_pages'])) {
            if ($current_page_id && in_array($current_page_id, $settings['excluded_pages'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse comma-separated ID list into array of integers
     */
    private function parse_id_list(string $ids): array {
        if (empty($ids)) {
            return [];
        }
        return array_filter(array_map('absint', explode(',', $ids)));
    }

    /**
     * Get offline message configuration for frontend
     */
    private function get_offline_config(array $pro_features): array {
        if (empty($pro_features['offline_message_enabled'])) {
            return ['enabled' => false];
        }

        // Check if currently outside business hours
        $pro = WPAIC_Pro_Features::get_instance();
        $is_offline = !$pro->is_within_business_hours();

        if (!$is_offline) {
            return ['enabled' => false];
        }

        return [
            'enabled'     => true,
            'title'       => $pro_features['offline_form_title'] ?? __('We are currently offline', 'rapls-ai-chatbot'),
            'description' => $pro_features['offline_form_description'] ?? __('Please leave a message and we will get back to you.', 'rapls-ai-chatbot'),
        ];
    }

    /**
     * Get the minimum capability required for frontend debug mode.
     * Defaults to 'edit_posts'; filterable via wpaic_frontend_debug_min_cap.
     * Falls back to default if filter returns non-string or empty value.
     */
    private function get_debug_min_cap(): string {
        $default = 'edit_posts';
        $cap = apply_filters('wpaic_frontend_debug_min_cap', $default);
        return (is_string($cap) && $cap !== '') ? $cap : $default;
    }

    /**
     * Darken color
     */
    private function darken_color(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

/**
 * Get badge preset SVG markup
 */
function wpaic_get_badge_preset_svg(string $preset): string {
    $presets = [
        // Communication
        'headset' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
        'message' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><path d="M7 9h10v2H7zm0-3h10v2H7z"/></svg>',
        'chat-dots' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><circle cx="8" cy="10" r="1.5"/><circle cx="12" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>',
        'chat-bubble' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c-4.97 0-9 3.13-9 7 0 2.38 1.56 4.47 3.93 5.7L5 20l4.53-2.1c.78.15 1.61.1 2.47.1 4.97 0 9-3.13 9-7s-4.03-7-9-7z"/></svg>',
        'phone' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>',
        'mail' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
        // Help & Support
        'question' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
        'help-circle' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2c0-3.25 3-3 3-5 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 2.5-3 2.75-3 5z"/></svg>',
        'info' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
        // AI & Tech
        'robot' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7v1H3v-1a7 7 0 017-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 012-2zM7.5 13A1.5 1.5 0 006 14.5 1.5 1.5 0 007.5 16 1.5 1.5 0 009 14.5 1.5 1.5 0 007.5 13zm9 0a1.5 1.5 0 00-1.5 1.5 1.5 1.5 0 001.5 1.5 1.5 1.5 0 001.5-1.5 1.5 1.5 0 00-1.5-1.5zM5 18v2h14v-2H5z"/></svg>',
        'sparkle' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>',
        'lightning' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>',
        'brain' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 00-5.64 11.14A5 5 0 008 22h8a5 5 0 001.64-8.86A7 7 0 0012 2zm0 2a5 5 0 014.33 7.5l-.58 1H13v-2a1 1 0 10-2 0v2H8.25l-.58-1A5 5 0 0112 4zm-4 14a3 3 0 01-2.83-2h13.66A3 3 0 0116 18H8z"/></svg>',
        'magic-wand' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 5.6L5 7l1.4-2.5L5 2l2.5 1.4L10 2 8.6 4.5 10 7 7.5 5.6zm12 9.8L22 14l-1.4 2.5L22 19l-2.5-1.4L17 19l1.4-2.5L17 14l2.5 1.4zM22 2l-2.5 1.4L17 2l1.4 2.5L17 7l2.5-1.4L22 7l-1.4-2.5L22 2zM14.37 5.29l4.34 4.34-11.71 11.71-4.34-4.34 11.71-11.71zm-2.83 2.83L4.83 14.83l1.41 1.41 6.71-6.71-1.41-1.41z"/></svg>',
        // Shapes & Symbols
        'heart' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
        'thumbs-up' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>',
        'smile' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>',
        'globe' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>',
        'shield' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>',
        'gift' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.11-.31.18-.65.18-1a3 3 0 00-3-3c-1.05 0-1.95.56-2.47 1.37L12 4.14l-.53-.77A2.99 2.99 0 009 2a3 3 0 00-3 3c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 12 7.4l3.38 4.6L17 10.83 14.92 8H20v6z"/></svg>',
        'store' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>',
        'cart' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>',
        // Communication (additional)
        'megaphone' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M18 11v2h4v-2h-4zm-2 6.61c.96.71 2.21 1.65 3.2 2.39.4-.53.8-1.07 1.2-1.6-.99-.74-2.24-1.68-3.2-2.4-.4.54-.8 1.08-1.2 1.61zM20.4 5.6c-.4-.53-.8-1.07-1.2-1.6-.99.74-2.24 1.68-3.2 2.4.4.53.8 1.07 1.2 1.6.96-.72 2.21-1.65 3.2-2.4zM4 9c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2h1l5 3V6L5 9H4zm11.5 3c0-1.33-.58-2.53-1.5-3.35v6.69c.92-.81 1.5-2.01 1.5-3.34z"/></svg>',
        'send' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>',
        'forum' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/></svg>',
        // AI & Tech (additional)
        'chip' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h12v2h2V4c0-1.1-.9-2-2-2H6C4.9 2 4 2.9 4 4v2h2V4zm0 16h12v-2h2v2c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2v-2h2v2zM20 8v8h2V8h-2zM2 8v8h2V8H2zm7-1h6v2h-6V7zm0 4h6v2h-6v-2zm0 4h4v2H9v-2z"/></svg>',
        'auto-fix' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 5.6L10 7 8.6 4.5 10 2 7.5 3.4 5 2l1.4 2.5L5 7l2.5-1.4zm12 9.8L17 14l1.4 2.5L17 19l2.5-1.4L22 19l-1.4-2.5L22 14l-2.5 1.4zM22 2l-2.5 1.4L17 2l1.4 2.5L17 7l2.5-1.4L22 7l-1.4-2.5L22 2zm-7.63 5.29a.9959.9959 0 00-1.41 0L1.29 18.96c-.39.39-.39 1.02 0 1.41l2.34 2.34c.39.39 1.02.39 1.41 0L16.71 11.05c.39-.39.39-1.02 0-1.41l-2.34-2.35zm-1.97 5.67L7.73 8.29l2.07-2.07 4.67 4.67-2.07 2.07z"/></svg>',
        'bulb' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/></svg>',
        'target' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10 10-4.49 10-10S17.51 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>',
        // People & Faces
        'person' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
        'support-agent' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.22C21 6.73 16.74 3 12 3c-4.69 0-9 3.65-9 9.28-.6.34-1 .98-1 1.72v2c0 1.1.9 2 2 2h1v-6.1c0-3.87 3.13-7 7-7s7 3.13 7 7V19h-8v2h8c1.1 0 2-.9 2-2v-1.22c.59-.31 1-.92 1-1.64v-2.3c0-.7-.41-1.31-1-1.62z"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M18 11.03A6.04 6.04 0 0012.05 6c-3.03 0-6.29 2.51-6.03 6.45a8.075 8.075 0 004.86-5.89c1.31 2.63 4 4.44 7.12 4.47z"/></svg>',
        'groups' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.73 1.17-.52 2.61-.91 4.24-.91zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm1.13 1.1c-.37-.06-.74-.1-1.13-.1-.99 0-1.93.21-2.78.58A2.01 2.01 0 000 16.43V18h4.5v-1.61c0-.83.23-1.61.63-2.29zM20 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm4 3.43c0-.81-.48-1.53-1.22-1.85A6.95 6.95 0 0020 14c-.39 0-.76.04-1.13.1.4.68.63 1.46.63 2.29V18H24v-1.57zM12 6c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z"/></svg>',
        // Nature & Misc
        'star' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>',
        'flower' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c4.97 0 9-4.03 9-9-4.97 0-9 4.03-9 9zM5.6 10.25c0 1.38 1.12 2.5 2.5 2.5.53 0 1.01-.16 1.42-.44l-.02.19c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5l-.02-.19c.4.28.89.44 1.42.44 1.38 0 2.5-1.12 2.5-2.5 0-1-.59-1.85-1.43-2.25.84-.4 1.43-1.25 1.43-2.25 0-1.38-1.12-2.5-2.5-2.5-.53 0-1.01.16-1.42.44l.02-.19C14.5 2.12 13.38 1 12 1S9.5 2.12 9.5 3.5l.02.19c-.4-.28-.89-.44-1.42-.44-1.38 0-2.5 1.12-2.5 2.5 0 1 .59 1.85 1.43 2.25-.84.4-1.43 1.25-1.43 2.25zM12 5.5c1.38 0 2.5 1.12 2.5 2.5s-1.12 2.5-2.5 2.5S9.5 9.38 9.5 8s1.12-2.5 2.5-2.5zM3 13c0 4.97 4.03 9 9 9 0-4.97-4.03-9-9-9z"/></svg>',
        'leaf' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M6.05 8.05a7.001 7.001 0 009.9 0 7.002 7.002 0 000-9.9C14.17-.03 11.81-.58 9.63.44 7.45 1.46 5.69 3.22 4.67 5.4c-1.02 2.18-.47 4.83 1.38 6.65zm11.62 3.57l-1.42 1.41c1.54 1.55 2.24 3.66 1.88 5.65l1.97.35c.48-2.63-.44-5.41-2.43-7.41zM3.41 20.41l1.41 1.41 13.17-13.17-1.41-1.41L3.41 20.41z"/></svg>',
        'sun' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M6.76 4.84l-1.8-1.79-1.41 1.41 1.79 1.79 1.42-1.41zM4 10.5H1v2h3v-2zm9-9.95h-2V3.5h2V.55zm7.45 3.91l-1.41-1.41-1.79 1.79 1.41 1.41 1.79-1.79zm-3.21 13.7l1.79 1.8 1.41-1.41-1.8-1.79-1.4 1.4zM20 10.5v2h3v-2h-3zm-8-5c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm-1 16.95h2V19.5h-2v2.95zm-7.45-3.91l1.41 1.41 1.79-1.8-1.41-1.41-1.79 1.8z"/></svg>',
        'moon' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-2.98 0-5.4-2.42-5.4-5.4 0-1.81.89-3.42 2.26-4.4-.44-.06-.9-.1-1.36-.1z"/></svg>',
        // Medical & Education
        'medical' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11h-4v4h-4v-4H6v-4h4V6h4v4h4v4z"/></svg>',
        'school' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>',
        'book' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>',
        // Food & Lifestyle
        'cafe' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM2 21h18v-2H2v2z"/></svg>',
        'restaurant' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>',
        // Travel & Places
        'flight' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>',
        'home' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
        'pin' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
        // Communication (more)
        'chat-smile' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><circle cx="9" cy="9" r="1.5"/><circle cx="15" cy="9" r="1.5"/><path d="M12 15c1.66 0 3.08-.93 3.8-2.3H8.2c.72 1.37 2.14 2.3 3.8 2.3z"/></svg>',
        'notification' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>',
        'mic' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>',
        // AI & Tech (more)
        'code' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>',
        'terminal' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10zm-2-1h-6v-2h6v2zM7.5 17l-1.41-1.41L8.67 13l-2.59-2.59L7.5 9l4 4-4 4z"/></svg>',
        'wifi' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>',
        'rocket' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5s-5 5-5 11c0 2.45.84 4.69 2.23 6.5h1.5c-.62-1.04-1.12-2.42-1.36-4h5.26c-.24 1.58-.74 2.96-1.36 4h1.5C16.16 18.19 17 15.95 17 13.5c0-6-5-11-5-11zM12 16c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM3.67 18.67L2 20.34V22h1.66l1.67-1.67c-.67-.67-1.22-1.22-1.66-1.66zm16.66 0c-.44.44-.99.99-1.66 1.66L20.34 22H22v-1.66l-1.67-1.67z"/></svg>',
        'analytics' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>',
        'search' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>',
        // Shapes & Symbols (more)
        'diamond' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5L2 9l10 12L22 9l-3-6zm-7 14.46L5.82 9h12.36L12 17.46z"/></svg>',
        'crown' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>',
        'fire' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/></svg>',
        'music' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>',
        'camera' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="3.2"/><path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>',
        'palette' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10c1.38 0 2.5-1.12 2.5-2.5 0-.61-.23-1.2-.64-1.67-.08-.1-.13-.21-.13-.33 0-.28.22-.5.5-.5H16c3.31 0 6-2.69 6-6 0-4.96-4.49-9-10-9zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 8 6.5 8 8 8.67 8 9.5 7.33 11 6.5 11zm3-4C8.67 7 8 6.33 8 5.5S8.67 4 9.5 4s1.5.67 1.5 1.5S10.33 7 9.5 7zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 4 14.5 4s1.5.67 1.5 1.5S15.33 7 14.5 7zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 8 17.5 8s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
        'pets' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><circle cx="4.5" cy="9.5" r="2.5"/><circle cx="9" cy="5.5" r="2.5"/><circle cx="15" cy="5.5" r="2.5"/><circle cx="19.5" cy="9.5" r="2.5"/><path d="M17.34 14.86c-1.21-1.62-3.06-2.61-5.09-2.61h-.5c-2.03 0-3.88.99-5.09 2.61C5.81 16.22 5.81 18.11 6.81 19.5 7.55 20.52 8.72 21.12 10 21.12h4c1.28 0 2.45-.6 3.19-1.62 1-1.39 1-3.28.15-4.64z"/></svg>',
        'fitness' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z"/></svg>',
    ];

    return $presets[$preset] ?? '';
}

/**
 * Get allowed HTML tags for SVG output via wp_kses
 */
function wpaic_get_svg_allowed_tags(): array {
    return [
        'svg' => [
            'class' => true,
            'viewbox' => true,
            'fill' => true,
            'xmlns' => true,
        ],
        'path' => [
            'd' => true,
            'fill' => true,
        ],
        'circle' => [
            'cx' => true,
            'cy' => true,
            'r' => true,
            'fill' => true,
        ],
        'rect' => [
            'x' => true,
            'y' => true,
            'width' => true,
            'height' => true,
            'rx' => true,
            'ry' => true,
            'fill' => true,
        ],
    ];
}
