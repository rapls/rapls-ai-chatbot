<?php
/**
 * Settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables, not true globals
?>
<div class="wrap raplsaich-admin">
    <h1>
        <?php esc_html_e('AI Chatbot - Settings', 'rapls-ai-chatbot'); ?>
        <?php if (defined('RAPLSAICH_VERSION')) : ?>
        <span style="font-size:12px;font-weight:normal;color:#666;margin-left:8px;">
            v<?php echo esc_html(RAPLSAICH_VERSION); ?><?php if (defined('RAPLSAICH_BUILD') && RAPLSAICH_BUILD && strpos(RAPLSAICH_BUILD, 'Format') === false) : ?> (<?php echo esc_html(RAPLSAICH_BUILD); ?>)<?php endif; ?>
        </span>
        <?php endif; ?>
    </h1>

    <?php $is_pro_active = raplsaich_is_pro_active(); ?>
    <?php if (!$is_pro_active) : ?>
    <div class="raplsaich-pro-settings-banner">
        <span class="dashicons dashicons-star-filled"></span>
        <span><?php esc_html_e('Extend your AI chatbot with automation, analytics, and business-ready features.', 'rapls-ai-chatbot'); ?></span>
        <a href="https://raplsworks.com/plugins/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer" class="button">
            <?php esc_html_e('Learn More', 'rapls-ai-chatbot'); ?>
        </a>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('raplsaich_settings_group'); ?>

        <div class="raplsaich-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#tab-ai" class="nav-tab nav-tab-active"><?php esc_html_e('AI Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-chat" class="nav-tab"><?php esc_html_e('Chat Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-display" class="nav-tab"><?php esc_html_e('Display Settings', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-security" class="nav-tab"><?php esc_html_e('Security', 'rapls-ai-chatbot'); ?></a>
                <a href="#tab-data" class="nav-tab"><?php esc_html_e('Data Management', 'rapls-ai-chatbot'); ?></a>
            </nav>

            <!-- AI Settings -->
            <div id="tab-ai" class="tab-content active">
                <input type="hidden" name="raplsaich_settings[_settings_page]" value="1">
                <div class="raplsaich-tab-header">
                    <h2><?php esc_html_e('AI Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="raplsaich-reset-tab-btn" data-tab="tab-ai">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Provider', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="raplsaich_settings[ai_provider]" id="ai_provider">
                                <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI (ChatGPT)</option>
                                <option value="claude" <?php selected($settings['ai_provider'] ?? '', 'claude'); ?>>Anthropic (Claude)</option>
                                <option value="gemini" <?php selected($settings['ai_provider'] ?? '', 'gemini'); ?>>Google (Gemini)</option>
                                <option value="openrouter" <?php selected($settings['ai_provider'] ?? '', 'openrouter'); ?>>OpenRouter</option>
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
                                <div class="raplsaich-api-key-wrapper">
                                    <input type="password" name="raplsaich_settings[openai_api_key]"
                                           id="openai_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['openai_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="raplsaich_settings[delete_openai_api_key]" id="delete_openai_api_key" value="0">
                                    <button type="button" class="button raplsaich-test-api" data-provider="openai"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['openai_api_key'])): ?>
                                        <button type="button" class="button raplsaich-clear-api-key" data-target="openai_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="raplsaich-key-status raplsaich-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="raplsaich-key-status raplsaich-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Enter your OpenAI API key.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $openai_vision_models = $openai_provider->get_vision_models(); ?>
                                <select name="raplsaich_settings[openai_model]" id="raplsaich-openai-model"
                                    data-initial-value="<?php echo esc_attr($settings['openai_model'] ?? 'gpt-4o-mini'); ?>">
                                    <?php foreach ($openai_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $openai_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['openai_model'] ?? 'gpt-4o-mini', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button raplsaich-refresh-models" data-provider="openai" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description raplsaich-vision-warning" style="display: none; color: #d63638;">
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
                                <div class="raplsaich-api-key-wrapper">
                                    <input type="password" name="raplsaich_settings[claude_api_key]"
                                           id="claude_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['claude_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="raplsaich_settings[delete_claude_api_key]" id="delete_claude_api_key" value="0">
                                    <button type="button" class="button raplsaich-test-api" data-provider="claude"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['claude_api_key'])): ?>
                                        <button type="button" class="button raplsaich-clear-api-key" data-target="claude_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="raplsaich-key-status raplsaich-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="raplsaich-key-status raplsaich-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Enter your Anthropic API key.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $claude_vision_models = $claude_provider->get_vision_models(); ?>
                                <select name="raplsaich_settings[claude_model]" id="raplsaich-claude-model"
                                    data-initial-value="<?php echo esc_attr($settings['claude_model'] ?? 'claude-haiku-4-5-20251001'); ?>">
                                    <?php foreach ($claude_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $claude_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['claude_model'] ?? 'claude-haiku-4-5-20251001', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button raplsaich-refresh-models" data-provider="claude" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description raplsaich-vision-warning" style="display: none; color: #d63638;">
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
                                <div class="raplsaich-api-key-wrapper">
                                    <input type="password" name="raplsaich_settings[gemini_api_key]"
                                           id="gemini_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['gemini_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="raplsaich_settings[delete_gemini_api_key]" id="delete_gemini_api_key" value="0">
                                    <button type="button" class="button raplsaich-test-api" data-provider="gemini"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['gemini_api_key'])): ?>
                                        <button type="button" class="button raplsaich-clear-api-key" data-target="gemini_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="raplsaich-key-status raplsaich-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="raplsaich-key-status raplsaich-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Get your API key from Google AI Studio.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <?php $gemini_vision_models = $gemini_provider->get_vision_models(); ?>
                                <select name="raplsaich_settings[gemini_model]" id="raplsaich-gemini-model"
                                    data-initial-value="<?php echo esc_attr($settings['gemini_model'] ?? 'gemini-2.0-flash'); ?>">
                                    <?php foreach ($gemini_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            data-vision="<?php echo esc_attr(in_array($value, $gemini_vision_models, true) ? '1' : '0'); ?>"
                                            <?php selected($settings['gemini_model'] ?? 'gemini-2.0-flash', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button raplsaich-refresh-models" data-provider="gemini" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description raplsaich-vision-warning" style="display: none; color: #d63638;">
                                    <?php esc_html_e('Multimodal is enabled. Please select a vision-capable model.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- OpenRouter Settings -->
                <div id="openrouter-settings" class="provider-settings">
                    <h3><?php esc_html_e('OpenRouter Settings', 'rapls-ai-chatbot'); ?></h3>
                    <p class="description" style="margin-bottom: 12px;">
                        <?php esc_html_e('Access 100+ AI models with a single API key. Get your key from openrouter.ai.', 'rapls-ai-chatbot'); ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <div class="raplsaich-api-key-wrapper">
                                    <input type="password" name="raplsaich_settings[openrouter_api_key]"
                                           id="openrouter_api_key"
                                           value=""
                                           class="regular-text" autocomplete="off"
                                           placeholder="<?php echo !empty($settings['openrouter_api_key']) ? esc_attr__('••••••••(configured)', 'rapls-ai-chatbot') : ''; ?>">
                                    <input type="hidden" name="raplsaich_settings[delete_openrouter_api_key]" id="delete_openrouter_api_key" value="0">
                                    <button type="button" class="button raplsaich-test-api" data-provider="openrouter"><?php esc_html_e('Test Connection', 'rapls-ai-chatbot'); ?></button>
                                    <?php if (!empty($settings['openrouter_api_key'])): ?>
                                        <button type="button" class="button raplsaich-clear-api-key" data-target="openrouter_api_key"><?php esc_html_e('Remove', 'rapls-ai-chatbot'); ?></button>
                                        <span class="raplsaich-key-status raplsaich-key-set"><?php esc_html_e('Configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php else: ?>
                                        <span class="raplsaich-key-status raplsaich-key-empty"><?php esc_html_e('Not configured', 'rapls-ai-chatbot'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Get your API key from openrouter.ai.', 'rapls-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model', 'rapls-ai-chatbot'); ?></th>
                            <td>
                                <select name="raplsaich_settings[openrouter_model]" id="raplsaich-openrouter-model"
                                    data-initial-value="<?php echo esc_attr($settings['openrouter_model'] ?? 'openrouter/auto'); ?>">
                                    <?php foreach ($openrouter_provider->get_available_models() as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>"
                                            <?php selected($settings['openrouter_model'] ?? 'openrouter/auto', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button raplsaich-refresh-models" data-provider="openrouter" title="<?php esc_attr_e('Refresh model list', 'rapls-ai-chatbot'); ?>">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                </button>
                                <p class="description">
                                    <?php esc_html_e('Click the refresh button to fetch all available models from OpenRouter.', 'rapls-ai-chatbot'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Embedding Settings -->
                <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                    <?php esc_html_e('Vector Embedding (RAG)', 'rapls-ai-chatbot'); ?>
                </h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Vector Search', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[embedding_enabled]" value="1"
                                    <?php checked($settings['embedding_enabled'] ?? false); ?>>
                                <?php esc_html_e('Enable vector embedding search', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Uses AI embeddings for semantic search in addition to keyword matching. Improves search accuracy, especially for Japanese and other non-English languages.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Embedding Provider', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="raplsaich_settings[embedding_provider]" id="embedding_provider">
                                <?php foreach (RAPLSAICH_Embedding_Generator::get_available_providers() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['embedding_provider'] ?? 'auto', $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                            $current_provider = $settings['ai_provider'] ?? 'openai';
                            if (in_array($current_provider, ['claude', 'openrouter'], true)) :
                            ?>
                            <p class="description" style="color: #d63638;">
                                <?php esc_html_e('Note: Claude and OpenRouter do not provide embedding APIs. An OpenAI or Gemini API key is required for embeddings.', 'rapls-ai-chatbot'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    $emb_gen = new RAPLSAICH_Embedding_Generator($settings);
                    if ($emb_gen->is_configured()) :
                        $idx_stats = RAPLSAICH_Content_Index::get_embedding_stats();
                    ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <span style="color: #00a32a;">&#10003;</span>
                            <?php
                            /* translators: 1: embedding provider, 2: model name */
                            printf(esc_html__('Active: %1$s / %2$s', 'rapls-ai-chatbot'), esc_html(ucfirst($emb_gen->get_provider())), esc_html($emb_gen->get_model()));
                            ?>
                            <br>
                            <?php
                            /* translators: 1: embedded count, 2: total count */
                            printf(esc_html__('%1$s / %2$s chunks embedded', 'rapls-ai-chatbot'),
                                esc_html(number_format($idx_stats['embedded_chunks'])),
                                esc_html(number_format($idx_stats['total_chunks']))
                            );
                            ?>
                        </td>
                    </tr>
                    <?php elseif (!empty($settings['embedding_enabled'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Status', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <span style="color: #d63638;">&#10007;</span>
                            <?php esc_html_e('An OpenAI or Gemini API key is required for vector embedding.', 'rapls-ai-chatbot'); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <!-- MCP Settings -->
                <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                    <?php esc_html_e('MCP (Model Context Protocol)', 'rapls-ai-chatbot'); ?>
                </h3>
                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e('Allow external AI agents (Claude Desktop, Cursor, etc.) to access your knowledge base and conversations via MCP.', 'rapls-ai-chatbot'); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('MCP Server', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[mcp_enabled]" value="1"
                                    <?php checked($settings['mcp_enabled'] ?? false); ?>>
                                <?php esc_html_e('Enable MCP server', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, external AI agents can connect to this site using the MCP protocol.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('MCP API Key', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div id="raplsaich-mcp-key-section">
                                <?php if (!empty($settings['mcp_api_key_hash'])) : ?>
                                    <code id="raplsaich-mcp-key-display">••••••••••••••••••••</code>
                                <?php else : ?>
                                    <span id="raplsaich-mcp-key-display" style="color: #d63638;">
                                        <?php esc_html_e('No API key generated yet.', 'rapls-ai-chatbot'); ?>
                                    </span>
                                <?php endif; ?>
                                <br><br>
                                <button type="button" class="button" id="raplsaich-mcp-generate-key">
                                    <?php echo !empty($settings['mcp_api_key_hash'])
                                        ? esc_html__('Regenerate Key', 'rapls-ai-chatbot')
                                        : esc_html__('Generate Key', 'rapls-ai-chatbot'); ?>
                                </button>
                                <button type="button" class="button" id="raplsaich-mcp-copy-key" style="display: none;">
                                    <?php esc_html_e('Copy Key', 'rapls-ai-chatbot'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php esc_html_e('The API key is shown only once when generated. Store it securely for use in your MCP client configuration.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('MCP Endpoint', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <code id="raplsaich-mcp-endpoint"><?php echo esc_url(rest_url('rapls-ai-chatbot/v1/mcp')); ?></code>
                            <button type="button" class="button button-small" id="raplsaich-mcp-copy-endpoint">
                                <?php esc_html_e('Copy', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e('Use this URL in your MCP client (Claude Desktop, Cursor, etc.) configuration.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- MCP Client Configuration Example -->
                <div style="margin-top: 10px; padding: 12px 15px; background: #f0f0f1; border-left: 4px solid #2271b1; border-radius: 2px;">
                    <strong><?php esc_html_e('Claude Desktop Configuration Example:', 'rapls-ai-chatbot'); ?></strong>
                    <pre style="margin: 8px 0 0; padding: 10px; background: #fff; border: 1px solid #c3c4c7; border-radius: 2px; font-size: 12px; overflow-x: auto;">{
  "mcpServers": {
    "<?php echo esc_js(sanitize_title(get_bloginfo('name'))); ?>": {
      "url": "<?php echo esc_url(rest_url('rapls-ai-chatbot/v1/mcp')); ?>",
      "headers": {
        "Authorization": "Bearer YOUR_MCP_API_KEY"
      }
    }
  }
}</pre>
                </div>

                <?php
                wp_localize_script('raplsaich-admin', 'raplsaichMcp', [
                    'nonce'        => wp_create_nonce('raplsaich_generate_mcp_key'),
                    'confirmGen'   => __('Generate a new MCP API key? The previous key will be invalidated.', 'rapls-ai-chatbot'),
                    'generating'   => __('Generating...', 'rapls-ai-chatbot'),
                    'regenerate'   => __('Regenerate Key', 'rapls-ai-chatbot'),
                    'errorGen'     => __('Error generating key.', 'rapls-ai-chatbot'),
                    'requestFail'  => __('Request failed.', 'rapls-ai-chatbot'),
                    'copied'       => __('Copied!', 'rapls-ai-chatbot'),
                    'copyKey'      => __('Copy Key', 'rapls-ai-chatbot'),
                    'copy'         => __('Copy', 'rapls-ai-chatbot'),
                ]);
                wp_add_inline_script('raplsaich-admin', '(function(){' .
                    'var L=raplsaichMcp,gB=document.getElementById("raplsaich-mcp-generate-key"),' .
                    'cK=document.getElementById("raplsaich-mcp-copy-key"),' .
                    'cE=document.getElementById("raplsaich-mcp-copy-endpoint"),' .
                    'kD=document.getElementById("raplsaich-mcp-key-display");' .
                    'function cp(t,b,o){if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(function(){b.textContent=L.copied;setTimeout(function(){b.textContent=o},2000)})}else{var a=document.createElement("textarea");a.value=t;a.style.cssText="position:fixed;opacity:0";document.body.appendChild(a);a.select();try{document.execCommand("copy");b.textContent=L.copied;setTimeout(function(){b.textContent=o},2000)}catch(e){}document.body.removeChild(a)}}' .
                    'if(gB){gB.addEventListener("click",function(){if(!confirm(L.confirmGen))return;gB.disabled=true;gB.textContent=L.generating;jQuery.post(ajaxurl,{action:"raplsaich_generate_mcp_key",_wpnonce:L.nonce},function(r){gB.disabled=false;gB.textContent=L.regenerate;if(r.success){var c=document.createElement("code");c.style.cssText="user-select:all;cursor:pointer;word-break:break-all";c.textContent=r.data.api_key;kD.textContent="";kD.appendChild(c);kD.style.color="";cK.style.display="inline-block";cK.dataset.key=r.data.api_key}else{alert(r.data||L.errorGen)}}).fail(function(){gB.disabled=false;gB.textContent=L.regenerate;alert(L.requestFail)})})}' .
                    'if(cK){cK.addEventListener("click",function(){var k=this.dataset.key;if(k)cp(k,cK,L.copyKey)})}' .
                    'if(cE){cE.addEventListener("click",function(){var e=document.getElementById("raplsaich-mcp-endpoint").textContent;cp(e,cE,L.copy)})}' .
                    '})();');
                ?>

            </div>

            <!-- Chat Settings -->
            <div id="tab-chat" class="tab-content">
                <div class="raplsaich-tab-header">
                    <h2><?php esc_html_e('Chat Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="raplsaich-reset-tab-btn" data-tab="tab-chat">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Bot Name', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="raplsaich_settings[bot_name]" id="raplsaich_bot_name"
                                   value="<?php echo esc_attr($settings['bot_name'] ?? 'Assistant'); ?>"
                                   class="regular-text">
                            <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_bot_name" data-default="Assistant">
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
                            <div class="raplsaich-avatar-setting">
                                <div class="raplsaich-avatar-preview" style="margin-bottom: 10px;">
                                    <?php if ($is_image): ?>
                                        <img src="<?php echo esc_url($bot_avatar_val); ?>" alt="Avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">
                                    <?php else: ?>
                                        <span style="font-size: 48px; line-height: 1;"><?php echo esc_html($bot_avatar_val); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input type="text" name="raplsaich_settings[bot_avatar]" id="raplsaich_bot_avatar"
                                           value="<?php echo esc_attr($bot_avatar_val); ?>"
                                           class="regular-text" placeholder="🤖">
                                    <button type="button" class="button" id="raplsaich-upload-avatar">
                                        <?php esc_html_e('Select Image', 'rapls-ai-chatbot'); ?>
                                    </button>
                                    <button type="button" class="button" id="raplsaich-reset-avatar">
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
                            <textarea name="raplsaich_settings[welcome_message]" id="raplsaich_welcome_message" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['welcome_message'] ?? 'Hello! How can I help you today?');
                            ?></textarea>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_welcome_message" data-default="Hello! How can I help you today?">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>

                            <?php
                            $welcome_messages = $settings['welcome_messages'] ?? [];
                            $welcome_langs = [
                                'en' => ['English', 'Hello! How can I help you today?'],
                                'ja' => ['日本語', 'こんにちは！何かお手伝いできることはありますか？'],
                                'zh' => ['中文', '您好！有什么可以帮助您的吗？'],
                                'ko' => ['한국어', '안녕하세요! 무엇을 도와드릴까요?'],
                                'es' => ['Español', '¡Hola! ¿En qué puedo ayudarte hoy?'],
                                'fr' => ['Français', 'Bonjour ! Comment puis-je vous aider aujourd\'hui ?'],
                                'de' => ['Deutsch', 'Hallo! Wie kann ich Ihnen heute helfen?'],
                                'pt' => ['Português', 'Olá! Como posso ajudá-lo hoje?'],
                                'it' => ['Italiano', 'Ciao! Come posso aiutarti oggi?'],
                                'ru' => ['Русский', 'Здравствуйте! Чем могу помочь?'],
                                'ar' => ['العربية', 'مرحبا! كيف يمكنني مساعدتك اليوم؟'],
                                'th' => ['ไทย', 'สวัสดีครับ! มีอะไรให้ช่วยไหมครับ?'],
                                'vi' => ['Tiếng Việt', 'Xin chào! Tôi có thể giúp gì cho bạn?'],
                            ];
                            ?>
                            <div id="raplsaich-per-language-welcome" style="display: <?php echo esc_attr(($settings['response_language'] ?? '') === 'auto' ? 'block' : 'none'); ?>; margin-top: 12px;">
                                <details>
                                    <summary style="cursor: pointer; font-weight: 600; margin-bottom: 8px;">
                                        <?php esc_html_e('Per-Language Welcome Messages', 'rapls-ai-chatbot'); ?>
                                    </summary>
                                    <div class="notice notice-info inline" style="margin: 8px 0 12px; padding: 8px 12px;">
                                        <p style="margin: 0;">
                                            <?php esc_html_e('Priority order:', 'rapls-ai-chatbot'); ?>
                                            <strong>1.</strong> <?php esc_html_e('Per-language message (below)', 'rapls-ai-chatbot'); ?>
                                            &rarr; <strong>2.</strong> <?php esc_html_e('Welcome Message (above)', 'rapls-ai-chatbot'); ?>
                                            &rarr; <strong>3.</strong> <?php esc_html_e('Built-in default translation', 'rapls-ai-chatbot'); ?>
                                        </p>
                                        <p class="description" style="margin: 4px 0 0;">
                                            <?php esc_html_e('If a per-language message is set, it is used. Otherwise, the Welcome Message above is shown. Built-in defaults are only used when the Welcome Message is unchanged.', 'rapls-ai-chatbot'); ?>
                                        </p>
                                    </div>
                                    <?php foreach ($welcome_langs as $lang_code => $lang_info) : ?>
                                        <div style="margin-bottom: 8px;">
                                            <label for="raplsaich_welcome_msg_<?php echo esc_attr($lang_code); ?>">
                                                <strong><?php echo esc_html($lang_info[0]); ?></strong> (<?php echo esc_html($lang_code); ?>)
                                            </label>
                                            <div style="display: flex; gap: 6px; align-items: flex-start;">
                                                <textarea
                                                    name="raplsaich_settings[welcome_messages][<?php echo esc_attr($lang_code); ?>]"
                                                    id="raplsaich_welcome_msg_<?php echo esc_attr($lang_code); ?>"
                                                    rows="2"
                                                    class="large-text"
                                                    placeholder="<?php echo esc_attr($lang_info[1]); ?>"
                                                ><?php echo esc_textarea($welcome_messages[$lang_code] ?? ''); ?></textarea>
                                                <button type="button" class="button button-small raplsaich-reset-welcome-lang" data-target="raplsaich_welcome_msg_<?php echo esc_attr($lang_code); ?>" style="flex-shrink: 0; margin-top: 4px;" title="<?php esc_attr_e('Clear', 'rapls-ai-chatbot'); ?>">&times;</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <p>
                                        <button type="button" class="button button-small" id="raplsaich-reset-all-welcome-langs">
                                            <?php esc_html_e('Clear All', 'rapls-ai-chatbot'); ?>
                                        </button>
                                    </p>
                                </details>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('System Prompt', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_system_prompt = "You are a knowledgeable assistant for this website. Follow these rules:\n\n1. ACCURACY: When reference information is provided, treat it as the primary and most reliable source. Base your answers on this information first.\n2. HONESTY: If the provided information does not cover the user's question, clearly state that you don't have specific information about it, then offer general guidance if appropriate.\n3. NO FABRICATION: Never invent facts, URLs, prices, dates, or specific details that are not in the provided reference information.\n4. CONCISENESS: Provide clear, focused answers. Avoid unnecessary repetition or filler.\n5. LANGUAGE: Always respond in the same language the user writes in.\n6. TONE: Be professional, friendly, and helpful.";
                            ?>
                            <textarea name="raplsaich_settings[system_prompt]" id="raplsaich_system_prompt" rows="10" class="large-text"><?php
                                echo esc_textarea($settings['system_prompt'] ?? $default_system_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('A prompt that defines the AI behavior.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_system_prompt" data-default="<?php echo esc_attr($default_system_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Response Language', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="raplsaich_settings[response_language]" id="raplsaich_response_language">
                                <option value="" <?php selected($settings['response_language'] ?? '', ''); ?>><?php esc_html_e('Site language', 'rapls-ai-chatbot'); ?> (<?php echo esc_html(get_locale()); ?>)</option>
                                <option value="auto" <?php selected($settings['response_language'] ?? '', 'auto'); ?>><?php esc_html_e('Auto-detect (match user language)', 'rapls-ai-chatbot'); ?></option>
                                <option value="en" <?php selected($settings['response_language'] ?? '', 'en'); ?>>English</option>
                                <option value="ja" <?php selected($settings['response_language'] ?? '', 'ja'); ?>>日本語</option>
                                <option value="zh" <?php selected($settings['response_language'] ?? '', 'zh'); ?>>中文</option>
                                <option value="ko" <?php selected($settings['response_language'] ?? '', 'ko'); ?>>한국어</option>
                                <option value="es" <?php selected($settings['response_language'] ?? '', 'es'); ?>>Español</option>
                                <option value="fr" <?php selected($settings['response_language'] ?? '', 'fr'); ?>>Français</option>
                                <option value="de" <?php selected($settings['response_language'] ?? '', 'de'); ?>>Deutsch</option>
                                <option value="pt" <?php selected($settings['response_language'] ?? '', 'pt'); ?>>Português</option>
                                <option value="it" <?php selected($settings['response_language'] ?? '', 'it'); ?>>Italiano</option>
                                <option value="ru" <?php selected($settings['response_language'] ?? '', 'ru'); ?>>Русский</option>
                                <option value="ar" <?php selected($settings['response_language'] ?? '', 'ar'); ?>>العربية</option>
                                <option value="th" <?php selected($settings['response_language'] ?? '', 'th'); ?>>ไทย</option>
                                <option value="vi" <?php selected($settings['response_language'] ?? '', 'vi'); ?>>Tiếng Việt</option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose the language for AI responses. "Auto-detect" will respond in the same language as the user\'s message.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="raplsaich_message_history_count"><?php esc_html_e('Message History Count', 'rapls-ai-chatbot'); ?></label></th>
                        <td>
                            <input type="number" name="raplsaich_settings[message_history_count]" id="raplsaich_message_history_count"
                                value="<?php echo esc_attr($settings['message_history_count'] ?? 10); ?>"
                                min="1" max="50" class="small-text">
                            <p class="description"><?php esc_html_e('Number of previous messages sent as context to the AI. Higher values give more context but increase token usage.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Feedback Buttons', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[show_feedback_buttons]" value="1"
                                    <?php checked($settings['show_feedback_buttons'] ?? true); ?>>
                                <?php esc_html_e('Show feedback buttons (👍👎) on bot messages', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Allow users to rate bot responses with 👍👎. Feedback is used to improve AI response quality.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Preset Question Buttons', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[preset_questions_enabled]" value="1"
                                    <?php checked($settings['preset_questions_enabled'] ?? false); ?>>
                                <?php esc_html_e('Show preset question buttons under the welcome message', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Suggest common questions as one-tap buttons so visitors can start a conversation without typing. Buttons disappear once the user sends their first message. Up to 10 entries.', 'rapls-ai-chatbot'); ?>
                            </p>
                            <?php
                            $preset_rows = $settings['preset_questions'] ?? [];
                            if (empty($preset_rows)) {
                                $preset_rows = [['label' => '', 'question' => '']];
                            }
                            ?>
                            <table class="raplsaich-presets-table" style="margin-top: 10px; border-collapse: collapse; width: 100%; max-width: 900px;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 4px 8px; font-weight: 600; width: 200px;"><?php esc_html_e('Preset button label', 'rapls-ai-chatbot'); ?></th>
                                        <th style="text-align: left; padding: 4px 8px; font-weight: 600;"><?php esc_html_e('Question sent to the bot', 'rapls-ai-chatbot'); ?></th>
                                        <th style="width: 32px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="raplsaich-presets-body">
                                    <?php foreach ($preset_rows as $row): ?>
                                        <tr class="raplsaich-presets-row">
                                            <td style="padding: 2px 8px; vertical-align: top;">
                                                <input type="text" name="raplsaich_settings[preset_questions][label][]"
                                                    value="<?php echo esc_attr($row['label'] ?? ''); ?>"
                                                    maxlength="40"
                                                    placeholder="<?php esc_attr_e('e.g. Pricing', 'rapls-ai-chatbot'); ?>"
                                                    class="regular-text"
                                                    style="width: 100%;">
                                            </td>
                                            <td style="padding: 2px 8px; vertical-align: top;">
                                                <textarea name="raplsaich_settings[preset_questions][question][]"
                                                    rows="2"
                                                    maxlength="200"
                                                    placeholder="<?php esc_attr_e('e.g. Could you tell me about the pricing plans?', 'rapls-ai-chatbot'); ?>"
                                                    class="large-text"
                                                    style="width: 100%; resize: vertical; min-height: 52px;"><?php echo esc_textarea($row['question'] ?? ''); ?></textarea>
                                            </td>
                                            <td style="padding: 2px 8px; vertical-align: top;">
                                                <button type="button" class="button button-small raplsaich-presets-remove" aria-label="<?php esc_attr_e('Remove this row', 'rapls-ai-chatbot'); ?>">&times;</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="margin-top: 8px;">
                                <button type="button" class="button" id="raplsaich-presets-add">
                                    + <?php esc_html_e('Add a preset', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                            <script>
                            (function() {
                                var maxRows = 10;
                                document.getElementById('raplsaich-presets-add').addEventListener('click', function() {
                                    var body = document.getElementById('raplsaich-presets-body');
                                    if (body.querySelectorAll('.raplsaich-presets-row').length >= maxRows) {
                                        return;
                                    }
                                    var tr = document.createElement('tr');
                                    tr.className = 'raplsaich-presets-row';
                                    tr.innerHTML =
                                        '<td style="padding: 2px 8px; vertical-align: top;"><input type="text" name="raplsaich_settings[preset_questions][label][]" maxlength="40" class="regular-text" style="width: 100%;"></td>' +
                                        '<td style="padding: 2px 8px; vertical-align: top;"><textarea name="raplsaich_settings[preset_questions][question][]" rows="2" maxlength="200" class="large-text" style="width: 100%; resize: vertical; min-height: 52px;"></textarea></td>' +
                                        '<td style="padding: 2px 8px; vertical-align: top;"><button type="button" class="button button-small raplsaich-presets-remove">&times;</button></td>';
                                    body.appendChild(tr);
                                });
                                document.getElementById('raplsaich-presets-body').addEventListener('click', function(e) {
                                    if (!e.target.classList.contains('raplsaich-presets-remove')) return;
                                    var rows = this.querySelectorAll('.raplsaich-presets-row');
                                    if (rows.length <= 1) {
                                        // Keep one empty row visible.
                                        var fields = e.target.closest('tr').querySelectorAll('input, textarea');
                                        fields.forEach(function(f) { f.value = ''; });
                                        return;
                                    }
                                    e.target.closest('tr').remove();
                                });
                            })();
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API Quota Error Message', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="raplsaich_settings[quota_error_message]"
                                   id="raplsaich_quota_error_message"
                                   value="<?php echo esc_attr($settings['quota_error_message'] ?? 'Currently recharging. Please try again later.'); ?>"
                                   class="large-text">
                            <p class="description"><?php esc_html_e('Message displayed when the API quota is exceeded or billing issue occurs.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_quota_error_message" data-default="Currently recharging. Please try again later.">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Max Tokens', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="raplsaich_settings[max_tokens]" id="raplsaich_max_tokens"
                                   value="<?php echo esc_attr($settings['max_tokens'] ?? 1000); ?>"
                                   min="100" max="16384" class="small-text">
                            <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_max_tokens" data-default="1000">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                            <?php
                            $gpt5_info = RAPLSAICH_OpenAI_Provider::get_gpt5_effective_tokens((int) ($settings['max_tokens'] ?? 1000));
                            ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: multiplier value, 2: effective token limit */
                                    esc_html__('For GPT-5 and reasoning models, this value is automatically multiplied (current filter value: x%1$d, approximate effective limit: %2$s tokens, recommended: x2-4) to account for internal reasoning tokens. Higher multipliers improve response completeness but increase API costs. Adjust via the raplsaich_gpt5_token_multiplier filter. The actual value may differ if the filter is context-dependent.', 'rapls-ai-chatbot'),
                                    (int) $gpt5_info['multiplier'],
                                    esc_html(number_format_i18n($gpt5_info['tokens']))
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Temperature', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="raplsaich_settings[temperature]" id="raplsaich_temperature"
                                   value="<?php echo esc_attr($settings['temperature'] ?? 0.7); ?>"
                                   min="0" max="2" step="0.1" class="small-text">
                            <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_temperature" data-default="0.7">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Closer to 0 is more deterministic, closer to 2 is more random.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Web Search', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[web_search_enabled]" value="1"
                                    <?php checked(!empty($settings['web_search_enabled'])); ?>>
                                <?php esc_html_e('Enable Web Search', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When the knowledge base does not contain a sufficient answer, the AI will automatically search the web in real time.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description"><strong><?php esc_html_e('Note: Additional charges may apply depending on the provider.', 'rapls-ai-chatbot'); ?></strong></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3>
                    <label>
                        <input type="checkbox" id="raplsaich_advanced_context_toggle" class="raplsaich-advanced-toggle" data-target="raplsaich-advanced-context-section">
                        <?php esc_html_e('Context Prompts (Advanced)', 'rapls-ai-chatbot'); ?>
                    </label>
                </h3>
                <p class="description"><?php esc_html_e('These prompts are appended to the system prompt when knowledge base or site learning data is used. Use {context} as a placeholder for the actual content.', 'rapls-ai-chatbot'); ?></p>

                <div id="raplsaich-advanced-context-section" class="raplsaich-advanced-section raplsaich-advanced-disabled">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Knowledge Base (Exact Match)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_exact_match = "=== STRICT INSTRUCTIONS ===\nAn EXACT MATCH has been found for the user's question.\nYou MUST:\n1. Use ONLY the Answer provided below\n2. DO NOT add any information not in this Answer\n3. DO NOT combine with other sources\n4. Respond naturally using this Answer's content\n\n=== ANSWER TO USE ===\n{context}\n=== END ===";
                            ?>
                            <textarea name="raplsaich_settings[knowledge_exact_prompt]" id="raplsaich_knowledge_exact_prompt" rows="8" class="large-text"><?php
                                echo esc_textarea($settings['knowledge_exact_prompt'] ?? $default_exact_match);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when an exact Q&A match is found in the knowledge base.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_knowledge_exact_prompt" data-default="<?php echo esc_attr($default_exact_match); ?>">
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
                            <textarea name="raplsaich_settings[knowledge_qa_prompt]" id="raplsaich_knowledge_qa_prompt" rows="10" class="large-text"><?php
                                echo esc_textarea($settings['knowledge_qa_prompt'] ?? $default_qa_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when Q&A format knowledge is found (but not exact match).', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_knowledge_qa_prompt" data-default="<?php echo esc_attr($default_qa_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Learning Context', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_site_prompt = "[IMPORTANT: Reference Information]\nBelow is reference information from this site's knowledge base. You MUST use this as the primary source when answering.\n- Search the ENTIRE reference information thoroughly before concluding that no relevant data exists.\n- The user's wording may differ from the reference text (e.g. \"料金プラン\" vs \"料金体系\", \"price\" vs \"pricing\"). Match by MEANING, not exact keywords.\n- If ANY part of the reference information is relevant to the user's question, use it to answer.\n- Only say you don't have the information if, after careful review, absolutely nothing in the reference is related.\n\n{context}";
                            ?>
                            <textarea name="raplsaich_settings[site_context_prompt]" id="raplsaich_site_context_prompt" rows="6" class="large-text"><?php
                                echo esc_textarea($settings['site_context_prompt'] ?? $default_site_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used when site learning content is provided as context.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_site_context_prompt" data-default="<?php echo esc_attr($default_site_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                </table>
                </div><!-- /.raplsaich-advanced-context-section -->

                <hr style="margin: 30px 0;">

                <h3>
                    <label>
                        <input type="checkbox" id="raplsaich_advanced_feature_toggle" class="raplsaich-advanced-toggle" data-target="raplsaich-advanced-feature-section">
                        <?php esc_html_e('Feature Prompts (Advanced)', 'rapls-ai-chatbot'); ?>
                    </label>
                </h3>
                <p class="description"><?php esc_html_e('These prompts control how AI behaves for specific features.', 'rapls-ai-chatbot'); ?></p>

                <div id="raplsaich-advanced-feature-section" class="raplsaich-advanced-section raplsaich-advanced-disabled">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Regenerate Response Instruction', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $default_regenerate_prompt = '[REGENERATION REQUEST #{variation_number}]: The user wants a DIFFERENT answer. FORBIDDEN: Do not start with "{forbidden_start}". {style}. Create a completely new response with different wording. IMPORTANT: Do NOT use headings, labels, or section markers like【】or brackets. Write in natural flowing paragraphs. Complete all sentences fully.';
                            ?>
                            <textarea name="raplsaich_settings[regenerate_prompt]" id="raplsaich_regenerate_prompt" rows="4" class="large-text"><?php
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
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_regenerate_prompt" data-default="<?php echo esc_attr($default_regenerate_prompt); ?>">
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
                            <textarea name="raplsaich_settings[feedback_good_header]" id="raplsaich_feedback_good_header" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['feedback_good_header'] ?? $default_feedback_good);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Header text prepended to positive feedback examples sent to AI.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_feedback_good_header" data-default="<?php echo esc_attr($default_feedback_good); ?>">
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
                            <textarea name="raplsaich_settings[feedback_bad_header]" id="raplsaich_feedback_bad_header" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['feedback_bad_header'] ?? $default_feedback_bad);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Header text prepended to negative feedback examples sent to AI.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_feedback_bad_header" data-default="<?php echo esc_attr($default_feedback_bad); ?>">
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
                            <textarea name="raplsaich_settings[summary_prompt]" id="raplsaich_summary_prompt" rows="3" class="large-text"><?php
                                echo esc_textarea($settings['summary_prompt'] ?? $default_summary_prompt);
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Prompt used to generate conversation summaries.', 'rapls-ai-chatbot'); ?></p>
                            <p>
                                <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_summary_prompt" data-default="<?php echo esc_attr($default_summary_prompt); ?>">
                                    <?php esc_html_e('Reset to default', 'rapls-ai-chatbot'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                </table>
                </div><!-- /.raplsaich-advanced-feature-section -->
            </div>

            <!-- Display Settings -->
            <div id="tab-display" class="tab-content">
                <div class="raplsaich-tab-header">
                    <h2><?php esc_html_e('Display Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="raplsaich-reset-tab-btn" data-tab="tab-display">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Widget Theme', 'rapls-ai-chatbot'); ?>
                            <span class="raplsaich-tooltip" data-tooltip="<?php esc_attr_e('Select the appearance theme for the chat widget. Pro version offers more refined design themes.', 'rapls-ai-chatbot'); ?>">?</span>
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
                            <div class="raplsaich-theme-selector">
                                <p class="raplsaich-theme-group-label"><?php esc_html_e('Free Themes', 'rapls-ai-chatbot'); ?></p>
                                <div class="raplsaich-theme-options">
                                    <?php foreach ($free_themes as $theme_key => $theme_name): ?>
                                        <label class="raplsaich-theme-option <?php echo esc_attr($current_theme === $theme_key ? 'selected' : ''); ?>">
                                            <input type="radio" name="raplsaich_settings[widget_theme]" value="<?php echo esc_attr($theme_key); ?>"
                                                <?php checked($current_theme, $theme_key); ?>>
                                            <span class="raplsaich-theme-preview raplsaich-theme-preview-<?php echo esc_attr($theme_key); ?>"></span>
                                            <span class="raplsaich-theme-name"><?php echo esc_html($theme_name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($is_pro_active): ?>
                                <p class="raplsaich-theme-group-label"><?php esc_html_e('Pro Themes', 'rapls-ai-chatbot'); ?></p>
                                <div class="raplsaich-theme-options">
                                    <?php foreach ($pro_themes as $theme_key => $theme_name): ?>
                                        <label class="raplsaich-theme-option <?php echo esc_attr($current_theme === $theme_key ? 'selected' : ''); ?>">
                                            <input type="radio" name="raplsaich_settings[widget_theme]" value="<?php echo esc_attr($theme_key); ?>"
                                                <?php checked($current_theme, $theme_key); ?>>
                                            <span class="raplsaich-theme-preview raplsaich-theme-preview-<?php echo esc_attr($theme_key); ?>"></span>
                                            <span class="raplsaich-theme-name"><?php echo esc_html($theme_name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="raplsaich-theme-group-label"><?php esc_html_e('Pro Themes', 'rapls-ai-chatbot'); ?> <span class="raplsaich-pro-badge-small">PRO</span></p>
                                <p class="description">
                                    <?php esc_html_e('10 additional themes available:', 'rapls-ai-chatbot'); ?>
                                    <?php echo esc_html(implode(', ', $pro_themes)); ?>
                                </p>
                                <p class="description">
                                    <a href="https://raplsworks.com/plugins/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to Pro to unlock all themes', 'rapls-ai-chatbot'); ?></a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Badge Position', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php $badge_position = $settings['badge_position'] ?? 'bottom-right'; ?>
                            <div class="raplsaich-badge-position-selector">
                                <div class="raplsaich-badge-position-grid">
                                    <label class="raplsaich-badge-pos-option<?php echo esc_attr($badge_position === 'top-left' ? ' active' : ''); ?>">
                                        <input type="radio" name="raplsaich_settings[badge_position]" value="top-left" <?php checked($badge_position, 'top-left'); ?>>
                                        <span class="raplsaich-badge-pos-box">
                                            <span class="raplsaich-badge-pos-dot" style="top: 4px; left: 4px;"></span>
                                        </span>
                                        <span class="raplsaich-badge-pos-label"><?php esc_html_e('Top Left', 'rapls-ai-chatbot'); ?></span>
                                    </label>
                                    <label class="raplsaich-badge-pos-option<?php echo esc_attr($badge_position === 'top-right' ? ' active' : ''); ?>">
                                        <input type="radio" name="raplsaich_settings[badge_position]" value="top-right" <?php checked($badge_position, 'top-right'); ?>>
                                        <span class="raplsaich-badge-pos-box">
                                            <span class="raplsaich-badge-pos-dot" style="top: 4px; right: 4px;"></span>
                                        </span>
                                        <span class="raplsaich-badge-pos-label"><?php esc_html_e('Top Right', 'rapls-ai-chatbot'); ?></span>
                                    </label>
                                    <label class="raplsaich-badge-pos-option<?php echo esc_attr($badge_position === 'bottom-left' ? ' active' : ''); ?>">
                                        <input type="radio" name="raplsaich_settings[badge_position]" value="bottom-left" <?php checked($badge_position, 'bottom-left'); ?>>
                                        <span class="raplsaich-badge-pos-box">
                                            <span class="raplsaich-badge-pos-dot" style="bottom: 4px; left: 4px;"></span>
                                        </span>
                                        <span class="raplsaich-badge-pos-label"><?php esc_html_e('Bottom Left', 'rapls-ai-chatbot'); ?></span>
                                    </label>
                                    <label class="raplsaich-badge-pos-option<?php echo esc_attr($badge_position === 'bottom-right' ? ' active' : ''); ?>">
                                        <input type="radio" name="raplsaich_settings[badge_position]" value="bottom-right" <?php checked($badge_position, 'bottom-right'); ?>>
                                        <span class="raplsaich-badge-pos-box">
                                            <span class="raplsaich-badge-pos-dot" style="bottom: 4px; right: 4px;"></span>
                                        </span>
                                        <span class="raplsaich-badge-pos-label"><?php esc_html_e('Bottom Right', 'rapls-ai-chatbot'); ?></span>
                                    </label>
                                </div>
                                <div class="raplsaich-badge-margin-group" style="margin-top: 12px;">
                                    <span class="raplsaich-badge-margin-label"><strong><?php esc_html_e('Desktop', 'rapls-ai-chatbot'); ?></strong> <?php esc_html_e('Margin', 'rapls-ai-chatbot'); ?>:</span>
                                    <label id="raplsaich_margin_h_wrap">
                                        <span id="raplsaich_margin_h_label"><?php echo esc_html(in_array($badge_position, ['bottom-left', 'top-left']) ? __('Left:', 'rapls-ai-chatbot') : __('Right:', 'rapls-ai-chatbot')); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_margin_right]" id="raplsaich_badge_margin_right"
                                               value="<?php echo esc_attr($settings['badge_margin_right'] ?? 20); ?>"
                                               min="0" max="200" style="width: 70px;"> px
                                    </label>
                                    <label id="raplsaich_margin_v_wrap">
                                        <span id="raplsaich_margin_v_label"><?php echo esc_html(in_array($badge_position, ['top-right', 'top-left']) ? __('Top:', 'rapls-ai-chatbot') : __('Bottom:', 'rapls-ai-chatbot')); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_margin_bottom]" id="raplsaich_badge_margin_bottom"
                                               value="<?php echo esc_attr($settings['badge_margin_bottom'] ?? 20); ?>"
                                               min="0" max="200" style="width: 70px;"> px
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Size:', 'rapls-ai-chatbot'); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_size]" id="raplsaich_badge_size"
                                               value="<?php echo esc_attr($settings['badge_size'] ?? 60); ?>"
                                               min="30" max="120" style="width: 70px;"> px
                                    </label>
                                </div>
                                <div class="raplsaich-badge-margin-group" style="margin-top: 8px;">
                                    <span class="raplsaich-badge-margin-label"><strong><?php esc_html_e('Mobile', 'rapls-ai-chatbot'); ?></strong> <?php esc_html_e('Margin', 'rapls-ai-chatbot'); ?>:</span>
                                    <label>
                                        <span><?php echo esc_html(in_array($badge_position, ['bottom-left', 'top-left']) ? __('Left:', 'rapls-ai-chatbot') : __('Right:', 'rapls-ai-chatbot')); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_margin_right_mobile]" id="raplsaich_badge_margin_right_mobile"
                                               value="<?php echo esc_attr($settings['badge_margin_right_mobile'] ?? 20); ?>"
                                               min="0" max="200" style="width: 70px;"> px
                                    </label>
                                    <label>
                                        <span><?php echo esc_html(in_array($badge_position, ['top-right', 'top-left']) ? __('Top:', 'rapls-ai-chatbot') : __('Bottom:', 'rapls-ai-chatbot')); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_margin_bottom_mobile]" id="raplsaich_badge_margin_bottom_mobile"
                                               value="<?php echo esc_attr($settings['badge_margin_bottom_mobile'] ?? 20); ?>"
                                               min="0" max="200" style="width: 70px;"> px
                                    </label>
                                    <label>
                                        <span><?php esc_html_e('Size:', 'rapls-ai-chatbot'); ?></span>
                                        <input type="number" name="raplsaich_settings[badge_size_mobile]" id="raplsaich_badge_size_mobile"
                                               value="<?php echo esc_attr($settings['badge_size_mobile'] ?? ($settings['badge_size'] ?? 60)); ?>"
                                               min="30" max="120" style="width: 70px;"> px
                                    </label>
                                    <button type="button" class="button button-small" onclick="jQuery('#raplsaich_badge_margin_right,#raplsaich_badge_margin_bottom,#raplsaich_badge_margin_right_mobile,#raplsaich_badge_margin_bottom_mobile').val(20); jQuery('#raplsaich_badge_size,#raplsaich_badge_size_mobile').val(60); return false;">
                                        <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                                    </button>
                                </div>
                            </div>
                            <!-- Badge position styles loaded via wp_enqueue_style('raplsaich-badge-position') -->
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Badge Icon', 'rapls-ai-chatbot'); ?>
                            <?php if (!$is_pro_active): ?><span class="raplsaich-pro-badge-small">PRO</span><?php endif; ?>
                        </th>
                        <td>
                            <?php
                            $badge_pro_settings = raplsaich_get_ext_settings($settings);
                            $badge_icon_type = $badge_pro_settings['badge_icon_type'] ?? 'default';
                            $badge_icon_preset = $badge_pro_settings['badge_icon_preset'] ?? '';
                            $badge_icon_image = $badge_pro_settings['badge_icon_image'] ?? '';
                            $badge_icon_emoji = $badge_pro_settings['badge_icon_emoji'] ?? '';
                            ?>
                            <div class="raplsaich-badge-icon-preview" style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: <?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); flex-shrink: 0; cursor: pointer;">
                                    <?php if ($badge_icon_type === 'preset' && !empty($badge_icon_preset)) : ?>
                                        <?php
                                        // Force SVG to 28x28 by adding width/height attributes
                                        $preview_svg = raplsaich_get_badge_preset_svg($badge_icon_preset);
                                        $preview_svg = str_replace('<svg ', '<svg width="28" height="28" ', $preview_svg);
                                        $svg_tags = raplsaich_get_svg_allowed_tags();
                                        $svg_tags['svg']['width'] = true;
                                        $svg_tags['svg']['height'] = true;
                                        echo wp_kses($preview_svg, $svg_tags);
                                        ?>
                                    <?php elseif ($badge_icon_type === 'image' && !empty($badge_icon_image)) : ?>
                                        <img src="<?php echo esc_url($badge_icon_image); ?>" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php elseif ($badge_icon_type === 'emoji' && !empty($badge_icon_emoji)) : ?>
                                        <span style="font-size: 28px; line-height: 1;"><?php echo esc_html($badge_icon_emoji); ?></span>
                                    <?php else : ?>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><circle cx="8" cy="10" r="1.5"/><circle cx="12" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($is_pro_active) : ?>
                                        <span class="description"><span class="raplsaich-pro-menu-badge raplsaich-pro-badge-active" style="font-size: 10px; padding: 1px 5px; vertical-align: middle;">PRO</span> <?php esc_html_e('Configured in Pro Settings > Badge Icon tab.', 'rapls-ai-chatbot'); ?></span>
                                    <?php else : ?>
                                        <span class="description">
                                            <span class="dashicons dashicons-star-filled" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom; color: #667eea;"></span>
                                            <a href="https://raplsworks.com/plugins/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to Pro to customize the badge icon with presets, images, or emoji.', 'rapls-ai-chatbot'); ?></a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Primary Color', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="raplsaich_settings[primary_color]" id="raplsaich_primary_color"
                                   value="<?php echo esc_attr($settings['primary_color'] ?? '#007bff'); ?>"
                                   class="raplsaich-color-field" data-default-color="#007bff">
                            <button type="button" class="button button-small raplsaich-reset-field" data-target="raplsaich_primary_color" data-default="#007bff">
                                <?php esc_html_e('Reset', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('This color is automatically set when you select a theme.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Dark Mode', 'rapls-ai-chatbot'); ?>
                            <?php if (!$is_pro_active): ?><span class="raplsaich-pro-badge-small">PRO</span><?php endif; ?>
                            <span class="raplsaich-tooltip" data-tooltip="<?php esc_attr_e('When dark mode is enabled, the chat widget displays in dark colors regardless of the selected theme.', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <?php if ($is_pro_active): ?>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[dark_mode]" value="1"
                                    <?php checked($settings['dark_mode'] ?? false); ?>>
                                <?php esc_html_e('Enable dark mode for the chatbot', 'rapls-ai-chatbot'); ?>
                            </label>
                            <?php else: ?>
                            <p class="description">
                                <span class="dashicons dashicons-star-filled" style="color: #667eea; vertical-align: text-bottom;"></span>
                                <a href="https://raplsworks.com/plugins/rapls-ai-chatbot-pro/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to Pro to enable dark mode for the chatbot.', 'rapls-ai-chatbot'); ?></a>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mobile Display', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[show_on_mobile]" value="1"
                                    <?php checked($settings['show_on_mobile'] ?? true); ?>>
                                <?php esc_html_e('Show on mobile devices', 'rapls-ai-chatbot'); ?>
                            </label>
                            <br>
                            <label style="margin-top: 8px; display: inline-block;">
                                <input type="checkbox" name="raplsaich_settings[ios_keyboard_fix]" value="1"
                                    <?php checked($settings['ios_keyboard_fix'] ?? false); ?>>
                                <?php esc_html_e('iOS Safari keyboard fix', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Keeps the close button visible when the on-screen keyboard is open on iPhone Safari. Uses the VisualViewport API. Disable if it conflicts with your theme.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Markdown Rendering', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[markdown_enabled]" value="1"
                                    <?php checked($settings['markdown_enabled'] ?? true); ?>>
                                <?php
                                /* translators: Markdown is a text formatting syntax used in AI responses */
                                esc_html_e('Enable Markdown rendering in bot messages', 'rapls-ai-chatbot');
                                ?>
                            </label>
                            <p class="description"><?php esc_html_e('Render bold, italic, code blocks, lists, and headings in AI responses.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Page Type Display', 'rapls-ai-chatbot'); ?>
                            <span class="raplsaich-tooltip" data-tooltip="<?php esc_attr_e('Select page types where the chatbot is displayed. Unchecking a type hides the chatbot on those pages.', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="raplsaich_settings[badge_show_on_home]" value="1"
                                        <?php checked($settings['badge_show_on_home'] ?? true); ?>>
                                    <?php esc_html_e('Homepage / Front Page', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="raplsaich_settings[badge_show_on_posts]" value="1"
                                        <?php checked($settings['badge_show_on_posts'] ?? true); ?>>
                                    <?php esc_html_e('Single Posts', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="raplsaich_settings[badge_show_on_pages]" value="1"
                                        <?php checked($settings['badge_show_on_pages'] ?? true); ?>>
                                    <?php esc_html_e('Pages', 'rapls-ai-chatbot'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="raplsaich_settings[badge_show_on_archives]" value="1"
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
                            <span class="raplsaich-tooltip" data-tooltip="<?php esc_attr_e('Enter IDs to show the chatbot only on those pages/posts. When empty, page type settings are used.', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <input type="text" name="raplsaich_settings[badge_include_ids]"
                                   value="<?php echo esc_attr($settings['badge_include_ids'] ?? ''); ?>"
                                   class="regular-text" placeholder="<?php esc_attr_e('e.g. 10, 25, 142', 'rapls-ai-chatbot'); ?>">
                            <p class="description"><?php esc_html_e('Comma-separated post/page IDs. If set, the chatbot will ONLY be displayed on these pages (overrides page type settings above).', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Exclude (IDs)', 'rapls-ai-chatbot'); ?>
                            <span class="raplsaich-tooltip" data-tooltip="<?php esc_attr_e('The chatbot will be hidden on pages/posts with the specified IDs.', 'rapls-ai-chatbot'); ?>">?</span>
                        </th>
                        <td>
                            <input type="text" name="raplsaich_settings[badge_exclude_ids]"
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
                            <div class="raplsaich-page-exclusion">
                                <!-- Empty value to ensure the field is submitted even when no pages are selected -->
                                <input type="hidden" name="raplsaich_settings[excluded_pages_submitted]" value="1">
                                <select id="raplsaich-page-selector" style="min-width: 300px;">
                                    <option value=""><?php esc_html_e('-- Select page to exclude --', 'rapls-ai-chatbot'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <?php if (!in_array($page->ID, $excluded_pages, true)): ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button" id="raplsaich-add-excluded-page"><?php esc_html_e('Add', 'rapls-ai-chatbot'); ?></button>

                                <div id="raplsaich-excluded-pages-list" style="margin-top: 15px;">
                                    <?php if (!empty($excluded_pages)): ?>
                                        <?php foreach ($excluded_pages as $page_id): ?>
                                            <?php $page_title = get_the_title($page_id); ?>
                                            <?php if ($page_title): ?>
                                                <div class="raplsaich-excluded-page-item" data-page-id="<?php echo esc_attr($page_id); ?>" style="display: inline-flex; align-items: center; background: #f0f0f1; border-radius: 4px; padding: 5px 10px; margin: 3px 5px 3px 0;">
                                                    <span><?php echo esc_html($page_title); ?></span>
                                                    <input type="hidden" name="raplsaich_settings[excluded_pages][]" value="<?php echo esc_attr($page_id); ?>">
                                                    <button type="button" class="raplsaich-remove-excluded-page" style="background: none; border: none; cursor: pointer; color: #a00; margin-left: 8px; font-size: 16px;">&times;</button>
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

                <hr style="margin: 20px 0;">
                <h3><?php esc_html_e('Cross-Site Embed', 'rapls-ai-chatbot'); ?></h3>
                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e('Use the code below to display this chatbot on external sites. Paste it before the closing </body> tag.', 'rapls-ai-chatbot'); ?>
                </p>
                <?php
                $embed_site_url = esc_url(home_url());
                $embed_plugin_url = esc_url(RAPLSAICH_PLUGIN_URL . 'assets/js/embed-loader.js');
                $embed_primary_color = esc_attr($settings['primary_color'] ?? '#007bff');
                // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- embed snippet for external sites, not enqueued here
                $embed_script_code = '<script src="' . $embed_plugin_url . '"' . "\n"
                    . '        data-site="' . $embed_site_url . '"' . "\n"
                    . '        data-color="' . $embed_primary_color . '"' . "\n"
                    . '        data-position="right"' . "\n"
                    . '        async></script>';
                $embed_iframe_code = '<iframe src="' . $embed_site_url . '/?raplsaich_embed=1"' . "\n"
                    . '        style="width:400px;height:600px;border:none;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.15)"' . "\n"
                    . '        allow="clipboard-write"' . "\n"
                    . '        title="Chat"></iframe>';
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Script Embed (Recommended)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div style="position:relative;">
                                <textarea id="raplsaich-embed-script-code" class="large-text code" rows="5" readonly onclick="this.select()"><?php echo esc_textarea($embed_script_code); ?></textarea>
                                <button type="button" class="button button-small raplsaich-copy-embed" data-target="raplsaich-embed-script-code" style="margin-top:5px;">
                                    <?php esc_html_e('Copy', 'rapls-ai-chatbot'); ?>
                                </button>
                            </div>
                            <p class="description"><?php esc_html_e('Displays a floating chat badge. Click to open the chat window.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Iframe Embed', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div style="position:relative;">
                                <textarea id="raplsaich-embed-iframe-code" class="large-text code" rows="4" readonly onclick="this.select()"><?php echo esc_textarea($embed_iframe_code); ?></textarea>
                                <button type="button" class="button button-small raplsaich-copy-embed" data-target="raplsaich-embed-iframe-code" style="margin-top:5px;">
                                    <?php esc_html_e('Copy', 'rapls-ai-chatbot'); ?>
                                </button>
                            </div>
                            <p class="description"><?php esc_html_e('Embeds the chat directly in the page at the specified size.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Security Settings -->
            <div id="tab-security" class="tab-content">
                <div class="raplsaich-tab-header">
                    <h2><?php esc_html_e('Security Settings', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="raplsaich-reset-tab-btn" data-tab="tab-security">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <h3><?php esc_html_e('reCAPTCHA', 'rapls-ai-chatbot'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Use Google reCAPTCHA v3 to prevent spam.', 'rapls-ai-chatbot'); ?>
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get keys from Google reCAPTCHA Admin Console', 'rapls-ai-chatbot'); ?></a>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable reCAPTCHA', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[recaptcha_enabled]" value="1"
                                    <?php checked($settings['recaptcha_enabled'] ?? false); ?>>
                                <?php esc_html_e('Protect with reCAPTCHA v3', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Key', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="text" name="raplsaich_settings[recaptcha_site_key]"
                                   value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>"
                                   class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e('reCAPTCHA v3 site key', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Secret Key', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="password" name="raplsaich_settings[recaptcha_secret_key]"
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
                            <input type="number" name="raplsaich_settings[recaptcha_threshold]"
                                   value="<?php echo esc_attr($settings['recaptcha_threshold'] ?? 0.5); ?>"
                                   min="0.1" max="1" step="0.1" class="small-text">
                            <p class="description"><?php esc_html_e('0.1-1.0 (default: 0.5). Requests below this score will be blocked.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Use Existing reCAPTCHA', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[recaptcha_use_existing]" value="1"
                                    <?php checked($settings['recaptcha_use_existing'] ?? false); ?>>
                                <?php esc_html_e('Use existing reCAPTCHA on the page', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('If another plugin (e.g., Contact Form 7) loads reCAPTCHA, avoid loading the script twice.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3><?php esc_html_e('Access Control', 'rapls-ai-chatbot'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Consent Strict Mode', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[consent_strict_mode]" value="1"
                                    <?php checked($settings['consent_strict_mode'] ?? false); ?>>
                                <?php esc_html_e('Require WP Consent API for localStorage and conversion tracking (GDPR strict)', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, user ID persistence (localStorage) and conversion tracking are disabled unless a consent management plugin (WP Consent API) is active and the user has granted consent. When disabled, these features work as usual regardless of consent status.', 'rapls-ai-chatbot'); ?>
                            </p>
                            <div class="notice notice-warning inline" style="margin: 8px 0; padding: 4px 12px;">
                                <p><?php esc_html_e('Note: When strict mode is ON and no Consent API plugin is installed, window size memory, session persistence, and conversion tracking will be disabled. The chatbot itself will still work normally.', 'rapls-ai-chatbot'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rate Limit', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <div>
                                    <input type="number" name="raplsaich_settings[rate_limit]"
                                           value="<?php echo esc_attr($settings['rate_limit'] ?? 20); ?>"
                                           min="0" class="small-text">
                                    <span><?php esc_html_e('requests', 'rapls-ai-chatbot'); ?></span>
                                </div>
                                <span>/</span>
                                <div>
                                    <select name="raplsaich_settings[rate_limit_window]">
                                        <option value="60" <?php selected($settings['rate_limit_window'] ?? 3600, 60); ?>><?php esc_html_e('1 minute', 'rapls-ai-chatbot'); ?></option>
                                        <option value="300" <?php selected($settings['rate_limit_window'] ?? 3600, 300); ?>><?php esc_html_e('5 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="600" <?php selected($settings['rate_limit_window'] ?? 3600, 600); ?>><?php esc_html_e('10 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="1800" <?php selected($settings['rate_limit_window'] ?? 3600, 1800); ?>><?php esc_html_e('30 minutes', 'rapls-ai-chatbot'); ?></option>
                                        <option value="3600" <?php selected($settings['rate_limit_window'] ?? 3600, 3600); ?>><?php esc_html_e('1 hour', 'rapls-ai-chatbot'); ?></option>
                                        <option value="10800" <?php selected($settings['rate_limit_window'] ?? 3600, 10800); ?>><?php esc_html_e('3 hours', 'rapls-ai-chatbot'); ?></option>
                                        <option value="21600" <?php selected($settings['rate_limit_window'] ?? 3600, 21600); ?>><?php esc_html_e('6 hours', 'rapls-ai-chatbot'); ?></option>
                                        <option value="43200" <?php selected($settings['rate_limit_window'] ?? 3600, 43200); ?>><?php esc_html_e('12 hours', 'rapls-ai-chatbot'); ?></option>
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
                                <input type="checkbox" name="raplsaich_settings[trust_cloudflare_ip]" value="1"
                                    <?php checked($settings['trust_cloudflare_ip'] ?? false); ?>>
                                <?php esc_html_e('Trust Cloudflare CF-Connecting-IP header', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Enable only if your site is behind Cloudflare. Uses Cloudflare\'s header to detect the real visitor IP for rate limiting.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description" style="color: #d63638;"><strong><?php esc_html_e('Security warning: Only enable this if ALL traffic to your server passes through Cloudflare. If your server is directly accessible (bypassing Cloudflare), attackers can forge this header to bypass rate limiting and IP blocking.', 'rapls-ai-chatbot'); ?></strong></p>

                            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #dcdcde;">
                            <label>
                                <input type="checkbox" name="raplsaich_settings[trust_proxy_ip]" value="1"
                                    <?php checked($settings['trust_proxy_ip'] ?? false); ?>>
                                <?php esc_html_e('Trust reverse proxy X-Forwarded-For header', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Enable if your site is behind a reverse proxy (Nginx, AWS ALB, etc.) that sets X-Forwarded-For. Uses the first public IP from the header for rate limiting.', 'rapls-ai-chatbot'); ?></p>
                            <p class="description" style="color: #d63638;"><strong><?php esc_html_e('Security warning: Only enable this if ALL traffic passes through your trusted proxy. Otherwise attackers can forge this header.', 'rapls-ai-chatbot'); ?></strong></p>
                            </div>

                            <details style="margin-top: 12px;">
                                <summary style="cursor: pointer; font-weight: 600; font-size: 13px; color: #2271b1;"><?php esc_html_e('Trusted proxy setup guide', 'rapls-ai-chatbot'); ?></summary>
                                <div style="margin-top: 8px; padding: 12px; background: #f6f7f7; border-radius: 4px; font-size: 13px; line-height: 1.8;">
                                    <?php echo wp_kses(
                                        '<p style="margin: 0 0 10px;"><strong>' . __('Setup checklist', 'rapls-ai-chatbot') . '</strong></p>'
                                        . '<ol style="margin: 0 0 12px 20px; padding: 0;">'
                                        . '<li>' . __('Confirm <code>REMOTE_ADDR</code> shows your proxy IP (not the visitor IP) before enabling', 'rapls-ai-chatbot') . '</li>'
                                        . '<li>' . __('For Cloudflare: enable "Trust Cloudflare" above (uses CF-Connecting-IP, no CIDR needed)', 'rapls-ai-chatbot') . '</li>'
                                        . '<li>' . __('For other proxies: add their IPs/CIDRs via the <code>raplsaich_trusted_proxies</code> filter', 'rapls-ai-chatbot') . '</li>'
                                        . '<li>' . __('Verify in Security Diagnostics below that client IPs are detected correctly', 'rapls-ai-chatbot') . '</li>'
                                        . '</ol>'
                                        . '<p style="margin: 0 0 10px;"><strong>' . __('Filter usage', 'rapls-ai-chatbot') . '</strong></p>'
                                        . '<p style="margin: 0 0 6px;">' . __('Add trusted proxy IPs or CIDR ranges via <code>raplsaich_trusted_proxies</code> filter:', 'rapls-ai-chatbot') . '</p>'
                                        . '<table style="font-size: 12px; border-collapse: collapse; margin: 0 0 12px;">'
                                        . '<tr><td style="padding: 2px 12px 2px 0; font-weight: 600;">Cloudflare</td><td style="padding: 2px 0;"><code>172.64.0.0/13, 104.16.0.0/13, 173.245.48.0/20</code> …</td></tr>'
                                        . '<tr><td style="padding: 2px 12px 2px 0; font-weight: 600;">AWS ALB</td><td style="padding: 2px 0;">' . __('Your VPC CIDR (e.g. <code>10.0.0.0/8</code>)', 'rapls-ai-chatbot') . '</td></tr>'
                                        . '</table>'
                                        . '<p style="margin: 0 0 6px; color: #d63638; font-weight: 600;">' . __('Note', 'rapls-ai-chatbot') . '</p>'
                                        . '<ul style="margin: 0 0 0 16px; padding: 0; color: #50575e;">'
                                        . '<li>' . __('Cloudflare IP ranges change periodically. Hardcoded CIDRs may become stale.', 'rapls-ai-chatbot') . '</li>'
                                        . '<li>' . __('If misconfigured, rate limiting applies to the proxy IP instead of real visitors.', 'rapls-ai-chatbot') . '</li>'
                                        . '</ul>',
                                        ['p' => ['style' => []], 'ol' => ['style' => []], 'ul' => ['style' => []], 'li' => [], 'strong' => [], 'code' => [], 'table' => ['style' => []], 'tr' => [], 'td' => ['style' => []]]
                                    ); ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA Failure Mode', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <select name="raplsaich_settings[recaptcha_fail_mode]">
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
                            $rest_controller = new RAPLSAICH_REST_Controller();
                            $diag_hosts = $rest_controller->get_allowed_origin_hosts();
                            ?>
                            <code><?php echo esc_html(implode(', ', $diag_hosts)); ?></code>
                            <?php if (empty($diag_hosts)) : ?>
                                <span style="color:#d63638;"><strong><?php esc_html_e('Warning: No allowed hosts detected. Origin/Referer checks will reject all requests.', 'rapls-ai-chatbot'); ?></strong></span>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('These hostnames are accepted for Origin/Referer checks and reCAPTCHA hostname validation (same source as runtime). Custom hosts can be added via the raplsaich_allowed_origins filter.', 'rapls-ai-chatbot'); ?></p>
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
                                $tkey = 'raplsaich_bot_drop_' . $bkey;
                                $bcount = $use_cache
                                    ? (int) wp_cache_get($tkey, 'raplsaich_bot')
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
                                    echo '<span style="margin-right:16px;">' . esc_html($blabel) . ': <strong>' . esc_html($bcount) . '</strong>' . esc_html($suffix) . '</span>';
                                }
                            }
                            if (!$has_detections) {
                                echo '<em>' . esc_html__('No bot activity detected in the past hour.', 'rapls-ai-chatbot') . '</em>';
                            }
                            ?>
                            <p class="description"><?php esc_html_e('Requests blocked by bot detection in the past hour. "(approx)" = sampled 1-in-10, shown as estimated total (×10). "Future clock" values are exact when client IP is available, otherwise sampled. High numbers may indicate your forms are being targeted.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('XFF Truncated (past hour)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $xff_key = 'raplsaich_xff_truncated';
                            $xff_count = $use_cache
                                ? (int) wp_cache_get($xff_key, 'raplsaich_bot')
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
                        <th scope="row"><?php esc_html_e('IP Detection', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $diag_settings = get_option('raplsaich_settings', []);
                            $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '—';
                            $xff = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])) : '';
                            $cf_ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'])) : '';
                            $trust_cf = !empty($diag_settings['trust_cloudflare_ip']);
                            $trust_proxy = !empty($diag_settings['trust_proxy_ip']);
                            ?>
                            <table class="widefat striped" style="max-width:600px;">
                                <tr><td>REMOTE_ADDR</td><td><code><?php echo esc_html($remote_addr); ?></code></td></tr>
                                <?php if ($trust_cf) : ?>
                                <tr><td>CF-Connecting-IP</td><td><code><?php echo esc_html($cf_ip ?: '—'); ?></code>
                                    <?php if ($cf_ip) : ?><span style="color:green;">&#x2713;</span><?php else : ?><span style="color:#d63638;">&#x2717; <?php esc_html_e('Not present', 'rapls-ai-chatbot'); ?></span><?php endif; ?>
                                </td></tr>
                                <?php endif; ?>
                                <?php if ($trust_proxy) : ?>
                                <tr><td>X-Forwarded-For</td><td><code><?php echo esc_html($xff ?: '—'); ?></code>
                                    <?php if ($xff) : ?><span style="color:green;">&#x2713;</span><?php else : ?><span style="color:#999;"><?php esc_html_e('Not present (expected if accessing directly)', 'rapls-ai-chatbot'); ?></span><?php endif; ?>
                                </td></tr>
                                <tr><td><?php esc_html_e('Trusted Proxies', 'rapls-ai-chatbot'); ?></td><td>
                                    <?php
                                    $raw_proxies = (array) apply_filters('raplsaich_trusted_proxies', []);
                                    if (!empty($raw_proxies)) {
                                        echo '<code>' . esc_html(implode(', ', array_slice($raw_proxies, 0, 10))) . '</code>';
                                        if (count($raw_proxies) > 10) {
                                            echo ' (+' . (int) (count($raw_proxies) - 10) . ')';
                                        }
                                    } else {
                                        echo '<em>' . esc_html__('None configured (private/loopback IPs are always trusted)', 'rapls-ai-chatbot') . '</em>';
                                    }
                                    ?>
                                </td></tr>
                                <?php endif; ?>
                            </table>
                            <p class="description"><?php esc_html_e('Shows the IP headers visible in this admin request. Visitor requests may show different values depending on your proxy/CDN configuration.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Recent Admin Failures (past 24h)', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $diag_events = get_transient('raplsaich_diag_events');
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
                        <th scope="row"><?php esc_html_e('Object Cache', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php if (wp_using_ext_object_cache()) : ?>
                                <?php
                                // Verify transients actually work (Redis/Memcached may be misconfigured)
                                $test_key = 'raplsaich_diag_oc_test';
                                set_transient($test_key, 'ok', 60);
                                $test_result = get_transient($test_key);
                                delete_transient($test_key);
                                if ($test_result === 'ok') :
                                ?>
                                    <span style="color:green;">&#x2713;</span> <?php esc_html_e('External object cache active, transient read/write verified.', 'rapls-ai-chatbot'); ?>
                                <?php else : ?>
                                    <span style="color:#d63638;">&#x2717;</span> <?php esc_html_e('External object cache detected but transient read/write test failed. Session rate limits and bot counters may not persist.', 'rapls-ai-chatbot'); ?>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;">—</span> <?php esc_html_e('Not active (using database). Rate limits and bot counters use DB-backed transients.', 'rapls-ai-chatbot'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA Config', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $rc_enabled = !empty($diag_settings['recaptcha_enabled']);
                            $rc_site = trim($diag_settings['recaptcha_site_key'] ?? '');
                            $rc_secret = trim($diag_settings['recaptcha_secret_key'] ?? '');
                            if (!$rc_enabled) :
                            ?>
                                <span style="color:#999;">—</span> <?php esc_html_e('Disabled', 'rapls-ai-chatbot'); ?>
                            <?php elseif (!empty($rc_site) && !empty($rc_secret)) : ?>
                                <span style="color:green;">&#x2713;</span> <?php esc_html_e('Enabled — site key and secret key configured.', 'rapls-ai-chatbot'); ?>
                            <?php elseif (!empty($rc_site) && empty($rc_secret)) : ?>
                                <span style="color:#d63638;">&#x2717;</span> <?php esc_html_e('Site key is set but secret key is missing. reCAPTCHA verification will fail.', 'rapls-ai-chatbot'); ?>
                            <?php elseif (empty($rc_site) && !empty($rc_secret)) : ?>
                                <span style="color:#d63638;">&#x2717;</span> <?php esc_html_e('Secret key is set but site key is missing. reCAPTCHA widget will not load.', 'rapls-ai-chatbot'); ?>
                            <?php else : ?>
                                <span style="color:#d63638;">&#x2717;</span> <?php esc_html_e('Enabled but both keys are missing. reCAPTCHA will not function.', 'rapls-ai-chatbot'); ?>
                            <?php endif; ?>
                            <?php
                            // Warn if offline-message (Pro) is enabled but reCAPTCHA is not configured
                            $pro_settings = raplsaich_get_ext_settings($diag_settings);
                            $offline_enabled = !empty($pro_settings['offline_message_enabled']);
                            if ($offline_enabled && !$rc_enabled) :
                            ?>
                                <br><span style="color:#d63638;">&#x26A0;</span>
                                <strong><?php esc_html_e('Offline Messages will not work', 'rapls-ai-chatbot'); ?></strong>
                                — <?php esc_html_e('This feature requires reCAPTCHA. Visitors will see "form unavailable" until reCAPTCHA is enabled and configured above.', 'rapls-ai-chatbot'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin Tables', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            global $wpdb;
                            $missing_tables = [];
                            $check_failed = false;
                            foreach (raplsaich_table_suffixes() as $suffix) {
                                $full = $wpdb->prefix . $suffix;
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full));
                                if (!empty($wpdb->last_error)) {
                                    $check_failed = true;
                                    break;
                                }
                                if (!$result) {
                                    $missing_tables[] = $suffix;
                                }
                            }
                            if ($check_failed) :
                            ?>
                                <span style="color:#dba617;">&#x26A0;</span>
                                <?php esc_html_e('Unable to verify tables (database permission issue). SHOW TABLES query failed — check that the database user has sufficient privileges.', 'rapls-ai-chatbot'); ?>
                                <br><small><?php esc_html_e('Check DB user privileges for SHOW TABLES, or ask your hosting provider about WAF rule exceptions for wp-admin requests.', 'rapls-ai-chatbot'); ?></small>
                                <?php
                                $support_info = sprintf(
                                    "Issue: SHOW TABLES LIKE query is blocked in wp-admin.\nURL: %s\nDate: %s\nPlugin: Rapls AI Chatbot v%s\nPHP: %s / WP: %s",
                                    esc_url(admin_url()),
                                    gmdate('Y-m-d H:i:s') . ' UTC',
                                    defined('RAPLSAICH_VERSION') ? RAPLSAICH_VERSION : '?',
                                    PHP_VERSION,
                                    get_bloginfo('version')
                                );
                                ?>
                                <br><button type="button" class="button button-small" id="raplsaich-copy-support" data-info="<?php echo esc_attr($support_info); ?>"><?php esc_html_e('Copy support info', 'rapls-ai-chatbot'); ?></button>
                                <span id="raplsaich-copy-status" style="margin-left:6px;display:none;"></span>
                                <textarea id="raplsaich-copy-fallback" class="raplsaich-supportinfo-fallback" style="display:none;width:100%;max-width:100%;margin-top:4px;box-sizing:border-box;font-size:12px;font-family:monospace;" rows="5" readonly></textarea>
                                <?php
                                wp_add_inline_script('raplsaich-admin', '(function(){' .
                                    'var b=document.getElementById("raplsaich-copy-support");if(!b)return;' .
                                    'b.addEventListener("click",function(){var info=b.dataset.info,' .
                                    's=document.getElementById("raplsaich-copy-status"),' .
                                    'f=document.getElementById("raplsaich-copy-fallback");' .
                                    'if(f.getAttribute("data-visible")==="1"){f.focus();f.select();return}' .
                                    'if(navigator.clipboard&&navigator.clipboard.writeText){' .
                                    'navigator.clipboard.writeText(info).then(function(){' .
                                    's.textContent=' . wp_json_encode(__('Copied!', 'rapls-ai-chatbot')) . ';' .
                                    's.style.color="green";s.style.display="inline";f.style.display="none";f.removeAttribute("data-visible")' .
                                    '}).catch(function(){' .
                                    'f.value=info;f.style.display="block";f.setAttribute("data-visible","1");f.focus();f.select();' .
                                    's.textContent=' . wp_json_encode(__('Copy failed — please select and copy manually.', 'rapls-ai-chatbot')) . ';' .
                                    's.style.color="#d63638";s.style.display="inline"' .
                                    '})}else{' .
                                    'f.value=info;f.style.display="block";f.setAttribute("data-visible","1");f.focus();f.select();' .
                                    's.textContent=' . wp_json_encode(__('Clipboard not available — please copy manually.', 'rapls-ai-chatbot')) . ';' .
                                    's.style.color="#d63638";s.style.display="inline"}})})();');
                                ?>
                            <?php elseif (empty($missing_tables)) : ?>
                                <span style="color:green;">&#x2713;</span> <?php echo esc_html(sprintf(
                                    /* translators: %d: number of tables */
                                    __('All %d tables exist.', 'rapls-ai-chatbot'),
                                    count(raplsaich_table_suffixes())
                                )); ?>
                            <?php else : ?>
                                <span style="color:#d63638;">&#x2717;</span>
                                <?php echo esc_html(sprintf(
                                    /* translators: %s: comma-separated list of missing table names */
                                    __('Missing tables: %s — analytics and features using these tables will return empty results. Try deactivating and reactivating the plugin.', 'rapls-ai-chatbot'),
                                    implode(', ', $missing_tables)
                                )); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('REST API', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <?php
                            $rest_url = get_rest_url(null, 'rapls-ai-chatbot/v1/message-limit');
                            $rest_args = [
                                'timeout'     => 5,
                                'redirection' => 0,
                                'user-agent'  => 'RAPLSAICH-Diagnostics/' . RAPLSAICH_VERSION,
                            ];
                            /** Allow insecure SSL for self-signed certs (default: false). */
                            if (apply_filters('raplsaich_diag_allow_insecure_ssl', false)) {
                                $rest_args['sslverify'] = false;
                            }
                            $rest_response = wp_remote_get($rest_url, $rest_args);
                            if (is_wp_error($rest_response)) :
                            ?>
                                <span style="color:#d63638;">&#x2717;</span>
                                <?php
                                /* translators: %s: error message */
                                echo esc_html(sprintf(__('REST API unreachable: %s. Chat functionality may not work.', 'rapls-ai-chatbot'), $rest_response->get_error_message()));
                                ?>
                            <?php else :
                                $code = wp_remote_retrieve_response_code($rest_response);
                                if ($code >= 200 && $code < 500) : ?>
                                    <span style="color:green;">&#x2713;</span> <?php esc_html_e('Reachable', 'rapls-ai-chatbot'); ?> <code><?php echo esc_html($code); ?></code>
                                <?php else : ?>
                                    <span style="color:#d63638;">&#x2717;</span> <?php esc_html_e('Unexpected response code:', 'rapls-ai-chatbot'); ?> <code><?php echo esc_html($code); ?></code>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('Tests if the plugin REST API endpoint is reachable from the server. Some security plugins or .htaccess rules may block REST API access.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Compatibility Note', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <p class="description"><?php esc_html_e('If users cannot submit forms (offline messages, lead capture), check that your JS optimization plugin (e.g. Autoptimize, WP Rocket, LiteSpeed Cache) does not defer or exclude the chatbot scripts. Excluding the chatbot page from optimization usually resolves this.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Data Management -->
            <div id="tab-data" class="tab-content">
                <div class="raplsaich-tab-header">
                    <h2><?php esc_html_e('Data Management', 'rapls-ai-chatbot'); ?></h2>
                    <button type="button" class="raplsaich-reset-tab-btn" data-tab="tab-data">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('Reset to Default', 'rapls-ai-chatbot'); ?>
                    </button>
                </div>

                <h3><?php esc_html_e('Conversation History', 'rapls-ai-chatbot'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Save History', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[save_history]" value="1"
                                    <?php checked($settings['save_history'] ?? true); ?>>
                                <?php esc_html_e('Save conversation history', 'rapls-ai-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('History Retention', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="number" name="raplsaich_settings[retention_days]"
                                   value="<?php echo esc_attr($settings['retention_days'] ?? 90); ?>"
                                   min="0" max="3650" class="small-text"> <?php esc_html_e('days', 'rapls-ai-chatbot'); ?>
                            <p class="description"><?php esc_html_e('0 for unlimited retention', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Delete Data on Uninstall', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="raplsaich_settings[delete_data_on_uninstall]" value="1"
                                    <?php checked($settings['delete_data_on_uninstall'] ?? false); ?>>
                                <?php esc_html_e('Delete all conversation history, knowledge base, leads, and other plugin data when the plugin is uninstalled', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description" style="color: #d63638;">
                                <?php esc_html_e('Warning: This action is irreversible. If disabled, plugin settings will still be removed but database tables (conversations, knowledge base, leads) will be preserved.', 'rapls-ai-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3><?php esc_html_e('Import/Export Settings', 'rapls-ai-chatbot'); ?></h3>
                <p class="description"><?php esc_html_e('Export or import all settings as a JSON file.', 'rapls-ai-chatbot'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Export', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <button type="button" id="raplsaich-export-settings" class="button button-secondary">
                                <?php esc_html_e('Export Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <label style="margin-left: 15px;">
                                <input type="checkbox" id="raplsaich-export-include-knowledge" checked>
                                <?php esc_html_e('Include knowledge data', 'rapls-ai-chatbot'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Download current settings as a JSON file.', 'rapls-ai-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Import', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <input type="file" id="raplsaich-import-file" accept=".json">
                            <button type="button" id="raplsaich-import-settings" class="button button-secondary" style="margin-left: 10px;">
                                <?php esc_html_e('Import Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Upload an exported JSON file to restore settings.', 'rapls-ai-chatbot'); ?></p>
                            <p id="raplsaich-import-status"></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Reset Settings', 'rapls-ai-chatbot'); ?></th>
                        <td>
                            <button type="button" id="raplsaich-reset-settings" class="button button-secondary" style="color: #a00;">
                                <?php esc_html_e('Reset Settings', 'rapls-ai-chatbot'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('Reset all settings to default values. API keys will also be deleted.', 'rapls-ai-chatbot'); ?></p>
                            <p id="raplsaich-reset-status"></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div id="raplsaich-global-submit">
            <?php submit_button(__('Save Settings', 'rapls-ai-chatbot')); ?>
        </div>
    </form>
</div>

<!-- Settings JS loaded via wp_enqueue_script("raplsaich-admin-settings") -->
