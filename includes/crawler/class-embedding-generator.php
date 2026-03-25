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
                $this->model = 'text-embedding-004';
                $this->dimensions = 768;
            }
        }
    }

    /**
     * Auto-detect provider from chat provider settings
     */
    private function auto_detect_provider(array $settings, string $chat_provider): void {
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
                    $this->model = 'text-embedding-004';
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
     * Generate embeddings for multiple texts in one API call
     *
     * @param string[] $texts Array of input texts
     * @return array Array of float arrays (null for failed items)
     */
    public function generate_batch(array $texts): array {
        if (!$this->is_configured() || empty($texts)) {
            return [];
        }

        // Filter empty texts, keeping indices
        $valid = [];
        foreach ($texts as $i => $text) {
            $text = trim($text);
            if ($text !== '') {
                $valid[$i] = $text;
            }
        }

        if (empty($valid)) {
            return array_fill(0, count($texts), null);
        }

        if ($this->provider === 'openai') {
            return $this->openai_batch($texts, $valid);
        }

        if ($this->provider === 'gemini') {
            return $this->gemini_batch($texts, $valid);
        }

        return array_fill(0, count($texts), null);
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
            'gemini' => 'Gemini (text-embedding-004)',
        ];
    }

    /**
     * OpenAI batch embedding
     */
    private function openai_batch(array $all_texts, array $valid): array {
        $results = array_fill(0, count($all_texts), null);
        $valid_texts = array_values($valid);
        $valid_indices = array_keys($valid);

        // OpenAI supports up to 2048 inputs per request; chunk if needed
        $chunk_size = 100;
        $chunks = array_chunk($valid_texts, $chunk_size);
        $index_chunks = array_chunk($valid_indices, $chunk_size);

        foreach ($chunks as $ci => $chunk) {
            $body = wp_json_encode([
                'input' => $chunk,
                'model' => $this->model,
            ]);

            /** This filter is documented in includes/ai-providers/class-openai-provider.php */
            $timeout = (int) apply_filters('raplsaich_api_timeout', 30);

            $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => $body,
                'timeout' => $timeout,
            ]);

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('RAPLSAICH Embedding API error: ' . $response->get_error_message());
                }
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $resp_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status !== 200 || empty($resp_body['data'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $err_msg = $resp_body['error']['message'] ?? 'Unknown error';
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log("RAPLSAICH Embedding API error (HTTP {$status}): {$err_msg}");
                }
                continue;
            }

            foreach ($resp_body['data'] as $item) {
                $idx = $item['index'] ?? null;
                if ($idx !== null && isset($index_chunks[$ci][$idx])) {
                    $results[$index_chunks[$ci][$idx]] = $item['embedding'];
                }
            }
        }

        return $results;
    }

    /**
     * Gemini batch embedding
     */
    private function gemini_batch(array $all_texts, array $valid): array {
        $results = array_fill(0, count($all_texts), null);
        $valid_texts = array_values($valid);
        $valid_indices = array_keys($valid);

        // Gemini batchEmbedContents supports up to 100 texts per request
        $chunk_size = 100;
        $chunks = array_chunk($valid_texts, $chunk_size);
        $index_chunks = array_chunk($valid_indices, $chunk_size);

        foreach ($chunks as $ci => $chunk) {
            $requests = [];
            foreach ($chunk as $text) {
                $requests[] = [
                    'model'   => 'models/' . $this->model,
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ];
            }

            $body = wp_json_encode(['requests' => $requests]);
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':batchEmbedContents?key=' . $this->api_key;

            /** This filter is documented in includes/ai-providers/class-openai-provider.php */
            $timeout = (int) apply_filters('raplsaich_api_timeout', 30);

            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => $body,
                'timeout' => $timeout,
            ]);

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('RAPLSAICH Gemini Embedding API error: ' . $response->get_error_message());
                }
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $resp_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status !== 200 || empty($resp_body['embeddings'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $err_msg = $resp_body['error']['message'] ?? 'Unknown error';
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log("RAPLSAICH Gemini Embedding API error (HTTP {$status}): {$err_msg}");
                }
                continue;
            }

            foreach ($resp_body['embeddings'] as $idx => $item) {
                if (isset($item['values'], $index_chunks[$ci][$idx])) {
                    $results[$index_chunks[$ci][$idx]] = $item['values'];
                }
            }
        }

        return $results;
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
