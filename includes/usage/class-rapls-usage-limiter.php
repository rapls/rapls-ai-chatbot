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
                    'allowed' => false, 'reason' => 'daily_limit', 'scope' => $scope,
                    'window' => $window, 'limit' => $max_requests, 'used' => $usage['requests'],
                ];
            }
            if ($max_tokens > 0 && $usage['tokens'] >= $max_tokens) {
                return [
                    'allowed' => false, 'reason' => 'token_limit', 'scope' => $scope,
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
     * Visitor-facing message for a specific block reason.
     *
     * Reason codes (shared across every gate so a blocked user can tell which
     * limit stopped them, even though the limits coexist):
     *   - message_quota : the existing monthly message-count limit
     *   - credit        : per-user credit balance exhausted (Pro)
     *   - token_limit   : per-role token limit (Pro)
     *   - daily_limit   : the free per-visitor/user daily request cap
     *   - rate_limit    : short-term (per-IP) rate limit
     *
     * The message and reason are overridable via `rapls_usage_check_result`
     * (set $check['message']) and the `rapls_usage_reason_message` filter, so
     * nothing here is hard-coded.
     *
     * @param string $reason One of the codes above.
     * @param array  $check  The failing limit details (scope/window/limit/used).
     */
    public static function message_for_reason(string $reason, array $check = []): string {
        // An explicit per-result message (e.g. set by Pro via the filter) wins.
        if (!empty($check['message'])) {
            return (string) $check['message'];
        }

        $settings = get_option('raplsaich_settings', []);
        // A site-wide custom override applies to the generic usage caps only.
        $custom = trim((string) ($settings['usage_block_message'] ?? ''));

        switch ($reason) {
            case 'message_quota':
                $msg = __('You have reached your message limit for this month.', 'rapls-ai-chatbot');
                break;
            case 'credit':
                $msg = __('You have used up your credits. Please wait until the next reset.', 'rapls-ai-chatbot');
                break;
            case 'token_limit':
                $msg = __('You have reached the usage limit. Please try again later.', 'rapls-ai-chatbot');
                break;
            case 'rate_limit':
                $msg = __('You have sent many messages in a short time. Please wait a moment.', 'rapls-ai-chatbot');
                break;
            case 'daily_limit':
            default:
                $msg = $custom !== ''
                    ? $custom
                    : (is_user_logged_in()
                        ? __('You have reached today\'s usage limit. Please try again later.', 'rapls-ai-chatbot')
                        : __('The daily limit has been reached. Please try again later, or sign in.', 'rapls-ai-chatbot'));
                break;
        }

        // Optional remaining display (off by default) — owner controls exposure.
        if (!empty($settings['usage_show_remaining']) && isset($check['limit'], $check['used']) && (int) $check['limit'] > 0) {
            $remaining = max(0, (int) $check['limit'] - (int) $check['used']);
            /* translators: %d: remaining allowance */
            $msg .= ' ' . sprintf(__('(Remaining: %d)', 'rapls-ai-chatbot'), $remaining);
        }

        /**
         * Filter the final block message for a reason.
         *
         * @param string $msg    The message.
         * @param string $reason The reason code.
         * @param array  $check  The failing limit details.
         */
        return (string) apply_filters('rapls_usage_reason_message', $msg, $reason, $check);
    }

    /**
     * The visitor-facing message shown when a usage-gate request is blocked.
     * Thin wrapper kept for back-compat; routes by reason.
     */
    public static function block_message(array $check): string {
        $reason = (string) ($check['reason'] ?? 'daily_limit');
        // Back-compat: honor the legacy single-message filter if hooked.
        $msg = self::message_for_reason($reason, $check);
        return (string) apply_filters('rapls_usage_block_message', $msg, $check);
    }
}
