<?php
/**
 * WP-CLI commands for Rapls AI Chatbot.
 *
 * Loaded only under WP-CLI (see RAPLSAICH_Main::load_dependencies).
 * Provides ops-friendly access to status, cleanup, crawling, and the
 * unanswered-question log without waiting for WP-Cron or the admin UI.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Manage the Rapls AI Chatbot plugin.
 */
class RAPLSAICH_CLI {

    /**
     * Show plugin status: version, provider, index size, conversation counts, cost.
     *
     * ## EXAMPLES
     *
     *     wp raplsaich status
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     */
    public function status($args, $assoc_args) {
        $settings = get_option('raplsaich_settings', []);
        $provider = $settings['ai_provider'] ?? 'openai';
        $model    = $settings[$provider . '_model'] ?? '';

        $index_count = class_exists('RAPLSAICH_Content_Index') ? (int) RAPLSAICH_Content_Index::get_count() : 0;
        $knowledge_count = class_exists('RAPLSAICH_Knowledge') ? (int) RAPLSAICH_Knowledge::get_count() : 0;
        $convo_total = class_exists('RAPLSAICH_Conversation') ? (int) RAPLSAICH_Conversation::get_count() : 0;
        $convo_today = class_exists('RAPLSAICH_Conversation') ? (int) RAPLSAICH_Conversation::get_today_count() : 0;

        $mtd_cost = '';
        if (class_exists('RAPLSAICH_Cost_Calculator') && method_exists('RAPLSAICH_Cost_Calculator', 'get_month_to_date_cost')) {
            $mtd_cost = '$' . number_format(RAPLSAICH_Cost_Calculator::get_month_to_date_cost(), 4);
        }

        $rows = [
            ['field' => 'version', 'value' => RAPLSAICH_VERSION],
            ['field' => 'provider', 'value' => $provider],
            ['field' => 'model', 'value' => $model !== '' ? $model : '(default)'],
            ['field' => 'indexed_pages', 'value' => $index_count],
            ['field' => 'knowledge_entries', 'value' => $knowledge_count],
            ['field' => 'conversations_total', 'value' => $convo_total],
            ['field' => 'conversations_today', 'value' => $convo_today],
            ['field' => 'month_to_date_cost', 'value' => $mtd_cost !== '' ? $mtd_cost : 'n/a'],
        ];

        WP_CLI\Utils\format_items('table', $rows, ['field', 'value']);
    }

    /**
     * Delete conversations older than the configured retention period.
     *
     * Runs the same routine as the daily raplsaich_cleanup_old_conversations
     * cron event (including the Pro raplsaich_after_cleanup extension point).
     *
     * ## EXAMPLES
     *
     *     wp raplsaich cleanup
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     */
    public function cleanup($args, $assoc_args) {
        $before = class_exists('RAPLSAICH_Conversation') ? (int) RAPLSAICH_Conversation::get_count() : 0;

        do_action('raplsaich_cleanup_old_conversations');

        $after = class_exists('RAPLSAICH_Conversation') ? (int) RAPLSAICH_Conversation::get_count() : 0;
        WP_CLI::success(sprintf('Cleanup complete. Conversations: %d -> %d (%d removed).', $before, $after, max(0, $before - $after)));
    }

    /**
     * Crawl the site and rebuild the content index now.
     *
     * Runs the same routine as the raplsaich_crawl_site cron event,
     * synchronously. Large sites may take a while.
     *
     * ## EXAMPLES
     *
     *     wp raplsaich crawl
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments (unused).
     */
    public function crawl($args, $assoc_args) {
        WP_CLI::log('Crawling site content...');

        do_action('raplsaich_crawl_site');

        $count = class_exists('RAPLSAICH_Content_Index') ? (int) RAPLSAICH_Content_Index::get_count() : 0;
        WP_CLI::success(sprintf('Crawl complete. Indexed entries: %d.', $count));
    }

    /**
     * List (or clear) visitor questions the bot could not answer from site content.
     *
     * ## OPTIONS
     *
     * [--clear]
     * : Clear the unanswered-question log instead of listing it.
     *
     * ## EXAMPLES
     *
     *     wp raplsaich unanswered
     *     wp raplsaich unanswered --clear
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments.
     */
    public function unanswered($args, $assoc_args) {
        if (!empty($assoc_args['clear'])) {
            delete_option('raplsaich_unanswered_log');
            WP_CLI::success('Unanswered-question log cleared.');
            return;
        }

        $log = get_option('raplsaich_unanswered_log', []);
        if (!is_array($log) || empty($log)) {
            WP_CLI::log('No unanswered questions recorded.');
            return;
        }

        $rows = [];
        foreach ($log as $entry) {
            $rows[] = [
                'question' => (string) ($entry['q'] ?? ''),
                'count'    => (int) ($entry['n'] ?? 1),
                'reason'   => (string) ($entry['reason'] ?? ''),
                'last_seen' => ($entry['t'] ?? 0) ? gmdate('Y-m-d H:i', (int) $entry['t']) : '',
            ];
        }

        WP_CLI\Utils\format_items('table', $rows, ['question', 'count', 'reason', 'last_seen']);
    }
}

WP_CLI::add_command('raplsaich', 'RAPLSAICH_CLI');
