=== Rapls AI Chatbot – Self-Hosted RAG & MCP Server ===

Contributors: rapls
Tags: ai chatbot, rag, chatbot, chatgpt, mcp
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.21.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Answers from your own posts and pages, says so when it doesn't know, and stops at the spending limit you set. Works with OpenAI, Claude or Gemini.

== Description ==

**Add an AI chatbot that answers visitors from *your own* content — and admits when it doesn't know.** Rapls AI Chatbot searches your posts, pages, and knowledge base first, then replies in natural language, in the visitor's own language. With Grounded Answers Only mode, if your content has no answer, the bot says so instead of making something up.

**You can be live in about five minutes, for free, with no credit card.** On first setup a guided "Start for free" panel hands you a free key from OpenRouter or the Google Gemini free tier, tests it, auto-selects a working model, and switches the chatbot on — no AI or API experience needed.

**Watch the setup, start to finish:**

https://www.youtube.com/watch?v=6mFve1lxvuw

**No monthly SaaS fee — and no runaway bill.** It runs on your own API key with no markup, and built-in Usage Control caps daily usage per visitor, so you can open the bot to the public without worrying about your API spend. A Monthly Cost Guard lets you set a hard budget, and Model Fallback keeps the bot answering on a lightweight model when your main model hits its quota. Conversations and keys stay on your own server.

Built for site owners, agencies, and developers who want control over the model, the data, and the cost. Connect OpenAI, Anthropic Claude, Google Gemini, or OpenRouter, or any OpenAI-compatible endpoint such as Alibaba Tongyi Qwen (DashScope) — useful where OpenAI and Gemini are hard to reach — and switch anytime.

= Why site owners pick Rapls =

* **Free to try, fast to launch.** Guided onboarding gets you from install to a working chatbot in minutes, with a no-credit-card OpenRouter or Gemini key.
* **Grounded answers, or an honest "I don't know."** RAG hybrid search grounds replies in your actual posts, pages, and knowledge base — and Grounded Answers Only mode keeps the bot from inventing answers your site doesn't contain.
* **Your bill can't run away.** Self-hosted and BYOK with no markup, plus per-visitor daily caps (Usage Control) so public traffic can't drain your API budget.
* **Replies that don't sound like a robot.** An optional AI-smell score reviews the bot's Japanese replies for machine-sounding wording, so you can tune the tone. (Detection only — replies are never altered.)
* **No lock-in.** Switch between OpenAI, Claude, Gemini, and OpenRouter whenever you want.
* **Speaks your visitors' language.** Automatic multi-language replies, so one bot serves an international audience.

= What it does =

* Bring your own key: OpenAI, Anthropic Claude, Google Gemini, OpenRouter, or any OpenAI-compatible endpoint (Alibaba Qwen/DashScope, DeepSeek, Zhipu), switchable per site.
* Site learning: indexes your posts and pages so the bot answers from your content.
* Knowledge base: add Q&A or upload TXT/MD/PDF/DOCX files (large documents are split into parts automatically so they embed reliably), with priority over general answers.
* RAG hybrid search: combines keyword and semantic retrieval for grounded replies.
* Grounded Answers Only: an optional mode that makes the bot reply "I couldn't find that" instead of inventing an answer your content doesn't support.
* Web search: lets the bot pull current information when configured.
* MCP tools: exposes 5 Model Context Protocol tools, so agents such as Claude or ChatGPT can read and act on your site through conversation.
* Usage dashboard: tracks conversations, messages, and API cost.
* Usage Control: optional per-visitor daily caps so public traffic can't run up your API bill.
* Unanswered Questions: records what the bot could not answer, with one-click "Add to knowledge" and an AI-drafted FAQ page.
* AI-smell score: an optional read-only score that flags machine-sounding wording in Japanese replies (detection only — replies are never altered).
* Industry starter templates: one click applies a ready-made system prompt and preset questions for hotels, shops, clinics, professional services, or company sites.
* Gutenberg block: drop the chatbot into any page or post.
* Deep-link auto-send: a `?raplsaich_q=` URL opens the chatbot and sends the question automatically, so end-of-article "suggested questions" become one-click conversations.

= Turns visitor questions into content =

The bot doesn't just answer — it tells you what your site is missing. Questions it could not answer from your content are recorded on the dashboard, one click turns them into a knowledge entry, and another click drafts an FAQ page from the last 30 days of real visitor questions. Answer gaps become content, content improves the bot, and the same pages work for SEO and AI search (LLMO).

= Self-hosted and private =

Conversations and keys stay on your own WordPress install. You are billed by your AI provider directly, so cost is transparent and there is no markup. Visitors can delete their own chat history from the widget (optional), and retention is configurable — GDPR-friendly by design.

= Free and Pro =

**Free — not a trial.** The free version is the full product: chatbot, site learning, knowledge base, RAG hybrid search, Grounded Answers Only, MCP server (5 tools), a usage dashboard (conversations, messages, and cost), and per-visitor daily caps. Run it forever at no cost on a free-tier key.

**Pro — for sites that run the bot as a business tool:**

* **Lead capture automation** — collect contacts from conversations, with Google Sheets and Slack sync.
* **Analytics** — conversation, cost, and quality metrics at a glance.
* **Usage Control suite** — role-based limits, per-user monthly credits with auto-reset, and a usage-control dashboard to grant and track credits, so members and guests each get a fair share of your API budget.
* **Extra MCP tools** — product-search and analytics tools for AI agents, on top of the five in Free.
* **Context &amp; embedding controls** — reprocess embeddings and shape the context retrieved for each reply.
* **LINE integration (add-on)** — connect the chatbot to LINE messaging, the dominant messenger in Japan.

= Up and running in about 5 minutes (free, no credit card) =

You do not need an API key or any AI experience to start. On first setup the plugin shows a "Start for free" panel with two no-credit-card paths: an OpenRouter free key or a Google Gemini free-tier key.

1. Pick OpenRouter or Gemini.
2. Click through to get a free key (about a minute) and paste it in.
3. Press Test Connection — the key is validated and saved, a working free model is auto-selected, and the chatbot is switched on.

That's it. Each option states its data-handling trade-off up front (the Gemini free tier may use submitted content to improve Google's models), so you choose with eyes open. You can switch to your own OpenAI, Claude, or Gemini key at any time.

Learn more: [Plugin details](https://raplsworks.com/plugins/rapls-ai-chatbot/) | [Source code (GitHub)](https://github.com/rapls/rapls-ai-chatbot)

= See it in action =

Watch the full setup — from install to first reply — in about 5 minutes, no credit card required:

https://www.youtube.com/watch?v=KWEeYuZ0uEg

See it answer from your own content, and how that differs from plain ChatGPT:

https://www.youtube.com/watch?v=HgbYr6c_QlI

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

= My API key stops working every few days and I have to re-enter it =
The API key (and the Pro license) is encrypted with a key derived from your WordPress security salts (AUTH_KEY / AUTH_SALT). If something changes those salts, the stored key can no longer be read. WordPress uses salt values kept in the database whenever the wp-config.php constants are missing, still set to "put your unique phrase here", or reused across constants — and those database values can be regenerated without wp-config.php changing. Security plugins that rotate salts, a failing object cache, and restored database backups are the usual causes; you will normally be logged out of WordPress at the same time.

To make the stored keys independent of the salts, add a long random string to wp-config.php, above the "That's all, stop editing!" line:

`define('RAPLSAICH_ENCRYPTION_KEY', 'a-long-random-string-of-at-least-32-characters');`

Existing keys are re-encrypted with it automatically the next time you open wp-admin, and they then survive any salt change. Keep the value backed up: losing it means re-entering your API key and license once.

= Can I use it for free? =
Yes — the plugin is free, and the onboarding panel connects a no-credit-card OpenRouter or Google Gemini free-tier key in about a minute. Free tiers have rate limits, so for production traffic you may want your own provider key.

= Do I need an API key? =
Yes. Rapls runs on your own API key so your data and cost stay under your control. If you do not have one, the plugin guides you to a free OpenRouter key or a free Google Gemini key on first setup — both need no credit card.

= Will the bot make things up? =
Not if you don't want it to. Turn on Grounded Answers Only and the bot replies "I couldn't find that on this site" whenever your content has no relevant answer, instead of answering from the model's general knowledge.

= Can visitors run up my API bill? =
No. Usage Control caps daily usage per guest and per logged-in user on the server side, separate from rate limiting. Pro adds role-based limits and per-user credits.

= Does it work with AI agents (MCP)? =
Yes. The free version ships an MCP (Model Context Protocol) server with 5 tools, so AI agents such as Claude or ChatGPT can search your posts, read knowledge entries, and act on your site through conversation. Pro adds product-search and analytics tools on top.

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

1. Chat widget — the front-end chatbot answering a visitor from your site content.
2. Start for free — the onboarding panel connects a no-credit-card OpenRouter or Google Gemini free key in about a minute, with each option's data-handling trade-off shown up front.
3. Dashboard — setup checklist, conversation/index/knowledge stats, System Health panel (API key, cron, REST reachability), and 30-day token usage with an estimated cost.
4. Knowledge base — add custom text/Q&A or import TXT, CSV, MD, PDF, or DOCX files.
5. Conversations — review, filter, archive, and export visitor conversations with lead and status details.
6. Analytics — conversation, message, cost, and quality metrics (Pro).
7. Site Learning — auto-crawl posts, pages, and custom post types so the bot answers from your own content.
8. AI Settings — choose your provider (OpenAI, Anthropic Claude, Google Gemini, or OpenRouter), enter your API key, pick a model, and enable Vector Embedding (RAG).
9. Unanswered Questions — questions the bot could not answer from your content, with one-click "Add to knowledge" and an AI-generated FAQ draft you can save as a draft page.
10. Cost controls — Monthly Cost Guard with budget warnings, per-visitor usage limits, and model fallback so API bills never run away.

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

= 5. OpenAI-compatible endpoints (e.g. Alibaba Qwen/DashScope, DeepSeek, Zhipu): AI Provider =

Used only if you select the "OpenAI-compatible" provider and enter a base URL. User messages, and optionally your site content (including indexed content for RAG embeddings), are sent to the endpoint you configure to generate responses or embeddings. Because the endpoint is chosen by you, please review the terms and privacy policy of the specific provider you connect to. For example, Alibaba Cloud Model Studio (DashScope), which powers Tongyi Qwen:

* Service URL: [https://www.alibabacloud.com/help/en/model-studio/](https://www.alibabacloud.com/help/en/model-studio/)
* Terms of Use: [https://www.alibabacloud.com/help/en/legal/](https://www.alibabacloud.com/help/en/legal/)
* Privacy Policy: [https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-privacy-policy/](https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-privacy-policy/)

= 6. Google reCAPTCHA v3 (Optional) =

Used only if you enable reCAPTCHA in the plugin settings for spam protection. The visitor's IP address and interaction data are sent to Google for verification.

* Service URL: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Terms of Use: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= 7. LINE Messaging API (Pro Add-on, Optional) =

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

= 1.21.0 =
* Fixed: Gemini 2.0 Flash, the default Gemini model, was shut down by Google on June 1, 2026, and Gemini 2.0 Flash-Lite and Gemini 3 Pro Preview have been shut down as well. A site still set to one of them now sends its chats to Google's replacement (Gemini 3.6 Flash, Gemini 3.1 Flash-Lite and Gemini 3.1 Pro Preview), and wp-admin says so until a model is chosen again. New installs use Gemini 3.5 Flash-Lite. The saved setting is not changed for you.
* Fixed: OpenAI shut down o3-mini on October 1, 2026, and shuts down GPT-4.1 nano and o4-mini on October 23 and GPT-5, GPT-5 mini, GPT-5 nano, GPT-5 Pro and o3 on December 11. From each date, a site still set to one of these models sends its chats to OpenAI's replacement (GPT-5.6 Sol, Terra or Luna). Claude Sonnet 4.5, which Anthropic retires on November 30, 2026, moves to Claude Sonnet 5.5 the same way.
* Added: a warning, on the settings screen and on the plugin's own admin screens, when the selected model has an announced retirement date. It says when, and which model will answer afterwards.
* Added: GPT-6 Astra, GPT-6.1 Sol, GPT-6 Luna and GPT-5.6 Sol, Terra and Luna; Claude Sonnet 5.5 (now the recommended Sonnet) and Claude Opus 5.5; Gemini 3.8 Flash (now recommended), 3.7 Flash, 3.6 Flash, 3.5 Flash, 3.5 Flash-Lite and 3.1 Flash-Lite. Models that are shut down or scheduled to be are removed from the lists; a site still using one keeps it selected, and a save no longer switches it to the first model in the list.
* Changed: retired Claude Sonnet and Opus models now go to Claude Sonnet 5.5 and Claude Opus 5.5 instead of the 4.6 models.
* Changed: GPT-6 and Gemini 3 models are sent without temperature (GPT-6 rejects it, and Google advises against lowering it on Gemini 3) and get more room for thinking, so replies are not cut off. Claude models that think by default, such as Sonnet 5.5 and Opus 5.5, are asked for low effort.
* Fixed: with web search on and nothing found in the site's content, Claude Opus 5.5 and Claude Fable 5.1 returned an error, because the plugin forced the search tool, which those models do not accept. They are now told to search in the prompt instead.
* Fixed: the OpenAI-compatible list offered deepseek-chat, which DeepSeek discontinued on July 24, 2026, and glm-4-plus, which no longer answers; the OpenRouter list offered deepseek/deepseek-chat-v3, which does not exist. They are replaced by deepseek-flash, glm-5.3, qwen3.8-max and deepseek/deepseek-chat, and the OpenRouter Claude entry is now Claude Sonnet 4.6. The model field's help text no longer names DeepSeek or GLM models, which change too often.
* Fixed: usage costs. GPT-4.1 models were counted at the GPT-4 price, up to 150 times too high, which could stop the chat early under Pro's monthly budget limit, and most current OpenAI, Claude and Gemini models had no price, so GPT-5.2 Pro, for example, was counted at about 1/280 of its cost. Prices now follow each provider's pricing page.
* Changed: Model Fallback uses Gemini 3.1 Flash-Lite, and the free-tier setup picks a Gemini 3 Flash-Lite model, because Google now serves Gemini 2.5 only to projects that already used it.

= 1.20.5 =
* Fixed: Claude Sonnet 4 and Claude Opus 4.1 were retired by Anthropic (June 15 and August 5, 2026) and stopped answering. Visitors saw "The AI model is currently unavailable. Please contact the site administrator.", and nothing in wp-admin said why. A site still set to a retired Claude model now sends its chats to the successor (Sonnet 4 to Sonnet 4.6, Opus 4 and 4.1 to Opus 4.6, Claude 3 Haiku models to Haiku 4.5), and wp-admin shows a notice until a model is chosen again. The saved setting is not changed for you. The same applies to a model typed in for a Pro bot.
* Added: Claude Sonnet 4.6, now the recommended Sonnet model. Claude Sonnet 4 and Claude Opus 4.1 are removed from the model list.
* Fixed: when the saved model was no longer in the list, the settings screen showed the first model instead, so saving the page for any other reason quietly switched the site to Claude Opus 4.6. It now shows the model that is actually answering.
* Fixed: usage costs for Claude Haiku 4.5, the default model, were counted at three times the real price, and Claude Opus 4.5 and 4.6 were also priced wrongly, so the Pro monthly budget limit could stop the chat early. Prices now follow Anthropic's pricing page.
* Changed: temperature is sent only to Claude models that accept it. Claude Opus 4.7 and later and Claude Sonnet 5 reject it, which matters for a model typed in for a Pro bot.

= 1.20.4 =
* Fixed: the Site Learning screen said it learns custom fields. Since 1.19.3 custom fields are not indexed unless a site names them with the raplsaich_crawl_indexed_meta_keys filter, so the description now lists posts, pages and custom post types only. The Japanese translation of the same sentence, which repeated itself, is corrected too.

= 1.20.3 =
* Fixed: an English question could pull in an unrelated page because an everyday word in it was enough for the keyword index to match. "I need a stairlift" searched for "need" as well, and "need" appears on most pages, so whichever page used it could come back as a result — and as a reference card. The English stopword list had 20 words and kept pronouns, prepositions, auxiliaries and opening verbs; it now covers them. Words that carry meaning on a real site (help, support, cost, price, service and the like) are deliberately still searched for. Japanese questions are unaffected.
* Fixed: when every word of an English question was a stopword ("how do I know?"), the remaining text was glued together into one keyword ("howdoIknow") that matches nothing. The plugin now searches for nothing in that case and leaves the answer to vector search. Japanese, which is written without spaces, still joins the remaining text as before. Thanks to @slafever for the report that led to both.

= 1.20.2 =
* Fixed: with the Pro response cache and Pro message encryption both on, a repeated question could be answered with a long encrypted string (starting "encg:") instead of the reply. Every other place that reads stored messages decrypts them first; the cache lookup returned the stored row as-is. It now decrypts too, and an entry that cannot be decrypted is treated as a cache miss so a fresh answer is generated — encrypted text is never shown in the chat.
* Fixed: when the reCAPTCHA secret key could no longer be decrypted (for example after the WordPress security salts changed), every chat message was refused and visitors only saw "This feature is currently unavailable." with no warning in wp-admin, because the decryption notice only checked the AI provider's API key. A separate notice now tells administrators to re-enter the reCAPTCHA secret key.
* Improved: the decryption notice no longer claims a key "was most likely encrypted on another site" when the salts are unchanged. The recorded fingerprint belongs to whichever key was saved last, so it now lists every way a value can be left under an old key. Thanks to Sander Rombout for the detailed report.

= 1.20.1 =
* Fixed: a request rejected as automated (added in 1.19.7) showed the vague "This feature is currently unavailable." message, because the `bot_request` error code had no entry in the message map. It now says the request looked automated and who to contact, which matters for the rare visitor whose browser is misidentified.

= 1.20.0 =
* Added: `RAPLSAICH_ENCRYPTION_KEY` — an optional wp-config.php constant that decouples the stored API key and Pro license from the WordPress security salts. Sites whose salts are rotated (by a security plugin, a failing object cache, or a restored database) lost both every few days and had to re-enter them. Define the constant and existing keys are re-encrypted with it on the next wp-admin request; they then survive any salt change. Nothing changes for sites that do not define it. See the FAQ for details.
* Improved: the "API key decryption failed" notice now names the likely cause. It compares the current salts against a fingerprint recorded when the key was saved, so it can tell "your salts changed on <date>" apart from "your salts are unchanged, so this key was encrypted on another site" instead of listing both possibilities. Thanks to Sander Rombout for the detailed report.

= 1.19.7 =
* Fixed: search-engine crawlers that run JavaScript (for example Baiduspider-render) could create fake conversations. When a crawler followed a deep link such as `?raplsaich_q=…`, the question was sent automatically, so every crawl showed up under Conversations as a new visitor and triggered a real AI request. Each crawl gets a new session, so per-visitor limits did not stop it. Known crawlers and automated browsers no longer auto-send deep-link questions, and the server refuses chat requests from crawler User-Agents before the AI is called or anything is saved. Developers can adjust detection with the new raplsaich_is_bot_request filter. Thanks to xyp for the detailed report.

= 1.19.6 =
* Fixed: with the optional "iOS keyboard fix" enabled, opening the chat on desktop locked the background page at the top — the page could not be scrolled until the chat was closed. The scroll-cancel that keeps the mobile full-screen chat pinned was running on desktop too, where the widget is only a small floating panel. It is now limited to the mobile view, matching the body lock it belongs to. No effect unless "iOS keyboard fix" is turned on. Thanks to @slafever for the detailed report.

= 1.19.5 =
* Fixed: a reference card below an answer could point to an unrelated page when the answer was actually grounded in a different page found by vector search. Reference cards and the sources list only showed pages that keyword search matched, so a page found only by semantic (vector) search — often the very page the answer came from — could never appear, and a weaker keyword hit was shown in its place. Vector matches that clear the grounding score are now eligible for reference cards and sources, so the card reflects the page the answer is based on. Only affects sites with Vector Search enabled. Thanks to @slafever for the detailed report.

= 1.19.4 =
* Fixed: System Health showed "Last crawl: Never run yet" even though Site Learning was crawling on schedule and the content index was growing. The scheduled crawler runs incrementally — one batch per run, resuming across runs — but the "Last crawl" time was only recorded when an entire sweep finished (every post type, every batch). On sites where a sweep spans several runs (many post types, or a type with more than 100 posts) the field could sit at "Never run yet" for days while crawls were in fact running. Each crawl run now records its own timestamp, so the status reflects the most recent run. Thanks to @slafever for the detailed report.

= 1.19.3 =
* Fixed: Site Learning (the site crawler) was adding third-party plugin metadata to the indexed content of a page, and it could surface in chatbot answers and reference cards. It walked every public custom field on a post and appended it under "Additional Info", filtering out only keys that start with an underscore. Plugins such as Rank Math SEO and page-builder themes store their data under plain keys (for example rank_math_focus_keyword or a theme's section layout flags), so that data slipped through and ended up in the knowledge base even though it never appeared in the page's visible content. The crawler no longer indexes arbitrary post meta; it indexes the title, excerpt, taxonomy terms, and page body. If you deliberately keep content in a custom field, you can opt specific keys back in with the new raplsaich_crawl_indexed_meta_keys filter. Run Site Learning again after updating to overwrite already-indexed pages. Thanks to @slafever for the detailed report.

= 1.19.2 =
* Fixed: In the cross-site script embed, the close (×) button did nothing on other domains, so visitors could not close the chat. The chat window notified the host page to close via a browser message addressed to the wrong origin (this plugin's own site instead of the page it was embedded on), so the browser dropped it. The embed now sends the message to the host page's own origin, and the close button works on any site. Embedding the chat on its own site was unaffected.

= 1.19.1 =
* Fixed: In the cross-site script embed, the Google reCAPTCHA v3 badge (shown when reCAPTCHA is enabled) could sit on top of the send button. Google pins the badge to the bottom-right corner, and the embedded chat window is narrow, so the badge landed right where the send button is. The badge is now lifted above the input bar so it no longer covers the send button. No change if you do not use reCAPTCHA.

= 1.19.0 =
* Added: Large uploaded documents are now split into parts automatically. Until now a document had to fit the embedding model's single-request token limit or it would not embed; now the Knowledge Base splits a big TXT, MD, PDF, or DOCX into smaller parts on upload, and each part embeds on its own. You no longer need to cut a file up by hand before uploading. The Knowledge Base list shows the document as one item with a "parts embedded" badge, and deleting it removes every part. This also improves answers: the bot retrieves the most relevant passage of a long document instead of one blurred embedding of the whole thing. Existing entries are untouched — re-upload a document to split it.

= 1.18.1 =
* Fixed: The bot avatar beside a message could occasionally render at full size (very large) on sites where a caching or CSS-optimization plugin combined and minified stylesheets. The avatar wrapper is now sized directly, so the icon stays at its intended size even if that one rule is dropped. If you see it oversized after updating, clear your caching plugin's cache once.

= 1.18.0 =
* **Fixed: embedding could loop indefinitely, sending requests to your provider without end.** The client kept asking for more as long as documents remained unembedded, so a single document that could never embed — one over the model's token limit, or a PDF with no extractable text — put the loop in a state it could not leave. On a metered API key that meant requests, and charges, with nothing to show for them. Permanent failures are now excluded from the next pass and the loop ends when it stops making progress. If you have uploaded a large document and left the Knowledge Base screen open, update.
* Fixed: Knowledge Base entries that failed to embed used to index silently — you saw 0 chunks with no reason. Each entry that cannot be embedded now shows why on the Knowledge Base list (for example, "Too large to embed — split this entry into smaller documents", or an API-key/rate-limit note). Thanks to @publiastel for the clear report.
* Improved: When you embed several documents at once and one is too large for the embedding model's single-request limit, the oversized document no longer sinks the whole batch. The rest embed normally, and only the oversized one is flagged. Split it into smaller entries and it will embed on the next run.

= 1.17.0 =
* Added: Optional HTML in the "Limit reached" message (Security, Usage Control). Turn on "Allow a safe HTML subset" to use links and basic emphasis in that message — for example, point a blocked visitor to a login or upgrade page: Please <a href="/login">log in</a> or go <a href="/vip">VIP</a>. Only a small allowlist is permitted (<a href>, <br>, <strong>, <em>); everything else is stripped on save, and the widget scrubs the message again before rendering. Off by default, so existing setups are unchanged.

= 1.16.2 =
* Added: Badge label. You can now show a short line of text next to the launcher icon — for example "Contact us here" — so first-time visitors understand what the button does instead of seeing an icon alone. Set it under Settings, Badge Label; leave it empty to keep the icon-only circle. The label is hidden on phones to save space.

= 1.16.1 =
* Fixed: With the OpenAI-compatible provider selected (e.g. Alibaba Qwen/DashScope), the chat widget returned "AI API key is not configured" even though the key was saved and Test Connection succeeded. The pre-send key check did not recognize the new provider and looked for an OpenAI key instead of the OpenAI-compatible one. Chat now reads the correct key. No settings changes are needed.

= 1.16.0 =
* Added: OpenAI-compatible provider. Connect any service that speaks the OpenAI Chat Completions API — including Chinese providers such as Alibaba Tongyi Qwen (DashScope), DeepSeek, and Zhipu GLM — by entering a base URL, a model name, and an API key. Vector embeddings (RAG) are supported as well, so sites that cannot reach OpenAI or Gemini (for example inside mainland China) can run both chat and RAG on a domestic provider such as Qwen. When the embedding provider is set to Auto and chat runs on the OpenAI-compatible provider, embeddings use that same vendor. Changing the embedding provider now warns you to clear and regenerate embeddings.
* Added: Deep-link auto-send. A URL such as `?raplsaich_q=your+question` (a plain `?q=` also works) opens the chatbot and sends that question automatically, with no typing or copy-paste — ideal for turning end-of-article "suggested questions" into one-click conversations. The question is shown as a normal visitor message, and the parameter is removed from the URL after sending so a refresh does not resend it.

Older versions (1.15.4 and earlier) are listed in [changelog.txt](https://plugins.svn.wordpress.org/rapls-ai-chatbot/trunk/changelog.txt).

== Upgrade Notice ==

= 1.21.0 =
Gemini 2.0 Flash, the former default, was shut down by Google; sites still using it resume on Gemini 3.6 Flash. Adds GPT-6, Claude Sonnet 5.5 and Opus 5.5 and Gemini 3.x, and warns before a selected model is retired.

= 1.20.5 =
If your site uses Claude Sonnet 4 or Claude Opus 4.1, which Anthropic has retired, chats resume on their successors after this update. Also corrects Claude usage costs, which were overcounted for Haiku 4.5.

= 1.20.4 =
The Site Learning screen no longer says it learns custom fields, which it has not done since 1.19.3.

= 1.20.3 =
Stops an unrelated page being matched (and shown as a reference card) because of an everyday English word such as "need" in the question.

= 1.20.2 =
Stops encrypted text appearing as a chat reply when Pro's response cache and message encryption are both on, and warns when the reCAPTCHA secret key can no longer be decrypted.

= 1.20.1 =
Gives a clear message to a visitor whose browser is mistaken for a crawler, instead of the generic "currently unavailable" text.

= 1.20.0 =
Adds an optional wp-config.php key so your API key and Pro license survive WordPress salt changes, and explains the cause when decryption does fail.

= 1.19.7 =
Stops search-engine crawlers from creating fake conversations (and AI requests) by following deep-link question URLs.

= 1.19.6 =
Fixes the background page being locked at the top on desktop when the chat is open, on sites with the optional "iOS keyboard fix" enabled.

= 1.19.5 =
Fixes reference cards showing an unrelated page when the answer was grounded by vector search. Only affects sites with Vector Search enabled.

= 1.19.4 =
Fixes System Health showing "Last crawl: Never run yet" while Site Learning was actually crawling. Each crawl run now records its own timestamp.

= 1.19.3 =
Stops Site Learning from indexing third-party plugin metadata (e.g. Rank Math SEO fields) that could surface in chatbot answers. Re-run Site Learning after updating to clean already-indexed pages.

= 1.19.2 =
Fixes the close (×) button not working when the chat is embedded on another site via the script. Recommended if you use the cross-site embed.

= 1.19.1 =
Minor fix for the cross-site embed: the Google reCAPTCHA badge no longer covers the send button. Only relevant if you use reCAPTCHA and embed the chat on another site via the script.

= 1.19.0 =
Large uploaded documents (TXT, MD, PDF, DOCX) are now split into parts automatically so each embeds on its own — no more cutting big files up by hand, and better answers from long documents. Existing entries are unchanged.

= 1.18.1 =
Recommended bug-fix release. Fixes embedding that could keep sending requests to your AI provider without end — and, on a metered API key, keep incurring charges — when a document could not be embedded (too large, or a PDF with no extractable text). Also stops an oversized document from blocking the rest of a batch, shows why an entry failed to embed, and keeps the message avatar at its intended size. If you have uploaded large documents, update.

= 1.18.0 =
Fixes a bug where embedding could keep sending requests to your AI provider without end — and, on a metered API key, keep incurring charges — when a document could not be embedded (too large, or a PDF with no extractable text). If you have uploaded large documents, update.

= 1.17.0 =
The "Limit reached" usage message can now include a safe subset of HTML (links and basic emphasis) when you enable it, so you can send blocked visitors to a login or upgrade page. Off by default; existing setups are unchanged.

= 1.16.2 =
Adds an optional text label beside the launcher icon (for example "Contact us here") so visitors know what the button does. Leave it empty to keep the icon-only button. New option only; existing setups are unchanged.

= 1.16.1 =
Fixes the OpenAI-compatible provider (Qwen/DashScope, DeepSeek, Zhipu): chat no longer reports "AI API key is not configured" when the key is set. Recommended for anyone using that provider.

= 1.16.0 =
Adds an OpenAI-compatible provider (use Alibaba Qwen/DashScope, DeepSeek, or Zhipu for both chat and RAG) and deep-link auto-send (a ?raplsaich_q= URL opens the chat and sends the question). New features only; existing setups are unchanged.

= 1.15.4 =
Stops WordPress repeatedly offering an update you have already installed. Also a shorter plugin name. No behaviour changes.

= 1.15.3 =
Fixes a stray "Choose file" upload control that could appear in the public chat widget on some themes. Recommended for all users.

= 1.15.2 =
Fixes a JavaScript error that could appear as a chat message after the bot replied on sites running the Pro add-on. Recommended for all Pro users.

= 1.15.0 =
Failed AI calls are now classified and visible to admins — an error badge in the conversation view and a 24-hour provider-error summary in System Health. Recommended for all users.

= 1.14.1 =
Chat errors now show their real cause (API key, model, provider, or quota) instead of one generic message, and the custom quota message setting works again. Recommended for all users.

= 1.14.0 =
Adds an "Add to knowledge" button on unanswered questions, a "Create draft page" button for generated FAQs, and a dashboard System Health panel. Recommended for all users.

= 1.13.0 =
Adds visitor-initiated chat history deletion (privacy), an Unanswered Questions report, a one-click FAQ draft generator, optional GA4 events, and WP-CLI commands. All new data features are off by default. Recommended for all users.

= 1.12.0 =
Adds a setup checklist, a demo preview, industry starter templates, page context, a monthly Cost Guard, model fallback, and an optional weekly summary email. Recommended for all users.

= 1.11.1 =
Blocked messages now say which limit was reached (quota, credits, token, daily, or rate limit). Recommended for sites using Usage Control.

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
