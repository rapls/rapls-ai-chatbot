=== Rapls AI Chatbot ===

Contributors: raplsworks
Tags: chatbot, ai, openai, claude, gemini
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.3.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot for WordPress. Supports OpenAI, Claude, and Gemini with site learning and custom knowledge base.



== Description ==

Rapls AI Chatbot is a powerful AI chatbot plugin that integrates seamlessly with your WordPress site. It supports multiple AI providers including OpenAI (GPT-4o, GPT-4o-mini), Anthropic Claude, and Google Gemini, allowing you to choose the best AI for your needs.

The free version is fully functional — no Pro add-on required for core AI chat features, site learning, knowledge base, and 6 built-in themes.

= Key Features =

* **Multiple AI Providers** - Support for OpenAI, Anthropic Claude, and Google Gemini
* **Site Learning** - Automatically learn from your website content (posts, pages, custom post types)
* **Custom Knowledge Base** - Add custom Q&A pairs and training data
* **Conversation History** - Save and review all chat conversations
* **Usage Statistics** - Track token usage and estimated API costs with visual charts
* **Feedback & Regeneration** - Users can rate responses and request regeneration
* **Fully Customizable** - Customize appearance, behavior, and AI responses
* **6 Built-in Themes** - Default, Simple, Classic, Light, Minimal, Flat
* **Multilingual** - Japanese translation included, easily translatable

= Supported AI Models =

**OpenAI:**
* GPT-4o (High Performance, Multimodal)
* GPT-4o-mini (Cost Efficient)
* GPT-4 Turbo
* o1, o1-mini, o3-mini (Reasoning Models)

**Anthropic Claude:**
* Claude Opus 4 (Highest Performance)
* Claude Sonnet 4 (High Performance, Balanced)
* Claude 3.5 Sonnet
* Claude 3.5 Haiku (Fast, Low Cost)

**Google Gemini:**
* Gemini 2.0 Flash (Latest)
* Gemini 1.5 Pro
* Gemini 1.5 Flash

= Site Learning =

The plugin can automatically crawl and index your website content, allowing the chatbot to answer questions based on your actual content. This includes:

* Posts and Pages
* Custom Post Types
* WooCommerce Products
* Any public content

= Custom Knowledge Base =

Create a custom knowledge base with:

* Q&A format data for FAQ-style responses
* Free-form content for general information
* Priority levels to control response relevance
* Import/Export functionality

= Usage Statistics & Cost Tracking =

Monitor your AI usage with:

* Daily token usage charts
* Model-by-model breakdown
* Estimated costs in USD and JPY
* Input/Output token tracking
* One-click statistics reset

= Free vs Pro =

The free version is fully functional. Upgrade with the optional Pro add-on to unlock advanced features.

* **Free** — Great for personal blogs and small sites
* **Pro** — Built for business sites, customer support, and lead generation

**AI Chat Core**

* ✅ Free: OpenAI, Claude, Gemini — all providers supported
* ✅ Free: Custom system prompt with improved accuracy defaults
* ✅ Free: Customizable feature prompts (regeneration, feedback, summary)
* ✅ Free: Knowledge base (up to 20 entries)
* ✅ Free: Monthly 500 AI responses (FAQ fallback after limit)
* ⭐ Pro: Unlimited AI responses
* ⭐ Pro: Unlimited knowledge base entries

**Site Learning**

* ✅ Free: Manual content indexing
* ⭐ Pro: Scheduled automatic crawling
* ⭐ Pro: Differential crawl (changed pages only)

**Appearance**

* ✅ Free: 6 themes (Default, Simple, Classic, Light, Minimal, Flat)
* ⭐ Pro: 10 additional themes (Modern, Gradient, Dark, Glass, Rounded, Ocean, Sunset, Forest, Neon, Elegant)
* ⭐ Pro: Dark mode
* ⭐ Pro: Custom badge icon
* ⭐ Pro: White label

**Analytics & Insights**

* ✅ Free: Token usage & cost tracking
* ⭐ Pro: Conversation analytics dashboard
* ⭐ Pro: FAQ ranking & auto-generation
* ⭐ Pro: Satisfaction score tracking
* ⭐ Pro: Unresolved questions detection
* ⭐ Pro: Real-time conversation monitor
* ⭐ Pro: Knowledge gap detection
* ⭐ Pro: Monthly email reports
* ⭐ Pro: Print/PDF analytics report
* ⭐ Pro: Conversion tracking (goal URL patterns)

**Lead Capture**

* ⭐ Pro: Customizable lead forms
* ⭐ Pro: Custom fields
* ⭐ Pro: CSV/JSON export (conversations, leads, knowledge)
* ⭐ Pro: Webhook integration (HMAC-signed)

**AI Enhancements**

* ✅ Free: Feedback (thumbs up/down)
* ✅ Free: Response regeneration
* ⭐ Pro: Multimodal (image upload)
* ⭐ Pro: Sentiment analysis (customizable prompts)
* ⭐ Pro: Cross-session context memory (customizable prompts)
* ⭐ Pro: Related questions & autocomplete
* ⭐ Pro: Conversation summary
* ⭐ Pro: AI Prompts settings tab (customize all AI prompts)

**Budget & Operations**

* ⭐ Pro: API cost alerts (email notification)
* ⭐ Pro: Budget limits (auto-block when exceeded)
* ⭐ Pro: Business hours scheduling
* ⭐ Pro: Holiday calendar
* ⭐ Pro: Banned words filter
* ⭐ Pro: IP blocking
* ⭐ Pro: Enhanced rate limiting (per-minute burst + per-hour sustained)

**Performance & Security**

* ⭐ Pro: Response caching (reduce API costs 30-50%)
* ⭐ Pro: Server-side PDF analytics export (Dompdf)
* ⭐ Pro: Audit logs (admin action tracking, CSV export)
* ⭐ Pro: Offline messages (business hours form)

**Advanced**

* ⭐ Pro: Conversation tags & notes
* ⭐ Pro: Human handoff / Operator mode
* ⭐ Pro: Post-chat surveys
* ⭐ Pro: Multiple chatbots (per-page)
* ⭐ Pro: Prompt templates
* ⭐ Pro: Answer templates (operator quick-insert)
* ⭐ Pro: Dynamic variables ({site_name}, {current_date}, etc.)
* ⭐ Pro: Settings import/export

Learn more at [raplsworks.com](https://raplsworks.com/rapls-ai-chatbot-pro)



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



== Screenshots ==

1. Dashboard - Overview of conversations, messages, and usage statistics with cost tracking
2. Settings - Configure AI provider, model selection, and chat behavior
3. Site Learning - Automatic content indexing and manual learning controls
4. Knowledge Base - Custom Q&A management with priority levels
5. Conversation History - View and manage all chat conversations
6. Chatbot Widget - Clean, modern chat interface on your website
7. Analytics Dashboard (Pro) - Conversation insights, satisfaction tracking, and FAQ analysis



== Frequently Asked Questions ==

= Which AI provider should I choose? =

Each provider has different strengths:
* **OpenAI GPT-4o-mini** - Best balance of cost and performance for most use cases
* **Claude Sonnet 4** - Excellent for nuanced, helpful responses
* **Gemini 2.0 Flash** - Fast and cost-effective, good for high-volume sites

= How much does it cost to use? =

The plugin itself is free. You pay for AI API usage directly to your chosen provider. Typical costs:
* GPT-4o-mini: ~$0.15/1M input tokens, ~$0.60/1M output tokens
* Claude 3.5 Haiku: ~$0.80/1M input tokens, ~$4.00/1M output tokens
* Gemini 1.5 Flash: ~$0.075/1M input tokens, ~$0.30/1M output tokens

= Can I use multiple AI providers? =

You can configure multiple API keys, but only one provider is active at a time. You can switch between providers in the settings.

= How does Site Learning work? =

The plugin crawls your published content and creates a searchable index. When users ask questions, relevant content is automatically included in the AI context for accurate responses.

= Can I customize the chatbot appearance? =

Yes. You can customize:
* Bot name and avatar
* Primary color and theme (6 built-in themes)
* Welcome message
* Badge position and margins
* Mobile visibility
* Excluded pages

= Is conversation history saved? =

Yes, by default. You can disable this in settings. Saved conversations can be viewed and deleted from the admin panel.

= Does it work with page builders? =

Yes, the chatbot widget works with any theme and page builder including Elementor, Divi, Beaver Builder, and Gutenberg.

= Can I use custom system prompts? =

Yes. Configure your own system prompt to define the AI's personality, behavior, and response style.

= What happens if I exceed my API quota? =

The plugin displays a customizable error message when quota limits are reached.

= What is the Pro add-on? =

The Pro add-on is a separate plugin that extends this free version with advanced features like analytics, lead capture, business hours, and more. The free version works fully on its own.



== External Services ==

This plugin connects to external third-party services to provide AI chatbot functionality. By using this plugin, you agree to the terms and privacy policies of these services.

= AI Providers =

The plugin sends user messages and optionally site content to AI providers for generating responses:

**OpenAI (GPT models)**
* Service URL: https://api.openai.com/
* Terms of Use: https://openai.com/terms/
* Privacy Policy: https://openai.com/privacy/

**Anthropic (Claude models)**
* Service URL: https://api.anthropic.com/
* Terms of Use: https://www.anthropic.com/terms
* Privacy Policy: https://www.anthropic.com/privacy

**Google (Gemini models)**
* Service URL: https://generativelanguage.googleapis.com/
* Terms of Use: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

= Google reCAPTCHA (Optional) =

If enabled, the plugin uses Google reCAPTCHA v3 for spam protection:

* Service URL: https://www.google.com/recaptcha/
* Terms of Use: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

= Data Transmitted =

* **User messages**: Chat messages entered by visitors
* **Site content** (if Site Learning enabled): Excerpts from your published posts/pages
* **Knowledge base** (if configured): Custom Q&A entries you create
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

* `wpaic_system_prompt` - Modify the system prompt sent to AI
* `wpaic_context` - Modify the context from site learning
* `wpaic_ai_response` - Filter the AI response before display
* `wpaic_chatbot_enabled` - Control chatbot visibility programmatically

= Example: Custom System Prompt =

`
add_filter( 'wpaic_system_prompt', function( $prompt, $settings ) {
    return $prompt . "\n\nAlways end responses with a friendly emoji.";
}, 10, 2 );
`

= Example: Conditionally Hide Chatbot =

`
add_filter( 'wpaic_chatbot_enabled', function( $enabled ) {
    // Hide on checkout page
    if ( is_page( 'checkout' ) ) {
        return false;
    }
    return $enabled;
} );
`

= REST API Endpoints =

The plugin registers REST API endpoints under the `wp-ai-chatbot/v1` namespace:

**Free:**

* `GET /wp-ai-chatbot/v1/session` - Get or create a chat session
* `POST /wp-ai-chatbot/v1/chat` - Send a message and receive AI response
* `GET /wp-ai-chatbot/v1/history/{session_id}` - Get conversation history
* `POST /wp-ai-chatbot/v1/feedback` - Rate a response (thumbs up/down)
* `POST /wp-ai-chatbot/v1/regenerate` - Regenerate AI response
* `GET /wp-ai-chatbot/v1/message-limit` - Check message limits

**Pro add-on (registered only when Pro is active):**

* `GET /wp-ai-chatbot/v1/summary/{session_id}` - Conversation summary
* `POST /wp-ai-chatbot/v1/suggestions` - Related question suggestions
* `POST /wp-ai-chatbot/v1/autocomplete` - Input autocomplete
* `POST /wp-ai-chatbot/v1/offline-message` - Submit offline message
* `POST /wp-ai-chatbot/v1/conversion` - Track conversion event
* `GET /wp-ai-chatbot/v1/templates` - Get answer templates

= Database Tables =

The plugin creates the following database tables:

* `{prefix}_aichat_conversations` - Chat sessions
* `{prefix}_aichat_messages` - Individual messages with token tracking
* `{prefix}_aichat_index` - Site learning content index
* `{prefix}_aichat_knowledge` - Custom knowledge base entries
* `{prefix}_aichat_leads` - Lead capture data
* `{prefix}_aichat_user_context` - Cross-session context memory (Pro)
* `{prefix}_aichat_audit_log` - Administrative action audit trail (Pro)

= Uninstallation =

When uninstalled, the plugin removes all database tables, options, and transients. Conversation history will be permanently deleted.



== Changelog ==

= 1.3.1 =
* Added: Enhanced rate limiting (Pro) — configurable two-tier throttling with per-minute burst protection and per-hour sustained limits, proxy-aware IP detection, custom rate limit messages
* Added: Server-side PDF export (Pro) — download analytics reports as PDF files using Dompdf, one-click download from the analytics page
* Added: Rate limiting stub methods in Free plugin for Pro compatibility
* Improved: Rate limit error messages are now customizable (returns specific message instead of generic text)
* Improved: Diagnostic options renamed to `wpaic_diag_*` namespace (old `wpaic_hash_unexpected_count` and `wpaic_diag_upgrade_order_issue` keys auto-migrated on upgrade)
* Improved: Frontend debug minimum capability is now filterable via `wpaic_frontend_debug_min_cap`

= 1.3.0 =
* Added: Response caching (Pro) — SHA-256 hash-based cache reduces API costs by 30-50%, with configurable TTL and cache statistics dashboard
* Added: Audit logs (Pro) — Track admin actions (settings changes, knowledge edits, exports), filterable log viewer with CSV export and retention policy
* Added: Conversion tracking (Pro) — Monitor chat-to-conversion rates with URL pattern goals, integrated into analytics dashboard
* Added: Offline messages (Pro) — Display contact form outside business hours, with email notification and webhook support
* Added: Answer templates (Pro) — Knowledge base type system with operator quick-insert, supports dynamic variables
* Added: New REST API endpoints — /offline-message, /conversion, /templates (Pro)
* Added: Cache statistics widget on dashboard (Pro)
* Added: Conversion rate card in analytics (Pro)
* Improved: Knowledge base supports 'qa' and 'template' entry types
* Improved: Messages table supports cache_hash and cache_hit columns
* Improved: Conversations table supports converted_at and conversion_goal columns

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
* Added: `wpaic_system_prompt` filter — developers can now modify the system prompt programmatically
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
* Added: Session cookie (wpaic_session_id) set on session creation for reliable history access across IP changes

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

= 1.3.1 =
Enhanced rate limiting, server-side PDF export, and diagnostic option namespace migration (`wpaic_diag_*`). Recommended update for all users.

= 1.3.0 =
Major feature release: Response caching, audit logs, conversion tracking, offline messages, and answer templates. Reduces API costs and adds business-critical Pro features. Recommended update for all users.

= 1.2.23 =
Sortable admin tables, knowledge base draft workflow, enhanced content extraction, and session management improvements. Recommended update for all users.

= 1.2.22 =
Improved AI chat accuracy with better default prompts. All feature prompts now customizable. Added wpaic_system_prompt filter. Recommended update for all users.

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
