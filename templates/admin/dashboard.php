<?php
/**
 * Dashboard template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpaic-admin">
    <h1><?php esc_html_e('AI Chatbot - Dashboard', 'rapls-ai-chatbot'); ?></h1>

    <?php if (!$is_unlimited && $remaining_messages <= 0): ?>
    <!-- Message Limit Reached Warning -->
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('Monthly AI response limit reached.', 'rapls-ai-chatbot'); ?></strong>
            <?php esc_html_e('The chatbot can no longer generate AI responses this month. Upgrade to Pro for unlimited responses.', 'rapls-ai-chatbot'); ?>
            <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Get Pro Version', 'rapls-ai-chatbot'); ?>
            </a>
        </p>
    </div>
    <?php elseif (!get_option('wpaic_pro_active')): ?>
    <!-- Pro Version Notice -->
    <div class="notice notice-info is-dismissible">
        <p>
            <strong><?php esc_html_e('Upgrade to Pro', 'rapls-ai-chatbot'); ?>:</strong>
            <?php esc_html_e('Unlock lead capture, analytics, webhooks, and more features.', 'rapls-ai-chatbot'); ?>
            <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Get Pro Version', 'rapls-ai-chatbot'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="wpaic-dashboard-grid">
        <!-- Statistics Cards -->
        <div class="wpaic-stats-cards">
            <div class="wpaic-stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['total_conversations'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Conversations', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="wpaic-stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['today_messages'])); ?></div>
                    <div class="stat-label"><?php esc_html_e("Today's Messages", 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="wpaic-stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['indexed_pages'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <div class="wpaic-stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($stats['knowledge_count'] ?? 0)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Custom Knowledge', 'rapls-ai-chatbot'); ?></div>
                </div>
            </div>

            <?php
            // Cache stats (Pro feature)
            $dashboard_settings = get_option('wpaic_settings', []);
            $dashboard_pro_settings = $dashboard_settings['pro_features'] ?? [];
            if (!empty($dashboard_pro_settings['response_cache_enabled']) && get_option('wpaic_pro_active')):
                $cache_stats = WPAIC_Message::get_cache_stats(30);
                if ($cache_stats['total_requests'] > 0):
            ?>
            <div class="wpaic-stat-card">
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

            <?php if (!$is_unlimited): ?>
            <div class="wpaic-stat-card <?php echo esc_attr($remaining_messages <= 0 ? 'wpaic-stat-card-critical' : ($remaining_messages <= ceil($message_limit * 0.2) ? 'wpaic-stat-card-warning' : '')); ?>">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html($used_messages . ' / ' . $message_limit); ?></div>
                    <div class="stat-label"><?php esc_html_e('Monthly AI Responses', 'rapls-ai-chatbot'); ?></div>
                    <?php if ($remaining_messages <= 0): ?>
                        <div class="stat-sub" style="color: #d63638; font-size: 12px; margin-top: 4px;">
                            <?php esc_html_e('Limit reached', 'rapls-ai-chatbot'); ?>
                        </div>
                    <?php else: ?>
                        <div class="stat-sub" style="color: #666; font-size: 12px; margin-top: 4px;">
                            <?php
                            printf(
                                /* translators: %d: number of remaining messages */
                                esc_html__('%d remaining', 'rapls-ai-chatbot'),
                                absint($remaining_messages)
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status Panel -->
        <div class="wpaic-status-panel">
            <h2><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></h2>
            <?php
            $settings = get_option('wpaic_settings', []);
            $has_api_key = !empty($settings['openai_api_key']) || !empty($settings['claude_api_key']) || !empty($settings['gemini_api_key']) || !empty($settings['openrouter_api_key']);
            ?>
            <table class="wpaic-status-table">
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
        <div class="wpaic-card wpaic-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e('API Usage (Last 30 Days)', 'rapls-ai-chatbot'); ?></h2>
                <button type="button" id="wpaic-reset-usage" class="button button-secondary">
                    🔄 <?php esc_html_e('Reset Statistics', 'rapls-ai-chatbot'); ?>
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="wpaic-usage-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="wpaic-usage-card" style="background: #f0f0f1; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #2271b1;">
                        <?php echo esc_html(number_format($usage_stats['totals']['total_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Total Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="wpaic-usage-card" style="background: #e7f5e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;">
                        <?php echo esc_html(number_format($usage_stats['totals']['input_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Input Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="wpaic-usage-card" style="background: #fef4e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #dba617;">
                        <?php echo esc_html(number_format($usage_stats['totals']['output_tokens'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Output Tokens', 'rapls-ai-chatbot'); ?></div>
                </div>
                <div class="wpaic-usage-card" style="background: #fce7e7; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #d63638;">
                        <?php echo esc_html($usage_stats['totals']['cost_formatted'] ?? '$0.00'); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Estimated Cost', 'rapls-ai-chatbot'); ?></div>
                    <div style="font-size: 10px; color: #999;">
                        (<?php echo esc_html(WPAIC_Cost_Calculator::format_cost_jpy($usage_stats['totals']['cost'] ?? 0)); ?>)
                    </div>
                </div>
            </div>

            <!-- Usage Chart -->
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;"><?php esc_html_e('Daily Token Usage', 'rapls-ai-chatbot'); ?></h3>
                <div style="height: 250px;">
                    <canvas id="wpaic-usage-chart"></canvas>
                </div>
            </div>

            <!-- Model Breakdown -->
            <?php if (!empty($usage_stats['model_totals'])): ?>
            <div>
                <h3 style="margin-bottom: 10px;"><?php esc_html_e('Usage by Model', 'rapls-ai-chatbot'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('ai_model', __('Model', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'ASC', 'model_')); ?></th>
                            <th><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('input_tokens', __('Input Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('output_tokens', __('Output Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('total_tokens', __('Total Tokens', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
                            <th style="text-align: right;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('cost', __('Estimated Cost', 'rapls-ai-chatbot'), $model_orderby, $model_order, 'DESC', 'model_')); ?></th>
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
                                    (<?php echo esc_html(WPAIC_Cost_Calculator::format_cost_jpy($model['cost'] ?? 0)); ?>)
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

<script>
jQuery(document).ready(function($) {
    // Token Usage Chart
    var ctx = document.getElementById('wpaic-usage-chart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode($chart_data['labels']); ?>,
                datasets: [
                    {
                        label: '<?php echo esc_js(__('Input Tokens', 'rapls-ai-chatbot')); ?>',
                        data: <?php echo wp_json_encode($chart_data['input_data']); ?>,
                        backgroundColor: 'rgba(0, 163, 42, 0.6)',
                        borderColor: 'rgba(0, 163, 42, 1)',
                        borderWidth: 1
                    },
                    {
                        label: '<?php echo esc_js(__('Output Tokens', 'rapls-ai-chatbot')); ?>',
                        data: <?php echo wp_json_encode($chart_data['output_data']); ?>,
                        backgroundColor: 'rgba(219, 166, 23, 0.6)',
                        borderColor: 'rgba(219, 166, 23, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return (value / 1000).toFixed(0) + 'K';
                                }
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' ' + <?php echo wp_json_encode(__('tokens', 'rapls-ai-chatbot')); ?>;
                            }
                        }
                    }
                }
            }
        });
    }

    // Reset Usage Statistics
    $('#wpaic-reset-usage').on('click', function() {
        var $btn = $(this);

        if (!confirm('<?php echo esc_js(__('Are you sure you want to reset usage statistics?\nThis will clear all token counts but keep conversation history.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php echo esc_js(__('Resetting...', 'rapls-ai-chatbot')); ?>');

        wpaicDestructiveAjax({
            data: {
                action: 'wpaic_reset_usage',
                nonce: wpaicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to reset.', 'rapls-ai-chatbot')); ?>');
                    $btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Reset Statistics', 'rapls-ai-chatbot')); ?>');
                }
            },
            fail: function() {
                alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Reset Statistics', 'rapls-ai-chatbot')); ?>');
            },
            cancel: function() {
                $btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Reset Statistics', 'rapls-ai-chatbot')); ?>');
            }
        });
    });
});
</script>
