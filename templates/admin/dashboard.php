<?php
/**
 * Dashboard template
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables, not true globals
?>
<div class="wrap raplsaich-admin">
    <h1>
        <?php esc_html_e('AI Chatbot - Dashboard', 'rapls-ai-chatbot'); ?>
        <?php
        $locale = get_locale();
        $free_docs = (strpos($locale, 'ja') === 0)
            ? 'https://raplsworks.com/rapls-ai-chatbot-free-manual-ja/'
            : 'https://raplsworks.com/rapls-ai-chatbot-free-manual-en/';
        $pro_docs = (strpos($locale, 'ja') === 0)
            ? 'https://raplsworks.com/rapls-ai-chatbot-manual-pro-ja/'
            : 'https://raplsworks.com/rapls-ai-chatbot-manual-pro-en/';
        ?>
        <a href="<?php echo esc_url($free_docs); ?>" target="_blank" rel="noopener noreferrer" class="page-title-action"><?php esc_html_e('Free Docs', 'rapls-ai-chatbot'); ?></a>
        <a href="<?php echo esc_url($pro_docs); ?>" target="_blank" rel="noopener noreferrer" class="page-title-action"><?php esc_html_e('Pro Docs', 'rapls-ai-chatbot'); ?></a>
    </h1>

    <?php if (!raplsaich_is_pro_active()): ?>
    <!-- Pro Version Notice (single, dismissible) -->
    <div class="notice notice-info is-dismissible">
        <p>
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: %s: link to Pro page */
                    esc_html__('Unlock analytics, lead capture, scenarios, and more with %s.', 'rapls-ai-chatbot'),
                    '<a href="https://raplsworks.com/plugins/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer">Pro</a>'
                ),
                ['a' => ['href' => true, 'target' => true, 'rel' => true]]
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <?php
    // Review request is now a usage-triggered admin notice registered globally
    // (see RAPLSAICH_Admin::review_request_notice); it renders above this page
    // and on other plugin screens, so it is intentionally no longer inline here.
    ?>

    <?php if (isset($setup_progress) && $setup_progress['done'] < $setup_progress['total']): ?>
    <!-- Setup checklist — hidden automatically once every step is complete -->
    <div class="raplsaich-setup-checklist" style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 16px 20px; margin: 15px 0;">
        <p style="margin: 0 0 10px; font-weight: 600;">
            <?php
            printf(
                /* translators: %1$d: completed steps, %2$d: total steps */
                esc_html__('Setup: %1$d of %2$d steps complete', 'rapls-ai-chatbot'),
                (int) $setup_progress['done'],
                (int) $setup_progress['total']
            );
            ?>
        </p>
        <ul style="margin: 0; list-style: none; padding: 0;">
            <?php foreach ($setup_progress['steps'] as $i => $step): ?>
            <li style="display: flex; align-items: center; gap: 8px; padding: 4px 0;">
                <?php if ($step['done']): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;" aria-hidden="true"></span>
                    <span style="color: #50575e;"><?php echo esc_html($step['label']); ?></span>
                <?php else: ?>
                    <span class="dashicons dashicons-marker" style="color: #c3c4c7;" aria-hidden="true"></span>
                    <span><?php echo esc_html($step['label']); ?></span>
                    <a href="<?php echo esc_url($step['url']); ?>" class="button button-small" style="margin-left: 6px;"><?php echo esc_html($step['action']); ?></a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="raplsaich-dashboard-grid">
        <!-- Statistics Cards -->
        <div class="raplsaich-stats-cards">
            <div class="raplsaich-stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['total_conversations'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Conversations', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="raplsaich-stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['today_messages'])); ?></div>
                    <div class="stat-label"><?php esc_html_e("Today's Messages", 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="raplsaich-stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['indexed_pages'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="raplsaich-stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['knowledge_count'] ?? 0)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Custom Knowledge', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <?php
            // Cache stats (Pro feature)
            $dashboard_settings = get_option('raplsaich_settings', []);
            $dashboard_pro_settings = raplsaich_get_ext_settings($dashboard_settings);
            if (!empty($dashboard_pro_settings['response_cache_enabled']) && raplsaich_is_pro_active()):
                $cache_stats = RAPLSAICH_Message::get_cache_stats(30);
                if ($cache_stats['total_requests'] > 0):
            ?>
            <div class="raplsaich-stat-card">
                <div class="stat-icon">⚡</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html($cache_stats['hit_rate']); ?>%</div>
                    <div class="stat-label"><?php esc_html_e('Cache Hit Rate', 'rapls-ai-chatbot'); ?></div>
                    <div class="stat-sub" style="color: #666; font-size: 12px; margin-top: 4px;">
                        <?php
                        printf(
                            /* translators: %s: number of saved tokens */
                            esc_html__('%s tokens saved', 'rapls-ai-chatbot'),
                            esc_html(number_format($cache_stats['saved_tokens']))
                        );
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; endif; ?>

        </div>

        <!-- Status Panel -->
        <div class="raplsaich-status-panel">
            <h2><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></h2>
            <?php
            $settings = get_option('raplsaich_settings', []);
            $has_api_key = !empty($settings['openai_api_key']) || !empty($settings['claude_api_key']) || !empty($settings['gemini_api_key']) || !empty($settings['openrouter_api_key']);
            ?>
            <table class="raplsaich-status-table">
                <tr>
                    <td><?php esc_html_e('AI Provider', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr( $has_api_key ? 'ok' : 'warning' ); ?>">
                            <?php echo esc_html(strtoupper($settings['ai_provider'] ?? 'openai')); ?>
                            <?php echo $has_api_key ? '<span aria-hidden="true">&#10003;</span>' : '(' . esc_html__('API Key not set', 'rapls-ai-chatbot') . ')'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML entity ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Site Learning', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr( !empty($settings['crawler_enabled']) ? 'ok' : 'off' ); ?>">
                            <?php echo !empty($settings['crawler_enabled']) ? esc_html__('Enabled', 'rapls-ai-chatbot') : esc_html__('Disabled', 'rapls-ai-chatbot'); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Save History', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr( !empty($settings['save_history']) ? 'ok' : 'off' ); ?>">
                            <?php echo !empty($settings['save_history']) ? esc_html__('Enabled', 'rapls-ai-chatbot') : esc_html__('Disabled', 'rapls-ai-chatbot'); ?>
                        </span>
                    </td>
                </tr>
            </table>

            <!-- System health (1.14.0) -->
            <h2 style="margin-top: 20px;"><?php esc_html_e('System Health', 'rapls-ai-chatbot'); ?></h2>
            <table class="raplsaich-status-table">
                <?php if (!empty($health_checks)) : foreach ($health_checks as $health_check) : ?>
                <tr>
                    <td><?php echo esc_html($health_check['label']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($health_check['status'] === 'ok' ? 'ok' : 'warning'); ?>">
                            <?php echo esc_html($health_check['detail']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                <tr>
                    <td><?php esc_html_e('REST API', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <span id="raplsaich-health-rest" class="status-badge status-off">
                            <?php esc_html_e('Checking…', 'rapls-ai-chatbot'); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Usage Statistics -->
        <div class="raplsaich-card raplsaich-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e('API Usage (Last 30 Days)', 'rapls-ai-chatbot'); ?></h2>
                <button type="button" id="raplsaich-reset-usage" class="button button-secondary">
                    🔄 <?php esc_html_e('Reset Statistics', 'rapls-ai-chatbot'); ?>
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="raplsaich-usage-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="raplsaich-usage-card" style="background: #f0f0f1; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #2271b1;">
                        <?php echo esc_html(number_format($usage_stats['totals']['total_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Total Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="raplsaich-usage-card" style="background: #e7f5e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;">
                        <?php echo esc_html(number_format($usage_stats['totals']['input_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Input Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="raplsaich-usage-card" style="background: #fef4e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #dba617;">
                        <?php echo esc_html(number_format($usage_stats['totals']['output_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Output Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="raplsaich-usage-card" style="background: #fce7e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #d63638;">
                        <?php echo esc_html($usage_stats['totals']['cost_formatted'] ?? '$0.00'); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Estimated Cost', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 10px; color: #999;">
                        (<?php echo esc_html(RAPLSAICH_Cost_Calculator::format_cost_jpy($usage_stats['totals']['cost'] ?? 0)); ?>)
                    </div>
                </div>
            </div>

            <!-- Usage Chart -->
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;"><?php esc_html_e('Daily Token Usage', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 250px;">
                    <canvas id="raplsaich-usage-chart"></canvas>
                </div>
            </div>

            <!-- Model Breakdown -->
            <?php if (!empty($usage_stats['model_totals'])): ?>
            <div>
                <h3 style="margin-bottom: 10px;"><?php esc_html_e('Usage by Model', 'rapls-ai-chatbot'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('ai_model', __('Model', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'ASC', 'model_')); ?></th>
                            <th><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('input_tokens', __('Input Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('output_tokens', __('Output Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('total_tokens', __('Total Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('cost', __('Estimated Cost', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usage_stats['model_totals'] as $model): ?>
                        <tr>
                            <td><code><?php echo esc_html($model['ai_model'] ?? 'unknown'); ?></code></td>
                            <td><?php echo esc_html(ucfirst($model['ai_provider'] ?? 'unknown')); ?></td>
                            <td style="text-align: right;"><?php echo esc_html(number_format($model['input_tokens'] ?? 0)); ?></td>
                            <td style="text-align: right;"><?php echo esc_html(number_format($model['output_tokens'] ?? 0)); ?></td>
                            <td style="text-align: right;"><?php echo esc_html(number_format($model['total_tokens'] ?? 0)); ?></td>
                            <td style="text-align: right;">
                                <?php echo esc_html($model['cost_formatted'] ?? '$0.00'); ?>
                                <span style="color: #999; font-size: 11px;">
                                    (<?php echo esc_html(RAPLSAICH_Cost_Calculator::format_cost_jpy($model['cost'] ?? 0)); ?>)
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Content gaps: unanswered questions + FAQ draft generator (1.13.0) -->
        <?php
        $unanswered_log = get_option('raplsaich_unanswered_log', []);
        if (!is_array($unanswered_log)) {
            $unanswered_log = [];
        }
        $unanswered_reason_labels = [
            'no_context' => __('No matching site content', 'rapls-ai-chatbot'),
            'grounding'  => __('Refused (Grounded Answers Only)', 'rapls-ai-chatbot'),
        ];
        ?>
        <div class="raplsaich-card raplsaich-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                <h2 style="margin: 0;"><?php esc_html_e('Unanswered Questions', 'rapls-ai-chatbot'); ?></h2>
                <div>
                    <button type="button" id="raplsaich-generate-faq-draft" class="button button-secondary">
                        ✨ <?php esc_html_e('Generate FAQ draft from questions', 'rapls-ai-chatbot'); ?>
                    </button>
                    <?php if (!empty($unanswered_log)) : ?>
                    <button type="button" id="raplsaich-clear-unanswered" class="button">
                        <?php esc_html_e('Clear list', 'rapls-ai-chatbot'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <p style="color: #666; margin-top: 0;">
                <?php esc_html_e('Questions your visitors asked that the bot could not answer from site content. Adding pages or knowledge entries for these topics improves answer quality the most.', 'rapls-ai-chatbot'); ?>
            </p>

            <?php if (empty($unanswered_log)) : ?>
                <p><em><?php esc_html_e('Nothing recorded yet — the bot has been able to answer from site content so far.', 'rapls-ai-chatbot'); ?></em></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Question', 'rapls-ai-chatbot'); ?></th>
                            <th style="width: 70px; text-align: right;"><?php esc_html_e('Times', 'rapls-ai-chatbot'); ?></th>
                            <th style="width: 200px;"><?php esc_html_e('Reason', 'rapls-ai-chatbot'); ?></th>
                            <th style="width: 140px;"><?php esc_html_e('Last asked', 'rapls-ai-chatbot'); ?></th>
                            <th style="width: 130px;"><span class="screen-reader-text"><?php esc_html_e('Action', 'rapls-ai-chatbot'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($unanswered_log, 0, 10) as $unanswered_entry) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($unanswered_entry['q'] ?? '')); ?></td>
                            <td style="text-align: right;"><?php echo esc_html(number_format((int) ($unanswered_entry['n'] ?? 1))); ?></td>
                            <td><?php echo esc_html($unanswered_reason_labels[$unanswered_entry['reason'] ?? ''] ?? (string) ($unanswered_entry['reason'] ?? '')); ?></td>
                            <td>
                                <?php
                                $unanswered_ts = (int) ($unanswered_entry['t'] ?? 0);
                                echo $unanswered_ts ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $unanswered_ts)) : '—';
                                ?>
                            </td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=raplsaich-knowledge&raplsaich_prefill=' . rawurlencode((string) ($unanswered_entry['q'] ?? '')))); ?>">
                                    <?php esc_html_e('Add to knowledge', 'rapls-ai-chatbot'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($unanswered_log) > 10) : ?>
                <p style="color: #999; font-size: 12px;">
                    <?php
                    printf(
                        /* translators: %d: number of additional recorded questions not shown */
                        esc_html__('…and %d more (latest 50 are kept).', 'rapls-ai-chatbot'),
                        (int) (count($unanswered_log) - 10)
                    );
                    ?>
                </p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- FAQ draft result -->
            <div id="raplsaich-faq-result" style="margin-top: 15px;" hidden>
                <h3 style="margin-bottom: 6px;"><?php esc_html_e('FAQ draft (review and edit before publishing)', 'rapls-ai-chatbot'); ?></h3>
                <textarea id="raplsaich-faq-textarea" readonly rows="14" style="width: 100%; font-family: monospace;"></textarea>
                <p>
                    <button type="button" id="raplsaich-faq-copy" class="button button-secondary"><?php esc_html_e('Copy to clipboard', 'rapls-ai-chatbot'); ?></button>
                    <button type="button" id="raplsaich-faq-save-draft" class="button button-secondary"><?php esc_html_e('Create draft page', 'rapls-ai-chatbot'); ?></button>
                    <a id="raplsaich-faq-edit-link" href="#" target="_blank" rel="noopener noreferrer" class="button button-primary" hidden><?php esc_html_e('Open draft', 'rapls-ai-chatbot'); ?></a>
                    <span id="raplsaich-faq-copied" style="color: #00a32a; margin-left: 8px;" hidden><?php esc_html_e('Copied!', 'rapls-ai-chatbot'); ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var faqNonce = <?php echo wp_json_encode(wp_create_nonce('raplsaich_generate_faq_draft')); ?>;
    var clearNonce = <?php echo wp_json_encode(wp_create_nonce('raplsaich_clear_unanswered')); ?>;
    var saveDraftNonce = <?php echo wp_json_encode(wp_create_nonce('raplsaich_save_faq_draft')); ?>;
    var restProbeUrl = <?php echo wp_json_encode(esc_url_raw(rest_url('rapls-ai-chatbot/v1/message-limit'))); ?>;
    var i18n = {
        generating: <?php echo wp_json_encode(__('Generating… this may take up to a minute.', 'rapls-ai-chatbot')); ?>,
        generateLabel: <?php echo wp_json_encode('✨ ' . __('Generate FAQ draft from questions', 'rapls-ai-chatbot')); ?>,
        errorOccurred: <?php echo wp_json_encode(__('An error occurred.', 'rapls-ai-chatbot')); ?>,
        confirmClear: <?php echo wp_json_encode(__('Clear the unanswered-question list?', 'rapls-ai-chatbot')); ?>,
        saving: <?php echo wp_json_encode(__('Saving…', 'rapls-ai-chatbot')); ?>,
        saveDraftLabel: <?php echo wp_json_encode(__('Create draft page', 'rapls-ai-chatbot')); ?>,
        restOk: <?php echo wp_json_encode(__('Reachable', 'rapls-ai-chatbot')); ?>,
        restNg: <?php echo wp_json_encode(__('Not reachable — the chat widget cannot contact the server. Check security plugins or REST API blockers.', 'rapls-ai-chatbot')); ?>
    };

    // REST reachability probe (client-side, same origin as a real visitor)
    (function() {
        var $badge = $('#raplsaich-health-rest');
        if (!$badge.length || typeof window.fetch !== 'function') return;
        fetch(restProbeUrl, { credentials: 'same-origin' })
            .then(function(r) {
                var ok = r && r.ok;
                $badge.text(ok ? i18n.restOk : i18n.restNg)
                    .removeClass('status-off')
                    .addClass(ok ? 'status-ok' : 'status-warning');
            })
            .catch(function() {
                $badge.text(i18n.restNg).removeClass('status-off').addClass('status-warning');
            });
    })();

    $('#raplsaich-faq-save-draft').on('click', function() {
        var faqText = $('#raplsaich-faq-textarea').val();
        if (!faqText) return;
        var $btn = $(this).prop('disabled', true).text(i18n.saving);
        $.post(ajaxurl, { action: 'raplsaich_save_faq_draft', nonce: saveDraftNonce, faq: faqText })
            .done(function(resp) {
                if (resp && resp.success && resp.data && resp.data.edit_url) {
                    $('#raplsaich-faq-edit-link').attr('href', resp.data.edit_url).prop('hidden', false);
                } else {
                    alert((resp && resp.data && resp.data.message) || i18n.errorOccurred);
                }
            })
            .fail(function(xhr) {
                var msg = i18n.errorOccurred;
                try {
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                } catch (e) {}
                alert(msg);
            })
            .always(function() {
                $btn.prop('disabled', false).text(i18n.saveDraftLabel);
            });
    });

    $('#raplsaich-generate-faq-draft').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text(i18n.generating);
        $.post(ajaxurl, { action: 'raplsaich_generate_faq_draft', nonce: faqNonce })
            .done(function(resp) {
                if (resp && resp.success && resp.data && resp.data.faq) {
                    $('#raplsaich-faq-textarea').val(resp.data.faq);
                    $('#raplsaich-faq-result').prop('hidden', false);
                } else {
                    alert((resp && resp.data && resp.data.message) || i18n.errorOccurred);
                }
            })
            .fail(function(xhr) {
                var msg = i18n.errorOccurred;
                try {
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                } catch (e) {}
                alert(msg);
            })
            .always(function() {
                $btn.prop('disabled', false).text(i18n.generateLabel);
            });
    });

    $('#raplsaich-faq-copy').on('click', function() {
        var el = document.getElementById('raplsaich-faq-textarea');
        el.select();
        try { document.execCommand('copy'); } catch (e) {}
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(el.value).catch(function() {});
        }
        $('#raplsaich-faq-copied').prop('hidden', false);
        setTimeout(function() { $('#raplsaich-faq-copied').prop('hidden', true); }, 2000);
    });

    $('#raplsaich-clear-unanswered').on('click', function() {
        if (!window.confirm(i18n.confirmClear)) return;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxurl, { action: 'raplsaich_clear_unanswered', nonce: clearNonce })
            .done(function() { window.location.reload(); })
            .fail(function() {
                alert(i18n.errorOccurred);
                $btn.prop('disabled', false);
            });
    });
});
</script>

<?php
wp_enqueue_script('raplsaich-admin-dashboard', RAPLSAICH_PLUGIN_URL . 'assets/js/admin-dashboard.js', ['jquery', 'raplsaich-admin', 'raplsaich-chartjs'], RAPLSAICH_VERSION, true);
wp_localize_script('raplsaich-admin-dashboard', 'raplsaichDashboard', [
    'labels'       => $chart_data['labels'],
    'inputData'    => $chart_data['input_data'],
    'outputData'   => $chart_data['output_data'],
    'inputLabel'   => __('Input Tokens', 'rapls-ai-chatbot'),
    'outputLabel'  => __('Output Tokens', 'rapls-ai-chatbot'),
    'tokensLabel'  => __('tokens', 'rapls-ai-chatbot'),
    'confirmReset' => __('Are you sure you want to reset usage statistics?\nThis will clear all token counts but keep conversation history.', 'rapls-ai-chatbot'),
    'resetting'    => __('Resetting...', 'rapls-ai-chatbot'),
    'resetLabel'   => __('Reset Statistics', 'rapls-ai-chatbot'),
    'failedReset'  => __('Failed to reset.', 'rapls-ai-chatbot'),
    'errorOccurred' => __('An error occurred.', 'rapls-ai-chatbot'),
]);
?>
