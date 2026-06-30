<?php
/**
 * Usage Limiter — the gate for ⑤ usage control.
 *
 * Called BEFORE the LLM request. Compares an actor's current usage against the
 * configured limits and returns allow/block. Server-side only; the client is
 * never trusted. Admin users bypass limits (override) by default.
 *
 * Free provides a minimal safety valve: a per-day request cap for guests and
 * for logged-in users (0 = unlimited). Pro injects richer limits — role-based,
 * token-based, multiple windows, and credits — via the `rapls_usage_limits`
 * filter, which this class consumes without needing to know about Pro.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Usage_Limiter {

    /** Whether usage control is on at all. */
    public static function is_enabled(): bool {
        $settings = get_option('raplsaich_settings', []);
        $enabled  = !empty($settings['usage_enabled']);
        return (bool) apply_filters('rapls_usage_enabled', $enabled);
    }

    /**
     * Build the effective limit set for an actor.
     *
     * Each entry: ['window' => 'hour|day|month', 'max_requests' => int,
     * 'max_tokens' => int]. 0 means "no limit on this dimension".
     *
     * @return array<int,array{scope:string,window:string,max_requests:int,max_tokens:int}>
     */
    private static function limits_for(RAPLSAICH_Usage_Actor $actor): array {
        $settings = get_option('raplsaich_settings', []);
        $limits   = [];

        if ($actor->type === 'guest') {
            $cap = (int) ($settings['usage_guest_daily_limit'] ?? 0);
            if ($cap > 0) {
                $limits[] = ['scope' => 'guest', 'window' => 'day', 'max_requests' => $cap, 'max_tokens' => 0];
            }
        } else {
            $cap = (int) ($settings['usage_user_daily_limit'] ?? 0);
            if ($cap > 0) {
                $limits[] = ['scope' => 'user', 'window' => 'day', 'max_requests' => $cap, 'max_tokens' => 0];
            }
        }

        /**
         * Filter the effective limits for this actor. Pro adds role-based,
         * token-based, multi-window, and credit limits here.
         *
         * @param array                 $limits  Base (Free) limits.
         * @param RAPLSAICH_Usage_Actor $actor   The actor being checked.
         */
        $limits = apply_filters('rapls_usage_limits', $limits, $actor);

        return is_array($limits) ? $limits : [];
    }

    /**
     * Check whether the actor may make another request.
     *
     * @return array{allowed:bool,reason:string,scope:string,window:string,limit:int,used:int}
     */
    public static function check(RAPLSAICH_Usage_Actor $actor): array {
        $ok = ['allowed' => true, 'reason' => '', 'scope' => '', 'window' => '', 'limit' => 0, 'used' => 0];

        if (!self::is_enabled()) {
            return $ok;
        }

        // Admin override (on by default) — never block a manager testing the bot.
        $override = apply_filters('rapls_usage_admin_override', true, $actor);
        if ($actor->is_admin && $override) {
            return $ok;
        }

        foreach (self::limits_for($actor) as $limit) {
            $window       = $limit['window'] ?? 'day';
            $max_requests = (int) ($limit['max_requests'] ?? 0);
            $max_tokens   = (int) ($limit['max_tokens'] ?? 0);
            if ($max_requests <= 0 && $max_tokens <= 0) {
                continue;
            }

            // A role-scoped limit reads the role's aggregate row; everything
            // else reads the actor's own row.
            $scope = $limit['scope'] ?? $actor->type;
            if (strpos($scope, 'role:') === 0) {
                $usage = RAPLSAICH_Usage_Store::get('role', substr($scope, 5, 64), $window);
            } else {
                $usage = RAPLSAICH_Usage_Store::get($actor->type, $actor->key, $window);
            }

            if ($max_requests > 0 && $usage['requests'] >= $max_requests) {
                return [
                    'allowed' => false, 'reason' => 'requests', 'scope' => $scope,
                    'window' => $window, 'limit' => $max_requests, 'used' => $usage['requests'],
                ];
            }
            if ($max_tokens > 0 && $usage['tokens'] >= $max_tokens) {
                return [
                    'allowed' => false, 'reason' => 'tokens', 'scope' => $scope,
                    'window' => $window, 'limit' => $max_tokens, 'used' => $usage['tokens'],
                ];
            }
        }

        /**
         * Final allow/block decision. Pro hooks this to enforce credit balances
         * (a depleting allowance, not a per-window cap) without Free needing to
         * know about credits. Return the result array with 'allowed' => false to
         * block, optionally setting 'reason' => 'credits'.
         *
         * @param array                 $ok    The (currently allowed) result.
         * @param RAPLSAICH_Usage_Actor $actor The actor being checked.
         */
        return apply_filters('rapls_usage_check_result', $ok, $actor);
    }

    /**
     * The visitor-facing message shown when a request is blocked.
     */
    public static function block_message(array $check): string {
        $settings = get_option('raplsaich_settings', []);
        $msg = trim((string) ($settings['usage_block_message'] ?? ''));
        if ($msg === '') {
            $msg = is_user_logged_in()
                ? __('You have reached the usage limit for now. Please try again later.', 'rapls-ai-chatbot')
                : __('The daily limit has been reached. Please try again later, or sign in.', 'rapls-ai-chatbot');
        }
        /** @param array $check The failing limit details. */
        return (string) apply_filters('rapls_usage_block_message', $msg, $check);
    }
}
