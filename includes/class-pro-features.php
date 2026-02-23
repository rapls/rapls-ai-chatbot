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
     * Feature constants
     */
    const FEATURE_MESSAGE_LIMIT = 'message_limit';
    const FEATURE_LEAD_CAPTURE = 'lead_capture';
    const FEATURE_EXPORT = 'conversation_export';
    const FEATURE_WHITE_LABEL = 'white_label';
    const FEATURE_WEBHOOK = 'webhook';

    /**
     * Free version limits
     */
    const FREE_MESSAGE_LIMIT = 50;
    const FREE_FAQ_LIMIT = 20;

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
        $table = $wpdb->prefix . 'aichat_messages';
        // Use WordPress site timezone for consistent month boundary calculation
        $month_start = wp_date('Y-m-01 00:00:00');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $db_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE role = 'assistant' AND created_at >= %s",
            $month_start
        ));
        // Include messages sent while save_history was OFF (same TZ key)
        $nohist_count = (int) get_option('wpaic_nohist_msg_count_' . wp_date('Y_m'), 0);
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
     * Check if message limit is reached
     */
    public function check_message_limit() {
        if ($this->get_remaining_messages() <= 0) {
            return new \WP_Error('message_limit_exceeded', __('Monthly AI response limit reached.', 'rapls-ai-chatbot'));
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
     * Get default Pro features settings
     * This is kept for settings compatibility with Pro plugin
     */
    public static function get_default_settings(): array {
        return [
            // Lead capture
            'lead_capture_enabled' => false,
            'lead_capture_required' => false,
            'lead_fields' => [
                'name' => ['enabled' => true, 'required' => true, 'label' => __('Name', 'rapls-ai-chatbot')],
                'email' => ['enabled' => true, 'required' => true, 'label' => __('Email', 'rapls-ai-chatbot')],
                'phone' => ['enabled' => false, 'required' => false, 'label' => __('Phone', 'rapls-ai-chatbot')],
                'company' => ['enabled' => false, 'required' => false, 'label' => __('Company', 'rapls-ai-chatbot')],
            ],
            'lead_form_title' => __('Before we start', 'rapls-ai-chatbot'),
            'lead_form_description' => __('Please enter your information', 'rapls-ai-chatbot'),
            'lead_notification_enabled' => false,
            'lead_notification_email' => '',

            // White label
            'white_label_enabled' => false,
            'hide_powered_by' => false,
            'custom_css' => '',

            // Webhook
            'webhook_enabled' => false,
            'webhook_url' => '',
            'webhook_secret' => '',
            'webhook_events' => [
                'new_conversation' => true,
                'new_message' => true,
                'lead_captured' => true,
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
            'handoff_email' => '',
            'handoff_message' => __('I understand this may need human assistance. A support representative will contact you soon.', 'rapls-ai-chatbot'),

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

            'realtime_monitor_enabled' => false,
            'monitor_refresh_interval' => 10,

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

            // Offline Messages
            'offline_message_enabled' => false,
            'offline_form_title' => __('We are currently offline', 'rapls-ai-chatbot'),
            'offline_form_description' => __('Please leave a message and we will get back to you.', 'rapls-ai-chatbot'),
            'offline_notification_email' => '',
            'offline_notification_enabled' => false,
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
     * Stub: Check enhanced rate limit
     */
    public function check_enhanced_rate_limit(?string $ip = null): array {
        return ['blocked' => false, 'message' => ''];
    }

    /**
     * Stub: Get rate limit message
     */
    public function get_rate_limit_message(): string {
        return __('Too many messages. Please wait a moment before sending again.', 'rapls-ai-chatbot');
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
     * Stub: Get user context
     */
    public function get_user_context(string $user_id): array {
        return [];
    }

    /**
     * Stub: Save user context
     */
    public function save_user_context(string $user_id, array $context): bool {
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
     * Stub: Validate image
     */
    public function validate_image(array $file) {
        return new WP_Error('multimodal_disabled', __('Image upload requires Pro.', 'rapls-ai-chatbot'));
    }

    /**
     * Stub: Process image for AI
     */
    public function process_image_for_ai(string $file_path): string {
        return '';
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
}
