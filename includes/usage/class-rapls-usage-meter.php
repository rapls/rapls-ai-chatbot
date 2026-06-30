<?php
/**
 * Usage Meter — records request/token consumption for ⑤.
 *
 * Thin wrapper over the Store. Records into every window that any active limit
 * cares about (hour/day/month) so the Limiter can read whichever it needs.
 * Token counts come from the provider's real usage (tokens_used), not estimates.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Usage_Meter {

    /**
     * Record one request (and its token usage) for an actor.
     *
     * @param RAPLSAICH_Usage_Actor $actor
     * @param int                   $tokens  Real tokens used (0 if unknown).
     * @param int                   $credits Credits consumed (Pro; 0 in Free).
     */
    public static function record(RAPLSAICH_Usage_Actor $actor, int $tokens = 0, int $credits = 0): void {
        // Record in all standard windows; reads are cheap and this keeps the
        // Limiter free to enforce any window without a write-time decision.
        foreach (['hour', 'day', 'month'] as $window) {
            RAPLSAICH_Usage_Store::add($actor->type, $actor->key, $window, 1, $tokens, $credits);
        }

        // For a logged-in user, also meter per-role so role limits can be
        // enforced even if the role's quota is shared across users (Pro reads
        // the user rows; the role rows give an aggregate view for dashboards).
        foreach ($actor->roles as $role) {
            $role_key = substr($role, 0, 64);
            foreach (['day', 'month'] as $window) {
                RAPLSAICH_Usage_Store::add('role', $role_key, $window, 1, $tokens, $credits);
            }
        }

        /**
         * Fired after a request's usage has been recorded. Pro hooks this to
         * decrement a per-user credit balance.
         *
         * @param RAPLSAICH_Usage_Actor $actor   The actor charged.
         * @param int                   $tokens  Real tokens used.
         * @param int                   $credits Credits consumed (Pro).
         */
        do_action('rapls_usage_recorded', $actor, $tokens, $credits);
    }
}
