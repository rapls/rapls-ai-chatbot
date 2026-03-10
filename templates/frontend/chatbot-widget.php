<?php
/**
 * チャットボットウィジェットテンプレート
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="wp-ai-chatbot" class="wp-ai-chatbot wpaic-chatbot <?php echo esc_attr($theme_class); ?>" data-state="closed" data-position="<?php echo esc_attr($badge_position ?? 'bottom-right'); ?>">

    <!-- バッジ（閉じた状態） -->
    <button class="chatbot-badge" aria-label="<?php esc_attr_e('Open chat', 'rapls-ai-chatbot'); ?>">
        <?php if ($badge_icon_type === 'preset' && !empty($badge_icon_preset)) : ?>
            <?php echo wp_kses(wpaic_get_badge_preset_svg($badge_icon_preset), wpaic_get_svg_allowed_tags()); ?>
        <?php elseif ($badge_icon_type === 'image' && !empty($badge_icon_image)) : ?>
            <img class="badge-icon-image" src="<?php echo esc_url($badge_icon_image); ?>" alt="">
        <?php elseif ($badge_icon_type === 'emoji' && !empty($badge_icon_emoji)) : ?>
            <span class="badge-icon-emoji"><?php echo esc_html($badge_icon_emoji); ?></span>
        <?php else : ?>
            <svg class="badge-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                <circle cx="8" cy="10" r="1.5"/>
                <circle cx="12" cy="10" r="1.5"/>
                <circle cx="16" cy="10" r="1.5"/>
            </svg>
        <?php endif; ?>
        <span class="badge-notification" hidden></span>
    </button>

    <!-- チャットウィンドウ -->
    <div class="chatbot-window" aria-hidden="true">

        <!-- ヘッダー -->
        <header class="chatbot-header">
            <div class="header-info">
                <?php if ($bot_avatar_is_image): ?>
                    <img class="bot-avatar bot-avatar-img" src="<?php echo esc_url($bot_avatar); ?>" alt="<?php echo esc_attr($bot_name); ?>">
                <?php else: ?>
                    <span class="bot-avatar"><?php echo esc_html($bot_avatar); ?></span>
                <?php endif; ?>
                <div class="header-text">
                    <span class="bot-name"><?php echo esc_html($bot_name); ?></span>
                    <span class="bot-status"><?php esc_html_e('Online', 'rapls-ai-chatbot'); ?></span>
                </div>
            </div>
            <button class="chatbot-tts-toggle" aria-label="<?php esc_attr_e('Text-to-speech', 'rapls-ai-chatbot'); ?>" hidden>
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                </svg>
            </button>
            <button class="chatbot-close" aria-label="<?php esc_attr_e('Close', 'rapls-ai-chatbot'); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </header>

        <!-- リード獲得フォーム（Pro機能） -->
        <div class="chatbot-lead-form" hidden>
            <div class="lead-form-content">
                <h3 class="lead-form-title"></h3>
                <p class="lead-form-description"></p>
                <form class="lead-form" novalidate>
                    <div class="lead-field lead-field-name" hidden>
                        <label for="lead-name"><?php esc_html_e('Name', 'rapls-ai-chatbot'); ?></label>
                        <input type="text" id="lead-name" name="name" autocomplete="name">
                    </div>
                    <div class="lead-field lead-field-email" hidden>
                        <label for="lead-email"><?php esc_html_e('Email', 'rapls-ai-chatbot'); ?></label>
                        <input type="email" id="lead-email" name="email" autocomplete="email">
                    </div>
                    <div class="lead-field lead-field-phone" hidden>
                        <label for="lead-phone"><?php esc_html_e('Phone', 'rapls-ai-chatbot'); ?></label>
                        <input type="tel" id="lead-phone" name="phone" autocomplete="tel">
                    </div>
                    <div class="lead-field lead-field-company" hidden>
                        <label for="lead-company"><?php esc_html_e('Company', 'rapls-ai-chatbot'); ?></label>
                        <input type="text" id="lead-company" name="company" autocomplete="organization">
                    </div>
                    <div class="lead-form-buttons">
                        <button type="submit" class="lead-submit-btn"><?php esc_html_e('Start Chat', 'rapls-ai-chatbot'); ?></button>
                        <button type="button" class="lead-skip-btn" hidden><?php esc_html_e('Skip', 'rapls-ai-chatbot'); ?></button>
                    </div>
                    <div class="lead-form-error" hidden></div>
                </form>
            </div>
        </div>

        <!-- メッセージエリア -->
        <div class="chatbot-messages" role="log" aria-live="polite">
            <!-- メッセージはJavaScriptで追加される -->
        </div>

        <!-- タイピングインジケーター -->
        <div class="chatbot-typing" hidden aria-label="<?php esc_attr_e('Typing', 'rapls-ai-chatbot'); ?>">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <!-- 画像プレビュー（Pro: マルチモーダル） -->
        <div class="chatbot-image-preview" hidden>
            <img src="" alt="<?php esc_attr_e('Preview', 'rapls-ai-chatbot'); ?>">
            <button type="button" class="image-preview-remove" aria-label="<?php esc_attr_e('Remove image', 'rapls-ai-chatbot'); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>

        <!-- 入力エリア -->
        <form class="chatbot-input" autocomplete="off" novalidate>
            <input type="file" class="chatbot-image-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <button type="button" class="chatbot-image-btn" aria-label="<?php esc_attr_e('Upload image', 'rapls-ai-chatbot'); ?>" hidden>
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                </svg>
            </button>
            <button type="button" class="chatbot-screenshot-btn" aria-label="<?php esc_attr_e('Screenshot', 'rapls-ai-chatbot'); ?>" hidden>
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="12" r="3.2"/>
                    <path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                </svg>
            </button>
            <button type="button" class="chatbot-mic-btn" aria-label="<?php esc_attr_e('Voice input', 'rapls-ai-chatbot'); ?>" hidden>
                <svg class="chatbot-mic-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V5z"/>
                    <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                </svg>
                <svg class="chatbot-mic-stop-icon" viewBox="0 0 24 24" fill="currentColor" style="display:none">
                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                </svg>
            </button>
            <textarea
                name="wpaic_message_<?php echo esc_attr(uniqid()); ?>"
                placeholder="<?php esc_attr_e('Type a message...', 'rapls-ai-chatbot'); ?>"
                rows="1"
                aria-label="<?php esc_attr_e('Message input', 'rapls-ai-chatbot'); ?>"
                autocomplete="new-password"
                spellcheck="false"
            ></textarea>
            <button type="submit" aria-label="<?php esc_attr_e('Send', 'rapls-ai-chatbot'); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </form>

        <?php
        $wl_footer = $settings['pro_features']['white_label_footer'] ?? '';
        if ($wl_footer !== '' && WPAIC_Pro_Features::get_instance()->is_pro()) :
            $wl_url = $settings['pro_features']['white_label_footer_url'] ?? '';
            $wl_target = $settings['pro_features']['white_label_footer_target'] ?? '_blank';
        ?>
        <div class="chatbot-footer-branding">
            <?php if ($wl_url !== '') : ?>
                <a href="<?php echo esc_url($wl_url); ?>" target="<?php echo esc_attr($wl_target); ?>"<?php echo $wl_target === '_blank' ? ' rel="noopener noreferrer"' : ''; ?>><?php echo esc_html($wl_footer); ?></a>
            <?php else : ?>
                <?php echo esc_html($wl_footer); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($settings['pro_features']['white_label_enabled']) || empty($settings['pro_features']['hide_powered_by'])) : ?>
        <div class="chatbot-footer-powered"><a href="https://raplsworks.com/rapls-ai-chatbot-guide/" target="_blank" rel="noopener noreferrer">Powered by Rapls Works</a></div>
        <?php endif; ?>

    </div>
</div>
