<?php
/**
 * MCP Tool: search_knowledge
 *
 * Searches the knowledge base and site content index.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_MCP_Tool_Search_Knowledge {

    /**
     * Register this tool with the registry.
     *
     * @param WPAIC_MCP_Tool_Registry $registry Tool registry.
     */
    public function register(WPAIC_MCP_Tool_Registry $registry): void {
        $registry->register('search_knowledge', $this->get_schema(), [$this, 'execute']);
    }

    /**
     * Tool schema for tools/list.
     *
     * @return array
     */
    private function get_schema(): array {
        return [
            'name'        => 'search_knowledge',
            'description' => 'Search the knowledge base and site content index. Returns matching articles, FAQs, and indexed pages.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'Search query text.',
                    ],
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Maximum number of results to return (default: 5).',
                        'default'     => 5,
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param array $args Tool arguments.
     * @return array Search results.
     */
    public function execute(array $args): array {
        $query = sanitize_text_field($args['query'] ?? '');
        $limit = absint($args['limit'] ?? 5);

        if (empty($query)) {
            return ['error' => __('Query is required.', 'rapls-ai-chatbot')];
        }

        if ($limit < 1 || $limit > 50) {
            $limit = 5;
        }

        $search_engine = new WPAIC_Search_Engine();
        $results = $search_engine->search($query, $limit);

        $formatted = [];
        foreach ($results as $item) {
            $entry = [
                'type'    => $item['type'] ?? 'unknown',
                'title'   => $item['title'] ?? '',
                'content' => $item['content'] ?? '',
                'score'   => $item['score'] ?? 0,
            ];

            if (!empty($item['url'])) {
                $entry['url'] = $item['url'];
            }

            if (isset($item['category'])) {
                $entry['category'] = $item['category'];
            }

            $formatted[] = $entry;
        }

        return [
            'results' => $formatted,
            'total'   => count($formatted),
        ];
    }
}
