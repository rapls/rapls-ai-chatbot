=== Rapls AI Chatbot ===

Contributors: rapls
Tags: chatbot, ai, openai, claude, gemini
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 1.7.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot with OpenAI, Claude, Gemini, OpenRouter. Site learning, knowledge base, web search, MCP server, 13-language auto-reply.



== Description ==

Rapls AI Chatbot adds an AI chatbot to your site with OpenAI, Claude, Gemini, or OpenRouter. Includes site learning, knowledge base, web search, and **automatic multilingual replies in 13 languages** — visitors get answered in their browser language without you maintaining a separate translation.

This is especially useful for tourism, hospitality, and retail sites that get inquiries in languages they don't have native staff for: a Japanese inn answering visitors in English / Chinese / Korean / Spanish / etc., a tour operator handling questions in the visitor's own language, or a multilingual storefront whose actual support team is one person.

👉 **Documentation & Developer's Guide:** [Why I built this plugin and how RAG works](https://raplsworks.com/plugins/rapls-ai-chatbot/)

= Key Features =

* **Multiple AI Providers** — OpenAI, Anthropic Claude, Google Gemini, and OpenRouter (100+ models via single API key)
* **Web Search** — AI automatically searches the web when the knowledge base lacks a sufficient answer, using each provider's built-in capability (OpenAI web_search_preview, Claude web_search, Gemini google_search)
* **Site Learning** — Crawl and index your website content (posts, pages, custom post types, WooCommerce products) for context-aware responses
* **Vector Embedding RAG** — Hybrid search combining keyword matching (40%) and vector similarity (60%) for accurate retrieval
* **Custom Knowledge Base** — Add Q&A pairs, free-form content, PDF/DOCX uploads with priority levels and draft/published workflow
* **MCP Server** — 7 built-in tools via JSON-RPC 2.0 for AI agent integration (Claude Desktop, Cursor, VS Code)
* **WordPress Abilities API** — Auto-register MCP tools as WordPress Abilities for discovery by MCP Adapters
* **Gutenberg Block** — Insert AI Chatbot block in the block editor with height, theme, and bot-id settings; SSR support
* **Response Language Auto-detect** — Automatically detect browser language for welcome message and AI responses
* **Cross-site Embed** — Embed the chatbot on external sites via iframe or script loader
* **Conversation History** — Save and review all chat conversations with configurable retention
* **Usage Statistics** — Track token usage and estimated API costs with visual charts and provider breakdown
* **Feedback & Regeneration** — Users can rate responses (thumbs up/down) and request regeneration
* **6 Built-in Themes** — Default, Simple, Classic, Light, Minimal, Flat
* **Security** — reCAPTCHA v3, rate limiting, consent mode, Cloudflare support, security diagnostics
* **Settings Import/Export** — Backup and restore all settings as JSON
* **Automatic Multilingual Replies (13 languages)** — Auto-detects the visitor's browser language and replies in it. English, Japanese, Chinese, Korean, Spanish, French, German, Portuguese, Italian, Russian, Arabic, Thai, Vietnamese. Welcome messages are also configurable per language. Practical use case: a tourism / hospitality / retail site that doesn't have multilingual staff can still answer foreign visitors in their own language

= Supported AI Models =

**OpenAI:**
* GPT-5.2, GPT-5.1, GPT-5 series (Latest generation)
* GPT-4.1 series (Long context, 1M tokens)
* GPT-4o, GPT-4o-mini (Multimodal)
* o1, o3, o4-mini (Reasoning models)

**Anthropic Claude:**
* Claude Opus 4.6 (Most powerful)
* Claude Sonnet 4.5 (Recommended — fast and powerful)
* Claude Haiku 4.5 (Recommended — fastest)
* Claude Opus 4.5, Opus 4.1, Sonnet 4, 3.7 Sonnet

**Google Gemini:**
* Gemini 3 Pro/Flash (Preview, latest)
* Gemini 2.5 Pro/Flash (Recommended)
* Gemini 2.0 Flash (Stable)
* Gemini 1.5 Pro/Flash (Legacy)

**OpenRouter:**
* Access 100+ models from multiple providers through a single API key

= Dashboard =

The dashboard provides an at-a-glance overview of your chatbot's activity:

* Statistics cards: total conversations, today's messages, indexed pages, knowledge entries, monthly AI responses with usage limit
* Status indicators: AI provider, site learning, conversation history
* API usage statistics (past 30 days): total tokens, input/output tokens, estimated cost, daily usage chart, provider breakdown

= Settings (5 Tabs) =

**AI Settings** — Configure your AI provider, model, and API key. Enable vector search (RAG) with embedding provider. Set up MCP server with API key generation and Claude Desktop configuration example.

**Chat Settings** — Customize bot name, avatar (emoji or image), welcome messages (13 languages: English, Japanese, Chinese, Korean, Spanish, French, German, Portuguese, Italian, Russian, Arabic, Thai, Vietnamese), system prompt, response language, message history count, feedback buttons, and API quota error message. Advanced: context prompts for knowledge matching, Q&A format, and site learning; feature prompts for regeneration instructions, good/bad example learning, and conversation summary.

**Display Settings** — Choose from 6 free themes (Default, Simple, Classic, Light, Minimal, Flat). Configure badge position (4-corner grid), margins, primary/secondary colors, mobile display, Markdown rendering, typing indicator, maximum input length, page exclusion, footer text, and cross-site embed options (script or iframe).

**Security Settings** — Enable reCAPTCHA v3 with site key, secret key, and score threshold. Configure access control: consent strict mode, rate limiting, Cloudflare integration, reverse proxy trust, reCAPTCHA failure mode. View security diagnostics (read-only): allowed origins, trusted proxies, IP detection, API key status, WP Consent API, rate limiting, reCAPTCHA, SSL/TLS, CSRF.

**Data Management** — Enable/disable conversation history with configurable retention period. Import/export settings (optionally including knowledge base). Reset all settings to defaults.

= Knowledge Base =

* Add entries as text: title, content, category, priority level
* File import: .txt, .csv, .md, .pdf, .docx (server-side parsing)
* Statistics: total entries, active, inactive, categories
* Filter by status: all, published, draft
* Sortable table: ID, title, category, type, priority, updated date
* Unlimited entries

= Site Learning =

The plugin crawls and indexes your published content for context-aware AI responses:

* Posts and Pages
* Custom Post Types
* WooCommerce Products
* Any public content

With vector embedding enabled, hybrid search combines keyword matching (40%) and vector similarity (60%) for better retrieval accuracy.

= Free vs Pro =

The free version is fully functional with no artificial limits — you pay only your own AI API costs. An optional Pro add-on is available for business-oriented features.

* **Free** — Full AI chat, unlimited responses, unlimited knowledge base, 6 themes, MCP server, Gutenberg block
* **Pro** — Adds analytics, lead capture, scenarios, operator mode, WooCommerce, LINE, and more

**What Free includes:**

* All 4 AI providers (OpenAI, Claude, Gemini, OpenRouter)
* Unlimited AI responses and knowledge base entries
* Web search, site learning with vector RAG
* MCP server, Gutenberg block, cross-site embed
* 6 themes, feedback, regeneration, reCAPTCHA, security diagnostics

**What Pro adds:**

* Analytics dashboard with satisfaction scores, FAQ ranking, and PDF export
* Lead capture forms, CSV/JSON export, webhooks, Google Sheets
* Conversation scenarios, business hours, human handoff, operator mode
* WooCommerce product cards, LINE Messaging API, Slack notifications
* 10 additional themes, dark mode, voice input/TTS, multimodal
* Response caching, encryption, audit logs, and more

Learn more about Pro features at [raplsworks.com](https://raplsworks.com/plugins/rapls-ai-chatbot-pro/), or read the [developer's overview](https://raplsworks.com/plugins/rapls-ai-chatbot/) for the full context.



== Installation ==

1. Upload the `rapls-ai-chatbot` folder to the `/wp-content/plugins/` directory
2. Activate the plugin via the 'Plugins' menu in WordPress
3. Go to AI Chatbot > Settings to configure your AI provider and API key
4. Customize the chatbot appearance and behavior as needed
5. The chatbot will automatically appear on your site

= API Key Setup =

You'll need an API key from at least one AI provider:

* **OpenAI:** Get your key at [platform.openai.com](https://platform.openai.com/)
* **Anthropic Claude:** Get your key at [console.anthropic.com](https://console.anthropic.com/)
* **Google Gemini:** Get your key at [aistudio.google.com](https://aistudio.google.com/)
* **OpenRouter:** Get your key at [openrouter.ai](https://openrouter.ai/)

= MCP Server Setup =

1. Go to AI Chatbot > Settings > AI Settings
2. Enable MCP and click "Generate API Key"
3. Copy the endpoint URL and API key
4. Add the configuration to your AI agent (Claude Desktop, Cursor, or VS Code)

The plugin provides 7 MCP tools: get_site_info, search_content, get_knowledge, manage_knowledge, get_conversations, get_settings, search_products (Pro).



== Screenshots ==

1. Dashboard — Overview of conversations, messages, and usage statistics with cost tracking
2. Settings — Configure AI provider, model selection, and chat behavior
3. Site Learning — Automatic content indexing and manual learning controls
4. Knowledge Base — Custom Q&A management with priority levels and PDF/DOCX upload
5. Conversation History — View and manage all chat conversations
6. Chatbot Widget — Clean, modern chat interface on your website
7. Analytics Dashboard (Pro) — Conversation insights, satisfaction tracking, and FAQ analysis



== Frequently Asked Questions ==

= Where can I find detailed documentation? =

A comprehensive developer's guide explains why this plugin was built, how the RAG hybrid search works, setup walkthroughs, and common troubleshooting:

* [Rapls AI Chatbot — Developer's Guide](https://raplsworks.com/plugins/rapls-ai-chatbot/)
* [Source code on GitHub](https://github.com/rapls/rapls-ai-chatbot)
* [Developer's blog — Rapls Works](https://raplsworks.com/)

= Which AI provider should I choose? =

Each provider has different strengths:
* **OpenAI GPT-4o-mini** — Best balance of cost and performance for most use cases
* **Claude Sonnet 4.5** — Excellent for nuanced, helpful responses
* **Gemini 2.5 Flash** — Fast and cost-effective, good for high-volume sites
* **OpenRouter** — Access to 100+ models from multiple providers with a single API key

= How much does it cost to use? =

The plugin itself is free. You pay for AI API usage directly to your chosen provider. Typical costs:
* GPT-4o-mini: ~$0.15/1M input tokens, ~$0.60/1M output tokens
* Claude Haiku 4.5: ~$0.80/1M input tokens, ~$4.00/1M output tokens
* Gemini 2.5 Flash: ~$0.15/1M input tokens, ~$0.60/1M output tokens

= Can I use multiple AI providers? =

You can configure multiple API keys, but only one provider is active at a time. You can switch between providers in the settings.

= How does Site Learning work? =

The plugin crawls your published content and creates a searchable index. When users ask questions, relevant content is included in the AI context for accurate, site-specific responses. With vector embedding enabled, hybrid search combines keyword matching (40%) and vector similarity (60%) for better retrieval.

= How does Web Search work? =

When the knowledge base and site content don't have a sufficient answer, the AI automatically searches the web using each provider's built-in capability (OpenAI web_search_preview, Claude web_search, Gemini google_search). Web sources are shown with a globe icon.

= What is the MCP Server? =

MCP (Model Context Protocol) allows external AI agents like Claude Desktop, Cursor, and VS Code to interact with your chatbot's data. The plugin provides 7 built-in tools for searching content, managing knowledge, and viewing conversations. Tools are also registered as WordPress Abilities for auto-discovery.

= Can I embed the chatbot on external sites? =

Yes. The plugin provides a cross-site embed page (`?raplsaich_embed=1`) and a loader script (`assets/js/embed-loader.js`) for easy integration on any external website via iframe.

= Can I use the Gutenberg block? =

Yes. Search for "AI Chatbot" in the block editor to insert the chatbot block. Configure height, theme, and bot-id settings. Server-side rendering (SSR) is supported.

= Can I customize the chatbot appearance? =

Yes. You can customize:
* Bot name and avatar (emoji or image)
* Primary and secondary colors
* Theme (6 built-in themes)
* Welcome message (13 languages)
* Badge position, margins, and icon
* Mobile visibility
* Excluded pages
* Typing indicator and Markdown rendering

= Is conversation history saved? =

Yes, by default. You can disable this in Settings > Data Management. Saved conversations are auto-deleted after the configured retention period (default: 90 days).

= Does it work with page builders? =

Yes, the chatbot widget works with any theme and page builder including Elementor, Divi, Beaver Builder, and Gutenberg.

= Can I use custom system prompts? =

Yes. Configure your own system prompt to define the AI's personality, behavior, and response style. The `raplsaich_system_prompt` filter is also available for programmatic customization. Advanced feature prompts (regeneration, feedback learning, summary) are also customizable.

= What happens if I exceed my API quota? =

The plugin displays a customizable error message when your AI provider's quota limits are reached. There is no artificial response limit in the plugin itself.

= What is the Pro add-on? =

The Pro add-on is a separate plugin that adds business-oriented features such as analytics, lead capture, conversation scenarios, operator mode, WooCommerce integration, and LINE integration. The free version is fully functional on its own with no artificial limits.

= What happens to my data when I uninstall? =

By default, the plugin keeps your settings and conversation data so you can re-install without losing anything. To delete all data on uninstall, enable "Delete data on uninstall" in Settings > Data Management. Temporary cache and diagnostic counters are always removed regardless of this setting. On multisite, each site has its own setting.

= How can I adjust multisite uninstall performance? =

On large multisite networks, uninstall batch size is adjustable via filters. Add to your `functions.php` or an MU-plugin:

`add_filter( 'raplsaich_uninstall_batch_size', function() { return 50; } );`

`add_filter( 'raplsaich_uninstall_snapshot_threshold', function() { return 1000; } );`

Guide: low-memory/slow-DB → batch size 20-50, standard → 100, fast/large-scale → 200-500.


== External Services ==

This plugin connects to the following external third-party services. **No data is sent to any service until you configure an API key and enable the feature in the plugin settings.** Each service requires the site administrator to create an account and obtain API credentials. By using these services, you agree to their respective terms and privacy policies listed below.

= 1. OpenAI (GPT models) — AI Provider =

Used when you select OpenAI as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://api.openai.com/](https://api.openai.com/)
* Terms of Use: [https://openai.com/terms/](https://openai.com/terms/)
* Privacy Policy: [https://openai.com/privacy/](https://openai.com/privacy/)

= 2. Anthropic (Claude models) — AI Provider =

Used when you select Anthropic Claude as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://api.anthropic.com/](https://api.anthropic.com/)
* Terms of Use: [https://www.anthropic.com/terms](https://www.anthropic.com/terms)
* Privacy Policy: [https://www.anthropic.com/privacy](https://www.anthropic.com/privacy)

= 3. Google (Gemini models) — AI Provider =

Used when you select Google Gemini as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://generativelanguage.googleapis.com/](https://generativelanguage.googleapis.com/)
* Terms of Use: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= 4. OpenRouter — AI Provider =

Used when you select OpenRouter as your AI provider. OpenRouter is a unified API gateway that routes requests to various AI models.

* Service URL: [https://openrouter.ai/api/](https://openrouter.ai/api/)
* Terms of Use: [https://openrouter.ai/terms](https://openrouter.ai/terms)
* Privacy Policy: [https://openrouter.ai/privacy](https://openrouter.ai/privacy)

= 5. Google reCAPTCHA v3 (Optional) =

Used only if you enable reCAPTCHA in the plugin settings for spam protection. The visitor's IP address and interaction data are sent to Google for verification.

* Service URL: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Terms of Use: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= 6. LINE Messaging API (Pro Add-on, Optional) =

Used only if you enable the LINE integration via the Pro add-on. Connects to the LINE Messaging API for chatbot-to-LINE messaging.

* Service URL: [https://api.line.me/](https://api.line.me/)
* Terms of Use: [https://terms.line.me/](https://terms.line.me/)
* Privacy Policy: [https://line.me/en/terms/policy/](https://line.me/en/terms/policy/)

= Cross-Site Embed =

The plugin includes an optional embed loader script (`embed-loader.js`) for embedding the chatbot on external websites via an iframe. This script does not load any external CDN resources or third-party scripts — it creates an iframe pointing back to your own WordPress site. All data processing occurs on your server.

= Data Transmitted to External Services =

* **User messages**: Chat messages entered by visitors (sent to the configured AI provider only)
* **Site content** (if Site Learning is enabled): Excerpts from your published posts/pages (sent to the configured AI provider)
* **Knowledge base** (if configured): Custom Q&A entries you create (sent to the configured AI provider)
* **IP address** (reCAPTCHA only): Sent to Google for spam verification

= Data Storage =

* **Conversation history**: Stored locally in your WordPress database (can be disabled)
* **Visitor IP**: Stored as SHA-256 hash (not plain text) for rate limiting
* **Retention**: Configurable auto-deletion period (default 90 days)

= User Controls =

You can disable these features in the plugin settings:
* Conversation history saving
* Site content crawling/learning
* Google reCAPTCHA verification
* Web search



== Privacy ==

= Data Collected =

When conversation history is enabled, the plugin stores:
* Chat messages (user and AI responses)
* Session identifiers
* Page URLs where chats occurred
* Hashed IP addresses (SHA-256, not reversible)
* Timestamps

= Data Retention =

Conversation data is automatically deleted after the configured retention period (default: 90 days). Administrators can manually delete conversations at any time.

= User Rights =

Site administrators can:
* View all conversation history
* Delete individual or all conversations
* Export settings (does not include conversation data)
* Disable history saving entirely



== Other Notes ==

= Developer Information =

The plugin provides hooks and filters for customization:

= Available Filters =

* `raplsaich_system_prompt` — Modify the system prompt sent to AI
* `raplsaich_context` — Modify the context from site learning
* `raplsaich_ai_response` — Filter the AI response before display
* `raplsaich_chatbot_enabled` — Control chatbot visibility programmatically
* `raplsaich_allowed_origins` — Add allowed origin hosts for same-origin check
* `raplsaich_chat_response_data` — Filter chat response data before returning to client
* `raplsaich_gpt5_token_multiplier` — GPT-5 reasoning token multiplier (default: 4, range: 1-8)

= Example: Custom System Prompt =

`
add_filter( 'raplsaich_system_prompt', function( $prompt, $settings ) {
    return $prompt . "\n\nAlways end responses with a friendly emoji.";
}, 10, 2 );
`

= Example: Conditionally Hide Chatbot =

`
add_filter( 'raplsaich_chatbot_enabled', function( $enabled ) {
    // Hide on checkout page
    if ( is_page( 'checkout' ) ) {
        return false;
    }
    return $enabled;
} );
`

= REST API Endpoints =

The plugin registers REST API endpoints under the `rapls-ai-chatbot/v1` namespace:

**Session authentication:** Pass the session ID via the `X-RAPLSAICH-Session` HTTP header (recommended). When the header is present, any session_id in the request body is ignored (prevents APM/WAF body-logging leakage). Query string parameters (`?session_id=...`) are **not** accepted for GET requests to prevent session leakage in server access logs.

**Free:**

* `GET /rapls-ai-chatbot/v1/session` — Get or create a chat session
* `POST /rapls-ai-chatbot/v1/chat` — Send a message and receive AI response
* `GET /rapls-ai-chatbot/v1/history/{session_id}` — Get conversation history
* `POST /rapls-ai-chatbot/v1/feedback` — Rate a response (thumbs up/down)
* `POST /rapls-ai-chatbot/v1/regenerate` — Regenerate AI response
* `GET /rapls-ai-chatbot/v1/message-limit` — Check message limits
* `POST /rapls-ai-chatbot/v1/lead` — Submit lead form
* `GET /rapls-ai-chatbot/v1/lead-config` — Lead form configuration

The Pro add-on registers additional endpoints for analytics, scenarios, LINE, and more. See the Pro documentation for details.

= Settings Architecture =

Extension settings are stored under the `extensions` key in `raplsaich_settings`. For backward compatibility, the legacy `pro_features` key is read as a fallback but all new settings are written to `extensions`.

= Database Tables =

The plugin creates the following database tables:

* `{prefix}_aichat_conversations` — Chat sessions
* `{prefix}_aichat_messages` — Individual messages with token tracking
* `{prefix}_aichat_index` — Site learning content index
* `{prefix}_aichat_knowledge` — Custom knowledge base entries
* `{prefix}_aichat_leads` — Lead capture data

= Uninstallation =

When uninstalled with "Delete data on uninstall" enabled, the plugin removes all database tables, options, and transients. Without this setting, data is preserved for re-installation.



== Development ==

Release ZIPs are CI-verified for packaging correctness. Report any issues via the support forum.

= Credits =

* [Chart.js](https://www.chartjs.org/) (MIT License) — Usage statistics charts

== Changelog ==

= 1.7.6 =
* Fixed: Conversation list message search returned no hits when AES-256-GCM encryption was on. The search ran a SQL `LIKE` against `messages.content`, but with encryption enabled the column holds `encg:...` ciphertext that can never match a plaintext keyword. The list now also scans the most recent N encrypted messages in PHP after decryption (default 2000, filterable via `raplsaich_search_decrypt_limit`). Plaintext sites keep using the cheaper SQL path.
* Added: `?conversation_id=N` direct filter on the conversations admin page. Used by Pro analytics' "View Conversation" button so the link always resolves to the target row, regardless of encryption state. An info notice appears at the top showing which conversation is filtered, with a "Show all" link to return to the unfiltered list.
* Changed: Hierarchical preset group chips (Pro 1.6.0+) now use the same neutral grey palette as their child chips, instead of the site primary colour. Avoids visual confusion with the visitor's own messages (which use the primary). The "▸" marker baked into the label still signals "tap to reveal more".

= 1.7.5 =
* Security: Session tokens issued to the chat widget now carry an expiry and a session-version stamp. Previous v1 tokens were a deterministic HMAC of the session id alone, with no TTL or revocation channel — once leaked, they remained valid until the underlying session row was deleted. The new v2 format is `version.iat.exp.hmac`, signed over `version.iat.exp.session_id`. Default TTL is 7 days (filterable via `raplsaich_session_token_ttl`, capped at 30 days). Bumping `raplsaich_session_version` (the existing "Reset all user sessions" admin button) now also revokes every outstanding token. Old v1 tokens are rejected at verification time; clients automatically receive a v2 token on the next `/session` call, so the visitor experience is unchanged
* Security: Offline-message endpoint's permission callback now verifies the request comes from one of the allowed site hosts (Origin/Referer match), not just that any Origin/Referer header is present. The internal `guard_public_post()` same-origin check is kept as defense-in-depth, but non-browser clients with spoofed headers can no longer reach the callback at all

= 1.7.4 =
* Fixed: New "Start a new conversation" header button (1.7.3) was hard-to-read white-on-white in the Simple and Light themes. The Simple/Light theme overrides now apply to both header buttons (close + new-conversation) instead of only close.

= 1.7.3 =
* Added: New "Link Open Behavior" setting under Display Settings — choose between opening reply links in a new window/tab (existing behavior) or in the same window. Applies to all link types in chat replies (knowledge base sources, web sources, content cards, action buttons, product cards, markdown links, raw URLs).
* Added: "Start a new conversation" button in the chat header (circular-arrow icon, next to Close). Tapping it confirms once, then drops the visitor's local session and re-renders the welcome message. Useful for visitors who want to test a fresh question without prior context, and for admins testing the bot. Server-side conversation history is kept intact for admin review.
* Added: Drag-and-drop reordering on the preset question button rows in Chat Settings. A grip handle appears on the left of each row; drag a row up or down to reorder. Save the form to persist the new order.

= 1.7.2 =
* Changed: Preset chips now use a neutral grey colour scheme distinct from user-message bubbles, so visitors no longer mistake a preset chip for one of their own past messages
* Changed: Welcome-message preset chips stay visible after a click instead of being removed — visitors can scroll back and tap a different preset later. Tapped chips are visually marked as "visited" (semi-transparent). The persistent "show under every reply" mode is unchanged: those re-render naturally on each bot reply
* Changed: Hierarchical preset groups containing only one child are now rendered as flat chips so visitors don't need two taps to send a single question

= 1.7.1 =
* Added: chatbot.js support for hierarchical preset chips — when a preset item carries a `children` array, the chip renders as a group with a "▸" suffix; tapping it reveals the child chips with a "← back" chip to return. Free's flat presets are unchanged
* Added: REST `/preset-canned` validation now also accepts canned-reply clicks coming from Pro 1.6.0+ hierarchical preset groups
* Changed: CSS for preset chips gains group/back variants (filled chip for groups, dashed-border chip for back)

= 1.7.0 =
* Added: Glossary (proper-noun protection list) — register up to 50 terms (product names, service names, brand names, words that take on a different meaning when translated) so the AI keeps them verbatim across all reply languages. Optional per-row Notes field for free-form per-language instructions (e.g. "Always render as Staff Perks in English, 员工福利 in Chinese"). Toggleable on/off in Chat Settings
* Added: New `raplsaich_inject_glossary` filter callback (priority 98) on the existing `raplsaich_system_prompt` chain — applies to all surfaces that use the filter (REST `/chat`, Pro regenerate / suggestions / LINE / MCP tools), so the glossary is enforced consistently regardless of how the conversation is triggered

= 1.6.6 =
* Added: Optional "Fixed reply" field on each preset question row. When set, tapping the chip renders the configured reply instantly with no AI call (zero token cost, no AI provider latency, fully controllable wording). Empty field keeps the existing behavior (forwards the question to the AI). Mix per row — common FAQs can be canned, edge cases can stay AI-driven
* Added: New REST endpoint `POST /preset-canned` that persists the canned exchange to the conversation history (when save_history is on) and tags the user message with `metadata.preset_index` + `metadata.preset_canned = true`, so Pro analytics still counts the click and the conversation shows up in the admin Conversations list

= 1.6.5 =
* Fixed: WordPress.org Plugin Check rejected 1.6.4 with "Description: A maximum of 150 characters is supported." — the short description had grown to 152 characters when the 13-language auto-reply pitch was added in 1.6.1. Trimmed to 130 characters by dropping filler words ("for WordPress", "and") without losing keyword coverage

= 1.6.4 =
* Added: New "Also show preset buttons under every bot reply" toggle in Chat Settings. When on, the same preset chips re-appear after every AI response — handy for navigation-style bots where visitors hop between common topics. When off (default), behavior is unchanged: chips appear once after the welcome message and disappear after the first user message
* Added: Stale preset chip cleanup when the visitor sends a message — prevents chips from stacking up across turns in persistent mode

= 1.6.3 =
* Changed: The "Question sent to the bot" field on each preset question row is now a 2-row textarea (vertically resizable) instead of a single-line input. Long questions like "次回のイベント開催日を教えてください" no longer overflow the visible area in the editor

= 1.6.2 =
* Added: Preset question buttons — chips shown under the welcome message (configurable on/off in Chat Settings, up to 10 entries). Tapping a chip submits the configured question text. Chips disappear once the user sends their first message. Useful for nudging visitors toward common topics ("Pricing", "Hours", "Staff perks", etc.) without making them type.
* Added: `preset_index` request param on `/chat` and `metadata.preset_index` on the user message row, so Pro analytics (1.5.0+) can attribute clicks back to a specific preset.
* Added: Public helper `RAPLSAICH_Admin::sanitize_preset_questions()` so Pro per-bot sanitizers can reuse the validation rules (max 10 rows, label ≤ 40 chars, question ≤ 200 chars, both required).

= 1.6.1 =
* Changed: Description and feature copy in readme + LP files now lead with the 13-language auto-reply use case (tourism, hospitality, retail sites that get multilingual inquiries without multilingual staff). The feature itself is unchanged

= 1.6.0 =
* Milestone release consolidating eight patch updates (1.5.15–1.5.22):
  * Current-date injection so the AI can answer "today / yesterday / this week" questions without manually adding the date to the knowledge base
  * Robust against weak models (e.g. Gemini Flash Lite) that previously dismissed injected dates as fabrication
  * `channel` column on conversations + Channel column in the admin Conversations list (Web / LINE)
  * Conversations admin decluttered from 12 columns to 8, with overlap fixes and CSS for the channel badge
  * Japanese label for "Conversations" simplified to 会話
  * `raplsaich_get_max_context_chars()` exposed as a helper so external surfaces (Pro's LINE channel) feed the same volume of knowledge to the model
  * Plugin home and Pro upgrade links migrated to the new /plugins/ URL structure on raplsworks.com

= 1.5.22 =
* Changed: Plugin home and Pro upgrade links updated to the new `/plugins/...` URL structure on raplsworks.com (the standalone `rapls-ai-chatbot-guide/` and `rapls-ai-chatbot-pro` pages have moved). Affects the plugin header URI, the dashboard upsell, the Settings banner, and the readme

= 1.5.21 =
* Changed: The "max RAG context characters" calculation that scales with the configured AI model is now exposed as a public helper (`raplsaich_get_max_context_chars()`) so non-REST surfaces — Pro's LINE channel in particular — can feed the same volume of knowledge to the model. The REST controller's `get_max_context_chars()` method is preserved as a thin wrapper for back-compat

= 1.5.20 =
* Fixed: Conversations admin — Msgs and Lead cells visually overlapped on narrow viewports because the new layout left those columns at default width with no overflow handling. Lead now has an explicit 200px width, Msgs is widened to 70px, and cell content is constrained with overflow/word-break rules. Channel badge in the Session cell also gets its own styles so Web vs LINE is distinct
* Changed: Japanese label for "Conversations" simplified from "会話履歴" to "会話" so the admin menu reads naturally as just "会話"

= 1.5.19 =
* Changed: Conversations admin page now shows 8 columns instead of 12. The ID column is gone (still in the row's data-id and shown in the Session tooltip), Channel is folded into the Session column as a small badge, the separate Handoff column is folded into Status as a sub-badge, and Started has been merged into Last Active (start time is now in the cell tooltip). All sort options remain available

= 1.5.18 =
* Added: New `channel` column on the conversations table (web / line / etc.) plus a "Channel" column in the Conversations admin list, so multi-channel installations can tell at a glance which platform a conversation came from. Existing rows default to "web". Migration runs automatically on plugin update

= 1.5.17 =
* Fixed: Smaller AI models (e.g. Gemini 2.5 Flash Lite) still refused to answer "today's date" questions in 1.5.16 because the user's own system prompt typically forbids "inventing dates" and the model treated the injected date as a forbidden invention. The injected date block now (a) opens with a clear "OVERRIDE — TAKES ABSOLUTE PRECEDENCE" marker, (b) explicitly states that no-fabrication rules do NOT apply to the system-provided date, and (c) includes a few-shot example showing the expected answer format in both Japanese and English

= 1.5.16 =
* Fixed: Current date injection in 1.5.15 was only applied to the main send-message endpoint and was sometimes ignored by weaker AI models that interpreted the system prompt's "do not invent dates" rule too literally. Now hooked on the `raplsaich_system_prompt` filter at priority 99 so all endpoints (regenerate, suggestions, MCP tool, etc.) receive it, and the prompt explicitly states the date is authoritative system context — not fabrication

= 1.5.15 =
* Added: System prompt now injects the current date (in the site's WordPress timezone) so the AI can correctly resolve relative time references like "today", "yesterday", or "this week" without needing them in the knowledge base

= 1.5.14 =
* Fixed: Root cause of the iPhone Safari "close button off-screen" issue — chat input textarea now uses font-size: 16px so iOS Safari no longer auto-zooms the viewport on focus. The iOS keyboard fix from 1.5.13 is kept as a secondary safeguard
* Fixed: Reference link cards now survive a page reload — message display metadata (sources, cards, web sources) is persisted on the message row and returned by the history endpoint
* Fixed: Link card titles and excerpts no longer show literal HTML entities like `&amp;`
* Fixed: Link card excerpts strip common Markdown syntax (`**`, backticks, `#`, `[text](url)`, etc.) so previews read as plain prose
* Fixed: Link card rows on mobile no longer overflow past the chat bubble — added `min-width: 0` to the message content flex child so the inner horizontal scroll container takes effect

= 1.5.13 =
* Added: Optional iOS Safari keyboard fix (opt-in) — when enabled, uses the VisualViewport API to keep the chatbot header (including the close button) on-screen while the on-screen keyboard is visible. Disable this setting if it conflicts with your theme's scroll behavior. Default: off

= 1.5.12 =
* Fixed: Vector embedding search checkbox could not be turned off after being enabled — unchecked state was ignored because HTML forms omit unchecked checkboxes from POST
* Fixed: Badge position margin setting was not applied on mobile screens (<=480px) due to a hardcoded media-query override
* Fixed: Primary color was not applied to the badge icon on the Simple / Classic / Light / Minimal / Flat themes (background color was hardcoded)
* Fixed: On iPhone Safari, focusing the chat input pushed the close button off-screen — switched to `100dvh` dynamic viewport height
* Added: Separate margin settings for desktop and mobile screens
* Added: Badge icon size setting (30–120px), separate values for desktop and mobile

= 1.5.11 =
* Fixed: Fatal error on Settings page in 1.5.10 — template include helper broke local variable scope causing `$openai_provider` to be null. Templates are now included inline with a file_exists() guard so render-method locals stay accessible

= 1.5.10 =
* Fixed: Re-deploy all template files (`templates/admin/*.php`) — the 1.5.9 release was missing these due to an SVN upload issue, causing admin screens to render blank
* Added: Admin templates now render a visible error notice instead of a blank page if any template file is missing, so future deploy issues are immediately diagnosable

= 1.5.9 =
* Removed: Discontinued OpenAI models from the dropdown — `gpt-4`, `gpt-4-turbo`, `gpt-3.5-turbo`, `o1`, `o1-pro` (all retired by OpenAI)
* Removed: Discontinued Claude model `claude-3-7-sonnet-20250219` from the dropdown (retired by Anthropic)

= 1.5.8 =
* Fixed: Gemini API key validation now uses the models list endpoint (no more false "Invalid API key" errors for valid keys)
* Fixed: API key is saved automatically on successful connection test (previously required a separate save step)
* Fixed: Gemini embedding model updated from deprecated `text-embedding-004` to `gemini-embedding-001` (with 768-dim output for backward compatibility)
* Removed: Discontinued Gemini 1.5 series models (Pro / Flash / Flash 8B) from the model dropdown — Google retired these models
* Added: `gemini-2.0-flash-lite` to the model dropdown

= 1.5.7 =
* Updated: Japanese translations — full-width parentheses replaced with half-width per WordPress Style Guide
* Updated: Dashboard Docs links and review request banner

= 1.5.6 =
* Fixed: Response Language setting now works when set to "Site language" — was silently ignored
* Fixed: AI responses in wrong language when RAG context is in a different language (triple enforcement)
* Fixed: Chatbot placeholder language now respects site locale over browser language
* Fixed: Response cache now includes language in hash key to prevent stale translations
* Fixed: Reset confirm dialog newlines not rendering in prompt()
* Improved: Response language instruction placed at beginning, end of system prompt, and in user message

= 1.5.5 =
* Improved: Complete Free/Pro code separation — all Pro UI code moved to separate Pro plugin
* Improved: Frontend chatbot.js reduced by 50% (4,300 → 2,175 lines) for faster page loads
* Improved: Consolidated admin menu into single "Pro Features" overview page
* Improved: Hook-based extension architecture for Pro features
* Security: Removed arbitrary custom CSS injection — use WordPress Customizer instead
* Security: All CSS variable values escaped with esc_attr(), position margins with absint()
* Security: Block render output sanitized with wp_kses() and widget-aware allow-list
* Security: Added `rel="noopener noreferrer"` to all external links
* Security: Inline JS/CSS now uses wp_add_inline_script() and wp_add_inline_style() instead of raw output
* Fixed: Lead form display and submission when Pro is active
* Changed: Settings key renamed from `pro_features` to `extensions` with automatic migration
* Changed: Unique prefix `raplsaich_` applied to all functions, options, hooks, and REST namespace
* Updated: External Services section with per-service documentation and embed-loader.js clarification
* Updated: Chart.js to v4.5.1, html2canvas bundled locally

= 1.5.2 =
* Fixed: WordPress Plugin Check compliance (WP_Filesystem annotations, prepared SQL annotations)
* Removed: Artificial free-tier limits — all core features are fully available
* Removed: Default "Powered by" footer from chatbot widget
* Updated: Neutral error messages replacing promotional upsell text

= 1.5.0 =
* Added: Gutenberg block — Insert AI Chatbot block in the block editor with height, theme, and bot-id settings; SSR (server-side rendering) support; i18n (JA/EN translation JSON)
* Added: WordPress Abilities API Bridge — Register all 7 MCP tools as WordPress Abilities for auto-discovery by MCP Adapters (Claude Desktop, Cursor, VS Code)
* Added: Response language auto-detect — Automatically detect browser language for welcome message and AI responses; choose from "Site language", "Auto-detect", or manual
* Added: OpenRouter provider support (100+ models via single API key)
* Added: Pro add-on compatibility layer for extended features
* Fixed: MCP tool registration timing for reliable integration
* Fixed: Abilities API category registration and naming compliance
* Updated: Japanese translation — all strings translated including Abilities API, Gutenberg block, and response language settings
* Updated: WordPress Plugin Check compliance fixes

= 1.4.0 =
* Added: Web search integration — AI automatically searches the web when knowledge base lacks a sufficient answer (OpenAI web_search_preview, Claude web_search, Gemini google_search grounding)
* Added: Web search toggle in AI Settings tab with per-provider cost notice
* Added: Web source citations displayed with globe icon, separate from knowledge base sources
* Added: Cross-site embed page — embed chatbot on external sites via iframe (?raplsaich_embed=1 endpoint)
* Added: Embed loader script (assets/js/embed-loader.js) for easy cross-site integration
* Added: PDF and DOCX file upload support in knowledge base (server-side parsing)
* Added: Vector embedding RAG with hybrid search (keyword 40% + vector 60%)
* Updated: AI model lists — OpenAI GPT-5.2/5.1/5/4.1 series, Claude Opus 4.6/Sonnet 4.5/Haiku 4.5, Gemini 3/2.5 series
* Updated: Japanese translation

= 1.3.2 =
* Security: Session ID now transmitted via `X-RAPLSAICH-Session` header instead of query strings (prevents access log leakage)
* Security: GET requests no longer accept `?session_id=` query parameter
* Security: POST requests ignore body `session_id` when header is present (prevents APM/WAF body-logging leakage)
* Security: Removed client-side `raplsaich_user_id` remnant from JavaScript
* Security: Context key derivation simplified to session-only HMAC (removed IP binding for stability)
* Security: DOM-based URL linking and offline form rendering (XSS hardening)
* Security: Dompdf post-init safety assertion (`isPhpEnabled` / `isRemoteEnabled` check)
* Added: Rate-limited error logging (`raplsaich_rate_limited_log()`) with filterable interval via `raplsaich_rate_limited_log_interval`
* Added: Server-side offline message dedup (30-second window, session-preferred key)
* Added: Client-side offline form dedup via sessionStorage
* Improved: Offline message endpoint allows unauthenticated submissions (`allow_no_headers`)
* Improved: Rate limit fallback keys hashed to prevent `wp_options` bloat
* Improved: Standardized REST error responses with `error_code` field
* Improved: Dompdf errors return JSON response instead of `wp_die()` for better admin UX
* Improved: REST API session authentication documented in readme

= 1.3.1 =
* Added: Pro add-on compatibility for enhanced rate limiting and PDF export
* Improved: Rate limit error messages are now customizable
* Improved: Diagnostic options renamed to `raplsaich_diag_*` namespace
* Improved: Frontend debug minimum capability is now filterable via `raplsaich_frontend_debug_min_cap`

= 1.3.0 =
* Added: Pro add-on compatibility for response caching, audit logs, conversion tracking, offline messages, and answer templates
* Improved: Knowledge base supports 'qa' and 'template' entry types
* Improved: Database schema updates for caching and conversion tracking

= 1.2.23 =
* Added: Sortable column headers in admin tables — Dashboard model stats, Conversations, Knowledge, and Crawler pages now support click-to-sort with ascending/descending toggle
* Added: Knowledge base draft status — entries can be "published" or "draft", with filter tabs and draft count badge
* Added: Enhanced content extraction (Pro) — DOMDocument-based HTML parsing preserves document structure as Markdown-style text for better AI context
* Added: Session reset feature — administrators can invalidate all existing chat sessions at once
* Improved: Session cookie set as httpOnly with SameSite=Lax for better security
* Improved: Knowledge base model supports status filtering and sorting with SQL whitelist validation
* Improved: Content index model supports orderby/order parameters for sorted admin views
* Updated: Japanese translation

= 1.2.22 =
* Improved: Default system prompt now enforces accuracy, honesty, and no-fabrication rules for better AI response quality
* Improved: Site learning context prompt explicitly instructs AI not to guess or fabricate when information is missing
* Added: `raplsaich_system_prompt` filter — developers can now modify the system prompt programmatically
* Added: Customizable feature prompts — regenerate instruction, feedback learning headers, and summary prompt are now editable in Settings
* Added: Advanced prompt sections gated behind checkboxes (disabled by default) for safe editing
* Added: Placeholder documentation for regeneration prompt ({variation_number}, {forbidden_start}, {style})
* Updated: Debugging guide with new prompt customization section

= 1.2.21 =
* Added: Knowledge base export support (CSV/JSON) — available with Pro add-on
* Added: Budget limit check integration in REST API (blocks AI calls when Pro budget limit exceeded)
* Added: Budget alert hook after AI responses (triggers Pro email notifications)
* Added: Pro features stub methods for budget management (check_budget_limit, get_budget_block_message, maybe_send_budget_alert)
* Improved: Default settings include budget and monthly report configuration keys
* Added: Detailed debugging guide documentation (docs/debugging-guide.md)

= 1.2.20 =
* Added: Knowledge page prefill support (prefill_question parameter for quick FAQ creation from analytics)

= 1.2.19 =
* Security: API key encryption now covers Google Gemini keys (AIza...) in addition to OpenAI and Claude
* Security: Added OpenSSL availability check with graceful fallback for encryption/decryption
* Security: Conversation history endpoint (/history) now verifies session ownership via cookie and IP
* Security: Chart.js bundled locally instead of loading from external CDN (WordPress.org compliance)
* Added: Session cookie (raplsaich_session_id) set on session creation for reliable history access across IP changes

= 1.2.18 =
* Security: Sanitized API error messages to prevent information leakage
* Security: Proxy-aware client IP detection for rate limiting (Cloudflare, X-Forwarded-For)
* Security: Consistent SHA-256 hashing for IP-based rate limiting
* Fixed: User message duplication in AI context (improved response accuracy)
* Fixed: Uninstall now removes all tables including leads and user_context
* Fixed: Transient cleanup uses prepared statements
* Improved: Database schema checks cached per request (performance)
* Improved: Consolidated database upgrade logic in Activator class
* Improved: Pro-only REST routes only registered when Pro is active
* Added detailed Japanese descriptions for all AI models
* Translation improvements

= 1.2.5 =
* Security improvements and code quality enhancements
* WordPress Plugin Check compliance updates
* Updated AI model pricing information
* Bug fixes and performance improvements

= 1.0.0 =
* Initial release
* Multiple AI provider support (OpenAI, Claude, Gemini)
* Site learning with automatic content crawling
* Custom knowledge base with Q&A format support
* Priority levels for knowledge entries
* Conversation history with search
* Usage statistics with cost estimation
* Daily token usage charts
* Model-by-model cost breakdown
* Customizable chatbot appearance
* Rate limiting support
* Quota error handling with custom messages
* Settings import/export/reset
* Japanese translation included
* Mobile-responsive chat widget
* Page exclusion settings



== Upgrade Notice ==

= 1.7.0 =
New Glossary feature protects product / brand / service names from mistranslation across all 13 supported reply languages. Especially recommended for multilingual sites.

= 1.6.0 =
Milestone release: current-date awareness, channel tracking, decluttered Conversations admin, and infrastructure to keep LINE answers in parity with Web. Recommended for everyone.

= 1.5.17 =
Strengthens the date injection so smaller AI models (Gemini Flash Lite, etc.) can no longer dismiss it. Recommended for anyone on 1.5.15 or 1.5.16.

= 1.5.16 =
Reliability fix for the 1.5.15 date injection — recommended for anyone running 1.5.15.

= 1.5.15 =
The chatbot now knows today's date so it can answer questions involving "today", "yesterday", or relative timeframes correctly.

= 1.5.12 =
Fixes multiple Display Settings bugs (vector-search toggle, mobile margin, theme primary color, iPhone Safari overflow) and adds mobile-specific margin and badge size settings.

= 1.5.11 =
Fixes a fatal error introduced in 1.5.10 on the Settings page. Critical update.

= 1.5.10 =
Re-ships template files missing from 1.5.9 (SVN upload issue). Users of 1.5.9 who saw blank admin screens should update immediately.

= 1.5.9 =
Removes discontinued OpenAI and Claude models from the model dropdown. Recommended for all users.

= 1.5.8 =
Fixes Gemini API key validation, auto-saves keys on test, updates deprecated Gemini models. Strongly recommended for Gemini users.

= 1.5.7 =
Translation and UI improvements. Recommended for all users.

= 1.5.6 =
Response Language setting fix: AI now correctly responds in the configured language. Recommended for all users.

= 1.5.5 =
Major update: Complete Free/Pro code separation, 50% smaller frontend JS, security hardening (CSS escaping, output sanitization), and unique prefix. Recommended for all users.

= 1.5.2 =
Plugin Check compliance fixes and artificial limits removed. Recommended update.

= 1.5.0 =
Major update: Gutenberg block, Abilities API, language auto-detect, OpenRouter. Recommended update for all users.

= 1.4.0 =
Feature release: Web search integration (AI auto-searches the web when knowledge base is insufficient), cross-site embed support, PDF/DOCX knowledge upload, vector embedding hybrid search, and updated AI model lists. Recommended update for all users.

= 1.3.2 =
Security hardening: session ID header transport, XSS prevention via DOM API, rate-limited logging, and offline message dedup. **Breaking change:** GET requests no longer accept `?session_id=` — use the `X-RAPLSAICH-Session` header instead. Recommended update for all users.

= 1.3.1 =
Enhanced rate limiting, server-side PDF export, and diagnostic option namespace migration (`raplsaich_diag_*`). Recommended update for all users.

= 1.3.0 =
Major feature release: Response caching, audit logs, conversion tracking, offline messages, and answer templates. Reduces API costs and adds business-critical Pro features. Recommended update for all users.

= 1.2.23 =
Sortable admin tables, knowledge base draft workflow, enhanced content extraction, and session management improvements. Recommended update for all users.

= 1.2.22 =
Improved AI chat accuracy with better default prompts. All feature prompts now customizable. Added raplsaich_system_prompt filter. Recommended update for all users.

= 1.2.21 =
Added knowledge base export support, budget management integration, and comprehensive debugging guide. Recommended update for Pro users.

= 1.2.20 =
Knowledge page prefill support for quick FAQ creation from analytics.

= 1.2.18 =
Security and performance improvements. Fixed message duplication bug affecting AI response accuracy. Recommended update for all users.

= 1.2.5 =
Security and code quality improvements. Recommended update for all users.

= 1.0.0 =
Initial release of Rapls AI Chatbot. Configure your AI provider API key to get started.
