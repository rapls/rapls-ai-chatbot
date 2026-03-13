<?php
/**
 * Conversations page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$total_pages = ceil($total / 20);
$is_pro_active = get_option('wpaic_pro_active');
$has_filters = ($search ?? '') !== '' || ($status_filter ?? '') !== '' || ($date_from ?? '') !== '' || ($date_to ?? '') !== '';
?>
<style>
.wpaic-handoff-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
.wpaic-handoff-badge--pending {
    background: #fff3e0;
    color: #e65100;
}
.wpaic-handoff-badge--active {
    background: #e8f5e9;
    color: #2e7d32;
}
.wpaic-handoff-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #e65100;
    animation: wpaic-pulse 1.5s ease-in-out infinite;
}
.wpaic-handoff-dot--active {
    background: #2e7d32;
}
@keyframes wpaic-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.wpaic-screenshot-badge {
    display: inline-block;
    margin-left: 4px;
    font-size: 14px;
    vertical-align: middle;
    cursor: help;
}
</style>
<div class="wrap wpaic-admin">
    <h1><?php esc_html_e('AI Chatbot - Conversations', 'rapls-ai-chatbot'); ?></h1>

    <!-- Statistics -->
    <div class="wpaic-list-stats">
        <div class="wpaic-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['total'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card stat-highlight">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['active'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['closed'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card stat-info">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['today'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Today', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php if ($conv_stats['archived'] > 0): ?>
        <div class="wpaic-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['archived'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Archived', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($conv_stats['handoff'] > 0): ?>
        <div class="wpaic-list-stat-card" style="border-left: 3px solid #e65100;">
            <div class="stat-value" style="color: #e65100;"><?php echo esc_html(number_format($conv_stats['handoff'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Handoff', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search & Filter -->
    <form method="get" class="wpaic-conversation-filters" style="display: flex; gap: 8px; align-items: center; margin: 12px 0; flex-wrap: wrap;">
        <input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['page'] ?? ''))); ?>">
        <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
        <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">

        <input type="search" name="s" value="<?php echo esc_attr($search ?? ''); ?>"
               placeholder="<?php esc_attr_e('Search messages...', 'rapls-ai-chatbot'); ?>"
               style="min-width: 220px;">

        <select name="status">
            <option value="" <?php selected($status_filter ?? '', ''); ?>><?php esc_html_e('Active & Closed', 'rapls-ai-chatbot'); ?></option>
            <option value="active" <?php selected($status_filter ?? '', 'active'); ?>><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></option>
            <option value="closed" <?php selected($status_filter ?? '', 'closed'); ?>><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></option>
            <option value="archived" <?php selected($status_filter ?? '', 'archived'); ?>><?php esc_html_e('Archived', 'rapls-ai-chatbot'); ?></option>
            <option value="all" <?php selected($status_filter ?? '', 'all'); ?>><?php esc_html_e('All Statuses', 'rapls-ai-chatbot'); ?></option>
        </select>

        <input type="date" name="date_from" value="<?php echo esc_attr($date_from ?? ''); ?>"
               placeholder="<?php esc_attr_e('From', 'rapls-ai-chatbot'); ?>">
        <input type="date" name="date_to" value="<?php echo esc_attr($date_to ?? ''); ?>"
               placeholder="<?php esc_attr_e('To', 'rapls-ai-chatbot'); ?>">

        <?php submit_button(__('Filter', 'rapls-ai-chatbot'), 'secondary', 'filter_action', false); ?>

        <?php if ($has_filters): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'] ?? '')))); ?>" class="button"><?php esc_html_e('Clear', 'rapls-ai-chatbot'); ?></a>
        <?php endif; ?>
    </form>

    <?php if (!empty($conversations)): ?>
        <!-- Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <button type="button" id="wpaic-delete-selected" class="button" disabled>
                    <?php esc_html_e('Delete Selected', 'rapls-ai-chatbot'); ?>
                </button>
                <button type="button" id="wpaic-delete-all" class="button button-link-delete">
                    <?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?>
                </button>
                <button type="button" id="wpaic-reset-sessions" class="button" style="margin-left: 10px;">
                    <?php esc_html_e('Reset All User Sessions', 'rapls-ai-chatbot'); ?>
                </button>
            </div>
            <div class="alignright" style="display: flex; gap: 10px; align-items: center;">
                <select id="wpaic-export-format" <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                </select>
                <input type="date" id="wpaic-export-date-from" placeholder="<?php esc_attr_e('From', 'rapls-ai-chatbot'); ?>" <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                <input type="date" id="wpaic-export-date-to" placeholder="<?php esc_attr_e('To', 'rapls-ai-chatbot'); ?>" <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                <button type="button" id="wpaic-export-conversations" class="button" <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                    <?php esc_html_e('Export', 'rapls-ai-chatbot'); ?>
                    <?php if (!$is_pro_active): ?>
                    <span class="wpaic-pro-badge-small">PRO</span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="wpaic-select-all" aria-label="<?php esc_attr_e('Select all conversations', 'rapls-ai-chatbot'); ?>"></th>
                    <th style="width: 60px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('id', 'ID', $orderby, $order, 'DESC')); ?></th>
                    <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('session_id', __('Session', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 60px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('message_count', __('Msgs', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></th>
                    <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('page_url', __('Start Page', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 100px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('status', __('Status', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 100px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('handoff_status', __('Handoff', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('created_at', __('Started', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('updated_at', __('Last Updated', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    // Get lead info for this conversation
                    $lead = WPAIC_Lead::get_by_conversation($conv['id']);
                    ?>
                    <tr data-id="<?php echo esc_attr($conv['id']); ?>">
                        <td><input type="checkbox" class="wpaic-conv-checkbox" value="<?php echo esc_attr($conv['id']); ?>" aria-label="<?php /* translators: %d: conversation ID */ printf(esc_attr__('Select conversation %d', 'rapls-ai-chatbot'), (int) $conv['id']); ?>"></td>
                        <td><?php echo esc_html($conv['id']); ?></td>
                        <td>
                            <code class="wpaic-copy-session" title="<?php echo esc_attr($conv['session_id']); ?>" style="cursor: pointer;"><?php echo esc_html(substr($conv['session_id'], 0, 8)); ?>...</code>
                        </td>
                        <td style="text-align: center;">
                            <?php echo esc_html(isset($conv['message_count']) ? number_format((int) $conv['message_count']) : '-'); ?>
                            <?php if (!empty($conv['has_screenshot'])): ?>
                                <span class="wpaic-screenshot-badge" title="<?php esc_attr_e('Contains screenshot', 'rapls-ai-chatbot'); ?>">&#128247;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead): ?>
                                <div class="wpaic-lead-info">
                                    <?php if (!empty($lead['name'])): ?>
                                        <strong><?php echo esc_html($lead['name']); ?></strong><br>
                                    <?php endif; ?>
                                    <a href="mailto:<?php echo esc_attr($lead['email']); ?>" style="font-size: 12px;">
                                        <?php echo esc_html($lead['email']); ?>
                                    </a>
                                    <?php if (!empty($lead['phone'])): ?>
                                        <br><span style="font-size: 11px; color: #666;"><?php echo esc_html($lead['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <em style="color: #999;">-</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($conv['page_url'])): ?>
                                <a href="<?php echo esc_url($conv['page_url']); ?>" target="_blank" title="<?php echo esc_attr($conv['page_url']); ?>">
                                    <?php echo esc_html(wp_trim_words($conv['page_url'], 5)); ?>
                                </a>
                            <?php else: ?>
                                <em>-</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($conv['status']); ?>">
                                <?php
                                $status_labels = [
                                    'active'   => __('Active', 'rapls-ai-chatbot'),
                                    'closed'   => __('Closed', 'rapls-ai-chatbot'),
                                    'archived' => __('Archived', 'rapls-ai-chatbot'),
                                ];
                                echo esc_html($status_labels[$conv['status']] ?? $conv['status']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $handoff = $conv['handoff_status'] ?? null;
                            if ($handoff === 'pending'):
                            ?>
                                <span class="wpaic-handoff-badge wpaic-handoff-badge--pending">
                                    <span class="wpaic-handoff-dot"></span>
                                    <?php esc_html_e('Pending', 'rapls-ai-chatbot'); ?>
                                </span>
                                <button type="button" class="button button-small wpaic-reset-handoff" data-id="<?php echo esc_attr($conv['id']); ?>" title="<?php esc_attr_e('Reset handoff status', 'rapls-ai-chatbot'); ?>" style="margin-top: 4px; font-size: 11px;">
                                    <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                </button>
                            <?php elseif ($handoff === 'active'): ?>
                                <span class="wpaic-handoff-badge wpaic-handoff-badge--active">
                                    <span class="wpaic-handoff-dot wpaic-handoff-dot--active"></span>
                                    <?php esc_html_e('Active', 'rapls-ai-chatbot'); ?>
                                </span>
                                <button type="button" class="button button-small wpaic-reset-handoff" data-id="<?php echo esc_attr($conv['id']); ?>" title="<?php esc_attr_e('Reset handoff status', 'rapls-ai-chatbot'); ?>" style="margin-top: 4px; font-size: 11px;">
                                    <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                </button>
                            <?php else: ?>
                                <em style="color: #999;">—</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['created_at'])); ?></td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['updated_at'])); ?></td>
                        <td>
                            <button type="button" class="button button-small wpaic-view-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>">
                                <?php esc_html_e('Details', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php if ($conv['status'] === 'archived'): ?>
                            <button type="button" class="button button-small wpaic-unarchive-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    title="<?php esc_attr_e('Restore', 'rapls-ai-chatbot'); ?>">
                                <?php esc_html_e('Restore', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php else: ?>
                            <button type="button" class="button button-small wpaic-archive-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    title="<?php esc_attr_e('Archive', 'rapls-ai-chatbot'); ?>">
                                <?php esc_html_e('Archive', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="button button-small wpaic-download-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    data-session="<?php echo esc_attr($conv['session_id']); ?>"
                                    title="<?php esc_attr_e('Download', 'rapls-ai-chatbot'); ?>">
                                <span class="dashicons dashicons-download" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                            </button>
                            <button type="button" class="button button-small button-link-delete wpaic-delete-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>">
                                <?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html(number_format($total)); ?> <?php esc_html_e('items', 'rapls-ai-chatbot'); ?></span>
                    <span class="pagination-links">
                        <?php
                        $pagination_args = [
                            'orderby' => $orderby,
                            'order'   => $order,
                            'paged'   => '%#%',
                        ];
                        if (!empty($search)) {
                            $pagination_args['s'] = $search;
                        }
                        if (!empty($status_filter)) {
                            $pagination_args['status'] = $status_filter;
                        }
                        if (!empty($date_from)) {
                            $pagination_args['date_from'] = $date_from;
                        }
                        if (!empty($date_to)) {
                            $pagination_args['date_to'] = $date_to;
                        }
                        $pagination_base = add_query_arg($pagination_args);
                        echo wp_kses_post(paginate_links([
                            'base'      => $pagination_base,
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $page,
                        ]));
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($has_filters): ?>
        <p><?php esc_html_e('No conversations match the current filters.', 'rapls-ai-chatbot'); ?></p>
    <?php else: ?>
        <p><?php esc_html_e('No conversation history.', 'rapls-ai-chatbot'); ?></p>
    <?php endif; ?>

    <!-- Conversation Detail Modal -->
    <div id="wpaic-conversation-modal" class="wpaic-modal" style="display: none;">
        <div class="wpaic-modal-content">
            <div class="wpaic-modal-header">
                <h2><?php esc_html_e('Conversation Details', 'rapls-ai-chatbot'); ?></h2>
                <div class="wpaic-modal-header-actions">
                    <span id="wpaic-handoff-status-label" class="wpaic-handoff-badge" style="display:none;"></span>
                    <button type="button" id="wpaic-operator-start" class="button button-small" style="display:none;">
                        <?php esc_html_e('Start Operator Chat', 'rapls-ai-chatbot'); ?>
                    </button>
                    <button type="button" id="wpaic-operator-end" class="button button-small button-link-delete" style="display:none;">
                        <?php esc_html_e('End Operator Chat', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>
                <button type="button" class="wpaic-modal-close">&times;</button>
            </div>
            <div class="wpaic-modal-body">
                <div id="wpaic-conversation-messages"></div>
            </div>
            <div id="wpaic-operator-reply-form" class="wpaic-operator-reply" style="display:none;">
                <textarea id="wpaic-operator-message" rows="2" placeholder="<?php esc_attr_e('Type a reply...', 'rapls-ai-chatbot'); ?>"></textarea>
                <button type="button" id="wpaic-operator-send" class="button button-primary"><?php esc_html_e('Send', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.wpaic-msg-meta {
    display: inline-flex;
    gap: 6px;
    margin-top: 4px;
}
.wpaic-msg-meta-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
    line-height: 1.4;
    background: #f0f0f1;
    color: #50575e;
}
.wpaic-msg-meta-badge.cached {
    background: #dff0d8;
    color: #3c763d;
}
.wpaic-modal-header {
    display: flex;
    align-items: center;
    gap: 10px;
}
.wpaic-modal-header h2 {
    flex: 0 0 auto;
}
.wpaic-modal-header-actions {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}
.wpaic-modal-header .wpaic-modal-close {
    flex: 0 0 auto;
}
.wpaic-operator-reply {
    display: flex;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #ddd;
    background: #f9f9f9;
    align-items: flex-end;
}
.wpaic-operator-reply textarea {
    flex: 1;
    resize: vertical;
    min-height: 36px;
    max-height: 120px;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.wpaic-operator-reply .button {
    flex: 0 0 auto;
    height: 36px;
}
.wpaic-message.message-operator {
    background: #e8f0fe !important;
    border-left: 3px solid #1a73e8 !important;
}
.wpaic-operator-role-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
    background: #1a73e8;
    color: #fff;
    margin-left: 6px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var i18n = wpaicAdmin.i18n || {};
    var _operatorPollTimer = null;
    var _currentConvId = null;
    var _lastMessageId = 0;
    var _isPro = !!(wpaicAdmin.isPro);
    var _apiBase = (wpaicAdmin.restUrl || '/wp-json/') + 'wp-ai-chatbot/v1';

    // Build a single message DOM element
    function buildMessageEl(msg, allMessages) {
        var roleLabel;
        var roleClass;
        if (msg.role === 'user') {
            roleLabel = '<?php echo esc_js(__('User', 'rapls-ai-chatbot')); ?>';
            roleClass = 'user';
        } else if (msg.role === 'operator') {
            roleLabel = '<?php echo esc_js(__('Operator', 'rapls-ai-chatbot')); ?>';
            roleClass = 'operator';
        } else {
            roleLabel = '<?php echo esc_js(__('AI', 'rapls-ai-chatbot')); ?>';
            roleClass = 'assistant';
        }

        var wrap = document.createElement('div');
        wrap.className = 'wpaic-message message-' + roleClass;
        if (msg.id) wrap.dataset.messageId = msg.id;

        var header = document.createElement('div');
        var strong = document.createElement('strong');
        strong.textContent = roleLabel;
        header.appendChild(strong);

        if (msg.role === 'operator') {
            var roleBadge = document.createElement('span');
            roleBadge.className = 'wpaic-operator-role-badge';
            roleBadge.textContent = '<?php echo esc_js(__('Operator', 'rapls-ai-chatbot')); ?>';
            header.appendChild(roleBadge);
        }

        header.appendChild(document.createTextNode(' '));
        var small = document.createElement('small');
        small.textContent = '(' + msg.created_at + ')';
        header.appendChild(small);

        // Feedback badge
        if (msg.role === 'assistant' && typeof msg.feedback !== 'undefined') {
            var badge = document.createElement('span');
            if (msg.feedback === 1) {
                badge.className = 'wpaic-feedback-badge wpaic-feedback-positive';
                badge.title = '<?php echo esc_attr(__('Positive feedback', 'rapls-ai-chatbot')); ?>';
                badge.textContent = '\uD83D\uDC4D';
                header.appendChild(document.createTextNode(' '));
                header.appendChild(badge);
            } else if (msg.feedback === -1) {
                badge.className = 'wpaic-feedback-badge wpaic-feedback-negative';
                badge.title = '<?php echo esc_attr(__('Negative feedback', 'rapls-ai-chatbot')); ?>';
                badge.textContent = '\uD83D\uDC4E';
                header.appendChild(document.createTextNode(' '));
                header.appendChild(badge);
            }
        }

        // Extract [image:URL] markers
        var textContent = msg.content || '';
        var imageMatch = textContent.match(/\[image:(https?:\/\/[^\]]+)\]/);
        if (imageMatch) {
            textContent = textContent.replace(/\n?\[image:[^\]]+\]/, '').trim();
        }

        var p = document.createElement('p');
        p.style.whiteSpace = 'pre-wrap';
        p.textContent = textContent;

        wrap.appendChild(header);
        if (imageMatch) {
            var imgWrap = document.createElement('div');
            imgWrap.style.cssText = 'margin: 6px 0;';
            var img = document.createElement('img');
            img.src = imageMatch[1];
            img.alt = <?php echo wp_json_encode(__('Screenshot', 'rapls-ai-chatbot')); ?>;
            img.style.cssText = 'max-width: 300px; max-height: 200px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer;';
            img.addEventListener('click', function() { window.open(this.src, '_blank'); });
            imgWrap.appendChild(img);
            wrap.appendChild(imgWrap);
        }
        wrap.appendChild(p);

        // Suggest Improvement (Pro)
        if (msg.role === 'assistant' && _isPro && allMessages) {
            var suggestBtn = document.createElement('button');
            suggestBtn.type = 'button';
            suggestBtn.className = 'button button-small wpaic-suggest-edit';
            suggestBtn.textContent = '<?php echo esc_js(__('Suggest Improvement', 'rapls-ai-chatbot')); ?>';
            suggestBtn.style.cssText = 'margin-top: 6px; font-size: 11px;';
            suggestBtn.dataset.content = msg.content;
            var idx = allMessages.indexOf(msg);
            suggestBtn.dataset.userMsg = (idx > 0 ? allMessages[idx - 1].content : '') || '';
            wrap.appendChild(suggestBtn);
        }

        // AI metadata badges
        if (msg.role === 'assistant' && (msg.ai_model || msg.tokens || msg.cache_hit)) {
            var metaDiv = document.createElement('div');
            metaDiv.className = 'wpaic-msg-meta';
            if (msg.ai_model) {
                var modelBadge = document.createElement('span');
                modelBadge.className = 'wpaic-msg-meta-badge';
                modelBadge.textContent = msg.ai_model;
                metaDiv.appendChild(modelBadge);
            }
            if (msg.tokens) {
                var tokenBadge = document.createElement('span');
                tokenBadge.className = 'wpaic-msg-meta-badge';
                tokenBadge.textContent = msg.tokens.toLocaleString() + ' <?php echo esc_js(__('tokens', 'rapls-ai-chatbot')); ?>';
                metaDiv.appendChild(tokenBadge);
            }
            if (msg.cache_hit) {
                var cacheBadge = document.createElement('span');
                cacheBadge.className = 'wpaic-msg-meta-badge cached';
                cacheBadge.textContent = '\u26A1 <?php echo esc_js(__('cached', 'rapls-ai-chatbot')); ?>';
                metaDiv.appendChild(cacheBadge);
            }
            wrap.appendChild(metaDiv);
        }

        return wrap;
    }

    // Update operator UI state based on handoff status
    function updateOperatorUI(handoffStatus) {
        var $start = $('#wpaic-operator-start');
        var $end = $('#wpaic-operator-end');
        var $form = $('#wpaic-operator-reply-form');
        var $label = $('#wpaic-handoff-status-label');

        if (!_isPro) {
            $start.hide(); $end.hide(); $form.hide(); $label.hide();
            return;
        }

        if (handoffStatus === 'pending' || handoffStatus === 'active') {
            $start.hide();
            $end.show();
            $form.show();
            $label.show().text(handoffStatus === 'pending'
                ? '<?php echo esc_js(__('Pending', 'rapls-ai-chatbot')); ?>'
                : '<?php echo esc_js(__('Operator Active', 'rapls-ai-chatbot')); ?>'
            ).attr('class', 'wpaic-handoff-badge ' + (handoffStatus === 'pending' ? 'wpaic-handoff-badge--pending' : 'wpaic-handoff-badge--active'));
            startOperatorPolling();
        } else {
            $start.show();
            $end.hide();
            $form.hide();
            $label.hide();
            stopOperatorPolling();
        }
    }

    // Polling for new messages
    function startOperatorPolling() {
        stopOperatorPolling();
        _operatorPollTimer = setInterval(function() {
            if (!_currentConvId) return;
            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_get_conversation_messages',
                    nonce: wpaicAdmin.nonce,
                    conversation_id: _currentConvId
                },
                success: function(response) {
                    if (!response.success) return;
                    var data = response.data;
                    var messages = data.messages || data;
                    if (!messages || !messages.length) return;

                    // Find new messages
                    var container = document.getElementById('wpaic-conversation-messages');
                    var lastRendered = _lastMessageId;
                    messages.forEach(function(msg) {
                        if (msg.id && msg.id > lastRendered) {
                            container.appendChild(buildMessageEl(msg, null));
                            _lastMessageId = msg.id;
                        }
                    });

                    // Update handoff status
                    if (data.handoff_status !== undefined) {
                        updateOperatorUI(data.handoff_status);
                    }

                    // Auto-scroll
                    var modalBody = container.closest('.wpaic-modal-body');
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                }
            });
        }, 5000);
    }

    function stopOperatorPolling() {
        if (_operatorPollTimer) {
            clearInterval(_operatorPollTimer);
            _operatorPollTimer = null;
        }
    }

    // View conversation details
    $('.wpaic-view-conversation').on('click', function() {
        var conversationId = $(this).data('id');
        _currentConvId = conversationId;
        _lastMessageId = 0;
        var modal = $('#wpaic-conversation-modal');
        var messagesContainer = $('#wpaic-conversation-messages');

        messagesContainer.empty().append($('<p></p>').text(i18n.processing || 'Loading...'));
        $('#wpaic-operator-reply-form').hide();
        $('#wpaic-operator-start').hide();
        $('#wpaic-operator-end').hide();
        $('#wpaic-handoff-status-label').hide();
        modal.show();

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_get_conversation_messages',
                nonce: wpaicAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (!response.success) {
                    messagesContainer.empty().append($('<p></p>').text(i18n.error || 'Error'));
                    return;
                }

                var data = response.data;
                // Support both old format (array) and new format ({messages, handoff_status})
                var messages = Array.isArray(data) ? data : (data.messages || []);
                var handoffStatus = data.handoff_status || null;

                if (messages.length > 0) {
                    var container = document.createElement('div');
                    messages.forEach(function(msg) {
                        container.appendChild(buildMessageEl(msg, messages));
                        if (msg.id && msg.id > _lastMessageId) _lastMessageId = msg.id;
                    });
                    messagesContainer.empty().append(container);

                    // Auto-scroll to bottom
                    var modalBody = messagesContainer.closest('.wpaic-modal-body')[0];
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                } else {
                    messagesContainer.html('<p><?php echo esc_js(__('No messages.', 'rapls-ai-chatbot')); ?></p>');
                }

                updateOperatorUI(handoffStatus);
            }
        });
    });

    // Response edit suggestion (Pro)
    $(document).on('click', '.wpaic-suggest-edit', function() {
        var $btn = $(this);
        var content = $btn.data('content');
        var userMsg = $btn.data('userMsg') || '';
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'rapls-ai-chatbot')); ?>');

        // Remove any existing suggestion
        $btn.siblings('.wpaic-edit-suggestion').remove();

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_suggest_response_edit',
                nonce: wpaicAdmin.nonce,
                content: content,
                user_message: userMsg
            },
            success: function(response) {
                if (response.success && response.data.suggestions) {
                    var sugDiv = document.createElement('div');
                    sugDiv.className = 'wpaic-edit-suggestion';
                    sugDiv.style.cssText = 'margin-top: 8px; padding: 10px; background: #fef9e7; border: 1px solid #f0c36d; border-radius: 4px; font-size: 13px; white-space: pre-wrap;';
                    sugDiv.textContent = response.data.suggestions;
                    $btn.after(sugDiv);
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to generate suggestions.', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Request failed.', 'rapls-ai-chatbot')); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Suggest Improvement', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Close modal
    $('.wpaic-modal-close, .wpaic-modal').on('click', function(e) {
        if (e.target === this) {
            $('#wpaic-conversation-modal').hide();
            stopOperatorPolling();
            _currentConvId = null;
        }
    });

    // Operator: Start chat (set handoff to pending)
    $('#wpaic-operator-start').on('click', function() {
        if (!_currentConvId || !_isPro) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: _apiBase + '/handoff-action',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': wpaicAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, action: 'accept' }),
            success: function(response) {
                if (response.success) {
                    updateOperatorUI('active');
                } else {
                    alert(response.error || i18n.error || 'Error');
                }
            },
            error: function() { alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>'); },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Operator: End chat (resolve handoff)
    $('#wpaic-operator-end').on('click', function() {
        if (!_currentConvId || !_isPro) return;
        if (!confirm('<?php echo esc_js(__('End operator chat and return to AI mode?', 'rapls-ai-chatbot')); ?>')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: _apiBase + '/handoff-action',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': wpaicAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, action: 'resolve' }),
            success: function(response) {
                if (response.success) {
                    updateOperatorUI(null);
                } else {
                    alert(response.error || i18n.error || 'Error');
                }
            },
            error: function() { alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>'); },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Operator: Send reply
    $('#wpaic-operator-send').on('click', function() { sendOperatorReply(); });
    $('#wpaic-operator-message').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendOperatorReply();
        }
    });

    function sendOperatorReply() {
        var $textarea = $('#wpaic-operator-message');
        var message = $textarea.val().trim();
        if (!message || !_currentConvId || !_isPro) return;

        var $btn = $('#wpaic-operator-send');
        $btn.prop('disabled', true);

        $.ajax({
            url: _apiBase + '/operator-reply',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': wpaicAdmin.restNonce },
            data: JSON.stringify({ conversation_id: _currentConvId, message: message }),
            success: function(response) {
                if (response.success) {
                    $textarea.val('');
                    // Add message to UI immediately
                    var container = document.getElementById('wpaic-conversation-messages');
                    var now = new Date();
                    var msgEl = buildMessageEl({
                        id: response.data.message_id,
                        role: 'operator',
                        content: message,
                        created_at: now.getFullYear() + '/' + String(now.getMonth()+1).padStart(2,'0') + '/' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0')
                    }, null);
                    container.appendChild(msgEl);
                    if (response.data.message_id > _lastMessageId) {
                        _lastMessageId = response.data.message_id;
                    }
                    // Auto-scroll
                    var modalBody = container.closest('.wpaic-modal-body');
                    if (modalBody) modalBody.scrollTop = modalBody.scrollHeight;
                    // Update status
                    updateOperatorUI('active');
                } else {
                    alert(response.error || '<?php echo esc_js(__('Failed to send message.', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() { alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>'); },
            complete: function() { $btn.prop('disabled', false); $textarea.focus(); }
        });
    }

    // Select all checkbox
    $('#wpaic-select-all').on('change', function() {
        $('.wpaic-conv-checkbox').prop('checked', $(this).prop('checked'));
        updateDeleteSelectedButton();
    });

    // Individual checkbox
    $('.wpaic-conv-checkbox').on('change', function() {
        updateDeleteSelectedButton();
        var allChecked = $('.wpaic-conv-checkbox:not(:checked)').length === 0;
        $('#wpaic-select-all').prop('checked', allChecked);
    });

    // Update delete selected button state
    function updateDeleteSelectedButton() {
        var checkedCount = $('.wpaic-conv-checkbox:checked').length;
        $('#wpaic-delete-selected').prop('disabled', checkedCount === 0);
    }

    // Restore from archive (delegated for dynamically replaced buttons)
    $(document).on('click', '.wpaic-unarchive-conversation', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_unarchive_conversation',
                nonce: wpaicAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.status-badge').attr('class', 'status-badge status-closed').text('<?php echo esc_js(__('Closed', 'rapls-ai-chatbot')); ?>');
                    $btn.replaceWith(
                        '<button type="button" class="button button-small wpaic-archive-conversation" data-id="' + id + '"><?php echo esc_js(__('Archive', 'rapls-ai-chatbot')); ?></button>'
                    );
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to restore.', 'rapls-ai-chatbot')); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Archive single (delegated for dynamically replaced buttons)
    $(document).on('click', '.wpaic-archive-conversation', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_archive_conversation',
                nonce: wpaicAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.status-badge').attr('class', 'status-badge status-archived').text('<?php echo esc_js(__('Archived', 'rapls-ai-chatbot')); ?>');
                    $btn.remove();
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to archive.', 'rapls-ai-chatbot')); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Download conversation as Markdown
    $('.wpaic-download-conversation').on('click', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var sessionId = $btn.data('session') || id;
        $btn.prop('disabled', true);

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_get_conversation_messages',
                nonce: wpaicAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                $btn.prop('disabled', false);
                if (!response.success) {
                    alert(i18n.error || 'Error');
                    return;
                }

                var data = response.data;
                var messages = Array.isArray(data) ? data : (data.messages || []);

                // Build Markdown
                var md = '# <?php echo esc_js(__('Conversation', 'rapls-ai-chatbot')); ?> #' + id + '\n\n';
                md += '**Session:** ' + sessionId + '\n\n';
                md += '---\n\n';

                messages.forEach(function(msg) {
                    var role = msg.role === 'user' ? '<?php echo esc_js(__('User', 'rapls-ai-chatbot')); ?>'
                             : msg.role === 'operator' ? '<?php echo esc_js(__('Operator', 'rapls-ai-chatbot')); ?>'
                             : '<?php echo esc_js(__('Assistant', 'rapls-ai-chatbot')); ?>';
                    md += '### ' + role;
                    if (msg.created_at) {
                        md += ' (' + msg.created_at + ')';
                    }
                    md += '\n\n' + (msg.content || '') + '\n\n';
                });

                // Download as file
                var blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'conversation-' + id + '.md';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            },
            error: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Delete single
    $('.wpaic-delete-conversation').on('click', function() {
        var id = $(this).data('id');
        if (!confirm(i18n.confirmDelete || '<?php echo esc_js(__('Are you sure you want to delete this conversation?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        var $row = $(this).closest('tr');
        $row.css('opacity', '0.5');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_delete_conversation',
                nonce: wpaicAdmin.nonce,
                conversation_id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.wpaic-conv-checkbox').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $row.css('opacity', '1');
            }
        });
    });

    // Delete selected conversations
    $('#wpaic-delete-selected').on('click', function() {
        var ids = [];
        $('.wpaic-conv-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert('<?php echo esc_js(__('Please select conversations to delete.', 'rapls-ai-chatbot')); ?>');
            return;
        }

        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the selected conversations?', 'rapls-ai-chatbot')); ?>'.replace('%d', ids.length))) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || '<?php echo esc_js(__('Deleting...', 'rapls-ai-chatbot')); ?>');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_delete_conversations_bulk',
                nonce: wpaicAdmin.nonce,
                conversation_ids: ids
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Delete Selected', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $button.prop('disabled', false).text('<?php echo esc_js(__('Delete Selected', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Delete all conversations
    $('#wpaic-delete-all').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete all conversation history?\nThis action cannot be undone.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || '<?php echo esc_js(__('Deleting...', 'rapls-ai-chatbot')); ?>');

        wpaicDestructiveAjax({
            data: { action: 'wpaic_delete_all_conversations', nonce: wpaicAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
                }
            },
            cancel: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
            },
            fail: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $button.prop('disabled', false).text('<?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Reset all user sessions
    $('#wpaic-reset-sessions').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to reset all user sessions?', 'rapls-ai-chatbot')); ?>' + '\n\n' + '<?php echo esc_js(__('This will force all users to start new conversations on their next visit.', 'rapls-ai-chatbot')); ?>' + '\n' + '<?php echo esc_js(__('Existing conversation history will remain in the database.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(i18n.processing || '<?php echo esc_js(__('Processing...', 'rapls-ai-chatbot')); ?>');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_reset_sessions',
                nonce: wpaicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Reset All User Sessions', 'rapls-ai-chatbot')); ?>');
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to reset sessions.', 'rapls-ai-chatbot')); ?>');
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Reset All User Sessions', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $button.prop('disabled', false).text('<?php echo esc_js(__('Reset All User Sessions', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Handoff reset
    $(document).on('click', '.wpaic-reset-handoff', function() {
        var $btn = $(this);
        var convId = $btn.data('id');
        if (!confirm('<?php echo esc_js(__('Reset handoff status for this conversation?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }
        $btn.prop('disabled', true).text('...');
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpaic_reset_handoff',
                conversation_id: convId,
                nonce: wpaicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $td = $btn.closest('td');
                    $td.html('<em style="color:#999;">—</em>');
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to reset handoff.', 'rapls-ai-chatbot')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Reset', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Reset', 'rapls-ai-chatbot')); ?>');
            }
        });
    });
    // Auto-open conversation modal if conversation_id is in URL (e.g., from handoff email)
    var urlParams = new URLSearchParams(window.location.search);
    var autoOpenId = parseInt(urlParams.get('conversation_id'), 10) || 0;
    if (autoOpenId) {
        var $target = $('.wpaic-view-conversation[data-id="' + autoOpenId + '"]');
        if ($target.length) {
            $target.trigger('click');
        } else {
            // Conversation may not be on current page — open modal directly
            var modal = $('#wpaic-conversation-modal');
            var messagesContainer = $('#wpaic-conversation-messages');
            messagesContainer.empty().append($('<p></p>').text(i18n.processing || 'Loading...'));
            modal.show();
            $.ajax({
                url: wpaicAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wpaic_get_conversation_messages',
                    nonce: wpaicAdmin.nonce,
                    conversation_id: autoOpenId
                },
                success: function(response) {
                    if (response.success && response.data && response.data.messages) {
                        messagesContainer.empty();
                        response.data.messages.forEach(function(msg) {
                            messagesContainer.append(buildMessageEl(msg, response.data.messages));
                        });
                    } else {
                        messagesContainer.empty().append($('<p></p>').text(response.data || 'Error'));
                    }
                },
                error: function() {
                    messagesContainer.empty().append($('<p></p>').text(wpaicAdmin.i18n.error || 'Error'));
                }
            });
        }
    }

    // Session ID: click to copy
    $(document).on('click', '.wpaic-copy-session', function() {
        var fullId = $(this).attr('title');
        if (!fullId) return;
        var el = this;
        var copyPromise;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            copyPromise = navigator.clipboard.writeText(fullId);
        } else {
            // Fallback for non-HTTPS or older browsers
            var ta = document.createElement('textarea');
            ta.value = fullId;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copyPromise = Promise.resolve();
        }
        copyPromise.then(function() {
            var orig = el.textContent;
            el.textContent = '<?php echo esc_js(__('Copied!', 'rapls-ai-chatbot')); ?>';
            setTimeout(function() { el.textContent = orig; }, 1000);
        });
    });
});
</script>
