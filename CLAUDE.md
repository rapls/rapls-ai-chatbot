# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress AI chatbot plugin supporting OpenAI, Anthropic Claude, and Google Gemini.
Two-tier architecture: **Free** (this repo, WordPress.org) + **Pro** (separate plugin `rapls-ai-chatbot-pro/`).

- Version: 1.3.1 | PHP 7.4+ | WordPress 5.8+
- Text Domain: `rapls-ai-chatbot`
- All settings stored in: `get_option('wpaic_settings')`
- Pro settings nested at: `wpaic_settings['pro_features']`
- Constants: `WPAIC_VERSION`, `WPAIC_PLUGIN_DIR`, `WPAIC_PLUGIN_URL`, `WPAIC_PLUGIN_BASENAME`

## Development Environment

No build tools, bundlers, linters, or test frameworks. Pure PHP/JS/CSS WordPress plugin.

- **Local environment**: Local by Flywheel (Local Sites)
- **PHP/JS/CSS**: Edit directly, no compilation step
- **Translations**: Compile `.po` to `.mo` with `msgfmt languages/rapls-ai-chatbot-ja.po -o languages/rapls-ai-chatbot-ja.mo`
- **Distribution**: `git archive` only (`.gitattributes` export-ignore excludes dev files like CLAUDE.md)
- **ZIP verification**: Automated via `.github/workflows/zip-verify.yml` (runs on tags + PRs). Manual check:
  ```bash
  # Cross-platform: use unzip -l (not tar -t) so it works on Windows/BusyBox too
  git archive --format=zip HEAD -o /tmp/check.zip
  unzip -l /tmp/check.zip | grep -E '(CLAUDE\.md|\.DS_Store|node_modules/|\.claude/)' && echo "FAIL" && exit 1 || echo "OK"
  rm /tmp/check.zip
  ```
- **Output location**: ZIP files, reports, and all generated artifacts must be placed in `/Users/min/Local Sites/hash/app/public/wp-content/plugins/` — **NEVER on Desktop or any other location**

### WordPress Hook/Filter Type Safety Rules

- **NEVER** use strict PHP type hints on the first parameter of `add_filter`/`add_action` callbacks — WordPress core passes `mixed` (WP_Error, null, WP_HTTP_Response, etc.)
- Accept `$result` untyped, then guard with `is_wp_error()`, `null` check, `instanceof`, or `method_exists()`
- Return type hints should also be omitted on filter callbacks (return `mixed`)
- **HTTP headers**: Never overwrite — always merge. Use `append_header_csv()` for Vary, Cache-Control, etc.

### Idempotent / Re-runnable Code Rules

- **Uninstall, upgrade, and migration** functions may be interrupted and re-run. Keep them idempotent (DB deletes, option updates only).
- **Do NOT** add external side effects (file I/O, remote API calls, email sends) to these paths — they are not transactional and cannot be safely retried.
- **Activator (`class-activator.php`)**: allowed side effects are DB schema (`dbDelta`, `$wpdb`), options/transients, and WP cron only. Posts, users, files, HTTP, hooks, `eval`, and variable functions (`$fn()`) are all forbidden. CI enforces this via deny-list + allow-list.
- **Do NOT** add `catch` blocks that swallow exceptions in uninstall/upgrade paths — silent catch breaks `completed_at` accuracy. Always rethrow.
- **Catch blocks in sensitive files must be ≤40 lines** and end with `throw`/`rethrow`. CI enforces this (40-line window). Violations are intentional CI failures — fix by extracting a helper, not by increasing the window.
- **completed_at-sensitive files** — single source of truth: `.ci/sensitive-files.txt`
  CI reads this file directly. To add a file, edit `.ci/sensitive-files.txt` only.

### Option Key Naming & Cleanup

All plugin option keys must follow these category prefixes so `uninstall.php` can clean them reliably:

| Prefix | Category | Uninstall behavior |
|--------|----------|-------------------|
| `wpaic_diag_` | Diagnostic/telemetry (counters, timestamps) | Always deleted (LIKE query) |
| `wpaic_settings` | User settings | Deleted only if `delete_data_on_uninstall` |
| `wpaic_version`, `wpaic_db_version`, etc. | Plugin state | Deleted only if `delete_data_on_uninstall` |
| `wpaic_pro_*` | Pro license/state | Deleted only if `delete_data_on_uninstall` |

- **New options must fit one of these categories.** If a new prefix is needed, add it to `uninstall.php`'s delete logic first.
- Never create options outside `wpaic_` namespace — they won't be cleaned up.

### Translation Style Guide (WordPress.org Japanese)

Follow the [WordPress Japanese Translation Style Guide](https://ja.wordpress.org/team/handbook/translation/translation-style-guide/) and [Glossary](https://translate.wordpress.org/locale/ja/default/glossary/).

**Spacing rules:**
- Half-width **letters/symbols** adjacent to full-width characters: insert half-width space (`AI 設定`, `API キー`, `Claude 設定`)
- Half-width **numbers** adjacent to full-width characters: NO space (`5MB`, `2024年`, `30日間`)
- Half-width parentheses `( )`: space outside, no space inside

**Katakana long vowel (ー):**
- Base word 4 chars or fewer → add ー (ユーザー, フィルター)
- Base word 5 chars or more → omit ー (プロパティ, コンピュータ)
- Exceptions per WordPress glossary: カテゴリー, プロバイダー

**Word choices:**
- 「ください」not「下さい」, 「すべて」not「全て」
- Prefer active voice over passive (「～しました」not「～されました」)
- Use WordPress glossary terms: activate→有効化, deactivate→無効化, Dashboard→ダッシュボード, plugin→プラグイン, theme→テーマ, category→カテゴリー

**Do not translate:** WordPress, plugin/theme names, brand names

### Plugin Check (WordPress.org Validation)

The `plugin-check` plugin (v1.8.0) is installed for WordPress.org compliance validation.

```bash
# Basic static check
wp plugin check rapls-ai-chatbot

# Full check (static + runtime) — recommended before submission
wp plugin check rapls-ai-chatbot --require=./wp-content/plugins/plugin-check/cli.php

# WordPress.org repo requirements only
wp plugin check rapls-ai-chatbot --require=./wp-content/plugins/plugin-check/cli.php --categories=plugin_repo

# Check with specific format
wp plugin check rapls-ai-chatbot --format=json

# Ignore specific codes
wp plugin check rapls-ai-chatbot --ignore-codes=WordPress.Security.EscapeOutput
```

## Architecture

### Initialization Flow
```
rapls-ai-chatbot.php (entry point, defines constants)
  → WPAIC_Main (includes/class-main.php)
    → Loads all dependencies
    → Registers hooks via WPAIC_Loader (includes/class-loader.php)
    → Admin hooks → WPAIC_Admin
    → Public hooks → WPAIC_Chatbot_Widget
    → REST hooks → WPAIC_REST_Controller
    → Cron hooks → wpaic_crawl_site, wpaic_cleanup_old_conversations
```

### Free/Pro Separation (WordPress.org compliance)
- **No Pro detection logic in Free**: `WPAIC_Pro_Features::is_pro()` always returns `false`
- **Pro features shown as locked**: UI shows Pro options disabled with lock icons
- **Pro plugin overrides**: When active, Pro replaces admin menus and adds CSS/JS
- **Shared database**: Both plugins use identical tables (created by Free's `WPAIC_Activator`)
- **Never add `$is_pro` conditionals to Free code**
- **Singleton Override Pattern**: Pro replaces Free's `WPAIC_Pro_Features` instance via `set_instance()` so `is_pro()` returns `true`

### AI Provider Interface
All providers implement `WPAIC_AI_Provider_Interface` (`includes/ai-providers/interface-ai-provider.php`):
```php
set_api_key(string $key): void
set_model(string $model): void
send_message(array $messages, array $options = []): array
get_available_models(): array
validate_api_key(): bool
get_name(): string
```
Implementations: `class-openai-provider.php`, `class-claude-provider.php`, `class-gemini-provider.php`

### REST API
Namespace: `wp-ai-chatbot/v1`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/session` | Get/create chat session |
| POST | `/chat` | Send message, get AI response |
| GET | `/history/{session_id}` | Conversation history |
| POST | `/lead` | Submit lead form |
| GET | `/lead-config` | Lead form configuration |
| GET | `/message-limit` | Check message limits |
| POST | `/feedback` | Rate response (thumbs up/down) |
| POST | `/regenerate` | Regenerate AI response |
| GET | `/summary/{session_id}` | Conversation summary |
| POST | `/suggestions` | Related question suggestions |
| POST | `/autocomplete` | Input autocomplete |

Pro adds: `/pro-config`, `/save-context`, `/conversion`, `/offline-message`, `/templates` (via `class-pro-rest.php`)

### WordPress Hooks (for developers)
```php
// Filters
wpaic_system_prompt          // Modify system prompt sent to AI
wpaic_context                // Modify RAG context from site learning
wpaic_ai_response            // Filter AI response before display
wpaic_chatbot_enabled        // Control chatbot visibility
wpaic_allowed_origins        // Add allowed origin hosts for same-origin check
wpaic_gpt5_token_multiplier  // GPT-5 reasoning token multiplier (default: 4, range: 1-8)
```

### Database Tables (prefix: `wp_aichat_`)
| Table | Purpose |
|-------|---------|
| `conversations` | Chat sessions |
| `messages` | Individual messages (includes `feedback`, `cache_hash`, `cache_hit` columns) |
| `index` | Site content index (RAG) |
| `knowledge` | Knowledge base Q&A |
| `leads` | Lead capture data (`custom_fields` column) |
| `user_context` | Cross-session context memory (Pro, auto-created on use) |
| `audit_log` | Administrative action audit trail (Pro) |

Tables created by `WPAIC_Activator::activate()`. Pro does NOT create tables.

### Content Crawler (RAG)
`includes/crawler/` - Crawls WordPress content, chunks it, stores in `wp_aichat_index` with FULLTEXT search for retrieval-augmented generation.

### Key Files by Role
| Role | File |
|------|------|
| Admin UI controller | `includes/admin/class-admin.php` (largest file, ~88KB) |
| REST API | `includes/api/class-rest-controller.php` |
| Frontend widget | `includes/frontend/class-chatbot-widget.php` |
| Widget template | `templates/frontend/chatbot-widget.php` |
| Settings template | `templates/admin/settings.php` |
| Frontend JS | `assets/js/chatbot.js` (vanilla JS, no frameworks) |
| Frontend CSS | `assets/css/chatbot.css` (includes 6 Free themes) |
| Pro themes + dark mode | `rapls-ai-chatbot-pro/assets/css/pro-themes.css` |

## Adding New Pro Features

1. Add constant to `class-pro-features.php`
2. Add default value in `get_default_settings()`
3. Add sanitization in `class-admin.php` → `sanitize_pro_features_settings()`
4. Add UI in `templates/admin/settings.php` (Pro Features tab)
5. Implement feature (REST API, frontend, etc.)
6. Update this file's implementation status below

## Widget Themes

**Free (6)**: Default, Simple, Classic, Light, Minimal, Flat — CSS in `chatbot.css`
**Pro (10)**: Modern, Gradient, Dark, Glass, Rounded, Ocean, Sunset, Forest, Neon, Elegant — CSS in `pro-themes.css`
**Dark mode**: Pro-only, overlay approach applicable to any theme

## Pro Feature Implementation Status

All Phase 0-5 features are implemented. See the full backlog of unimplemented features in the section below.

### Implemented Features
- Message limits (Free: 500/mo, Pro: unlimited)
- Lead capture forms, Webhooks, White label, CSV/JSON export
- Feedback buttons, Response regeneration, Quick replies
- Business hours & holidays, Banned words filter, IP blocking, Enhanced rate limiting (burst + sustained)
- Analytics dashboard (Chart.js), FAQ ranking, unresolved questions, satisfaction scores
- Time/device/page analysis, Real-time monitor
- Conversation summary, Prompt templates, Related questions, Autocomplete
- Multimodal (image upload), Sentiment analysis, Context memory
- Scheduled/differential crawl, Human handoff, Surveys, Conversation tags
- Multiple chatbots (per-page), Operator mode, FAQ auto-generation
- API cost alerts + budget caps, Knowledge export (CSV/JSON), Monthly email reports
- Knowledge gap detection, PDF/print analytics report, Server-side PDF export (Dompdf)
- Dynamic variables ({site_name}, {current_date}, {business_hours}, etc.)
- Settings import/export (JSON backup/restore)
- FAQ auto-generation from knowledge gaps (AI-powered draft workflow)
- Enhanced content extraction (DOMDocument-based)
- Sortable admin tables, Knowledge base draft status
- Response caching (SHA-256 hash, TTL, cache stats, clear cache)
- Audit logs (admin action tracking, CSV export, retention policy)
- Conversion tracking (goal URL patterns, analytics integration)
- Offline messages (business hours form, email notification, webhook)
- Answer templates (knowledge type field, operator mode insertion, dynamic variables)

### Unimplemented Pro Features (Backlog)
**AI**: Text-to-speech, response edit suggestions
**Analytics**: AI quality score, churn analysis
**UI**: Badge customization, fullscreen/embedded modes, custom fonts, animations, sounds, welcome screen, seasonal themes
**Integrations**: Slack, LINE, Google Sheets
**Operations**: Response delay, AI approval workflow, spam detection, country blocking, external site/PDF/video learning, staging, change history, rollback, multisite
**Chat**: File sending, screen sharing, booking integration, embedded forms, bookmarks, full-text search, conversation sharing, multi-bot coordination
**Knowledge**: Similar question merge, conditional answers, intent classification, versioning, expiration, priority auto-adjustment, related links
**Security**: Encryption, GDPR, cookie consent, PII masking, data retention policies, RBAC, IP whitelist, security headers, vulnerability scanning
**Performance**: Similar question cache, batch processing, queue management, performance monitoring
**Developer**: Custom fields, test mode, multi-API key management


<claude-mem-context>

</claude-mem-context>