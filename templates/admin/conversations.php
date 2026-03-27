<?php
/**
 * Conversations page template
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables, not true globals

$total_pages = ceil($total / 20);
$is_pro_active = raplsaich_is_pro_active();
?>
<!-- Conversation styles loaded via wp_enqueue_style('raplsaich-conversations') -->
<div class="wrap raplsaich-admin">
    <h1><?php esc_html_e('AI Chatbot - Conversations', 'rapls-ai-chatbot'); ?></h1>

    <!-- Statistics -->
    <div class="raplsaich-list-stats">
        <div class="raplsaich-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['total'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card stat-highlight">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['active'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['closed'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Closed', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card stat-info">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['today'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Today', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php if ($conv_stats['archived'] > 0): ?>
        <div class="raplsaich-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($conv_stats['archived'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Archived', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($conv_stats['handoff'] > 0): ?>
        <div class="raplsaich-list-stat-card" style="border-left: 3px solid #e65100;">
            <div class="stat-value" style="color: #e65100;"><?php echo esc_html(number_format($conv_stats['handoff'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Handoff', 'rapls-ai-chatbot'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search & Filter -->
    <form method="get" class="raplsaich-conversation-filters" style="display: flex; gap: 8px; align-items: center; margin: 12px 0; flex-wrap: wrap;">
        <input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field(wp_unslash(// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page display
$_GET['page'] ?? ''))); ?>">
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . sanitize_text_field(wp_unslash(// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page display
$_GET['page'] ?? '')))); ?>" class="button"><?php esc_html_e('Clear', 'rapls-ai-chatbot'); ?></a>
        <?php endif; ?>
    </form>

    <?php if (!empty($conversations)): ?>
        <!-- Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <button type="button" id="raplsaich-delete-selected" class="button" disabled>
                    <?php esc_html_e('Delete Selected', 'rapls-ai-chatbot'); ?>
                </button>
                <button type="button" id="raplsaich-delete-all" class="button button-link-delete">
                    <?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?>
                </button>
                <button type="button" id="raplsaich-reset-sessions" class="button" style="margin-left: 10px;">
                    <?php esc_html_e('Reset All User Sessions', 'rapls-ai-chatbot'); ?>
                </button>
            </div>
            <div class="alignright" style="display: flex; gap: 10px; align-items: center;">
                <?php if ($is_pro_active): ?>
                <select id="raplsaich-export-format">
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                </select>
                <input type="date" id="raplsaich-export-date-from" placeholder="<?php esc_attr_e('From', 'rapls-ai-chatbot'); ?>">
                <input type="date" id="raplsaich-export-date-to" placeholder="<?php esc_attr_e('To', 'rapls-ai-chatbot'); ?>">
                <button type="button" id="raplsaich-export-conversations" class="button">
                    <?php esc_html_e('Export', 'rapls-ai-chatbot'); ?>
                </button>
                <?php else: ?>
                <span class="description">
                    <span class="dashicons dashicons-star-filled" style="color: #667eea; vertical-align: text-bottom;"></span>
                    <a href="https://raplsworks.com/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Export available with Pro', 'rapls-ai-chatbot'); ?></a>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="raplsaich-select-all" aria-label="<?php esc_attr_e('Select all conversations', 'rapls-ai-chatbot'); ?>"></th>
                    <th style="width: 60px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('id', 'ID', $orderby, $order, 'DESC')); ?></th>
                    <th><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('session_id', __('Session', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 60px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('message_count', __('Msgs', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th><?php esc_html_e('Lead', 'rapls-ai-chatbot'); ?></th>
                    <th><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('page_url', __('Start Page', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 100px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('status', __('Status', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 100px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('handoff_status', __('Handoff', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('created_at', __('Started', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 130px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('updated_at', __('Last Updated', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    // Get lead info for this conversation
                    $lead = RAPLSAICH_Lead::get_by_conversation($conv['id']);
                    ?>
                    <tr data-id="<?php echo esc_attr($conv['id']); ?>">
                        <td><input type="checkbox" class="raplsaich-conv-checkbox" value="<?php echo esc_attr($conv['id']); ?>" aria-label="<?php /* translators: %d: conversation ID */ echo esc_attr(sprintf(__('Select conversation %d', 'rapls-ai-chatbot'), (int) $conv['id'])); ?>"></td>
                        <td><?php echo esc_html($conv['id']); ?></td>
                        <td>
                            <code class="raplsaich-copy-session" title="<?php echo esc_attr($conv['session_id']); ?>" style="cursor: pointer;"><?php echo esc_html(substr($conv['session_id'], 0, 8)); ?>...</code>
                        </td>
                        <td style="text-align: center;">
                            <?php echo esc_html(isset($conv['message_count']) ? number_format((int) $conv['message_count']) : '-'); ?>
                            <?php if (!empty($conv['has_screenshot'])): ?>
                                <span class="raplsaich-screenshot-badge" title="<?php esc_attr_e('Contains screenshot', 'rapls-ai-chatbot'); ?>">&#128247;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead): ?>
                                <div class="raplsaich-lead-info">
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
                                <a href="<?php echo esc_url($conv['page_url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($conv['page_url']); ?>">
                                    <?php echo esc_html(mb_strlen($conv['page_url']) > 50 ? mb_substr($conv['page_url'], 0, 50) . '...' : $conv['page_url']); ?>
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
                                <span class="raplsaich-handoff-badge raplsaich-handoff-badge--pending">
                                    <span class="raplsaich-handoff-dot"></span>
                                    <?php esc_html_e('Pending', 'rapls-ai-chatbot'); ?>
                                </span>
                                <button type="button" class="button button-small raplsaich-reset-handoff" data-id="<?php echo esc_attr($conv['id']); ?>" title="<?php esc_attr_e('Reset handoff status', 'rapls-ai-chatbot'); ?>" style="margin-top: 4px; font-size: 11px;">
                                    <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                </button>
                            <?php elseif ($handoff === 'active'): ?>
                                <span class="raplsaich-handoff-badge raplsaich-handoff-badge--active">
                                    <span class="raplsaich-handoff-dot raplsaich-handoff-dot--active"></span>
                                    <?php esc_html_e('Active', 'rapls-ai-chatbot'); ?>
                                </span>
                                <button type="button" class="button button-small raplsaich-reset-handoff" data-id="<?php echo esc_attr($conv['id']); ?>" title="<?php esc_attr_e('Reset handoff status', 'rapls-ai-chatbot'); ?>" style="margin-top: 4px; font-size: 11px;">
                                    <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                </button>
                            <?php else: ?>
                                <em style="color: #999;">—</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['created_at'])); ?></td>
                        <td><?php echo esc_html(mysql2date('Y/m/d H:i', $conv['updated_at'])); ?></td>
                        <td>
                            <button type="button" class="button button-small raplsaich-view-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>">
                                <?php esc_html_e('Details', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php if ($conv['status'] === 'archived'): ?>
                            <button type="button" class="button button-small raplsaich-unarchive-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    title="<?php esc_attr_e('Restore', 'rapls-ai-chatbot'); ?>">
                                <?php esc_html_e('Restore', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php else: ?>
                            <button type="button" class="button button-small raplsaich-archive-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    title="<?php esc_attr_e('Archive', 'rapls-ai-chatbot'); ?>">
                                <?php esc_html_e('Archive', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="button button-small raplsaich-download-conversation"
                                    data-id="<?php echo esc_attr($conv['id']); ?>"
                                    data-session="<?php echo esc_attr($conv['session_id']); ?>"
                                    title="<?php esc_attr_e('Download', 'rapls-ai-chatbot'); ?>">
                                <span class="dashicons dashicons-download" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                            </button>
                            <button type="button" class="button button-small button-link-delete raplsaich-delete-conversation"
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
    <div id="raplsaich-conversation-modal" class="raplsaich-modal" style="display: none;">
        <div class="raplsaich-modal-content">
            <div class="raplsaich-modal-header">
                <h2><?php esc_html_e('Conversation Details', 'rapls-ai-chatbot'); ?></h2>
                <div class="raplsaich-modal-header-actions">
                    <span id="raplsaich-handoff-status-label" class="raplsaich-handoff-badge" style="display:none;"></span>
                    <button type="button" id="raplsaich-operator-start" class="button button-small" style="display:none;">
                        <?php esc_html_e('Start Operator Chat', 'rapls-ai-chatbot'); ?>
                    </button>
                    <button type="button" id="raplsaich-operator-end" class="button button-small button-link-delete" style="display:none;">
                        <?php esc_html_e('End Operator Chat', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>
                <button type="button" class="raplsaich-modal-close">&times;</button>
            </div>
            <div class="raplsaich-modal-body">
                <div id="raplsaich-conversation-messages"></div>
            </div>
            <div id="raplsaich-operator-reply-form" class="raplsaich-operator-reply" style="display:none;">
                <textarea id="raplsaich-operator-message" rows="2" placeholder="<?php esc_attr_e('Type a reply...', 'rapls-ai-chatbot'); ?>"></textarea>
                <button type="button" id="raplsaich-operator-send" class="button button-primary"><?php esc_html_e('Send', 'rapls-ai-chatbot'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Conversation modal styles loaded via wp_enqueue_style('raplsaich-conversations') -->

<?php
wp_enqueue_script("raplsaich-admin-conversations", RAPLSAICH_PLUGIN_URL . "assets/js/admin-conversations.js", ["jquery", "raplsaich-admin"], RAPLSAICH_VERSION, true);
wp_localize_script("raplsaich-admin-conversations", "raplsaichConv", [
    "ai" => __("AI", "rapls-ai-chatbot"),
    "anErrorOccurred" => __("An error occurred.", "rapls-ai-chatbot"),
    "archive" => __("Archive", "rapls-ai-chatbot"),
    "archived" => __("Archived", "rapls-ai-chatbot"),
    "confirmDeleteAll" => __("Are you sure you want to delete all conversation history?\nThis action cannot be undone.", "rapls-ai-chatbot"),
    "confirmDeleteSelected" => __("Are you sure you want to delete the selected conversations?", "rapls-ai-chatbot"),
    "confirmDeleteOne" => __("Are you sure you want to delete this conversation?", "rapls-ai-chatbot"),
    "confirmResetSessions" => __("Are you sure you want to reset all user sessions?", "rapls-ai-chatbot"),
    "assistant" => __("Assistant", "rapls-ai-chatbot"),
    "cached" => __("cached", "rapls-ai-chatbot"),
    "closed" => __("Closed", "rapls-ai-chatbot"),
    "conversation" => __("Conversation", "rapls-ai-chatbot"),
    "copied" => __("Copied!", "rapls-ai-chatbot"),
    "deleteAll" => __("Delete All", "rapls-ai-chatbot"),
    "deleteSelected" => __("Delete Selected", "rapls-ai-chatbot"),
    "deleting" => __("Deleting...", "rapls-ai-chatbot"),
    "confirmEndOperator" => __("End operator chat and return to AI mode?", "rapls-ai-chatbot"),
    "existingHistory" => __("Existing conversation history will remain in the database.", "rapls-ai-chatbot"),
    "failedToArchive" => __("Failed to archive.", "rapls-ai-chatbot"),
    "failedToDelete" => __("Failed to delete.", "rapls-ai-chatbot"),
    "failedToGenerateSuggestions" => __("Failed to generate suggestions.", "rapls-ai-chatbot"),
    "failedToResetHandoff" => __("Failed to reset handoff.", "rapls-ai-chatbot"),
    "failedToResetSessions" => __("Failed to reset sessions.", "rapls-ai-chatbot"),
    "failedToRestore" => __("Failed to restore.", "rapls-ai-chatbot"),
    "failedToSendMessage" => __("Failed to send message.", "rapls-ai-chatbot"),
    "generating" => __("Generating...", "rapls-ai-chatbot"),
    "negativeFeedback" => __("Negative feedback", "rapls-ai-chatbot"),
    "noMessages" => __("No messages.", "rapls-ai-chatbot"),
    "operator" => __("Operator", "rapls-ai-chatbot"),
    "operatorActive" => __("Operator Active", "rapls-ai-chatbot"),
    "pending" => __("Pending", "rapls-ai-chatbot"),
    "pleaseSelect" => __("Please select conversations to delete.", "rapls-ai-chatbot"),
    "positiveFeedback" => __("Positive feedback", "rapls-ai-chatbot"),
    "processing" => __("Processing...", "rapls-ai-chatbot"),
    "requestFailed" => __("Request failed.", "rapls-ai-chatbot"),
    "reset" => __("Reset", "rapls-ai-chatbot"),
    "resetAllUserSessions" => __("Reset All User Sessions", "rapls-ai-chatbot"),
    "confirmResetHandoff" => __("Reset handoff status for this conversation?", "rapls-ai-chatbot"),
    "restore" => __("Restore", "rapls-ai-chatbot"),
    "screenshot" => __("Screenshot", "rapls-ai-chatbot"),
    "suggestImprovement" => __("Suggest Improvement", "rapls-ai-chatbot"),
    "forceNewSessions" => __("This will force all users to start new conversations on their next visit.", "rapls-ai-chatbot"),
    "tokens" => __("tokens", "rapls-ai-chatbot"),
    "user" => __("User", "rapls-ai-chatbot"),
]);
?>
