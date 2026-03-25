<?php
/**
 * Shared helper functions for Rapls AI Chatbot.
 *
 * Loaded early by rapls-ai-chatbot.php so they are available everywhere.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decrypt an API key (supports AES-256-GCM and legacy AES-256-CBC).
 *
 * This is the SINGLE implementation — all code that decrypts API keys
 * should call this function instead of duplicating the logic.
 *
 * @param string $encrypted Encrypted (or plaintext) key.
 * @return string Decrypted key, or empty string on failure.
 */
function raplsaich_decrypt_api_key(string $encrypted): string {
    if (empty($encrypted)) {
        return '';
    }

    // Return as-is if not encrypted (check known API key prefixes).
    if (
        strpos($encrypted, 'sk-') === 0
        || strpos($encrypted, 'sk-ant-') === 0
        || strpos($encrypted, 'AIza') === 0
        || strpos($encrypted, 'sk-or-') === 0
    ) {
        return $encrypted;
    }

    if (!function_exists('openssl_decrypt')) {
        return '';
    }

    $new_key = hash('sha256', wp_salt('auth'), true);
    $aad     = 'raplsaich_' . wp_parse_url(get_site_url(), PHP_URL_HOST);
    $old_key = wp_salt('auth'); // Legacy fallback

    // --- AES-256-GCM (new format, tamper-resistant) ---
    if (strpos($encrypted, 'encg:') === 0) {
        $data = base64_decode(substr($encrypted, 5), true);
        if ($data === false || strlen($data) <= 28) { // 12 (IV) + 16 (tag) = 28 minimum
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH: API key decryption failed (invalid GCM data). Key may need to be re-entered.');
            }
            return '';
        }

        $iv             = substr($data, 0, 12);
        $tag            = substr($data, 12, 16);
        $encrypted_data = substr($data, 28);

        // Try: normalized key + AAD → normalized key only → legacy key
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $new_key, OPENSSL_RAW_DATA, $iv, $tag);
        }
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-gcm', $old_key, OPENSSL_RAW_DATA, $iv, $tag);
        }

        if ($decrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RAPLSAICH: API key GCM decryption failed (salt may have changed). Please re-enter your API key.');
            }
            return '';
        }
        return $decrypted;
    }

    // --- AES-256-CBC (legacy format) ---
    $raw = $encrypted;
    if (strpos($raw, 'enc:') === 0) {
        $raw = substr($raw, 4);
    }

    $data = base64_decode($raw, true);
    if ($data === false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('RAPLSAICH: API key decryption failed (invalid base64). Key may need to be re-entered.');
        }
        return '';
    }

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    if (strlen($data) <= $iv_length) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('RAPLSAICH: API key decryption failed (data too short). Key may need to be re-entered.');
        }
        return '';
    }

    $iv             = substr($data, 0, $iv_length);
    $encrypted_data = substr($data, $iv_length);

    $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $new_key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $old_key, OPENSSL_RAW_DATA, $iv);
    }

    if ($decrypted === false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('RAPLSAICH: API key decryption failed (salt may have changed). Please re-enter your API key.');
        }
        return '';
    }

    return $decrypted;
}

/**
 * Create an AI provider instance with the correct API key and model.
 *
 * Centralises provider construction so that LINE, MCP, REST, etc.
 * all go through the same code path.
 *
 * @param array       $settings   Plugin settings array (from get_option('raplsaich_settings')).
 * @param array|null  $bot_config Optional per-bot overrides (ai_provider, model).
 * @return RAPLSAICH_AI_Provider_Interface
 */
function raplsaich_create_ai_provider(array $settings, ?array $bot_config = null): RAPLSAICH_AI_Provider_Interface {
    $provider_name = (is_array($bot_config) && !empty($bot_config['ai_provider']))
        ? $bot_config['ai_provider']
        : ($settings['ai_provider'] ?? 'openai');
    $bot_model = is_array($bot_config) ? ($bot_config['model'] ?? '') : '';

    switch ($provider_name) {
        case 'claude':
            $provider = new RAPLSAICH_Claude_Provider();
            $provider->set_api_key(raplsaich_decrypt_api_key($settings['claude_api_key'] ?? ''));
            $provider->set_model(!empty($bot_model) ? $bot_model : ($settings['claude_model'] ?? 'claude-haiku-4-5-20251001'));
            break;

        case 'gemini':
            $provider = new RAPLSAICH_Gemini_Provider();
            $provider->set_api_key(raplsaich_decrypt_api_key($settings['gemini_api_key'] ?? ''));
            $provider->set_model(!empty($bot_model) ? $bot_model : ($settings['gemini_model'] ?? 'gemini-2.0-flash'));
            break;

        case 'openrouter':
            $provider = new RAPLSAICH_OpenRouter_Provider();
            $provider->set_api_key(raplsaich_decrypt_api_key($settings['openrouter_api_key'] ?? ''));
            $provider->set_model(!empty($bot_model) ? $bot_model : ($settings['openrouter_model'] ?? 'openrouter/auto'));
            break;

        default: // openai
            $provider = new RAPLSAICH_OpenAI_Provider();
            $provider->set_api_key(raplsaich_decrypt_api_key($settings['openai_api_key'] ?? ''));
            $provider->set_model(!empty($bot_model) ? $bot_model : ($settings['openai_model'] ?? 'gpt-4o-mini'));
            break;
    }

    return $provider;
}
