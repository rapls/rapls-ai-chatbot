<?php
/**
 * Chatbot widget class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Chatbot_Widget {

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles(): void {
        if (!$this->should_display()) {
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
        $margin_right = absint($settings['badge_margin_right'] ?? 20);
        $margin_bottom = absint($settings['badge_margin_bottom'] ?? 20);

        $custom_css = "
            :root {
                --wpaic-primary: {$primary_color};
                --wpaic-primary-dark: " . $this->darken_color($primary_color, 20) . ";
            }
            .wp-ai-chatbot {
                right: {$margin_right}px;
                bottom: {$margin_bottom}px;
            }
        ";

        // White label: hide powered by
        $pro_settings = $settings['pro_features'] ?? [];
        $pro = WPAIC_Pro_Features::get_instance();
        if ($pro->is_pro() && !empty($pro_settings['hide_powered_by'])) {
            $custom_css .= "
            .chatbot-footer-powered {
                display: none !important;
            }
            ";
        }

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
        if (!$this->should_display()) {
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

        wp_enqueue_script(
            'wpaic-chatbot',
            WPAIC_PLUGIN_URL . 'assets/js/chatbot.js',
            [],
            WPAIC_VERSION,
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
            'bot_name'            => $settings['bot_name'] ?? 'Assistant',
            'bot_avatar'          => $bot_avatar,
            'bot_avatar_is_image' => $bot_avatar_is_image,
            'welcome_message'     => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'recaptcha_enabled'   => $recaptcha_enabled,
            'recaptcha_site_key'  => $recaptcha_site_key,
            'is_pro'              => (bool) get_option('wpaic_pro_active'),
            'session_version'     => get_option('wpaic_session_version', 1),
            'show_feedback'       => !empty($settings['show_feedback_buttons']),
            'show_regenerate'     => (bool) $show_regenerate,
            'related_suggestions' => $related_suggestions,
            'autocomplete'        => $autocomplete,
            'multimodal_enabled'  => $multimodal_enabled,
            'multimodal_max_size' => $multimodal_max_size,
            'conversion_tracking'  => !empty($pro_features['conversion_tracking_enabled']),
            'conversion_goals'     => !empty($pro_features['conversion_tracking_enabled']) ? ($pro_features['conversion_goals'] ?? []) : [],
            'offline_message'      => $this->get_offline_config($pro_features),
            'consent_strict_mode'  => !empty($settings['consent_strict_mode']),
            // wpaic_frontend_debug filter: always include a capability check in callbacks.
            // Logged-in guard prevents accidental exposure to anonymous visitors.
            // Minimum cap (default edit_posts) required even when filter overrides,
            // to prevent misuse granting debug to all logged-in subscribers.
            // Use wpaic_frontend_debug_min_cap filter to change the minimum capability.
            'debug'                => is_user_logged_in() && current_user_can($this->get_debug_min_cap()) && (bool) apply_filters('wpaic_frontend_debug', defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')),
            'strings'              => [
                'error_occurred'         => __('An error occurred.', 'rapls-ai-chatbot'),
                'error_rate_limit'       => __('Too many requests. Please try again in a moment.', 'rapls-ai-chatbot'),
                'error_unavailable'      => __('This feature is currently unavailable.', 'rapls-ai-chatbot'),
                'error_server'           => __('A temporary error occurred. Please try again later, or contact the site administrator.', 'rapls-ai-chatbot'),
                'error_session_expired'  => __('Your session has expired. Please reload the page.', 'rapls-ai-chatbot'),
                'error_pro_required'     => __('This feature requires the Pro version.', 'rapls-ai-chatbot'),
                'error_timing'           => __('Please wait a moment and try again.', 'rapls-ai-chatbot'),
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
            ],
        ]);
    }

    /**
     * Render widget
     */
    public function render_widget(): void {
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
        $pro_features = $settings['pro_features'] ?? [];
        $badge_icon_type = $pro_features['badge_icon_type'] ?? 'default';
        $badge_icon_preset = $pro_features['badge_icon_preset'] ?? '';
        $badge_icon_image = $pro_features['badge_icon_image'] ?? '';
        $badge_icon_emoji = $pro_features['badge_icon_emoji'] ?? '';

        include WPAIC_PLUGIN_DIR . 'templates/frontend/chatbot-widget.php';
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
        'headset' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
        'question' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
        'message' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><path d="M7 9h10v2H7zm0-3h10v2H7z"/></svg>',
        'robot' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7v1H3v-1a7 7 0 017-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 012-2zM7.5 13A1.5 1.5 0 006 14.5 1.5 1.5 0 007.5 16 1.5 1.5 0 009 14.5 1.5 1.5 0 007.5 13zm9 0a1.5 1.5 0 00-1.5 1.5 1.5 1.5 0 001.5 1.5 1.5 1.5 0 001.5-1.5 1.5 1.5 0 00-1.5-1.5zM5 18v2h14v-2H5z"/></svg>',
        'sparkle' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>',
        'heart' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
        'lightning' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>',
        'help-circle' => '<svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2c0-3.25 3-3 3-5 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 2.5-3 2.75-3 5z"/></svg>',
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
