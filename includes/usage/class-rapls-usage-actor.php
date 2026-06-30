<?php
/**
 * Usage Actor — privacy-safe identity for usage metering/limiting (⑤).
 *
 * Resolves who is making a request into one of three actor types:
 *   - user  : a logged-in user (actor_key = user id)
 *   - guest : a non-logged-in visitor (actor_key = HASHED id, never raw IP)
 * and carries the user's roles so role-based limits can apply.
 *
 * Privacy: a guest is identified by hash(session_id [+ optional IP] + site_salt).
 * The raw IP is never stored. IP can be excluded entirely via the
 * `usage_guest_ip_identify` setting (session-only identification).
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Usage_Actor {

    /** @var string 'user' | 'guest' */
    public string $type;

    /** @var string Numeric user id, or a hashed guest id (≤64 chars). */
    public string $key;

    /** @var string[] Role slugs for a logged-in user; empty for guests. */
    public array $roles;

    /** @var bool Whether the current user can manage the plugin (admin override). */
    public bool $is_admin;

    private function __construct(string $type, string $key, array $roles, bool $is_admin) {
        $this->type     = $type;
        $this->key      = $key;
        $this->roles    = $roles;
        $this->is_admin = $is_admin;
    }

    /**
     * Resolve the current request into an actor.
     *
     * @param string $session_id The chat session id (used for guest identity).
     * @param string $client_ip  Raw client IP (used only to derive a hash; never stored).
     */
    public static function resolve(string $session_id = '', string $client_ip = ''): self {
        $settings = get_option('raplsaich_settings', []);

        if (is_user_logged_in()) {
            $user     = wp_get_current_user();
            $roles    = is_array($user->roles) ? array_values($user->roles) : [];
            $is_admin = current_user_can(RAPLSAICH_Admin::get_manage_cap());
            $actor    = new self('user', (string) $user->ID, $roles, $is_admin);
        } else {
            $actor = new self('guest', self::guest_key($session_id, $client_ip, $settings), [], false);
        }

        /**
         * Filter the resolved actor identity.
         *
         * @param RAPLSAICH_Usage_Actor $actor   The resolved actor.
         * @param string                $session The session id.
         */
        return apply_filters('rapls_usage_actor_id', $actor, $session_id);
    }

    /**
     * Build a privacy-safe guest identifier. Never returns or stores a raw IP.
     */
    private static function guest_key(string $session_id, string $client_ip, array $settings): string {
        // IP identification is on by default but can be turned off for
        // session-only identification (stricter privacy).
        $use_ip = !isset($settings['usage_guest_ip_identify']) || !empty($settings['usage_guest_ip_identify']);
        $material = $session_id;
        if ($use_ip && $client_ip !== '') {
            $material .= '|' . $client_ip;
        }
        if ($material === '') {
            // No session and no IP — fall back to a constant bucket so limits
            // still apply globally to anonymous traffic rather than failing open.
            $material = 'anon';
        }
        return hash('sha256', $material . '|' . wp_salt('auth'));
    }

    /**
     * Stable identifier for transient/cache keys (already hashed for guests).
     */
    public function cache_token(): string {
        return $this->type . ':' . substr(hash('sha256', $this->key), 0, 24);
    }
}
