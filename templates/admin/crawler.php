<?php
/**
 * Crawler settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wpaic_settings', []);
$post_types = get_post_types(['public' => true], 'objects');
?>
<div class="wrap wpaic-admin">
    <h1><?php esc_html_e('AI Chatbot - Site Learning', 'rapls-ai-chatbot'); ?></h1>

    <div class="wpaic-crawler-grid">
        <!-- Status -->
        <div class="wpaic-card">
            <h2><?php esc_html_e('Learning Status', 'rapls-ai-chatbot'); ?></h2>
            <table class="wpaic-status-table">
                <tr>
                    <td><?php esc_html_e('Learning Feature', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr( $status['enabled'] ? 'ok' : 'off' ); ?>">
                            <?php echo $status['enabled'] ? esc_html__('Enabled', 'rapls-ai-chatbot') : esc_html__('Disabled', 'rapls-ai-chatbot'); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></td>
                    <td><strong><?php echo esc_html(number_format($status['indexed_count'])); ?></strong> <?php esc_html_e('pages', 'rapls-ai-chatbot'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Last Crawl', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <?php
                        if (!empty($status['last_crawl'])) {
                            echo esc_html(mysql2date('Y/m/d H:i', $status['last_crawl']));
                        } else {
                            echo '<em>' . esc_html__('Never', 'rapls-ai-chatbot') . '</em>';
                        }
                        ?>
                    </td>
                </tr>
                <?php if (!empty($status['last_results'])): ?>
                <tr>
                    <td><?php esc_html_e('Last Result', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <?php esc_html_e('New:', 'rapls-ai-chatbot'); ?> <?php echo esc_html($status['last_results']['indexed'] ?? 0); ?>,
                        <?php esc_html_e('Updated:', 'rapls-ai-chatbot'); ?> <?php echo esc_html($status['last_results']['updated'] ?? 0); ?>,
                        <?php esc_html_e('Skipped:', 'rapls-ai-chatbot'); ?> <?php echo esc_html($status['last_results']['skipped'] ?? 0); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <div class="wpaic-actions">
                <button type="button" id="wpaic-manual-crawl" class="button button-primary">
                    🔄 <?php esc_html_e('Run Learning Now', 'rapls-ai-chatbot'); ?>
                </button>
                <span id="crawl-status"></span>
            </div>
        </div>

        <!-- Settings -->
        <div class="wpaic-card">
            <h2><?php esc_html_e('Learning Settings', 'rapls-ai-chatbot'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('wpaic_settings_group'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Learning Feature', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[crawler_enabled]" value="1"
                                    <?php checked($settings['crawler_enabled'] ?? false); ?>>
                                <?php esc_html_e('Auto-learn site content', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Target Content', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label style="display: block; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <input type="checkbox"
                                       name="wpaic_settings[crawler_post_types][]"
                                       value="all"
                                       id="crawler-all-types"
                                    <?php checked(in_array('all', $settings['crawler_post_types'] ?? [])); ?>>
                                <strong>✅ <?php esc_html_e('All Public Content (Recommended)', 'rapls-ai-chatbot'); ?></strong>
                                <p class="description" style="margin-left: 24px; margin-top: 4px;">
                                    <?php esc_html_e('Learn all posts, pages, custom post types, and custom fields.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </label>

                            <div id="individual-post-types" style="<?php echo esc_attr( in_array('all', $settings['crawler_post_types'] ?? []) ? 'opacity: 0.5;' : '' ); ?>">
                                <p class="description" style="margin-bottom: 8px;"><?php esc_html_e('Or select individually:', 'rapls-ai-chatbot'); ?></p>
                                <?php foreach ($post_types as $pt): ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox"
                                               name="wpaic_settings[crawler_post_types][]"
                                               value="<?php echo esc_attr($pt->name); ?>"
                                               class="individual-type"
                                            <?php checked(in_array($pt->name, $settings['crawler_post_types'] ?? ['post', 'page']) && !in_array('all', $settings['crawler_post_types'] ?? [])); ?>>
                                        <?php echo esc_html($pt->label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <script>
                            document.getElementById('crawler-all-types').addEventListener('change', function() {
                                var individual = document.getElementById('individual-post-types');
                                var checkboxes = individual.querySelectorAll('.individual-type');
                                if (this.checked) {
                                    individual.style.opacity = '0.5';
                                    checkboxes.forEach(function(cb) { cb.checked = false; });
                                } else {
                                    individual.style.opacity = '1';
                                }
                            });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Learning Interval', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="wpaic_settings[crawler_interval]">
                                <option value="hourly" <?php selected($settings['crawler_interval'] ?? 'daily', 'hourly'); ?>><?php esc_html_e('Hourly', 'rapls-ai-chatbot'); ?></option>
                                <option value="twicedaily" <?php selected($settings['crawler_interval'] ?? 'daily', 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'rapls-ai-chatbot'); ?></option>
                                <option value="daily" <?php selected($settings['crawler_interval'] ?? 'daily', 'daily'); ?>><?php esc_html_e('Daily', 'rapls-ai-chatbot'); ?></option>
                                <option value="weekly" <?php selected($settings['crawler_interval'] ?? 'daily', 'weekly'); ?>><?php esc_html_e('Weekly', 'rapls-ai-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Reference Count', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="wpaic_settings[crawler_max_results]"
                                   value="<?php echo esc_attr($settings['crawler_max_results'] ?? 3); ?>"
                                   min="1" max="10" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum pages to reference when answering', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Enhanced Content Extraction', 'rapls-ai-chatbot'); ?>
                            <?php if (!$is_pro_active): ?><span class="wpaic-pro-badge-small">PRO</span><?php endif; ?>
                        </th>
                        <td>
                            <?php
                            $pro_features = $settings['pro_features'] ?? [];
                            $enhanced_enabled = !empty($pro_features['enhanced_content_extraction']);
                            ?>
                            <label class="<?php echo !$is_pro_active ? 'wpaic-pro-locked' : ''; ?>">
                                <input type="checkbox" id="wpaic_enhanced_extraction"
                                    <?php checked($enhanced_enabled); ?>
                                    <?php echo !$is_pro_active ? 'disabled' : ''; ?>
                                    data-pro-setting="enhanced_content_extraction">
                                <?php esc_html_e('Enable enhanced HTML content extraction', 'rapls-ai-chatbot'); ?>
                                <?php if (!$is_pro_active): ?>
                                <span class="dashicons dashicons-lock" style="color: #999; margin-left: 5px;"></span>
                                <?php endif; ?>
                            </label>
                            <p class="description"><?php esc_html_e('Uses DOMDocument to parse HTML and extract structured content from headings, tables, lists, code blocks, and meta tags.', 'rapls-ai-chatbot'); ?></p>
                            <?php if ($is_pro_active): ?>
                            <div style="margin-top: 10px; padding: 10px 15px; background: #f0f6fc; border-left: 3px solid #667eea; border-radius: 4px; font-size: 12px; color: #50575e;">
                                <strong><?php esc_html_e('Extracted elements:', 'rapls-ai-chatbot'); ?></strong><br>
                                <code>&lt;h1&gt;</code>-<code>&lt;h6&gt;</code> → <?php esc_html_e('Markdown headings', 'rapls-ai-chatbot'); ?>,
                                <code>&lt;table&gt;</code> <code>&lt;tr&gt;</code> <code>&lt;td&gt;</code> <code>&lt;th&gt;</code> → <?php esc_html_e('Structured table data', 'rapls-ai-chatbot'); ?>,
                                <code>&lt;ul&gt;</code> <code>&lt;ol&gt;</code> <code>&lt;li&gt;</code> → <?php esc_html_e('Formatted lists', 'rapls-ai-chatbot'); ?>,
                                <code>&lt;pre&gt;</code> <code>&lt;code&gt;</code> → <?php esc_html_e('Code blocks', 'rapls-ai-chatbot'); ?>,
                                <code>&lt;blockquote&gt;</code> → <?php esc_html_e('Quoted text', 'rapls-ai-chatbot'); ?>,
                                <code>&lt;meta&gt;</code> → <?php esc_html_e('SEO description & keywords', 'rapls-ai-chatbot'); ?>
                            </div>
                            <p class="description" style="margin-top: 8px; color: #d63638;">
                                <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                <?php esc_html_e('After changing this setting, re-run Site Learning to re-index all content.', 'rapls-ai-chatbot'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Hidden settings to maintain -->
                <input type="hidden" name="wpaic_settings[ai_provider]" value="<?php echo esc_attr($settings['ai_provider'] ?? 'openai'); ?>">
                <input type="hidden" name="wpaic_settings[openai_api_key]" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[claude_api_key]" value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[gemini_api_key]" value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[openai_model]" value="<?php echo esc_attr($settings['openai_model'] ?? 'gpt-4o'); ?>">
                <input type="hidden" name="wpaic_settings[claude_model]" value="<?php echo esc_attr($settings['claude_model'] ?? 'claude-sonnet-4-20250514'); ?>">
                <input type="hidden" name="wpaic_settings[gemini_model]" value="<?php echo esc_attr($settings['gemini_model'] ?? 'gemini-2.0-flash-exp'); ?>">
                <input type="hidden" name="wpaic_settings[bot_name]" value="<?php echo esc_attr($settings['bot_name'] ?? 'Assistant'); ?>">
                <input type="hidden" name="wpaic_settings[bot_avatar]" value="<?php echo esc_attr($settings['bot_avatar'] ?? '🤖'); ?>">
                <input type="hidden" name="wpaic_settings[welcome_message]" value="<?php echo esc_attr($settings['welcome_message'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[system_prompt]" value="<?php echo esc_attr($settings['system_prompt'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[max_tokens]" value="<?php echo esc_attr($settings['max_tokens'] ?? 1000); ?>">
                <input type="hidden" name="wpaic_settings[temperature]" value="<?php echo esc_attr($settings['temperature'] ?? 0.7); ?>">
                <input type="hidden" name="wpaic_settings[position]" value="<?php echo esc_attr($settings['position'] ?? 'bottom-right'); ?>">
                <input type="hidden" name="wpaic_settings[primary_color]" value="<?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>">
                <input type="hidden" name="wpaic_settings[show_on_mobile]" value="<?php echo esc_attr($settings['show_on_mobile'] ?? 1); ?>">
                <input type="hidden" name="wpaic_settings[save_history]" value="<?php echo esc_attr($settings['save_history'] ?? 1); ?>">
                <input type="hidden" name="wpaic_settings[retention_days]" value="<?php echo esc_attr($settings['retention_days'] ?? 90); ?>">
                <input type="hidden" name="wpaic_settings[rate_limit]" value="<?php echo esc_attr($settings['rate_limit'] ?? 20); ?>">
                <input type="hidden" name="wpaic_settings[crawler_chunk_size]" value="<?php echo esc_attr($settings['crawler_chunk_size'] ?? 1000); ?>">

                <?php submit_button(__('Save Settings', 'rapls-ai-chatbot')); ?>
            </form>
        </div>

        <!-- Post Type Statistics -->
        <?php if (!empty($post_type_counts)): ?>
        <div class="wpaic-list-stats" style="margin-bottom: 20px;">
            <?php
            $total_indexed = array_sum($post_type_counts);
            $stat_colors = ['stat-highlight', 'stat-info', 'stat-warning', ''];
            $color_i = 0;
            ?>
            <div class="wpaic-list-stat-card">
                <div class="stat-value"><?php echo esc_html(number_format($total_indexed)); ?></div>
                <div class="stat-label"><?php esc_html_e('Total', 'rapls-ai-chatbot'); ?></div>
            </div>
            <?php foreach ($post_type_counts as $type => $count): ?>
                <div class="wpaic-list-stat-card <?php echo esc_attr($stat_colors[$color_i % count($stat_colors)]); ?>">
                    <div class="stat-value"><?php echo esc_html(number_format($count)); ?></div>
                    <div class="stat-label"><?php echo esc_html($type); ?></div>
                </div>
                <?php $color_i++; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Indexed Pages List -->
        <div class="wpaic-card wpaic-card-full">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><?php esc_html_e('Indexed Pages', 'rapls-ai-chatbot'); ?></h2>
                <?php if (!empty($indexed_list)): ?>
                    <button type="button" id="wpaic-delete-all-index" class="button button-secondary">
                        🗑️ <?php esc_html_e('Delete All', 'rapls-ai-chatbot'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php if (!empty($indexed_list)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('title', __('Title', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                            <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('post_type', __('Type', 'rapls-ai-chatbot'), $orderby, $order)); ?></th>
                            <th><?php esc_html_e('URL', 'rapls-ai-chatbot'); ?></th>
                            <th><?php echo wp_kses_post(WPAIC_Admin::sortable_column_header('indexed_at', __('Indexed Date', 'rapls-ai-chatbot'), $orderby, $order, 'DESC')); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="indexed-list-body">
                        <?php foreach ($indexed_list as $item): ?>
                            <tr data-post-id="<?php echo esc_attr($item['post_id']); ?>">
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($item['post_id'])); ?>">
                                        <?php echo esc_html($item['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($item['post_type']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($item['url']); ?>" target="_blank">
                                        <?php echo esc_html(wp_trim_words($item['url'], 5)); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(mysql2date('Y/m/d H:i', $item['indexed_at'])); ?></td>
                                <td>
                                    <button type="button" class="button button-small wpaic-delete-index" data-post-id="<?php echo esc_attr($item['post_id']); ?>" title="<?php esc_attr_e('Delete', 'rapls-ai-chatbot'); ?>">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('No indexed pages yet. Click "Run Learning Now" to start indexing.', 'rapls-ai-chatbot'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Delete single index
    $(document).on('click', '.wpaic-delete-index', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $row = $btn.closest('tr');

        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this index?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wpaic_delete_index',
            nonce: wpaicAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    // Update count
                    var $tbody = $('#indexed-list-body');
                    if ($tbody.find('tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Enhanced extraction toggle (saves to pro_features via AJAX)
    $('#wpaic_enhanced_extraction').on('change', function() {
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.post(ajaxurl, {
            action: 'wpaic_save_pro_setting',
            nonce: wpaicAdmin.nonce,
            key: 'enhanced_content_extraction',
            value: enabled
        });
    });

    // Delete all index
    $('#wpaic-delete-all-index').on('click', function() {
        var $btn = $(this);

        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete all indexed pages?\nThis action cannot be undone.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        $btn.prop('disabled', true).text('<?php echo esc_js(__('Deleting...', 'rapls-ai-chatbot')); ?>');

        $.post(ajaxurl, {
            action: 'wpaic_delete_all_index',
            nonce: wpaicAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
        });
    });
});
</script>
