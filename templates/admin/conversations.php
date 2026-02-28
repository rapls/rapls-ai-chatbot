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
            <option value=""><?php esc_html_e('All Statuses', 'rapls-ai-chatbot'); ?></option>
            <option value="active" <?php selected($status_filter ?? '', 'active'); ?>><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></option>
            <option value="closed" <?php selected($status_filter ?? '', 'closed'); ?>><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></option>
            <option value="archived" <?php selected($status_filter ?? '', 'archived'); ?>><?php esc_html_e('Archived', 'rapls-ai-chatbot'); ?></option>
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
                    <th style="width: 40px;"><input type="checkbox" id="wpaic-select-all"></th>
                    <th style="width: 60px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('id', 'ID', $orderby, $order, 'DESC')); ?></th>
                    <th><?php esc_html_e('Session', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 60px;"><?php esc_html_e('Msgs', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Start Page', 'rapls-ai-chatbot'); ?></th>
                    <th style="width: 100px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('status', __('Status', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('created_at', __('Started', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('updated_at', __('Last Updated', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    // Get lead info for this conversation
                    $lead = WPAIC_Lead::get_by_conversation($conv['id']);
                    ?>
                    <tr data-id="<?php echo esc_attr($conv['id']); ?>">
                        <td><input type="checkbox" class="wpaic-conv-checkbox" value="<?php echo esc_attr($conv['id']); ?>"></td>
                        <td><?php echo esc_html($conv['id']); ?></td>
                        <td>
                            <code><?php echo esc_html(substr($conv['session_id'], 0, 8)); ?>...</code>
                        </td>
                        <td style="text-align: center;"><?php echo esc_html(isset($conv['message_count']) ? number_format((int) $conv['message_count']) : '-'); ?></td>
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
                                <a href="<?php echo esc_url($conv['page_url']); ?>" target="_blank">
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
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['created_at'])); ?></td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['updated_at'])); ?></td>
                        <td>
                            <button type="button" class="button button-small wpaic-view-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>">
                                <?php esc_html_e('Details', 'rapls-ai-chatbot'); ?>
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
                <button type="button" class="wpaic-modal-close">&times;</button>
            </div>
            <div class="wpaic-modal-body">
                <div id="wpaic-conversation-messages"></div>
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
</style>

<script>
jQuery(document).ready(function($) {
    var i18n = wpaicAdmin.i18n || {};

    // View conversation details
    $('.wpaic-view-conversation').on('click', function() {
        var conversationId = $(this).data('id');
        var modal = $('#wpaic-conversation-modal');
        var messagesContainer = $('#wpaic-conversation-messages');

        messagesContainer.html('<p>' + (i18n.processing || 'Loading...') + '</p>');
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
                if (response.success && response.data.length > 0) {
                    var container = document.createElement('div');
                    response.data.forEach(function(msg) {
                        var roleLabel = msg.role === 'user' ? '<?php echo esc_js(__('User', 'rapls-ai-chatbot')); ?>' : '<?php echo esc_js(__('AI', 'rapls-ai-chatbot')); ?>';

                        var wrap = document.createElement('div');
                        wrap.className = 'wpaic-message message-' + (msg.role === 'user' ? 'user' : 'assistant');

                        var header = document.createElement('div');
                        var strong = document.createElement('strong');
                        strong.textContent = roleLabel;
                        header.appendChild(strong);
                        header.appendChild(document.createTextNode(' '));
                        var small = document.createElement('small');
                        small.textContent = '(' + msg.created_at + ')';
                        header.appendChild(small);

                        // Show feedback status for AI messages
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

                        var p = document.createElement('p');
                        p.style.whiteSpace = 'pre-wrap';
                        p.textContent = msg.content;

                        wrap.appendChild(header);
                        wrap.appendChild(p);

                        // Response edit suggestion button (Pro)
                        if (msg.role === 'assistant' && wpaicAdmin.isPro) {
                            var suggestBtn = document.createElement('button');
                            suggestBtn.type = 'button';
                            suggestBtn.className = 'button button-small wpaic-suggest-edit';
                            suggestBtn.textContent = '<?php echo esc_js(__('Suggest Improvement', 'rapls-ai-chatbot')); ?>';
                            suggestBtn.style.cssText = 'margin-top: 6px; font-size: 11px;';
                            suggestBtn.dataset.content = msg.content;
                            suggestBtn.dataset.userMsg = (response.data[response.data.indexOf(msg) - 1] || {}).content || '';
                            wrap.appendChild(suggestBtn);
                        }

                        // Show AI metadata badges
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

                        container.appendChild(wrap);
                    });
                    messagesContainer.empty().append(container);
                } else if (response.success) {
                    messagesContainer.html('<p><?php echo esc_js(__('No messages.', 'rapls-ai-chatbot')); ?></p>');
                } else {
                    messagesContainer.html('<p>' + (i18n.error || 'Error occurred.') + '</p>');
                }
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
        }
    });

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
});
</script>
