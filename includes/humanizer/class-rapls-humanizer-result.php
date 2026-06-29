<?php
/**
 * Humanizer Result — value object returned by the B-layer detector.
 *
 * Pure data; no behaviour beyond simple accessors. Holds the AI-smell score,
 * the per-category hits, and any non-blocking warnings.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Humanizer_Result {

    /** @var int 0–100, higher = more "AI-smell". */
    public int $score = 0;

    /** @var string One of 'green' | 'yellow' | 'red'. */
    public string $level = 'green';

    /**
     * @var array<string,array{count:int,matches:string[]}>
     * Per-category hits. Key is the category id (see Rules); value carries the
     * occurrence count and a capped list of matched substrings (for highlight).
     */
    public array $hits = [];

    /** @var string[] Non-blocking notices (e.g. "proper nouns are sparse"). */
    public array $warnings = [];

    /** @var bool True when the text was not scored (e.g. not Japanese, empty). */
    public bool $skipped = false;

    /** @var string Reason for skipping, when $skipped is true. */
    public string $skip_reason = '';

    /**
     * Total number of hits across all categories.
     */
    public function total_hits(): int {
        $n = 0;
        foreach ($this->hits as $info) {
            $n += (int) ($info['count'] ?? 0);
        }
        return $n;
    }

    /**
     * Array shape suitable for JSON responses / wp_localize_script.
     *
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'score'    => $this->score,
            'level'    => $this->level,
            'hits'     => $this->hits,
            'warnings' => $this->warnings,
            'skipped'  => $this->skipped,
        ];
    }
}
