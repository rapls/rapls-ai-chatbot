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
                    '<a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" rel="noopener noreferrer">Pro</a>'
                ),
                ['a' => ['href' => true, 'target' => true, 'rel' => true]]
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <?php
    // Review request — show only if not dismissed and plugin has been active for 7+ days
    $activated_at = get_option('raplsaich_activated_at', 0);
    if (!$activated_at) {
        update_option('raplsaich_activated_at', time(), false);
        $activated_at = time();
    }
    $dismissed = get_option('raplsaich_review_dismissed', false);
    if (!$dismissed && (time() - $activated_at) > 7 * DAY_IN_SECONDS): ?>
    <div class="notice notice-success is-dismissible raplsaich-review-notice" id="raplsaich-review-notice" style="padding: 12px 16px; border-left-color: #ffb900;">
        <p>
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: %s: link to WordPress.org review page */
                    esc_html__('Enjoying Rapls AI Chatbot? We\'d appreciate a %s review on WordPress.org!', 'rapls-ai-chatbot'),
                    '<a href="https://wordpress.org/support/plugin/rapls-ai-chatbot/reviews/#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
                ),
                ['a' => ['href' => true, 'target' => true, 'rel' => true]]
            );
            ?>
        </p>
    </div>
    <script>jQuery(function($){$('#raplsaich-review-notice').on('click','.notice-dismiss',function(){$.post(ajaxurl,{action:'raplsaich_dismiss_review',nonce:'<?php echo esc_js(wp_create_nonce('raplsaich_dismiss_review')); ?>'});});});</script>
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
    </div>
</div>

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
