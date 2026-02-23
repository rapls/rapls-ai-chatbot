<?php
/**
 * Settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpaic-admin">
    <h1><?php esc_html_e('AI Chatbot - Settings', 'rapls-ai-chatbot'); ?></h1>

    <?php $is_pro_active = get_option('wpaic_pro_active'); ?>
    <?php if (!$is_pro_active) : ?>
    <div class="wpaic-pro-settings-banner">
        <span class="dashicons dashicons-star-filled"></span>
        <span><?php esc_html_e('Extend your AI chatbot with automation, analytics, and business-ready features.', 'rapls-ai-chatbot'); ?></span>
        <a href="https://raplsworks.com/rapls-ai-chatbot-pro" target="_blank" class="button">
            <?php esc_html_e('Learn More', 'rapls-ai-chatbot'); ?>
        </a>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('wpaic_settings_group'); ?>

        <div class="wpaic-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#tab-ai" class="nav-tab nav-tab-active"><?php esc_html_e('AI Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-chat" class="nav-tab"><?php esc_html_e('Chat Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-display" class="nav-tab"><?php esc_html_e('Display Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-recaptcha" class="nav-tab"><?php esc_html_e('reCAPTCHA', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-advanced" class="nav-tab"><?php esc_html_e('Advanced', 'rapls-ai-chatbot'); ?></a>
            </nav>

            <!-- AI Settings -->
            <div id="tab-ai" class="tab-content active">
                <div class="wpaic-tab-header">
                    <h2><?php esc_html_e('AI Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="wpaic-reset-tab-btn" data-tab="tab-ai">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Provider', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="wpaic_settings[ai_provider]" id="ai_provider">
                                <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI (ChatGPT)</option>
                                <option value="claude" <?php selected($settings['ai_provider'] ?? '', 'claude'); ?>>Anthropic (Claude)</option>
                                <option value="gemini" <?php selected($settings['ai_provider'] ?? '', 'gemini'); ?>>Google (Gemini)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- OpenAI Settings -->
                <div id="openai-settings" class="provider-settings">
                    <h3><?php esc_html_e('OpenAI Settings', 'rapls-ai-chatbot'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <div class="wpaic-api-key-wrapper">
                                    <input type="password" name="wpaic_settings[openai_api_key]"
                                           id="openai_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['openai_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="wpaic_settings[delete_openai_api_key]" id="delete_openai_api_key" value="0">
                                    <button type="button" class="button wpaic-test-api" data-provider="openai"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['openai_api_key'])): ?>
                                        <button type="button" class="button wpaic-clear-api-key" data-target="openai_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="wpaic-key-status wpaic-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="wpaic-key-status wpaic-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Enter your OpenAI API key.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $openai_vision_models = $openai_provider->get_vision_models(); ?>
                                <select name="wpaic_settings[openai_model]" id="wpaic-openai-model"
                                    data-initial-value="<?php echo esc_attr($settings['openai_model'] ?? 'gpt-4o-mini'); ?>">
                                    <?php foreach ($openai_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $openai_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['openai_model'] ?? 'gpt-4o-mini', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button wpaic-refresh-models" data-provider="openai" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description wpaic-vision-warning" style="display: none; color: #d63638;">
                                    <?php esc_html_e('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Claude Settings -->
                <div id="claude-settings" class="provider-settings">
                    <h3><?php esc_html_e('Claude Settings', 'rapls-ai-chatbot'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <div class="wpaic-api-key-wrapper">
                                    <input type="password" name="wpaic_settings[claude_api_key]"
                                           id="claude_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['claude_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="wpaic_settings[delete_claude_api_key]" id="delete_claude_api_key" value="0">
                                    <button type="button" class="button wpaic-test-api" data-provider="claude"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['claude_api_key'])): ?>
                                        <button type="button" class="button wpaic-clear-api-key" data-target="claude_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="wpaic-key-status wpaic-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="wpaic-key-status wpaic-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Enter your Anthropic API key.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $claude_vision_models = $claude_provider->get_vision_models(); ?>
                                <select name="wpaic_settings[claude_model]" id="wpaic-claude-model"
                                    data-initial-value="<?php echo esc_attr($settings['claude_model'] ?? 'claude-sonnet-4-20250514'); ?>">
                                    <?php foreach ($claude_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $claude_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['claude_model'] ?? 'claude-sonnet-4-20250514', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button wpaic-refresh-models" data-provider="claude" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description wpaic-vision-warning" style="display: none; color: #d63638;">
                                    <?php esc_html_e('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Gemini Settings -->
                <div id="gemini-settings" class="provider-settings">
                    <h3><?php esc_html_e('Gemini Settings', 'rapls-ai-chatbot'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <div class="wpaic-api-key-wrapper">
                                    <input type="password" name="wpaic_settings[gemini_api_key]"
                                           id="gemini_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['gemini_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="wpaic_settings[delete_gemini_api_key]" id="delete_gemini_api_key" value="0">
                                    <button type="button" class="button wpaic-test-api" data-provider="gemini"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['gemini_api_key'])): ?>
                                        <button type="button" class="button wpaic-clear-api-key" data-target="gemini_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="wpaic-key-status wpaic-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="wpaic-key-status wpaic-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Get your API key from Google AI Studio.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $gemini_vision_models = $gemini_provider->get_vision_models(); ?>
                                <select name="wpaic_settings[gemini_model]" id="wpaic-gemini-model"
                                    data-initial-value="<?php echo esc_attr($settings['gemini_model'] ?? 'gemini-2.0-flash-exp'); ?>">
                                    <?php foreach ($gemini_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $gemini_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['gemini_model'] ?? 'gemini-2.0-flash-exp', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button wpaic-refresh-models" data-provider="gemini" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description wpaic-vision-warning" style="display: none; color: #d63638;">
                                    <?php esc_html_e('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Chat Settings -->
            <div id="tab-chat" class="tab-content">
                <div class="wpaic-tab-header">
                    <h2><?php esc_html_e('Chat Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="wpaic-reset-tab-btn" data-tab="tab-chat">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Bot Name', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="wpaic_settings[bot_name]" id="wpaic_bot_name"
                                   value="<?php echo esc_attr($settings['bot_name'] ?? 'Assistant'); ?>"
                                   class="regular-text">
                            <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_bot_name" data-default="Assistant">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Avatar', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $bot_avatar_val = $settings['bot_avatar'] ?? '🤖';
                            $is_image = filter_var($bot_avatar_val, FILTER_VALIDATE_URL) || preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $bot_avatar_val);
                            ?>
                            <div class="wpaic-avatar-setting">
                                <div class="wpaic-avatar-preview" style="margin-bottom: 10px;">
                                    <?php if ($is_image): ?>
                                        <img src="<?php echo esc_url($bot_avatar_val); ?>" alt="Avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">
                                    <?php else: ?>
                                        <span style="font-size: 48px; line-height: 1;"><?php echo esc_html($bot_avatar_val); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input type="text" name="wpaic_settings[bot_avatar]" id="wpaic_bot_avatar"
                                           value="<?php echo esc_attr($bot_avatar_val); ?>"
                                           class="regular-text" placeholder="🤖">
                                    <button type="button" class="button" id="wpaic-upload-avatar">
                                        <?php esc_html_e('Select Image', 'rapls-ai-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button" id="wpaic-reset-avatar">
                                        <?php esc_html_e('Reset to Emoji', 'rapls-ai-chatbot'); ?>
                                    </button>
                                </div>
                                <p class="description"><?php esc_html_e('Enter an emoji or select an image (recommended: 96x96px or larger, square).', 'rapls-ai-chatbot'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Welcome Message', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <textarea name="wpaic_settings[welcome_message]" id="wpaic_welcome_message" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['welcome_message'] ?? 'Hello! How can I help you today?');
                            ?></textarea>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_welcome_message" data-default="Hello! How can I help you today?">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('System Prompt', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_system_prompt = "You are a knowledgeable assistant for this website. Follow these rules:\n\n1. ACCURACY: When reference information is provided, treat it as the primary and most reliable source. Base your answers on this information first.\n2. HONESTY: If the provided information does not cover the user's question, clearly state that you don't have specific information about it, then offer general guidance if appropriate.\n3. NO FABRICATION: Never invent facts, URLs, prices, dates, or specific details that are not in the provided reference information.\n4. CONCISENESS: Provide clear, focused answers. Avoid unnecessary repetition or filler.\n5. LANGUAGE: Always respond in the same language the user writes in.\n6. TONE: Be professional, friendly, and helpful.";
                            ?>
                            <textarea name="wpaic_settings[system_prompt]" id="wpaic_system_prompt" rows="10" class="large-text"><?php
                                echo esc_textarea($settings['system_prompt'] ?? $default_system_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('A prompt that defines the AI behavior.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_system_prompt" data-default="<?php echo esc_attr($default_system_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API Quota Error Message', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="wpaic_settings[quota_error_message]"
                                   id="wpaic_quota_error_message"
                                   value="<?php echo esc_attr($settings['quota_error_message'] ?? 'Currently recharging. Please try again later.'); ?>"
                                   class="large-text">
                            <p class="description"><?php esc_html_e('Message displayed when the API quota is exceeded or billing issue occurs.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_quota_error_message" data-default="Currently recharging. Please try again later.">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Max Tokens', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="wpaic_settings[max_tokens]" id="wpaic_max_tokens"
                                   value="<?php echo esc_attr($settings['max_tokens'] ?? 1000); ?>"
                                   min="100" max="4000" class="small-text">
                            <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_max_tokens" data-default="1000">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Temperature', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="wpaic_settings[temperature]" id="wpaic_temperature"
                                   value="<?php echo esc_attr($settings['temperature'] ?? 0.7); ?>"
                                   min="0" max="2" step="0.1" class="small-text">
                            <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_temperature" data-default="0.7">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Closer to 0 is more deterministic, closer to 2 is more random.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3>
                    <label>
                        <input type="checkbox" id="wpaic_advanced_context_toggle" class="wpaic-advanced-toggle" data-target="wpaic-advanced-context-section">
                        <?php esc_html_e('Context Prompts (Advanced)', 'rapls-ai-chatbot'); ?>
                    </label>
                </h3>
                <p class="description"><?php esc_html_e('These prompts are appended to the system prompt when knowledge base or site learning data is used. Use {context} as a placeholder for the actual content.', 'rapls-ai-chatbot'); ?></p>

                <div id="wpaic-advanced-context-section" class="wpaic-advanced-section wpaic-advanced-disabled">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Knowledge Base (Exact Match)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_exact_match = "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===";
                            ?>
                            <textarea name="wpaic_settings[knowledge_exact_prompt]" id="wpaic_knowledge_exact_prompt" rows="8" class="large-text"><?php
                                echo esc_textarea($settings['knowledge_exact_prompt'] ?? $default_exact_match);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when an exact Q&A match is found in the knowledge base.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_knowledge_exact_prompt" data-default="<?php echo esc_attr($default_exact_match); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Knowledge Base (Q&A Format)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_qa_prompt = "=== CRITICAL INSTRUCTIONS ===\nBelow is a FAQ database. When the user asks a question:\n1. FIRST, look for [BEST MATCH] - this is the most relevant Q&A for the user's question\n2. If [BEST MATCH] exists, use that Answer to respond\n3. If no [BEST MATCH], find the Question that matches or is similar to the user's question\n4. Return the corresponding Answer from the FAQ\n5. DO NOT make up answers - ONLY use the information provided below\n\nIMPORTANT: The Answer after [BEST MATCH] is your primary response source.\n\n=== FAQ DATABASE ===\n{context}\n=== END FAQ DATABASE ===";
                            ?>
                            <textarea name="wpaic_settings[knowledge_qa_prompt]" id="wpaic_knowledge_qa_prompt" rows="10" class="large-text"><?php
                                echo esc_textarea($settings['knowledge_qa_prompt'] ?? $default_qa_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when Q&A format knowledge is found (but not exact match).', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_knowledge_qa_prompt" data-default="<?php echo esc_attr($default_qa_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Learning Context', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_site_prompt = "[IMPORTANT: Reference Information]\nYou MUST use the following information as the primary source when answering. If the answer can be found in this information, use it directly.\nIf the reference information does NOT contain the answer, clearly state that you don't have specific information about it. Do NOT guess or fabricate details.\n\n{context}";
                            ?>
                            <textarea name="wpaic_settings[site_context_prompt]" id="wpaic_site_context_prompt" rows="6" class="large-text"><?php
                                echo esc_textarea($settings['site_context_prompt'] ?? $default_site_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when site learning content is provided as context.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_site_context_prompt" data-default="<?php echo esc_attr($default_site_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                </table>
                </div><!-- /.wpaic-advanced-context-section -->

                <hr style="margin: 30px 0;">

                <h3>
                    <label>
                        <input type="checkbox" id="wpaic_advanced_feature_toggle" class="wpaic-advanced-toggle" data-target="wpaic-advanced-feature-section">
                        <?php esc_html_e('Feature Prompts (Advanced)', 'rapls-ai-chatbot'); ?>
                    </label>
                </h3>
                <p class="description"><?php esc_html_e('These prompts control how AI behaves for specific features.', 'rapls-ai-chatbot'); ?></p>

                <div id="wpaic-advanced-feature-section" class="wpaic-advanced-section wpaic-advanced-disabled">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Regenerate Response Instruction', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_regenerate_prompt = '[REGENERATION REQUEST #{variation_number}]: The user wants a DIFFERENT answer. FORBIDDEN: Do not start with "{forbidden_start}". {style}. Create a completely new response with different wording. IMPORTANT: Do NOT use headings, labels, or section markers like【】or brackets. Write in natural flowing paragraphs. Complete all sentences fully.';
                            ?>
                            <textarea name="wpaic_settings[regenerate_prompt]" id="wpaic_regenerate_prompt" rows="4" class="large-text"><?php
                                echo esc_textarea($settings['regenerate_prompt'] ?? $default_regenerate_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Instruction appended when user requests response regeneration.', 'rapls-ai-chatbot'); ?></p>
                            <details style="margin-top: 6px;">
                                <summary style="cursor: pointer; color: #2271b1; font-size: 13px;"><?php esc_html_e('Available placeholders', 'rapls-ai-chatbot'); ?></summary>
                                <ul style="margin: 6px 0 0 16px; font-size: 13px; color: #646970;">
                                    <li><code>{variation_number}</code> — <?php esc_html_e('A random number (1-1000) inserted each time to force the AI to generate a unique response.', 'rapls-ai-chatbot'); ?></li>
                                    <li><code>{forbidden_start}</code> — <?php esc_html_e('The first 50 characters of the previous response. The AI is instructed not to begin with the same text.', 'rapls-ai-chatbot'); ?></li>
                                    <li><code>{style}</code> — <?php esc_html_e('A randomly selected style instruction (e.g. "Use a casual tone", "Explain from a different angle") to vary the response.', 'rapls-ai-chatbot'); ?></li>
                                </ul>
                            </details>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_regenerate_prompt" data-default="<?php echo esc_attr($default_regenerate_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Feedback Learning: Good Examples', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_feedback_good = "[LEARNING FROM USER FEEDBACK - GOOD EXAMPLES]\nThe following responses received positive feedback. Use these as examples of good responses:";
                            ?>
                            <textarea name="wpaic_settings[feedback_good_header]" id="wpaic_feedback_good_header" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['feedback_good_header'] ?? $default_feedback_good);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Header text prepended to positive feedback examples sent to AI.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_feedback_good_header" data-default="<?php echo esc_attr($default_feedback_good); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Feedback Learning: Bad Examples', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_feedback_bad = "[LEARNING FROM USER FEEDBACK - AVOID THESE PATTERNS]\nThe following responses received negative feedback. AVOID responding in similar ways:";
                            ?>
                            <textarea name="wpaic_settings[feedback_bad_header]" id="wpaic_feedback_bad_header" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['feedback_bad_header'] ?? $default_feedback_bad);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Header text prepended to negative feedback examples sent to AI.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_feedback_bad_header" data-default="<?php echo esc_attr($default_feedback_bad); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Conversation Summary Prompt', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_summary_prompt = 'Please summarize the following conversation in 2-3 sentences, highlighting the main topics discussed and any conclusions reached:';
                            ?>
                            <textarea name="wpaic_settings[summary_prompt]" id="wpaic_summary_prompt" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['summary_prompt'] ?? $default_summary_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used to generate conversation summaries.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_summary_prompt" data-default="<?php echo esc_attr($default_summary_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                </table>
                </div><!-- /.wpaic-advanced-feature-section -->
            </div>

            <!-- Display Settings -->
            <div id="tab-display" class="tab-content">
                <div class="wpaic-tab-header">
                    <h2><?php esc_html_e('Display Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="wpaic-reset-tab-btn" data-tab="tab-display">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Widget Theme', 'rapls-ai-chatbot'); ?>
                            <span class="wpaic-tooltip" data-tooltip="<?php esc_attr_e('チャットウィジェットの外観テーマを選択します。Pro版ではより洗練されたデザインのテーマが利用できます。', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <?php
                            $current_theme = $settings['widget_theme'] ?? 'default';
                            $free_themes = [
                                'default' => __('Default', 'rapls-ai-chatbot'),
                                'simple' => __('Simple', 'rapls-ai-chatbot'),
                                'classic' => __('Classic', 'rapls-ai-chatbot'),
                                'light' => __('Light', 'rapls-ai-chatbot'),
                                'minimal' => __('Minimal', 'rapls-ai-chatbot'),
                                'flat' => __('Flat', 'rapls-ai-chatbot'),
                            ];
                            $pro_themes = [
                                'modern' => __('Modern', 'rapls-ai-chatbot'),
                                'gradient' => __('Gradient', 'rapls-ai-chatbot'),
                                'dark' => __('Dark', 'rapls-ai-chatbot'),
                                'glass' => __('Glass', 'rapls-ai-chatbot'),
                                'rounded' => __('Rounded', 'rapls-ai-chatbot'),
                                'ocean' => __('Ocean', 'rapls-ai-chatbot'),
                                'sunset' => __('Sunset', 'rapls-ai-chatbot'),
                                'forest' => __('Forest', 'rapls-ai-chatbot'),
                                'neon' => __('Neon', 'rapls-ai-chatbot'),
                                'elegant' => __('Elegant', 'rapls-ai-chatbot'),
                            ];
                            ?>
                            <div class="wpaic-theme-selector">
                                <p class="wpaic-theme-group-label"><?php esc_html_e('Free Themes', 'rapls-ai-chatbot'); ?></p>
                                <div class="wpaic-theme-options">
                                    <?php foreach ($free_themes as $theme_key => $theme_name): ?>
                                        <label class="wpaic-theme-option <?php echo esc_attr($current_theme === $theme_key ? 'selected' : ''); ?>">
                                            <input type="radio" name="wpaic_settings[widget_theme]" value="<?php echo esc_attr($theme_key); ?>"
                                                <?php checked($current_theme, $theme_key); ?>>
                                            <span class="wpaic-theme-preview wpaic-theme-preview-<?php echo esc_attr($theme_key); ?>"></span>
                                            <span class="wpaic-theme-name"><?php echo esc_html($theme_name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <p class="wpaic-theme-group-label"><?php esc_html_e('Pro Themes', 'rapls-ai-chatbot'); ?> <?php if (!$is_pro_active): ?><span class="wpaic-pro-badge-small">PRO</span><?php endif; ?></p>
                                <div class="wpaic-theme-options <?php echo esc_attr(!$is_pro_active ? 'wpaic-themes-locked' : ''); ?>">
                                    <?php foreach ($pro_themes as $theme_key => $theme_name): ?>
                                        <label class="wpaic-theme-option <?php echo esc_attr($current_theme === $theme_key ? 'selected' : ''); ?> <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>">
                                            <input type="radio" name="wpaic_settings[widget_theme]" value="<?php echo esc_attr($theme_key); ?>"
                                                <?php checked($current_theme, $theme_key); ?>
                                                <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                                            <span class="wpaic-theme-preview wpaic-theme-preview-<?php echo esc_attr($theme_key); ?>"></span>
                                            <span class="wpaic-theme-name"><?php echo esc_html($theme_name); ?></span>
                                            <?php if (!$is_pro_active): ?>
                                            <span class="wpaic-theme-lock"><span class="dashicons dashicons-lock"></span></span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!$is_pro_active): ?>
                                <p class="description">
                                    <a href="https://raplsworks.com/rapls-ai-chatbot-pro/" target="_blank"><?php esc_html_e('Upgrade to Pro to unlock all themes', 'rapls-ai-chatbot'); ?></a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Badge Position (Margin)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div class="wpaic-margin-inputs">
                                <label>
                                    <?php esc_html_e('Right:', 'rapls-ai-chatbot'); ?>
                                    <input type="number" name="wpaic_settings[badge_margin_right]" id="wpaic_badge_margin_right"
                                           value="<?php echo esc_attr($settings['badge_margin_right'] ?? 20); ?>"
                                           min="0" max="200" style="width: 70px;"> px
                                </label>
                                <label>
                                    <?php esc_html_e('Bottom:', 'rapls-ai-chatbot'); ?>
                                    <input type="number" name="wpaic_settings[badge_margin_bottom]" id="wpaic_badge_margin_bottom"
                                           value="<?php echo esc_attr($settings['badge_margin_bottom'] ?? 20); ?>"
                                           min="0" max="200" style="width: 70px;"> px
                                </label>
                                <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_badge_margin_right" data-default="20" onclick="jQuery('#wpaic_badge_margin_right').val(20); jQuery('#wpaic_badge_margin_bottom').val(20); return false;">
                                    <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                </button>
                            </div>
                            <p class="description"><?php esc_html_e('Adjust the badge position from the bottom right of the screen.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?>
                            <?php if (!$is_pro_active): ?><span class="wpaic-pro-badge-small">PRO</span><?php endif; ?>
                        </th>
                        <td>
                            <?php
                            $badge_pro_settings = $settings['pro_features'] ?? [];
                            $badge_icon_type = $badge_pro_settings['badge_icon_type'] ?? 'default';
                            $badge_icon_preset = $badge_pro_settings['badge_icon_preset'] ?? '';
                            $badge_icon_image = $badge_pro_settings['badge_icon_image'] ?? '';
                            $badge_icon_emoji = $badge_pro_settings['badge_icon_emoji'] ?? '';
                            ?>
                            <div class="wpaic-badge-icon-preview" style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: <?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); flex-shrink: 0;">
                                    <?php if ($badge_icon_type === 'preset' && !empty($badge_icon_preset)) : ?>
                                        <span style="width: 24px; height: 24px;"><?php echo wp_kses(wpaic_get_badge_preset_svg($badge_icon_preset), wpaic_get_svg_allowed_tags()); ?></span>
                                    <?php elseif ($badge_icon_type === 'image' && !empty($badge_icon_image)) : ?>
                                        <img src="<?php echo esc_url($badge_icon_image); ?>" alt="" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                    <?php elseif ($badge_icon_type === 'emoji' && !empty($badge_icon_emoji)) : ?>
                                        <span style="font-size: 22px; line-height: 1;"><?php echo esc_html($badge_icon_emoji); ?></span>
                                    <?php else : ?>
                                        <svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><circle cx="8" cy="10" r="1.5"/><circle cx="12" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($is_pro_active) : ?>
                                        <span class="description"><?php esc_html_e('Configured in Pro Settings > Badge Icon tab.', 'rapls-ai-chatbot'); ?></span>
                                    <?php else : ?>
                                        <span class="description" style="color: #999;">
                                            <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                            <?php esc_html_e('Upgrade to Pro to customize the badge icon with presets, images, or emoji.', 'rapls-ai-chatbot'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Primary Color', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="color" name="wpaic_settings[primary_color]" id="wpaic_primary_color"
                                   value="<?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>">
                            <button type="button" class="button button-small wpaic-reset-field" data-target="wpaic_primary_color" data-default="#007bff">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('This color is automatically set when you select a theme.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Dark Mode', 'rapls-ai-chatbot'); ?>
                            <?php if (!$is_pro_active): ?><span class="wpaic-pro-badge-small">PRO</span><?php endif; ?>
                            <span class="wpaic-tooltip" data-tooltip="<?php esc_attr_e('ダークモードを有効にすると、選択したテーマに関係なくチャットウィジェットがダークカラーで表示されます。', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <label class="<?php echo esc_attr(!$is_pro_active ? 'wpaic-pro-locked' : ''); ?>">
                                <input type="checkbox" name="wpaic_settings[dark_mode]" value="1"
                                    <?php checked($settings['dark_mode'] ?? false); ?>
                                    <?php echo esc_attr(!$is_pro_active ? 'disabled' : ''); ?>>
                                <?php esc_html_e('Enable dark mode for the chatbot', 'rapls-ai-chatbot'); ?>
                                <?php if (!$is_pro_active): ?>
                                <span class="dashicons dashicons-lock" style="color: #999; margin-left: 5px;"></span>
                                <?php endif; ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mobile Display', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[show_on_mobile]" value="1"
                                    <?php checked($settings['show_on_mobile'] ?? true); ?>>
                                <?php esc_html_e('Show on mobile devices', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Feedback Buttons', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[show_feedback_buttons]" value="1"
                                    <?php checked($settings['show_feedback_buttons'] ?? false); ?>>
                                <?php esc_html_e('Show feedback buttons (👍👎) on bot messages', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Allow users to rate bot responses. Feedback is used to improve AI response quality.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Page Type Display', 'rapls-ai-chatbot'); ?>
                            <span class="wpaic-tooltip" data-tooltip="<?php esc_attr_e('チャットボットを表示するページの種類を選択します。チェックを外すとそのタイプのページでは非表示になります。', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="wpaic_settings[badge_show_on_home]" value="1"
                                        <?php checked($settings['badge_show_on_home'] ?? true); ?>>
                                    <?php esc_html_e('Homepage / Front Page', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="wpaic_settings[badge_show_on_posts]" value="1"
                                        <?php checked($settings['badge_show_on_posts'] ?? true); ?>>
                                    <?php esc_html_e('Single Posts', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="wpaic_settings[badge_show_on_pages]" value="1"
                                        <?php checked($settings['badge_show_on_pages'] ?? true); ?>>
                                    <?php esc_html_e('Pages', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="wpaic_settings[badge_show_on_archives]" value="1"
                                        <?php checked($settings['badge_show_on_archives'] ?? true); ?>>
                                    <?php esc_html_e('Archives (Category, Tag, Date, Author)', 'rapls-ai-chatbot'); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Uncheck to hide the chatbot on specific page types. Note: Include IDs below will override these settings.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Include Only (IDs)', 'rapls-ai-chatbot'); ?>
                            <span class="wpaic-tooltip" data-tooltip="<?php esc_attr_e('ここに ID を入力すると、そのページ/投稿でのみチャットボットが表示されます。空の場合はページタイプ設定に従います。', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <input type="text" name="wpaic_settings[badge_include_ids]"
                                   value="<?php echo esc_attr($settings['badge_include_ids'] ?? ''); ?>"
                                   class="regular-text" placeholder="<?php esc_attr_e('e.g. 10, 25, 142', 'rapls-ai-chatbot'); ?>">
                            <p class="description"><?php esc_html_e('Comma-separated post/page IDs. If set, the chatbot will ONLY be displayed on these pages (overrides page type settings above).', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Exclude (IDs)', 'rapls-ai-chatbot'); ?>
                            <span class="wpaic-tooltip" data-tooltip="<?php esc_attr_e('指定した ID のページ/投稿ではチャットボットが非表示になります。', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <input type="text" name="wpaic_settings[badge_exclude_ids]"
                                   value="<?php echo esc_attr($settings['badge_exclude_ids'] ?? ''); ?>"
                                   class="regular-text" placeholder="<?php esc_attr_e('e.g. 5, 30, 200', 'rapls-ai-chatbot'); ?>">
                            <p class="description"><?php esc_html_e('Comma-separated post/page IDs. The chatbot will NOT be displayed on these pages.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Page Exclusion', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $excluded_pages = $settings['excluded_pages'] ?? [];
                            $pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title']);
                            ?>
                            <div class="wpaic-page-exclusion">
                                <!-- Empty value to ensure the field is submitted even when no pages are selected -->
                                <input type="hidden" name="wpaic_settings[excluded_pages_submitted]" value="1">
                                <select id="wpaic-page-selector" style="min-width: 300px;">
                                    <option value=""><?php esc_html_e('-- Select page to exclude --', 'rapls-ai-chatbot'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <?php if (!in_array($page->ID, $excluded_pages, true)): ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button" id="wpaic-add-excluded-page"><?php esc_html_e('Add', 'rapls-ai-chatbot'); ?></button>

                                <div id="wpaic-excluded-pages-list" style="margin-top: 15px;">
                                    <?php if (!empty($excluded_pages)): ?>
                                        <?php foreach ($excluded_pages as $page_id): ?>
                                            <?php $page_title = get_the_title($page_id); ?>
                                            <?php if ($page_title): ?>
                                                <div class="wpaic-excluded-page-item" data-page-id="<?php echo esc_attr($page_id); ?>" style="display: inline-flex; align-items: center; background: #f0f0f1; border-radius: 4px; padding: 5px 10px; margin: 3px 5px 3px 0;">
                                                    <span><?php echo esc_html($page_title); ?></span>
                                                    <input type="hidden" name="wpaic_settings[excluded_pages][]" value="<?php echo esc_attr($page_id); ?>">
                                                    <button type="button" class="wpaic-remove-excluded-page" style="background: none; border: none; cursor: pointer; color: #a00; margin-left: 8px; font-size: 16px;">&times;</button>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Select specific pages to hide the chatbot (dropdown-based). For posts, use Exclude IDs above.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- reCAPTCHA Settings -->
            <div id="tab-recaptcha" class="tab-content">
                <div class="wpaic-tab-header">
                    <h2><?php esc_html_e('reCAPTCHA Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="wpaic-reset-tab-btn" data-tab="tab-recaptcha">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>
                <p class="description">
                    <?php esc_html_e('Use Google reCAPTCHA v3 to prevent spam.', 'rapls-ai-chatbot'); ?>
                    <a href="https://www.google.com/recaptcha/admin" target="_blank"><?php esc_html_e('Get keys from Google reCAPTCHA Admin Console', 'rapls-ai-chatbot'); ?></a>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable reCAPTCHA', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[recaptcha_enabled]" value="1"
                                    <?php checked($settings['recaptcha_enabled'] ?? false); ?>>
                                <?php esc_html_e('Protect with reCAPTCHA v3', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Key', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="wpaic_settings[recaptcha_site_key]"
                                   value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>"
                                   class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e('reCAPTCHA v3 site key', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Secret Key', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="password" name="wpaic_settings[recaptcha_secret_key]"
                                   value=""
                                   class="regular-text" autocomplete="off"
                                   placeholder="<?php echo esc_attr(!empty($settings['recaptcha_secret_key']) ? '••••••••' : ''); ?>">
                            <p class="description">
                                <?php esc_html_e('reCAPTCHA v3 secret key', 'rapls-ai-chatbot'); ?>
                                <?php if (!empty($settings['recaptcha_secret_key'])): ?>
                                    <span style="color: #46b450;">&#10003; <?php esc_html_e('Key saved (encrypted). Leave empty to keep current key.', 'rapls-ai-chatbot'); ?></span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Score Threshold', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="wpaic_settings[recaptcha_threshold]"
                                   value="<?php echo esc_attr($settings['recaptcha_threshold'] ?? 0.5); ?>"
                                   min="0" max="1" step="0.1" class="small-text">
                            <p class="description"><?php esc_html_e('0.0-1.0 (default: 0.5). Requests below this score will be blocked.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Use Existing reCAPTCHA', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[recaptcha_use_existing]" value="1"
                                    <?php checked($settings['recaptcha_use_existing'] ?? false); ?>>
                                <?php esc_html_e('Use existing reCAPTCHA on the page', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('If another plugin (e.g., Contact Form 7) loads reCAPTCHA, avoid loading the script twice.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Advanced Settings -->
            <div id="tab-advanced" class="tab-content">
                <div class="wpaic-tab-header">
                    <h2><?php esc_html_e('Advanced Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="wpaic-reset-tab-btn" data-tab="tab-advanced">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Save History', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[save_history]" value="1"
                                    <?php checked($settings['save_history'] ?? true); ?>>
                                <?php esc_html_e('Save conversation history', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('History Retention', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="wpaic_settings[retention_days]"
                                   value="<?php echo esc_attr($settings['retention_days'] ?? 90); ?>"
                                   min="0" class="small-text"> <?php esc_html_e('days', 'rapls-ai-chatbot'); ?>
                            <p class="description"><?php esc_html_e('0 for unlimited retention', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Delete Data on Uninstall', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[delete_data_on_uninstall]" value="1"
                                    <?php checked($settings['delete_data_on_uninstall'] ?? false); ?>>
                                <?php esc_html_e('Delete all conversation history, knowledge base, leads, and other plugin data when the plugin is uninstalled', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description" style="color: #d63638;">
                                <?php esc_html_e('Warning: This action is irreversible. If disabled, plugin settings will still be removed but database tables (conversations, knowledge base, leads) will be preserved.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rate Limit', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <div>
                                    <input type="number" name="wpaic_settings[rate_limit]"
                                           value="<?php echo esc_attr($settings['rate_limit'] ?? 20); ?>"
                                           min="0" class="small-text">
                                    <span><?php esc_html_e('requests', 'rapls-ai-chatbot'); ?></span>
                                </div>
                                <span>/</span>
                                <div>
                                    <select name="wpaic_settings[rate_limit_window]">
                                        <option value="60" <?php selected($settings['rate_limit_window'] ?? 3600, 60); ?>><?php esc_html_e('1 minute', 'rapls-ai-chatbot'); ?></option>
                                        <option value="300" <?php selected($settings['rate_limit_window'] ?? 3600, 300); ?>><?php esc_html_e('5 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="600" <?php selected($settings['rate_limit_window'] ?? 3600, 600); ?>><?php esc_html_e('10 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="1800" <?php selected($settings['rate_limit_window'] ?? 3600, 1800); ?>><?php esc_html_e('30 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="3600" <?php selected($settings['rate_limit_window'] ?? 3600, 3600); ?>><?php esc_html_e('1 hour', 'rapls-ai-chatbot'); ?></option>
                                        <option value="86400" <?php selected($settings['rate_limit_window'] ?? 3600, 86400); ?>><?php esc_html_e('1 day', 'rapls-ai-chatbot'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Limit per IP address. Set requests to 0 for unlimited.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cloudflare Integration', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpaic_settings[trust_cloudflare_ip]" value="1"
                                    <?php checked($settings['trust_cloudflare_ip'] ?? false); ?>>
                                <?php esc_html_e('Trust Cloudflare CF-Connecting-IP header', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Enable only if your site is behind Cloudflare. Uses Cloudflare\'s header to detect the real visitor IP for rate limiting.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description" style="color: #d63638;"><strong><?php esc_html_e('Security warning: Only enable this if ALL traffic to your server passes through Cloudflare. If your server is directly accessible (bypassing Cloudflare), attackers can forge this header to bypass rate limiting and IP blocking.', 'rapls-ai-chatbot'); ?></strong></p>
                            <br>
                            <label>
                                <input type="checkbox" name="wpaic_settings[trust_proxy_ip]" value="1"
                                    <?php checked($settings['trust_proxy_ip'] ?? false); ?>>
                                <?php esc_html_e('Trust reverse proxy X-Forwarded-For header', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Enable if your site is behind a reverse proxy (Nginx, AWS ALB, etc.) that sets X-Forwarded-For. Uses the first public IP from the header for rate limiting.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description" style="color: #d63638;"><strong><?php esc_html_e('Security warning: Only enable this if ALL traffic passes through your trusted proxy. Otherwise attackers can forge this header.', 'rapls-ai-chatbot'); ?></strong></p>
                            <p class="description"><?php
                                echo wp_kses(
                                    __('To add trusted proxy IPs or CIDR ranges, use the <code>wpaic_trusted_proxies</code> filter in your theme or a custom plugin.<br>'
                                     . '<strong>Cloudflare example:</strong> 172.64.0.0/13, 104.16.0.0/13, 173.245.48.0/20, etc.<br>'
                                     . '<strong>AWS ALB example:</strong> Your VPC CIDR (e.g. 10.0.0.0/8)<br>'
                                     . '<strong>If misconfigured:</strong> Rate limiting applies to the proxy IP instead of real visitors, or attackers can bypass rate limits by forging the X-Forwarded-For header.<br>'
                                     . '<span style="color:#d63638;"><strong>Important:</strong> Cloudflare IP ranges change periodically. If you hardcode CIDRs, they may become stale and cause XFF to be ignored (rate limiting / IP detection will fall back to the proxy IP). Check <code>https://www.cloudflare.com/ips/</code> regularly and update your filter accordingly.</span><br>'
                                     . '<strong>Setup checklist:</strong><br>'
                                     . '1. Confirm REMOTE_ADDR shows your proxy IP (not the visitor IP) before enabling<br>'
                                     . '2. For Cloudflare: enable "Trust Cloudflare" above (uses CF-Connecting-IP, no CIDR needed)<br>'
                                     . '3. For other proxies: add their IPs/CIDRs via the <code>wpaic_trusted_proxies</code> filter<br>'
                                     . '4. Verify in Security Diagnostics that client IPs are detected correctly', 'rapls-ai-chatbot'),
                                    ['code' => [], 'br' => [], 'strong' => [], 'span' => ['style' => []]]
                                );
                            ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA Failure Mode', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="wpaic_settings[recaptcha_fail_mode]">
                                <option value="open" <?php selected($settings['recaptcha_fail_mode'] ?? 'open', 'open'); ?>><?php esc_html_e('Fail-open (allow requests)', 'rapls-ai-chatbot'); ?></option>
                                <option value="closed" <?php selected($settings['recaptcha_fail_mode'] ?? 'open', 'closed'); ?>><?php esc_html_e('Fail-closed (block requests)', 'rapls-ai-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Behavior when reCAPTCHA verification server is unreachable. Fail-open allows requests through (recommended for most sites). Fail-closed blocks requests for maximum security.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h2><?php esc_html_e('Security Diagnostics', 'rapls-ai-chatbot'); ?></h2>
                <p class="description"><?php esc_html_e('Current security configuration status (read-only).', 'rapls-ai-chatbot'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Allowed Origin Hosts', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            // Use the same function as runtime to guarantee display matches actual checks.
                            $rest_controller = new WPAIC_REST_Controller();
                            $diag_hosts = $rest_controller->get_allowed_origin_hosts();
                            ?>
                            <code><?php echo esc_html(implode(', ', $diag_hosts)); ?></code>
                            <?php if (empty($diag_hosts)) : ?>
                                <span style="color:#d63638;"><strong><?php esc_html_e('Warning: No allowed hosts detected. Origin/Referer checks will reject all requests.', 'rapls-ai-chatbot'); ?></strong></span>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('These hostnames are accepted for Origin/Referer checks and reCAPTCHA hostname validation (same source as runtime). Custom hosts can be added via the wpaic_allowed_origins filter.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Recent Bot Detections (past hour)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $bot_types = [
                                'honeypot_offl'  => __('Honeypot (Offline)', 'rapls-ai-chatbot'),
                                'timing_offl'    => __('Timing (Offline)', 'rapls-ai-chatbot'),
                                'future_ts_offl' => __('Future clock (Offline)', 'rapls-ai-chatbot'),
                                'honeypot_pub'   => __('Honeypot (Chat)', 'rapls-ai-chatbot'),
                                'timing_pub'     => __('Timing (Chat)', 'rapls-ai-chatbot'),
                                'future_ts_pub'  => __('Future clock (Chat)', 'rapls-ai-chatbot'),
                                'honeypot_lead'  => __('Honeypot (Lead)', 'rapls-ai-chatbot'),
                                'timing_lead'    => __('Timing (Lead)', 'rapls-ai-chatbot'),
                                'future_ts_lead' => __('Future clock (Lead)', 'rapls-ai-chatbot'),
                            ];
                            $has_detections = false;
                            $use_cache = wp_using_ext_object_cache();
                            foreach ($bot_types as $bkey => $blabel) {
                                $tkey = 'wpaic_bot_drop_' . $bkey;
                                $bcount = $use_cache
                                    ? (int) wp_cache_get($tkey, 'wpaic_bot')
                                    : (int) get_transient($tkey);
                                // future_ts counters are always exact; others are sampled 1-in-10
                                $is_future_ts = strpos($bkey, 'future_ts_') === 0;
                                $is_sampled = (!$use_cache && !$is_future_ts);
                                if ($is_sampled && $bcount > 0) {
                                    $bcount *= 10;
                                }
                                if ($bcount > 0) {
                                    $has_detections = true;
                                    $suffix = $is_sampled ? ' ' . esc_html__('(approx)', 'rapls-ai-chatbot') : '';
                                    echo '<span style="margin-right:16px;">' . esc_html($blabel) . ': <strong>' . esc_html($bcount) . '</strong>' . $suffix . '</span>';
                                }
                            }
                            if (!$has_detections) {
                                echo '<em>' . esc_html__('No bot activity detected in the past hour.', 'rapls-ai-chatbot') . '</em>';
                            }
                            ?>
                            <p class="description"><?php esc_html_e('Approximate count of requests blocked by bot detection in the past hour. High numbers may indicate your forms are being targeted.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('XFF Truncated (past hour)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $xff_key = 'wpaic_xff_truncated';
                            $xff_count = $use_cache
                                ? (int) wp_cache_get($xff_key, 'wpaic_bot')
                                : (int) get_transient($xff_key);
                            if (!$use_cache && $xff_count > 0) {
                                $xff_count *= 10;
                            }
                            if ($xff_count > 0) {
                                echo '<strong>' . esc_html($xff_count) . '</strong>';
                            } else {
                                echo '<em>' . esc_html__('None in the past hour.', 'rapls-ai-chatbot') . '</em>';
                            }
                            ?>
                            <p class="description"><?php esc_html_e('Number of oversized X-Forwarded-For headers truncated (past hour). High numbers may indicate a CDN/proxy chain issue or an attack. Check your trusted proxy configuration.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Recent Admin Failures (past 24h)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $diag_events = get_transient('wpaic_diag_events');
                            if (is_array($diag_events) && !empty($diag_events)) {
                                $recent = array_reverse($diag_events);
                                echo '<ul style="margin:0;">';
                                foreach ($recent as $evt) {
                                    $time_ago = human_time_diff($evt['time'], time());
                                    echo '<li><code>' . esc_html($evt['code']) . '</code> — '
                                         . /* translators: %s: time difference */ sprintf(esc_html__('%s ago', 'rapls-ai-chatbot'), esc_html($time_ago))
                                         . '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '<em>' . esc_html__('No recent failures recorded.', 'rapls-ai-chatbot') . '</em>';
                            }
                            ?>
                            <p class="description"><?php esc_html_e('Last 10 admin operation failure codes (no sensitive details). Helps diagnose API key testing, import, and other configuration issues.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Environment', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $env_items = [];
                            $env_items[] = 'PHP ' . PHP_VERSION;
                            $env_items[] = 'WP ' . get_bloginfo('version');
                            $env_items[] = wp_using_ext_object_cache()
                                ? __('Object cache: active', 'rapls-ai-chatbot')
                                : __('Object cache: not available (bot counters use DB sampling)', 'rapls-ai-chatbot');
                            if (!function_exists('dns_get_record')) {
                                $env_items[] = '<span style="color:#d63638;">' . esc_html__('dns_get_record: not available (IPv6 SSRF validation limited)', 'rapls-ai-chatbot') . '</span>';
                            }
                            echo wp_kses(implode(' &middot; ', $env_items), ['span' => ['style' => []]]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Compatibility Note', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <p class="description"><?php esc_html_e('If users cannot submit forms (offline messages, lead capture), check that your JS optimization plugin (e.g. Autoptimize, WP Rocket, LiteSpeed Cache) does not defer or exclude the chatbot scripts. Excluding the chatbot page from optimization usually resolves this.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h2><?php esc_html_e('Import/Export Settings', 'rapls-ai-chatbot'); ?></h2>
                <p class="description"><?php esc_html_e('Export or import all settings as a JSON file.', 'rapls-ai-chatbot'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Export', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <button type="button" id="wpaic-export-settings" class="button button-secondary">
                                <?php esc_html_e('Export Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <label style="margin-left: 15px;">
                                <input type="checkbox" id="wpaic-export-include-knowledge" checked>
                                <?php esc_html_e('Include knowledge data', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Download current settings as a JSON file.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Import', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="file" id="wpaic-import-file" accept=".json">
                            <button type="button" id="wpaic-import-settings" class="button button-secondary" style="margin-left: 10px;">
                                <?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Upload an exported JSON file to restore settings.', 'rapls-ai-chatbot'); ?></p>
                            <p id="wpaic-import-status"></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Reset Settings', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <button type="button" id="wpaic-reset-settings" class="button button-secondary" style="color: #a00;">
                                <?php esc_html_e('Reset Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Reset all settings to default values. API keys will also be deleted.', 'rapls-ai-chatbot'); ?></p>
                            <p id="wpaic-reset-status"></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div id="wpaic-global-submit">
            <?php submit_button(__('Save Settings', 'rapls-ai-chatbot')); ?>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var i18n = wpaicAdmin.i18n || {};

    // Export
    $('#wpaic-export-settings').on('click', function() {
        var $button = $(this);
        var includeKnowledge = $('#wpaic-export-include-knowledge').is(':checked');

        $button.prop('disabled', true).text(i18n.exporting || 'Exporting...');

        $.ajax({
            url: wpaicAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpaic_export_settings',
                nonce: wpaicAdmin.nonce,
                include_knowledge: includeKnowledge ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    // Download JSON file
                    var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'wpaic-settings-' + new Date().toISOString().slice(0,10) + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert((i18n.exportFailed || 'Export failed') + ': ' + response.data);
                }
            },
            error: function() {
                alert(i18n.exportFailed || 'Export failed.');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Export Settings', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Import
    $('#wpaic-import-settings').on('click', function() {
        var $button = $(this);
        var $status = $('#wpaic-import-status');
        var fileInput = $('#wpaic-import-file')[0];

        if (!fileInput.files.length) {
            $status.html('<span style="color: red;">' + (i18n.selectFile || 'Please select a file.') + '</span>');
            return;
        }

        var file = fileInput.files[0];
        if (!file.name.endsWith('.json')) {
            $status.html('<span style="color: red;">' + (i18n.invalidJson || 'Please select a JSON file.') + '</span>');
            return;
        }

        if (!confirm(i18n.confirmOverwrite || 'Current settings will be overwritten. Continue?')) {
            return;
        }

        $button.prop('disabled', true).text(i18n.importing || 'Importing...');
        $status.text('');

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var importData = JSON.parse(e.target.result);

                $.ajax({
                    url: wpaicAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'wpaic_import_settings',
                        nonce: wpaicAdmin.nonce,
                        import_data: JSON.stringify(importData)
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color: green;">' + response.data + '</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $status.html('<span style="color: red;">' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: red;">' + (i18n.importFailed || 'Import failed.') + '</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php echo esc_js(__('Import Settings', 'rapls-ai-chatbot')); ?>');
                    }
                });
            } catch (err) {
                $status.html('<span style="color: red;">' + (i18n.invalidJson || 'Invalid JSON file.') + '</span>');
                $button.prop('disabled', false).text('<?php echo esc_js(__('Import Settings', 'rapls-ai-chatbot')); ?>');
            }
        };
        reader.readAsText(file);
    });

    // Reset settings
    $('#wpaic-reset-settings').on('click', function() {
        var $button = $(this);
        var $status = $('#wpaic-reset-status');

        var input = prompt(i18n.resetConfirm || 'All settings will be reset. API keys will also be deleted.\n\nThis action cannot be undone.\n\nTo reset, type "reset":');

        if (input !== 'reset') {
            if (input !== null) {
                $status.html('<span style="color: red;">' + (i18n.resetTypeError || 'Please type "reset".') + '</span>');
            }
            return;
        }

        $button.prop('disabled', true).text(i18n.resetting || 'Resetting...');
        $status.text('');

        wpaicDestructiveAjax({
            data: {
                action: 'wpaic_reset_settings',
                nonce: wpaicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">' + response.data + '</span>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $status.html('<span style="color: red;">' + response.data + '</span>');
                }
                $button.prop('disabled', false).text('<?php echo esc_js(__('Reset Settings', 'rapls-ai-chatbot')); ?>');
            },
            fail: function() {
                $status.html('<span style="color: red;">' + (i18n.resetFailed || 'Reset failed.') + '</span>');
                $button.prop('disabled', false).text('<?php echo esc_js(__('Reset Settings', 'rapls-ai-chatbot')); ?>');
            },
            cancel: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Reset Settings', 'rapls-ai-chatbot')); ?>');
            }
        });
    });

    // Page exclusion - Add page
    $('#wpaic-add-excluded-page').on('click', function() {
        var $select = $('#wpaic-page-selector');
        var pageId = $select.val();
        var pageTitle = $select.find('option:selected').text();

        if (!pageId) {
            return;
        }

        // Create tag item
        var $item = $('<div class="wpaic-excluded-page-item" data-page-id="' + pageId + '" style="display: inline-flex; align-items: center; background: #f0f0f1; border-radius: 4px; padding: 5px 10px; margin: 3px 5px 3px 0;">' +
            '<span>' + pageTitle + '</span>' +
            '<input type="hidden" name="wpaic_settings[excluded_pages][]" value="' + pageId + '">' +
            '<button type="button" class="wpaic-remove-excluded-page" style="background: none; border: none; cursor: pointer; color: #a00; margin-left: 8px; font-size: 16px;">&times;</button>' +
            '</div>');

        $('#wpaic-excluded-pages-list').append($item);

        // Remove from select
        $select.find('option[value="' + pageId + '"]').remove();
        $select.val('');
    });

    // Page exclusion - Remove page
    $(document).on('click', '.wpaic-remove-excluded-page', function() {
        var $item = $(this).closest('.wpaic-excluded-page-item');
        var pageId = $item.data('page-id');
        var pageTitle = $item.find('span').text();

        // Add back to select
        $('#wpaic-page-selector').append('<option value="' + pageId + '">' + pageTitle + '</option>');

        // Remove item
        $item.remove();
    });

    // Reset field to default
    $(document).on('click', '.wpaic-reset-field', function() {
        var targetId = $(this).data('target');
        var defaultValue = $(this).data('default');
        var $target = $('#' + targetId);

        if ($target.length) {
            $target.val(defaultValue);
            // Flash effect to indicate change
            $target.css('background-color', '#fff9c4');
            setTimeout(function() {
                $target.css('background-color', '');
            }, 500);
        }
    });

    // Avatar image uploader
    var avatarFrame;
    $('#wpaic-upload-avatar').on('click', function(e) {
        e.preventDefault();

        if (avatarFrame) {
            avatarFrame.open();
            return;
        }

        avatarFrame = wp.media({
            title: '<?php echo esc_js(__('Select Avatar Image', 'rapls-ai-chatbot')); ?>',
            button: {
                text: '<?php echo esc_js(__('Use as Avatar', 'rapls-ai-chatbot')); ?>'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        avatarFrame.on('select', function() {
            var attachment = avatarFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

            $('#wpaic_bot_avatar').val(imageUrl);
            updateAvatarPreview(imageUrl);
        });

        avatarFrame.open();
    });

    // Reset avatar to emoji
    $('#wpaic-reset-avatar').on('click', function() {
        $('#wpaic_bot_avatar').val('🤖');
        updateAvatarPreview('🤖');
    });

    // Update avatar preview
    function updateAvatarPreview(value) {
        var $preview = $('.wpaic-avatar-preview');
        var isImage = /^(https?:\/\/|\/)/i.test(value) || /\.(jpg|jpeg|png|gif|svg|webp)$/i.test(value);

        if (isImage) {
            $preview.html('<img src="' + value + '" alt="Avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">');
        } else {
            $preview.html('<span style="font-size: 48px; line-height: 1;">' + value + '</span>');
        }
    }

    // Update preview on input change
    $('#wpaic_bot_avatar').on('input', function() {
        updateAvatarPreview($(this).val());
    });

    // Multimodal vision model filter
    var multimodalEnabled = <?php
        $pro_settings = $settings['pro_features'] ?? [];
        echo !empty($pro_settings['multimodal_enabled']) ? 'true' : 'false';
    ?>;

    function checkMultimodalModels() {
        if (!multimodalEnabled) {
            // Reset all options when multimodal is disabled
            $('#wpaic-openai-model, #wpaic-claude-model, #wpaic-gemini-model').each(function() {
                $(this).find('option').each(function() {
                    var $opt = $(this);
                    $opt.prop('disabled', false);
                    if ($opt.data('original-text')) {
                        $opt.text($opt.data('original-text'));
                    }
                });
                $(this).css('border-color', '');
                $(this).siblings('.wpaic-vision-warning').hide();
            });
            return;
        }

        var provider = $('[name="wpaic_settings[ai_provider]"]').val();
        var modelSelect = null;

        switch (provider) {
            case 'openai':
                modelSelect = $('#wpaic-openai-model');
                break;
            case 'claude':
                modelSelect = $('#wpaic-claude-model');
                break;
            case 'gemini':
                modelSelect = $('#wpaic-gemini-model');
                break;
        }

        if (!modelSelect || !modelSelect.length) {
            return;
        }

        // First, disable all non-vision models
        var firstVisionModel = null;
        modelSelect.find('option').each(function() {
            var $opt = $(this);
            var vision = $opt.data('vision');
            var isVision = (vision === 1 || vision === '1' || vision === true);

            if (!isVision) {
                $opt.prop('disabled', true);
                if (!$opt.data('original-text')) {
                    $opt.data('original-text', $opt.text());
                }
                $opt.text($opt.data('original-text') + ' (<?php esc_html_e('No vision support', 'rapls-ai-chatbot'); ?>)');
            } else {
                $opt.prop('disabled', false);
                if ($opt.data('original-text')) {
                    $opt.text($opt.data('original-text'));
                }
                if (!firstVisionModel) {
                    firstVisionModel = $opt.val();
                }
            }
        });

        // Check if currently selected model is vision-capable
        var selectedOption = modelSelect.find('option:selected');
        var selectedVision = selectedOption.data('vision');
        var isVisionModel = (selectedVision === 1 || selectedVision === '1' || selectedVision === true);
        var $warning = modelSelect.siblings('.wpaic-vision-warning');

        if (!isVisionModel) {
            // Auto-select first vision model
            if (firstVisionModel) {
                modelSelect.val(firstVisionModel);
            }
            $warning.show();
            modelSelect.css('border-color', '#d63638');
        } else {
            $warning.hide();
            modelSelect.css('border-color', '');
        }
    }

    // Check on page load
    checkMultimodalModels();

    // Check when provider changes
    $('[name="wpaic_settings[ai_provider]"]').on('change', function() {
        setTimeout(checkMultimodalModels, 100);
    });

    // Check when model changes
    $('#wpaic-openai-model, #wpaic-claude-model, #wpaic-gemini-model').on('change', checkMultimodalModels);

    // Prevent form submission with non-vision model when multimodal is enabled
    $('form').on('submit', function(e) {
        if (!multimodalEnabled) return true;

        var provider = $('[name="wpaic_settings[ai_provider]"]').val();
        var modelSelect = $('#wpaic-' + provider + '-model');

        if (modelSelect.length) {
            var selectedOption = modelSelect.find('option:selected');
            var vision = selectedOption.data('vision');
            var isVision = (vision === 1 || vision === '1' || vision === true);

            if (!isVision) {
                alert('<?php echo esc_js(__('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot')); ?>');
                e.preventDefault();
                return false;
            }
        }
        return true;
    });
});
</script>
