<?php
/**
 * WordPress Abilities API Bridge
 *
 * Registers MCP tools as WordPress Abilities so they are discoverable
 * by the WordPress MCP Adapter (Claude Desktop, Cursor, VS Code, etc.).
 *
 * Requires WordPress 6.9+ (Abilities API) and the MCP Adapter plugin.
 * Gracefully does nothing on older WordPress versions.
 *
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Abilities_Bridge {

    /**
     * Category for all chatbot abilities.
     */
    private const CATEGORY = 'rapls-ai-chatbot';

    /**
     * Tool registry reference.
     *
     * @var WPAIC_MCP_Tool_Registry
     */
    private WPAIC_MCP_Tool_Registry $registry;

    /**
     * Constructor.
     *
     * @param WPAIC_MCP_Tool_Registry $registry Tool registry with registered tools.
     */
    public function __construct(WPAIC_MCP_Tool_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * Initialize: hook into Abilities API if available.
     */
    public function init(): void {
        // Only register if the Abilities API is available (WordPress 6.9+)
        if (!function_exists('wp_register_ability')) {
            return;
        }

        add_action('wp_abilities_api_categories_init', [$this, 'register_category']);
        add_action('wp_abilities_api_init', [$this, 'register_abilities']);
    }

    /**
     * Register the ability category.
     *
     * Categories must be registered before abilities that reference them.
     * Hooked to wp_abilities_api_categories_init.
     */
    public function register_category(): void {
        wp_register_ability_category(self::CATEGORY, [
            'label'       => __('AI Chatbot', 'rapls-ai-chatbot'),
            'description' => __('AI chatbot abilities powered by Rapls AI Chatbot.', 'rapls-ai-chatbot'),
        ]);
    }

    /**
     * Register all MCP tools as WordPress Abilities.
     *
     * Hooked to wp_abilities_api_init.
     */
    public function register_abilities(): void {
        $tools = $this->registry->list_tools();

        // Read-only tools get readonly annotation
        $readonly_tools = [
            'get-site-info',
            'search-knowledge',
            'list-conversations',
            'get-conversation',
            'get-analytics',
        ];

        foreach ($tools as $schema) {
            $name = $schema['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            // Abilities API requires lowercase alphanumeric, dashes, and slashes only
            $ability_name = str_replace('_', '-', $name);
            $ability_id   = self::CATEGORY . '/' . $ability_name;

            $input = $this->normalize_schema($schema['inputSchema'] ?? []);

            wp_register_ability($ability_id, [
                'label'               => $this->make_label($name),
                'description'         => $schema['description'] ?? '',
                'category'            => self::CATEGORY,
                'input_schema'        => $input,
                'output_schema'       => [
                    'type'       => 'object',
                    'properties' => [
                        'result' => [
                            'type'        => 'object',
                            'description' => 'Tool execution result.',
                        ],
                    ],
                ],
                'execute_callback'    => $this->make_executor($name),
                'permission_callback' => [$this, 'check_permission'],
                'meta'                => [
                    'show_in_rest' => true,
                    'annotations'  => [
                        'readonly' => in_array($ability_name, $readonly_tools, true),
                    ],
                ],
            ]);
        }
    }

    /**
     * Permission callback: require manage_options capability.
     *
     * The MCP Adapter respects this check, ensuring AI agents
     * can only execute tools if the authenticated user is an admin.
     *
     * @return bool
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Create an execute callback for a given tool name.
     *
     * @param string $name Tool name.
     * @return callable
     */
    private function make_executor(string $name): callable {
        $registry = $this->registry;

        return function ($input) use ($name, $registry) {
            $args = is_array($input) ? $input : [];
            $result = $registry->call_tool($name, $args);

            if (is_wp_error($result)) {
                return $result;
            }

            return ['result' => $result];
        };
    }

    /**
     * Convert a tool name to a human-readable label.
     *
     * @param string $name Tool name (e.g., 'search_knowledge').
     * @return string Label (e.g., 'Search Knowledge').
     */
    private function make_label(string $name): string {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Normalize MCP inputSchema to Abilities API format.
     *
     * The MCP schema uses (object)[] for empty properties, which needs
     * to be converted to a proper array for the Abilities API.
     *
     * @param array $schema MCP inputSchema.
     * @return array Normalized schema.
     */
    private function normalize_schema($schema): array {
        if (!is_array($schema)) {
            return [];
        }

        // Convert stdClass properties to array
        if (isset($schema['properties']) && $schema['properties'] instanceof \stdClass) {
            $schema['properties'] = (array) $schema['properties'];
        }

        return $schema;
    }
}
