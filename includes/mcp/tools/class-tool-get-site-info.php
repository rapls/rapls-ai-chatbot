<?php
/**
 * MCP Tool: get_site_info
 *
 * Returns WordPress site information and plugin configuration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_MCP_Tool_Get_Site_Info {

    /**
     * Register this tool with the registry.
     *
     * @param WPAIC_MCP_Tool_Registry $registry Tool registry.
     */
    public function register(WPAIC_MCP_Tool_Registry $registry): void {
        $registry->register('get_site_info', $this->get_schema(), [$this, 'execute']);
    }

    /**
     * Tool schema for tools/list.
     *
     * @return array
     */
    private function get_schema(): array {
        return [
            'name'        => 'get_site_info',
            'description' => 'Get WordPress site information and AI chatbot plugin configuration. Does not expose API keys.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => (object) [],
                'required'   => [],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array $args Tool arguments (unused).
     * @return array Site information.
     */
    public function execute(array $args): array {
        $settings = get_option('wpaic_settings', []);

        // Count knowledge entries
        $knowledge_count = 0;
        if (class_exists('WPAIC_Knowledge')) {
            $knowledge_count = WPAIC_Knowledge::get_count();
        }

        // Count indexed content
        $index_count = 0;
        if (class_exists('WPAIC_Content_Index')) {
            $index_count = WPAIC_Content_Index::get_count();
        }

        // Conversation stats
        $conversation_count = WPAIC_Conversation::get_count();
        $today_count = WPAIC_Conversation::get_today_count();

        return [
            'site_name'          => get_bloginfo('name'),
            'site_url'           => home_url(),
            'site_language'      => get_locale(),
            'wordpress_version'  => get_bloginfo('version'),
            'plugin_version'     => defined('WPAIC_VERSION') ? WPAIC_VERSION : 'unknown',
            'is_pro'             => WPAIC_Pro_Features::get_instance()->is_pro(),
            'ai_provider'        => $settings['ai_provider'] ?? 'openai',
            'ai_model'           => $this->get_active_model($settings),
            'knowledge_count'    => $knowledge_count,
            'index_count'        => $index_count,
            'conversation_count' => $conversation_count,
            'today_conversations' => $today_count,
            'save_history'       => !empty($settings['save_history']),
            'embedding_enabled'  => !empty($settings['embedding_enabled']),
        ];
    }

    /**
     * Get the active AI model name.
     *
     * @param array $settings Plugin settings.
     * @return string Model name.
     */
    private function get_active_model(array $settings): string {
        $provider = $settings['ai_provider'] ?? 'openai';

        switch ($provider) {
            case 'claude':
                return $settings['claude_model'] ?? 'claude-haiku-4-5-20251001';
            case 'gemini':
                return $settings['gemini_model'] ?? 'gemini-2.0-flash';
            case 'openrouter':
                return $settings['openrouter_model'] ?? 'openrouter/auto';
            default:
                return $settings['openai_model'] ?? 'gpt-4o-mini';
        }
    }
}
