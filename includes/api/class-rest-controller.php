<?php
/**
 * REST API Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_REST_Controller {

    /**
     * Namespace
     */
    private string $namespace = 'rapls-ai-chatbot/v1';

    /**
     * Add no-cache headers to a REST response.
     * Prevents page caching plugins from caching dynamic per-user responses.
     * Intentionally overwrites any existing Cache-Control — only used on
     * chat/dedup responses that must never be cached.
     */
    private function no_cache(WP_REST_Response $response): WP_REST_Response {
        $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        return $response;
    }

    /**
     * Append a comma-separated value to a response header without overwriting.
     * Handles case-insensitive header key lookup and duplicate detection.
     * Use for headers like Vary and Cache-Control that accumulate directives.
     *
     * @param WP_REST_Response $response REST response.
     * @param string           $header   Header name (e.g. 'Vary', 'Cache-Control').
     * @param string           $value    Directive to add (e.g. 'Cookie', 'no-store').
     */
    private function append_header_csv(WP_REST_Response $response, string $header, string $value): void {
        // get_headers() key casing varies — normalize to case-insensitive lookup.
        $headers = $response->get_headers();
        $existing = '';
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $header) === 0) {
                $existing = $v;
                break;
            }
        }
        // Check if the value is already present (case-insensitive).
        $parts = array_filter(array_map('trim', explode(',', $existing)));
        foreach ($parts as $part) {
            if (strcasecmp($part, $value) === 0) {
                return; // Already present.
            }
        }
        $parts[] = $value;
        $response->header($header, implode(', ', $parts));
    }

    /**
     * Build a "silent success" response for bot-detected requests.
     * Returns HTTP 200 with {'success': true} so bots learn nothing,
     * but adds an X-RAPLSAICH-Dropped header (reason) when WP_DEBUG is on
     * or the current user has manage_options — allowing admins / support
     * to diagnose false positives from DevTools without exposing info to attackers.
     *
     * @param string $reason Short reason code (e.g. 'honeypot', 'timing').
     * @return WP_REST_Response
     */
    private function silent_success(string $reason): WP_REST_Response {
        // Body looks like a normal success but includes a _dropped flag so the
        // front-end can show a generic retry hint without revealing the reason.
        $body = ['success' => true, '_dropped' => true];
        $response = new WP_REST_Response($body, 200);
        // Prevent intermediate caches from storing and re-serving this response.
        // Critical when X-RAPLSAICH-Dropped is present: without no-store, an admin's
        // response could be cached and served to general users, leaking the header.
        $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $this->append_header_csv($response, 'Vary', 'Cookie');
        if ((defined('WP_DEBUG') && WP_DEBUG) || current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
            $response->header('X-RAPLSAICH-Dropped', $reason);
        }
        return $response;
    }

    /**
     * Whether to log dedup-related diagnostics.
     * Gated on WP_DEBUG + WP_DEBUG_LOG (same policy as provider should_log).
     */
    private function should_log_dedup(): bool {
        if (apply_filters('raplsaich_debug_log_enabled', false)) {
            return true;
        }
        return defined('WP_DEBUG') && WP_DEBUG
            && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }

    /**
     * Ensure no-cache headers on public GET routes regardless of response status.
     * Hooked to rest_post_dispatch so ALL code paths (success, error, exception) are covered.
     *
     * Uses structural matching: any GET request under our namespace gets no-cache.
     * This avoids the fragility of a hardcoded route list — new public GET routes
     * automatically inherit no-cache behavior without manual updates.
     *
     * IMPORTANT: rest_post_dispatch can pass WP_REST_Response, WP_HTTP_Response,
     * WP_Error, or null — never use strict type hints on the first parameter.
     *
     * @param mixed           $result  REST response (WP_REST_Response|WP_HTTP_Response|WP_Error|null).
     * @param WP_REST_Server  $server  REST server.
     * @param WP_REST_Request $request REST request.
     * @return mixed
     */
    public function ensure_no_cache_public_gets($result, WP_REST_Server $server, WP_REST_Request $request) {
        if ($request->get_method() !== 'GET') {
            return $result;
        }

        $route = $request->get_route();
        $prefix = '/' . $this->namespace . '/';
        if (strpos($route, $prefix) !== 0) {
            return $result;
        }

        // WP_Error or null — nothing to add headers to.
        if ($result === null || is_wp_error($result)) {
            return $result;
        }

        // WP_REST_Response and WP_HTTP_Response both have header().
        // Merge no-cache directives instead of overwriting to preserve existing
        // directives (e.g. 'private') set by other plugins or CDN layers.
        if ($result instanceof WP_REST_Response) {
            foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
                $this->append_header_csv($result, 'Cache-Control', $directive);
            }
            $result->header('Pragma', 'no-cache');
            $result->header('Expires', '0');
        } elseif (is_object($result) && method_exists($result, 'header')) {
            // WP_HTTP_Response — no get_headers(), fall back to direct set.
            $result->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $result->header('Pragma', 'no-cache');
            $result->header('Expires', '0');
        }

        return $result;
    }

    /**
     * Enrich 403/429 responses with debug reason for admins.
     *
     * Adds both a header (X-RAPLSAICH-Debug-Reason) and a body field (debug_reason)
     * so the reason is visible even when CDN/WAF strips custom headers.
     * Only exposed to users with the plugin's manage capability
     * (RAPLSAICH_Admin::get_manage_cap() — same getter used by all admin checks).
     *
     * Known error_code values (kept as a reference for support docs):
     *   rate_limited, origin_mismatch, recaptcha_required, recaptcha_failed,
     *   recaptcha_misconfigured, session_expired, session_missing,
     *   honeypot_triggered, timing_failed,
     *   raplsaich_table_error, unknown
     *
     * @param mixed            $result  Response object.
     * @param WP_REST_Server   $server  REST server.
     * @param WP_REST_Request  $request Request.
     * @return mixed
     */
    public function add_debug_reason_header($result, $server, $request) {
        if (!($result instanceof WP_REST_Response)) {
            return $result;
        }
        $route = $request->get_route();
        if (strpos($route, '/' . $this->namespace . '/') !== 0) {
            return $result;
        }
        $status = $result->get_status();
        if ($status !== 403 && $status !== 429) {
            return $result;
        }
        // Defensive: require both logged-in AND capability check.
        // Prevents cache/proxy edge cases from leaking debug info to anonymous users.
        // Same cap getter as all admin permission checks — must stay in sync.
        if (!is_user_logged_in() || !current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
            return $result;
        }
        $data = $result->get_data();
        $reason = (is_array($data) && !empty($data['error_code']))
            ? $data['error_code']
            : 'unknown';
        // Header: always set for admin (lightweight, not cached by APM/log tools as body)
        $result->header('X-RAPLSAICH-Debug-Reason', $reason);
        // Body: only when WP_DEBUG is on — prevents debug_reason from persisting
        // in APM/monitoring/reverse-proxy logs that capture response bodies.
        if (is_array($data) && defined('WP_DEBUG') && WP_DEBUG) {
            $data['debug_reason'] = $reason;
            if ($reason === 'unknown') {
                $data['debug_hint'] = 'Check server error logs for details. If you cannot access error logs, contact hosting support and include the copied diagnostics from the plugin settings page.';
            }
            $result->set_data($data);
        }
        // Prevent CDN from caching admin-enriched response and serving to others
        $result->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $result->header('Pragma', 'no-cache');          // Legacy proxy compat (HTTP/1.0)
        $result->header('X-Robots-Tag', 'noindex');     // Prevent accidental indexing of debug responses
        $this->append_header_csv($result, 'Vary', 'Cookie');
        return $result;
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Ensure no-cache headers on ALL responses (including errors) for public GET routes.
        // Using rest_post_dispatch guarantees headers are set regardless of which code path
        // generates the response (success, rate limit, exception, etc.).
        add_filter('rest_post_dispatch', [$this, 'ensure_no_cache_public_gets'], 10, 3);
        add_filter('rest_post_dispatch', [$this, 'add_debug_reason_header'], 20, 3);

        // CORS headers for cross-site embed (iframe uses same-origin, but future script mode needs CORS)
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 4);

        // Get/Create session
        // Public: visitors need sessions before authentication is possible.
        // Defenses: IP-based rate limit (30/min + 5 new/10min per IP), UUID4 validation.
        register_rest_route($this->namespace, '/session', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_session'],
            'permission_callback' => [$this, 'allow_public_access'],
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
                    'validate_callback' => function ($value) {
                        // 8000 chars ≈ 2000 tokens — prevents DoS via giant payloads
                        // hitting AI provider and inflating API cost.
                        $max = (int) apply_filters('raplsaich_max_message_length', 8000);
                        if (mb_strlen((string) $value) > $max) {
                            return new WP_Error(
                                'rest_invalid_param',
                                /* translators: %d: maximum character count */
                                sprintf(__('Message exceeds maximum length of %d characters.', 'rapls-ai-chatbot'), $max),
                                ['status' => 400]
                            );
                        }
                        return true;
                    },
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
                'image' => [
                    'required'          => false,
                    'type'              => 'string',
                    'validate_callback' => [$this, 'validate_image_param'],
                ],
                'file' => [
                    'required'          => false,
                    'type'              => 'string',
                    'validate_callback' => [$this, 'validate_file_param'],
                ],
                'file_name' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_file_name',
                ],
                'bot_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'default',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'client_request_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
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

        // Get lead form configuration
        // Public: widget must query form config before session exists.
        // Defenses: rate limit (30/min), returns only UI flags (no sensitive data).
        register_rest_route($this->namespace, '/lead-config', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_lead_config'],
            'permission_callback' => [$this, 'allow_public_access'],
        ]);

        // Get message limit status
        // Public: widget needs real-time quota check to disable input at Free limit.
        // Defenses: rate limit (30/min), no-cache headers, obfuscates Pro/Free plan info.
        register_rest_route($this->namespace, '/message-limit', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_message_limit_status'],
            'permission_callback' => [$this, 'allow_public_access'],
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
                    'sanitize_callback' => 'intval',
                ],
                'session_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Pro-only routes are registered by the Pro plugin via rest_api_init hook.
        // See rapls-ai-chatbot-pro/includes/class-pro-rest.php for route definitions.
    }

    /**
     * Get or create session
     */
    public function get_session(WP_REST_Request $request): WP_REST_Response {
        $rate_check = $this->check_public_rate_limit('ses', 30, 60);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check, 'error_code' => 'rate_limited'], 429);
        }

        $session_version = get_option('raplsaich_session_version', 1);
        $settings = get_option('raplsaich_settings', []);
        $save_history = !empty($settings['save_history']);

        // Reuse existing session from cookie only when save_history is ON.
        // When OFF, always create a new session so conversations are not carried over.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $existing_session = ($save_history && isset($_COOKIE['raplsaich_session_id'])) ? sanitize_text_field(wp_unslash($_COOKIE['raplsaich_session_id'])) : '';
        if (!empty($existing_session)) {
            // Strict format check: must be UUID4 (8-4-4-4-12 hex)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $existing_session)) {
                // Invalid format — discard and fall through to new session
                $existing_session = '';
            }
        }

        if (!empty($existing_session)) {
            // Short-lived cache to avoid hitting DB on every page view
            $cache_key = 'raplsaich_sess_' . substr(hash('sha256', $existing_session . wp_salt()), 0, 16);
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
            $conversation = RAPLSAICH_Conversation::get_by_session($existing_session);
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
            $transient_key = 'raplsaich_boot_' . substr(hash('sha256', $existing_session . wp_salt()), 0, 32);
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

        // C-1: Stricter rate limit for new session creation (DoS protection).
        // The general /session limit above allows 30/60s for page-view reuse.
        // New session creation is heavier — limit to 10 per 5 minutes per IP.
        $create_check = $this->check_public_rate_limit('snew', 10, 300);
        if ($create_check !== true) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => $create_check,
                'error_code' => 'rate_limited',
            ], 429);
        }

        // IP-only rate limit for new session creation (prevents UA rotation bypass)
        $ip = $this->get_client_ip();
        if (!empty($ip)) {
            $ip_key = 'raplsaich_rl_snew_' . substr(hash('sha256', $ip . wp_salt()), 0, 24);
            $ip_count = $this->get_resilient_counter($ip_key, 600);
            if ($ip_count >= 5) {
                return new WP_REST_Response([
                    'success'    => false,
                    'error'      => __('Too many requests. Please try again later.', 'rapls-ai-chatbot'),
                    'error_code' => 'rate_limited',
                ], 429);
            }
            $this->increment_resilient_counter($ip_key, $ip_count, 600);
        }

        // Generate new session
        $session_id = RAPLSAICH_Conversation::generate_session_id();

        // Set httpOnly cookie for session ownership verification
        $cookie_set = false;
        if (!headers_sent()) {
            /**
             * Filter the SameSite attribute for the session cookie.
             * Default 'Lax' is safe for same-site use. Set to 'None' for cross-site
             * iframe embedding (forces Secure=true). Use raplsaich_allowed_origins filter
             * to also allow the embedding domain's origin.
             *
             * @param string $samesite SameSite attribute ('Lax', 'Strict', or 'None').
             */
            $samesite = apply_filters('raplsaich_cookie_samesite', 'Lax');
            /**
             * Filter the Secure flag for the session cookie.
             * Default: true when SameSite=None (required by spec), otherwise is_ssl().
             * Override for reverse proxy setups where is_ssl() returns false despite HTTPS termination.
             * Also considers wp_is_using_https() as a secondary signal for misdetection.
             *
             * @param bool $secure Whether to set the Secure flag.
             */
            $secure_default = ($samesite === 'None') ? true : (is_ssl() || wp_is_using_https());
            $secure = (bool) apply_filters('raplsaich_cookie_secure', $secure_default);
            setcookie('raplsaich_session_id', $session_id, [
                'expires'  => 0,
                'path'     => '/',
                'httponly'  => true,
                'samesite' => $samesite,
                'secure'   => $secure,
            ]);
            $cookie_set = true;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH: headers_sent() prevented setting session cookie.');
            }
        }

        // Store bootstrap transient ONLY when cookie could not be set
        // (e.g. headers already sent by theme/plugin). This prevents
        // transient flooding if /session is called repeatedly.
        if (!$cookie_set) {
            $ip = $this->get_client_ip();
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            $bootstrap_hash = hash('sha256', $ip . $user_agent . wp_salt());
            $transient_key = 'raplsaich_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
            set_transient($transient_key, $bootstrap_hash, 15 * MINUTE_IN_SECONDS);
        }

        // Generate HMAC session token for cookie-less environments
        $session_token = $this->generate_session_token($session_id);

        return $this->no_cache(new WP_REST_Response([
            'success'         => true,
            'session_id'      => $session_id,
            'session_token'   => $session_token,
            'session_version' => $session_version,
        ], 200));
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
        $session_id        = sanitize_text_field($request->get_param('session_id'));
        $message           = sanitize_textarea_field($request->get_param('message'));
        $page_url          = esc_url_raw($request->get_param('page_url') ?? '');
        $recaptcha_token   = sanitize_text_field($request->get_param('recaptcha_token') ?? '');
        $client_request_id = sanitize_text_field($request->get_param('client_request_id') ?? '');
        $image             = $request->get_param('image');
        $file_data         = $request->get_param('file');
        $file_name         = $request->get_param('file_name') ?? '';

        // Reject image if multimodal is not enabled (Pro feature)
        // Allow if screenshot/screen sharing is enabled
        if (!empty($image)) {
            $pro_settings_check = get_option('raplsaich_settings', [])['pro_features'] ?? [];
            $multimodal_allowed = !empty($pro_settings_check['multimodal_enabled']) || !empty($pro_settings_check['screen_sharing_enabled']);
            if (!$multimodal_allowed) {
                return new WP_REST_Response([
                    'success'    => false,
                    'error'      => __('Image upload is not available.', 'rapls-ai-chatbot'),
                    'error_code' => 'multimodal_disabled',
                ], 400);
            }
        }

        // Handle uploaded file: extract text for plain text files, pass binary to AI for others
        if (!empty($file_data)) {
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $text_extensions = ['txt', 'csv', 'json', 'rtf'];

            if (in_array($ext, $text_extensions, true)) {
                // Plain text files: extract and append to message
                $file_text = $this->extract_file_text($file_data);
                if (!empty($file_text)) {
                    $message = $message . "\n\n---\n" . sprintf(
                        /* translators: %s: file name */
                        __('Uploaded file (%s) content:', 'rapls-ai-chatbot'),
                        $file_name
                    ) . "\n" . $file_text;
                }
                $file_data = null; // Already handled, don't pass to AI
            }
            // For PDF/doc/etc., $file_data stays set and will be passed to AI provider
        }

        // Multi-bot: resolve bot configuration (Pro feature)
        $bot_id = sanitize_key($request->get_param('bot_id') ?? 'default');
        $bot_config = RAPLSAICH_Extensions::get_instance()->resolve_bot_config($bot_id);

        // Multi-bot coordination (intent-based / round-robin routing)
        $bot_config = apply_filters('raplsaich_resolve_bot_config', $bot_config, $message);

        // Session ownership already verified by check_session_permission()

        // Validate input
        $max_length = (int) apply_filters('raplsaich_max_message_length', 8000);
        $message_length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if (empty($message) || $message_length > $max_length) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Message is empty or too long.', 'rapls-ai-chatbot'),
                'error_code' => 'invalid_message',
            ], 400);
        }

        // Dedup: if client_request_id was already processed, return cached result.
        // Prevents double sends from 409 retries or network glitches.
        // Key includes session_id + client_request_id + server salt to prevent
        // cross-session cache poisoning (session ownership already verified above).
        $dedup_key  = '';
        $keyhash    = '';
        if (!empty($client_request_id)) {
            // Note: $blog_id is captured once; assumes no switch_to_blog() mid-request
            // in the REST handler path (standard WordPress REST lifecycle).
            $blog_id    = get_current_blog_id();
            $dedup_hash = hash('sha256', $session_id . $client_request_id . wp_salt() . '|' . $blog_id);
            $keyhash    = substr($dedup_hash, 0, 12);
            // Defensive: if hash() ever returns unexpected result, fall back to
            // a per-site static key so rate limiting still works (DoS protection
            // priority). blog_id isolates sites; log + option counter help diagnose.
            if (strlen($keyhash) < 8) {
                $keyhash = 'fb' . str_pad((string) $blog_id, 10, '0', STR_PAD_LEFT);
                // Two-layer guard: static (per-process) + option-based timestamp
                // (cross-worker, 60s cooldown). Prevents DB write storms when many
                // FPM workers hit the fallback simultaneously.
                // Concurrent requests may both pass the 60s check (no row-level
                // lock); this is acceptable — purpose is detection/throttling,
                // not precise accounting. Count may under- or over-report.
                static $hash_unexpected_logged = false;
                if (!$hash_unexpected_logged) {
                    $hash_unexpected_logged = true;
                    $ts_key  = 'raplsaich_diag_hash_unexpected_ts';
                    $last_ts = (int) get_option($ts_key, 0);
                    $now_ts  = time();
                    if (($now_ts - $last_ts) >= 60) {
                        update_option($ts_key, $now_ts, false);
                        $opt_key = 'raplsaich_diag_hash_unexpected';
                        $count   = (int) get_option($opt_key, 0);
                        update_option($opt_key, $count + 1, false);
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                        error_log(sprintf(
                            'RAPLSAICH dedup: note=hash_unexpected | hash_len=%d | blog_id=%d | total=%d',
                            strlen($dedup_hash),
                            $blog_id,
                            $count + 1
                        ));
                    }
                }
            }
            $dedup_key  = 'raplsaich_dedup_' . substr($dedup_hash, 0, 16);
            $cached_result = get_transient($dedup_key);
            if ($cached_result !== false) {
                // Flag as cache-originated so client can distinguish dedup hits
                // from fresh responses (helps diagnose _truncated false positives).
                $now = time();
                $cached_result['dedup_hit']   = true;
                $cached_result['_server_now'] = $now;

                // Detect anomalous dedup hits: malformed data, missing timestamps,
                // or stale entries past transient TTL. malformed_data always logs
                // (indicates corruption); other anomalies gated on should_log_dedup.
                $dedup_data_arr = $cached_result['data'] ?? null;
                $saved_at       = is_array($dedup_data_arr) ? ($dedup_data_arr['_saved_at'] ?? 0) : 0;
                $log_reason     = '';
                if (!is_array($dedup_data_arr)) {
                    $log_reason = 'malformed_data';
                } elseif ($saved_at <= 0) {
                    $log_reason = 'missing_saved_at';
                } elseif (($now - $saved_at) >= 90) {
                    $log_reason = 'stale_age';
                }
                // malformed_data = corruption, rate-limited to 1/10s per source
                // to prevent log DoS while preserving per-source diagnostics.
                // Other anomalies are operational noise, gated on should_log_dedup.
                $should_log = $this->should_log_dedup();
                if ($log_reason === 'malformed_data') {
                    // Per-source rate key: blog_id + keyhash (12 chars) for isolation.
                    // Debug mode: 3s cooldown (diagnostic). Non-debug: 10s cooldown.
                    $rate_key    = 'raplsaich_mf_' . $blog_id . '_' . $keyhash;
                    $cooldown    = $should_log ? 3 : 10;
                    $last_logged = (int) get_transient($rate_key);
                    // Static fallback: if object cache is unreliable during anomaly,
                    // transient may fail. In-process static array guarantees at least
                    // per-request rate limiting even without working cache backend.
                    static $mf_rate_cache = [];
                    $static_last = $mf_rate_cache[$rate_key] ?? 0;
                    $effective_last = max($last_logged, $static_last);
                    if ($now - $effective_last >= $cooldown) {
                        set_transient($rate_key, $now, $cooldown * 3);
                        $mf_rate_cache[$rate_key] = $now;
                        $should_log = true;
                    } else {
                        $should_log = false; // suppress duplicate within cooldown
                    }
                }
                if (!empty($log_reason) && $should_log) {
                    $log_extra = '';
                    if ($log_reason === 'malformed_data') {
                        $log_extra = sprintf(' | data_type=%s', gettype($dedup_data_arr));
                    } elseif ($saved_at > 0) {
                        $log_extra = sprintf(' | saved_at=%d | now=%d | note=clock_skew_possible', $saved_at, $now);
                    }
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'RAPLSAICH dedup: anomaly=%s | keyhash=%s | age=%s | object_cache=%s%s',
                        $log_reason,
                        $keyhash,
                        ($saved_at > 0) ? ($now - $saved_at) . 's' : 'n/a',
                        wp_using_ext_object_cache() ? 'yes' : 'no',
                        $log_extra
                    ));
                }

                $dedup_response = new WP_REST_Response($cached_result, 200);
                $this->append_header_csv($dedup_response, 'Vary', 'Cookie');
                return $this->no_cache($dedup_response);
            }
        }

        // Verify reCAPTCHA
        $recaptcha_result = $this->verify_recaptcha($recaptcha_token, 'chat');
        if (is_wp_error($recaptcha_result)) {
            $response_data = [
                'success'    => false,
                'error'      => $recaptcha_result->get_error_message(),
                'error_code' => $recaptcha_result->get_error_code(),
            ];
            $error_data = $recaptcha_result->get_error_data();
            if (!empty($error_data['google_error_codes'])) {
                $response_data['recaptcha_error_codes'] = $error_data['google_error_codes'];
            }
            return new WP_REST_Response($response_data, 403);
        }

        // Check rate limit (Pro enhanced or basic)
        // Bypass rate limit for handoff keyword messages so users can always reach support
        $is_handoff = RAPLSAICH_Extensions::get_instance()->is_handoff_keyword($message);
        if (!$is_handoff) {
            $rate_limit_result = $this->check_rate_limit();
            if ($rate_limit_result !== true) {
                do_action('raplsaich_rate_limit_exceeded', $this->get_client_ip());
                $rate_limit_msg = is_string($rate_limit_result) && $rate_limit_result !== ''
                    ? $rate_limit_result
                    : __('Too many messages. Please wait a moment before sending again.', 'rapls-ai-chatbot');
                return new WP_REST_Response([
                    'success'    => false,
                    'error'      => $rate_limit_msg,
                    'error_code' => 'rate_limited',
                ], 429);
            }
        }

        $pro_features = RAPLSAICH_Extensions::get_instance();

        /**
         * Filter: Pre-chat validation hook.
         * Pro plugin uses this for IP blocking, banned words, spam detection, budget limits, etc.
         * Return a WP_REST_Response to reject the message, or null to continue.
         *
         * @param WP_REST_Response|null $result  Null to continue, WP_REST_Response to reject.
         * @param string               $message The user's message.
         * @param WP_REST_Request       $request The full REST request.
         */
        $pre_check = apply_filters('raplsaich_pre_chat_check', null, $message, $request);
        if ($pre_check instanceof WP_REST_Response) {
            return $pre_check;
        }

        // Business hours message (deferred until after user message is saved)
        $unavailable_message = apply_filters('raplsaich_unavailable_message', null);

        // Pre-check API key
        $settings = get_option('raplsaich_settings', []);
        // Bot config may override the provider
        $provider_name = (is_array($bot_config) && !empty($bot_config['ai_provider']))
            ? $bot_config['ai_provider']
            : ($settings['ai_provider'] ?? 'openai');

        switch ($provider_name) {
            case 'claude':
                $api_key = $this->decrypt_api_key($settings['claude_api_key'] ?? '');
                break;
            case 'gemini':
                $api_key = $this->decrypt_api_key($settings['gemini_api_key'] ?? '');
                break;
            case 'openrouter':
                $api_key = $this->decrypt_api_key($settings['openrouter_api_key'] ?? '');
                break;
            default:
                $api_key = $this->decrypt_api_key($settings['openai_api_key'] ?? '');
                break;
        }

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('AI API key is not configured. Please configure it in the admin settings.', 'rapls-ai-chatbot'),
                'error_code' => 'api_key_missing',
            ], 400);
        }

        try {
            $save_history = !empty($settings['save_history']);
            $conversation = null;
            $conversation_id = 0;

            if ($save_history) {
                // Get or create conversation
                $conversation = RAPLSAICH_Conversation::get_or_create($session_id, [
                    'page_url'   => $page_url,
                    'visitor_ip' => $this->get_client_ip(),
                    'bot_id'     => $bot_id,
                ]);

                if (!$conversation) {
                    return new WP_REST_Response([
                        'success'    => false,
                        'error'      => __('Failed to create conversation session.', 'rapls-ai-chatbot'),
                        'error_code' => 'conversation_failed',
                    ], 500);
                }
                $conversation_id = $conversation['id'];

                // Save uploaded image to media library for admin review
                $saved_image_url = '';
                if (!empty($image)) {
                    $saved_image_url = $this->save_image_to_media($image, $conversation_id);
                }

                // Save user message (with image URL marker if present)
                $save_content = $message;
                if ($saved_image_url) {
                    $save_content .= "\n[image:" . $saved_image_url . ']';
                }
                RAPLSAICH_Message::create([
                    'conversation_id' => $conversation_id,
                    'role'            => 'user',
                    'content'         => $save_content,
                ]);
            } else {
                // save_history OFF — store context in transient only
                $this->append_transient_context($session_id, 'user', $message);
            }

            // Return business hours / holiday message (after saving user message)
            if ($unavailable_message !== null) {
                $unavail_msg_id = 0;
                if ($save_history) {
                    $ai_message = RAPLSAICH_Message::create([
                        'conversation_id' => $conversation_id,
                        'role'            => 'assistant',
                        'content'         => $unavailable_message,
                    ]);
                    $unavail_msg_id = $ai_message ? $ai_message['id'] : 0;
                }
                return $this->no_cache(new WP_REST_Response([
                    'success' => true,
                    'data'    => [
                        'message_id'  => $unavail_msg_id,
                        'content'     => $unavailable_message,
                        'is_auto'     => true,
                        'sources'     => [],
                        'session_id'  => $session_id,
                    ],
                ], 200));
            }

            // Check message limit — if reached, try FAQ fallback instead of AI
            // get_monthly_ai_response_count() includes no-history counter automatically
            if ($pro_features->is_limit_reached()) {
                $search_engine = new RAPLSAICH_Search_Engine();
                // Apply bot-specific knowledge filter for FAQ fallback
                $faq_use_knowledge = $bot_config['use_knowledge'] ?? true;
                if (!empty($bot_config['knowledge_categories'])) {
                    $search_engine->set_bot_filters($bot_config['knowledge_categories'], false, $faq_use_knowledge);
                } elseif (!$faq_use_knowledge) {
                    $search_engine->set_bot_filters([], false, false);
                }
                $faq_results = $search_engine->search_knowledge_only($message, 3);
                $faq_answer = $this->extract_faq_answer($faq_results, $message);

                if (empty($faq_answer)) {
                    $faq_answer = __('Unable to generate an AI response at this time. Please try again later.', 'rapls-ai-chatbot');
                }

                // Save synthetic assistant message (only when history is enabled)
                $limit_msg_id = 0;
                if ($save_history) {
                    $ai_message = RAPLSAICH_Message::create([
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

            // Check if conversation is in handoff or message triggers handoff — skip AI call
            $handoff_response = apply_filters('raplsaich_pre_ai_handoff_check', null, $conversation_id ?? 0, $session_id, $message);
            if (is_array($handoff_response)) {
                return $this->no_cache(new WP_REST_Response([
                    'success' => true,
                    'data'    => $handoff_response,
                ], 200));
            }

            // Get AI provider (bot config may override provider/model)
            $ai_provider = $this->get_ai_provider($bot_config);

            // Search related content
            $search_engine = new RAPLSAICH_Search_Engine();
            // Apply bot-specific knowledge/crawl filters
            $bot_use_knowledge = $bot_config['use_knowledge'] ?? true;
            $bot_use_crawl = $bot_config['use_site_crawl'] ?? true;
            if (!empty($bot_config['knowledge_categories'])) {
                $search_engine->set_bot_filters(
                    $bot_config['knowledge_categories'],
                    $bot_use_crawl,
                    $bot_use_knowledge
                );
            } elseif (!$bot_use_knowledge || !$bot_use_crawl) {
                $search_engine->set_bot_filters([], $bot_use_crawl, $bot_use_knowledge);
            }
            $related_content = $search_engine->search($message, $settings['crawler_max_results'] ?? 3);
            $context = $search_engine->build_context($related_content, $this->get_max_context_chars(), $message);

            // Notify Pro features about knowledge hits (for auto-priority)
            do_action('raplsaich_knowledge_hits', $related_content);

            // Response cache check (Pro feature)
            $ext_settings = $settings['pro_features'] ?? [];
            $cache_enabled = !empty($ext_settings['response_cache_enabled']);
            $cache_hash = null;

            if ($cache_enabled) {
                $cache_ttl = (int) ($ext_settings['cache_ttl_days'] ?? 7);
                $cache_hash = RAPLSAICH_Message::build_cache_hash($message, $context, $bot_id);
                $cached = RAPLSAICH_Message::find_cached_response($cache_hash, $cache_ttl);

                if ($cached) {
                    $cache_msg_id = 0;

                    if ($save_history) {
                        // Cache hit — save a copy as the new assistant message
                        $ai_message = RAPLSAICH_Message::create([
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
                            RAPLSAICH_Message::store_cache_hash((int) $ai_message['id'], $cache_hash);
                            global $wpdb;
                            $msg_table = trim(raplsaich_validated_table('raplsaich_messages'), '`');
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->update($msg_table, ['cache_hit' => 1], ['id' => $ai_message['id']], ['%d'], ['%d']);
                        }
                    } else {
                        // save_history OFF — store in transient
                        $this->append_transient_context($session_id, 'assistant', $cached['content']);
                        $this->increment_no_history_monthly_count();
                    }

                    // Get source URLs (filter by display mode)
                    $cache_sources_mode = $settings['sources_display_mode'] ?? 'matched';
                    $cache_sources = [];
                    if ($cache_sources_mode === 'all') {
                        // Show all indexed page URLs
                        foreach ($search_engine->get_indexed_urls() as $url) {
                            $url = esc_url_raw($url, ['http', 'https']);
                            if ($url) {
                                $cache_sources[] = $url;
                            }
                        }
                    } elseif ($cache_sources_mode === 'matched' && is_array($related_content)) {
                        foreach ($related_content as $rc) {
                            if (empty($rc['url']) || empty($rc['keyword_matched'])) {
                                continue;
                            }
                            $url = esc_url_raw((string) $rc['url'], ['http', 'https']);
                            if ($url) {
                                $cache_sources[] = $url;
                            }
                        }
                        $cache_sources = array_values(array_unique($cache_sources));
                    }
                    $remaining_messages = $pro_features->get_remaining_messages();

                    $cached_content = apply_filters('raplsaich_ai_response', $cached['content'], $message, $settings);

                    $cache_response_data = [
                        'message_id'         => $cache_msg_id,
                        'content'            => $cached_content,
                        'tokens_used'        => (int) ($cached['tokens_used'] ?? 0),
                        'tokens_billed'      => 0,
                        'sources'            => $cache_sources,
                        'remaining_messages' => $remaining_messages === PHP_INT_MAX ? null : $remaining_messages,
                        'cached'             => true,
                        'session_id'         => $session_id,
                    ];

                    // Build content cards for cache path (same logic as main path)
                    $cache_card_items = ($cache_sources_mode === 'all')
                        ? $search_engine->get_indexed_pages()
                        : $related_content;
                    if ($cache_sources_mode !== 'none' && is_array($cache_card_items) && !empty($cache_card_items)) {
                        $cache_cards = [];
                        foreach ($cache_card_items as $item) {
                            if (($item['type'] ?? '') !== 'index') {
                                continue;
                            }
                            if ($cache_sources_mode === 'matched' && empty($item['keyword_matched'])) {
                                continue;
                            }
                            $pt = $item['post_type'] ?? '';
                            if ($pt === 'product' || $pt === 'product_variation') {
                                continue;
                            }
                            $url = $item['url'] ?? '';
                            if (empty($url)) {
                                continue;
                            }
                            $cache_cards[] = [
                                'title'   => $item['title'] ?? '',
                                'url'     => esc_url_raw($url, ['http', 'https']),
                                'excerpt' => wp_trim_words(wp_strip_all_tags($item['content'] ?? ''), 20, '…'),
                                'type'    => $pt ?: 'page',
                            ];
                            if (count($cache_cards) >= 3) {
                                break;
                            }
                        }
                        if (!empty($cache_cards)) {
                            $cache_response_data['content_cards'] = $cache_cards;
                        }
                    }

                    /** This filter is documented above in the main response path. */
                    $cache_response_data = apply_filters('raplsaich_chat_response_data', $cache_response_data, $related_content, $message);

                    return new WP_REST_Response([
                        'success' => true,
                        'data'    => $cache_response_data,
                    ], 200);
                }
            }

            // Build system prompt (bot config may override)
            $bot_no_knowledge = is_array($bot_config) && empty($bot_config['use_knowledge']) && empty($bot_config['use_site_crawl']);
            if (is_array($bot_config) && !empty($bot_config['system_prompt'])) {
                $system_prompt = $bot_config['system_prompt'];
            } elseif ($bot_no_knowledge) {
                // Bot has knowledge/crawl disabled — use generic prompt, not global (which may contain site info)
                $system_prompt = "You are a helpful assistant. Please answer user questions politely.\n\nIMPORTANT: You do NOT have access to any knowledge base or site content. Do NOT answer questions about specific companies, products, services, or site content. If asked about such topics, politely explain that you don't have that information.";
            } else {
                $system_prompt = $settings['system_prompt'] ?? 'You are a helpful assistant. Please answer user questions politely.';
            }

            /**
             * Filter the system prompt sent to the AI provider.
             *
             * @param string $system_prompt The system prompt.
             * @param array  $settings      The plugin settings.
             */
            $system_prompt = apply_filters('raplsaich_system_prompt', $system_prompt, $settings);

            // Response language instruction
            $response_lang = $settings['response_language'] ?? '';
            if ($response_lang === 'auto') {
                $system_prompt .= "\n\nIMPORTANT: Always detect the language of the user's message and respond in that same language.";
            } elseif (!empty($response_lang)) {
                $lang_names = [
                    'en' => 'English', 'ja' => 'Japanese', 'zh' => 'Chinese',
                    'ko' => 'Korean', 'es' => 'Spanish', 'fr' => 'French',
                    'de' => 'German', 'pt' => 'Portuguese', 'it' => 'Italian',
                    'ru' => 'Russian', 'ar' => 'Arabic', 'th' => 'Thai',
                    'vi' => 'Vietnamese',
                ];
                $lang_name = $lang_names[$response_lang] ?? $response_lang;
                $system_prompt .= "\n\nIMPORTANT: Always respond in {$lang_name}.";
            }

            // Sentiment analysis hook (Pro adds via filter)
            $system_prompt = apply_filters('raplsaich_system_prompt_sentiment', $system_prompt, $message);

            // Add feedback examples for learning (if feedback is enabled)
            // Skip feedback examples when bot has knowledge/crawl disabled (prevents info leak from other bots)
            if (!empty($settings['show_feedback_buttons']) && !$bot_no_knowledge) {
                $feedback_prompt = '';

                // Positive examples - what works well
                $positive_examples = RAPLSAICH_Message::get_positive_feedback_examples(3);
                if (!empty($positive_examples)) {
                    $good_header = $settings['feedback_good_header'] ?? "[LEARNING FROM USER FEEDBACK - GOOD EXAMPLES]\nThe following responses received positive feedback. Use these as examples of good responses:";
                    $feedback_prompt .= "\n\n" . $good_header . "\n";
                    foreach ($positive_examples as $idx => $example) {
                        $feedback_prompt .= sprintf("\nGood Example %d:\nQ: %s\nA: %s\n", $idx + 1, $example['question'], $example['answer']);
                    }
                }

                // Negative examples - what to avoid
                $negative_examples = RAPLSAICH_Message::get_negative_feedback_examples(2);
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

            // Context memory hook (Pro adds via filter)
            $session_id_for_context = sanitize_text_field($request->get_param('session_id') ?? '');
            $system_prompt = apply_filters('raplsaich_system_prompt_context', $system_prompt, $session_id_for_context);
            $web_search_enabled = !empty($settings['web_search_enabled']);

            /**
             * Filter the RAG context before injection into the system prompt.
             *
             * @param string $context  The context from site learning and knowledge base.
             * @param string $message  The user's message.
             * @param array  $settings The plugin settings.
             */
            $context = apply_filters('raplsaich_context', $context, $message, $settings);

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
                    $site_prompt = $settings['site_context_prompt'] ?? "[IMPORTANT: Reference Information]\nBelow is reference information from this site's knowledge base. You MUST use this as the primary source when answering.\n- Search the ENTIRE reference information thoroughly before concluding that no relevant data exists.\n- The user's wording may differ from the reference text (e.g. \"料金プラン\" vs \"料金体系\", \"price\" vs \"pricing\"). Match by MEANING, not exact keywords.\n- If ANY part of the reference information is relevant to the user's question, use it to answer.\n- Only say you don't have the information if, after careful review, absolutely nothing in the reference is related.\n\n{context}";
                    $system_prompt .= "\n\n" . str_replace('{context}', $context, $site_prompt);
                }
            }

            // Web search instruction — strength depends on whether knowledge base had relevant content
            if ($web_search_enabled) {
                if (empty($context)) {
                    // No knowledge base context — force web search
                    $system_prompt .= "\n\n[WEB SEARCH — MANDATORY]\nNo reference information is available for this question. You MUST use your web search tool to find current, accurate information before answering. Do NOT answer from memory or training data alone — ALWAYS search the web first.";
                } else {
                    // Knowledge base context available — web search as supplement
                    $system_prompt .= "\n\n[WEB SEARCH]\nYou have access to a web search tool. If the reference information above does not fully answer the question, use web search to find additional or more current information. For questions about dates, recent events, or current status, ALWAYS search the web.";
                }
            }

            // Get conversation history
            $history_count = absint($settings['message_history_count'] ?? 10);
            $history = $save_history
                ? RAPLSAICH_Message::get_context_messages($conversation_id, $history_count)
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

            // Send to AI (clamp settings to safe ranges; bot config may override)
            $bot_max_tokens = is_array($bot_config) ? ($bot_config['max_tokens'] ?? null) : null;
            $bot_temperature = is_array($bot_config) ? ($bot_config['temperature'] ?? null) : null;
            $max_tokens = max(1, min(16384, (int) ($bot_max_tokens ?? $settings['max_tokens'] ?? 1000)));
            $temperature = max(0.0, min(2.0, (float) ($bot_temperature ?? $settings['temperature'] ?? 0.7)));

            // Generate request ID at the REST layer for response header correlation
            $request_id = wp_generate_uuid4();

            $send_options = [
                'max_tokens'   => $max_tokens,
                'temperature'  => $temperature,
                '_request_id'  => $request_id,
            ];
            if ($web_search_enabled) {
                $send_options['web_search'] = true;
                // Force web search when knowledge base has no relevant content
                if (empty($context)) {
                    $send_options['force_web_search'] = true;
                }
            }

            // Pass image data to AI provider for multimodal vision
            if (!empty($image)) {
                $send_options['image'] = $image;
            }

            // Pass file data to AI provider (PDF, docx, etc.)
            if (!empty($file_data)) {
                $send_options['file'] = $file_data;
                $send_options['file_name'] = $file_name;
            }

            // Queue management: check availability before AI call
            $queue_check = apply_filters('raplsaich_queue_check', ['allowed' => true], $request_id);
            if (!$queue_check['allowed']) {
                return new WP_REST_Response([
                    'success'      => false,
                    'error'        => sprintf(
                        /* translators: %d: queue position */
                        __('The server is busy. You are #%d in the queue. Please wait a moment.', 'rapls-ai-chatbot'),
                        $queue_check['position'] ?? 1
                    ),
                    'error_code'   => 'queue_full',
                    'queue_position' => $queue_check['position'] ?? 1,
                    'retry_after'  => $queue_check['wait_seconds'] ?? 3,
                ], 503);
            }

            do_action('raplsaich_ai_request_start', $request_id);

            try {
                $response = $ai_provider->send_message($messages, $send_options);
            } finally {
                do_action('raplsaich_ai_request_end', $request_id);
            }

            // Save AI response
            $resp_msg_id = 0;
            if ($save_history) {
                $ai_message = RAPLSAICH_Message::create([
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
                    RAPLSAICH_Message::store_cache_hash($resp_msg_id, $cache_hash);
                }
            } else {
                // save_history OFF — store in transient and increment counter
                $this->append_transient_context($session_id, 'assistant', $response['content']);
                $this->increment_no_history_monthly_count();
            }

            // Budget alert check (Pro feature)
            $msg_cost = RAPLSAICH_Cost_Calculator::calculate_cost(
                $response['model'] ?? '',
                $response['input_tokens'] ?? 0,
                $response['output_tokens'] ?? 0
            );
            $pro_features->maybe_send_budget_alert($msg_cost);

            // Get source URLs (filter by display mode)
            $sources_mode = $settings['sources_display_mode'] ?? 'matched';
            $sources = [];
            if ($sources_mode === 'all') {
                // Show all indexed page URLs
                foreach ($search_engine->get_indexed_urls() as $url) {
                    $url = esc_url_raw($url, ['http', 'https']);
                    if ($url) {
                        $sources[] = $url;
                    }
                }
            } elseif ($sources_mode === 'matched' && is_array($related_content)) {
                foreach ($related_content as $rc) {
                    if (empty($rc['url']) || empty($rc['keyword_matched'])) {
                        continue;
                    }
                    $url = esc_url_raw((string) $rc['url'], ['http', 'https']);
                    if ($url) {
                        $sources[] = $url;
                    }
                }
                $sources = array_values(array_unique($sources));
            }

            // Notify extensions of new message (Slack, etc.)
            if ($save_history && $conversation) {
                do_action('raplsaich_new_message', $conversation, $message, $response['content']);
            }

            // Trigger webhook for new message (Pro feature)
            if ($save_history && $conversation && class_exists('RAPLSAICH_Webhook')) {
                try {
                    $webhook = RAPLSAICH_Webhook::get_instance();
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
            $response['content'] = apply_filters('raplsaich_ai_response', $response['content'], $message, $settings);

            // Build response data
            $response_data = [
                'message_id'    => $resp_msg_id,
                'content'       => $response['content'],
                'tokens_used'   => $response['tokens_used'],
                'tokens_billed' => $response['tokens_used'],
                'sources'       => $sources,
                'remaining_messages' => $remaining_messages === PHP_INT_MAX ? null : $remaining_messages,
                'session_id'    => $session_id,
            ];

            // Include resolved bot_id when multi-bot is active
            $resolved_bot_slug = is_array($bot_config) ? ($bot_config['slug'] ?? $bot_id) : $bot_id;
            if ($resolved_bot_slug !== 'default') {
                $response_data['bot_id'] = $resolved_bot_slug;
            }

            // Add web search sources if present
            if (!empty($response['web_sources'])) {
                $response_data['web_sources'] = $response['web_sources'];
            }

            // Add sentiment to response if sentiment analysis is enabled
            $sentiment_enabled = $pro_features->is_sentiment_analysis_enabled();
            if ($sentiment_enabled && !empty($sentiment) && $sentiment !== 'neutral') {
                $response_data['sentiment'] = $sentiment;
            }

            // Build content cards from RAG sources (non-product pages)
            // Respect sources_display_mode: skip content cards when set to "none"
            $card_items = ($sources_mode === 'all')
                ? $search_engine->get_indexed_pages()
                : $related_content;
            if ($sources_mode !== 'none' && is_array($card_items) && !empty($card_items)) {
                $content_cards = [];
                foreach ($card_items as $item) {
                    if (($item['type'] ?? '') !== 'index') {
                        continue;
                    }
                    if ($sources_mode === 'matched' && empty($item['keyword_matched'])) {
                        continue;
                    }
                    $pt = $item['post_type'] ?? '';
                    if ($pt === 'product' || $pt === 'product_variation') {
                        continue;
                    }
                    $url = $item['url'] ?? '';
                    if (empty($url)) {
                        continue;
                    }
                    $content_cards[] = [
                        'title'   => $item['title'] ?? '',
                        'url'     => esc_url_raw($url, ['http', 'https']),
                        'excerpt' => wp_trim_words(wp_strip_all_tags($item['content'] ?? ''), 20, '…'),
                        'type'    => $pt ?: 'page',
                    ];
                    if (count($content_cards) >= 3) {
                        break;
                    }
                }
                if (!empty($content_cards)) {
                    $response_data['content_cards'] = $content_cards;
                }
            }

            /**
             * Filter the chat response data before returning to the client.
             * Pro plugins can use this to enrich the response (e.g. product cards).
             *
             * @param array  $response_data   The response data array.
             * @param array  $related_content  The search results used for context.
             * @param string $message          The user's original message.
             */
            $response_data = apply_filters('raplsaich_chat_response_data', $response_data, $related_content, $message);

            $result_body = [
                'success'     => true,
                'data'        => $response_data,
                '_server_now' => time(),
            ];

            // Cache result for dedup (60s window for 409 retries / network glitches).
            // Store minimal data — sources trimmed to top 5 for cache only (the
            // full source list is returned in $result_body above). If still > 32KB,
            // fall back to a "done marker" so the dedup key exists and prevents a
            // second AI call.
            if (!empty($dedup_key)) {
                $dedup_sources = $response_data['sources'] ?? [];
                if (count($dedup_sources) > 5) {
                    $dedup_sources = array_slice($dedup_sources, 0, 5);
                }
                $dedup_data_inner = [
                    'message_id'  => $response_data['message_id'] ?? 0,
                    'content'     => $response_data['content'] ?? '',
                    'tokens_used' => $response_data['tokens_used'] ?? 0,
                    'sources'     => $dedup_sources,
                    '_saved_at'   => time(),
                ];
                // Include product cards in dedup cache if present
                if (!empty($response_data['product_cards'])) {
                    $dedup_data_inner['product_cards'] = $response_data['product_cards'];
                }
                // Include content cards in dedup cache if present
                if (!empty($response_data['content_cards'])) {
                    $dedup_data_inner['content_cards'] = $response_data['content_cards'];
                }
                $dedup_data = [
                    'success' => true,
                    'data'    => $dedup_data_inner,
                ];
                $encoded = wp_json_encode($dedup_data);
                $dedup_size = ($encoded !== false) ? strlen($encoded) : 0;
                if ($encoded === false || $dedup_size > 32768) {
                    // Payload too large for DB-backed transient — store a minimal
                    // "done marker" so the dedup key exists (prevents double AI call)
                    // but omit the heavy content/sources. client_request_id included
                    // as fallback lookup key when message_id is 0 (save_history OFF).
                    $dedup_data = [
                        'success' => true,
                        'data'    => [
                            'message_id'        => $response_data['message_id'] ?? 0,
                            'content'           => '',
                            'tokens_used'       => $response_data['tokens_used'] ?? 0,
                            'sources'           => [],
                            '_truncated'        => true,
                            'client_request_id' => $client_request_id,
                            '_history_saved'    => $save_history,
                            '_saved_at'         => time(),
                        ],
                    ];
                    $dedup_size = 256; // done marker is always small
                }
                $stored = set_transient($dedup_key, $dedup_data, 60);
                if (!$stored && $this->should_log_dedup()) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'RAPLSAICH dedup: set_transient failed | keyhash=%s | size=%d | object_cache=%s',
                        $keyhash,
                        $dedup_size,
                        wp_using_ext_object_cache() ? 'yes' : 'no'
                    ));
                }
            }

            $rest_response = new WP_REST_Response($result_body, 200);
            // Expose request ID in response header for debugging/support correlation
            if (!empty($request_id)) {
                $rest_response->header('X-RAPLSAICH-Request-Id', $request_id);
            }
            // Prevent CDN/cache plugins from caching per-user AI responses
            $this->append_header_csv($rest_response, 'Vary', 'Cookie');
            return $this->no_cache($rest_response);

        } catch (RAPLSAICH_Quota_Exceeded_Exception $e) {
            // Return custom quota error message from settings
            $quota_message = $settings['quota_error_message'] ?? 'Currently recharging. Please try again later.';
            $quota_body = [
                'success' => false,
                'error'   => $quota_message,
            ];
            $retry_after = $e->get_retry_after();
            if ($retry_after > 0) {
                $quota_body['retry_after'] = $retry_after;
            }
            return new WP_REST_Response($quota_body, 503);

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $code = $e->getCode();

            // Log detailed error for admin debugging (never log API keys).
            // Rate-limited: under API outages, every chat request would trigger this.
            raplsaich_rate_limited_log(
                'chat_error_' . $code,
                sprintf('RAPLSAICH Chat Error [%d]: %s (request_id=%s)', $code, $error_message, $request_id)
            );

            do_action('raplsaich_ai_error', $code, $error_message);

            // Build response body — include request_id for admin debugging
            $body = ['success' => false];
            if (current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
                $body['debug'] = ['request_id' => $request_id, 'error_code' => $code];
            }

            // 401/403: Authentication / API key errors
            if ($code === 401 || $code === 403 || strpos($error_message, 'API key') !== false) {
                $body['error'] = __('The AI service is not configured correctly. Please contact the site administrator.', 'rapls-ai-chatbot');
                return new WP_REST_Response($body, 500);
            }

            // 404: Model not found / deprecated
            if ($code === 404 || stripos($error_message, 'not found') !== false || stripos($error_message, 'deprecated') !== false) {
                $body['error'] = __('The AI model is currently unavailable. Please contact the site administrator.', 'rapls-ai-chatbot');
                return new WP_REST_Response($body, 500);
            }

            // 409 Conflict: return retryable status so client JS can retry with backoff
            if ($code === 409) {
                $body['error'] = __('Temporary conflict. Please try again.', 'rapls-ai-chatbot');
                $body['retryable'] = true;
                return new WP_REST_Response($body, 409);
            }

            // Timeout / network errors (RAPLSAICH_Communication_Exception)
            if ($e instanceof RAPLSAICH_Communication_Exception) {
                $body['error'] = __('Could not reach the AI service. Please check your connection and try again.', 'rapls-ai-chatbot');
                $body['retryable'] = true;
                return new WP_REST_Response($body, 504);
            }

            // 5xx: Server-side errors from the AI provider
            if ($code >= 500 && $code < 600) {
                $body['error'] = __('The AI service is temporarily unavailable. Please try again later.', 'rapls-ai-chatbot');
                $body['retryable'] = true;
                return new WP_REST_Response($body, 503);
            }

            // Generic fallback — unknown or unclassified error
            $body['error'] = __('Sorry, an error occurred while processing your request. Please try again later.', 'rapls-ai-chatbot');
            if (current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
                $body['error'] .= ' ' . sprintf(
                    /* translators: %s: request ID for support reference */
                    __('(Admin: request_id=%s — check error log for details)', 'rapls-ai-chatbot'),
                    $request_id
                );
            }
            return new WP_REST_Response($body, 500);
        }
    }

    /**
     * Get conversation history
     */
    public function get_history(WP_REST_Request $request): WP_REST_Response {
        // When save_history is OFF, no messages are stored — return empty
        $settings = get_option('raplsaich_settings', []);
        if (empty($settings['save_history'])) {
            return new WP_REST_Response([
                'success'  => true,
                'messages' => [],
            ], 200);
        }

        $session_id = sanitize_text_field($request->get_param('session_id'));

        $conversation = RAPLSAICH_Conversation::get_by_session($session_id);

        if (!$conversation) {
            return new WP_REST_Response([
                'success'  => true,
                'messages' => [],
            ], 200);
        }

        // Session ownership already verified by check_session_permission()

        $messages = RAPLSAICH_Message::get_by_conversation($conversation['id']);

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
    private function get_ai_provider(?array $bot_config = null): RAPLSAICH_AI_Provider_Interface {
        $settings = get_option('raplsaich_settings', []);
        return raplsaich_create_ai_provider($settings, $bot_config);
    }

    /**
     * Get max context characters based on the configured model.
     * Conservative limits (~25% of model token window) to leave room for system prompt + response.
     */
    private function get_max_context_chars(): int {
        $settings = get_option('raplsaich_settings', []);
        $provider = $settings['ai_provider'] ?? 'openai';

        switch ($provider) {
            case 'openai':
                $model = $settings['openai_model'] ?? 'gpt-4o-mini';
                // GPT-4.1 and o-series have 128K+ context
                if (strpos($model, 'gpt-4.1') === 0 || preg_match('/^o[1-9]/', $model)) {
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
                $model = $settings['gemini_model'] ?? 'gemini-2.0-flash';
                // Gemini 2.0 Flash Lite: smaller context
                if (strpos($model, 'flash-lite') !== false) {
                    return 15000;
                }
                // Gemini Pro/Flash: 1M+ context
                return 40000;

            case 'openrouter':
                // Conservative default; actual context varies by model
                return 30000;

            default:
                return 20000;
        }
    }

    /**
     * Decrypt API key — delegates to global helper.
     */
    private function decrypt_api_key(string $encrypted): string {
        return raplsaich_decrypt_api_key($encrypted);
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

    /**
     * Validate uploaded file parameter (PDF, Word, etc.)
     */
    public function validate_file_param( $value, $request, $param ) {
        if (empty($value)) {
            return true;
        }

        // Must be a data URI
        if (!preg_match('/^data:([^;]+);base64,/', $value, $matches)) {
            return new WP_Error('invalid_file', __('Invalid file format.', 'rapls-ai-chatbot'));
        }

        $settings = get_option('raplsaich_settings', []);
        $ext_settings = $settings['pro_features'] ?? [];
        if (empty($ext_settings['file_upload_enabled'])) {
            return new WP_Error('file_upload_disabled', __('File upload is not enabled.', 'rapls-ai-chatbot'));
        }

        // Check file size
        $max_size_kb = (int) ($ext_settings['file_upload_max_size'] ?? 5120);
        $comma_pos = strpos($value, ',');
        if ($comma_pos === false) {
            return new WP_Error('invalid_file', __('Invalid file data.', 'rapls-ai-chatbot'));
        }

        $base64_data = substr($value, $comma_pos + 1);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $decoded = base64_decode($base64_data, true);
        if ($decoded === false) {
            return new WP_Error('invalid_file', __('Invalid file data.', 'rapls-ai-chatbot'));
        }

        if (strlen($decoded) > $max_size_kb * 1024) {
            return new WP_Error('file_too_large', sprintf(
                /* translators: %d: maximum file size in KB */
                __('File is too large. Maximum size is %dKB.', 'rapls-ai-chatbot'),
                $max_size_kb
            ));
        }

        // Validate MIME type against allowed file types
        $allowed_types = $ext_settings['file_upload_types'] ?? ['pdf', 'doc', 'docx', 'txt', 'csv'];
        $ext_to_mime = [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/csv', 'text/plain', 'application/csv'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'rtf'  => ['application/rtf', 'text/rtf'],
            'json' => ['application/json'],
        ];

        $allowed_mimes = [];
        foreach ($allowed_types as $ext) {
            if (isset($ext_to_mime[$ext])) {
                $allowed_mimes = array_merge($allowed_mimes, $ext_to_mime[$ext]);
            }
        }

        $mime = $matches[1];
        // Also check with finfo for safety
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $real_mime = finfo_buffer($finfo, $decoded);
                finfo_close($finfo);
                if ($real_mime !== false) {
                    $mime = $real_mime;
                }
            }
        }

        if (!in_array($mime, $allowed_mimes, true)) {
            return new WP_Error('invalid_file_type', __('This file type is not allowed.', 'rapls-ai-chatbot'));
        }

        return true;
    }

    /**
     * Save a base64-encoded image to the WordPress media library.
     *
     * @param string $base64_data Base64 data URI (data:image/...;base64,...)
     * @param int    $conversation_id Conversation ID for filename.
     * @return string Attachment URL or empty string on failure.
     */
    private function save_image_to_media(string $base64_data, int $conversation_id): string {
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/', $base64_data, $matches)) {
            return '';
        }
        $ext = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
        $comma_pos = strpos($base64_data, ',');
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $decoded = base64_decode(substr($base64_data, $comma_pos + 1), true);
        if (!$decoded || strlen($decoded) > 2 * 1024 * 1024) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $filename = 'chatbot-image-' . $conversation_id . '-' . time() . '.' . $ext;
        $filepath = $upload_dir['path'] . '/' . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if (file_put_contents($filepath, $decoded) === false) {
            return '';
        }

        $attachment = [
            'post_mime_type' => 'image/' . $ext,
            'post_title'     => sanitize_file_name($filename),
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $filepath);
        if (is_wp_error($attach_id) || !$attach_id) {
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return wp_get_attachment_url($attach_id);
    }

    /**
     * @param string $data_uri Base64-encoded data URI
     * @return string Extracted text
     */
    private function extract_file_text(string $data_uri): string {
        $comma_pos = strpos($data_uri, ',');
        if ($comma_pos === false) {
            return '';
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $decoded = base64_decode(substr($data_uri, $comma_pos + 1), true);
        if ($decoded === false) {
            return '';
        }

        $text = wp_check_invalid_utf8($decoded, true);

        // Truncate to prevent context overflow
        $max_chars = 30000;
        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $max_chars);
        } else {
            $text = substr($text, 0, $max_chars);
        }

        return $text;
    }

    private function get_client_ip(): string {
        $settings = get_option('raplsaich_settings', []);

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
            $remote = $this->normalize_ip(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));

            // Only trust XFF when the direct connection comes from a known proxy.
            // Private/loopback REMOTE_ADDR means a local reverse proxy (Nginx, Docker, etc.).
            // Additional trusted proxies can be added via filter (IP or CIDR notation).
            // Validate filter output: only accept valid IPs or CIDR ranges.
            $raw_proxies = (array) apply_filters('raplsaich_trusted_proxies', []);
            $trusted_ips   = [];
            $trusted_cidrs = [];
            foreach ($raw_proxies as $entry) {
                if (!is_string($entry)) { continue; }
                $entry = trim($entry);
                if (strpos($entry, '/') !== false) {
                    // CIDR notation (e.g. 172.64.0.0/13)
                    list($cidr_ip, $cidr_bits) = explode('/', $entry, 2);
                    if (filter_var($cidr_ip, FILTER_VALIDATE_IP) && is_numeric($cidr_bits)) {
                        $bits = (int) $cidr_bits;
                        $is_v6 = filter_var($cidr_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                        $max_bits = $is_v6 ? 128 : 32;
                        // Reject dangerously broad CIDRs: IPv4 < /8, IPv6 < /32
                        // (e.g. 0.0.0.0/0 would trust everything, defeating XFF security)
                        $min_bits = $is_v6 ? 32 : 8;
                        if ($bits >= $min_bits && $bits <= $max_bits) {
                            $trusted_cidrs[] = $entry;
                        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                            error_log('RAPLSAICH: Rejected overly broad trusted proxy CIDR: ' . sanitize_text_field($entry));
                        }
                    }
                } elseif (filter_var($entry, FILTER_VALIDATE_IP)) {
                    $trusted_ips[] = $entry;
                }
            }
            $remote_is_proxy = (
                !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ||
                in_array($remote, $trusted_ips, true) ||
                $this->ip_in_cidrs($remote, $trusted_cidrs)
            );

            if ($remote_is_proxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));

                // Guard against oversized XFF headers (DoS via CPU-expensive parsing).
                // Normal XFF rarely exceeds a few hundred bytes; cap at 2KB / 20 entries.
                if (strlen($forwarded) > 2048) {
                    $forwarded = substr($forwarded, 0, 2048);
                    $this->increment_xff_truncated();
                }

                $ips = explode(',', $forwarded);
                $max_entries = 20;
                foreach ($ips as $i => $candidate) {
                    if ($i >= $max_entries) { break; }
                    $candidate = trim($candidate);
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
     * Normalize an IP address: convert IPv4-mapped IPv6 (::ffff:x.x.x.x) to plain IPv4.
     * Some environments (e.g. dual-stack servers) report REMOTE_ADDR in mapped form,
     * which would fail IPv4 CIDR matching without normalization.
     */
    private function normalize_ip(string $ip): string {
        // Match ::ffff:x.x.x.x (IPv4-mapped IPv6)
        if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $m)) {
            return $m[1];
        }
        return $ip;
    }

    /**
     * Check if an IP address falls within any of the given CIDR ranges.
     *
     * @param string   $ip    IP address to check.
     * @param string[] $cidrs Array of CIDR notation strings (e.g. '172.64.0.0/13').
     * @return bool
     */
    private function ip_in_cidrs(string $ip, array $cidrs): bool {
        if (empty($cidrs) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        // Normalize IPv4-mapped IPv6 (::ffff:1.2.3.4) to plain IPv4 for CIDR matching
        $ip = $this->normalize_ip($ip);
        $ip_bin = inet_pton($ip);
        if ($ip_bin === false) {
            return false;
        }
        foreach ($cidrs as $cidr) {
            list($subnet, $bits) = explode('/', $cidr, 2);
            $subnet_bin = inet_pton($subnet);
            if ($subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
                continue; // IPv4/IPv6 mismatch
            }
            $bits = (int) $bits;
            // Build bitmask
            $mask = str_repeat("\xff", (int) ($bits / 8));
            if ($bits % 8) {
                $mask .= chr(0xff << (8 - ($bits % 8)) & 0xff);
            }
            $mask = str_pad($mask, strlen($ip_bin), "\x00");
            if (($ip_bin & $mask) === ($subnet_bin & $mask)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract session_id from a REST request.
     *
     * Priority: X-RAPLSAICH-Session header > URL path param / body param.
     *
     * Security policy (since Round 99b):
     * - GET: query-string ?session_id= is NOT accepted. Use the X-RAPLSAICH-Session
     *   header or URL path params (e.g. /history/{session_id}) only.
     * - POST: body params only (query string ignored).
     * - External tools (curl, monitoring) must send the header:
     *     curl -H "X-RAPLSAICH-Session: <uuid>" https://example.com/wp-json/rapls-ai-chatbot/v1/pro-config
     *
     * @param WP_REST_Request $request REST request object.
     * @return string Sanitized session_id (may be empty).
     */
    public function get_session_id(WP_REST_Request $request): string {
        // 1. Header (always preferred — never appears in URL/logs/APM body captures).
        //    When header is present, body session_id is intentionally ignored to
        //    prevent APM/WAF body logging from leaking the session identifier.
        $from_header = $request->get_header('X_RAPLSAICH_Session');
        if (!empty($from_header)) {
            return sanitize_text_field($from_header);
        }
        // 2. GET: only accept from URL path params (e.g. /history/{session_id}), not query string.
        if ($request->get_method() === 'GET') {
            $url_params = $request->get_url_params();
            return sanitize_text_field($url_params['session_id'] ?? '');
        }
        // 3. POST/PUT/etc (header absent): fallback to body params only (not query string).
        //    The chatbot widget always sends the header, so this path is only
        //    reached by external/direct REST callers that omit the header.
        $body = $request->get_json_params();
        if (isset($body['session_id'])) {
            return sanitize_text_field($body['session_id']);
        }
        $body_params = $request->get_body_params();
        return sanitize_text_field($body_params['session_id'] ?? '');
    }

    /**
     * Permission callback for public GET endpoints (session, lead-config, message-limit).
     *
     * Intentionally public: these endpoints serve the chatbot widget for unauthenticated visitors.
     * The chatbot must be enabled for access to be granted.
     * Additional defenses (rate limiting, no-cache) are applied in the callbacks themselves.
     *
     * @return bool True if the chatbot is enabled.
     */
    public function allow_public_access(): bool {
        return (bool) apply_filters('raplsaich_chatbot_enabled', true);
    }

    /**
     * Permission callback for offline message submission (POST).
     *
     * Intentionally public: allows unauthenticated visitors to leave messages outside business hours.
     * Requires Origin/Referer header to be present (same-origin policy).
     * Additional defenses (rate limit, honeypot, timing, reCAPTCHA) are applied in the callback.
     *
     * @return bool True if origin headers are present.
     */
    public function allow_offline_submission(): bool {
        if (!(bool) apply_filters('raplsaich_chatbot_enabled', true)) {
            return false;
        }
        return $this->has_origin_headers();
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
        $session_id = $this->get_session_id($request);

        if (empty($session_id)) {
            return new WP_Error(
                'rest_missing_session',
                __('Session ID is required.', 'rapls-ai-chatbot'),
                ['status' => 400, 'error_code' => 'session_missing']
            );
        }

        if (!$this->verify_session_ownership($session_id)) {
            return new WP_Error(
                'rest_session_forbidden',
                __('Invalid session.', 'rapls-ai-chatbot'),
                ['status' => 403, 'error_code' => 'session_expired']
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
    public function verify_session_ownership(string $session_id): bool {
        // Prefer check_session_permission() as permission_callback for REST routes.
        // This method is public so Pro can delegate instead of duplicating the logic.
        // Admins always pass
        if (current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
            return true;
        }

        // Primary: cookie set at session creation
        if (isset($_COOKIE['raplsaich_session_id'])) {
            $cookie_session = sanitize_text_field(wp_unslash($_COOKIE['raplsaich_session_id']));
            if (hash_equals($cookie_session, $session_id)) {
                return true;
            }
        }

        // Secondary: HMAC-signed session token (IP-independent, works across mobile/VPN/proxy)
        // Client stores token in localStorage and sends via X-RAPLSAICH-Session-Token header
        if (isset($_SERVER['HTTP_X_RAPLSAICH_SESSION_TOKEN'])) {
            $client_token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RAPLSAICH_SESSION_TOKEN']));
            if ($this->verify_session_token($session_id, $client_token)) {
                return true;
            }
        }

        $ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $current_hash = hash('sha256', $ip . $user_agent . wp_salt());

        // Header-based session verification (legacy localStorage fallback)
        // Requires matching IP+UA hash via bootstrap transient to prevent session_id-only spoofing
        if (isset($_SERVER['HTTP_X_RAPLSAICH_SESSION'])) {
            $header_session = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RAPLSAICH_SESSION']));
            if (hash_equals($header_session, $session_id)) {
                $transient_key = 'raplsaich_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
                $stored_hash = get_transient($transient_key);
                if ($stored_hash !== false && hash_equals($stored_hash, $current_hash)) {
                    return true;
                }
            }
        }

        // Fallback 1: visitor IP + User-Agent hash match against conversation record
        $conversation = RAPLSAICH_Conversation::get_by_session($session_id);
        if ($conversation && !empty($conversation['visitor_ip'])) {
            if (hash_equals($conversation['visitor_ip'], $current_hash)) {
                return true;
            }
        }

        // Fallback 2: bootstrap transient (covers cookie-less first request after /session)
        $transient_key = 'raplsaich_boot_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
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
        $pro_features = RAPLSAICH_Extensions::get_instance();
        $pro_settings = get_option('raplsaich_settings', []);
        $pro_feat_settings = $pro_settings['pro_features'] ?? [];

        // Enhanced rate limit hook (Pro adds via filter)
        $enhanced_result = apply_filters('raplsaich_enhanced_rate_limit', null, $pro_feat_settings);
        if ($enhanced_result !== null) {
            return $enhanced_result;
        }

        // Basic rate limit
        $settings = get_option('raplsaich_settings', []);
        $limit = (int) ($settings['rate_limit'] ?? 20);
        $window = (int) ($settings['rate_limit_window'] ?? 3600);

        // 0 = unlimited (no rate limiting, including burst)
        if ($limit === 0) {
            return true;
        }
        if ($window < 60) {
            $window = 3600;
        }

        $ip = $this->get_client_ip();

        // If IP detection fails, fall back to session-based rate limiting
        // to prevent unlimited access from unknown-IP environments
        if (empty($ip)) {
            // Use session cookie, X-RAPLSAICH-Session header, or global fallback key
            $fallback_id = '';
            if (isset($_COOKIE['raplsaich_session_id'])) {
                $fallback_id = sanitize_text_field(wp_unslash($_COOKIE['raplsaich_session_id']));
            } elseif (!empty($_SERVER['HTTP_X_RAPLSAICH_SESSION'])) {
                $fallback_id = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RAPLSAICH_SESSION']));
            }
            $rate_id = $fallback_id ?: 'global_noip';
            $session_key = 'raplsaich_noip_' . substr(hash('sha256', $rate_id . wp_salt()), 0, 32);
            $noip_count = (int) get_transient($session_key);
            if ($noip_count >= $limit) {
                return __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
            }
            set_transient($session_key, $noip_count + 1, $window);
            return true;
        }

        $ip_hash = hash('sha256', $ip . wp_salt());

        // Burst protection: max 3 requests per 10 seconds
        // Prefer session-based key to avoid NAT/corporate proxy collisions;
        // fall back to IP-based key when session is unavailable
        if (isset($_COOKIE['raplsaich_session_id'])) {
            $burst_key = 'raplsaich_burst_' . substr(hash('sha256', sanitize_text_field(wp_unslash($_COOKIE['raplsaich_session_id'])) . wp_salt()), 0, 32);
        } else {
            $burst_key = 'raplsaich_burst_' . substr($ip_hash, 0, 32);
        }
        $burst_count = (int) get_transient($burst_key);
        if ($burst_count >= 3) {
            return __('Too many requests. Please wait a few seconds.', 'rapls-ai-chatbot');
        }
        set_transient($burst_key, $burst_count + 1, 10);

        // Use session_id in key when available (from cookie) to reduce
        // false positives behind shared NAT/corporate networks
        $session_suffix = '';
        if (isset($_COOKIE['raplsaich_session_id'])) {
            $session_suffix = '_' . substr(hash('sha256', sanitize_text_field(wp_unslash($_COOKIE['raplsaich_session_id']))), 0, 8);
        }
        $transient_key = 'raplsaich_rate_' . substr($ip_hash, 0, 24) . $session_suffix;

        $count = (int) get_transient($transient_key);

        if ($count >= $limit) {
            return __('Rate limit exceeded. Please wait a moment.', 'rapls-ai-chatbot');
        }

        set_transient($transient_key, $count + 1, $window);

        // Also enforce a global per-IP limit (2x) to prevent abuse via multiple sessions
        $global_key = 'raplsaich_rate_ip_' . substr($ip_hash, 0, 32);
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
        $transient_key = 'raplsaich_prl_' . $route_key . '_' . substr($identity_hash, 0, 24);

        $count = (int) get_transient($transient_key);

        if ($count >= $limit) {
            return __('Too many requests. Please try again later.', 'rapls-ai-chatbot');
        }

        set_transient($transient_key, $count + 1, $window);
        return true;
    }

    /**
     * Allowed bot counter types. Fixed set to prevent transient key proliferation under attack.
     */
    private static array $allowed_bot_types = [
        'honeypot_offl', 'timing_offl', 'future_ts_offl',
        'honeypot_pub', 'timing_pub', 'future_ts_pub',
        'honeypot_lead', 'timing_lead', 'future_ts_lead',
    ];

    /**
     * Increment a bot detection counter (1-hour window).
     * Used by guard_public_post() to track honeypot/timing drops for admin visibility.
     *
     * - External object cache (Redis/Memcached): always records (no DB writes).
     * - No external cache: samples 1-in-10 to minimize wp_options writes under attack.
     *
     * @param string $type Detection type (must be in $allowed_bot_types).
     */
    private function increment_bot_counter(string $type): void {
        // Only allow predefined counter types to prevent transient key proliferation
        if (!in_array($type, self::$allowed_bot_types, true)) {
            return;
        }

        $key = 'raplsaich_bot_drop_' . $type;

        // future_ts events are rare (only client-clock-ahead) — always count exactly.
        // honeypot/timing can spike under bot attack — sample 1-in-10 to limit DB writes.
        $is_future_ts = strpos($type, 'future_ts_') === 0;

        // Prefer object cache (Redis/Memcached) to avoid wp_options DB writes under attack.
        if (wp_using_ext_object_cache()) {
            $count = (int) wp_cache_get($key, 'raplsaich_bot');
            wp_cache_set($key, $count + 1, 'raplsaich_bot', HOUR_IN_SECONDS);
        } else {
            // Sample 1-in-10 to reduce DB writes when under bot attack.
            // Counter value is multiplied by 10 when displayed for approximate total.
            // Exception: future_ts is always counted exactly (rare, needs accurate diagnostics)
            // but rate-capped at 1 write/minute per IP to prevent abuse via crafted _ts values.
            if ($is_future_ts) {
                $ip = $this->get_client_ip();
                if ($ip !== '') {
                    // Rate-cap: 1 write/minute per IP to prevent abuse via crafted _ts values.
                    $ip_hash = substr(hash('sha256', $ip . wp_salt()), 0, 12);
                    $cap_key = 'raplsaich_fts_cap_' . $ip_hash;
                    if (get_transient($cap_key)) {
                        return; // Already recorded for this IP within the window
                    }
                    set_transient($cap_key, 1, MINUTE_IN_SECONDS);
                } else {
                    // IP unavailable — fall back to 1-in-10 sampling to limit DB writes
                    // while still recording some events for diagnostics.
                    if (wp_rand(1, 10) !== 1) {
                        return;
                    }
                }
            } elseif (wp_rand(1, 10) !== 1) {
                return;
            }
            $count = (int) get_transient($key);
            set_transient($key, $count + 1, HOUR_IN_SECONDS);
        }
    }

    /**
     * Increment the XFF truncation counter (1-hour window).
     * Tracks how often oversized X-Forwarded-For headers are truncated.
     * Uses the same sampling strategy as bot counters.
     */
    private function increment_xff_truncated(): void {
        $key = 'raplsaich_xff_truncated';
        if (wp_using_ext_object_cache()) {
            $count = (int) wp_cache_get($key, 'raplsaich_bot');
            wp_cache_set($key, $count + 1, 'raplsaich_bot', HOUR_IN_SECONDS);
        } else {
            if (wp_rand(1, 10) !== 1) { return; }
            $count = (int) get_transient($key);
            set_transient($key, $count + 1, HOUR_IN_SECONDS);
        }
    }

    /**
     * Build the list of allowed origin hostnames for this site.
     * Includes home_url, site_url, www variants, and the raplsaich_allowed_origins filter.
     * Shared by check_same_origin() and verify_recaptcha() hostname validation.
     *
     * @return string[] Array of lowercase hostnames.
     */
    public function get_allowed_origin_hosts(): array {
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
         * Port-based origin restriction is not supported: Origin/Referer matching
         * uses hostname only, so any port entries are automatically stripped.
         *
         * @param string[] $allowed Array of lowercase hostnames.
         */
        $filtered = apply_filters('raplsaich_allowed_origins', $allowed);

        // Re-sanitize filter output: normalize to lowercase hostnames, strip schemes/paths/ports.
        $sanitized = [];
        foreach ((array) $filtered as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }
            // If someone passed a full URL, extract host only.
            $host = wp_parse_url($entry, PHP_URL_HOST);
            if ($host === null || $host === false) {
                // Not a URL — treat as bare hostname.
                $host = $entry;
            }
            $host = strtolower(trim($host));
            if ($host === '') {
                continue;
            }
            // Strip port from hostname (e.g. "example.com:8443" → "example.com").
            // Origin/Referer matching uses PHP_URL_HOST which excludes port,
            // so a port-bearing entry would never match. Auto-strip and warn.
            if (strpos($host, ':') !== false && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = preg_replace('/:\d+$/', '', $host);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('RAPLSAICH: Port stripped from allowed origin host — port is ignored by design (Origin/Referer matching uses hostname only). Use hostname without :port in raplsaich_allowed_origins filter.');
                }
            }
            if ($host !== '') {
                $sanitized[] = $host;
            }
        }
        return array_values(array_unique($sanitized));
    }

    /**
     * Add CORS headers for cross-site embed requests.
     * Only applies to rapls-ai-chatbot/v1 namespace.
     * Uses the same allowed origins list as the origin check.
     *
     * @param bool             $served  Whether the request has already been served.
     * @param WP_HTTP_Response $result  Result to send.
     * @param WP_REST_Request  $request REST request.
     * @param WP_REST_Server   $server  REST server.
     * @return bool
     */
    public function add_cors_headers($served, $result, $request, $server) {
        if (strpos($request->get_route(), '/' . $this->namespace) !== 0) {
            return $served;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? wp_unslash($_SERVER['HTTP_ORIGIN']) : '';
        if (empty($origin)) {
            return $served;
        }

        $allowed = $this->get_allowed_origin_hosts();
        $origin_host = wp_parse_url($origin, PHP_URL_HOST);
        if ($origin_host && in_array(strtolower($origin_host), $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, X-RAPLSAICH-Session, X-RAPLSAICH-Session-Token');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Vary: Origin');
        }

        return $served;
    }

    /**
     * Check whether Origin or Referer headers are present in the current request.
     *
     * @return bool True if at least one header is present, false if neither.
     */
    protected function has_origin_headers(): bool {
        return !empty($_SERVER['HTTP_ORIGIN']) || !empty($_SERVER['HTTP_REFERER']);
    }

    /**
     * Check that a public POST request originates from the same site.
     * Compares Origin or Referer header host against allowed hosts (home_url, site_url).
     * Not bulletproof (headers can be spoofed) but raises the bar for casual abuse.
     *
     * Return contract: always returns true (pass) or WP_REST_Response (reject).
     * To check whether headers were present at all, use has_origin_headers() separately.
     *
     * When no Origin/Referer headers are present, returns true (permissive).
     * Callers that need stricter policy should check has_origin_headers() and decide.
     *
     * @return true|WP_REST_Response
     */
    protected function check_same_origin() {
        $allowed = $this->get_allowed_origin_hosts();

        if (empty($allowed)) {
            // Allowed hosts should never be empty in a properly configured site.
            // If they are, reject with a diagnostic error rather than silently allowing.
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Security configuration error. Please contact the site administrator.', 'rapls-ai-chatbot'),
                'error_code' => 'origin_config_invalid',
            ], 403);
        }

        $has_headers = $this->has_origin_headers();

        // Parse host from Origin/Referer (use esc_url_raw for predictable sanitization)
        $origin_host  = isset($_SERVER['HTTP_ORIGIN'])
            ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN'])), PHP_URL_HOST)
            : null;
        $referer_host = isset($_SERVER['HTTP_REFERER'])
            ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])), PHP_URL_HOST)
            : null;

        // Accept if either header matches any allowed host (exact match)
        if (($origin_host && in_array(strtolower($origin_host), $allowed, true)) ||
            ($referer_host && in_array(strtolower($referer_host), $allowed, true))) {
            return true;
        }

        // Headers exist but host could not be parsed (e.g. "Origin: null", malformed URL).
        // This is distinct from "no headers" and must be rejected.
        if ($has_headers && !$origin_host && !$referer_host) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Invalid Origin/Referer header.', 'rapls-ai-chatbot'),
                'error_code' => 'origin_invalid',
            ], 403);
        }

        // No headers at all — permissive (callers use has_origin_headers() for stricter policy)
        if (!$origin_host && !$referer_host) {
            return true;
        }

        // Headers present but don't match — hard reject
        return new WP_REST_Response([
            'success'    => false,
            'error'      => __('Cross-origin request denied.', 'rapls-ai-chatbot'),
            'error_code' => 'origin_mismatch',
        ], 403);
    }

    /**
     * Consolidated guard for public POST endpoints.
     * Runs: same-origin, rate limit, honeypot, timing, reCAPTCHA.
     *
     * Return contract: always returns true (pass) or WP_REST_Response (reject).
     * Callers must use: if ($guard !== true) { return $guard; }
     *
     * @param WP_REST_Request $request          The REST request.
     * @param string          $rate_key         Short identifier for rate limiting transient.
     * @param int             $rate_limit       Max requests per window.
     * @param int             $rate_window      Window in seconds.
     * @param bool            $require_captcha  Whether reCAPTCHA must be fully configured.
     * @param string          $captcha_action   reCAPTCHA action name (e.g. 'offline', 'lead').
     * @param bool            $allow_no_headers If true, allow requests with no Origin/Referer.
     *                                          Use for endpoints where other auth (session, nonce)
     *                                          compensates. Used by offline-message endpoint
     *                                          where reCAPTCHA + per-IP limits compensate.
     * @return true|WP_REST_Response True if all checks pass, or error response.
     */
    private function guard_public_post(
        WP_REST_Request $request,
        string $rate_key = 'pub',
        int $rate_limit = 30,
        int $rate_window = 60,
        bool $require_captcha = false,
        string $captcha_action = '',
        bool $allow_no_headers = false
    ) {
        // 1. Same-origin check (returns true or WP_REST_Response)
        $origin_result = $this->check_same_origin();
        if ($origin_result instanceof WP_REST_Response) {
            return $origin_result;
        }

        // 2. No-headers policy — context-dependent:
        //   When Origin/Referer are absent (proxies, privacy extensions, JS optimization):
        //   - allow_no_headers=true:  permit (rate limit is primary defense)
        //   - require_captcha=true:   permit (reCAPTCHA compensates below)
        //   - otherwise:              reject with diagnostic message
        $has_headers = $this->has_origin_headers();
        $origin_ok = $has_headers; // Used later for timing+captcha interaction

        if (!$has_headers && !$allow_no_headers && !$require_captcha) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Request blocked: missing Origin/Referer header. If you use a caching or JS optimization plugin, ensure it does not defer or block the chatbot scripts.', 'rapls-ai-chatbot'),
                'error_code' => 'origin_headers_missing',
            ], 403);
        }

        // 2. Public rate limit
        $rate_check = $this->check_public_rate_limit($rate_key, $rate_limit, $rate_window);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check, 'error_code' => 'rate_limited'], 429);
        }

        // 3. Honeypot: reject if hidden field is filled (bots auto-fill)
        // Field name is unique to avoid collision with other plugins' forms.
        $hp = $request->get_param('raplsaich_hp');
        if (!empty($hp)) {
            $this->increment_bot_counter('honeypot_' . $rate_key);
            return $this->silent_success('honeypot');
        }

        // 4. Timing check: reject if submitted faster than 5 seconds (bot speed).
        // _ts is a client-side Unix timestamp set by JS when the form renders.
        // Future timestamps (client clock ahead of server) are excluded from timing
        // rejection to avoid false positives on VM/corporate/NTP-broken environments.
        $form_ts = (int) $request->get_param('_ts');
        $now = time();
        if ($form_ts > $now) {
            // Client clock is ahead — skip timing check, log for diagnostics
            $this->increment_bot_counter('future_ts_' . $rate_key);
        } elseif ($form_ts > 0 && ($now - $form_ts) < 5) {
            $this->increment_bot_counter('timing_' . $rate_key);
            return $this->silent_success('timing');
        }

        // When _ts is missing (JS disabled/delayed) and captcha is required, reject.
        // Without both timing and captcha, bot detection is too weak.
        if ($form_ts === 0 && $require_captcha && !$origin_ok) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Form validation failed. Please reload the page and try again.', 'rapls-ai-chatbot'),
                'error_code' => 'timing_failed',
            ], 403);
        }

        // 5. reCAPTCHA (when required)
        if ($require_captcha) {
            $settings = get_option('raplsaich_settings', []);
            $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
            $recaptcha_site_key = trim($settings['recaptcha_site_key'] ?? '');
            $recaptcha_secret_key = trim($settings['recaptcha_secret_key'] ?? '');

            if (!$recaptcha_enabled) {
                // User-friendly message (no internal details); admin sees setup hint
                $user_msg = __('This form is temporarily unavailable. Please try again later, or contact the site administrator for assistance.', 'rapls-ai-chatbot');
                if (current_user_can(RAPLSAICH_Admin::get_manage_cap())) {
                    $user_msg = sprintf(
                        /* translators: %s: feature name */
                        __('%s requires reCAPTCHA to be configured. Go to Rapls AI Chatbot → Settings → Security to set it up.', 'rapls-ai-chatbot'),
                        ucfirst(str_replace('_', ' ', $captcha_action))
                    );
                }
                return new WP_REST_Response([
                    'success'    => false,
                    'error'      => $user_msg,
                    'error_code' => 'recaptcha_required',
                ], 403);
            }

            if (empty($recaptcha_site_key) || empty($recaptcha_secret_key)) {
                return new WP_REST_Response([
                    'success'    => false,
                    'error'      => __('reCAPTCHA is enabled but not fully configured (missing site key or secret key). Please complete the reCAPTCHA setup in plugin settings.', 'rapls-ai-chatbot'),
                    'error_code' => 'recaptcha_misconfigured',
                ], 403);
            }

            $recaptcha_token = sanitize_text_field($request->get_param('recaptcha_token') ?? '');
            $recaptcha_result = $this->verify_recaptcha($recaptcha_token, $captcha_action);
            if (is_wp_error($recaptcha_result)) {
                $resp_data = [
                    'success'    => false,
                    'error'      => $recaptcha_result->get_error_message(),
                    'error_code' => $recaptcha_result->get_error_code(),
                ];
                $err_data = $recaptcha_result->get_error_data();
                if (!empty($err_data['google_error_codes'])) {
                    $resp_data['recaptcha_error_codes'] = $err_data['google_error_codes'];
                }
                return new WP_REST_Response($resp_data, 403);
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
        $settings = get_option('raplsaich_settings', []);

        // Skip if reCAPTCHA is disabled
        if (empty($settings['recaptcha_enabled'])) {
            return true;
        }

        $secret_key = $settings['recaptcha_secret_key'] ?? '';
        $threshold = floatval($settings['recaptcha_threshold'] ?? 0.5);

        // Decrypt secret key if encrypted (GCM or legacy CBC)
        // Loop handles double-encryption caused by older sanitize_settings bug (max 3 layers)
        for ($i = 0; $i < 3 && !empty($secret_key) && (strpos($secret_key, 'encg:') === 0 || strpos($secret_key, 'enc:') === 0); $i++) {
            $secret_key = RAPLSAICH_Admin::decrypt_secret_static($secret_key);
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
            $fail_mode = $settings['recaptcha_fail_mode'] ?? 'open';
            if ($fail_mode === 'closed') {
                return new WP_Error('recaptcha_unavailable', __('Security verification is temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'));
            }
            return true; // fail-open: allow request through
        }

        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);
        $http_code = (int) wp_remote_retrieve_response_code($response);

        // Fail-open when Google response is unreachable/malformed (non-200, empty body, or bad JSON)
        if ($http_code !== 200 || !is_array($body)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[RAPLSAICH reCAPTCHA] Google returned HTTP ' . $http_code . ', body: ' . substr($raw_body, 0, 500));
            }
            $fail_mode = $settings['recaptcha_fail_mode'] ?? 'open';
            if ($fail_mode === 'open') {
                return true;
            }
            return new WP_Error('recaptcha_unavailable', __('Security verification is temporarily unavailable. Please try again later.', 'rapls-ai-chatbot'));
        }

        if (empty($body['success'])) {
            $error_codes = $body['error-codes'] ?? [];
            // Debug: log Google's error response for troubleshooting
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[RAPLSAICH reCAPTCHA] Verification failed. Google error codes: ' . wp_json_encode($error_codes) . ' | Response: ' . wp_json_encode($body));
            }
            do_action('raplsaich_recaptcha_failed', $error_codes);

            // Fail-open for transient/server-side errors (Google outage, token expiry, empty error codes)
            $fail_mode = $settings['recaptcha_fail_mode'] ?? 'open';
            $transient_codes = ['timeout-or-duplicate', 'bad-request', 'internal-error'];
            $is_transient = empty($error_codes) || !empty(array_intersect($error_codes, $transient_codes));
            if ($fail_mode === 'open' && $is_transient) {
                return true;
            }

            $error = new WP_Error('recaptcha_failed', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
            // Attach Google error codes for client-side retry logic
            $error->add_data(['google_error_codes' => $error_codes]);
            return $error;
        }

        // Verify action matches expected (prevents token reuse across different forms)
        if (!empty($expected_action) && isset($body['action']) && $body['action'] !== $expected_action) {
            return new WP_Error('recaptcha_action_mismatch', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
        }

        // Verify hostname matches this site (prevents token from other sites).
        // Uses the same allowed-hosts logic as check_same_origin() to handle
        // www/non-www, home_url/site_url differences, and custom origins.
        // Fail-closed: if hostname is empty or missing, reject (Google should always provide it).
        $allowed_hosts = $this->get_allowed_origin_hosts();
        if (!empty($allowed_hosts)) {
            if (empty($body['hostname'])) {
                return new WP_Error('recaptcha_hostname_missing', __('Security verification failed. Please reload the page.', 'rapls-ai-chatbot'));
            }
            $token_host = strtolower($body['hostname']);
            if (!in_array($token_host, $allowed_hosts, true)) {
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
     * Get lead form configuration
     */
    public function get_lead_config(WP_REST_Request $request): WP_REST_Response {
        $rate_check = $this->check_public_rate_limit('lcfg', 30, 60);
        if ($rate_check !== true) {
            return new WP_REST_Response(['success' => false, 'error' => $rate_check, 'error_code' => 'rate_limited'], 429);
        }

        try {
            $settings = get_option('raplsaich_settings', []);
            $ext_settings = $settings['pro_features'] ?? [];

            // Lead capture requires Pro to be active AND setting enabled.
            // Prevents stale DB values from enabling lead form when Pro is deactivated.
            $is_enabled = raplsaich_is_pro_active() && !empty($ext_settings['lead_capture_enabled']);

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
            $lead_fields = $ext_settings['lead_fields'] ?? [];

            foreach ($lead_fields as $field_name => $field_config) {
                if (!empty($field_config['enabled'])) {
                    $fields[$field_name] = [
                        'label'    => $field_config['label'] ?? ucfirst($field_name),
                        'required' => !empty($field_config['required']),
                        'type'     => $field_config['type'] ?? ($field_name === 'email' ? 'email' : ($field_name === 'phone' ? 'tel' : 'text')),
                    ];
                }
            }

            // Append custom fields
            $custom_fields = $ext_settings['lead_custom_fields'] ?? [];
            foreach ($custom_fields as $cf) {
                $key = $cf['key'] ?? '';
                if ($key === '') {
                    continue;
                }
                $field_def = [
                    'label'    => $cf['label'] ?? $key,
                    'required' => !empty($cf['required']),
                    'type'     => $cf['type'] ?? 'text',
                    'custom'   => true,
                ];
                if (($cf['type'] ?? '') === 'select' && !empty($cf['options'])) {
                    $field_def['options'] = array_map('trim', explode(',', $cf['options']));
                }
                $fields['custom_' . $key] = $field_def;
            }

            return $this->no_cache(new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'enabled'     => true,
                    'required'    => !empty($ext_settings['lead_capture_required']),
                    'title'       => $ext_settings['lead_form_title'] ?? __('Before we start', 'rapls-ai-chatbot'),
                    'description' => $ext_settings['lead_form_description'] ?? __('Please enter your information', 'rapls-ai-chatbot'),
                    'fields'      => $fields,
                ],
            ], 200));

        } catch (\Throwable $e) {
            raplsaich_rate_limited_log(
                'lead_config_error',
                'RAPLSAICH Lead Config Error: ' . $e->getMessage()
            );
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
            return new WP_REST_Response(['success' => false, 'error' => $rate_check, 'error_code' => 'rate_limited'], 429);
        }

        $pro_features = RAPLSAICH_Extensions::get_instance();
        $remaining = $pro_features->get_remaining_messages();

        // Return only UI-necessary fields.
        // Design note: Free/Pro plan type is already publicly inferable from widget
        // appearance (Pro themes, branding removal), so remaining=null for unlimited
        // is not a meaningful information leak. Only return what the frontend needs.
        return $this->no_cache(new WP_REST_Response([
            'success' => true,
            'data'    => [
                'remaining' => $remaining === PHP_INT_MAX ? null : $remaining,
                'reached'   => $pro_features->is_limit_reached(),
            ],
        ], 200));
    }

    /**
     * Submit message feedback
     */
    public function submit_feedback(WP_REST_Request $request): WP_REST_Response {
        // Feedback requires stored messages
        $settings = get_option('raplsaich_settings', []);
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
        $message = RAPLSAICH_Message::get_by_id($message_id);
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
        $conversation = RAPLSAICH_Conversation::get_by_session($session_id);
        if (!$conversation || (int) $conversation['id'] !== (int) $message['conversation_id']) {
            return new WP_REST_Response([
                'success'    => false,
                'error'      => __('Invalid session.', 'rapls-ai-chatbot'),
                'error_code' => 'session_expired',
            ], 403);
        }

        // Session ownership already verified by check_session_permission()

        // Update feedback
        $result = RAPLSAICH_Message::update_feedback($message_id, $feedback);

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
        $query_lower = raplsaich_mb_strtolower($query);
        $title_lower = raplsaich_mb_strtolower($title);

        if (!empty($title) && (
            raplsaich_mb_strpos($title_lower, $query_lower) !== false ||
            raplsaich_mb_strpos($query_lower, $title_lower) !== false
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
     * Get the transient key for ephemeral session context.
     * Used when save_history is OFF to keep multi-turn context without DB writes.
     */
    private function get_context_transient_key(string $session_id): string {
        return 'raplsaich_ctx_' . substr(hash('sha256', $session_id . wp_salt()), 0, 32);
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
     * Stored as a single array option to avoid per-month key proliferation.
     */
    private function increment_no_history_monthly_count(): void {
        $month_key = wp_date('Y_m');
        $counts = (array) get_option('raplsaich_nohist_msg_counts', []);
        $counts[$month_key] = ((int) ($counts[$month_key] ?? 0)) + 1;

        // Prune entries older than 3 months to prevent unbounded growth.
        // Use wp_date() for both key and cutoff to ensure consistent timezone.
        $cutoff = wp_date('Y_m', time() - (3 * MONTH_IN_SECONDS));
        foreach (array_keys($counts) as $k) {
            if ($k < $cutoff) {
                unset($counts[$k]);
            }
        }

        update_option('raplsaich_nohist_msg_counts', $counts, false);
    }

    /**
     * Get a rate limit counter that works even when external object cache is broken.
     *
     * Tries transient first (fast path). If transient returns false and an external
     * object cache is active, falls back to a single DB array option.
     *
     * @param string $key    Transient/option key
     * @param int    $window TTL in seconds
     * @return int Current counter value
     */
    private function get_resilient_counter(string $key, int $window): int {
        $count = get_transient($key);
        if ($count !== false) {
            return (int) $count;
        }

        // Transient miss — check if external object cache may have lost it.
        // Hash the key for DB storage to avoid bloating wp_options with long transient keys.
        if (wp_using_ext_object_cache()) {
            $store = (array) get_option('raplsaich_rl_fallback', []);
            $hashed = substr(hash('sha256', $key), 0, 16);
            if (isset($store[$hashed]) && ($store[$hashed]['exp'] ?? 0) > time()) {
                return (int) ($store[$hashed]['c'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Increment a resilient rate limit counter.
     *
     * Writes to transient (primary). If transient write fails (external cache down),
     * falls back to a single DB array option with per-key expiry.
     * Max 200 entries; expired entries pruned on each write.
     *
     * @param string $key    Transient/option key
     * @param int    $count  Current counter value
     * @param int    $window TTL in seconds
     */
    private function increment_resilient_counter(string $key, int $count, int $window): void {
        $new_count = $count + 1;
        $written = set_transient($key, $new_count, $window);

        // If transient write failed and we're using external cache, fall back to DB.
        // Hash the key for DB storage to avoid bloating wp_options with long transient keys.
        if (!$written && wp_using_ext_object_cache()) {
            $store = (array) get_option('raplsaich_rl_fallback', []);
            $now = time();
            $hashed = substr(hash('sha256', $key), 0, 16);

            // Prune expired entries
            foreach ($store as $k => $v) {
                if (($v['exp'] ?? 0) <= $now) {
                    unset($store[$k]);
                }
            }

            // Cap at 200 entries — evict oldest if full
            if (count($store) >= 200) {
                uasort($store, function ($a, $b) {
                    return ($a['exp'] ?? 0) - ($b['exp'] ?? 0);
                });
                $store = array_slice($store, -199, null, true);
            }

            $store[$hashed] = ['c' => $new_count, 'exp' => $now + $window];
            update_option('raplsaich_rl_fallback', $store, false);
        }
    }

}
