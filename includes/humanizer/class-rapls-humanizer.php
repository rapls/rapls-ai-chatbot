<?php
/**
 * Humanizer facade — public entry point for AI-smell analysis.
 *
 * B-layer only: detection and scoring. There is intentionally NO clean()/process()
 * that rewrites text — visitor-facing chatbot replies are never altered. The
 * facade exposes analyze() and a couple of helpers used by the admin UI.
 *
 * Zero network I/O. Safe to call on the request path; cheap for short replies.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Humanizer {

    private RAPLSAICH_Humanizer_Detector $detector;
    private ?RAPLSAICH_Humanizer_Cleaner $cleaner = null;

    public function __construct() {
        $this->detector = new RAPLSAICH_Humanizer_Detector();
    }

    private function cleaner(): RAPLSAICH_Humanizer_Cleaner {
        if ($this->cleaner === null) {
            $this->cleaner = new RAPLSAICH_Humanizer_Cleaner();
        }
        return $this->cleaner;
    }

    /**
     * Whether the B-layer is enabled. Off unless explicitly turned on, and
     * overridable via the rapls_humanizer_enabled filter.
     */
    public static function is_enabled(): bool {
        $settings = get_option('raplsaich_settings', []);
        $enabled  = !empty($settings['humanizer_enabled']);
        return (bool) apply_filters('rapls_humanizer_enabled', $enabled);
    }

    /**
     * Configured score threshold (above which the text is flagged).
     */
    public static function threshold(): int {
        $settings = get_option('raplsaich_settings', []);
        $t = isset($settings['humanizer_threshold']) ? (int) $settings['humanizer_threshold'] : 40;
        $t = (int) apply_filters('rapls_humanizer_score_threshold', $t);
        return max(0, min(100, $t));
    }

    /**
     * Analyse a piece of text. Always returns a Result (never throws); a Result
     * with skipped=true means the text was not scored (empty / not Japanese).
     */
    public function analyze(string $text): RAPLSAICH_Humanizer_Result {
        return $this->detector->analyze($text);
    }

    /**
     * A-layer: return a cleaned copy of authored Japanese text (safe,
     * deterministic, idempotent). Non-Japanese / empty text is returned as-is.
     * Never alters facts. Intended for editor-generated content the user reviews.
     */
    public function clean(string $text): string {
        return $this->cleaner()->clean($text);
    }

    /**
     * Convenience for the editor: clean the text and score both before/after.
     *
     * @return array{cleaned:string,changed:bool,score_before:int,score_after:int}
     */
    public function refine(string $text): array {
        $cleaned = $this->clean($text);
        $before  = $this->analyze($text);
        $after   = $this->analyze($cleaned);
        return [
            'cleaned'      => $cleaned,
            'changed'      => ($cleaned !== $text),
            'score_before' => $before->skipped ? 0 : $before->score,
            'score_after'  => $after->skipped ? 0 : $after->score,
        ];
    }
}
