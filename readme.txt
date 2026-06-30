=== Rapls AI Chatbot ===

Contributors: rapls
Tags: ai chatbot, openrouter, claude, rag, mcp
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 1.11.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot for WordPress that answers visitors from your own content. Start free in 5 minutes, no credit card. OpenAI, Claude, Gemini, OpenRouter.

== Description ==

**Add an AI chatbot to WordPress that answers visitors from *your own* content — not generic web answers.** When someone asks a question, Rapls AI Chatbot searches your posts, pages, and knowledge base first, then replies in natural language, in the visitor's own language. It turns the content you already have into a 24/7 assistant for support, pre-sales, and FAQs.

**You can be live in about five minutes, for free, with no credit card.** On first setup a guided "Start for free" panel hands you a free key from OpenRouter or the Google Gemini free tier, tests it, auto-selects a working model, and switches the chatbot on — no AI or API experience needed. There is no monthly SaaS fee and no per-message markup: it runs on your own API key, and your conversations stay on your own server.

Built for site owners, agencies, and developers who want control over the model, the data, and the cost. Connect OpenAI, Anthropic Claude, Google Gemini, or OpenRouter, and switch anytime.

= Why site owners pick Rapls =

* **Free to try, fast to launch.** Guided onboarding gets you from install to a working chatbot in minutes, with a no-credit-card OpenRouter or Gemini key.
* **Answers from your site, not the open web.** RAG hybrid search grounds replies in your actual posts, pages, and knowledge base, so answers stay on-brand and accurate.
* **Your data, your bill.** Self-hosted: conversations and keys live on your WordPress install, and you pay your AI provider directly with no markup.
* **No lock-in.** Switch between OpenAI, Claude, Gemini, and OpenRouter whenever you want.
* **Speaks your visitors' language.** Automatic multi-language replies, so one bot serves an international audience.

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

= Up and running in about 5 minutes (free, no credit card) =

You do not need an API key or any AI experience to start. On first setup the plugin shows a "Start for free" panel with two no-credit-card paths: an OpenRouter free key or a Google Gemini free-tier key.

1. Pick OpenRouter or Gemini.
2. Click through to get a free key (about a minute) and paste it in.
3. Press Test Connection — the key is validated and saved, a working free model is auto-selected, and the chatbot is switched on.

That's it. Each option states its data-handling trade-off up front (the Gemini free tier may use submitted content to improve Google's models), so you choose with eyes open. You can switch to your own OpenAI, Claude, or Gemini key at any time.

Learn more: [Plugin details](https://raplsworks.com/plugins/rapls-ai-chatbot/) | [Source code (GitHub)](https://github.com/rapls/rapls-ai-chatbot)

= See it in action =

Watch the full setup — from install to first reply — in about 5 minutes, no credit card required:

https://youtu.be/IzGM4BK7N8Q

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

= My Gemini key starts with "AQ." instead of "AIza" — is that OK? =
Yes. Google is moving Gemini API keys from the legacy "AIza" standard format to the new "AQ." auth format, and Google AI Studio now issues "AQ." keys by default. Rapls works with both. Google is retiring the standard format (unrestricted "AIza" keys are rejected from 2026-06-19, and all "AIza" keys from 2026-09), so if you still have an "AIza" key the plugin shows a notice with how to migrate. New keys created today are already "AQ." keys and need no action.

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

== Screenshots ==

1. Dashboard — conversations, messages, indexed pages, and custom knowledge counts, the active AI provider status, and 30-day token usage with an estimated cost.
2. AI Settings — choose your provider (OpenAI, Anthropic Claude, Google Gemini, or OpenRouter), enter your API key, pick a model, and enable Vector Embedding (RAG).
3. Site Learning — auto-crawl posts, pages, and custom post types so the bot answers from your own content.
4. Knowledge base — add custom text/Q&A or import TXT, CSV, MD, PDF, or DOCX files.
5. Conversations — review, filter, archive, and export visitor conversations with lead and status details.
6. Chat widget — the front-end chatbot answering a visitor from your site content.
7. Analytics — conversation, message, cost, and quality metrics (Pro).
8. Start for free — the onboarding panel connects a no-credit-card OpenRouter or Google Gemini free key in about a minute, with each option's data-handling trade-off shown up front.

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

= 1.11.0 =
* Added: "Grounded Answers Only" mode (Settings → AI Settings). When on, if the knowledge base and site learning have no content relevant to a question, the bot replies that it could not find the information instead of answering from the model's general knowledge — reducing hallucination. The relevance threshold and the "not found" message are configurable; works across keyword and vector (RAG) search. Off by default; ignored when Web Search is on.
* Added: "Usage Control" (Settings → Security) — an optional safety valve for your own API spend (BYOK) when the chatbot is open to guests or members. Caps total daily usage per guest and per logged-in user (separate from the per-IP rate limit). Counts are kept server-side and cannot be bypassed by the client; guests are identified by a salted hash (never a raw IP) with a configurable retention period and automatic cleanup. Role-based limits, per-user credits, and a usage dashboard are available in Pro.

= 1.10.0 =
* Added: "AI-smell score" for Japanese bot replies in the Conversations log. When enabled (Settings → Chat Settings), each Japanese reply gets a read-only score and a category breakdown (stiff connectives, over-emphatic vocabulary, monotone endings, and more) with in-text highlighting, so you can spot wording that sounds machine-written. It only detects and scores — replies are never altered, non-Japanese replies are skipped, and it makes no external calls. Banned vocabulary and weights are customizable via the `rapls_humanizer_*` filters.

= 1.9.4 =
* Changed: The feedback buttons (👍👎) on bot messages now default to OFF on new installs. You can turn them on anytime in Settings → Chat Settings → "Show feedback buttons". Existing sites keep their current setting.

= 1.9.3 =
* Added: Support for Google's new "AQ." Gemini API keys. Google AI Studio now issues keys in the new auth-key format (`AQ.Ab…`) instead of the legacy "AIza" standard keys, which Google is retiring (unrestricted standard keys rejected from 2026-06-19, all standard keys from 2026-09). Onboarding, key validation, and every Gemini API call (chat, model list, and site-learning embeddings) now accept and work with both formats.
* Changed: Gemini requests now send the key in the `x-goog-api-key` header instead of the `?key=` query string — Google's recommended method, required for the new "AQ." keys, and it keeps the key out of server/proxy/CDN logs.
* Added: An admin notice warns when your saved Gemini key is a legacy "AIza" standard key, with the retirement dates and how to migrate to an "AQ." key (or restrict the existing one). It clears automatically once an "AQ." key is saved.

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

= 1.11.0 =
Adds optional "Grounded Answers Only" (anti-hallucination) and "Usage Control" (per-visitor/user caps to protect your API spend). Both off by default. Recommended for all users.

= 1.10.0 =
Adds an optional "AI-smell score" for Japanese bot replies in the Conversations log (detection only; replies are never changed). Recommended for all users.

= 1.9.4 =
Feedback buttons (👍👎) on bot messages now default to off for new installs. Existing settings are unchanged.

= 1.9.3 =
Adds support for Google's new "AQ." Gemini API keys (the legacy "AIza" format is being retired by Google) and warns if your current Gemini key needs migrating. Recommended for all Gemini users.

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
