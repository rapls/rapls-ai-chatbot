=== Rapls AI Chatbot ===

Contributors: rapls
Tags: chatbot, ai, claude, rag, wordpress-7
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 1.8.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Claude-powered RAG chatbot for WordPress. WP 7.0 Connectors API, MCP server, site learning, knowledge base, web search, 13-language auto-reply.

== Description ==

**Rapls AI Chatbot** — a Claude-native RAG chatbot for WordPress, fully optimized for the WordPress 7.0 Connectors API.

= Why Claude? =

* **Native Claude Support** — First-class Claude Opus 4.6 / Sonnet 4.5 / Haiku 4.5 with built-in web search
* **MCP Server** — 7 built-in tools via JSON-RPC 2.0 for agent integration (Claude Desktop, Cursor, VS Code)
* **Superior RAG Performance** — Accurate retrieval-augmented generation with vector embedding + keyword hybrid search

= WordPress 7.0 & Connectors API =

**Full WP 7.0 integration:**
* Register MCP tools as WordPress Abilities for discovery by Connectors UI
* Unified API key management via Connectors API (no duplicate key storage per plugin)
* Support for Connectors-registered AI providers (OpenAI, Gemini, custom providers also supported)

👉 **Implementation details:** [WordPress 7.0 の WP AI Client を実装目線で読み解いた話](https://raplsworks.com/wp-ai-client-wordpress-7-0/)

= Core Features =

* **AI Providers** — Claude (native), OpenAI, Google Gemini, OpenRouter (100+ models)
* **RAG Site Learning** — Crawl posts, pages, custom post types, WooCommerce products. Hybrid search: 40% keyword + 60% vector similarity
* **Knowledge Base** — Q&A pairs, free-form content, PDF/DOCX uploads with priority levels and draft workflow
* **Web Search** — AI auto-searches when knowledge base insufficient (Claude web_search, OpenAI web_search_preview, Google search)
* **MCP Server** — 7 built-in tools for agent integration
* **Gutenberg Block** — Insert with height, theme, bot-id settings; SSR support
* **Language Auto-detect** — Multilingual responses in 13 languages (English, Japanese, Chinese, Korean, Spanish, French, German, Portuguese, Italian, Russian, Arabic, Thai, Vietnamese)
* **Cross-site Embed** — iframe or script loader for external sites
* **Security** — reCAPTCHA v3, rate limiting, consent mode, Cloudflare support, security diagnostics
* **Conversation History** — Save & review all chats with configurable retention
* **Usage Statistics** — Token usage, estimated costs, visual charts, provider breakdown
* **6 Built-in Themes** — Customizable colors, badges, mobile display

= Supported AI Models =

**Anthropic Claude (Recommended):**
* Claude Opus 4.6 (Most powerful)
* Claude Sonnet 4.5 (Fast & powerful — recommended)
* Claude Haiku 4.5 (Fastest — recommended)

**OpenAI:**
* GPT-4.1, GPT-4o, GPT-4o-mini
* o1, o3 (Reasoning models)

**Google Gemini:**
* Gemini 3 Pro/Flash
* Gemini 2.5 Pro/Flash

**OpenRouter:**
* 100+ models via single API key

= Free vs Pro =

**Free version** includes:
* All AI providers (Claude, OpenAI, Gemini, OpenRouter)
* Unlimited responses & knowledge base
* RAG site learning with vector search
* MCP server, Gutenberg block, cross-site embed
* 6 themes, feedback, reCAPTCHA, security diagnostics

**Pro add-on** adds:
* Analytics, lead capture, conversation scenarios, operator mode
* WooCommerce product cards, LINE Messaging API, Slack notifications
* 10 additional themes, dark mode, voice I/O, encryption, audit logs

Learn more: [Pro Features](https://raplsworks.com/rapls-ai-chatbot-pro/) | [Developer Overview](https://raplsworks.com/rapls-ai-chatbot-guide/)

== Installation ==

1. Upload `rapls-ai-chatbot` folder to `/wp-content/plugins/`
2. Activate via Plugins menu
3. Go to AI Chatbot > Settings
4. Configure AI provider (Claude recommended)
5. Enable site learning or create knowledge base entries
6. Insert Gutenberg block or enable sitewide display

= Getting Started =

1. **API Key** — Get Claude API key from [console.anthropic.com](https://console.anthropic.com)
2. **Enable RAG** — Add site learning (auto-crawl) or knowledge base entries
3. **Customize** — Set bot name, avatar, welcome message, system prompt
4. **Deploy** — Insert block or enable sitewide

👉 **Setup Guide:** [Rapls AI Chatbot Implementation Guide](https://raplsworks.com/rapls-ai-chatbot-guide/)

== Frequently Asked Questions ==

= Do I need an API key? =
Yes. You need at least one: Claude (recommended), OpenAI, Google Gemini, or OpenRouter.

= Can I use multiple AI providers? =
Yes. Configure multiple providers in Settings and switch between them. WordPress 7.0 Connectors API also supports unified key management.

= Does it crawl my entire site automatically? =
Yes. Enable "Site Learning" in Settings to crawl published content (posts, pages, custom post types, WooCommerce products). Configure crawl scope and frequency.

= Can I embed on external sites? =
Yes. Configure cross-site embed in Display Settings. Use iframe or script loader.

= How do I set up WordPress 7.0 Connectors API? =
In Settings > AI Settings, Connectors UI appears if WP 7.0 is active. Register your Claude API key once; all Connectors-compatible plugins access it.

= Is there conversation history? =
Yes. Data Management tab lets you save/review all conversations. Configure retention period (30/90/365 days or indefinite).

= Troubleshooting: chat not appearing? =
* Verify API key is valid (test in Settings)
* Check Gutenberg block or Display Settings (enable sitewide)
* Review Security Diagnostics for rate limit, IP detection, or consent issues

= More questions? =
See [Implementation Guide](https://raplsworks.com/rapls-ai-chatbot-guide/) or [WordPress.org Support](https://wordpress.org/support/plugin/rapls-ai-chatbot/)

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

== Changelog ==

= 1.8.2 =
* Documentation: refreshed the plugin description, feature list, FAQ, and search tags. No functional changes.

= 1.8.1 =
* Fixed: "Undefined array key link_target" PHP warning emitted by the chatbot widget on sites where the link-target option had never been saved.

= 1.8.0 =
* Added: WordPress 7.0 "AI Client (Connectors)" provider — chat routes through wp_ai_client_prompt(); API keys and models are managed in Settings → Connectors instead of the plugin.
* Added: Curated cross-provider model dropdown, plus an automatic one-shot retry without temperature for models (GPT-5 / o-series) that reject a custom value.
* Fixed: API-key-decryption "please re-enter" notice now fires only when the active provider's own key is broken; the wpai path skips the plugin-side key pre-check.
* Tested up to: WordPress 7.0.

= 1.5.6 =
* Response Language setting fix
* Recommended for all users

= 1.5.0 =
* Major update: Gutenberg block, Abilities API, language auto-detect, OpenRouter

= 1.4.0 =
* Web search, cross-site embed, PDF/DOCX knowledge, vector embedding hybrid search

= 1.3.0 =
* Response caching, audit logs, conversion tracking, offline messages

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.8.2 =
Documentation refresh — updated description, features, and FAQ. No functional changes.

= 1.8.1 =
Fixes a PHP "Undefined array key" warning in the chatbot widget. Recommended for all.

= 1.8.0 =
Adds the WordPress 7.0 "AI Client (Connectors)" provider option. Recommended for WordPress 7.0 sites.

= 1.5.0 =
Major release: Gutenberg block, Abilities API, language auto-detect. Recommended for all users.
