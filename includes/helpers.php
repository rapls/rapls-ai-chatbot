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
 * Return the safe upper bound of characters to include as RAG context
 * for the currently configured AI model. Conservative — roughly 25% of the
 * model's token window, so there's room for the system prompt and the
 * response on top.
 *
 * Shared between the Web /chat handler and the LINE channel so both
 * surfaces feed the same volume of knowledge to the model.
 */
function raplsaich_get_max_context_chars(): int {
    $settings = get_option('raplsaich_settings', []);
    $provider = $settings['ai_provider'] ?? 'openai';

    switch ($provider) {
        case 'openai':
            $model = $settings['openai_model'] ?? 'gpt-4o-mini';
            if (strpos($model, 'gpt-4.1') === 0 || preg_match('/^o[1-9]/', $model)) {
                return 40000;
            }
            if (strpos($model, 'gpt-4o') === 0) {
                return 30000;
            }
            if (strpos($model, 'gpt-4-turbo') === 0) {
                return 30000;
            }
            if (strpos($model, 'gpt-4') === 0) {
                return 8000;
            }
            if (strpos($model, 'gpt-3.5') === 0) {
                return 12000;
            }
            return 20000;

        case 'claude':
            return 40000;

        case 'gemini':
            $model = $settings['gemini_model'] ?? 'gemini-2.0-flash';
            if (strpos($model, 'flash-lite') !== false) {
                return 15000;
            }
            return 40000;

        case 'openrouter':
            return 30000;

        default:
            return 20000;
    }
}

/**
 * Inject the current date into the system prompt so the AI can resolve
 * relative time references ("today", "yesterday", "this week", etc.).
 *
 * Hooked at priority 99 on `raplsaich_system_prompt` so it runs after Pro's
 * own filter callbacks (priorities 5/10/12/15) — even if Pro replaces the
 * prompt entirely (e.g. via prompt templates), the date is still prepended.
 *
 * Uses wp_date() so the date follows the site's WordPress timezone, not
 * server time. The prompt is worded explicitly so weak models won't dismiss
 * the date as "fabrication" when the user's own system prompt forbids
 * inventing dates.
 */
/**
 * Inject the glossary (proper-noun protection list) into the system prompt
 * so the AI keeps brand names, product names, and other terms verbatim
 * across all languages.
 *
 * Hooked at priority 98 — runs just before raplsaich_inject_current_date
 * (priority 99), so the date block stays at the very top of the merged
 * prompt and the glossary sits one block below it. Both blocks are no-ops
 * when their respective settings are off.
 */
add_filter('raplsaich_system_prompt', 'raplsaich_inject_glossary', 98);
function raplsaich_inject_glossary($system_prompt) {
    $settings = get_option('raplsaich_settings', []);
    if (empty($settings['glossary_enabled'])) {
        return $system_prompt;
    }
    $entries = is_array($settings['glossary'] ?? null) ? $settings['glossary'] : [];
    if (empty($entries)) {
        return $system_prompt;
    }

    $lines = [];
    foreach ($entries as $row) {
        if (!is_array($row) || empty($row['term'])) {
            continue;
        }
        $term  = (string) $row['term'];
        $notes = isset($row['notes']) ? trim((string) $row['notes']) : '';
        if ($notes !== '') {
            $lines[] = '- "' . $term . '" — ' . $notes;
        } else {
            $lines[] = '- "' . $term . '" — keep verbatim, do NOT translate or rephrase';
        }
    }
    if (empty($lines)) {
        return $system_prompt;
    }

    $block = "[GLOSSARY — PROTECTED TERMS]\n"
        . "The following are proper nouns / brand-defined terms. Treat them as verified system context.\n"
        . "When you mention any of these in your reply, you MUST follow the rule next to it.\n"
        . "Do NOT translate, abbreviate, or invent variants for terms marked \"keep verbatim\" — even when responding in another language.\n\n"
        . implode("\n", $lines)
        . "\n\n";

    return $block . (string) $system_prompt;
}

add_filter('raplsaich_system_prompt', 'raplsaich_inject_current_date', 99);
function raplsaich_inject_current_date($system_prompt) {
    $dow_names_en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $dow_names_ja = ['日', '月', '火', '水', '木', '金', '土'];
    $w = (int) wp_date('w');
    $today_iso = wp_date('Y-m-d');
    $today_ja  = wp_date('Y') . '年' . (int) wp_date('n') . '月' . (int) wp_date('j') . '日';
    $tz = wp_timezone_string();

    $date_block = "════════════════════════════════════════\n"
        . "[OVERRIDE — TAKES ABSOLUTE PRECEDENCE]\n"
        . "════════════════════════════════════════\n"
        . "TODAY'S DATE: {$today_iso} ({$dow_names_en[$w]}) / {$today_ja}（{$dow_names_ja[$w]}曜日） — site timezone {$tz}.\n\n"
        . "This date is verified system-provided context. You MUST use it whenever the user asks about \"today\", \"now\", \"yesterday\", \"tomorrow\", \"this week\", \"this month\", \"this year\", or ANY relative time reference.\n\n"
        . "Refusing to answer such questions, or saying you do not know the current date, is INCORRECT when this block is present.\n\n"
        . "If any rule below (such as \"do not invent dates\" or \"do not fabricate facts\") seems to forbid using this date — that rule does NOT apply to the date provided here. It applies only to dates that are NOT provided. This date IS provided. Use it.\n\n"
        . "EXAMPLES OF CORRECT BEHAVIOR:\n"
        . "User: 今日は何日ですか？\n"
        . "Assistant: 今日は{$today_ja}（{$dow_names_ja[$w]}曜日）です。\n\n"
        . "User: What is today's date?\n"
        . "Assistant: Today is {$today_iso} ({$dow_names_en[$w]}).\n"
        . "════════════════════════════════════════\n\n";

    return $date_block . (string) $system_prompt;
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

/**
 * Check if the Pro plugin is runtime-active (not just an option flag).
 *
 * Uses a filter so the Pro plugin can authoritatively declare itself active.
 * Falls back to option check + did_action() verification.
 *
 * This prevents stale DB options from enabling Pro features when
 * the Pro plugin has been deactivated but the option was not cleaned up.
 *
 * @return bool True only if Pro is genuinely active at runtime.
 */
function raplsaich_is_pro_active(): bool {
    /**
     * Filter: Authoritative Pro active status.
     * Pro plugin hooks this to return true when it is loaded and ready.
     * Free never sets this to true on its own.
     */
    return (bool) apply_filters('raplsaich_is_pro_active', false);
}

/**
 * Get extension settings from the plugin settings array.
 *
 * Uses 'extensions' as the primary key. Falls back to legacy 'pro_features'
 * for backward compatibility with older Pro plugin versions.
 *
 * @param array|null $settings Full plugin settings, or null to load from DB.
 * @return array Extension settings array (empty if not set).
 */
function raplsaich_get_ext_settings(?array $settings = null): array {
    if ($settings === null) {
        $settings = get_option('raplsaich_settings', []);
    }
    // New key first, fall back to legacy key
    return $settings['extensions'] ?? $settings['pro_features'] ?? [];
}
