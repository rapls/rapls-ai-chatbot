<?php
/**
 * REST API Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_REST_Controller {

    /**
     * Namespace
     */
    private string $namespace = 'wp-ai-chatbot/v1';

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Get/Create session
        register_rest_route($this->namespace, '/session', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_session'],
            'permission_callback' => '__return_true',
        ]);

        // Send chat message
        register_rest_route($this->namespace, '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'send_message'],
            'permission_callback' => [$this, 'check_session_permission'],
            'args'                => [
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'page_url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'recaptcha_token' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'user_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'image' => [
                    'required'          => false,
                    'type'              => 'string',
                    'validate_callback' => [$this, 'validate_image_param'],
                ],
            ],
        ]);

        // Get conversation history
        register_rest_route($this->namespace, '/history/(?P<session_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_history'],
            'permission_callback' => [$this, 'check_session_permission'],
            'args'                => [
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Submit lead (Pro feature)
        register_rest_route($this->namespace, '/lead', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit_lead'],
            'permission_callback' => [$this, 'check_session_permission'],
            'args'                => [
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'name' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'phone' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'company' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page_url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        // Get lead form configuration
        register_rest_route($this->namespace, '/lead-config', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_lead_config'],
            'permission_callback' => '__return_true',
        ]);

        // Get message limit status
        register_rest_route($this->namespace, '/message-limit', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_message_limit_status'],
            'permission_callback' => '__return_true',
        ]);

        // Submit message feedback (Free feature)
        register_rest_route($this->namespace, '/feedback', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit_feedback'],
            'permission_callback' => [$this, 'check_session_permission'],
            'args'                => [
                'message_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'feedback' => [
                    'required'          => true,
                    'type'              => 'integer',
                ],
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Regenerate response (Free feature)
        register_rest_route($this->namespace, '/regenerate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'regenerate_response'],
            'permission_callback' => [$this, 'check_session_permission'],
            'args'                => [
                'message_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Pro-only routes: only register when Pro is active
        $pro_features = WPAIC_Pro_Features::get_instance();
        if ($pro_features->is_pro()) {
            // Get conversation summary (Pro feature)
            register_rest_route($this->namespace, '/summary/(?P<session_id>[a-zA-Z0-9-]+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_conversation_summary'],
                'permission_callback' => [$this, 'check_session_permission'],
                'args'                => [
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            // Get related questions (Pro feature)
            register_rest_route($this->namespace, '/suggestions', [
                'methods'             => 'POST',
                'callback'            => [$this, 'get_related_suggestions'],
                'permission_callback' => [$this, 'check_session_permission'],
                'args'                => [
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'last_response' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ]);

            // Get autocomplete suggestions (Pro feature)
            register_rest_route($this->namespace, '/autocomplete', [
                'methods'             => 'POST',
                'callback'            => [$this, 'get_autocomplete'],
                'permission_callback' => [$this, 'check_session_permission'],
                'args'                => [
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'query' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            // Submit offline message (Pro feature)
            register_rest_route($this->namespace, '/offline-message', [
                'methods'             => 'POST',
                'callback'            => [$this, 'submit_offline_message'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'name' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'email' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_email',
                    ],
                    'message' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                    'page_url' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]);

            // Track conversion (Pro feature)
            register_rest_route($this->namespace, '/conversion', [
                'methods'             => 'POST',
                'callback'            => [$this, 'track_conversion'],
                'permission_callback' => [$this, 'check_session_permission'],
                'args'                => [
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'goal' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);
        }
    }

    /**
     * Get or create session
     */
    public function get_session(WP_REST_Request $request): WP_REST_Response {
        $rate_check = $this->check_public_rate_limit('ses', 30, 60);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check], 429);
        }

        $session_version = get_option('wpaic_session_version', 1);

        // Reuse existing session from cookie only if it passes strict validation
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $existing_session = isset($_COOKIE['wpaic_session_id']) ? sanitize_text_field(wp_unslash($_COOKIE['wpaic_session_id'])) : '';
        if (!empty($existing_session)) {
            // Strict format check: must be UUID4 (8-4-4-4-12 hex)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $existing_session)) {
                // Invalid format — discard and fall through to new session
                $existing_session = '';
            }
        }

        if (!empty($existing_session)) {
            // Short-lived cache to avoid hitting DB on every page view
            $cache_key = 'wpaic_sess_' . substr(hash('sha256', $existing_session . wp_salt()), 0, 16);
            $cached = get_transient($cache_key);

            if ($cached === 'exists') {
                return new WP_REST_Response([
                    'success'         => true,
                    'session_id'      => $existing_session,
                    'session_token'   => $this->generate_session_token($existing_session),
                    'session_version' => $session_version,
                ], 200);
            }

            // Only reuse if a conversation actually exists in DB
            $conversation = WPAIC_Conversation::get_by_session($existing_session);
            if ($conversation) {
                set_transient($cache_key, 'exists', 60);
                return new WP_REST_Response([
                    'success'         => true,
                    'session_id'      => $existing_session,
                    'session_token'   => $this->generate_session_token($existing_session),
                    'session_version' => $session_version,
                ], 200);
            }

            // No DB conversation — only accept if bootstrap transient exists
            // (proves this server issued the session_id recently)
            $transient_key = 'wpaic_boot_' . substr(hash('sha256', $existing_session . wp_salt()), 0, 32);
            if (get_transient($transient_key)) {
                return new WP_REST_Response([
                    'success'         => true,
                    'session_id'      => $existing_session,
                    'session_token'   => $this->generate_session_token($existing_session),
                    'session_version' => $session_version,
                ], 200);
            }

            // Neither DB nor transient — discard the cookie session (session fixation防止)
            // Fall through to generate a new session below
        }

        // Generate new session
        $session_id = WPAIC_Conversation::generate_session_id();

        // Set httpOnly cookie for session ownership verification
        $cookie_set = false;
        if (!headers_sent()) {
            setcookie('wpaic_session_id', $session_id, [
                'expires'  => 0,
                'path'     => '/',
                'httponly'  => true,
                'samesite' => 'Lax',
                'secure'   => is_ssl(),
            ]);
            $cookie_set = true;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC: headers_sent() prevented setting session cookie.');
            }
        }

        // Store bootstrap transient ONLY when cookie could not be set
        // (e.g. headers already sent by theme/plugin). This prevents
        // transient flooding if /session is called repeatedly.
        if (!$cookie_set) {
            $ip = $this->get_client_ip();
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            $bootstrap_hash = hash('sha256', $ip . $user_agent . wp_salt());
            $transient_key = 'wpaic_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
            set_transient($transient_key, $bootstrap_hash, 15 * MINUTE_IN_SECONDS);
        }

        // Generate HMAC session token for cookie-less environments
        $session_token = $this->generate_session_token($session_id);

        return new WP_REST_Response([
            'success'         => true,
            'session_id'      => $session_id,
            'session_token'   => $session_token,
            'session_version' => $session_version,
        ], 200);
    }

    /**
     * Send chat message
     */
    public function send_message(WP_REST_Request $request): WP_REST_Response {
        // Same-origin check for public POST
        // Session permission (nonce) provides primary auth, so allow missing headers.
        $origin_check = $this->check_same_origin();
        if ($origin_check instanceof WP_REST_Response) {
            return $origin_check;
        }

        // Route args apply sanitize_callback automatically;
        // re-sanitize here for defense-in-depth.
        $session_id      = sanitize_text_field($request->get_param('session_id'));
        $message         = sanitize_textarea_field($request->get_param('message'));
        $page_url        = esc_url_raw($request->get_param('page_url') ?? '');
        $recaptcha_token = sanitize_text_field($request->get_param('recaptcha_token') ?? '');
        $image           = $request->get_param('image');

        // Reject image if multimodal is not enabled (Pro feature)
        if (!empty($image)) {
            $pro_features_check = WPAIC_Pro_Features::get_instance();
            if (!$pro_features_check->is_pro() || !method_exists($pro_features_check, 'is_multimodal_enabled') || !$pro_features_check->is_multimodal_enabled()) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Image upload is not available.', 'rapls-ai-chatbot'),
                ], 400);
            }
        }

        // Session ownership already verified by check_session_permission()

        // Validate input
        $message_length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if (empty($message) || $message_length > 2000) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Message is empty or too long.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Verify reCAPTCHA
        $recaptcha_result = $this->verify_recaptcha($recaptcha_token, 'chat');
        if (is_wp_error($recaptcha_result)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => $recaptcha_result->get_error_message(),
            ], 403);
        }

        // Check rate limit (Pro enhanced or basic)
        $rate_limit_result = $this->check_rate_limit();
        if ($rate_limit_result !== true) {
            $rate_limit_msg = is_string($rate_limit_result) ? $rate_limit_result : __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
            return new WP_REST_Response([
                'success' => false,
                'error'   => $rate_limit_msg,
            ], 429);
        }

        // Check monthly message limit
        $pro_features = WPAIC_Pro_Features::get_instance();

        // Check IP block (Pro feature)
        if ($pro_features->is_ip_blocked()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => $pro_features->get_ip_block_message(),
                'code'    => 'ip_blocked',
            ], 403);
        }

        // Check business hours and holidays (Pro feature)
        $unavailable_message = $pro_features->get_unavailable_message();
        if ($unavailable_message !== null) {
            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'content'     => $unavailable_message,
                    'is_auto'     => true,
                    'sources'     => [],
                ],
            ], 200);
        }

        // Check budget limit (Pro feature)
        if ($pro_features->check_budget_limit()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => $pro_features->get_budget_block_message(),
                'code'    => 'budget_exceeded',
            ], 429);
        }

        // Check banned words (Pro feature)
        if ($pro_features->contains_banned_words($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => $pro_features->get_banned_words_message(),
                'code'    => 'banned_words',
            ], 400);
        }

        // Pre-check API key
        $settings = get_option('wpaic_settings', []);
        $provider_name = $settings['ai_provider'] ?? 'openai';

        switch ($provider_name) {
            case 'claude':
                $api_key = $this->decrypt_api_key($settings['claude_api_key'] ?? '');
                break;
            case 'gemini':
                $api_key = $this->decrypt_api_key($settings['gemini_api_key'] ?? '');
                break;
            default:
                $api_key = $this->decrypt_api_key($settings['openai_api_key'] ?? '');
                break;
        }

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('AI API key is not configured. Please configure it in the admin settings.', 'rapls-ai-chatbot'),
            ], 400);
        }

        try {
            $save_history = !empty($settings['save_history']);
            $conversation = null;
            $conversation_id = 0;

            if ($save_history) {
                // Get or create conversation
                $conversation = WPAIC_Conversation::get_or_create($session_id, [
                    'page_url'   => $page_url,
                    'visitor_ip' => $this->get_client_ip(),
                ]);

                if (!$conversation) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => __('Failed to create conversation session.', 'rapls-ai-chatbot'),
                    ], 500);
                }
                $conversation_id = $conversation['id'];

                // Save user message
                WPAIC_Message::create([
                    'conversation_id' => $conversation_id,
                    'role'            => 'user',
                    'content'         => $message,
                ]);
            } else {
                // save_history OFF — store context in transient only
                $this->append_transient_context($session_id, 'user', $message);
            }

            // Check message limit — if reached, try FAQ fallback instead of AI
            // get_monthly_ai_response_count() includes no-history counter automatically
            if ($pro_features->is_limit_reached()) {
                $search_engine = new WPAIC_Search_Engine();
                $faq_results = $search_engine->search_knowledge_only($message, 3);
                $faq_answer = $this->extract_faq_answer($faq_results, $message);

                if (empty($faq_answer)) {
                    $faq_answer = __('The monthly AI response limit has been reached. For more advanced usage, please consider the Pro version.', 'rapls-ai-chatbot');
                }

                // Save synthetic assistant message (only when history is enabled)
                $limit_msg_id = 0;
                if ($save_history) {
                    $ai_message = WPAIC_Message::create([
                        'conversation_id' => $conversation_id,
                        'role'            => 'assistant',
                        'content'         => $faq_answer,
                    ]);
                    $limit_msg_id = $ai_message['id'] ?? 0;
                }

                return new WP_REST_Response([
                    'success' => true,
                    'data'    => [
                        'message_id'         => $limit_msg_id,
                        'content'            => $faq_answer,
                        'tokens_used'        => 0,
                        'tokens_billed'      => 0,
                        'sources'            => [],
                        'remaining_messages' => 0,
                        'limit_reached'      => true,
                    ],
                ], 200);
            }

            // Get AI provider
            $ai_provider = $this->get_ai_provider();

            // Search related content
            $search_engine = new WPAIC_Search_Engine();
            $related_content = $search_engine->search($message, $settings['crawler_max_results'] ?? 3);
            $context = $search_engine->build_context($related_content, $this->get_max_context_chars(), $message);

            // Response cache check (Pro feature)
            $pro_settings = $settings['pro_features'] ?? [];
            $cache_enabled = !empty($pro_settings['response_cache_enabled']) && $pro_features->is_pro();
            $cache_hash = null;

            if ($cache_enabled) {
                $cache_ttl = (int) ($pro_settings['cache_ttl_days'] ?? 7);
                $cache_hash = WPAIC_Message::build_cache_hash($message, $context);
                $cached = WPAIC_Message::find_cached_response($cache_hash, $cache_ttl);

                if ($cached) {
                    $cache_msg_id = 0;

                    if ($save_history) {
                        // Cache hit — save a copy as the new assistant message
                        $ai_message = WPAIC_Message::create([
                            'conversation_id' => $conversation_id,
                            'role'            => 'assistant',
                            'content'         => $cached['content'],
                            'tokens_used'     => $cached['tokens_used'] ?? 0,
                            'input_tokens'    => 0,
                            'output_tokens'   => 0,
                            'ai_provider'     => $cached['ai_provider'] ?? null,
                            'ai_model'        => $cached['ai_model'] ?? null,
                        ]);

                        // Mark as cache hit and store hash
                        if ($ai_message) {
                            $cache_msg_id = $ai_message['id'];
                            WPAIC_Message::store_cache_hash((int) $ai_message['id'], $cache_hash);
                            global $wpdb;
                            $msg_table = $wpdb->prefix . 'aichat_messages';
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $wpdb->update($msg_table, ['cache_hit' => 1], ['id' => $ai_message['id']], ['%d'], ['%d']);
                        }
                    } else {
                        // save_history OFF — store in transient
                        $this->append_transient_context($session_id, 'assistant', $cached['content']);
                        $this->increment_no_history_monthly_count();
                    }

                    $urls = is_array($related_content) ? array_column($related_content, 'url') : [];
                    $sources = array_filter(array_map(
                        static function ($u) {
                            return esc_url_raw((string) $u, ['http', 'https']);
                        },
                        $urls
                    ));
                    $remaining_messages = $pro_features->get_remaining_messages();

                    $cached_content = apply_filters('wpaic_ai_response', $cached['content'], $message, $settings);

                    return new WP_REST_Response([
                        'success' => true,
                        'data'    => [
                            'message_id'         => $cache_msg_id,
                            'content'            => $cached_content,
                            'tokens_used'        => (int) ($cached['tokens_used'] ?? 0),
                            'tokens_billed'      => 0,
                            'sources'            => array_values($sources),
                            'remaining_messages' => $remaining_messages === PHP_INT_MAX ? null : $remaining_messages,
                            'cached'             => true,
                        ],
                    ], 200);
                }
            }

            // Build system prompt
            $system_prompt = $settings['system_prompt'] ?? 'You are a helpful assistant. Please answer user questions politely.';

            /**
             * Filter the system prompt sent to the AI provider.
             *
             * @param string $system_prompt The system prompt.
             * @param array  $settings      The plugin settings.
             */
            $system_prompt = apply_filters('wpaic_system_prompt', $system_prompt, $settings);

            // Add sentiment analysis (Pro feature)
            $sentiment = $pro_features->analyze_sentiment($message);
            $sentiment_prompt = $pro_features->get_sentiment_prompt($sentiment);
            if (!empty($sentiment_prompt)) {
                $system_prompt .= $sentiment_prompt;
            }

            // Add feedback examples for learning (if feedback is enabled)
            if (!empty($settings['show_feedback_buttons'])) {
                $feedback_prompt = '';

                // Positive examples - what works well
                $positive_examples = WPAIC_Message::get_positive_feedback_examples(3);
                if (!empty($positive_examples)) {
                    $good_header = $settings['feedback_good_header'] ?? "[LEARNING FROM USER FEEDBACK - GOOD EXAMPLES]\nThe following responses received positive feedback. Use these as examples of good responses:";
                    $feedback_prompt .= "\n\n" . $good_header . "\n";
                    foreach ($positive_examples as $idx => $example) {
                        $feedback_prompt .= sprintf("\nGood Example %d:\nQ: %s\nA: %s\n", $idx + 1, $example['question'], $example['answer']);
                    }
                }

                // Negative examples - what to avoid
                $negative_examples = WPAIC_Message::get_negative_feedback_examples(2);
                if (!empty($negative_examples)) {
                    $bad_header = $settings['feedback_bad_header'] ?? "[LEARNING FROM USER FEEDBACK - AVOID THESE PATTERNS]\nThe following responses received negative feedback. AVOID responding in similar ways:";
                    $feedback_prompt .= "\n\n" . $bad_header . "\n";
                    foreach ($negative_examples as $idx => $example) {
                        $feedback_prompt .= sprintf("\nBad Example %d:\nQ: %s\nA (AVOID): %s\n", $idx + 1, $example['question'], $example['answer']);
                    }
                }

                if (!empty($feedback_prompt)) {
                    $feedback_prompt .= "\n" . __('Learn from these examples to improve response quality.', 'rapls-ai-chatbot') . "\n";
                    $system_prompt .= $feedback_prompt;
                }
            }

            // Add context memory (Pro feature)
            $user_id = sanitize_text_field($request->get_param('user_id') ?? '');
            if (!empty($user_id) && $pro_features->is_context_memory_enabled()) {
                $user_context = $pro_features->get_user_context($user_id);
                $context_prompt = $pro_features->build_context_memory_prompt($user_context);
                if (!empty($context_prompt)) {
                    $system_prompt .= $context_prompt;
                }
            }
            /**
             * Filter the RAG context before injection into the system prompt.
             *
             * @param string $context  The context from site learning and knowledge base.
             * @param string $message  The user's message.
             * @param array  $settings The plugin settings.
             */
            $context = apply_filters('wpaic_context', $context, $message, $settings);

            if (!empty($context)) {
                // Check if context contains Q&A format
                $has_qa_format = preg_match('/Question\s*[:：]/ui', $context) && preg_match('/Answer\s*[:：]/ui', $context);

                if ($has_qa_format) {
                    // Check for exact match (highest priority)
                    $has_exact_match = strpos($context, '[EXACT MATCH') !== false;

                    if ($has_exact_match) {
                        // Exact match found - use configurable prompt
                        $exact_prompt = $settings['knowledge_exact_prompt'] ?? "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===";
                        $system_prompt .= "\n\n" . str_replace('{context}', $context, $exact_prompt);
                    } else {
                        // Q&A format - use configurable prompt
                        $qa_prompt = $settings['knowledge_qa_prompt'] ?? "=== CRITICAL INSTRUCTIONS ===\nBelow is a FAQ database. When the user asks a question:\n1. FIRST, look for [BEST MATCH] - this is the most relevant Q&A for the user's question\n2. If [BEST MATCH] exists, use that Answer to respond\n3. If no [BEST MATCH], find the Question that matches or is similar to the user's question\n4. Return the corresponding Answer from the FAQ\n5. DO NOT make up answers - ONLY use the information provided below\n\nIMPORTANT: The Answer after [BEST MATCH] is your primary response source.\n\n=== FAQ DATABASE ===\n{context}\n=== END FAQ DATABASE ===";
                        $system_prompt .= "\n\n" . str_replace('{context}', $context, $qa_prompt);
                    }
                } else {
                    // Standard reference information - use configurable prompt
                    $site_prompt = $settings['site_context_prompt'] ?? "[IMPORTANT: Reference Information]\nYou MUST use the following information as the primary source when answering. If the answer can be found in this information, use it directly.\nIf the reference information does NOT contain the answer, clearly state that you don't have specific information about it. Do NOT guess or fabricate details.\n\n{context}";
                    $system_prompt .= "\n\n" . str_replace('{context}', $context, $site_prompt);
                }
            }

            // Get conversation history
            $history = $save_history
                ? WPAIC_Message::get_context_messages($conversation_id, 10)
                : $this->get_transient_context($session_id);

            // Build message array
            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
            ];

            foreach ($history as $msg) {
                $messages[] = [
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            // Send to AI (clamp settings to safe ranges)
            $max_tokens = max(1, min(16384, (int) ($settings['max_tokens'] ?? 1000)));
            $temperature = max(0.0, min(2.0, (float) ($settings['temperature'] ?? 0.7)));

            $response = $ai_provider->send_message($messages, [
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
            ]);

            // Save AI response
            $resp_msg_id = 0;
            if ($save_history) {
                $ai_message = WPAIC_Message::create([
                    'conversation_id' => $conversation_id,
                    'role'            => 'assistant',
                    'content'         => $response['content'],
                    'tokens_used'     => $response['tokens_used'],
                    'input_tokens'    => $response['input_tokens'] ?? 0,
                    'output_tokens'   => $response['output_tokens'] ?? 0,
                    'ai_provider'     => $response['provider'],
                    'ai_model'        => $response['model'],
                ]);
                $resp_msg_id = $ai_message['id'] ?? 0;

                // Store cache hash on the new response
                if ($cache_enabled && $cache_hash && $resp_msg_id) {
                    WPAIC_Message::store_cache_hash($resp_msg_id, $cache_hash);
                }
            } else {
                // save_history OFF — store in transient and increment counter
                $this->append_transient_context($session_id, 'assistant', $response['content']);
                $this->increment_no_history_monthly_count();
            }

            // Budget alert check (Pro feature)
            $msg_cost = WPAIC_Cost_Calculator::calculate_cost(
                $response['model'] ?? '',
                $response['input_tokens'] ?? 0,
                $response['output_tokens'] ?? 0
            );
            $pro_features->maybe_send_budget_alert($msg_cost);

            // Get source URLs
            $urls = is_array($related_content) ? array_column($related_content, 'url') : [];
            $sources = array_filter(array_map(
                static function ($u) {
                    return esc_url_raw((string) $u, ['http', 'https']);
                },
                $urls
            ));

            // Trigger webhook for new message (Pro feature)
            if ($save_history && $conversation && class_exists('WPAIC_Webhook')) {
                try {
                    $webhook = WPAIC_Webhook::get_instance();
                    $webhook->trigger_new_message($conversation, $message, $response['content']);
                } catch (\Throwable $e) {
                    // Webhook error - ignore
                }
            }

            // Get remaining messages for response
            $remaining_messages = $pro_features->get_remaining_messages();

            /**
             * Filter the AI response content before returning to the user.
             *
             * @param string $content  The AI response text.
             * @param string $message  The user's original message.
             * @param array  $settings The plugin settings.
             */
            $response['content'] = apply_filters('wpaic_ai_response', $response['content'], $message, $settings);

            // Build response data
            $response_data = [
                'message_id'  => $resp_msg_id,
                'content'     => $response['content'],
                'tokens_used' => $response['tokens_used'],
                'sources'     => array_values($sources),
                'remaining_messages' => $remaining_messages === PHP_INT_MAX ? null : $remaining_messages,
            ];

            // Add sentiment to response if sentiment analysis is enabled
            $sentiment_enabled = $pro_features->is_sentiment_analysis_enabled();
            if ($sentiment_enabled && !empty($sentiment) && $sentiment !== 'neutral') {
                $response_data['sentiment'] = $sentiment;
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => $response_data,
            ], 200);

        } catch (WPAIC_Quota_Exceeded_Exception $e) {
            // Return custom quota error message from settings
            $quota_message = $settings['quota_error_message'] ?? 'Currently recharging. Please try again later.';
            return new WP_REST_Response([
                'success' => false,
                'error'   => $quota_message,
            ], 503);

        } catch (Exception $e) {
            $error_message = $e->getMessage();

            // Log detailed error for admin debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Chat Error: ' . $error_message);
            }

            // Return generic message to user (don't expose API internals)
            if (strpos($error_message, 'API key') !== false) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('The AI service is not configured correctly. Please contact the site administrator.', 'rapls-ai-chatbot'),
                ], 500);
            }

            // Model not found / deprecated — admin needs to update model selection
            $code = $e->getCode();
            if ($code === 404 || stripos($error_message, 'not found') !== false || stripos($error_message, 'deprecated') !== false) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('The AI model is currently unavailable. Please contact the site administrator.', 'rapls-ai-chatbot'),
                ], 500);
            }

            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Sorry, an error occurred while processing your request. Please try again later.', 'rapls-ai-chatbot'),
            ], 500);
        }
    }

    /**
     * Get conversation history
     */
    public function get_history(WP_REST_Request $request): WP_REST_Response {
        // When save_history is OFF, no messages are stored — return empty
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['save_history'])) {
            return new WP_REST_Response([
                'success'  => true,
                'messages' => [],
            ], 200);
        }

        $session_id = sanitize_text_field($request->get_param('session_id'));

        $conversation = WPAIC_Conversation::get_by_session($session_id);

        if (!$conversation) {
            return new WP_REST_Response([
                'success'  => true,
                'messages' => [],
            ], 200);
        }

        // Session ownership already verified by check_session_permission()

        $messages = WPAIC_Message::get_by_conversation($conversation['id']);

        // Return only necessary information
        $formatted = array_map(function($msg) {
            return [
                'id'         => $msg['id'],
                'role'       => $msg['role'],
                'content'    => $msg['content'],
                'created_at' => $msg['created_at'],
            ];
        }, $messages);

        return new WP_REST_Response([
            'success'  => true,
            'messages' => $formatted,
        ], 200);
    }

    /**
     * Get AI provider
     */
    private function get_ai_provider(): WPAIC_AI_Provider_Interface {
        $settings = get_option('wpaic_settings', []);
        $provider_name = $settings['ai_provider'] ?? 'openai';

        switch ($provider_name) {
            case 'claude':
                $provider = new WPAIC_Claude_Provider();
                $provider->set_api_key($this->decrypt_api_key($settings['claude_api_key'] ?? ''));
                $provider->set_model($settings['claude_model'] ?? 'claude-sonnet-4-20250514');
                break;

            case 'gemini':
                $provider = new WPAIC_Gemini_Provider();
                $provider->set_api_key($this->decrypt_api_key($settings['gemini_api_key'] ?? ''));
                $provider->set_model($settings['gemini_model'] ?? 'gemini-2.0-flash-exp');
                break;

            default: // openai
                $provider = new WPAIC_OpenAI_Provider();
                $provider->set_api_key($this->decrypt_api_key($settings['openai_api_key'] ?? ''));
                $provider->set_model($settings['openai_model'] ?? 'gpt-4o');
                break;
        }

        return $provider;
    }

    /**
     * Get max context characters based on the configured model.
     * Conservative limits (~25% of model token window) to leave room for system prompt + response.
     */
    private function get_max_context_chars(): int {
        $settings = get_option('wpaic_settings', []);
        $provider = $settings['ai_provider'] ?? 'openai';

        switch ($provider) {
            case 'openai':
                $model = $settings['openai_model'] ?? 'gpt-4o';
                // GPT-4.1 and o-series have 128K+ context
                if (strpos($model, 'gpt-4.1') === 0 || strpos($model, 'o') === 0) {
                    return 40000;
                }
                // GPT-4o: 128K context
                if (strpos($model, 'gpt-4o') === 0) {
                    return 30000;
                }
                // GPT-4-turbo: 128K context
                if (strpos($model, 'gpt-4-turbo') === 0) {
                    return 30000;
                }
                // GPT-4: 8K context
                if (strpos($model, 'gpt-4') === 0) {
                    return 8000;
                }
                // GPT-3.5-turbo: 16K
                if (strpos($model, 'gpt-3.5') === 0) {
                    return 12000;
                }
                return 20000;

            case 'claude':
                // Claude models generally have 200K context
                return 40000;

            case 'gemini':
                $model = $settings['gemini_model'] ?? 'gemini-2.0-flash-exp';
                // Gemini 2.0 Flash Lite: smaller context
                if (strpos($model, 'flash-lite') !== false) {
                    return 15000;
                }
                // Gemini Pro/Flash: 1M+ context
                return 40000;

            default:
                return 20000;
        }
    }

    /**
     * Decrypt API key (supports GCM and legacy CBC)
     */
    private function decrypt_api_key(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }

        // Return as-is if not encrypted (check known API key prefixes)
        if (strpos($encrypted, 'sk-') === 0 || strpos($encrypted, 'sk-ant-') === 0 || strpos($encrypted, 'AIza') === 0) {
            return $encrypted;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $new_key = hash('sha256', wp_salt('auth'), true);
        $aad = 'wpaic_' . wp_parse_url(get_site_url(), PHP_URL_HOST);
        $old_key = wp_salt('auth'); // Legacy fallback

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

            // Try normalized key + AAD → normalized key → legacy key
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag);
            }
            if ($decrypted === false) {
                $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $old_key, OPENSSL_RAW_DATA, $iv, $tag);
            }

            if ($decrypted === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key GCM decryption failed (salt may have changed or data tampered). Please re-enter your API key in settings.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return '';
            }
            return $decrypted;
        }

        // AES-256-CBC (legacy format)
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

        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $new_key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $old_key, OPENSSL_RAW_DATA, $iv);
        }

        if ($decrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WPAIC: API key decryption failed (salt may have changed). Please re-enter your API key in settings.'); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return '';
        }

        return $decrypted;
    }

    public function validate_image_param( $value, $request, $param ) {
        if (empty($value)) {
            return true;
        }

        // Must be a data URI with allowed MIME type
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/', $value, $matches)) {
            return new WP_Error('invalid_image', __('Invalid image format. Allowed: JPEG, PNG, GIF, WebP.', 'rapls-ai-chatbot'));
        }

        // Quick reject: base64 encoding expands ~33%, so 2MB binary ≈ 2.67MB encoded
        if (strlen($value) > 2800000) {
            return new WP_Error('image_too_large', __('Image is too large. Maximum size is 2MB.', 'rapls-ai-chatbot'));
        }

        // Verify decoded binary size and content
        $comma_pos = strpos($value, ',');
        if ($comma_pos === false) {
            return new WP_Error('invalid_image', __('Invalid image data.', 'rapls-ai-chatbot'));
        }

        $base64_data = substr($value, $comma_pos + 1);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $decoded = base64_decode($base64_data, true);
        if ($decoded === false) {
            return new WP_Error('invalid_image', __('Invalid image data.', 'rapls-ai-chatbot'));
        }

        // 2MB = 2 * 1024 * 1024 = 2097152 bytes
        if (strlen($decoded) > 2097152) {
            return new WP_Error('image_too_large', __('Image is too large. Maximum size is 2MB.', 'rapls-ai-chatbot'));
        }

        // Verify actual MIME type via finfo (not just the data URI header)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $real_mime = finfo_buffer($finfo, $decoded);
                finfo_close($finfo);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if ($real_mime !== false && !in_array($real_mime, $allowed_mimes, true)) {
                    return new WP_Error('invalid_image', __('Invalid image type detected. Allowed: JPEG, PNG, GIF, WebP.', 'rapls-ai-chatbot'));
                }
            }
            // If finfo_open fails, skip MIME check and rely on getimagesizefromstring below
        }

        // Verify image dimensions (max 2048px per side)
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- getimagesizefromstring may emit warnings on corrupt data
        $image_info = @getimagesizefromstring($decoded);
        if ($image_info === false) {
            return new WP_Error('invalid_image', __('Unable to read image dimensions.', 'rapls-ai-chatbot'));
        }
        $max_dimension = 2048;
        if ($image_info[0] > $max_dimension || $image_info[1] > $max_dimension) {
            return new WP_Error(
                'image_too_large',
                /* translators: %d: maximum pixel dimension */
                sprintf(__('Image dimensions exceed the maximum of %dpx per side.', 'rapls-ai-chatbot'), $max_dimension)
            );
        }

        return true;
    }

    private function get_client_ip(): string {
        $settings = get_option('wpaic_settings', []);

        // Trust Cloudflare header only when explicitly enabled
        if (!empty($settings['trust_cloudflare_ip'])) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Trust reverse proxy X-Forwarded-For only when explicitly enabled AND
        // REMOTE_ADDR is a trusted proxy (private/loopback = local proxy, or in allowlist).
        if (!empty($settings['trust_proxy_ip'])) {
            $remote = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));

            // Only trust XFF when the direct connection comes from a known proxy.
            // Private/loopback REMOTE_ADDR means a local reverse proxy (Nginx, Docker, etc.).
            // Additional trusted proxies can be added via filter.
            $trusted_proxies = apply_filters('wpaic_trusted_proxies', []);
            $remote_is_proxy = (
                !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ||
                in_array($remote, $trusted_proxies, true)
            );

            if ($remote_is_proxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
                $ips = array_map('trim', explode(',', $forwarded));
                foreach ($ips as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $candidate;
                    }
                }
            }
        }

        // Default: use REMOTE_ADDR (cannot be spoofed by client)
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Permission callback for session-based REST routes.
     *
     * Extracts session_id from the request and verifies ownership.
     * Returns true (allowed) or WP_Error (denied).
     *
     * @param WP_REST_Request $request REST request object.
     * @return true|WP_Error
     */
    public function check_session_permission(WP_REST_Request $request) {
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');

        if (empty($session_id)) {
            return new WP_Error(
                'rest_missing_session',
                __('Session ID is required.', 'rapls-ai-chatbot'),
                ['status' => 400]
            );
        }

        if (!$this->verify_session_ownership($session_id)) {
            return new WP_Error(
                'rest_session_forbidden',
                __('Invalid session.', 'rapls-ai-chatbot'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Verify that the current request owns the given session.
     *
     * Checks (in order): cookie → HMAC token → IP+UA hash fallbacks.
     * Cookie is primary; HMAC token is secondary (no IP dependency);
     * IP+UA hash is the last resort for legacy/transient scenarios.
     * Admins always pass.
     *
     * @param string $session_id  Session ID to verify
     * @return bool True if ownership is verified
     */
    private function verify_session_ownership(string $session_id): bool {
        // Note: prefer using check_session_permission() as permission_callback for REST routes.
        // This method is kept for internal use where a bool return is needed.
        // Admins always pass
        if (current_user_can('manage_options')) {
            return true;
        }

        // Primary: cookie set at session creation
        if (isset($_COOKIE['wpaic_session_id'])) {
            $cookie_session = sanitize_text_field(wp_unslash($_COOKIE['wpaic_session_id']));
            if (hash_equals($cookie_session, $session_id)) {
                return true;
            }
        }

        // Secondary: HMAC-signed session token (IP-independent, works across mobile/VPN/proxy)
        // Client stores token in localStorage and sends via X-WPAIC-Session-Token header
        if (isset($_SERVER['HTTP_X_WPAIC_SESSION_TOKEN'])) {
            $client_token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WPAIC_SESSION_TOKEN']));
            if ($this->verify_session_token($session_id, $client_token)) {
                return true;
            }
        }

        $ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $current_hash = hash('sha256', $ip . $user_agent . wp_salt());

        // Header-based session verification (legacy localStorage fallback)
        // Requires matching IP+UA hash via bootstrap transient to prevent session_id-only spoofing
        if (isset($_SERVER['HTTP_X_WPAIC_SESSION'])) {
            $header_session = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WPAIC_SESSION']));
            if (hash_equals($header_session, $session_id)) {
                $transient_key = 'wpaic_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
                $stored_hash = get_transient($transient_key);
                if ($stored_hash !== false && hash_equals($stored_hash, $current_hash)) {
                    return true;
                }
            }
        }

        // Fallback 1: visitor IP + User-Agent hash match against conversation record
        $conversation = WPAIC_Conversation::get_by_session($session_id);
        if ($conversation && !empty($conversation['visitor_ip'])) {
            if (hash_equals($conversation['visitor_ip'], $current_hash)) {
                return true;
            }
        }

        // Fallback 2: bootstrap transient (covers cookie-less first request after /session)
        $transient_key = 'wpaic_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
        $stored_hash = get_transient($transient_key);
        if ($stored_hash !== false && hash_equals($stored_hash, $current_hash)) {
            return true;
        }

        return false;
    }

    /**
     * Generate an HMAC-signed session token.
     *
     * The token is IP-independent so it survives mobile/VPN/proxy IP changes.
     * It binds session_id to a server secret, preventing forgery without
     * access to wp_salt().
     *
     * @param string $session_id Session ID to sign
     * @return string HMAC token (hex)
     */
    private function generate_session_token(string $session_id): string {
        return hash_hmac('sha256', $session_id, wp_salt('auth'));
    }

    /**
     * Verify an HMAC-signed session token.
     *
     * @param string $session_id   Session ID
     * @param string $client_token Token from the client
     * @return bool True if the token is valid
     */
    private function verify_session_token(string $session_id, string $client_token): bool {
        $expected = $this->generate_session_token($session_id);
        return hash_equals($expected, $client_token);
    }

    /**
     * Check rate limit
     *
     * @return true|string True if allowed, or error message string if blocked
     */
    private function check_rate_limit() {
        // Check Pro enhanced rate limit first
        $pro_features = WPAIC_Pro_Features::get_instance();
        $pro_settings = get_option('wpaic_settings', []);
        $pro_feat_settings = $pro_settings['pro_features'] ?? [];

        if ($pro_features->is_pro() && !empty($pro_feat_settings['enhanced_rate_limit_enabled'])) {
            $result = $pro_features->check_enhanced_rate_limit();
            if ($result['blocked']) {
                return $result['message'];
            }
            return true;
        }

        // Basic rate limit (Free version fallback)
        $settings = get_option('wpaic_settings', []);
        $limit = (int) ($settings['rate_limit'] ?? 20);
        $window = (int) ($settings['rate_limit_window'] ?? 3600);

        // Enforce minimum floor: even if admin sets 0, always apply at least
        // 60 requests per hour to prevent bot abuse
        if ($limit < 1) {
            $limit = 60;
        }
        if ($window < 60) {
            $window = 3600;
        }

        $ip = $this->get_client_ip();

        // If IP detection fails, fall back to session-based rate limiting
        // to prevent unlimited access from unknown-IP environments
        if (empty($ip)) {
            if (isset($_COOKIE['wpaic_session_id'])) {
                $session_key = 'wpaic_noip_' . substr(hash('sha256', sanitize_text_field(wp_unslash($_COOKIE['wpaic_session_id'])) . wp_salt()), 0, 32);
                $noip_count = (int) get_transient($session_key);
                if ($noip_count >= $limit) {
                    return __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
                }
                set_transient($session_key, $noip_count + 1, $window);
            }
            return true;
        }

        $ip_hash = hash('sha256', $ip . wp_salt());

        // Burst protection: max 3 requests per 10 seconds
        // Prefer session-based key to avoid NAT/corporate proxy collisions;
        // fall back to IP-based key when session is unavailable
        if (isset($_COOKIE['wpaic_session_id'])) {
            $burst_key = 'wpaic_burst_' . substr(hash('sha256', sanitize_text_field(wp_unslash($_COOKIE['wpaic_session_id'])) . wp_salt()), 0, 32);
        } else {
            $burst_key = 'wpaic_burst_' . substr($ip_hash, 0, 32);
        }
        $burst_count = (int) get_transient($burst_key);
        if ($burst_count >= 3) {
            return __('Too many requests. Please wait a few seconds.', 'rapls-ai-chatbot');
        }
        set_transient($burst_key, $burst_count + 1, 10);

        // Use session_id in key when available (from cookie) to reduce
        // false positives behind shared NAT/corporate networks
        $session_suffix = '';
        if (isset($_COOKIE['wpaic_session_id'])) {
            $session_suffix = '_' . substr(hash('sha256', sanitize_text_field(wp_unslash($_COOKIE['wpaic_session_id']))), 0, 8);
        }
        $transient_key = 'wpaic_rate_' . substr($ip_hash, 0, 24) . $session_suffix;

        $count = (int) get_transient($transient_key);

        if ($count >= $limit) {
            return __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
        }

        set_transient($transient_key, $count + 1, $window);

        // Also enforce a global per-IP limit (2x) to prevent abuse via multiple sessions
        $global_key = 'wpaic_rate_ip_' . substr($ip_hash, 0, 32);
        $global_count = (int) get_transient($global_key);
        $global_limit = $limit * 2;

        if ($global_count >= $global_limit) {
            return __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
        }

        set_transient($global_key, $global_count + 1, $window);

        return true;
    }

    /**
     * Lightweight rate limit for public (unauthenticated) REST routes.
     * Less strict than check_rate_limit() — prevents automated abuse without
     * interfering with normal page views that trigger /session or /lead-config.
     *
     * @param string $route_key Short identifier for the route (used in transient key)
     * @param int    $limit     Max requests per window (default 30)
     * @param int    $window    Window in seconds (default 60)
     * @return true|string True if allowed, or error message string if blocked
     */
    private function check_public_rate_limit(string $route_key = 'pub', int $limit = 30, int $window = 60) {
        $ip = $this->get_client_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'no-ua';

        // Combine IP + User-Agent to reduce NAT false-positives while adding
        // an extra factor that IP-rotating attackers must also spoof.
        $identity = ($ip ?: 'no-ip') . '|' . $ua;
        $identity_hash = hash('sha256', $identity . wp_salt());
        $transient_key = 'wpaic_prl_' . $route_key . '_' . substr($identity_hash, 0, 24);

        $count = (int) get_transient($transient_key);

        if ($count >= $limit) {
            return __('Too many requests. Please try again later.', 'rapls-ai-chatbot');
        }

        set_transient($transient_key, $count + 1, $window);
        return true;
    }

    /**
     * Increment a bot detection counter (transient-based, 1-hour window).
     * Used by guard_public_post() to track honeypot/timing drops for admin visibility.
     *
     * @param string $type Detection type ('honeypot', 'timing').
     */
    private function increment_bot_counter(string $type): void {
        $key = 'wpaic_bot_drop_' . $type;
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    /**
     * Build the list of allowed origin hostnames for this site.
     * Includes home_url, site_url, www variants, and the wpaic_allowed_origins filter.
     * Shared by check_same_origin() and verify_recaptcha() hostname validation.
     *
     * @return string[] Array of lowercase hostnames.
     */
    protected function get_allowed_origin_hosts(): array {
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $site_host = wp_parse_url(site_url(), PHP_URL_HOST);

        $allowed = [];
        foreach (array_filter([$home_host, $site_host]) as $h) {
            $h = strtolower($h);
            $allowed[] = $h;
            if (strpos($h, 'www.') === 0) {
                $allowed[] = substr($h, 4);
            } else {
                $allowed[] = 'www.' . $h;
            }
        }
        $allowed = array_unique($allowed);

        /**
         * Filter allowed origin hosts for public POST requests and reCAPTCHA hostname validation.
         * Values must be lowercase hostnames (no scheme, no port, no path).
         *
         * @param string[] $allowed Array of lowercase hostnames.
         */
        return apply_filters('wpaic_allowed_origins', $allowed);
    }

    /**
     * Check that a public POST request originates from the same site.
     * Compares Origin or Referer header host against allowed hosts (home_url, site_url).
     * Not bulletproof (headers can be spoofed) but raises the bar for casual abuse.
     *
     * Returns:
     *   true              — Origin/Referer matched an allowed host.
     *   'no_headers'      — Neither Origin nor Referer was present (caller decides policy).
     *   WP_REST_Response  — Origin/Referer present but did NOT match (hard reject).
     *
     * @return true|string|WP_REST_Response
     */
    protected function check_same_origin() {
        $allowed = $this->get_allowed_origin_hosts();

        if (empty($allowed)) {
            return 'no_headers'; // Can't determine site host; delegate to caller policy
        }

        $origin = isset($_SERVER['HTTP_ORIGIN']) ? wp_parse_url(sanitize_url(wp_unslash($_SERVER['HTTP_ORIGIN'])), PHP_URL_HOST) : null;
        $referer = isset($_SERVER['HTTP_REFERER']) ? wp_parse_url(sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])), PHP_URL_HOST) : null;

        // Accept if either header matches any allowed host (exact match)
        if (($origin && in_array(strtolower($origin), $allowed, true)) ||
            ($referer && in_array(strtolower($referer), $allowed, true))) {
            return true;
        }

        // No headers at all — return sentinel so caller can decide based on other defenses
        if (!$origin && !$referer) {
            return 'no_headers';
        }

        // Headers present but don't match — hard reject
        return new WP_REST_Response([
            'success' => false,
            'error'   => __('Cross-origin request denied.', 'rapls-ai-chatbot'),
        ], 403);
    }

    /**
     * Consolidated guard for public POST endpoints.
     * Runs: same-origin, rate limit, honeypot, timing, reCAPTCHA.
     *
     * Return contract: always returns true (pass) or WP_REST_Response (reject).
     * Callers must use: if ($guard !== true) { return $guard; }
     *
     * @param WP_REST_Request $request         The REST request.
     * @param string          $rate_key        Short identifier for rate limiting transient.
     * @param int             $rate_limit      Max requests per window.
     * @param int             $rate_window     Window in seconds.
     * @param bool            $require_captcha Whether reCAPTCHA must be fully configured.
     * @param string          $captcha_action  reCAPTCHA action name (e.g. 'offline', 'lead').
     * @return true|WP_REST_Response True if all checks pass, or error response.
     */
    private function guard_public_post(
        WP_REST_Request $request,
        string $rate_key = 'pub',
        int $rate_limit = 30,
        int $rate_window = 60,
        bool $require_captcha = false,
        string $captcha_action = ''
    ) {
        // 1. Same-origin check
        $origin_result = $this->check_same_origin();

        // Hard reject: headers present but don't match
        if ($origin_result instanceof WP_REST_Response) {
            return $origin_result;
        }

        // No Origin/Referer headers: allow only when other defenses compensate.
        // When captcha is required, the reCAPTCHA check below provides equivalent protection.
        // Without captcha, rate limiting is the primary defense for headerless requests
        // (blocking would reject legitimate users behind proxies/privacy extensions).
        $origin_ok = ($origin_result === true);

        // 2. Public rate limit
        $rate_check = $this->check_public_rate_limit($rate_key, $rate_limit, $rate_window);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check], 429);
        }

        // 3. Honeypot: reject if hidden field is filled (bots auto-fill)
        // Field name is unique to avoid collision with other plugins' forms.
        $hp = $request->get_param('wpaic_hp');
        if (!empty($hp)) {
            $this->increment_bot_counter('honeypot_' . $rate_key);
            return new WP_REST_Response(['success' => true], 200); // Silent success
        }

        // 4. Timing check: reject if submitted faster than 5 seconds (bot speed).
        // _ts is a client-side Unix timestamp set by JS when the form renders.
        $form_ts = (int) $request->get_param('_ts');
        if ($form_ts > 0 && (time() - $form_ts) < 5) {
            $this->increment_bot_counter('timing_' . $rate_key);
            return new WP_REST_Response(['success' => true], 200); // Silent success
        }

        // When _ts is missing (JS disabled/delayed) and captcha is required, reject.
        // Without both timing and captcha, bot detection is too weak.
        if ($form_ts === 0 && $require_captcha && !$origin_ok) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Form validation failed. Please reload the page and try again.', 'rapls-ai-chatbot'),
            ], 403);
        }

        // 5. reCAPTCHA (when required)
        if ($require_captcha) {
            $settings = get_option('wpaic_settings', []);
            $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
            $recaptcha_site_key = trim($settings['recaptcha_site_key'] ?? '');
            $recaptcha_secret_key = trim($settings['recaptcha_secret_key'] ?? '');

            if (!$recaptcha_enabled) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => sprintf(
                        /* translators: %s: feature name */
                        __('%s requires reCAPTCHA to be enabled. Please configure reCAPTCHA in the plugin settings.', 'rapls-ai-chatbot'),
                        ucfirst(str_replace('_', ' ', $captcha_action))
                    ),
                ], 403);
            }

            if (empty($recaptcha_site_key) || empty($recaptcha_secret_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('reCAPTCHA is enabled but not fully configured (missing site key or secret key). Please complete the reCAPTCHA setup in plugin settings.', 'rapls-ai-chatbot'),
                ], 403);
            }

            $recaptcha_token = sanitize_text_field($request->get_param('recaptcha_token') ?? '');
            $recaptcha_result = $this->verify_recaptcha($recaptcha_token, $captcha_action);
            if (is_wp_error($recaptcha_result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => $recaptcha_result->get_error_message(),
                ], 403);
            }
        }

        return true;
    }

    /**
     * Verify reCAPTCHA
     *
     * @param string|null $token    reCAPTCHA token
     * @param string      $expected_action Expected reCAPTCHA action name (e.g. 'chat', 'lead', 'offline')
     * @return bool|WP_Error True if verified, WP_Error on failure
     */
    private function verify_recaptcha( $token, string $expected_action = '' ) {
        $settings = get_option('wpaic_settings', []);

        // Skip if reCAPTCHA is disabled
        if (empty($settings['recaptcha_enabled'])) {
            return true;
        }

        $secret_key = $settings['recaptcha_secret_key'] ?? '';
        $threshold = floatval($settings['recaptcha_threshold'] ?? 0.5);

        // Decrypt secret key if encrypted (GCM or legacy CBC)
        if (!empty($secret_key) && (strpos($secret_key, 'encg:') === 0 || strpos($secret_key, 'enc:') === 0)) {
            $secret_key = WPAIC_Admin::decrypt_secret_static($secret_key);
        }
        $secret_key = trim($secret_key);

        // reCAPTCHA enabled but secret key is missing — misconfiguration
        if (empty($secret_key)) {
            // Cost-sensitive actions must be blocked to prevent abuse
            $cost_sensitive_actions = ['chat', 'lead', 'offline'];
            if (in_array($expected_action, $cost_sensitive_actions, true)) {
                return new WP_Error('recaptcha_misconfigured', __('Security verification is not properly configured. Please contact the site administrator.', 'rapls-ai-chatbot'));
            }
            return true;
        }

        // Error if token is missing
        if (empty($token)) {
            return new WP_Error('recaptcha_missing', __('reCAPTCHA token is missing. Please reload the page.', 'rapls-ai-chatbot'));
        }

        // Send verification request to Google reCAPTCHA API
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => $this->get_client_ip(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            // Determine fail mode: cost-sensitive actions (chat, lead, offline) default to closed
            $fail_mode = $settings['recaptcha_fail_mode'] ?? 'open';
            $cost_sensitive_actions = ['chat', 'lead', 'offline'];
            $should_block = ($fail_mode === 'closed') || in_array($expected_action, $cost_sensitive_actions, true);
            if ($should_block) {
                return new WP_Error('recaptcha_unavailable', __('Security verification is temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'));
            }
            return true; // fail-open: allow request through
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return new WP_Error('recaptcha_failed', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
        }

        // Verify action matches expected (prevents token reuse across different forms)
        if (!empty($expected_action) && isset($body['action']) && $body['action'] !== $expected_action) {
            return new WP_Error('recaptcha_action_mismatch', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
        }

        // Verify hostname matches this site (prevents token from other sites).
        // Uses the same allowed-hosts logic as check_same_origin() to handle
        // www/non-www, home_url/site_url differences, and custom origins.
        if (!empty($body['hostname'])) {
            $allowed_hosts = $this->get_allowed_origin_hosts();
            $token_host = strtolower($body['hostname']);
            if (!empty($allowed_hosts) && !in_array($token_host, $allowed_hosts, true)) {
                return new WP_Error('recaptcha_hostname_mismatch', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
            }
        }

        // Check score (reCAPTCHA v3)
        $score = floatval($body['score'] ?? 0);
        if ($score < $threshold) {
            return new WP_Error('recaptcha_low_score', __('Security check failed.', 'rapls-ai-chatbot'));
        }

        return true;
    }

    /**
     * Submit lead form (Pro feature)
     */
    public function submit_lead(WP_REST_Request $request): WP_REST_Response {
        // Same-origin check — session ownership provides primary auth,
        // so allow missing headers (proxy/privacy extension scenarios).
        $origin_check = $this->check_same_origin();
        if ($origin_check instanceof WP_REST_Response) {
            return $origin_check;
        }

        try {
            // Check if Pro feature is available
            $pro_features = WPAIC_Pro_Features::get_instance();
            if (!$pro_features->is_feature_available(WPAIC_Pro_Features::FEATURE_LEAD_CAPTURE)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Lead capture feature requires Pro license.', 'rapls-ai-chatbot'),
                ], 403);
            }

            // Session ownership already verified by check_session_permission()
            $session_id = sanitize_text_field($request->get_param('session_id'));

            // Rate limit: max 10 lead submissions per IP per hour
            $ip = $this->get_client_ip();
            if (!empty($ip)) {
                $ip_hash = hash('sha256', $ip . wp_salt());
                $transient_key = 'wpaic_lead_rate_' . substr($ip_hash, 0, 32);
                $count = (int) get_transient($transient_key);
                if ($count >= 10) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => __('Too many submissions. Please try again later.', 'rapls-ai-chatbot'),
                    ], 429);
                }
                set_transient($transient_key, $count + 1, HOUR_IN_SECONDS);
            }

            // Verify reCAPTCHA if enabled
            $recaptcha_token = sanitize_text_field($request->get_param('recaptcha_token') ?? '');
            $recaptcha_result = $this->verify_recaptcha($recaptcha_token, 'lead');
            if (is_wp_error($recaptcha_result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => $recaptcha_result->get_error_message(),
                ], 403);
            }

            $session_id = sanitize_text_field($request->get_param('session_id'));
            $email      = sanitize_email($request->get_param('email'));
            $name       = sanitize_text_field($request->get_param('name') ?? '');
            $phone      = sanitize_text_field($request->get_param('phone') ?? '');
            $company    = sanitize_text_field($request->get_param('company') ?? '');
            $page_url   = esc_url_raw($request->get_param('page_url') ?? '');

            // Validate email
            if (empty($email) || !is_email($email)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Please enter a valid email address.', 'rapls-ai-chatbot'),
                ], 400);
            }

            // Get or create conversation
            $conversation = WPAIC_Conversation::get_or_create($session_id, [
                'page_url'   => $page_url,
                'visitor_ip' => $this->get_client_ip(),
            ]);

            if (!$conversation) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Failed to create conversation.', 'rapls-ai-chatbot'),
                ], 500);
            }

            // Check if lead already exists for this conversation
            $existing_lead = WPAIC_Lead::get_by_conversation($conversation['id']);
            if ($existing_lead) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => [
                        'lead_id' => $existing_lead['id'],
                        'message' => __('Lead already submitted.', 'rapls-ai-chatbot'),
                    ],
                ], 200);
            }

            // Create lead
            $lead = WPAIC_Lead::create([
                'conversation_id' => $conversation['id'],
                'name'            => $name,
                'email'           => $email,
                'phone'           => $phone,
                'company'         => $company,
            ]);

            if (!$lead) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Failed to save lead information.', 'rapls-ai-chatbot'),
                ], 500);
            }

            // Trigger webhook for lead captured (Pro feature)
            if (class_exists('WPAIC_Webhook')) {
                try {
                    $webhook = WPAIC_Webhook::get_instance();
                    $webhook->trigger_lead_captured($lead);
                } catch (\Throwable $webhook_error) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                        error_log('WPAIC Webhook Error: ' . $webhook_error->getMessage());
                    }
                }
            }

            // Send admin notification email if enabled (wrapped in try-catch)
            try {
                $this->maybe_send_lead_notification($lead);
            } catch (\Throwable $notification_error) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('WPAIC Notification Error: ' . $notification_error->getMessage());
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'lead_id' => $lead['id'],
                    'message' => __('Thank you for your information.', 'rapls-ai-chatbot'),
                ],
            ], 200);

        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Lead Submit Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Failed to submit lead information.', 'rapls-ai-chatbot'),
            ], 500);
        }
    }

    /**
     * Get lead form configuration
     */
    public function get_lead_config(WP_REST_Request $request): WP_REST_Response {
        $rate_check = $this->check_public_rate_limit('lcfg', 30, 60);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check], 429);
        }

        try {
            $pro_features = WPAIC_Pro_Features::get_instance();
            $settings = get_option('wpaic_settings', []);
            $pro_settings = $settings['pro_features'] ?? WPAIC_Pro_Features::get_default_settings();

            // Check if lead capture is enabled and available
            $is_enabled = !empty($pro_settings['lead_capture_enabled']) &&
                          $pro_features->is_feature_available(WPAIC_Pro_Features::FEATURE_LEAD_CAPTURE);

            if (!$is_enabled) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => [
                        'enabled'  => false,
                        'required' => false,
                    ],
                ], 200);
            }

            // Build fields configuration
            $fields = [];
            $lead_fields = $pro_settings['lead_fields'] ?? [];

            foreach ($lead_fields as $field_name => $field_config) {
                if (!empty($field_config['enabled'])) {
                    $fields[$field_name] = [
                        'label'    => $field_config['label'] ?? ucfirst($field_name),
                        'required' => !empty($field_config['required']),
                        'type'     => $field_name === 'email' ? 'email' : ($field_name === 'phone' ? 'tel' : 'text'),
                    ];
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'enabled'     => true,
                    'required'    => !empty($pro_settings['lead_capture_required']),
                    'title'       => $pro_settings['lead_form_title'] ?? __('Before we start', 'rapls-ai-chatbot'),
                    'description' => $pro_settings['lead_form_description'] ?? __('Please enter your information', 'rapls-ai-chatbot'),
                    'fields'      => $fields,
                ],
            ], 200);

        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Lead Config Error: ' . $e->getMessage());
            }
            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'enabled'  => false,
                    'required' => false,
                ],
            ], 200);
        }
    }

    /**
     * Get message limit status
     */
    public function get_message_limit_status(WP_REST_Request $request): WP_REST_Response {
        $rate_check = $this->check_public_rate_limit('mlim', 30, 60);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check], 429);
        }

        $pro_features = WPAIC_Pro_Features::get_instance();
        $remaining = $pro_features->get_remaining_messages();

        // Return only UI-necessary fields; omit raw limit to avoid exposing plan details
        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'remaining' => $remaining === PHP_INT_MAX ? null : $remaining,
                'reached'   => $pro_features->is_limit_reached(),
            ],
        ], 200);
    }

    /**
     * Send lead notification email to admin
     */
    private function maybe_send_lead_notification(array $lead): void {
        $settings = get_option('wpaic_settings', []);
        $pro_settings = $settings['pro_features'] ?? [];

        // Check if notification is enabled
        if (empty($pro_settings['lead_notification_enabled'])) {
            return;
        }

        // Determine recipient
        $to = $pro_settings['lead_notification_email'] ?? '';
        if (empty($to) || !is_email($to)) {
            $to = get_option('admin_email');
        }

        if (empty($to)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Lead Notification: No valid email address found');
            }
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] New lead captured', 'rapls-ai-chatbot'),
            $site_name
        );

        // Build HTML message
        $message_html = sprintf(
            '<h2>%s</h2>
            <table style="border-collapse: collapse; width: 100%%; max-width: 500px;">
                <tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th><td style="padding: 8px; border-bottom: 1px solid #ddd;">%s</td></tr>
                <tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th><td style="padding: 8px; border-bottom: 1px solid #ddd;"><a href="mailto:%s">%s</a></td></tr>
                <tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th><td style="padding: 8px; border-bottom: 1px solid #ddd;">%s</td></tr>
                <tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th><td style="padding: 8px; border-bottom: 1px solid #ddd;">%s</td></tr>
                <tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th><td style="padding: 8px; border-bottom: 1px solid #ddd;">%s</td></tr>
            </table>
            <p style="margin-top: 20px;"><a href="%s">%s</a></p>',
            __('New Lead Captured', 'rapls-ai-chatbot'),
            __('Name', 'rapls-ai-chatbot'),
            esc_html($lead['name'] ?: '-'),
            __('Email', 'rapls-ai-chatbot'),
            esc_attr($lead['email']),
            esc_html($lead['email']),
            __('Phone', 'rapls-ai-chatbot'),
            esc_html($lead['phone'] ?: '-'),
            __('Company', 'rapls-ai-chatbot'),
            esc_html($lead['company'] ?: '-'),
            __('Date', 'rapls-ai-chatbot'),
            esc_html($lead['created_at']),
            esc_url(admin_url('admin.php?page=wpaic-leads')),
            __('View all leads', 'rapls-ai-chatbot')
        );

        // Plain text fallback
        /* translators: %1$s: name, %2$s: email, %3$s: phone, %4$s: company, %5$s: date, %6$s: leads page URL */
        $message_text = sprintf(
            __("A new lead has been captured:\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nCompany: %4\$s\nDate: %5\$s\n\nView all leads: %6\$s", 'rapls-ai-chatbot'),
            $lead['name'] ?: '-',
            $lead['email'],
            $lead['phone'] ?: '-',
            $lead['company'] ?: '-',
            $lead['created_at'],
            admin_url('admin.php?page=wpaic-leads')
        );

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        ];

        // Send email
        $result = wp_mail($to, $subject, $message_html, $headers);

        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('WPAIC Lead Notification: wp_mail() failed. To: ' . $to);
        }
    }

    /**
     * Submit message feedback
     */
    public function submit_feedback(WP_REST_Request $request): WP_REST_Response {
        // Feedback requires stored messages
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['save_history'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Feedback is not available when conversation history is disabled.', 'rapls-ai-chatbot'),
            ], 400);
        }

        $message_id = absint($request->get_param('message_id'));
        $feedback   = (int) $request->get_param('feedback');
        $session_id = sanitize_text_field($request->get_param('session_id'));

        // Validate feedback value
        if (!in_array($feedback, [-1, 0, 1], true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Invalid feedback value.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Verify message exists
        $message = WPAIC_Message::get_by_id($message_id);
        if (!$message) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Message not found.', 'rapls-ai-chatbot'),
            ], 404);
        }

        // Only allow feedback on assistant messages
        if ($message['role'] !== 'assistant') {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Feedback only allowed on AI responses.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Verify message belongs to the given session
        $conversation = WPAIC_Conversation::get_by_session($session_id);
        if (!$conversation || (int) $conversation['id'] !== (int) $message['conversation_id']) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Invalid session.', 'rapls-ai-chatbot'),
            ], 403);
        }

        // Session ownership already verified by check_session_permission()

        // Update feedback
        $result = WPAIC_Message::update_feedback($message_id, $feedback);

        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Failed to save feedback.', 'rapls-ai-chatbot'),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'message_id' => $message_id,
                'feedback'   => $feedback,
            ],
        ], 200);
    }

    /**
     * Regenerate AI response (Pro feature)
     */
    public function regenerate_response(WP_REST_Request $request): WP_REST_Response {
        // Regeneration requires stored messages
        $regen_settings = get_option('wpaic_settings', []);
        if (empty($regen_settings['save_history'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Response regeneration is not available when conversation history is disabled.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Check Pro license
        $pro_features = WPAIC_Pro_Features::get_instance();
        if (!$pro_features->is_pro()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('This feature requires a Pro license.', 'rapls-ai-chatbot'),
            ], 403);
        }

        $message_id = absint($request->get_param('message_id'));
        $session_id = sanitize_text_field($request->get_param('session_id'));

        // Get the message to regenerate
        $message = WPAIC_Message::get_by_id($message_id);
        if (!$message || $message['role'] !== 'assistant') {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Invalid message.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Get conversation
        $conversation = WPAIC_Conversation::get_by_session($session_id);
        if (!$conversation || $conversation['id'] != $message['conversation_id']) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Invalid session.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Session ownership already verified by check_session_permission()

        // Check message limit
        $limit_check = $pro_features->check_message_limit();
        if (is_wp_error($limit_check)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => $limit_check->get_error_message(),
            ], 429);
        }

        // Get messages before this one
        $messages = WPAIC_Message::get_by_conversation($conversation['id']);
        $context_messages = [];
        foreach ($messages as $msg) {
            if ($msg['id'] >= $message_id) {
                break;
            }
            if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                $context_messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        // Find the user message that triggered this response
        $user_message_content = '';
        for ($i = count($context_messages) - 1; $i >= 0; $i--) {
            if ($context_messages[$i]['role'] === 'user') {
                $user_message_content = $context_messages[$i]['content'];
                break;
            }
        }

        if (empty($user_message_content)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Could not find original question.', 'rapls-ai-chatbot'),
            ], 400);
        }

        try {
            // Get AI provider
            $provider = $this->get_ai_provider();
            $settings = get_option('wpaic_settings', []);

            // Search for related content
            $search = new WPAIC_Search_Engine();
            $related_content = $search->search($user_message_content, 3);

            // Build system prompt with context
            $system_prompt = $settings['system_prompt'] ?? 'You are a helpful assistant.';
            if (!empty($related_content)) {
                $context = "Reference:\n";
                foreach ($related_content as $item) {
                    $context .= "- " . ($item['content'] ?? '') . "\n";
                }
                $system_prompt .= "\n\n" . $context;
            }

            // Get the previous response to avoid repeating it
            $previous_response = $message['content'];

            // Extract first 100 characters of previous response to explicitly forbid
            $forbidden_start = function_exists('mb_substr') ? mb_substr(trim($previous_response), 0, 100) : substr(trim($previous_response), 0, 100);

            // Generate random variation number to force different output
            $variation_number = wp_rand(1, 1000);
            $variation_styles = [
                __('Use a casual, friendly tone', 'rapls-ai-chatbot'),
                __('Use a formal, professional tone', 'rapls-ai-chatbot'),
                __('Start your answer with a different opening', 'rapls-ai-chatbot'),
                __('Explain from a different angle', 'rapls-ai-chatbot'),
                __('Use different examples', 'rapls-ai-chatbot'),
                __('Focus on different aspects', 'rapls-ai-chatbot'),
            ];
            $random_style = $variation_styles[array_rand($variation_styles)];

            // Add stronger instruction to system prompt
            $regen_settings = get_option('wpaic_settings', []);
            $regen_template = $regen_settings['regenerate_prompt'] ?? '[REGENERATION REQUEST #{variation_number}]: The user wants a DIFFERENT answer. FORBIDDEN: Do not start with "{forbidden_start}". {style}. Create a completely new response with different wording. IMPORTANT: Do NOT use headings, labels, or section markers like【】or brackets. Write in natural flowing paragraphs. Complete all sentences fully.';
            $regenerate_instruction = "\n\n" . str_replace(
                ['{variation_number}', '{forbidden_start}', '{style}'],
                [$variation_number, function_exists('mb_substr') ? mb_substr($forbidden_start, 0, 50) : substr($forbidden_start, 0, 50), $random_style],
                $regen_template
            );
            $system_prompt .= $regenerate_instruction;

            // Remove the last context message if it's the same user message
            array_pop($context_messages);

            // Build message array for AI
            $ai_messages = [
                ['role' => 'system', 'content' => $system_prompt],
            ];

            // Add conversation history (without the previous AI response)
            foreach ($context_messages as $ctx_msg) {
                $ai_messages[] = $ctx_msg;
            }

            // Add original user question
            $ai_messages[] = [
                'role'    => 'user',
                'content' => $user_message_content,
            ];

            // Add the previous AI response that user didn't like
            $ai_messages[] = [
                'role'    => 'assistant',
                'content' => $previous_response,
            ];

            // Add user request for different answer with random element
            // Use microtime for unique identifier
            $unique_id = substr(md5(microtime(true) . wp_rand()), 0, 6);
            $regenerate_request = sprintf(
                /* translators: 1: style instruction, 2: unique request ID */
                __('Please give me a different answer. %1$s. Do not use any headings or special formatting. Write naturally and complete all sentences. [ID:%2$s]', 'rapls-ai-chatbot'),
                $random_style,
                $unique_id
            );
            $ai_messages[] = [
                'role'    => 'user',
                'content' => $regenerate_request,
            ];

            // Call AI with higher temperature for more variety
            // Use higher max_tokens for regeneration to avoid truncation
            $max_tokens = max(($settings['max_tokens'] ?? 1000), 2000);
            $response = $provider->send_message($ai_messages, [
                'max_tokens'  => $max_tokens,
                'temperature' => 1.0, // Maximum temperature for variety
            ]);

            // Delete old message and create new one
            global $wpdb;
            $table = $wpdb->prefix . 'aichat_messages';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, ['id' => $message_id], ['%d']);

            // Save new AI response
            $new_message = WPAIC_Message::create([
                'conversation_id' => $conversation['id'],
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens_used' => $response['tokens_used'],
                'input_tokens' => $response['input_tokens'] ?? 0,
                'output_tokens' => $response['output_tokens'] ?? 0,
                'ai_provider' => $settings['ai_provider'] ?? 'openai',
                'ai_model' => $response['model'] ?? null,
            ]);

            $urls = is_array($related_content) ? array_column($related_content, 'url') : [];
            $sources = array_filter(array_map(
                static function ($u) {
                    return esc_url_raw((string) $u, ['http', 'https']);
                },
                $urls
            ));

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'message_id'  => $new_message['id'],
                    'content'     => $response['content'],
                    'tokens_used' => $response['tokens_used'],
                    'sources'     => array_values($sources),
                ],
            ], 200);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Regenerate Error: ' . $e->getMessage());
            }
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Failed to regenerate response.', 'rapls-ai-chatbot'),
            ], 500);
        }
    }

    /**
     * Get conversation summary (Pro feature)
     */
    public function get_conversation_summary(WP_REST_Request $request): WP_REST_Response {
        // Summary requires stored messages
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['save_history'])) {
            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'summary' => null,
                    'message' => __('Conversation summary is not available when history is disabled.', 'rapls-ai-chatbot'),
                ],
            ], 200);
        }

        // Check Pro license
        $pro_features = WPAIC_Pro_Features::get_instance();
        if (!$pro_features->is_pro()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('This feature requires a Pro license.', 'rapls-ai-chatbot'),
            ], 403);
        }

        $session_id = sanitize_text_field($request->get_param('session_id'));

        // Session ownership already verified by check_session_permission()

        $conversation = WPAIC_Conversation::get_by_session($session_id);

        if (!$conversation) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Conversation not found.', 'rapls-ai-chatbot'),
            ], 404);
        }

        // Get all messages
        $messages = WPAIC_Message::get_by_conversation($conversation['id'], 100);

        if (count($messages) < 4) {
            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'summary' => null,
                    'message' => __('Conversation is too short to summarize.', 'rapls-ai-chatbot'),
                ],
            ], 200);
        }

        // Build conversation text for summary
        $conversation_text = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'user') {
                $conversation_text .= "User: " . $msg['content'] . "\n";
            } elseif ($msg['role'] === 'assistant') {
                $conversation_text .= "Assistant: " . wp_trim_words($msg['content'], 50) . "\n";
            }
        }

        try {
            // Get AI provider
            $provider = $this->get_ai_provider();

            // Generate summary
            $sum_settings = get_option('wpaic_settings', []);
            $summary_prompt = $sum_settings['summary_prompt'] ?? __('Please summarize the following conversation in 2-3 sentences, highlighting the main topics discussed and any conclusions reached:', 'rapls-ai-chatbot');

            $response = $provider->generate_response([
                ['role' => 'user', 'content' => $summary_prompt . "\n\n" . $conversation_text]
            ], [
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'summary'       => $response['content'],
                    'message_count' => count($messages),
                ],
            ], 200);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC Summary Error: ' . $e->getMessage());
            }
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Failed to generate summary.', 'rapls-ai-chatbot'),
            ], 500);
        }
    }

    /**
     * Get related question suggestions (Pro feature)
     */
    public function get_related_suggestions(WP_REST_Request $request): WP_REST_Response {
        try {
            // Check Pro license
            $pro_features = WPAIC_Pro_Features::get_instance();
            if (!$pro_features->is_pro()) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            // Check if related suggestions are enabled
            $settings = get_option('wpaic_settings', []);
            $pro_settings = $settings['pro_features'] ?? [];
            if (empty($pro_settings['related_suggestions_enabled'])) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            $session_id    = sanitize_text_field($request->get_param('session_id'));
            $last_response = sanitize_textarea_field($request->get_param('last_response') ?? '');

            $conversation = WPAIC_Conversation::get_by_session($session_id);
            if (!$conversation) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            // Get recent messages for context
            $messages = WPAIC_Message::get_context_messages($conversation['id'], 4);

            if (empty($messages)) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            // Get AI provider
            $provider = $this->get_ai_provider();

            // Build context
            $context = '';
            foreach ($messages as $msg) {
                // Decode HTML entities to avoid issues with AI
                $content = html_entity_decode(wp_trim_words($msg['content'], 30), ENT_QUOTES, 'UTF-8');
                $context .= ($msg['role'] === 'user' ? 'Q: ' : 'A: ') . $content . "\n";
            }

            // Generate suggestions
            $suggestion_prompt = __('Based on the following conversation, suggest 3 short follow-up questions the user might want to ask. Return only the questions, one per line, without numbering:', 'rapls-ai-chatbot');

            // Use higher token limit for reasoning models (o1, o3, etc.)
            $response = $provider->send_message([
                ['role' => 'user', 'content' => $suggestion_prompt . "\n\n" . $context]
            ], [
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);

            // Parse suggestions
            $suggestions = array_filter(
                array_map('trim', explode("\n", $response['content'])),
                function($s) { $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); return !empty($s) && $len > 5 && $len < 100; }
            );

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'suggestions' => array_values(array_slice($suggestions, 0, 3)),
                ],
            ], 200);

        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'success' => true,
                'data'    => ['suggestions' => []],
            ], 200);
        }
    }

    /**
     * Get autocomplete suggestions (Pro feature)
     */
    public function get_autocomplete(WP_REST_Request $request): WP_REST_Response {
        try {
            // Check Pro license
            $pro_features = WPAIC_Pro_Features::get_instance();
            if (!$pro_features->is_pro()) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            // Check if autocomplete is enabled
            $settings = get_option('wpaic_settings', []);
            $pro_settings = $settings['pro_features'] ?? [];
            if (empty($pro_settings['autocomplete_enabled'])) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            $query = sanitize_text_field($request->get_param('query'));

            $query_len = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
            if ($query_len < 3) {
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => ['suggestions' => []],
                ], 200);
            }

            // Get suggestions from knowledge base and past questions
            $suggestions = [];

            // Search knowledge base for matching titles (safe: KB is admin-curated content)
            try {
                global $wpdb;
                $kb_table = $wpdb->prefix . 'aichat_knowledge';

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $kb_titles = $wpdb->get_col($wpdb->prepare(
                    "SELECT title FROM `{$kb_table}`
                     WHERE is_active = 1 AND status = 'published'
                     AND title LIKE %s
                     ORDER BY priority DESC
                     LIMIT 5",
                    '%' . $wpdb->esc_like($query) . '%'
                ));

                if ($kb_titles) {
                    $suggestions = array_merge($suggestions, $kb_titles);
                }
            } catch (\Throwable $e) {
                // Skip knowledge search on error
            }

            // Search past user messages from THIS session only (privacy: never expose other users' questions)
            try {
                $session_id = sanitize_text_field($request->get_param('session_id'));
                $conversation = WPAIC_Conversation::get_by_session($session_id);

                if ($conversation) {
                    global $wpdb;
                    $msg_table = $wpdb->prefix . 'aichat_messages';

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $past_questions = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT content FROM `{$msg_table}`
                         WHERE role = 'user'
                         AND conversation_id = %d
                         AND content LIKE %s
                         AND CHAR_LENGTH(content) > 10
                         AND CHAR_LENGTH(content) < 150
                         ORDER BY created_at DESC
                         LIMIT 5",
                        $conversation['id'],
                        '%' . $wpdb->esc_like($query) . '%'
                    ));

                    if ($past_questions) {
                        $suggestions = array_merge($suggestions, $past_questions);
                    }
                }
            } catch (\Throwable $e) {
                // Skip past questions on error
            }

            // Remove duplicates and limit
            $suggestions = array_unique($suggestions);
            $suggestions = array_slice($suggestions, 0, 5);

            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'suggestions' => array_values($suggestions),
                ],
            ], 200);

        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'success' => true,
                'data'    => ['suggestions' => []],
            ], 200);
        }
    }

    /**
     * Extract FAQ answer from knowledge search results
     *
     * @param array  $results Knowledge search results
     * @param string $query   User query
     * @return string FAQ answer or empty string if no match
     */
    private function extract_faq_answer(array $results, string $query): string {
        if (empty($results)) {
            return '';
        }

        // Try Q&A format extraction from the best match
        $best = $results[0];
        $content = $best['content'] ?? '';

        if (empty($content)) {
            return '';
        }

        // Check if content contains Q&A format
        if (preg_match('/(?:Answer|A)\s*[:：]\s*(.+)/uis', $content, $matches)) {
            return trim($matches[1]);
        }

        // If content has a title matching the query closely, return the content as-is
        $title = $best['title'] ?? '';
        $query_lower = wpaic_mb_strtolower($query);
        $title_lower = wpaic_mb_strtolower($title);

        if (!empty($title) && (
            wpaic_mb_strpos($title_lower, $query_lower) !== false ||
            wpaic_mb_strpos($query_lower, $title_lower) !== false
        )) {
            return $content;
        }

        // Return best match content if score is high enough
        if (($best['score'] ?? 0) >= 30) {
            return $content;
        }

        return '';
    }

    /**
     * Track conversion (Pro feature)
     */
    public function track_conversion(WP_REST_Request $request): WP_REST_Response {
        // Session ownership already verified by check_session_permission()
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $goal       = sanitize_text_field($request->get_param('goal') ?? '');

        $settings = get_option('wpaic_settings', []);
        $pro_settings = $settings['pro_features'] ?? [];

        if (empty($pro_settings['conversion_tracking_enabled'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Conversion tracking is not enabled.', 'rapls-ai-chatbot'),
            ], 400);
        }

        $result = WPAIC_Conversation::mark_converted($session_id, $goal);

        return new WP_REST_Response([
            'success' => $result,
        ], $result ? 200 : 400);
    }

    /**
     * Submit offline message (Pro feature)
     */
    public function submit_offline_message(WP_REST_Request $request): WP_REST_Response {
        // Consolidated public POST guard: same-origin, rate limit, honeypot, timing, reCAPTCHA
        $guard = $this->guard_public_post($request, 'offl', 10, 60, true, 'offline');
        if ($guard !== true) {
            return $guard;
        }

        $name     = sanitize_text_field($request->get_param('name') ?? '');
        $email    = sanitize_email($request->get_param('email'));
        $message  = sanitize_textarea_field($request->get_param('message'));
        $page_url = esc_url_raw($request->get_param('page_url') ?? '');

        if (empty($email) || !is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('A valid email address is required.', 'rapls-ai-chatbot'),
            ], 400);
        }

        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Message is required.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Message length limit (5,000 characters)
        $msg_len = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if ($msg_len > 5000) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Message is too long. Maximum 5,000 characters.', 'rapls-ai-chatbot'),
            ], 400);
        }

        $settings = get_option('wpaic_settings', []);
        $pro_settings = $settings['pro_features'] ?? [];

        if (empty($pro_settings['offline_message_enabled'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Offline messages are not enabled.', 'rapls-ai-chatbot'),
            ], 400);
        }

        // Additional per-IP hourly rate limit for offline messages
        $ip = $this->get_client_ip();
        if (!empty($ip)) {
            $ip_hash = hash('sha256', $ip . wp_salt());
            $transient_key = 'wpaic_offline_rate_' . substr($ip_hash, 0, 32);
            $count = (int) get_transient($transient_key);
            if ($count >= 5) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => __('Too many submissions. Please try again later.', 'rapls-ai-chatbot'),
                ], 429);
            }
            set_transient($transient_key, $count + 1, HOUR_IN_SECONDS);
        }

        // Save via WPAIC_Lead::create() for consistent sanitization and format specifiers
        $lead = WPAIC_Lead::create([
            'conversation_id' => 0,
            'name'            => $name,
            'email'           => $email,
            'custom_fields'   => [
                'type'     => 'offline_message',
                'message'  => sanitize_textarea_field($message),
                'page_url' => esc_url_raw($page_url),
            ],
        ]);

        if (!$lead) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => __('Failed to save message.', 'rapls-ai-chatbot'),
            ], 500);
        }

        // Send email notification
        if (!empty($pro_settings['offline_notification_enabled'])) {
            $to = $pro_settings['offline_notification_email'] ?? '';
            if (empty($to)) {
                $to = get_option('admin_email');
            }

            $site_name = get_bloginfo('name');
            $subject = sprintf(
                /* translators: %s: site name */
                __('[%s] New Offline Message', 'rapls-ai-chatbot'),
                $site_name
            );

            $body = sprintf(
                "%s: %s\n%s: %s\n\n%s:\n%s",
                __('Name', 'rapls-ai-chatbot'),
                $name ?: __('(not provided)', 'rapls-ai-chatbot'),
                __('Email', 'rapls-ai-chatbot'),
                $email,
                __('Message', 'rapls-ai-chatbot'),
                $message
            );

            if (!empty($page_url)) {
                $body .= "\n\n" . __('Page', 'rapls-ai-chatbot') . ': ' . $page_url;
            }

            wp_mail($to, $subject, $body);
        }

        // Trigger webhook
        if (class_exists('WPAIC_Webhook')) {
            try {
                $webhook = WPAIC_Webhook::get_instance();
                $webhook->send('offline_message_received', [
                    'name'     => $name,
                    'email'    => $email,
                    'message'  => $message,
                    'page_url' => $page_url,
                ]);
            } catch (\Throwable $e) {
                // Ignore webhook errors
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'message' => __('Thank you! Your message has been received. We will get back to you soon.', 'rapls-ai-chatbot'),
            ],
        ], 200);
    }

    /**
     * Get the transient key for ephemeral session context.
     * Used when save_history is OFF to keep multi-turn context without DB writes.
     */
    private function get_context_transient_key(string $session_id): string {
        return 'wpaic_ctx_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
    }

    /**
     * Retrieve ephemeral context messages from transient (save_history OFF mode).
     *
     * @return array Array of ['role' => ..., 'content' => ...] entries.
     */
    private function get_transient_context(string $session_id): array {
        $key = $this->get_context_transient_key($session_id);
        $data = get_transient($key);
        return is_array($data) ? $data : [];
    }

    /**
     * Append a message to the ephemeral context transient (save_history OFF mode).
     * Keeps the most recent 20 entries (10 user + 10 assistant) with a 1-hour TTL.
     */
    private function append_transient_context(string $session_id, string $role, string $content): void {
        $key = $this->get_context_transient_key($session_id);
        $data = get_transient($key);
        if (!is_array($data)) {
            $data = [];
        }
        $data[] = ['role' => $role, 'content' => $content];
        // Keep last 20 entries
        if (count($data) > 20) {
            $data = array_slice($data, -20);
        }
        set_transient($key, $data, HOUR_IN_SECONDS);
    }

    /**
     * Increment monthly AI response counter (used when save_history is OFF).
     * When save_history is ON, the counter is derived from the messages table.
     */
    private function increment_no_history_monthly_count(): void {
        $option_key = 'wpaic_nohist_msg_count_' . wp_date('Y_m');
        $count = (int) get_option($option_key, 0);
        update_option($option_key, $count + 1, false);
    }

}
