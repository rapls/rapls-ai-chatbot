<?php
/**
 * AI Provider Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

interface RAPLSAICH_AI_Provider_Interface {

    /**
     * Set API key
     */
    public function set_api_key(string $key): void;

    /**
     * Set model
     */
    public function set_model(string $model): void;

    /**
     * Send message and get response
     *
     * @param array $messages Message array [['role' => 'user', 'content' => '...'], ...]
     * @param array $options Options ['max_tokens', 'temperature', etc.]
     * @return array ['content' => 'response', 'tokens_used' => 123]
     */
    public function send_message(array $messages, array $options = []): array;

    /**
     * Get available models
     */
    public function get_available_models(): array;

    /**
     * Validate API key
     */
    public function validate_api_key(): bool;

    /**
     * Get provider name
     */
    public function get_name(): string;

    /**
     * Fetch models from API
     *
     * @return array ['model-id' => 'Display Name', ...] or empty array on failure
     */
    public function fetch_models_from_api(): array;
}
