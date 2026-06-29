<?php
/**
 * Humanizer Detector — B-layer: detect Japanese "AI-smell" and score it.
 *
 * DETECTION ONLY. This class never rewrites the text and never performs any
 * network I/O. It counts pattern hits per category, normalises them by length,
 * and produces a 0–100 score plus per-category breakdown for the UI.
 *
 * ReDoS safety: every regex is linear (no nested quantifiers); lazy quantifiers
 * are always bounded by an explicit delimiter; input is hard-capped before any
 * matching runs.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Humanizer_Detector {

    /**
     * Analyse a piece of text and return a Result.
     */
    public function analyze(string $text): RAPLSAICH_Humanizer_Result {
        $result = new RAPLSAICH_Humanizer_Result();

        $text = (string) $text;
        if (trim($text) === '') {
            $result->skipped     = true;
            $result->skip_reason = 'empty';
            return $result;
        }

        // Length guard: cap before any regex runs.
        $full_len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($full_len > RAPLSAICH_Humanizer_Rules::MAX_INPUT_CHARS) {
            $text = function_exists('mb_substr')
                ? mb_substr($text, 0, RAPLSAICH_Humanizer_Rules::MAX_INPUT_CHARS)
                : substr($text, 0, RAPLSAICH_Humanizer_Rules::MAX_INPUT_CHARS);
            $result->warnings[] = __('Text is long; only the beginning was scored.', 'rapls-ai-chatbot');
        }

        // Japanese gate: the rules are Japanese-specific. Skip non-Japanese text
        // so English/other-language replies are not falsely scored.
        if (!$this->is_japanese($text)) {
            $result->skipped     = true;
            $result->skip_reason = 'not_japanese';
            return $result;
        }

        $char_count = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        $hits = [];
        $this->detect_substring($hits, $text, 'koreniyori', ['これにより']);
        $this->detect_substring($hits, $text, 'dakedenaku', ['だけでなく']);
        $this->detect_list($hits, $text, 'banned', RAPLSAICH_Humanizer_Rules::banned_vocab());
        $this->detect_list($hits, $text, 'connective', RAPLSAICH_Humanizer_Rules::connectives());
        $this->detect_list($hits, $text, 'authority', RAPLSAICH_Humanizer_Rules::authority_phrases());
        $this->detect_list($hits, $text, 'outlook', RAPLSAICH_Humanizer_Rules::outlook_phrases());
        $this->detect_list($hits, $text, 'preamble', RAPLSAICH_Humanizer_Rules::preamble_phrases());
        $this->detect_list($hits, $text, 'hedging', RAPLSAICH_Humanizer_Rules::hedging_phrases());
        $this->detect_list($hits, $text, 'flattery', RAPLSAICH_Humanizer_Rules::flattery_phrases());

        // Tacked-on significance (item 14): "Xを浮き彫りにしている/示しており/…".
        $this->detect_regex($hits, $text, 'meaning',
            '/(?:浮き彫りにして|示して|物語って|裏付けて|象徴して)(?:いる|おり|います)/u');

        // Negative parallelism (item 22): "Xではない。Yだ。"
        $this->detect_regex($hits, $text, 'negparallel',
            '/では(?:ない|ありません)。[^。]{1,40}(?:だ|です)。/u');

        // Colon overuse (item 1): full-width colons.
        $this->detect_regex($hits, $text, 'colon', '/：/u');

        // Long katakana runs (item 10): 6+ katakana, or 3+ ・-joined katakana words.
        $kata = 0;
        $kata_matches = [];
        if (preg_match_all('/[\x{30A0}-\x{30FF}ー]{6,}/u', $text, $m1)) {
            $kata += count($m1[0]);
            $kata_matches = array_merge($kata_matches, $m1[0]);
        }
        if (preg_match_all('/(?:[\x{30A0}-\x{30FF}ー]+・){2,}[\x{30A0}-\x{30FF}ー]+/u', $text, $m2)) {
            $kata += count($m2[0]);
            $kata_matches = array_merge($kata_matches, $m2[0]);
        }
        if ($kata > 0) {
            $hits['katakana'] = [
                'count'   => $kata,
                'matches' => $this->cap_matches($kata_matches),
            ];
        }

        // Monotone sentence endings (item 11): if the most common 2-char ending
        // makes up > 60% of sentences (and there are enough sentences).
        $this->detect_monotone($hits, $text);

        // Non-blocking warning: sparse proper nouns / lived experience (item 25).
        // Detection only — never auto-completed. Heuristic: digits + 「…」 quoted
        // terms per 1000 chars below a threshold.
        $this->maybe_warn_sparse_specifics($result, $text, $char_count);

        $result->hits  = $hits;
        $result->score = $this->score($hits, $char_count);
        $result->level = $this->level_for($result->score);

        return $result;
    }

    /**
     * Whether the text is substantially Japanese (has hiragana/katakana/kanji
     * in a meaningful proportion). Cheap, single pass.
     */
    private function is_japanese(string $text): bool {
        if (!preg_match_all('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text, $m)) {
            return false;
        }
        $jp  = count($m[0]);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len <= 0) {
            return false;
        }
        // At least 10% Japanese characters, and at least 8 of them.
        return $jp >= 8 && ($jp / $len) >= 0.10;
    }

    /**
     * Count plain substring occurrences (no regex).
     *
     * @param array<string,array> $hits  Hit accumulator (by reference).
     * @param string[]            $needles
     */
    private function detect_substring(array &$hits, string $text, string $cat, array $needles): void {
        $count   = 0;
        $matches = [];
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            $n = substr_count($text, $needle);
            if ($n > 0) {
                $count    += $n;
                $matches[] = $needle;
            }
        }
        if ($count > 0) {
            $hits[$cat] = ['count' => $count, 'matches' => $this->cap_matches($matches)];
        }
    }

    /**
     * Count occurrences of any phrase in a dictionary list (plain substring).
     *
     * @param array<string,array> $hits
     * @param string[]            $list
     */
    private function detect_list(array &$hits, string $text, string $cat, array $list): void {
        $this->detect_substring($hits, $text, $cat, $list);
    }

    /**
     * Count regex matches for a category.
     *
     * @param array<string,array> $hits
     */
    private function detect_regex(array &$hits, string $text, string $cat, string $pattern): void {
        if (preg_match_all($pattern, $text, $m)) {
            $hits[$cat] = ['count' => count($m[0]), 'matches' => $this->cap_matches($m[0])];
        }
    }

    /**
     * Detect monotone sentence endings.
     *
     * @param array<string,array> $hits
     */
    private function detect_monotone(array &$hits, string $text): void {
        // Split on Japanese full stop; keep non-empty trimmed sentences.
        $parts = preg_split('/。/u', $text);
        if (!is_array($parts)) {
            return;
        }
        $endings = [];
        $total   = 0;
        foreach ($parts as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }
            $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
            if ($len < 2) {
                continue;
            }
            $end = function_exists('mb_substr') ? mb_substr($s, -2) : substr($s, -2);
            $endings[$end] = ($endings[$end] ?? 0) + 1;
            $total++;
        }
        if ($total < 4) {
            return; // too few sentences to judge
        }
        arsort($endings);
        $top_ending = (string) array_key_first($endings);
        $top        = (int) reset($endings);
        if ($top / $total > 0.60) {
            $hits['monotone'] = [
                'count'   => 1,
                'matches' => [$top_ending],
            ];
        }
    }

    /**
     * Add a non-blocking warning when concrete specifics look sparse.
     * Detection only — never modifies the text.
     */
    private function maybe_warn_sparse_specifics(RAPLSAICH_Humanizer_Result $result, string $text, int $char_count): void {
        if ($char_count < 400) {
            return; // too short to judge meaningfully
        }
        $digits = preg_match_all('/[0-9０-９]+/u', $text, $dm) ? count($dm[0]) : 0;
        $quoted = preg_match_all('/「[^」]{1,30}」/u', $text, $qm) ? count($qm[0]) : 0;
        $per_k  = max(1.0, $char_count / 1000);
        $specifics_per_k = ($digits + $quoted) / $per_k;
        if ($specifics_per_k < 2.0) {
            $result->warnings[] = __('Concrete specifics (numbers, named examples) look sparse. Not auto-completed.', 'rapls-ai-chatbot');
        }
    }

    /**
     * Compute the 0–100 score from weighted, length-normalised hit counts.
     *
     * @param array<string,array> $hits
     */
    private function score(array $hits, int $char_count): int {
        $weights = RAPLSAICH_Humanizer_Rules::weights();
        $per_k   = $char_count > 0 ? $char_count / 1000 : 1;
        $raw     = 0.0;
        foreach ($hits as $cat => $info) {
            $w = (int) ($weights[$cat] ?? 0);
            $raw += $w * (int) ($info['count'] ?? 0);
        }
        return (int) min(100, round($raw / max(1, $per_k)));
    }

    /**
     * Map a score to a traffic-light level.
     */
    private function level_for(int $score): string {
        if ($score <= 25) {
            return 'green';
        }
        if ($score <= 45) {
            return 'yellow';
        }
        return 'red';
    }

    /**
     * Cap and de-duplicate the matched-substring list kept for highlighting.
     *
     * @param string[] $matches
     * @return string[]
     */
    private function cap_matches(array $matches): array {
        $matches = array_values(array_unique($matches));
        if (count($matches) > RAPLSAICH_Humanizer_Rules::MAX_MATCHES_PER_CATEGORY) {
            $matches = array_slice($matches, 0, RAPLSAICH_Humanizer_Rules::MAX_MATCHES_PER_CATEGORY);
        }
        return $matches;
    }
}
