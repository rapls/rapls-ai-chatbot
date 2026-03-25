<?php
/**
 * Knowledge page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$total_pages = ceil($total / 20);
$pro_features = RAPLSAICH_Pro_Features::get_instance();
$faq_count = RAPLSAICH_Knowledge::get_count();
$faq_limit = $pro_features->get_faq_limit();
$faq_limit_reached = !$pro_features->can_add_faq();
$is_pro = $pro_features->is_pro();
?>
<div class="wrap raplsaich-admin">
    <h1><?php esc_html_e('AI Chatbot - Knowledge', 'rapls-ai-chatbot'); ?></h1>

    <p class="description">
        <?php esc_html_e('Upload text or files to teach the chatbot custom knowledge. The learned content will be used as reference when answering user questions.', 'rapls-ai-chatbot'); ?>
    </p>


    <div class="raplsaich-knowledge-grid">
        <!-- Add Text Form -->
        <div class="raplsaich-card">
            <h2><?php esc_html_e('Add Text', 'rapls-ai-chatbot'); ?></h2>
            <form id="raplsaich-add-knowledge-form">
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
                                <span class="raplsaich-pro-menu-badge raplsaich-pro-badge-active" style="font-size: 10px; padding: 1px 5px; vertical-align: middle;">PRO</span>
                                <?php esc_html_e('Dynamic variables (auto-replaced at runtime):', 'rapls-ai-chatbot'); ?>
                            </p>
                            <div class="raplsaich-dynamic-vars" style="display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin-top: 4px; font-size: 12px;">
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
                    <button type="submit" class="button button-primary" <?php disabled($faq_limit_reached); ?>><?php esc_html_e('Add', 'rapls-ai-chatbot'); ?></button>
                    <span id="add-knowledge-status"></span>
                </p>
            </form>
        </div>

        <!-- File Upload -->
        <div class="raplsaich-card">
            <h2><?php esc_html_e('Import from File', 'rapls-ai-chatbot'); ?></h2>
            <form id="raplsaich-import-knowledge-form" enctype="multipart/form-data">
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
                    <button type="submit" class="button button-secondary" <?php disabled($faq_limit_reached); ?>><?php esc_html_e('Import', 'rapls-ai-chatbot'); ?></button>
                    <span id="import-knowledge-status"></span>
                </p>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="raplsaich-list-stats">
        <div class="raplsaich-list-stat-card">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['total'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Total Entries', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card stat-highlight">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['active'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Active', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card stat-warning">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['inactive'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Inactive', 'rapls-ai-chatbot'); ?></div>
        </div>
        <div class="raplsaich-list-stat-card stat-info">
            <div class="stat-value"><?php echo esc_html(number_format($knowledge_stats['categories'])); ?></div>
            <div class="stat-label"><?php esc_html_e('Categories', 'rapls-ai-chatbot'); ?></div>
        </div>
    </div>

    <!-- Knowledge List -->
    <div class="raplsaich-card raplsaich-card-full">
        <h2>
            <?php esc_html_e('Knowledge List', 'rapls-ai-chatbot'); ?>
            <span class="raplsaich-count">(<?php echo esc_html(number_format($total)); ?> <?php esc_html_e('items', 'rapls-ai-chatbot'); ?>)</span>
        </h2>

        <!-- Status filter tabs -->
        <?php
        $base_tab_url = admin_url('admin.php?page=raplsaich-knowledge');
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
                <div class="raplsaich-filter" style="margin-bottom: 0;">
                    <label><?php esc_html_e('Filter by category:', 'rapls-ai-chatbot'); ?></label>
                    <select id="raplsaich-category-filter">
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
            $settings = get_option('raplsaich_settings', []);
            $pro_settings = $settings['pro_features'] ?? [];
            if ($is_pro && !empty($pro_settings['faq_auto_generation_enabled'])):
            ?>
            <button type="button" id="raplsaich-generate-faq" class="button button-secondary" style="margin-left: auto;">
                <span class="dashicons dashicons-lightbulb" style="vertical-align: text-bottom;"></span>
                <?php esc_html_e('Generate FAQ from Gaps', 'rapls-ai-chatbot'); ?>
            </button>
            <span id="raplsaich-generate-faq-status"></span>
            <?php endif; ?>
        </div>

        <?php if ($is_pro && !empty($knowledge_list)): ?>
        <div class="raplsaich-export-actions" style="margin-bottom: 15px;">
            <button type="button" class="button raplsaich-export-knowledge" data-format="csv" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?>
            </button>
            <button type="button" class="button raplsaich-export-knowledge" data-format="json" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export JSON', 'rapls-ai-chatbot'); ?>
            </button>
            <span class="raplsaich-export-knowledge-status"></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($knowledge_list)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('id', 'ID', $orderby, $order, 'DESC')); ?></th>
                        <th><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('title', __('Title', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                        <th style="width: 100px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('category', __('Category', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                        <th style="width: 120px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('priority', __('Priority', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                        <th style="width: 130px;"><?php echo wp_kses_post(RAPLSAICH_Admin::sortable_column_header('created_at', __('Created', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($knowledge_list as $item):
                        $item_status = $item['status'] ?? 'published';
                        $item_type = $item['type'] ?? 'qa';
                    ?>
                        <tr data-id="<?php echo esc_attr($item['id']); ?>" class="<?php echo esc_attr($item_status === 'draft' ? 'raplsaich-draft-row' : ''); ?>">
                            <td><?php echo esc_html($item['id']); ?></td>
                            <td>
                                <strong><?php echo esc_html($item['title']); ?></strong>
                                <?php if ($item_status === 'draft'): ?>
                                    <span class="raplsaich-draft-badge"><?php esc_html_e('Draft', 'rapls-ai-chatbot'); ?></span>
                                <?php endif; ?>
                                <?php if ($item_type === 'template'): ?>
                                    <span class="raplsaich-template-badge" style="background: #e8f5e9; color: #2e7d32; font-size: 11px; padding: 1px 6px; border-radius: 3px; margin-left: 4px;"><?php esc_html_e('Template', 'rapls-ai-chatbot'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['category'])): ?>
                                    <span class="raplsaich-category-badge"><?php echo esc_html($item['category']); ?></span>
                                <?php else: ?>
                                    <em>-</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $current_priority = (int) ($item['priority'] ?? 0); ?>
                                <select class="raplsaich-priority-select" data-id="<?php echo esc_attr($item['id']); ?>" aria-label="<?php esc_attr_e('Priority', 'rapls-ai-chatbot'); ?>">
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
                                <label class="raplsaich-toggle">
                                    <input type="checkbox" class="raplsaich-toggle-active"
                                           data-id="<?php echo esc_attr($item['id']); ?>"
                                           aria-label="<?php esc_attr_e('Toggle active status', 'rapls-ai-chatbot'); ?>"
                                           <?php checked($item['is_active'], 1); ?>>
                                    <span class="raplsaich-toggle-slider"></span>
                                </label>
                            </td>
                            <td><?php echo esc_html(mysql2date('Y/m/d H:i', $item['created_at'])); ?></td>
                            <td>
                                <?php if ($item_status === 'draft' && $is_pro): ?>
                                <button type="button" class="button button-small button-primary raplsaich-approve-draft"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Approve', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="button button-small raplsaich-edit-knowledge"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Edit', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php if ($item_status === 'draft' && $is_pro): ?>
                                <button type="button" class="button button-small button-link-delete raplsaich-reject-draft"
                                        data-id="<?php echo esc_attr($item['id']); ?>">
                                    <?php esc_html_e('Reject', 'rapls-ai-chatbot'); ?>
                                </button>
                                <?php else: ?>
                                <button type="button" class="button button-small button-link-delete raplsaich-delete-knowledge"
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
                            $base_url = admin_url('admin.php?page=raplsaich-knowledge');
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
<div id="raplsaich-edit-modal" class="raplsaich-modal" style="display: none;">
    <div class="raplsaich-modal-content raplsaich-modal-large">
        <div class="raplsaich-modal-header">
            <h2><?php esc_html_e('Edit Knowledge', 'rapls-ai-chatbot'); ?></h2>
            <button type="button" class="raplsaich-modal-close">&times;</button>
        </div>
        <div class="raplsaich-modal-body">
            <form id="raplsaich-edit-knowledge-form">
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
                    <button type="button" class="button raplsaich-modal-close"><?php esc_html_e('Cancel', 'rapls-ai-chatbot'); ?></button>
                    <?php if ($is_pro): ?>
                    <button type="button" class="button" id="raplsaich-show-versions" style="float: right; display: inline-flex; align-items: center; gap: 4px;">
                        <span class="dashicons dashicons-backup"></span>
                        <?php esc_html_e('Version History', 'rapls-ai-chatbot'); ?>
                    </button>
                    <?php endif; ?>
                </p>
            </form>
            <?php if ($is_pro): ?>
            <div id="raplsaich-versions-panel" style="display: none; margin-top: 16px; border-top: 1px solid #ddd; padding-top: 16px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Version History', 'rapls-ai-chatbot'); ?></h3>
                <div id="raplsaich-versions-list"></div>
                <div id="raplsaich-diff-panel" style="display: none; margin-top: 12px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px;">
                    <div style="padding: 8px 12px; background: #fff; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                        <strong id="raplsaich-diff-title"></strong>
                        <button type="button" class="button button-small" id="raplsaich-diff-close">&times;</button>
                    </div>
                    <div id="raplsaich-diff-content" style="padding: 12px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Knowledge styles loaded via wp_enqueue_style('raplsaich-knowledge') -->

<?php
wp_enqueue_script("raplsaich-admin-knowledge", RAPLSAICH_PLUGIN_URL . "assets/js/admin-knowledge.js", ["jquery", "raplsaich-admin"], RAPLSAICH_VERSION, true);
wp_localize_script("raplsaich-admin-knowledge", "raplsaichKB", [
    "adding" => __("Adding...", "rapls-ai-chatbot"),
    "addedOk" => __("Added successfully", "rapls-ai-chatbot"),
    "errorOccurred" => __("An error occurred", "rapls-ai-chatbot"),
    "addBtn" => __("Add", "rapls-ai-chatbot"),
    "selectFile" => __("Please select a file", "rapls-ai-chatbot"),
    "importing" => __("Importing...", "rapls-ai-chatbot"),
    "importFailed" => __("Import failed", "rapls-ai-chatbot"),
    "importBtn" => __("Import", "rapls-ai-chatbot"),
    "pageUrl" => admin_url("admin.php?page=raplsaich-knowledge"),
    "draftUrl" => admin_url("admin.php?page=raplsaich-knowledge&status=draft"),
    "priorityFail" => __("Failed to update priority", "rapls-ai-chatbot"),
    "updating" => __("Updating...", "rapls-ai-chatbot"),
    "updateBtn" => __("Update", "rapls-ai-chatbot"),
    "confirmDelete" => __("Are you sure you want to delete this knowledge?", "rapls-ai-chatbot"),
    "confirmGenerate" => __("Generate FAQ drafts from knowledge gaps? This will use your AI API.", "rapls-ai-chatbot"),
    "generating" => __("Generating...", "rapls-ai-chatbot"),
    "deleteBtn" => __("Delete", "rapls-ai-chatbot"),
    "error" => __("Error", "rapls-ai-chatbot"),
    "confirmReject" => __("Reject and delete this draft?", "rapls-ai-chatbot"),
    "exporting" => __("Exporting...", "rapls-ai-chatbot"),
    "noChanges" => __("No changes.", "rapls-ai-chatbot"),
    "loading" => __("Loading...", "rapls-ai-chatbot"),
    "noHistory" => __("No version history found.", "rapls-ai-chatbot"),
    "author" => __("Author", "rapls-ai-chatbot"),
    "date" => __("Date", "rapls-ai-chatbot"),
    "diff" => __("Diff", "rapls-ai-chatbot"),
    "actions" => __("Actions", "rapls-ai-chatbot"),
    "current" => __("Current", "rapls-ai-chatbot"),
    "category" => __("Category", "rapls-ai-chatbot"),
    "content" => __("Content", "rapls-ai-chatbot"),
    "title" => __("Title", "rapls-ai-chatbot"),
    "restoreVersion" => __("Restore to version", "rapls-ai-chatbot"),
    "restore" => __("Restore", "rapls-ai-chatbot"),
    "restored" => __("Version restored successfully.", "rapls-ai-chatbot"),
    "historyFail" => __("Failed to load version history.", "rapls-ai-chatbot"),
    "restoreFail" => __("Failed to restore version.", "rapls-ai-chatbot"),
    "ajaxError" => __("AJAX error.", "rapls-ai-chatbot"),
    "isPro" => RAPLSAICH_Pro_Features::get_instance()->is_pro(),
]);
?>
