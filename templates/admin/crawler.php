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
        <div class="wpaic-card wpaic-card-status">
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
                <?php if (class_exists('WooCommerce')): ?>
                <tr>
                    <td>WooCommerce</td>
                    <td>
                        <span class="status-badge status-ok"><?php esc_html_e('Detected', 'rapls-ai-chatbot'); ?></span>
                        <?php
                        $wc_product_count = wp_count_posts('product');
                        $wc_published = isset($wc_product_count->publish) ? (int) $wc_product_count->publish : 0;
                        if ($wc_published > 0) {
                            /* translators: %s: number of products */
                            printf(' (' . esc_html__('%s products', 'rapls-ai-chatbot') . ')', esc_html(number_format($wc_published)));
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
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

        <!-- Embedding Status -->
        <?php
        $embedding_enabled = !empty($settings['embedding_enabled']);
        $emb_generator = new WPAIC_Embedding_Generator($settings);
        $emb_configured = $emb_generator->is_configured();
        $index_stats = WPAIC_Content_Index::get_embedding_stats();
        $knowledge_stats = WPAIC_Knowledge::get_embedding_stats();
        $emb_total = $index_stats['total_chunks'] + $knowledge_stats['total'];
        $emb_done = $index_stats['embedded_chunks'] + $knowledge_stats['embedded'];
        $emb_pct = $emb_total > 0 ? round(($emb_done / $emb_total) * 100) : 0;
        ?>
        <div class="wpaic-card wpaic-card-embedding">
            <h2><?php esc_html_e('Vector Embedding', 'rapls-ai-chatbot'); ?></h2>
            <table class="wpaic-status-table">
                <tr>
                    <td><?php esc_html_e('Embedding', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <?php if ($embedding_enabled && $emb_configured) : ?>
                            <span class="status-badge status-ok"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                        <?php elseif ($embedding_enabled) : ?>
                            <span class="status-badge status-off"><?php esc_html_e('Not Configured', 'rapls-ai-chatbot'); ?></span>
                        <?php else : ?>
                            <span class="status-badge status-off"><?php esc_html_e('Disabled', 'rapls-ai-chatbot'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($embedding_enabled && $emb_configured) : ?>
                <tr>
                    <td><?php esc_html_e('Provider', 'rapls-ai-chatbot'); ?></td>
                    <td><?php echo esc_html(ucfirst($emb_generator->get_provider()) . ' / ' . $emb_generator->get_model()); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Embedded Chunks', 'rapls-ai-chatbot'); ?></td>
                    <td>
                        <strong><?php echo esc_html(number_format($emb_done)); ?></strong> / <?php echo esc_html(number_format($emb_total)); ?>
                        (<?php echo esc_html($emb_pct); ?>%)
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top: 8px;">
                        <div style="background: #e0e0e0; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: #667eea; height: 100%; width: <?php echo esc_attr($emb_pct); ?>%; transition: width 0.3s;"></div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if ($embedding_enabled && $emb_configured) : ?>
            <div class="wpaic-actions" style="margin-top: 12px;">
                <?php if ($emb_done < $emb_total) : ?>
                <button type="button" id="wpaic-generate-embeddings" class="button button-primary">
                    <?php esc_html_e('Generate Embeddings', 'rapls-ai-chatbot'); ?>
                </button>
                <?php endif; ?>
                <?php if ($emb_done > 0) : ?>
                <button type="button" id="wpaic-clear-embeddings" class="button button-secondary">
                    🗑️ <?php esc_html_e('Clear All Embeddings', 'rapls-ai-chatbot'); ?>
                </button>
                <?php endif; ?>
                <span id="embedding-status"></span>
            </div>
            <?php elseif ($embedding_enabled && !$emb_configured) : ?>
            <p class="description" style="margin-top: 8px; color: #d63638;">
                <?php esc_html_e('An OpenAI or Gemini API key is required for vector embedding. Claude and OpenRouter do not provide embedding APIs.', 'rapls-ai-chatbot'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpaic-settings')); ?>"><?php esc_html_e('Go to Settings', 'rapls-ai-chatbot'); ?></a>
            </p>
            <?php elseif (!$embedding_enabled) : ?>
            <p class="description" style="margin-top: 8px;">
                <?php esc_html_e('Enable vector embedding in Settings > AI Settings to improve search accuracy.', 'rapls-ai-chatbot'); ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- Settings -->
        <div class="wpaic-card wpaic-card-settings">
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
                                <option value="monthly" <?php selected($settings['crawler_interval'] ?? 'daily', 'monthly'); ?>><?php esc_html_e('Monthly', 'rapls-ai-chatbot'); ?></option>
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
                        <th scope="row"><?php esc_html_e('Source Display', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="wpaic_settings[sources_display_mode]">
                                <option value="none" <?php selected($settings['sources_display_mode'] ?? 'matched', 'none'); ?>><?php esc_html_e('Do not show', 'rapls-ai-chatbot'); ?></option>
                                <option value="matched" <?php selected($settings['sources_display_mode'] ?? 'matched', 'matched'); ?>><?php esc_html_e('Show only pages with matching content', 'rapls-ai-chatbot'); ?></option>
                                <option value="all" <?php selected($settings['sources_display_mode'] ?? 'matched', 'all'); ?>><?php esc_html_e('Show all referenced pages', 'rapls-ai-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Controls whether reference page links are displayed below AI responses.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Excluded Pages', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $exclude_ids = $settings['crawler_exclude_ids'] ?? [];
                            ?>
                            <div id="wpaic-exclude-tags" style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                                <?php foreach ($exclude_ids as $eid):
                                    $eid = absint($eid);
                                    if (!$eid) continue;
                                    $etitle = get_the_title($eid);
                                    if (!$etitle) $etitle = '#' . $eid;
                                ?>
                                <span class="wpaic-exclude-tag" data-post-id="<?php echo esc_attr($eid); ?>" style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">
                                    <?php echo esc_html($etitle); ?> <small>(ID:<?php echo esc_html($eid); ?>)</small>
                                    <button type="button" class="wpaic-include-post" data-post-id="<?php echo esc_attr($eid); ?>" style="background: none; border: none; cursor: pointer; color: #b32d2e; font-size: 14px; padding: 0 2px;" title="<?php esc_attr_e('Remove exclusion', 'rapls-ai-chatbot'); ?>">&times;</button>
                                    <input type="hidden" name="wpaic_settings[crawler_exclude_ids][]" value="<?php echo esc_attr($eid); ?>">
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <input type="number" id="wpaic-exclude-id-input" min="1" class="small-text" placeholder="ID">
                                <button type="button" id="wpaic-add-exclude" class="button button-small"><?php esc_html_e('Add by ID', 'rapls-ai-chatbot'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Pages listed here will be skipped during learning and removed from the index.', 'rapls-ai-chatbot'); ?></p>
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
                                    name="wpaic_settings[enhanced_content_extraction]"
                                    value="1"
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
                            <table class="widefat striped" style="margin-top: 10px; font-size: 12px; border-left: 3px solid #667eea;">
                                <caption style="text-align: left; padding: 6px 10px; font-weight: 700; font-size: 12px; color: #50575e;"><?php esc_html_e('Extracted elements:', 'rapls-ai-chatbot'); ?></caption>
                                <tbody>
                                    <tr>
                                        <td><code>&lt;h1&gt;</code> ~ <code>&lt;h6&gt;</code></td>
                                        <td><?php esc_html_e('Markdown headings', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;table&gt;</code> <code>&lt;tr&gt;</code> <code>&lt;td&gt;</code> <code>&lt;th&gt;</code></td>
                                        <td><?php esc_html_e('Structured table data', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;ul&gt;</code> <code>&lt;ol&gt;</code> <code>&lt;li&gt;</code></td>
                                        <td><?php esc_html_e('Formatted lists', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;pre&gt;</code> <code>&lt;code&gt;</code></td>
                                        <td><?php esc_html_e('Code blocks', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;blockquote&gt;</code></td>
                                        <td><?php esc_html_e('Quoted text', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;meta&gt;</code></td>
                                        <td><?php esc_html_e('SEO description & keywords', 'rapls-ai-chatbot'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
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
                <!-- API keys are intentionally omitted from hidden fields to prevent
                     exposure in page source. The sanitize_settings() callback preserves
                     existing keys when blank values are submitted. -->
                <input type="hidden" name="wpaic_settings[openai_model]" value="<?php echo esc_attr($settings['openai_model'] ?? 'gpt-4o-mini'); ?>">
                <input type="hidden" name="wpaic_settings[claude_model]" value="<?php echo esc_attr($settings['claude_model'] ?? 'claude-sonnet-4-20250514'); ?>">
                <input type="hidden" name="wpaic_settings[gemini_model]" value="<?php echo esc_attr($settings['gemini_model'] ?? 'gemini-2.0-flash-exp'); ?>">
                <input type="hidden" name="wpaic_settings[openrouter_model]" value="<?php echo esc_attr($settings['openrouter_model'] ?? 'openrouter/auto'); ?>">
                <input type="hidden" name="wpaic_settings[bot_name]" value="<?php echo esc_attr($settings['bot_name'] ?? 'Assistant'); ?>">
                <input type="hidden" name="wpaic_settings[bot_avatar]" value="<?php echo esc_attr($settings['bot_avatar'] ?? '🤖'); ?>">
                <input type="hidden" name="wpaic_settings[welcome_message]" value="<?php echo esc_attr($settings['welcome_message'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[system_prompt]" value="<?php echo esc_attr($settings['system_prompt'] ?? ''); ?>">
                <input type="hidden" name="wpaic_settings[max_tokens]" value="<?php echo esc_attr($settings['max_tokens'] ?? 1000); ?>">
                <input type="hidden" name="wpaic_settings[temperature]" value="<?php echo esc_attr($settings['temperature'] ?? 0.7); ?>">
                <input type="hidden" name="wpaic_settings[badge_position]" value="<?php echo esc_attr($settings['badge_position'] ?? 'bottom-right'); ?>">
                <input type="hidden" name="wpaic_settings[primary_color]" value="<?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>">
                <input type="hidden" name="wpaic_settings[show_on_mobile]" value="<?php echo esc_attr($settings['show_on_mobile'] ?? 1); ?>">
                <input type="hidden" name="wpaic_settings[save_history]" value="<?php echo esc_attr($settings['save_history'] ?? 1); ?>">
                <input type="hidden" name="wpaic_settings[retention_days]" value="<?php echo esc_attr($settings['retention_days'] ?? 90); ?>">
                <input type="hidden" name="wpaic_settings[embedding_enabled]" value="<?php echo esc_attr($settings['embedding_enabled'] ?? 0); ?>">
                <input type="hidden" name="wpaic_settings[embedding_provider]" value="<?php echo esc_attr($settings['embedding_provider'] ?? 'auto'); ?>">
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
                        🗑️ <?php esc_html_e('Delete All Learning Data', 'rapls-ai-chatbot'); ?>
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
                            <th style="width: 100px;"><?php esc_html_e('Actions', 'rapls-ai-chatbot'); ?></th>
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
                                <td style="white-space: nowrap;">
                                    <button type="button" class="button button-small wpaic-delete-index" data-post-id="<?php echo esc_attr($item['post_id']); ?>" data-index-id="<?php echo esc_attr($item['id']); ?>" title="<?php esc_attr_e('Delete', 'rapls-ai-chatbot'); ?>">
                                        🗑️
                                    </button>
                                    <button type="button" class="button button-small wpaic-exclude-post" data-post-id="<?php echo esc_attr($item['post_id']); ?>" data-title="<?php echo esc_attr($item['title']); ?>" title="<?php esc_attr_e('Exclude from learning', 'rapls-ai-chatbot'); ?>">
                                        🚫
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
        var indexId = $btn.data('index-id');
        var $row = $btn.closest('tr');

        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this index?', 'rapls-ai-chatbot')); ?>')) {
            return;
        }

        $btn.prop('disabled', true);

        var postData = {
            action: 'wpaic_delete_index',
            nonce: wpaicAdmin.nonce,
            post_id: postId
        };
        if (!postId && indexId) {
            postData.index_id = indexId;
        }
        $.post(ajaxurl, postData, function(response) {
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

    // Exclude post from indexed list
    $(document).on('click', '.wpaic-exclude-post', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var title = $btn.data('title');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wpaic_crawler_exclude_post',
            nonce: wpaicAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                // Add tag to exclusion list
                var tag = '<span class="wpaic-exclude-tag" data-post-id="' + postId + '" style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">' +
                    $('<span>').text(title).html() + ' <small>(ID:' + postId + ')</small>' +
                    ' <button type="button" class="wpaic-include-post" data-post-id="' + postId + '" style="background: none; border: none; cursor: pointer; color: #b32d2e; font-size: 14px; padding: 0 2px;" title="<?php echo esc_js(__('Remove exclusion', 'rapls-ai-chatbot')); ?>">&times;</button>' +
                    '<input type="hidden" name="wpaic_settings[crawler_exclude_ids][]" value="' + postId + '">' +
                    '</span>';
                $('#wpaic-exclude-tags').append(tag);
                $row.fadeOut(300, function() {
                    $(this).remove();
                    if ($('#indexed-list-body tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Remove exclusion (include post)
    $(document).on('click', '.wpaic-include-post', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $tag = $btn.closest('.wpaic-exclude-tag');

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wpaic_crawler_include_post',
            nonce: wpaicAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $tag.fadeOut(200, function() { $(this).remove(); });
            } else {
                alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Add exclusion by ID
    $('#wpaic-add-exclude').on('click', function() {
        var $input = $('#wpaic-exclude-id-input');
        var postId = parseInt($input.val(), 10);
        if (!postId || postId < 1) return;

        // Check if already excluded
        if ($('#wpaic-exclude-tags .wpaic-exclude-tag[data-post-id="' + postId + '"]').length) {
            $input.val('');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wpaic_crawler_exclude_post',
            nonce: wpaicAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                var tag = '<span class="wpaic-exclude-tag" data-post-id="' + postId + '" style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; padding: 2px 8px; font-size: 13px;">' +
                    '#' + postId + ' <small>(ID:' + postId + ')</small>' +
                    ' <button type="button" class="wpaic-include-post" data-post-id="' + postId + '" style="background: none; border: none; cursor: pointer; color: #b32d2e; font-size: 14px; padding: 0 2px;" title="<?php echo esc_js(__('Remove exclusion', 'rapls-ai-chatbot')); ?>">&times;</button>' +
                    '<input type="hidden" name="wpaic_settings[crawler_exclude_ids][]" value="' + postId + '">' +
                    '</span>';
                $('#wpaic-exclude-tags').append(tag);
                $input.val('');
                // Remove from indexed list if present
                $('#indexed-list-body tr[data-post-id="' + postId + '"]').fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Generate Embeddings (batch processing with loop)
    $('#wpaic-generate-embeddings').on('click', function() {
        var $btn = $(this);
        var $status = $('#embedding-status');
        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(__('Processing...', 'rapls-ai-chatbot')); ?>');

        function processBatch(source) {
            $.post(ajaxurl, {
                action: 'wpaic_generate_embeddings',
                nonce: wpaicAdmin.nonce,
                source: source
            }, function(response) {
                if (response.success) {
                    $status.text(response.data.message);
                    if (response.data.remaining > 0) {
                        processBatch(source);
                    } else if (source === 'index') {
                        // After index, process knowledge
                        processBatch('knowledge');
                    } else {
                        $btn.prop('disabled', false);
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                } else {
                    $status.text('<?php echo esc_js(__('Error:', 'rapls-ai-chatbot')); ?> ' + (response.data || ''));
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                $status.text('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false);
            });
        }

        processBatch('index');
    });

    // Clear All Embeddings
    $('#wpaic-clear-embeddings').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Clear all embeddings? You will need to regenerate them.', 'rapls-ai-chatbot')); ?>')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'wpaic_clear_embeddings',
            nonce: wpaicAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
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

        $btn.prop('disabled', true).text('<?php echo esc_js(__('Deleting...', 'rapls-ai-chatbot')); ?>');

        wpaicDestructiveAjax({
            data: { action: 'wpaic_delete_all_index', nonce: wpaicAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to delete.', 'rapls-ai-chatbot')); ?>');
                    $btn.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
                }
            },
            cancel: function() {
                $btn.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
            },
            fail: function() {
                alert('<?php echo esc_js(__('An error occurred.', 'rapls-ai-chatbot')); ?>');
                $btn.prop('disabled', false).html('🗑️ <?php echo esc_js(__('Delete All', 'rapls-ai-chatbot')); ?>');
            }
        });
    });
});
</script>
