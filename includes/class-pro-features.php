<?php
/**
 * Pro features management class (Free version - stubs only)
 *
 * This class provides stubs for Pro features compatibility.
 * When the Pro plugin is installed, it overrides these methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guard: if the real Pro implementation was loaded first, skip this stub.
if (class_exists('WPAIC_Pro_Features', false)) {
    return;
}

class WPAIC_Pro_Features {

    /**
     * Feature constants (used by Pro plugin via is_feature_available)
     */
    const FEATURE_MESSAGE_LIMIT = 'message_limit';
    const FEATURE_LEAD_CAPTURE = 'lead_capture';
    const FEATURE_EXPORT = 'conversation_export';
    const FEATURE_WHITE_LABEL = 'white_label';
    const FEATURE_WEBHOOK = 'webhook';

    /**
     * Free version limits (no artificial limits — users pay their own API costs)
     */
    const FREE_MESSAGE_LIMIT = PHP_INT_MAX;
    const FREE_FAQ_LIMIT = PHP_INT_MAX;

    /**
     * Singleton instance (protected for Pro override)
     */
    protected static ?WPAIC_Pro_Features $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): WPAIC_Pro_Features {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set instance (for Pro plugin override)
     */
    public static function set_instance(WPAIC_Pro_Features $instance): void {
        self::$instance = $instance;
    }

    /**
     * Protected constructor (allows extension)
     */
    protected function __construct() {}

    /**
     * Check if user has Pro - always false in Free version
     */
    public function is_pro(): bool {
        return false;
    }

    /**
     * Check if a specific feature is available - always false in Free version
     */
    public function is_feature_available(string $feature): bool {
        return false;
    }

    /**
     * Get monthly message limit
     */
    public function get_message_limit(): int {
        return self::FREE_MESSAGE_LIMIT;
    }

    /**
     * Get monthly AI response count
     */
    public function get_monthly_ai_response_count(): int {
        global $wpdb;
        $table = wpaic_require_table('aichat_messages', 'get_monthly_ai_response_count');
        if (!$table) {
            // Fall back to no-history count only
            $nohist_counts = (array) get_option('wpaic_nohist_msg_counts', []);
            return (int) ($nohist_counts[wp_date('Y_m')] ?? 0);
        }
        // Use WordPress site timezone for consistent month boundary calculation
        $month_start = wp_date('Y-m-01 00:00:00');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $db_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE role = 'assistant' AND created_at >= %s",
            $month_start
        ));
        // Include messages sent while save_history was OFF (same TZ key)
        $nohist_counts = (array) get_option('wpaic_nohist_msg_counts', []);
        $nohist_count = (int) ($nohist_counts[wp_date('Y_m')] ?? 0);
        return $db_count + $nohist_count;
    }

    /**
     * Get remaining messages this month
     */
    public function get_remaining_messages(): int {
        $limit = $this->get_message_limit();
        if ($limit === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - $this->get_monthly_ai_response_count());
    }

    /**
     * Check if message limit is reached (always passes in Free — no artificial limits)
     */
    public function check_message_limit() {
        if ($this->get_remaining_messages() <= 0) {
            return new \WP_Error('message_limit_exceeded', __('Service temporarily unavailable.', 'rapls-ai-chatbot'));
        }
        return true;
    }

    /**
     * Check if limit is reached (boolean version)
     */
    public function is_limit_reached(): bool {
        return $this->get_remaining_messages() <= 0;
    }

    /**
     * Get FAQ limit
     */
    public function get_faq_limit(): int {
        return self::FREE_FAQ_LIMIT;
    }

    /**
     * Check if more FAQ entries can be added
     */
    public function can_add_faq(): bool {
        return WPAIC_Knowledge::get_count() < $this->get_faq_limit();
    }

    /**
     * Get the configured email subject prefix.
     *
     * @return string Prefix string (e.g. "Rapls AI Chatbot").
     */
    public static function get_email_subject_prefix(): string {
        $settings = get_option('wpaic_settings', []);
        $pro = $settings['pro_features'] ?? [];
        $prefix = trim($pro['email_subject_prefix'] ?? '');
        return $prefix !== '' ? $prefix : 'Rapls AI Chatbot';
    }

    /**
     * Get default Pro features settings
     * This is kept for settings compatibility with Pro plugin
     */
    public static function get_default_settings(): array {
        return [
            // Lead capture
            'lead_capture_enabled' => false,
            'lead_capture_required' => false,
            'lead_fields' => [
                'name' => ['enabled' => true, 'required' => true, 'label' => 'Name', 'type' => 'text'],
                'email' => ['enabled' => true, 'required' => true, 'label' => 'Email', 'type' => 'email'],
                'phone' => ['enabled' => false, 'required' => false, 'label' => 'Phone', 'type' => 'tel'],
                'company' => ['enabled' => false, 'required' => false, 'label' => 'Company', 'type' => 'text'],
            ],
            'lead_custom_fields' => [],
            'lead_form_title' => __('Before we start', 'rapls-ai-chatbot'),
            'lead_form_description' => __('Please enter your information', 'rapls-ai-chatbot'),
            'lead_notification_enabled' => false,
            'lead_notification_email' => '',

            // Email subject prefix (shared by all notifications)
            'email_subject_prefix' => 'Rapls AI Chatbot',

            // White label
            'white_label_enabled' => false,
            'hide_powered_by' => false,
            'white_label_footer' => '',
            'white_label_footer_url' => '',
            'white_label_footer_target' => '_blank',
            'custom_css' => '',

            // Webhook
            'webhook_enabled' => false,
            'webhook_url' => '',
            'webhook_secret' => '',
            'webhook_events' => [
                'new_conversation' => true,
                'new_message' => true,
                'lead_captured' => true,
                'handoff_requested' => true,
                'handoff_resolved' => true,
                'offline_message' => true,
                'ai_error' => true,
                'budget_alert' => true,
                'rate_limit_exceeded' => false,
                'banned_word_detected' => false,
                'recaptcha_failed' => false,
            ],

            // Quick replies
            'quick_replies_enabled' => false,
            'quick_replies' => [],

            // Business hours
            'business_hours_enabled' => false,
            'business_hours' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'saturday' => ['enabled' => false, 'start' => '10:00', 'end' => '17:00'],
                'sunday' => ['enabled' => false, 'start' => '10:00', 'end' => '17:00'],
            ],
            'business_hours_timezone' => 'Asia/Tokyo',
            'outside_hours_message' => __('We are currently outside business hours. Please leave a message and we will get back to you.', 'rapls-ai-chatbot'),

            // Holidays
            'holidays_enabled' => false,
            'holidays' => [],
            'holiday_message' => __('We are closed today. Please contact us on the next business day.', 'rapls-ai-chatbot'),

            // Content filters
            'banned_words_enabled' => false,
            'banned_words' => '',
            'banned_words_message' => __('Your message contains prohibited content.', 'rapls-ai-chatbot'),

            // IP blocking
            'ip_block_enabled' => false,
            'blocked_ips' => '',
            'ip_block_message' => __('Access denied.', 'rapls-ai-chatbot'),

            // Enhanced Rate Limiting
            'enhanced_rate_limit_enabled' => false,
            'rate_limit_per_minute' => 5,
            'rate_limit_per_hour' => 30,
            'rate_limit_message' => __('Too many messages. Please wait a moment before sending again.', 'rapls-ai-chatbot'),

            // AI Enhancement
            'related_suggestions_enabled' => false,
            'autocomplete_enabled' => false,
            'prompt_templates_enabled' => false,
            'prompt_templates' => [],
            'active_prompt_template' => '',
            'prompt_template_overrides' => [],
            'show_regenerate_button' => true,

            // Sentiment Analysis
            'sentiment_analysis_enabled' => false,

            // Context Memory
            'context_memory_enabled' => false,
            'context_memory_days' => 30,

            // Multimodal Support
            'multimodal_enabled' => false,
            'multimodal_max_size' => 2048,
            'multimodal_formats' => ['jpg', 'png', 'gif', 'webp'],

            // Enhanced Content Extraction
            'enhanced_content_extraction' => false,

            // Operations Enhancement
            'scheduled_crawl_enabled' => false,
            'scheduled_crawl_frequency' => 'weekly',
            'scheduled_crawl_time' => '03:00',
            'diff_crawl_enabled' => true,

            'human_handoff_enabled' => false,
            'handoff_keywords' => '',
            'handoff_auto_keywords_ja' => '人間と話したい,オペレーター,サポートに繋いで,担当者',
            'handoff_email' => '',
            'handoff_message' => __('I understand this may need human assistance. A support representative will contact you soon.', 'rapls-ai-chatbot'),
            'handoff_notification_method' => 'email',
            'handoff_slack_webhook_url' => '',
            'handoff_auto_detect' => true,
            'operator_auto_close_minutes' => 30,

            'survey_enabled' => false,
            'survey_trigger' => 'end',
            'survey_question' => __('How would you rate this conversation?', 'rapls-ai-chatbot'),
            'survey_options' => [],

            'conversation_tags_enabled' => false,
            'auto_tags' => [],

            // Advanced Features
            'multi_bot_enabled' => false,
            'bots' => [],

            'operator_mode_enabled' => false,
            'operator_takeover_message' => __('You are now connected with a support representative.', 'rapls-ai-chatbot'),

            'faq_auto_generation_enabled' => false,
            'faq_min_occurrences' => 3,
            'faq_generation_prompt' => "You are a helpful assistant for a website. Based on the following frequently asked question, write a clear, concise, and helpful answer.\n\nQuestion: {question}\n\nProvide ONLY the answer text, no prefix or labels. Keep it under 300 words. Use a friendly, professional tone.",

            // Badge icon
            'badge_icon_type' => 'default',
            'badge_icon_preset' => '',
            'badge_icon_image' => '',
            'badge_icon_emoji' => '',

            // Budget & Usage Alerts
            'budget_alert_enabled' => false,
            'budget_alert_email' => '',
            'budget_alert_threshold' => 10.00,
            'budget_limit_enabled' => false,
            'budget_limit_amount' => 50.00,
            'budget_block_message' => __('The AI service is temporarily unavailable due to usage limits. Please try again later.', 'rapls-ai-chatbot'),

            // Monthly Report
            'monthly_report_enabled' => false,
            'monthly_report_email' => '',

            // Summary Report (daily/weekly)
            'summary_report_enabled' => false,
            'summary_report_frequency' => 'weekly',
            'summary_report_email' => '',

            // AI Prompts (customizable)
            'sentiment_prompt' => "Analyze the emotional sentiment of the following message and respond with ONLY ONE of these words: frustrated, confused, urgent, positive, negative, neutral\n\nRules:\n- frustrated: anger, irritation, complaint\n- confused: uncertainty, asking for help understanding\n- urgent: time pressure, emergency\n- positive: gratitude, satisfaction, happiness\n- negative: sadness, disappointment, hardship\n- neutral: no clear emotion\n\nMessage: {message}\n\nSentiment:",
            'sentiment_tone_frustrated' => '[TONE ADJUSTMENT: The user appears frustrated. Respond with extra patience and empathy. Acknowledge their frustration, apologize for any inconvenience, and focus on providing clear, step-by-step solutions. Avoid defensive language.]',
            'sentiment_tone_confused' => '[TONE ADJUSTMENT: The user seems confused. Provide explanations in simple, clear terms. Break down complex concepts. Use examples where helpful. Ask clarifying questions if needed.]',
            'sentiment_tone_urgent' => '[TONE ADJUSTMENT: The user has an urgent request. Be concise and direct. Prioritize the most important information first. Skip unnecessary pleasantries and get straight to the solution.]',
            'sentiment_tone_positive' => '[TONE ADJUSTMENT: The user is in a positive mood. Match their energy with a warm, friendly tone while remaining helpful.]',
            'sentiment_tone_negative' => '[TONE ADJUSTMENT: The user seems dissatisfied. Be understanding and solution-focused. Acknowledge their concern and work towards resolution.]',
            'suggestions_prompt' => 'Based on the following conversation, suggest 3 short follow-up questions the user might want to ask. Return only the questions, one per line, without numbering:',
            'pro_summary_prompt' => 'Please summarize the following conversation in 2-3 sentences, highlighting the main topics discussed and any conclusions reached:',
            'context_extraction_prompt' => "Based on this conversation, create a brief user profile in JSON format with these fields:\n- summary: 1-2 sentence summary of what the user was asking about (include user's name if mentioned)\n- topics: array of 3-5 key topics discussed\n- preferences: array of any user preferences or personal info mentioned (name, location, etc.)\nReturn ONLY valid JSON, no markdown or explanation.\n\nConversation:\n{conversation}",
            'context_memory_prompt' => "[USER CONTEXT FROM PREVIOUS CONVERSATIONS]\nSummary: {summary}\nPrevious topics discussed: {topics}\nKnown preferences: {preferences}\nLast interaction: {last_date}\n[Use this context to provide personalized assistance while respecting user privacy.]",

            // Response Cache
            'response_cache_enabled' => false,
            'cache_ttl_days' => 7,

            // Conversion Tracking
            'conversion_tracking_enabled' => false,
            'conversion_goals' => [],

            // WooCommerce Product Cards
            'woocommerce_cards_enabled' => true,

            // Note: embedding_enabled / embedding_provider are managed as top-level settings
            // in get_all_defaults(), not in pro_features.

            // Voice Input / TTS
            'voice_input_enabled' => false,
            'tts_enabled' => false,
            'tts_lang' => '',

            // LINE Messaging API
            'line_enabled' => false,
            'line_channel_secret' => '',
            'line_channel_access_token' => '',

            // AI Content Generation (Editor Sidebar)
            'ai_content_enabled' => false,

            // AI Forms
            'ai_forms_enabled' => false,
            'ai_forms' => [],

            // Actions / Intent Recognition
            'actions_enabled' => false,
            'actions' => [],

            // Conversation Scenarios
            'scenarios_enabled' => false,
            'scenarios' => [],

            // Offline Messages
            'offline_message_enabled' => false,
            'offline_form_title' => __('We are currently offline', 'rapls-ai-chatbot'),
            'offline_form_description' => __('Please leave a message and we will get back to you.', 'rapls-ai-chatbot'),
            'offline_notification_email' => '',
            'offline_notification_enabled' => false,

            // Role-based Access Control
            'role_access_enabled' => false,
            'role_access_default' => 'allow',
            'role_limits' => [],

            // UI Enhancements
            'fullscreen_mode' => false,
            'welcome_screen_enabled' => false,
            'welcome_screen_title' => '',
            'welcome_screen_message' => '',
            'welcome_screen_buttons' => [],
            'response_delay_enabled' => false,
            'response_delay_ms' => 500,
            'notification_sound_enabled' => false,
            'tooltips_enabled' => false,
            'custom_font' => '',
            'seasonal_theme' => '',

            // Integrations
            'slack_enabled' => false,
            'slack_webhook_url' => '',
            'slack_events' => ['new_conversation' => false, 'new_message' => false, 'lead_captured' => true, 'handoff_requested' => true],
            'google_sheets_enabled' => false,
            'google_sheets_url' => '',

            // Operations
            'spam_detection_enabled' => false,
            'spam_score_threshold' => 3,
            'country_block_enabled' => false,
            'blocked_countries' => '',
            'country_block_message' => 'Access denied from your region.',
            'ip_whitelist_enabled' => false,
            'whitelisted_ips' => '',
            'external_learning_enabled' => false,
            'external_urls' => '',

            // Chat
            'file_upload_enabled' => false,
            'file_upload_max_size' => 5120,
            'file_upload_types' => ['pdf', 'doc', 'docx', 'txt', 'csv'],
            'chat_bookmarks_enabled' => false,
            'chat_search_enabled' => false,
            'conversation_sharing_enabled' => false,

            // Knowledge
            'knowledge_versioning_enabled' => false,
            'knowledge_expiration_enabled' => false,
            'knowledge_expiration_days' => 90,
            'knowledge_auto_priority_enabled' => false,
            'knowledge_related_links_enabled' => false,
            'intent_classification_enabled' => false,
            'custom_intents' => '',

            // Security
            'pii_masking_enabled' => false,
            'pii_patterns' => 'email,phone,credit_card',
            'data_retention_enabled' => false,
            'data_retention_days' => 365,
            'security_headers_enabled' => false,

            // Performance
            'similar_cache_enabled' => false,
            'batch_processing_enabled' => false,
            'performance_monitoring_enabled' => false,

            // Developer
            'test_mode_enabled' => false,
            'test_mode_response' => '',

            // Change Management
            'change_history_enabled' => false,
            'change_history_max' => 50,
            'staging_enabled' => false,
            'approval_workflow_enabled' => false,
            'approval_email' => '',
            'rollback_enabled' => false,

            // Multisite
            'multisite_enabled' => false,
            'multisite_network_settings' => false,

            // Screen Sharing
            'screen_sharing_enabled' => false,

            // Booking Integration
            'booking_enabled' => false,
            'booking_provider' => '',
            'booking_keywords' => '',
            'booking_page_url' => '',

            // Multi-bot Coordination
            'multi_bot_coordination_enabled' => false,
            'bot_routing_mode' => 'manual',

            // Similar Question Merge
            'similar_question_merge_enabled' => false,
            'similar_threshold' => 80,

            // Encryption
            'encryption_enabled' => false,
            'encryption_fields' => 'messages,leads',

            // Vulnerability Scanning
            'vulnerability_scan_enabled' => false,
            'vulnerability_scan_schedule' => 'weekly',

            // Queue Management
            'queue_enabled' => false,
            'queue_max_concurrent' => 5,
            'queue_priority_logged_in' => true,
        ];
    }

    /**
     * Get default prompt templates (10 industry templates)
     *
     * Template prompts are in English (universal). Names and descriptions are translatable.
     *
     * @return array<string, array{id: string, name: string, description: string, prompt: string}>
     */
    public static function get_default_prompt_templates(): array {
        return [
            'customer_support' => [
                'id'          => 'customer_support',
                'name'        => __('Customer Support', 'rapls-ai-chatbot'),
                'description' => __('Friendly and empathetic support agent for general customer service.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a friendly and professional customer support agent for {site_name}. Your goal is to help customers resolve their issues quickly and efficiently.\n\nGuidelines:\n- Be empathetic and patient with all customers\n- Ask clarifying questions when the issue is unclear\n- Provide step-by-step solutions when possible\n- If you cannot resolve the issue, offer to escalate to a human agent\n- Always maintain a positive and helpful tone\n- Reference relevant help articles or documentation when available",
            ],
            'ecommerce' => [
                'id'          => 'ecommerce',
                'name'        => __('E-Commerce', 'rapls-ai-chatbot'),
                'description' => __('Shopping assistant for online stores with product recommendations.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a knowledgeable shopping assistant for {site_name}. Help customers find the right products and make informed purchasing decisions.\n\nGuidelines:\n- Ask about customer preferences, budget, and needs\n- Recommend products based on their requirements\n- Provide accurate product information including features and specifications\n- Help with order status, shipping, and return inquiries\n- Suggest complementary products when appropriate\n- Be honest about product limitations",
            ],
            'educational' => [
                'id'          => 'educational',
                'name'        => __('Educational', 'rapls-ai-chatbot'),
                'description' => __('Patient tutor that explains concepts clearly for learning platforms.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a patient and encouraging educational assistant for {site_name}. Help students learn and understand concepts effectively.\n\nGuidelines:\n- Explain concepts in simple, clear language\n- Use examples and analogies to illustrate points\n- Break complex topics into smaller, manageable parts\n- Encourage questions and curiosity\n- Adapt explanations to the student's level of understanding\n- Provide practice exercises or additional resources when helpful\n- Never give direct answers to assignments; guide students to find answers themselves",
            ],
            'faq' => [
                'id'          => 'faq',
                'name'        => __('FAQ Assistant', 'rapls-ai-chatbot'),
                'description' => __('Concise answers from knowledge base, focused on common questions.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a concise FAQ assistant for {site_name}. Provide clear, direct answers to frequently asked questions.\n\nGuidelines:\n- Give brief, accurate answers based on available information\n- Use bullet points for multi-part answers\n- If a question is outside your knowledge, clearly state that and suggest contacting support\n- Link to relevant pages or resources when available\n- Avoid unnecessary elaboration; be efficient with responses\n- Group related information logically",
            ],
            'appointment' => [
                'id'          => 'appointment',
                'name'        => __('Appointment Booking', 'rapls-ai-chatbot'),
                'description' => __('Scheduling assistant for service-based businesses.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a scheduling assistant for {site_name}. Help visitors book appointments and manage their reservations.\n\nGuidelines:\n- Ask about the type of service or appointment needed\n- Inquire about preferred dates and times\n- Confirm appointment details before finalizing\n- Provide information about preparation or requirements for the appointment\n- Help with rescheduling or cancellation requests\n- Be clear about business hours and availability\n- Collect necessary contact information politely",
            ],
            'restaurant' => [
                'id'          => 'restaurant',
                'name'        => __('Restaurant', 'rapls-ai-chatbot'),
                'description' => __('Restaurant assistant for menus, reservations, and dietary needs.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a welcoming restaurant assistant for {site_name}. Help guests with menu information, reservations, and dining inquiries.\n\nGuidelines:\n- Provide detailed menu information including ingredients and allergens\n- Help with table reservations and party arrangements\n- Accommodate dietary restrictions and preferences\n- Share information about specials, promotions, and events\n- Provide directions, parking information, and operating hours\n- Be warm and inviting in your communication style",
            ],
            'real_estate' => [
                'id'          => 'real_estate',
                'name'        => __('Real Estate', 'rapls-ai-chatbot'),
                'description' => __('Property assistant for real estate listings and inquiries.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a professional real estate assistant for {site_name}. Help visitors find properties and answer real estate inquiries.\n\nGuidelines:\n- Ask about property requirements (location, size, budget, type)\n- Provide detailed property information when available\n- Explain the buying or renting process clearly\n- Help schedule property viewings\n- Answer questions about neighborhoods, amenities, and market trends\n- Collect contact information for follow-up by agents\n- Be transparent about pricing and fees",
            ],
            'saas_tech' => [
                'id'          => 'saas_tech',
                'name'        => __('SaaS / Tech Support', 'rapls-ai-chatbot'),
                'description' => __('Technical support for software products with troubleshooting guidance.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a technical support specialist for {site_name}. Help users troubleshoot issues and get the most out of the product.\n\nGuidelines:\n- Ask for specific error messages, screenshots, or steps to reproduce issues\n- Provide step-by-step troubleshooting instructions\n- Explain technical concepts in user-friendly language\n- Suggest workarounds when direct solutions are not available\n- Help with account setup, configuration, and feature usage\n- Escalate complex issues to the engineering team when needed\n- Share links to documentation and knowledge base articles",
            ],
            'healthcare' => [
                'id'          => 'healthcare',
                'name'        => __('Healthcare', 'rapls-ai-chatbot'),
                'description' => __('Medical office assistant for appointments and general health info.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a healthcare information assistant for {site_name}. Help visitors with appointment scheduling and general health information.\n\nGuidelines:\n- Help schedule, reschedule, or cancel appointments\n- Provide general information about services offered\n- Share clinic hours, locations, and contact details\n- Answer questions about insurance and payment options\n- IMPORTANT: Never provide medical diagnoses or treatment recommendations\n- Always recommend consulting with a healthcare professional for medical concerns\n- Handle patient information requests with sensitivity and privacy awareness",
            ],
            'business_consulting' => [
                'id'          => 'business_consulting',
                'name'        => __('Business Consulting', 'rapls-ai-chatbot'),
                'description' => __('Professional and trustworthy assistant for B2B and consulting sites.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a professional business consultant assistant for {site_name}. Help visitors understand how your services can address their business challenges.\n\nGuidelines:\n- Maintain a professional, authoritative tone that builds trust\n- Ask about the visitor's business size, industry, and specific challenges\n- Explain service offerings with clear value propositions\n- Use data-driven language and reference industry best practices\n- Help qualify leads by understanding their needs and budget\n- Suggest relevant case studies or success stories when available\n- Guide visitors toward scheduling a consultation for detailed discussions\n- Be transparent about pricing models and engagement processes",
            ],
            'legal_consulting' => [
                'id'          => 'legal_consulting',
                'name'        => __('Legal / Consulting', 'rapls-ai-chatbot'),
                'description' => __('Professional services assistant for consultations and general legal info.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a professional services assistant for {site_name}. Help visitors understand available services and schedule consultations.\n\nGuidelines:\n- Explain service offerings and areas of expertise\n- Help schedule initial consultations\n- Provide general information about processes and timelines\n- IMPORTANT: Never provide specific legal, financial, or professional advice\n- Always recommend scheduling a consultation for specific questions\n- Collect relevant information for the initial meeting\n- Be professional and maintain confidentiality\n- Share fee structures and payment information when available",
            ],
            'landing_page' => [
                'id'          => 'landing_page',
                'name'        => __('Landing Page', 'rapls-ai-chatbot'),
                'description' => __('Conversion-focused assistant for product/service landing pages.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a conversion-focused assistant for {site_name}. Your goal is to answer visitor questions quickly and guide them toward taking action (sign up, purchase, request a demo).\n\nGuidelines:\n- Keep responses short and persuasive — visitors on landing pages have limited attention\n- Highlight key benefits and differentiators of the product or service\n- Address common objections and concerns proactively\n- Use clear calls to action (e.g., \"Ready to get started?\" or \"Want a personalized demo?\")\n- If the visitor seems interested, encourage them to fill out a form or click the CTA\n- Provide social proof (testimonials, case studies, numbers) when available\n- Never be pushy; be helpful and confident instead",
            ],
            'corporate' => [
                'id'          => 'corporate',
                'name'        => __('Corporate Site', 'rapls-ai-chatbot'),
                'description' => __('Professional assistant for corporate websites with company info and services.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a professional corporate assistant for {site_name}. Help visitors learn about the company, its services, and how to engage with the organization.\n\nGuidelines:\n- Maintain a formal yet approachable tone that reflects corporate professionalism\n- Provide accurate information about company overview, mission, and values\n- Explain business divisions, services, and solutions clearly\n- Guide visitors to appropriate departments or contact points\n- Help with career inquiries and job openings when available\n- Share press releases, news, and IR information as applicable\n- Assist with partnership and business development inquiries\n- Respect confidentiality — do not speculate on non-public information",
            ],
            'blog_media' => [
                'id'          => 'blog_media',
                'name'        => __('Blog / Media', 'rapls-ai-chatbot'),
                'description' => __('Content guide for blogs, news sites, and media platforms.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a content guide for {site_name}. Help readers discover articles, navigate topics, and find the information they are looking for.\n\nGuidelines:\n- Recommend relevant articles and content based on the reader's interests\n- Summarize key points from articles when asked\n- Help readers find content by topic, date, or category\n- Suggest related or popular articles to encourage further reading\n- Answer questions about topics covered on the site using published content\n- If a topic hasn't been covered, let the reader know honestly\n- Keep a conversational and engaging tone matching the site's editorial voice",
            ],
            'recruitment' => [
                'id'          => 'recruitment',
                'name'        => __('Recruitment', 'rapls-ai-chatbot'),
                'description' => __('Hiring assistant for career pages and recruitment sites.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a recruitment assistant for {site_name}. Help job seekers find the right positions and guide them through the application process.\n\nGuidelines:\n- Ask about the candidate's skills, experience, and career interests\n- Recommend suitable open positions based on their profile\n- Explain the application process, interview stages, and timelines\n- Provide information about company culture, benefits, and work environment\n- Help with common questions about requirements and qualifications\n- Guide candidates to the application form or career page\n- Be encouraging and respectful regardless of the candidate's experience level",
            ],
            'travel_hotel' => [
                'id'          => 'travel_hotel',
                'name'        => __('Travel / Hotel', 'rapls-ai-chatbot'),
                'description' => __('Hospitality assistant for travel agencies, hotels, and tourism sites.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a hospitality assistant for {site_name}. Help guests and travelers with bookings, local information, and travel planning.\n\nGuidelines:\n- Help with room/tour reservations, check-in/check-out, and availability\n- Provide information about amenities, facilities, and services\n- Recommend local attractions, restaurants, and activities\n- Assist with transportation and directions\n- Handle special requests (dietary needs, accessibility, celebrations)\n- Share pricing, packages, and seasonal promotions\n- Be warm, welcoming, and attentive to guest needs",
            ],
            'fitness_wellness' => [
                'id'          => 'fitness_wellness',
                'name'        => __('Fitness / Wellness', 'rapls-ai-chatbot'),
                'description' => __('Wellness assistant for gyms, yoga studios, and health-related sites.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a wellness assistant for {site_name}. Help visitors learn about programs, memberships, and wellness services.\n\nGuidelines:\n- Provide information about classes, programs, and schedules\n- Explain membership plans, pricing, and trial offers\n- Help with booking classes or sessions\n- Answer questions about facilities, trainers, and equipment\n- Share general wellness tips aligned with the site's offerings\n- IMPORTANT: Never provide specific medical or nutritional prescriptions\n- Encourage visitors to consult professionals for health concerns\n- Be motivating and supportive in your communication",
            ],
            'nonprofit' => [
                'id'          => 'nonprofit',
                'name'        => __('Nonprofit / NGO', 'rapls-ai-chatbot'),
                'description' => __('Mission-driven assistant for nonprofits, charities, and community organizations.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a community assistant for {site_name}. Help visitors understand the organization's mission and how they can get involved.\n\nGuidelines:\n- Explain the organization's mission, programs, and impact clearly\n- Guide visitors on how to donate, volunteer, or participate\n- Share success stories and impact data when available\n- Provide information about upcoming events and campaigns\n- Help with inquiries about services offered to beneficiaries\n- Be passionate about the cause while remaining informative\n- Make it easy for supporters to take the next step",
            ],
            'event' => [
                'id'          => 'event',
                'name'        => __('Event / Seminar', 'rapls-ai-chatbot'),
                'description' => __('Event assistant for conferences, seminars, and webinar registration sites.', 'rapls-ai-chatbot'),
                'prompt'      => "You are an event assistant for {site_name}. Help visitors learn about events and complete their registration.\n\nGuidelines:\n- Provide event details: date, time, venue, speakers, and agenda\n- Guide visitors through the registration or ticket purchase process\n- Answer questions about pricing, early-bird offers, and group discounts\n- Share information about access, parking, and accommodation\n- Help with cancellations, refunds, and transfers\n- Promote key highlights and reasons to attend\n- For virtual events, provide technical requirements and access instructions",
            ],
            'membership' => [
                'id'          => 'membership',
                'name'        => __('Membership / Subscription', 'rapls-ai-chatbot'),
                'description' => __('Subscription assistant for membership sites, SaaS plans, and online communities.', 'rapls-ai-chatbot'),
                'prompt'      => "You are a membership assistant for {site_name}. Help visitors choose the right plan and manage their subscriptions.\n\nGuidelines:\n- Clearly explain available plans, features, and pricing differences\n- Help visitors identify which plan best fits their needs\n- Assist with account creation, upgrades, and downgrades\n- Answer billing and payment questions\n- Explain free trial terms and cancellation policies\n- Highlight exclusive member benefits and content\n- Handle common account issues (password reset, access problems)",
            ],
        ];
    }

    /**
     * Stub methods - these return safe defaults in Free version
     */
    public function is_within_business_hours(): bool {
        return true;
    }

    public function is_holiday(): bool {
        return false;
    }

    public function get_unavailable_message(): ?string {
        return null;
    }

    public function contains_banned_words(string $message): bool {
        return false;
    }

    public function get_banned_words_message(): string {
        return __('Your message contains prohibited content.', 'rapls-ai-chatbot');
    }

    public function is_ip_blocked(?string $ip = null): bool {
        return false;
    }

    public function get_ip_block_message(): string {
        return __('Access denied.', 'rapls-ai-chatbot');
    }

    /**
     * Stub: Check IP whitelist
     */
    public function check_ip_whitelist(?string $ip = null): bool {
        return true; // Allow all when Pro is not active
    }

    /**
     * Stub: Check spam
     */
    public function is_spam(string $message): bool {
        return false;
    }

    public function get_spam_message(): string {
        return __('Your message was flagged as spam.', 'rapls-ai-chatbot');
    }

    /**
     * Stub: Check enhanced rate limit
     */
    public function check_enhanced_rate_limit(?string $ip = null): array {
        return ['blocked' => false, 'message' => ''];
    }

    public function get_quick_replies(): array {
        return [];
    }

    /**
     * Stub: Check if sentiment analysis is enabled
     */
    public function is_sentiment_analysis_enabled(): bool {
        return false;
    }

    /**
     * Stub: Analyze sentiment
     */
    public function analyze_sentiment(string $message): string {
        return 'neutral';
    }

    /**
     * Stub: Get sentiment prompt
     */
    public function get_sentiment_prompt(string $sentiment): string {
        return '';
    }

    /**
     * Stub: Check if context memory is enabled
     */
    public function is_context_memory_enabled(): bool {
        return false;
    }

    /**
     * Derive a stable, server-side context key from session_id.
     * Uses HMAC so the key cannot be guessed or manipulated by the client.
     * Stub returns '' (Pro overrides with actual implementation).
     *
     * @param string $session_id Session ID (UUID v4).
     * @return string Context key (hex, max 64 chars) or '' if unavailable.
     */
    public function derive_context_key(string $session_id): string {
        return '';
    }

    /**
     * Stub: Get user context
     */
    public function get_user_context(string $context_key): array {
        return [];
    }

    /**
     * Stub: Save user context
     */
    public function save_user_context(string $context_key, array $context): bool {
        return false;
    }

    /**
     * Stub: Build context memory prompt
     */
    public function build_context_memory_prompt(array $user_context): string {
        return '';
    }

    /**
     * Stub: Check if multimodal is enabled
     */
    public function is_multimodal_enabled(): bool {
        return false;
    }

    /**
     * Stub: Get multimodal config
     */
    public function get_multimodal_config(): array {
        return [
            'enabled' => false,
            'max_size' => 2048,
            'formats' => ['jpg', 'png', 'gif', 'webp'],
        ];
    }

    /**
     * Stub: Check budget limit
     */
    public function check_budget_limit(): bool {
        return false;
    }

    /**
     * Stub: Get budget block message
     */
    public function get_budget_block_message(): string {
        return '';
    }

    /**
     * Stub: Maybe send budget alert
     */
    public function maybe_send_budget_alert(float $cost): void {
        // no-op in Free
    }

    /**
     * Stub: Check if WooCommerce product cards are enabled
     */
    public function is_woocommerce_cards_enabled(): bool {
        return false;
    }

    /**
     * Stub: Get defined actions
     */
    public function get_actions(): array {
        return [];
    }

    /**
     * Stub: Check if message contains a handoff keyword (no DB lookup).
     * Used to bypass rate limiting for handoff requests.
     */
    public function is_handoff_keyword(string $message): bool {
        return false;
    }

    /**
     * Stub: Check if message triggers handoff to human operator
     */
    public function check_handoff_trigger(string $message, int $conversation_id): bool {
        return false;
    }

    /**
     * Stub: Get handoff status for a conversation
     */
    public function get_handoff_status(int $conversation_id): ?string {
        return null;
    }

    /**
     * Stub: Cancel handoff for a conversation
     */
    public function cancel_handoff(int $conversation_id): void {
    }

    /**
     * Stub: Process handoff escalation
     */
    public function process_handoff(int $conversation_id, string $session_id): array {
        return ['escalated' => false];
    }

    /**
     * Stub: Resolve bot configuration by slug.
     * Returns null in Free (single bot only). Pro overrides with actual lookup.
     *
     * @param string|null $bot_id Bot slug (e.g. 'sales').
     * @return array|null Bot config array or null if not found/disabled.
     */
    public function resolve_bot_config(?string $bot_id = null): ?array {
        return null;
    }

    /**
     * Stub: Get the bot assigned to a specific page via page rules.
     * Returns 'default' in Free (no page-based bot routing).
     *
     * @param int $page_id WordPress page/post ID.
     * @return string Bot slug or 'default'.
     */
    public function get_bot_for_page(int $page_id): string {
        return 'default';
    }

    /**
     * Stub: Get role-based message limit for current user.
     * Free version ignores roles and returns site-wide limit.
     */
    public function get_role_message_limit(): int {
        return $this->get_message_limit();
    }

    /**
     * Stub: Check if chat is allowed for current user based on role.
     * Free version always allows.
     */
    public function is_chat_allowed_for_user(): bool {
        return true;
    }

    /**
     * Stub: Get settings change history
     */
    public function get_change_history(): array {
        return [];
    }

    /**
     * Stub: Rollback settings to a specific version
     */
    public function rollback_settings(int $version_index): bool {
        return false;
    }

    /**
     * Stub: Get staging settings
     */
    public function get_staging_settings(): ?array {
        return null;
    }

    /**
     * Stub: Publish staging settings
     */
    public function publish_staging(): bool {
        return false;
    }

    /**
     * Stub: Get pending approval changes
     */
    public function get_pending_approvals(): array {
        return [];
    }

    /**
     * Stub: Run vulnerability scan
     */
    public function run_vulnerability_scan(): array {
        return [];
    }

    /**
     * Stub: Get queue status
     */
    public function get_queue_status(): array {
        return ['pending' => 0, 'processing' => 0, 'max' => 5];
    }

    /**
     * Stub: Find similar knowledge entries
     */
    public function find_similar_questions(int $knowledge_id): array {
        return [];
    }

    /**
     * Stub: Merge knowledge entries
     */
    public function merge_knowledge_entries(array $ids, int $primary_id): bool {
        return false;
    }

    /**
     * Stub: Get context memory retention days
     */
    public function get_context_memory_days(): int {
        return 30;
    }

    /**
     * Stub: Get active prompt template text
     */
    public function get_active_prompt_template_text(): ?string {
        return null;
    }

    /**
     * Stub: Calculate spam score for a message
     */
    public function calculate_spam_score(string $message): int {
        return 0;
    }

    /**
     * Stub: Mask PII in content
     */
    public function mask_pii(string $content): string {
        return $content;
    }

    /**
     * Stub: Send Slack notification
     */
    public function send_slack_notification(string $event, array $data): void {
        // No-op in Free version
    }

    /**
     * Stub: Send lead data to Google Sheets
     */
    public function send_to_google_sheets(array $lead_data): void {
        // No-op in Free version
    }
}
