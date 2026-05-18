<?php
/**
 * WordPress AI Client provider (WordPress 7.0+).
 *
 * Routes chat requests through core's wp_ai_client_prompt() builder,
 * which dispatches to whichever provider the site administrator has
 * configured under Settings → Connectors. No API key is managed by
 * this plugin for this provider — credentials live in Connectors.
 *
 * Gracefully unusable when wp_ai_client_prompt() is missing
 * (i.e. WordPress < 7.0 or AI Client disabled).
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_WPAI_Provider implements RAPLSAICH_AI_Provider_Interface {

    /**
     * Model preference is delegated to Settings → Connectors, so this is
     * accepted by the interface but not forwarded to the builder.
     */
    private string $model = '';

    /**
     * Whether the core AI Client is loaded on this WP install.
     */
    public static function is_available(): bool {
        return function_exists('wp_ai_client_prompt');
    }

    public function set_api_key(string $key): void {
        // No-op: WP AI Client reads credentials from the Connectors registry.
    }

    public function set_model(string $model): void {
        $this->model = $model;
    }

    public function get_name(): string {
        return 'wpai';
    }

    /**
     * Curated cross-provider model list. The empty value defers to the
     * Connectors-configured default; any non-empty value is passed to
     * using_model_preference() as a hint — the AI Client falls back to
     * any compatible model if the preference is not available.
     */
    public function get_available_models(): array {
        return [
            ''                       => __('Auto (Recommended — managed via Settings → Connectors)', 'rapls-ai-chatbot'),
            // OpenAI
            'gpt-5'                  => 'OpenAI: GPT-5',
            'gpt-5-mini'             => 'OpenAI: GPT-5 mini',
            'gpt-4.1'                => 'OpenAI: GPT-4.1',
            'gpt-4.1-mini'           => 'OpenAI: GPT-4.1 mini',
            'gpt-4o'                 => 'OpenAI: GPT-4o',
            'gpt-4o-mini'            => 'OpenAI: GPT-4o mini',
            // Anthropic
            'claude-opus-4-7'        => 'Anthropic: Claude Opus 4.7',
            'claude-sonnet-4-6'      => 'Anthropic: Claude Sonnet 4.6',
            'claude-haiku-4-5'       => 'Anthropic: Claude Haiku 4.5',
            // Google
            'gemini-2.5-pro'         => 'Google: Gemini 2.5 Pro',
            'gemini-2.5-flash'       => 'Google: Gemini 2.5 Flash',
            'gemini-2.0-flash'       => 'Google: Gemini 2.0 Flash',
        ];
    }

    public function fetch_models_from_api(): array {
        return $this->get_available_models();
    }

    /**
     * Availability check used by the "Test Connection" flow. Returns true
     * if the builder reports that at least one Connector can fulfil a
     * text-generation request.
     */
    public function validate_api_key(): bool {
        if (!self::is_available()) {
            return false;
        }
        try {
            // is_supported_for_text_generation() evaluates against the current
            // builder state; passing an empty prompt sometimes makes the SDK
            // report "not supported" even when a working Connector is in fact
            // registered. Use a short non-empty placeholder so the check
            // reflects Connector availability, not prompt emptiness.
            $builder = wp_ai_client_prompt('ping');
            if (is_callable([$builder, 'is_supported_for_text_generation'])) {
                return (bool) $builder->is_supported_for_text_generation();
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function send_message(array $messages, array $options = []): array {
        if (!self::is_available()) {
            throw new Exception(esc_html__('WordPress AI Client is unavailable. Requires WordPress 7.0 or later.', 'rapls-ai-chatbot'));
        }

        [$system, $history, $prompt] = $this->split_messages($messages);

        // First attempt — apply every configured parameter we know about.
        $builder = $this->build_prompt($prompt, $system, $history, $options, false);
        $text = $builder->generate_text();

        // Retry path — Connectors may route to a model (GPT-5, o-series, …)
        // that rejects custom temperature. Detect that exact failure and
        // retry once without temperature so the user does not see a 400.
        if (is_wp_error($text)
            && stripos($text->get_error_message(), 'temperature') !== false
            && isset($options['temperature'])
        ) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH WPAI: retrying without temperature (target model rejected custom value)');
            }
            $builder = $this->build_prompt($prompt, $system, $history, $options, true);
            $text = $builder->generate_text();
        }

        if (is_wp_error($text)) {
            $status = 0;
            $data = $text->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }
            throw new Exception(esc_html($text->get_error_message()), $status);
        }

        $content = (string) $text;
        if ($content === '') {
            throw new Exception(esc_html__('WordPress AI Client returned an empty response.', 'rapls-ai-chatbot'));
        }

        return [
            'content'       => $content,
            'tokens_used'   => 0,
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'model'         => $this->model,
            'provider'      => $this->get_name(),
        ];
    }

    /**
     * Build a configured WP_AI_Client_Prompt_Builder for a given chat turn.
     *
     * Extracted from send_message() so we can call it twice — once with
     * temperature, and again without it — on the retry path described in
     * send_message(). The function is intentionally side-effect-free
     * apart from on the returned builder.
     *
     * @param string $prompt           Trailing user prompt text.
     * @param string $system           Merged system instruction.
     * @param array  $history          Plugin-internal history turns.
     * @param array  $options          send_message $options.
     * @param bool   $skip_temperature Drop the using_temperature() call.
     * @return object Configured builder instance.
     * @throws Exception If no Connector is registered for text generation.
     */
    private function build_prompt(string $prompt, string $system, array $history, array $options, bool $skip_temperature) {
        $builder = wp_ai_client_prompt($prompt);

        if (is_callable([$builder, 'is_supported_for_text_generation'])
            && !$builder->is_supported_for_text_generation()
        ) {
            throw new Exception(esc_html__('No AI Connector is configured for text generation. Open Settings → Connectors and connect an AI provider.', 'rapls-ai-chatbot'));
        }

        if ($system !== '' && is_callable([$builder, 'using_system_instruction'])) {
            $builder->using_system_instruction($system);
        }

        if (!empty($history) && is_callable([$builder, 'with_history'])) {
            $this->apply_history($builder, $history);
        }

        if (!$skip_temperature
            && isset($options['temperature'])
            && is_callable([$builder, 'using_temperature'])
        ) {
            $builder->using_temperature((float) $options['temperature']);
        }

        if (isset($options['max_tokens']) && is_callable([$builder, 'using_max_tokens'])) {
            $builder->using_max_tokens((int) $options['max_tokens']);
        }

        // using_model_preference() is documented as variadic strings
        // ("preference, not a requirement" — silently falls back to any
        // compatible model). The exact accepted shape is not pinned down,
        // so wrap defensively: if the SDK rejects the shape, log and
        // continue without the preference rather than fail the chat turn.
        if ($this->model !== '' && is_callable([$builder, 'using_model_preference'])) {
            try {
                $builder->using_model_preference($this->model);
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'RAPLSAICH WPAI: using_model_preference(%s) threw %s: %s',
                        $this->model,
                        get_class($e),
                        $e->getMessage()
                    ));
                }
            }
        }

        return $builder;
    }

    /**
     * Convert our plugin-internal ChatML-style history to the AI Client's
     * Message DTO objects and pass them to $builder->with_history().
     *
     * WP_AI_Client_Prompt_Builder::with_history() is declared as
     *   with_history(Message ...$messages)
     * — variadic over WordPress\AiClient\Messages\DTO\Message. Raw arrays
     * are silently dropped (or trigger a TypeError on the underlying SDK
     * call), which is what caused multi-turn context to be lost.
     *
     * The underlying enum allows only 'user' and 'model' roles, so we map
     * our 'user' to UserMessage and any other role ('assistant' / 'bot')
     * to ModelMessage.
     *
     * Defensive: if the DTO classes are not autoloadable on this install,
     * skip history rather than failing the chat turn.
     *
     * @param object $builder Builder instance (kept loose-typed so
     *                        non-WP-7.0 environments do not break the file).
     * @param array  $history Plugin-internal turns: [['role'=>..., 'content'=>...], ...]
     */
    private function apply_history($builder, array $history): void {
        $part_class  = 'WordPress\\AiClient\\Messages\\DTO\\MessagePart';
        $user_class  = 'WordPress\\AiClient\\Messages\\DTO\\UserMessage';
        $model_class = 'WordPress\\AiClient\\Messages\\DTO\\ModelMessage';

        if (!class_exists($part_class) || !class_exists($user_class) || !class_exists($model_class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH WPAI: AI Client message DTO classes not autoloadable; history skipped.');
            }
            return;
        }

        try {
            $objects = [];
            foreach ($history as $turn) {
                $content = (string) ($turn['content'] ?? '');
                if ($content === '') {
                    continue;
                }
                $part = new $part_class($content);
                $role = ($turn['role'] ?? '') === 'user' ? 'user' : 'model';
                $objects[] = ($role === 'user')
                    ? new $user_class([$part])
                    : new $model_class([$part]);
            }
            if (!empty($objects)) {
                $builder->with_history(...$objects);
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH WPAI: with_history() failed: ' . get_class($e) . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Split internal ChatML-style $messages into the three pieces the WP AI
     * Client builder consumes: a merged system instruction, history (all
     * non-system messages except the trailing user turn), and the trailing
     * user prompt text.
     *
     * @return array{0:string,1:array,2:string} [system, history, prompt]
     */
    private function split_messages(array $messages): array {
        $system_parts = [];
        $non_system = [];
        foreach ($messages as $msg) {
            $role = is_string($msg['role'] ?? null) ? $msg['role'] : '';
            $content = $this->stringify_content($msg['content'] ?? '');
            if ($role === 'system') {
                if ($content !== '') {
                    $system_parts[] = $content;
                }
            } else {
                $non_system[] = ['role' => $role, 'content' => $content];
            }
        }

        $prompt = '';
        if (!empty($non_system)) {
            $last = end($non_system);
            if (($last['role'] ?? '') === 'user') {
                $prompt = $last['content'];
                array_pop($non_system);
            }
        }

        return [implode("\n\n", $system_parts), $non_system, $prompt];
    }

    /**
     * Flatten multimodal content parts down to plain text. WP AI Client
     * has its own file/with_file() pipeline for attachments — we only feed
     * it text here, matching the rest of this plugin's chat flow.
     */
    private function stringify_content($content): string {
        if (is_string($content)) {
            return $content;
        }
        if (!is_array($content)) {
            return '';
        }
        $out = [];
        foreach ($content as $part) {
            if (!is_array($part)) {
                continue;
            }
            if (isset($part['text']) && is_string($part['text'])) {
                $out[] = $part['text'];
            } elseif (($part['type'] ?? '') === 'input_text' && isset($part['text'])) {
                $out[] = (string) $part['text'];
            }
        }
        return implode("\n", $out);
    }
}
