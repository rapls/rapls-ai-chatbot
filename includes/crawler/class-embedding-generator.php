<?php
/**
 * Embedding generator - OpenAI / Gemini embedding API client
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Embedding_Generator {

    /**
     * Embedding provider ('openai' | 'gemini')
     */
    private string $provider = '';

    /**
     * Decrypted API key
     */
    private string $api_key = '';

    /**
     * Embedding model name
     */
    private string $model = '';

    /**
     * Embedding dimensions
     */
    private int $dimensions = 0;

    /**
     * Base URL for the generic OpenAI-compatible embedding endpoint
     * (only used when $provider === 'compat').
     */
    private string $base_url = '';

    /**
     * Per-item failure reasons from the most recent generate_batch() call,
     * keyed by the same index as the input $texts array. Values are stable
     * reason codes ('too_large', 'auth', 'rate_limit', 'no_text', 'api_error').
     * Consumed by callers that want to surface why a row did not embed.
     *
     * @var array<int,string>
     */
    private array $last_errors = [];

    /**
     * Constructor - auto-detects provider from settings
     *
     * @param array|null $settings Override settings (null = read from DB)
     */
    public function __construct(?array $settings = null) {
        if ($settings === null) {
            $settings = get_option('raplsaich_settings', []);
        }

        if (empty($settings['embedding_enabled'])) {
            return;
        }

        $embedding_provider = $settings['embedding_provider'] ?? 'auto';
        $chat_provider = $settings['ai_provider'] ?? 'openai';

        if ($embedding_provider === 'auto') {
            $this->auto_detect_provider($settings, $chat_provider);
        } elseif ($embedding_provider === 'openai') {
            $key = $this->decrypt_key($settings['openai_api_key'] ?? '');
            if ($key) {
                $this->provider = 'openai';
                $this->api_key = $key;
                $this->model = 'text-embedding-3-small';
                $this->dimensions = 1536;
            }
        } elseif ($embedding_provider === 'gemini') {
            $key = $this->decrypt_key($settings['gemini_api_key'] ?? '');
            if ($key) {
                $this->provider = 'gemini';
                $this->api_key = $key;
                $this->model = 'gemini-embedding-001';
                $this->dimensions = 768;
            }
        } elseif ($embedding_provider === 'compat') {
            $this->configure_compat($settings);
        }
    }

    /**
     * Configure this generator for the generic OpenAI-compatible embedding
     * endpoint (Qwen/DashScope, Zhipu, …). Falls back to the chat compat base
     * URL / key when no dedicated embedding endpoint is set.
     *
     * @return bool True if compat embeddings were configured (key + base URL present).
     */
    private function configure_compat(array $settings): bool {
        $key = $this->decrypt_key($settings['compat_api_key'] ?? '');
        $base = trim($settings['compat_embedding_base_url'] ?? '');
        if ($base === '') {
            $base = trim($settings['compat_base_url'] ?? '');
        }
        if (!$key || $base === '') {
            return false;
        }
        $this->provider   = 'compat';
        $this->api_key    = $key;
        $this->base_url   = function_exists('raplsaich_normalize_compat_base_url')
            ? raplsaich_normalize_compat_base_url($base)
            : rtrim($base, '/');
        $this->model      = trim($settings['compat_embedding_model'] ?? '') ?: 'text-embedding-v3';
        $this->dimensions = max(1, (int) ($settings['compat_embedding_dimensions'] ?? 1024)) ?: 1024;
        return true;
    }

    /**
     * Auto-detect provider from chat provider settings
     */
    private function auto_detect_provider(array $settings, string $chat_provider): void {
        // When chatting through the OpenAI-compatible provider (Qwen/DashScope, …),
        // use that same vendor for embeddings — DeepSeek/Moonshot have no embedding
        // API, but Qwen/Zhipu do, and it keeps everything on one account.
        if ($chat_provider === 'compat' && $this->configure_compat($settings)) {
            return;
        }

        // Preferred order: match chat provider first, then try all available keys
        $providers_to_try = ['openai', 'gemini'];
        if ($chat_provider === 'gemini') {
            $providers_to_try = ['gemini', 'openai'];
        }

        foreach ($providers_to_try as $provider) {
            $key_name = $provider . '_api_key';
            $key = $this->decrypt_key($settings[$key_name] ?? '');
            if ($key) {
                $this->provider = $provider;
                $this->api_key = $key;
                if ($provider === 'openai') {
                    $this->model = 'text-embedding-3-small';
                    $this->dimensions = 1536;
                } else {
                    $this->model = 'gemini-embedding-001';
                    $this->dimensions = 768;
                }
                return;
            }
        }
    }

    /**
     * Check if embedding generation is configured and ready
     */
    public function is_configured(): bool {
        return $this->provider !== '' && $this->api_key !== '';
    }

    /**
     * Generate embedding for a single text
     *
     * @param string $text Input text
     * @return array|null Float array or null on failure
     */
    public function generate(string $text): ?array {
        if (!$this->is_configured()) {
            return null;
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $batch = $this->generate_batch([$text]);
        return $batch[0] ?? null;
    }

    /**
     * Generate embeddings for multiple texts in one API call.
     *
     * On a batch failure the request is retried one row at a time so that a
     * single oversized/invalid document cannot silently sink the whole batch:
     * good rows still embed, and the offending row gets a reason recorded in
     * get_last_errors(). Auth / rate-limit failures apply to every row, so
     * they are recorded without the per-row retry.
     *
     * @param string[] $texts Array of input texts
     * @return array Array of float arrays (null for failed items)
     */
    public function generate_batch(array $texts): array {
        $this->last_errors = [];

        if (!$this->is_configured() || empty($texts)) {
            return [];
        }

        $results = array_fill(0, count($texts), null);

        // Filter empty texts, keeping indices. Empty rows have nothing to embed.
        $valid = [];
        foreach ($texts as $i => $text) {
            $text = trim($text);
            if ($text === '') {
                $this->last_errors[$i] = 'no_text';
            } else {
                $valid[$i] = $text;
            }
        }

        if (empty($valid)) {
            return $results;
        }

        $indices      = array_keys($valid);
        $values       = array_values($valid);
        $size         = $this->chunk_size();
        $chunks       = array_chunk($values, $size);
        $index_chunks = array_chunk($indices, $size);

        foreach ($chunks as $ci => $chunk) {
            $res = $this->send_request($chunk);

            if (!empty($res['ok'])) {
                foreach ($chunk as $pos => $unused) {
                    $gidx = $index_chunks[$ci][$pos];
                    if (isset($res['vectors'][$pos])) {
                        $results[$gidx] = $res['vectors'][$pos];
                    } else {
                        // Request succeeded overall but this row came back empty.
                        $this->last_errors[$gidx] = 'api_error';
                    }
                }
                continue;
            }

            $this->log_embedding_error($res);
            $batch_code = $this->classify_error((int) $res['status'], (string) $res['error']);

            // Conditions that fail the whole request identically for every row —
            // isolating would only fire N pointless (and possibly slow) retries:
            //   - a single-row chunk (nothing to isolate),
            //   - auth / rate-limit (applies to every row),
            //   - a transport failure with no HTTP response (status 0 = outage
            //     or timeout — the API was never reached).
            if (count($chunk) === 1
                || (int) $res['status'] === 0
                || in_array($batch_code, ['auth', 'rate_limit'], true)) {
                foreach ($chunk as $pos => $unused) {
                    $this->last_errors[$index_chunks[$ci][$pos]] = $batch_code;
                }
                continue;
            }

            // Ambiguous batch failure (typically one oversized row rejecting the
            // whole request): retry each row alone to let the good ones through
            // and pin the reason on the row(s) that actually fail.
            foreach ($chunk as $pos => $single) {
                $gidx = $index_chunks[$ci][$pos];
                $one  = $this->send_request([$single]);
                if (!empty($one['ok']) && isset($one['vectors'][0])) {
                    $results[$gidx] = $one['vectors'][0];
                } else {
                    $this->log_embedding_error($one);
                    $this->last_errors[$gidx] = $this->classify_error((int) $one['status'], (string) $one['error']);
                }
            }
        }

        return $results;
    }

    /**
     * Per-item failure reasons from the most recent generate_batch() call,
     * keyed by the input index. See $last_errors for the reason codes.
     *
     * @return array<int,string>
     */
    public function get_last_errors(): array {
        return $this->last_errors;
    }

    /**
     * Provider batch size (inputs per request).
     */
    private function chunk_size(): int {
        // DashScope caps embedding batches at 10; OpenAI/Gemini handle 100 fine.
        return $this->provider === 'compat' ? 10 : 100;
    }

    /**
     * Map an HTTP status + provider error message to a stable reason code.
     */
    private function classify_error(int $status, string $message): string {
        $m = strtolower($message);

        // Token / context-length overflow — the document is too large to embed
        // in a single request. This is the case that most needs surfacing.
        $too_large = [
            'maximum context length', 'context length', 'reduce the length',
            'reduce your', 'too long', 'too many tokens', 'tokens per',
            'string too long', 'maximum input', 'input is too large',
            'exceeds the maximum', 'max_tokens',
        ];
        if ($m !== '') {
            foreach ($too_large as $needle) {
                if (strpos($m, $needle) !== false) {
                    return 'too_large';
                }
            }
        }

        if ($status === 401 || $status === 403
            || strpos($m, 'api key') !== false
            || strpos($m, 'unauthorized') !== false
            || strpos($m, 'invalid authentication') !== false) {
            return 'auth';
        }

        if ($status === 429
            || strpos($m, 'rate limit') !== false
            || strpos($m, 'quota') !== false) {
            return 'rate_limit';
        }

        return 'api_error';
    }

    /**
     * Log an embedding request failure when WP_DEBUG is on.
     *
     * @param array{status?:int,error?:string} $res
     */
    private function log_embedding_error(array $res): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status = (int) ($res['status'] ?? 0);
            $msg    = (string) ($res['error'] ?? 'Unknown error');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("RAPLSAICH Embedding API error (HTTP {$status}): {$msg}");
        }
    }

    /**
     * Send one embedding request for the given inputs and normalize the result.
     *
     * @param string[] $inputs 0-indexed input texts.
     * @return array{ok:bool,status:int,error:string,vectors:array<int,array>} vectors keyed by input position.
     */
    private function send_request(array $inputs): array {
        if ($this->provider === 'openai') {
            return $this->request_openai_like($inputs, 'https://api.openai.com/v1/embeddings', false);
        }
        if ($this->provider === 'compat') {
            if ($this->base_url === '') {
                return ['ok' => false, 'status' => 0, 'error' => 'No embedding base URL configured', 'vectors' => []];
            }
            return $this->request_openai_like($inputs, $this->base_url . '/embeddings', true);
        }
        if ($this->provider === 'gemini') {
            return $this->request_gemini($inputs);
        }
        return ['ok' => false, 'status' => 0, 'error' => 'Unsupported embedding provider', 'vectors' => []];
    }

    /**
     * OpenAI / OpenAI-compatible embeddings request (same request/response shape).
     *
     * @param string[] $inputs   0-indexed input texts.
     * @param string   $url      Full embeddings endpoint URL.
     * @param bool     $is_compat Pass the `dimensions` param (DashScope v3/v4).
     * @return array{ok:bool,status:int,error:string,vectors:array<int,array>}
     */
    private function request_openai_like(array $inputs, string $url, bool $is_compat): array {
        $payload = [
            'input' => array_values($inputs),
            'model' => $this->model,
        ];
        if ($is_compat && $this->dimensions > 0) {
            $payload['dimensions'] = $this->dimensions;
        }

        /** This filter is documented in includes/ai-providers/class-openai-provider.php */
        $timeout = (int) apply_filters('raplsaich_api_timeout', 30);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 0, 'error' => $response->get_error_message(), 'vectors' => []];
        }

        $status    = (int) wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || empty($resp_body['data'])) {
            $err = is_array($resp_body) ? (string) ($resp_body['error']['message'] ?? 'Unknown error') : 'Unknown error';
            return ['ok' => false, 'status' => $status, 'error' => $err, 'vectors' => []];
        }

        $vectors = [];
        foreach ($resp_body['data'] as $item) {
            $idx = $item['index'] ?? null;
            if ($idx !== null && isset($item['embedding'])) {
                $vectors[(int) $idx] = $item['embedding'];
            }
        }

        return ['ok' => true, 'status' => $status, 'error' => '', 'vectors' => $vectors];
    }

    /**
     * Gemini batchEmbedContents request.
     *
     * @param string[] $inputs 0-indexed input texts.
     * @return array{ok:bool,status:int,error:string,vectors:array<int,array>}
     */
    private function request_gemini(array $inputs): array {
        $requests = [];
        foreach (array_values($inputs) as $text) {
            $requests[] = [
                'model'                => 'models/' . $this->model,
                'content'              => ['parts' => [['text' => $text]]],
                'outputDimensionality' => $this->dimensions,
            ];
        }

        // Key in the x-goog-api-key header (not ?key=) — Google's recommended
        // method; keeps the key out of logs and works with both the legacy
        // "AIza" and the new "AQ." auth keys.
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':batchEmbedContents';

        /** This filter is documented in includes/ai-providers/class-openai-provider.php */
        $timeout = (int) apply_filters('raplsaich_api_timeout', 30);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $this->api_key,
            ],
            'body'    => wp_json_encode(['requests' => $requests]),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 0, 'error' => $response->get_error_message(), 'vectors' => []];
        }

        $status    = (int) wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || empty($resp_body['embeddings'])) {
            $err = is_array($resp_body) ? (string) ($resp_body['error']['message'] ?? 'Unknown error') : 'Unknown error';
            return ['ok' => false, 'status' => $status, 'error' => $err, 'vectors' => []];
        }

        $vectors = [];
        foreach ($resp_body['embeddings'] as $idx => $item) {
            if (isset($item['values'])) {
                $vectors[(int) $idx] = $item['values'];
            }
        }

        return ['ok' => true, 'status' => $status, 'error' => '', 'vectors' => $vectors];
    }

    /**
     * Get the embedding model name
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Get embedding dimensions
     */
    public function get_dimensions(): int {
        return $this->dimensions;
    }

    /**
     * Get the active provider name
     */
    public function get_provider(): string {
        return $this->provider;
    }

    /**
     * Get available embedding providers for settings UI
     */
    public static function get_available_providers(): array {
        return [
            'auto'   => __('Auto (use chat provider API key)', 'rapls-ai-chatbot'),
            'openai' => 'OpenAI (text-embedding-3-small)',
            'gemini' => 'Gemini (gemini-embedding-001)',
            'compat' => __('OpenAI-compatible (Qwen/DashScope, Zhipu, …)', 'rapls-ai-chatbot'),
        ];
    }

    /**
     * Decrypt an API key from settings.
     *
     * Replicates the decryption logic used by RAPLSAICH_Admin and RAPLSAICH_REST_Controller.
     * Supports AES-256-GCM (encg:) and AES-256-CBC (enc:) formats, plus unencrypted keys.
     *
     * @param string $encrypted Encrypted or raw API key
     * @return string Decrypted key, or empty string on failure
     */
    private function decrypt_key(string $encrypted): string {
        return raplsaich_decrypt_api_key($encrypted);
    }
}
