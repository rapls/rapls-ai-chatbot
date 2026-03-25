<?php
/**
 * MCP (Model Context Protocol) Server
 *
 * JSON-RPC 2.0 dispatcher over WordPress REST API.
 * Streamable HTTP transport (POST only, synchronous responses).
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_MCP_Server {

    /**
     * REST namespace
     */
    private string $namespace = 'rapls-ai-chatbot/v1';

    /**
     * Tool registry
     */
    private RAPLSAICH_MCP_Tool_Registry $registry;

    /**
     * Protocol version
     */
    private string $protocol_version = '2024-11-05';

    /**
     * Constructor
     */
    public function __construct() {
        $this->registry = new RAPLSAICH_MCP_Tool_Registry();
        $this->register_default_tools();

        // Pro tools + Abilities Bridge deferred until rest_api_init
        // so Pro plugin has time to register its raplsaich_mcp_register_tools listener.
        add_action('rest_api_init', [$this, 'late_init'], 5);
    }

    /**
     * Late initialization: register Pro/third-party tools and Abilities Bridge.
     *
     * Runs on rest_api_init (priority 5) so Pro plugin's plugins_loaded hook
     * has already fired and registered its raplsaich_mcp_register_tools listener.
     */
    public function late_init(): void {
        /**
         * Allow Pro and third-party plugins to register additional MCP tools.
         *
         * @param RAPLSAICH_MCP_Tool_Registry $registry Tool registry instance.
         */
        do_action('raplsaich_mcp_register_tools', $this->registry);

        // Bridge MCP tools to WordPress Abilities API (WP 6.9+)
        require_once __DIR__ . '/class-abilities-bridge.php';
        (new RAPLSAICH_Abilities_Bridge($this->registry))->init();
    }

    /**
     * Register REST routes
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/mcp', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_request'],
            'permission_callback' => [$this, 'check_mcp_auth'],
        ]);
    }

    /**
     * Verify MCP API key from Authorization header.
     *
     * @param WP_REST_Request $request REST request.
     * @return bool|WP_Error
     */
    public function check_mcp_auth(WP_REST_Request $request) {
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header)) {
            return new WP_Error(
                'rest_forbidden',
                __('MCP API key is required.', 'rapls-ai-chatbot'),
                ['status' => 401]
            );
        }

        // Extract Bearer token
        if (strpos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid authorization format. Use: Bearer <api_key>', 'rapls-ai-chatbot'),
                ['status' => 401]
            );
        }

        $provided_key = substr($auth_header, 7);
        if (empty($provided_key)) {
            return new WP_Error(
                'rest_forbidden',
                __('MCP API key is required.', 'rapls-ai-chatbot'),
                ['status' => 401]
            );
        }

        // Verify against stored hash
        $settings = get_option('raplsaich_settings', []);
        $stored_hash = $settings['mcp_api_key_hash'] ?? '';

        if (empty($stored_hash) || !wp_check_password($provided_key, $stored_hash)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid MCP API key.', 'rapls-ai-chatbot'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Handle incoming JSON-RPC 2.0 request.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function handle_request(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_body();
        $data = json_decode($body, true);

        // Parse error
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->jsonrpc_error(null, -32700, 'Parse error');
        }

        // Validate JSON-RPC structure
        if (empty($data['jsonrpc']) || $data['jsonrpc'] !== '2.0' || empty($data['method'])) {
            return $this->jsonrpc_error(
                $data['id'] ?? null,
                -32600,
                'Invalid Request'
            );
        }

        $id     = $data['id'] ?? null;
        $method = $data['method'];
        $params = $data['params'] ?? [];

        // Dispatch by method
        switch ($method) {
            case 'initialize':
                return $this->handle_initialize($id, $params);

            case 'notifications/initialized':
                // Client acknowledgment — no response needed for notifications
                return new WP_REST_Response(null, 202);

            case 'tools/list':
                return $this->handle_tools_list($id);

            case 'tools/call':
                return $this->handle_tools_call($id, $params);

            case 'ping':
                return $this->jsonrpc_success($id, (object) []);

            default:
                return $this->jsonrpc_error($id, -32601, 'Method not found');
        }
    }

    /**
     * Handle initialize request.
     *
     * @param mixed $id    JSON-RPC request ID.
     * @param array $params Request params.
     * @return WP_REST_Response
     */
    private function handle_initialize($id, array $params): WP_REST_Response {
        return $this->jsonrpc_success($id, [
            'protocolVersion' => $this->protocol_version,
            'serverInfo'      => [
                'name'    => 'rapls-ai-chatbot',
                'version' => defined('RAPLSAICH_VERSION') ? RAPLSAICH_VERSION : '1.0.0',
            ],
            'capabilities'    => [
                'tools' => (object) [],
            ],
        ]);
    }

    /**
     * Handle tools/list request.
     *
     * @param mixed $id JSON-RPC request ID.
     * @return WP_REST_Response
     */
    private function handle_tools_list($id): WP_REST_Response {
        return $this->jsonrpc_success($id, [
            'tools' => $this->registry->list_tools(),
        ]);
    }

    /**
     * Handle tools/call request.
     *
     * @param mixed $id     JSON-RPC request ID.
     * @param array $params Request params (name, arguments).
     * @return WP_REST_Response
     */
    private function handle_tools_call($id, array $params): WP_REST_Response {
        $tool_name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (empty($tool_name)) {
            return $this->jsonrpc_error($id, -32602, 'Invalid params: tool name is required');
        }

        if (!is_array($arguments)) {
            $arguments = [];
        }

        $result = $this->registry->call_tool($tool_name, $arguments);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $jsonrpc_code = $error_code === 'tool_not_found' ? -32602 : -32603;
            return $this->jsonrpc_error($id, $jsonrpc_code, $result->get_error_message());
        }

        // Tool-level errors: return as MCP isError result instead of success
        if (is_array($result) && isset($result['error']) && !isset($result['content'])) {
            return $this->jsonrpc_success($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $result['error'],
                    ],
                ],
                'isError' => true,
            ]);
        }

        return $this->jsonrpc_success($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ],
            ],
        ]);
    }

    /**
     * Build a JSON-RPC 2.0 success response.
     *
     * @param mixed $id     Request ID.
     * @param mixed $result Result data.
     * @return WP_REST_Response
     */
    private function jsonrpc_success($id, $result): WP_REST_Response {
        return new WP_REST_Response([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ], 200);
    }

    /**
     * Build a JSON-RPC 2.0 error response.
     *
     * @param mixed  $id      Request ID.
     * @param int    $code    JSON-RPC error code.
     * @param string $message Error message.
     * @param mixed  $data    Optional error data.
     * @return WP_REST_Response
     */
    private function jsonrpc_error($id, int $code, string $message, $data = null): WP_REST_Response {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return new WP_REST_Response([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $error,
        ], 200);
    }

    /**
     * Register default MCP tools.
     */
    private function register_default_tools(): void {
        $tools_dir = RAPLSAICH_PLUGIN_DIR . 'includes/mcp/tools/';

        require_once $tools_dir . 'class-tool-search-knowledge.php';
        require_once $tools_dir . 'class-tool-list-conversations.php';
        require_once $tools_dir . 'class-tool-get-conversation.php';
        require_once $tools_dir . 'class-tool-send-message.php';
        require_once $tools_dir . 'class-tool-get-site-info.php';

        (new RAPLSAICH_MCP_Tool_Search_Knowledge())->register($this->registry);
        (new RAPLSAICH_MCP_Tool_List_Conversations())->register($this->registry);
        (new RAPLSAICH_MCP_Tool_Get_Conversation())->register($this->registry);
        (new RAPLSAICH_MCP_Tool_Send_Message())->register($this->registry);
        (new RAPLSAICH_MCP_Tool_Get_Site_Info())->register($this->registry);
    }
}
