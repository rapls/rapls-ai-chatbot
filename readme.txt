=== Rapls AI Chatbot ===

Contributors: rapls
Tags: ai chatbot, openrouter, claude, rag, mcp
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 1.9.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Answer visitor questions from your own site content. Self-hosted AI chatbot for WordPress with bring-your-own-key (OpenAI, Claude, Gemini, OpenRouter), RAG, and MCP.

== Description ==

Rapls AI Chatbot adds an AI assistant that answers your visitors from your own content. When someone asks a question, the bot searches your posts, pages, and knowledge base first, then replies in their own words. It runs on your own API key, so there is no monthly SaaS fee and no third party storing your conversations. You connect OpenAI, Anthropic Claude, Google Gemini, or OpenRouter.

It is built for developers, agencies, and technical site owners who want control over the model, the data, and the cost. If you do not have an API key yet, you can start for free with no credit card: pick OpenRouter free models or the Google Gemini free tier, and the plugin guides you through it on first setup.

= What it does =

* Bring your own key: OpenAI, Anthropic Claude, Google Gemini, or OpenRouter, switchable per site.
* Site learning: indexes your posts and pages so the bot answers from your content.
* Knowledge base: add Q&A or upload PDF/DOCX files, with priority over general answers.
* RAG hybrid search: combines keyword and semantic retrieval for grounded replies.
* Web search: lets the bot pull current information when configured.
* MCP tools: exposes 5 Model Context Protocol tools, so agents such as Claude or ChatGPT can read and act on your site through conversation.
* Usage dashboard: tracks conversations, messages, and API cost.
* Gutenberg block: drop the chatbot into any page or post.

= Self-hosted and private =

Conversations and keys stay on your own WordPress install. You are billed by your AI provider directly, so cost is transparent and there is no markup.

= Free and Pro =

The free version covers the full chatbot, site learning, knowledge base, RAG, MCP, and the usage dashboard. Pro adds lead capture automation and RAG hybrid search fine-tuning.

= Get started without paying =

On first setup the plugin shows two no-credit-card paths: an OpenRouter free key or a Google Gemini free-tier key. Pick one, paste the key, run the connection test, and the chatbot works immediately — a working free model is auto-selected for you. Each option states its data-handling trade-off up front (the Gemini free tier may use submitted content to improve Google's models), so you choose with eyes open. You can switch to your own OpenAI, Claude, or Gemini key at any time.

Learn more: [Plugin details](https://raplsworks.com/plugins/rapls-ai-chatbot/) | [Source code (GitHub)](https://github.com/rapls/rapls-ai-chatbot)

== Installation ==

1. Upload `rapls-ai-chatbot` folder to `/wp-content/plugins/`
2. Activate via Plugins menu
3. Go to AI Chatbot > Settings
4. Follow the onboarding panel to start free with an OpenRouter or Google Gemini key, or paste your own OpenAI / Claude / Gemini key.
5. Enable site learning or create knowledge base entries
6. Insert Gutenberg block or enable sitewide display

= Getting Started =

1. **API Key**: get a free OpenRouter or Google Gemini key from the onboarding panel, or your own key from [console.anthropic.com](https://console.anthropic.com), OpenAI, or Google AI Studio.
2. **Enable RAG**: add site learning (auto-crawl) or create knowledge base entries.
3. **Customize**: set bot name, avatar, welcome message, system prompt.
4. **Deploy**: insert the Gutenberg block, paste the shortcode, or enable sitewide display.

👉 **Plugin details:** [Rapls AI Chatbot](https://raplsworks.com/plugins/rapls-ai-chatbot/)

== Frequently Asked Questions ==

= Do I need an API key? =
Yes. Rapls runs on your own API key so your data and cost stay under your control. If you do not have one, the plugin guides you to a free OpenRouter key or a free Google Gemini key on first setup — both need no credit card.

= Can I use it for free? =
Yes. The plugin itself is free, and you can run it at no cost using either OpenRouter free models or the Google Gemini free tier. The onboarding panel lets you pick either one. Free models and free tiers have rate limits, so for production traffic you may want a paid model or your own provider key.

= Is my data private on the free options? =
It depends on the provider you choose, and the plugin tells you before you pick. OpenRouter free models are served by various upstream providers, each with its own data-handling policy. The Google Gemini free tier may use your submitted content to improve Google's models — if you do not want that, use a paid Gemini tier or another provider. Either way, your conversations and keys are stored on your own WordPress install, not on Rapls servers.

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
See [Plugin details](https://raplsworks.com/plugins/rapls-ai-chatbot/) or [WordPress.org Support](https://wordpress.org/support/plugin/rapls-ai-chatbot/)

== External Services ==

This plugin connects to the following external third-party services. **No data is sent to any service until you configure an API key and enable the feature in the plugin settings.** Each service requires the site administrator to create an account and obtain API credentials. By using these services, you agree to their respective terms and privacy policies listed below.

= 1. OpenAI (GPT models): AI Provider =

Used when you select OpenAI as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://api.openai.com/](https://api.openai.com/)
* Terms of Use: [https://openai.com/terms/](https://openai.com/terms/)
* Privacy Policy: [https://openai.com/privacy/](https://openai.com/privacy/)

= 2. Anthropic (Claude models): AI Provider =

Used when you select Anthropic Claude as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://api.anthropic.com/](https://api.anthropic.com/)
* Terms of Use: [https://www.anthropic.com/terms](https://www.anthropic.com/terms)
* Privacy Policy: [https://www.anthropic.com/privacy](https://www.anthropic.com/privacy)

= 3. Google (Gemini models): AI Provider =

Used when you select Google Gemini as your AI provider. User messages and optionally site content are sent to generate AI responses.

* Service URL: [https://generativelanguage.googleapis.com/](https://generativelanguage.googleapis.com/)
* Terms of Use: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= 4. OpenRouter: AI Provider =

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

The plugin includes an optional embed loader script (`embed-loader.js`) for embedding the chatbot on external websites via an iframe. This script does not load any external CDN resources or third-party scripts. It creates an iframe pointing back to your own WordPress site, and all data processing occurs on your server.

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

= 1.9.2 =
* Added: The "Start for free" onboarding now offers two no-credit-card paths — OpenRouter free models and the Google Gemini free tier — so new users can pick the provider that fits. Either choice validates the key, runs a connection test, and auto-selects a working free model (Gemini defaults to `gemini-2.5-flash`).
* Added: Clear in-panel disclosure for each free option, shown before you choose. The Gemini free tier may use submitted content to improve Google's models; the panel says so and points to paid tiers or other providers if you want your data excluded. OpenRouter free models carry an equivalent note that upstream data-handling varies.
* Docs: Refreshed the description, Getting Started, and FAQ for the two free paths, and added a "Is my data private on the free options?" FAQ entry.

= 1.9.1 =
* Fixed: Chat window froze with no reply shown when the AI answered with a markdown table. The table separator-row regex rejected the standard GFM trailing pipe (`|---|---|`), and the paragraph fallback then looped forever on the unconsumed line, exhausting browser memory. Tables now render, and the renderer always makes forward progress on any input.
* Fixed: Literal `<br>` tags that AI models emit inside markdown table cells now render as line breaks instead of showing as raw text.
* Fixed: The Remove button for the OpenAI / Gemini API key had no effect on WordPress 7.0 sites. The "WordPress AI Client" RAG section renders duplicate hidden fields with the same names later in the DOM, so its delete-flag "0" and empty key field overrode the visible section's values on submit. Hidden provider sections are now excluded from form submission.
* Improved: After the onboarding connection test succeeds, a prominent "Reload this page now" button appears (with auto-scroll and focus) so the saved provider and model are applied before any further edits to the settings form.

= 1.9.0 =
* Added: "Start for free" onboarding panel that appears on the Settings page when no provider API key has been saved. Walks new users from zero to a working chatbot in about a minute using an OpenRouter free API key (no credit card required). Key is encrypted (AES-256-GCM), provider auto-set to OpenRouter, and a concrete free model id (e.g. `openai/gpt-oss-120b:free`) is picked from the live `/v1/models` catalog and saved as the active model.
* Added: Automatic 429 fallback for free models. When the active `:free` model is throttled upstream (Venice / NVIDIA / etc), the active model is marked as throttled for 10 minutes, an alternative `:free` model is picked from the catalog, persisted to settings, and the chat request is retried once. Paid models pass quota errors through unchanged.
* Changed: Plugin description and search tags repositioned from "Claude-native RAG chatbot" to "self-hosted, BYOK, multi-provider" so the listing reflects the actual feature set (OpenAI, Anthropic Claude, Google Gemini, OpenRouter). Two new FAQ entries: "Do I need an API key?" and "Can I use it for free?".
* Hardened: OpenRouter key validation now requires the `sk-or-` prefix and hits `/v1/auth/key` (real auth check) instead of `/v1/models` (which returns 200 for any string). The existing per-provider Test Connection button shares the fix.
* Fixed: Rate-limit countdown badge no longer wipes the paper-plane SVG icon (the icon is restored when the cooldown ends), and the remaining-seconds text is now high-contrast white on the brand-color disc instead of blending with the disabled-gray background.
* Added: Free-version manual (Japanese and English) gains a "Quick Start (run it for free)" section explaining the onboarding flow and rate-limit notes.

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

= 1.9.2 =
The free onboarding now lets you choose between OpenRouter free models and the Google Gemini free tier, each with an up-front data-handling note. Recommended for all users.

= 1.9.1 =
Fixes a chat freeze when AI responses contain markdown tables, and API key deletion on WordPress 7.0 sites. Recommended for all users.

= 1.9.0 =
Adds a one-minute onboarding flow that lets new users connect a free OpenRouter API key (no credit card) and start chatting immediately. Includes automatic fallback when free models are rate-limited upstream.

= 1.8.2 =
Documentation refresh — updated description, features, and FAQ. No functional changes.

= 1.8.1 =
Fixes a PHP "Undefined array key" warning in the chatbot widget. Recommended for all.

= 1.8.0 =
Adds the WordPress 7.0 "AI Client (Connectors)" provider option. Recommended for WordPress 7.0 sites.

= 1.5.0 =
Major release: Gutenberg block, Abilities API, language auto-detect. Recommended for all users.
