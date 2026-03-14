<?php
/**
 * Knowledge page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$total_pages = ceil($total / 20);
$pro_features = WPAIC_Pro_Features::get_instance();
$faq_count = WPAIC_Knowledge::get_count();
$faq_limit = $pro_features->get_faq_limit();
$faq_limit_reached = !$pro_features->can_add_faq();
$is_pro = $pro_features->is_pro();
?>
<div class="wrap wpaic-admin">
    <h1><?php esc_html_e('AI Chatbot - Knowledge', 'rapls-ai-chatbot'); ?></h1>

    <p class="description">
        <?php esc_html_e('Upload text or files to teach the chatbot custom knowledge. The learned content will be used as reference when answering user questions.', 'rapls-ai-chatbot'); ?>
    </p>

    <?php if (!$is_pro): ?>
    <div class="notice notice-info" style="margin: 10px 0 15px;">
        <p>
            <?php
            printf(
                /* translators: 1: current FAQ count, 2: FAQ limit */
                esc_html__('FAQ entries: %1$d / %2$d (Free version limit)', 'rapls-ai-chatbot'),
                absint($faq_count),
                absint($faq_limit)
            );
            ?>
            <?php if ($faq_limit_reached): ?>
                — <strong><?php esc_html_e('Limit reached. Upgrade to Pro for unlimited entries.', 'rapls-ai-chatbot'); ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="wpaic-knowledge-grid">
        <!-- Add Text Form -->
        <div class="wpaic-card">
            <h2><?php esc_html_e('Add Text', 'rapls-ai-chatbot'); ?></h2>
            <form id="wpaic-add-knowledge-form">
                <table class="form-table">
                    <tr>
                        <th><label for="knowledge-title"><?php esc_html_e('Title', 'rapls-ai-chatbot'); ?> <span class="required">*</span></label></th>
                        <td>
                            <?php $prefill_question = isset($_GET['prefill_question']) ? sanitize_text_field(wp_unslash($_GET['prefill_question'])) : ''; ?>
                            <input type="text" id="knowledge-title" name="title" class="regular-text" required
                                value="<?php echo esc_attr($prefill_question); ?>">
                            <p class="description"><?php esc_html_e('A title to identify the knowledge', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="knowledge-content"><?php esc_html_e('Content', 'rapls-ai-chatbot'); ?> <span class="required">*</span></label></th>
                        <td>
                            <textarea id="knowledge-content" name="content" rows="10" class="large-text" required></textarea>
                            <p class="description"><?php esc_html_e('Text content to learn (FAQ, product info, manuals, etc.)', 'rapls-ai-chatbot'); ?></p>
                            <?php if ($is_pro): ?>
                            <p class="description" style="margin-top: 4px;">
                                <span class="wpaic-pro-menu-badge wpaic-pro-badge-active" style="font-size: 10px; padding: 1px 5px; vertical-align: middle;">PRO</span>
                                <?php esc_html_e('Dynamic variables (auto-replaced at runtime):', 'rapls-ai-chatbot'); ?>
                            </p>
                            <div class="wpaic-dynamic-vars" style="display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin-top: 4px; font-size: 12px;">
                                <code>{site_name}</code>       <span class="description"><?php esc_html_e('Site name', 'rapls-ai-chatbot'); ?></span>
                                <code>{site_url}</code>        <span class="description"><?php esc_html_e('Site URL', 'rapls-ai-chatbot'); ?></span>
                                <code>{current_date}</code>    <span class="description"><?php esc_html_e("Today's date", 'rapls-ai-chatbot'); ?></span>
                                <code>{current_year}</code>    <span class="description"><?php esc_html_e('Current year', 'rapls-ai-chatbot'); ?></span>
                                <code>{admin_email}</code>     <span class="description"><?php esc_html_e('Admin email', 'rapls-ai-chatbot'); ?></span>
                                <code>{business_hours}</code>  <span class="description"><?php esc_html_e('Business hours', 'rapls-ai-chatbot'); ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="knowledge-category"><?php esc_html_e('Category', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="text" id="knowledge-category" name="category" class="regular-text" list="category-list">
                            <datalist id="category-list">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <p class="description"><?php esc_html_e('Optional category for organization', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="knowledge-priority"><?php esc_html_e('Priority', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <select id="knowledge-priority" name="priority">
                                <option value="0">0 - <?php esc_html_e('Normal', 'rapls-ai-chatbot'); ?></option>
                                <option value="25">25 - <?php esc_html_e('Low', 'rapls-ai-chatbot'); ?></option>
                                <option value="50">50 - <?php esc_html_e('Medium', 'rapls-ai-chatbot'); ?></option>
                                <option value="75">75 - <?php esc_html_e('High', 'rapls-ai-chatbot'); ?></option>
                                <option value="100">100 - <?php esc_html_e('Highest', 'rapls-ai-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Higher priority content is always used in responses', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <?php if ($is_pro): ?>
                    <tr>
                        <th><label for="knowledge-type"><?php esc_html_e('Type', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <select id="knowledge-type" name="type">
                                <option value="qa"><?php esc_html_e('Q&A / Knowledge', 'rapls-ai-chatbot'); ?></option>
                                <option value="template"><?php esc_html_e('Answer Template', 'rapls-ai-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Templates can be inserted with one click in operator mode.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" <?php disabled($faq_limit_reached && !$is_pro); ?>><?php esc_html_e('Add', 'rapls-ai-chatbot'); ?></button>
                    <span id="add-knowledge-status"></span>
                </p>
            </form>
        </div>

        <!-- File Upload -->
        <div class="wpaic-card">
            <h2><?php esc_html_e('Import from File', 'rapls-ai-chatbot'); ?></h2>
            <form id="wpaic-import-knowledge-form" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th><label for="knowledge-file"><?php esc_html_e('File', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="file" id="knowledge-file" name="file" accept=".txt,.csv,.md,.pdf,.docx">
                            <p class="description"><?php esc_html_e('Supported formats: TXT, CSV, MD, PDF, DOCX (max 5MB)', 'rapls-ai-chatbot'); ?></p>
                            <p class="description"><?php esc_html_e('PDF: Only text-based files are supported. Scanned image PDFs cannot be processed.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description"><?php esc_html_e('CSV files must be UTF-8 encoded. If exporting from Excel, save as "CSV UTF-8 (Comma delimited) (*.csv)". Shift_JIS and CP932 (Japanese Windows) are also supported when available.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="import-category"><?php esc_html_e('Category', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="text" id="import-category" name="category" class="regular-text" list="category-list">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-secondary" <?php disabled($faq_limit_reached && !$is_pro); ?>><?php esc_html_e('Import', 'rapls-ai-chatbot'); ?></button>
                    <span id="import-knowledge-status"></span>
                </p>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="wpaic-list-stats">
        <div class="wpaic-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['total'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Total Entries', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card stat-highlight">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['active'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card stat-warning">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['inactive'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Inactive', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="wpaic-list-stat-card stat-info">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['categories'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Categories', 'rapls-ai-chatbot'); ?></div>
        </div>
    </div>

    <!-- Knowledge List -->
    <div class="wpaic-card wpaic-card-full">
        <h2>
            <?php esc_html_e('Knowledge List', 'rapls-ai-chatbot'); ?>
            <span class="wpaic-count">(<?php echo esc_html(number_format($total)); ?> <?php esc_html_e('items', 'rapls-ai-chatbot'); ?>)</span>
        </h2>

        <!-- Status filter tabs -->
        <?php
        $base_tab_url = admin_url('admin.php?page=wpaic-knowledge');
        if (!empty($category)) {
            $base_tab_url = add_query_arg('category', $category, $base_tab_url);
        }
        $base_tab_url = add_query_arg(['orderby' => $orderby, 'order' => $order], $base_tab_url);
        ?>
        <ul class="subsubsub" style="margin-bottom: 10px;">
            <li>
                <a href="<?php echo esc_url($base_tab_url); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
                    <?php esc_html_e('All', 'rapls-ai-chatbot'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url(add_query_arg('status', 'published', $base_tab_url)); ?>" class="<?php echo esc_attr($status_filter === 'published' ? 'current' : ''); ?>">
                    <?php esc_html_e('Published', 'rapls-ai-chatbot'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url(add_query_arg('status', 'draft', $base_tab_url)); ?>" class="<?php echo esc_attr($status_filter === 'draft' ? 'current' : ''); ?>">
                    <?php esc_html_e('Drafts', 'rapls-ai-chatbot'); ?>
                    <?php if ($draft_count > 0): ?>
                        <span class="count">(<?php echo esc_html($draft_count); ?>)</span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        <div style="clear: both;"></div>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
            <?php if (!empty($categories)): ?>
                <div class="wpaic-filter" style="margin-bottom: 0;">
                    <label><?php esc_html_e('Filter by category:', 'rapls-ai-chatbot'); ?></label>
                    <select id="wpaic-category-filter">
                        <option value=""><?php esc_html_e('All', 'rapls-ai-chatbot'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($category, $cat); ?>>
                                <?php echo esc_html($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php
            $settings = get_option('wpaic_settings', []);
            $pro_settings = $settings['pro_features'] ?? [];
            if ($is_pro && !empty($pro_settings['faq_auto_generation_enabled'])):
            ?>
            <button type="button" id="wpaic-generate-faq" class="button button-secondary" style="margin-left: auto;">
                <span class="dashicons dashicons-lightbulb" style="vertical-align: text-bottom;"></span>
                <?php esc_html_e('Generate FAQ from Gaps', 'rapls-ai-chatbot'); ?>
            </button>
            <span id="wpaic-generate-faq-status"></span>
            <?php endif; ?>
        </div>

        <?php if ($is_pro && !empty($knowledge_list)): ?>
        <div class="wpaic-export-actions" style="margin-bottom: 15px;">
            <button type="button" class="button wpaic-export-knowledge" data-format="csv" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?>
            </button>
            <button type="button" class="button wpaic-export-knowledge" data-format="json" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export JSON', 'rapls-ai-chatbot'); ?>
            </button>
            <span class="wpaic-export-knowledge-status"></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($knowledge_list)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('id', 'ID', $orderby, $order, 'DESC')); ?></th>
                        <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('title', __('Title', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                        <th style="width: 100px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('category', __('Category', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                        <th style="width: 120px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('priority', __('Priority', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 130px;"><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('created_at', __('Created', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($knowledge_list as $item):
                        $item_status = $item['status'] ?? 'published';
                        $item_type = $item['type'] ?? 'qa';
                    ?>
                        <tr data-id="<?php echo esc_attr($item['id']); ?>" class="<?php echo esc_attr($item_status === 'draft' ? 'wpaic-draft-row' : ''); ?>">
                            <td><?php echo esc_html($item['id']); ?></td>
                            <td>
                                <strong><?php echo esc_html($item['title']); ?></strong>
                                <?php if ($item_status === 'draft'): ?>
                                    <span class="wpaic-draft-badge"><?php esc_html_e('Draft', 'rapls-ai-chatbot'); ?></span>
                                <?php endif; ?>
                                <?php if ($item_type === 'template'): ?>
                                    <span class="wpaic-template-badge" style="background: #e8f5e9; color: #2e7d32; font-size: 11px; padding: 1px 6px; border-radius: 3px; margin-left: 4px;"><?php esc_html_e('Template', 'rapls-ai-chatbot'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['category'])): ?>
                                    <span class="wpaic-category-badge"><?php echo esc_html($item['category']); ?></span>
                                <?php else: ?>
                                    <em>-</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $current_priority = (int) ($item['priority'] ?? 0); ?>
                                <select class="wpaic-priority-select" data-id="<?php echo esc_attr($item['id']); ?>" aria-label="<?php esc_attr_e('Priority', 'rapls-ai-chatbot'); ?>">
                                    <?php if (!in_array($current_priority, [0, 25, 50, 75, 100], true)): ?>
                                    <option value="<?php echo esc_attr($current_priority); ?>" selected><?php echo esc_html($current_priority); ?></option>
                                    <?php endif; ?>
                                    <option value="0" <?php selected($current_priority, 0); ?>>0</option>
                                    <option value="25" <?php selected($current_priority, 25); ?>>25</option>
                                    <option value="50" <?php selected($current_priority, 50); ?>>50</option>
                                    <option value="75" <?php selected($current_priority, 75); ?>>75</option>
                                    <option value="100" <?php selected($current_priority, 100); ?>>100</option>
                                </select>
                            </td>
                            <td>
                                <label class="wpaic-toggle">
                                    <input type="checkbox" class="wpaic-toggle-active"
                                           data-id="<?php echo esc_attr($item['id']); ?>"
                                           aria-label="<?php esc_attr_e('Toggle active status', 'rapls-ai-chatbot'); ?>"
                                           <?php checked($item['is_active'], 1); ?>>
                                    <span class="wpaic-toggle-slider"></span>
                                </label>
                            </td>
                            <td><?php echo esc_html(mysql2date('Y/m/d H:i', $item['created_at'])); ?></td>
                            <td>
                                <?php if ($item_status === 'draft' && $is_pro): ?>
                                <button type="button" class="button button-small button-primary wpaic-approve-draft"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Approve', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="button button-small wpaic-edit-knowledge"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Edit', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php if ($item_status === 'draft' && $is_pro): ?>
                                <button type="button" class="button button-small button-link-delete wpaic-reject-draft"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Reject', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php else: ?>
                                <button type="button" class="button button-small button-link-delete wpaic-delete-knowledge"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Delete', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php endif; ?>
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
                            $base_url = admin_url('admin.php?page=wpaic-knowledge');
                            if (!empty($category)) {
                                $base_url = add_query_arg('category', $category, $base_url);
                            }
                            if (!empty($status_filter)) {
                                $base_url = add_query_arg('status', $status_filter, $base_url);
                            }
                            $base_url = add_query_arg([
                                'orderby' => $orderby,
                                'order'   => $order,
                            ], $base_url);
                            echo wp_kses_post(paginate_links([
                                'base'      => add_query_arg('paged', '%#%', $base_url),
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

        <?php else: ?>
            <p><?php esc_html_e('No knowledge data. Add text using the form above.', 'rapls-ai-chatbot'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="wpaic-edit-modal" class="wpaic-modal" style="display: none;">
    <div class="wpaic-modal-content wpaic-modal-large">
        <div class="wpaic-modal-header">
            <h2><?php esc_html_e('Edit Knowledge', 'rapls-ai-chatbot'); ?></h2>
            <button type="button" class="wpaic-modal-close">&times;</button>
        </div>
        <div class="wpaic-modal-body">
            <form id="wpaic-edit-knowledge-form">
                <input type="hidden" id="edit-knowledge-id" name="id">
                <table class="form-table">
                    <tr>
                        <th><label for="edit-knowledge-title"><?php esc_html_e('Title', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="text" id="edit-knowledge-title" name="title" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="edit-knowledge-content"><?php esc_html_e('Content', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <textarea id="edit-knowledge-content" name="content" rows="15" class="large-text" required></textarea>
                        </td>
                    </tr>
                    <?php if ($is_pro): ?>
                    <tr>
                        <th><label for="edit-knowledge-type"><?php esc_html_e('Type', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <select id="edit-knowledge-type" name="type">
                                <option value="qa"><?php esc_html_e('Q&A', 'rapls-ai-chatbot'); ?></option>
                                <option value="template"><?php esc_html_e('Template', 'rapls-ai-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="edit-knowledge-category"><?php esc_html_e('Category', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="text" id="edit-knowledge-category" name="category" class="regular-text" list="category-list">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="edit-knowledge-priority"><?php esc_html_e('Priority', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <select id="edit-knowledge-priority" name="priority">
                                <option value="0">0 - <?php esc_html_e('Normal', 'rapls-ai-chatbot'); ?></option>
                                <option value="25">25 - <?php esc_html_e('Low', 'rapls-ai-chatbot'); ?></option>
                                <option value="50">50 - <?php esc_html_e('Medium', 'rapls-ai-chatbot'); ?></option>
                                <option value="75">75 - <?php esc_html_e('High', 'rapls-ai-chatbot'); ?></option>
                                <option value="100">100 - <?php esc_html_e('Highest', 'rapls-ai-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Higher priority content is always used in responses', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Update', 'rapls-ai-chatbot'); ?></button>
                    <button type="button" class="button wpaic-modal-close"><?php esc_html_e('Cancel', 'rapls-ai-chatbot'); ?></button>
                    <?php if ($is_pro): ?>
                    <button type="button" class="button" id="wpaic-show-versions" style="float: right; display: inline-flex; align-items: center; gap: 4px;">
                        <span class="dashicons dashicons-backup"></span>
                        <?php esc_html_e('Version History', 'rapls-ai-chatbot'); ?>
                    </button>
                    <?php endif; ?>
                </p>
            </form>
            <?php if ($is_pro): ?>
            <div id="wpaic-versions-panel" style="display: none; margin-top: 16px; border-top: 1px solid #ddd; padding-top: 16px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Version History', 'rapls-ai-chatbot'); ?></h3>
                <div id="wpaic-versions-list"></div>
                <div id="wpaic-diff-panel" style="display: none; margin-top: 12px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px;">
                    <div style="padding: 8px 12px; background: #fff; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                        <strong id="wpaic-diff-title"></strong>
                        <button type="button" class="button button-small" id="wpaic-diff-close">&times;</button>
                    </div>
                    <div id="wpaic-diff-content" style="padding: 12px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.wpaic-knowledge-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.wpaic-card-full {
    grid-column: 1 / -1;
}

.wpaic-count {
    font-weight: normal;
    font-size: 14px;
    color: #666;
}

.wpaic-filter {
    margin-bottom: 15px;
}

.wpaic-filter label {
    margin-right: 8px;
}

.wpaic-category-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #e0e0e0;
    border-radius: 3px;
    font-size: 12px;
}

.wpaic-priority-select {
    width: 70px;
    padding: 3px 5px;
    font-size: 12px;
}

.wpaic-priority-select option[value="100"],
.wpaic-priority-select option[value="75"] {
    background: #fff3cd;
    font-weight: bold;
}

.wpaic-priority-select option[value="50"],
.wpaic-priority-select option[value="25"] {
    background: #e8f4fd;
}

.wpaic-toggle {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
}

.wpaic-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.wpaic-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 22px;
}

.wpaic-toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.wpaic-toggle input:checked + .wpaic-toggle-slider {
    background-color: #2271b1;
}

.wpaic-toggle input:checked + .wpaic-toggle-slider:before {
    transform: translateX(18px);
}

.wpaic-modal-large .wpaic-modal-content {
    max-width: 800px;
}

.required {
    color: #d63638;
}

.wpaic-draft-row {
    background: #fff8e5 !important;
}

.wpaic-draft-badge {
    display: inline-block;
    padding: 1px 6px;
    background: #dba617;
    color: #fff;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 5px;
    vertical-align: middle;
}

@media (max-width: 782px) {
    .wpaic-knowledge-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var i18n = wpaicAdmin.i18n || {};

    // Add text
    $('#wpaic-add-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $status = $('#add-knowledge-status');

        $button.prop('disabled', true).text(i18n.processing || '<?php echo esc_js(__('Adding...', 'rapls-ai-chatbot')); ?>');
        $status.text('');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_add_knowledge',
                nonce: wpaicAdmin.nonce,
                title: $('#knowledge-title').val(),
                content: $('#knowledge-content').val(),
                category: $('#knowledge-category').val(),
                priority: $('#knowledge-priority').val() || 0,
                type: $('#knowledge-type').val() || 'qa'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;"><?php echo esc_js(__('Added successfully', 'rapls-ai-chatbot')); ?></span>');
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: red;"></span>').find('span').text(response.data);
                }
            },
            error: function() {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Add', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // File import
    $('#wpaic-import-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $status = $('#import-knowledge-status');

        var fileInput = $('#knowledge-file')[0];
        if (!fileInput.files.length) {
            $status.html('<span style="color: red;"><?php echo esc_js(__('Please select a file', 'rapls-ai-chatbot')); ?></span>');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'wpaic_import_knowledge');
        formData.append('nonce', wpaicAdmin.nonce);
        formData.append('file', fileInput.files[0]);
        formData.append('category', $('#import-category').val());

        $button.prop('disabled', true).text(i18n.importing || '<?php echo esc_js(__('Importing...', 'rapls-ai-chatbot')); ?>');
        $status.text('');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;"></span>').find('span').text(response.data.message);
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: red;"></span>').find('span').text(response.data || '<?php echo esc_js(__('Import failed', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;"></span>').find('span').text(i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Import', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Category filter (preserve sort params)
    $('#wpaic-category-filter').on('change', function() {
        var category = $(this).val();
        var url = new URL('<?php echo esc_url(admin_url('admin.php?page=wpaic-knowledge')); ?>', window.location.origin);
        if (category) {
            url.searchParams.set('category', category);
        }
        var currentParams = new URLSearchParams(window.location.search);
        if (currentParams.has('orderby')) {
            url.searchParams.set('orderby', currentParams.get('orderby'));
        }
        if (currentParams.has('order')) {
            url.searchParams.set('order', currentParams.get('order'));
        }
        window.location.href = url.toString();
    });

    // Toggle active/inactive
    $('.wpaic-toggle-active').on('change', function() {
        var id = $(this).data('id');
        var isActive = $(this).prop('checked') ? 1 : 0;

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_toggle_knowledge',
                nonce: wpaicAdmin.nonce,
                id: id,
                is_active: isActive
            }
        });
    });

    // Change priority in list
    $('.wpaic-priority-select').on('change', function() {
        var $select = $(this);
        var id = $select.data('id');
        var priority = $select.val();

        $select.prop('disabled', true);

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_update_priority',
                nonce: wpaicAdmin.nonce,
                id: id,
                priority: priority
            },
            success: function(response) {
                if (!response.success) {
                    alert(response.data || '<?php echo esc_js(__('Failed to update priority', 'rapls-ai-chatbot')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });

    // Open edit modal
    $('.wpaic-edit-knowledge').on('click', function() {
        var id = $(this).data('id');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_get_knowledge',
                nonce: wpaicAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#edit-knowledge-id').val(data.id);
                    $('#edit-knowledge-title').val(data.title);
                    $('#edit-knowledge-content').val(data.content);
                    $('#edit-knowledge-type').val(data.type || 'qa');
                    $('#edit-knowledge-category').val(data.category || '');
                    $('#edit-knowledge-priority').val(data.priority || 0);
                    $('#wpaic-edit-modal').show();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Edit form submit
    $('#wpaic-edit-knowledge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');

        $button.prop('disabled', true).text(i18n.processing || '<?php echo esc_js(__('Updating...', 'rapls-ai-chatbot')); ?>');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_update_knowledge',
                nonce: wpaicAdmin.nonce,
                id: $('#edit-knowledge-id').val(),
                title: $('#edit-knowledge-title').val(),
                content: $('#edit-knowledge-content').val(),
                type: $('#edit-knowledge-type').val(),
                category: $('#edit-knowledge-category').val(),
                priority: $('#edit-knowledge-priority').val() || 0
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Update', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Delete
    $('.wpaic-delete-knowledge').on('click', function() {
        if (!confirm(i18n.confirmDelete || '<?php echo esc_js(__('Are you sure you want to delete this knowledge?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $row.css('opacity', '0.5');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_delete_knowledge',
                nonce: wpaicAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                alert(i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>');
                $row.css('opacity', '1');
            }
        });
    });

    // Close modal
    $('.wpaic-modal-close, .wpaic-modal').on('click', function(e) {
        if (e.target === this) {
            $('#wpaic-edit-modal').hide();
        }
    });

    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#wpaic-edit-modal').hide();
        }
    });

    // Generate FAQ from gaps (Pro)
    $('#wpaic-generate-faq').on('click', function() {
        var $btn = $(this);
        var $status = $('#wpaic-generate-faq-status');

        if (!confirm('<?php echo esc_js(__('Generate FAQ drafts from knowledge gaps? This will use your AI API.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(__('Generating...', 'rapls-ai-chatbot')); ?>');

        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_generate_faq',
            nonce: wpaicAdmin.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color:green;"></span>').find('span').text(response.data.message);
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url(admin_url('admin.php?page=wpaic-knowledge&status=draft')); ?>';
                }, 1500);
            } else {
                $status.html('<span style="color:red;"></span>').find('span').text(response.data);
            }
        }).fail(function() {
            $status.html('<span style="color:red;"><?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?></span>');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Approve draft (Pro)
    $(document).on('click', '.wpaic-approve-draft', function() {
        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_approve_faq_draft',
            nonce: wpaicAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $row.find('.wpaic-draft-badge').remove();
                $row.removeClass('wpaic-draft-row');
                $row.find('.wpaic-approve-draft, .wpaic-reject-draft').remove();
                $row.find('td:last').prepend('<button type="button" class="button button-small button-link-delete wpaic-delete-knowledge" data-id="' + id + '"><?php echo esc_js(__('Delete', 'rapls-ai-chatbot')); ?></button> ');
            } else {
                alert(response.data || '<?php echo esc_js(__('Error', 'rapls-ai-chatbot')); ?>');
            }
        }).fail(function(xhr) {
            console.error('wpaic_approve_faq_draft failed:', xhr.status, xhr.responseText);
            alert('AJAX error: ' + xhr.status);
        });
    });

    // Reject draft (Pro)
    $(document).on('click', '.wpaic-reject-draft', function() {
        if (!confirm('<?php echo esc_js(__('Reject and delete this draft?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $row.css('opacity', '0.5');

        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_reject_faq_draft',
            nonce: wpaicAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data || '<?php echo esc_js(__('Error', 'rapls-ai-chatbot')); ?>');
                $row.css('opacity', '1');
            }
        }).fail(function(xhr) {
            console.error('wpaic_reject_faq_draft failed:', xhr.status, xhr.responseText);
            alert('AJAX error: ' + xhr.status);
            $row.css('opacity', '1');
        });
    });

    // Knowledge export (Pro) — streaming file download (avoids JSON memory issues)
    $('.wpaic-export-knowledge').on('click', function(e) {
        e.preventDefault();
        var format = $(this).data('format');
        var $btn = $(this);
        var $status = $('.wpaic-export-knowledge-status');
        var category = $('#wpaic-category-filter').val() || '';

        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(__('Exporting...', 'rapls-ai-chatbot')); ?>');

        // Direct download via GET — server streams the file (no JSON payload in memory)
        var url = wpaicAdmin.ajaxUrl
            + '?action=wpaic_download_knowledge'
            + '&nonce=' + encodeURIComponent(wpaicAdmin.nonce)
            + '&format=' + encodeURIComponent(format)
            + '&category=' + encodeURIComponent(category);
        window.location.href = url;

        // Re-enable after delay (browser handles the download)
        setTimeout(function() {
            $btn.prop('disabled', false);
            $status.html('<span style="color:green;">✓</span>');
            setTimeout(function() { $status.text(''); }, 3000);
        }, 1500);
    });

    // ── Version History (Pro) ──────────────────────────────────────────
    <?php if ($is_pro): ?>

    // Line-based diff (WordPress revision style: red=removed, green=added)
    function wpaicLineDiff(oldText, newText) {
        if (oldText === newText) {
            return '<span style="color:#50575e;"><?php echo esc_js(__('No changes.', 'rapls-ai-chatbot')); ?></span>';
        }
        var esc = function(s) { return $('<span>').text(s).html(); };
        var oldLines = oldText.split('\n');
        var newLines = newText.split('\n');

        // LCS (Longest Common Subsequence) to find matching lines
        var m = oldLines.length, n = newLines.length;
        var dp = [];
        for (var i = 0; i <= m; i++) {
            dp[i] = [];
            for (var j = 0; j <= n; j++) {
                if (i === 0 || j === 0) { dp[i][j] = 0; }
                else if (oldLines[i - 1] === newLines[j - 1]) { dp[i][j] = dp[i - 1][j - 1] + 1; }
                else { dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]); }
            }
        }

        // Backtrack to build diff operations
        var ops = [];
        i = m; var j = n;
        while (i > 0 || j > 0) {
            if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
                ops.push({ type: 'equal', line: oldLines[i - 1] });
                i--; j--;
            } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
                ops.push({ type: 'add', line: newLines[j - 1] });
                j--;
            } else {
                ops.push({ type: 'del', line: oldLines[i - 1] });
                i--;
            }
        }
        ops.reverse();

        // Context diff: show only changed lines with up to 3 lines of surrounding context
        var ctx = 3;
        var show = [];
        for (i = 0; i < ops.length; i++) { show[i] = (ops[i].type !== 'equal'); }
        for (i = 0; i < ops.length; i++) {
            if (ops[i].type !== 'equal') { continue; }
            for (var k = Math.max(0, i - ctx); k <= Math.min(ops.length - 1, i + ctx); k++) {
                if (ops[k].type !== 'equal') { show[i] = true; break; }
            }
        }

        var html = '';
        var inGap = false;
        for (i = 0; i < ops.length; i++) {
            if (!show[i]) {
                if (!inGap) {
                    html += '<div style="padding:1px 6px;color:#999;text-align:center;font-size:12px;">⋯</div>';
                    inGap = true;
                }
                continue;
            }
            inGap = false;
            var o = ops[i];
            if (o.type === 'equal') {
                html += '<div style="padding:1px 6px;color:#50575e;">&nbsp; ' + esc(o.line) + '</div>';
            } else if (o.type === 'del') {
                html += '<div style="padding:1px 6px;background:#fcdddd;"><del style="text-decoration:none;">− ' + esc(o.line) + '</del></div>';
            } else {
                html += '<div style="padding:1px 6px;background:#d4fcd5;"><ins style="text-decoration:none;">+ ' + esc(o.line) + '</ins></div>';
            }
        }
        return html;
    }

    var _versionCache = [];

    $('#wpaic-show-versions').on('click', function() {
        var $panel = $('#wpaic-versions-panel');
        var $list = $('#wpaic-versions-list');
        var knowledgeId = $('#edit-knowledge-id').val();

        if ($panel.is(':visible')) {
            $panel.slideUp(200);
            return;
        }

        $list.html('<p><span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span><?php echo esc_js(__('Loading...', 'rapls-ai-chatbot')); ?></p>');
        $('#wpaic-diff-panel').hide();
        $panel.slideDown(200);

        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_get_knowledge_versions',
            nonce: wpaicAdmin.nonce,
            knowledge_id: knowledgeId
        }, function(r) {
            if (!r.success || !r.data.versions || r.data.versions.length === 0) {
                $list.html('<p class="description"><?php echo esc_js(__('No version history found.', 'rapls-ai-chatbot')); ?></p>');
                return;
            }
            _versionCache = r.data.versions;
            var html = '<table class="widefat striped" style="max-width: 100%;"><thead><tr>'
                + '<th style="width:50px;">#</th>'
                + '<th style="width:120px;"><?php echo esc_js(__('Author', 'rapls-ai-chatbot')); ?></th>'
                + '<th style="width:150px;"><?php echo esc_js(__('Date', 'rapls-ai-chatbot')); ?></th>'
                + '<th style="width:180px;"><?php echo esc_js(__('Actions', 'rapls-ai-chatbot')); ?></th>'
                + '</tr></thead><tbody>';

            _versionCache.forEach(function(v, idx) {
                html += '<tr>'
                    + '<td>v' + v.version_number + '</td>'
                    + '<td>' + $('<span>').text(v.created_by_name).html() + '</td>'
                    + '<td>' + $('<span>').text(v.created_at).html() + '</td>'
                    + '<td>'
                    + '<button type="button" class="button button-small wpaic-preview-version" data-idx="' + idx + '"><?php echo esc_js(__('Diff', 'rapls-ai-chatbot')); ?></button> '
                    + '<button type="button" class="button button-small wpaic-restore-version" data-id="' + v.id + '" data-version="' + v.version_number + '">↩ <?php echo esc_js(__('Restore', 'rapls-ai-chatbot')); ?></button>'
                    + '</td></tr>';
            });
            html += '</tbody></table>';
            $list.html(html);
        }).fail(function() {
            $list.html('<p class="description" style="color:#d63638;"><?php echo esc_js(__('Failed to load version history.', 'rapls-ai-chatbot')); ?></p>');
        });
    });

    // Diff preview — compare version content with current form content
    $(document).on('click', '.wpaic-preview-version', function() {
        var idx = $(this).data('idx');
        var v = _versionCache[idx];
        if (!v) { return; }

        var currentContent = $('#edit-knowledge-content').val();
        var oldContent = v.content;

        $('#wpaic-diff-title').text('v' + v.version_number + ' → <?php echo esc_js(__('Current', 'rapls-ai-chatbot')); ?>');

        var diffHtml = '';
        // Title diff
        var currentTitle = $('#edit-knowledge-title').val();
        if (v.title !== currentTitle) {
            diffHtml += '<div style="margin-bottom:8px;"><strong><?php echo esc_js(__('Title', 'rapls-ai-chatbot')); ?>:</strong><br>' + wpaicLineDiff(v.title, currentTitle) + '</div>';
        }
        // Content diff
        diffHtml += '<div><strong><?php echo esc_js(__('Content', 'rapls-ai-chatbot')); ?>:</strong><br>' + wpaicLineDiff(oldContent, currentContent) + '</div>';
        // Category diff
        var currentCat = $('#edit-knowledge-category').val();
        if (v.category !== currentCat) {
            diffHtml += '<div style="margin-top:8px;"><strong><?php echo esc_js(__('Category', 'rapls-ai-chatbot')); ?>:</strong> ' + wpaicLineDiff(v.category || '(none)', currentCat || '(none)') + '</div>';
        }

        $('#wpaic-diff-content').html(diffHtml);
        $('#wpaic-diff-panel').show();
        // Auto-scroll modal body to show diff panel
        var $modalBody = $('#wpaic-diff-panel').closest('.wpaic-modal-body');
        if ($modalBody.length) {
            $modalBody.animate({ scrollTop: $modalBody[0].scrollHeight }, 300);
        }
    });

    $('#wpaic-diff-close').on('click', function() {
        $('#wpaic-diff-panel').slideUp(200);
    });

    // Restore version — save to DB
    $(document).on('click', '.wpaic-restore-version', function() {
        var $btn = $(this);
        var versionNum = $btn.data('version');
        if (!confirm('<?php echo esc_js(__('Restore to version', 'rapls-ai-chatbot')); ?> v' + versionNum + '?')) {
            return;
        }
        $btn.prop('disabled', true);
        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_restore_knowledge_version',
            nonce: wpaicAdmin.nonce,
            version_id: $btn.data('id')
        }, function(r) {
            if (r.success) {
                // Reload the edit form with restored data
                var knowledgeId = $('#edit-knowledge-id').val();
                $.post(wpaicAdmin.ajaxUrl, {
                    action: 'wpaic_get_knowledge',
                    nonce: wpaicAdmin.nonce,
                    id: knowledgeId
                }, function(kr) {
                    if (kr.success) {
                        $('#edit-knowledge-title').val(kr.data.title);
                        $('#edit-knowledge-content').val(kr.data.content);
                        $('#edit-knowledge-category').val(kr.data.category || '');
                        $('#edit-knowledge-priority').val(kr.data.priority || 0);
                    }
                });
                $('#wpaic-versions-panel').slideUp(200);
                alert('<?php echo esc_js(__('Version restored successfully.', 'rapls-ai-chatbot')); ?>');
                // Reload page to reflect changes in the table
                location.reload();
            } else {
                alert(r.data || '<?php echo esc_js(__('Failed to restore version.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('AJAX error.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Hide versions panel when modal closes
    $('.wpaic-modal-close').on('click', function() {
        $('#wpaic-versions-panel').hide();
    });
    <?php endif; ?>

});
</script>
