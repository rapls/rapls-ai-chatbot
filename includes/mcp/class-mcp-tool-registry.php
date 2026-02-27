<?php
/**
 * MCP Tool Registry
 *
 * Manages registration, listing, and execution of MCP tools.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_MCP_Tool_Registry {

    /**
     * Registered tools: name => ['schema' => array, 'handler' => callable]
     *
     * @var array<string, array{schema: array, handler: callable}>
     */
    private array $tools = [];

    /**
     * Register a tool.
     *
     * @param string   $name    Unique tool name.
     * @param array    $schema  Tool schema (name, description, inputSchema).
     * @param callable $handler Function that receives (array $args) and returns array|WP_Error.
     */
    public function register(string $name, array $schema, callable $handler): void {
        $this->tools[$name] = [
            'schema'  => $schema,
            'handler' => $handler,
        ];
    }

    /**
     * List all registered tools (for tools/list response).
     *
     * @return array List of tool schemas.
     */
    public function list_tools(): array {
        $list = [];
        foreach ($this->tools as $tool) {
            $list[] = $tool['schema'];
        }
        return $list;
    }

    /**
     * Call a tool by name.
     *
     * @param string $name Tool name.
     * @param array  $args Tool arguments.
     * @return array|WP_Error Tool result or error.
     */
    public function call_tool(string $name, array $args) {
        if (!isset($this->tools[$name])) {
            return new WP_Error('tool_not_found', sprintf(
                /* translators: %s: tool name */
                __('Tool not found: %s', 'rapls-ai-chatbot'),
                $name
            ));
        }

        try {
            return call_user_func($this->tools[$name]['handler'], $args);
        } catch (\Exception $e) {
            return new WP_Error('tool_error', $e->getMessage());
        }
    }
}
