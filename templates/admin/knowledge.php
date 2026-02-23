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
                                <?php esc_html_e('Dynamic variables (auto-replaced at runtime):', 'rapls-ai-chatbot'); ?><br>
                                <code>{site_name}</code> <?php esc_html_e('Site name', 'rapls-ai-chatbot'); ?>&ensp;
                                <code>{site_url}</code> <?php esc_html_e('Site URL', 'rapls-ai-chatbot'); ?>&ensp;
                                <code>{current_date}</code> <?php esc_html_e("Today's date", 'rapls-ai-chatbot'); ?>&ensp;
                                <code>{current_year}</code> <?php esc_html_e('Current year', 'rapls-ai-chatbot'); ?>&ensp;
                                <code>{admin_email}</code> <?php esc_html_e('Admin email', 'rapls-ai-chatbot'); ?>&ensp;
                                <code>{business_hours}</code> <?php esc_html_e('Business hours', 'rapls-ai-chatbot'); ?>
                            </p>
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
                            <input type="file" id="knowledge-file" name="file" accept=".txt,.csv,.md">
                            <p class="description"><?php esc_html_e('Supported formats: TXT, CSV, MD (max 5MB)', 'rapls-ai-chatbot'); ?></p>
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
            <button type="button" class="button wpaic-export-knowledge" data-format="csv">
                <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                <?php esc_html_e('Export CSV', 'rapls-ai-chatbot'); ?>
            </button>
            <button type="button" class="button wpaic-export-knowledge" data-format="json">
                <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
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
                                <select class="wpaic-priority-select" data-id="<?php echo esc_attr($item['id']); ?>">
                                    <option value="0" <?php selected($item['priority'] ?? 0, 0); ?>>0</option>
                                    <option value="25" <?php selected($item['priority'] ?? 0, 25); ?>>25</option>
                                    <option value="50" <?php selected($item['priority'] ?? 0, 50); ?>>50</option>
                                    <option value="75" <?php selected($item['priority'] ?? 0, 75); ?>>75</option>
                                    <option value="100" <?php selected($item['priority'] ?? 0, 100); ?>>100</option>
                                </select>
                            </td>
                            <td>
                                <label class="wpaic-toggle">
                                    <input type="checkbox" class="wpaic-toggle-active"
                                           data-id="<?php echo esc_attr($item['id']); ?>"
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
                </p>
            </form>
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
                    $status.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">' + (i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>') + '</span>');
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
                    $status.html('<span style="color: green;">' + response.data.message + '</span>');
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: red;">' + (response.data || '<?php echo esc_js(__('Import failed', 'rapls-ai-chatbot')); ?>') + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">' + (i18n.error || '<?php echo esc_js(__('An error occurred', 'rapls-ai-chatbot')); ?>') + '</span>');
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
                $status.html('<span style="color:green;">' + response.data.message + '</span>');
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url(admin_url('admin.php?page=wpaic-knowledge&status=draft')); ?>';
                }, 1500);
            } else {
                $status.html('<span style="color:red;">' + response.data + '</span>');
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
                alert(response.data);
            }
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
                alert(response.data);
                $row.css('opacity', '1');
            }
        });
    });

    // Knowledge export (Pro)
    $('.wpaic-export-knowledge').on('click', function(e) {
        e.preventDefault();
        var format = $(this).data('format');
        var $btn = $(this);
        var $status = $('.wpaic-export-knowledge-status');

        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(__('Exporting...', 'rapls-ai-chatbot')); ?>');

        $.post(wpaicAdmin.ajaxUrl, {
            action: 'wpaic_export_knowledge',
            nonce: wpaicAdmin.nonce,
            format: format,
            category: $('#wpaic-category-filter').val() || ''
        }, function(response) {
            if (response.success) {
                var data = response.data;
                var content, type;

                if (data.format === 'json') {
                    content = JSON.stringify(data.data, null, 2);
                    type = 'application/json';
                } else {
                    var bom = '\ufeff';
                    var csv = data.data.map(function(row) {
                        return row.map(function(cell) {
                            cell = String(cell === null || cell === undefined ? '' : cell);
                            if (cell.indexOf(',') !== -1 || cell.indexOf('"') !== -1 || cell.indexOf('\n') !== -1) {
                                return '"' + cell.replace(/"/g, '""') + '"';
                            }
                            return cell;
                        }).join(',');
                    }).join('\n');
                    content = bom + csv;
                    type = 'text/csv;charset=utf-8';
                }

                var blob = new Blob([content], { type: type });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                $status.html('<span style="color:green;">✓</span>');
            } else {
                $status.html('<span style="color:red;">' + (response.data || 'Error') + '</span>');
            }
        }).fail(function() {
            $status.html('<span style="color:red;">Error</span>');
        }).always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 3000);
        });
    });

});
</script>
