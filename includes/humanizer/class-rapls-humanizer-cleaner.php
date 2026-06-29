<?php
/**
 * Humanizer Cleaner — A-layer: safe, deterministic AI-smell removal.
 *
 * Unlike the chatbot reply path (where we never rewrite output), this layer is
 * meant for *authored* content the user reviews before publishing — drafts and
 * improvements produced by the editor AI sidebar. It performs only conservative,
 * idempotent rewrites and never invents facts.
 *
 * Improvements over a naive rule pass (the reason this is not humanizer-ja
 * applied verbatim):
 *   - Fenced code blocks, inline code, and URLs are masked before any rewrite
 *     and restored afterwards, so code/links are never corrupted.
 *   - Every rule is idempotent: running clean() twice yields the same result.
 *   - Japanese-gated: non-Japanese text is returned unchanged.
 *   - Intentional **bold** is preserved; only the "**Label:** " decoration form
 *     at line start is unwrapped.
 *
 * Zero network I/O.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Humanizer_Cleaner {

    /** Decorative emoji removed only at line / list-item start. */
    const DECO_EMOJI = '✅|💡|🚀|✨|📌|🎯|🔥|👉|⭐|🎉|📝|⚡|🌟|🔑|📊|🛠️|🔧';

    /**
     * Clean a piece of authored Japanese text. Returns the input unchanged when
     * it is not Japanese or is empty. Always idempotent.
     */
    public function clean(string $text): string {
        if (trim($text) === '') {
            return $text;
        }
        if (!$this->is_japanese($text)) {
            return $text;
        }

        // Mask code/URLs so transformations cannot touch them.
        $store = [];
        $text  = $this->mask($text, $store);

        // Emoji first: a leading "✅ **Label:**" must lose the emoji before the
        // label-decoration rule can see "**" at the line start.
        $text = $this->strip_deco_emoji($text);          // 行頭装飾絵文字
        $text = $this->strip_label_decoration($text);   // **ラベル:** → ラベル外し
        $text = $this->normalize_dashes($text);          // ——挿入句—— → （…）
        $text = $this->shorten_dekiru($text);            // することができます → できます
        $text = $this->delete_residue_sentences($text);  // 定型残骸の削除
        $text = $this->collapse_whitespace($text);       // 連続空白・空行の正規化

        // Restore masked spans.
        $text = $this->unmask($text, $store);

        return $text;
    }

    /**
     * Whether the text is substantially Japanese.
     */
    private function is_japanese(string $text): bool {
        if (!preg_match_all('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text, $m)) {
            return false;
        }
        $jp  = count($m[0]);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        return $len > 0 && $jp >= 8 && ($jp / $len) >= 0.10;
    }

    /**
     * Replace fenced code blocks, inline code, and URLs with placeholders.
     *
     * @param array<string,string> $store Placeholder => original (by reference).
     */
    private function mask(string $text, array &$store): string {
        $i = 0;
        $mask = function ($m) use (&$store, &$i) {
            $key = "\x01RAPLSMASK" . $i . "\x02";
            $store[$key] = $m[0];
            $i++;
            return $key;
        };
        // Fenced code blocks ```...``` (non-greedy, bounded by closing fence).
        $text = preg_replace_callback('/```.*?```/su', $mask, $text);
        // Inline code `...` (single line).
        $text = preg_replace_callback('/`[^`\n]+`/u', $mask, $text);
        // URLs.
        $text = preg_replace_callback('#https?://[^\s<>"\')\]]+#u', $mask, $text);
        return $text;
    }

    /**
     * Restore masked spans.
     *
     * @param array<string,string> $store
     */
    private function unmask(string $text, array $store): string {
        if (empty($store)) {
            return $text;
        }
        return strtr($text, $store);
    }

    /**
     * Strip the "**Label:** content" decoration at line start (item 3).
     * Preserves intentional inline **bold** elsewhere.
     */
    private function strip_label_decoration(string $text): string {
        // Match a colon-bearing label only, so colon-less **bold** (intentional
        // emphasis) is preserved. Handles both "**ラベル:**" (colon inside the
        // bold) and "**ラベル**:" (colon outside).
        return preg_replace(
            '/^(\s*(?:[-*]\s*)?)\*\*([^*\n:：]{1,20})(?:[:：]\*\*|\*\*[:：])\s*/mu',
            '$1$2: ',
            $text
        );
    }

    /**
     * Remove decorative emoji at line / list-item start (item 4).
     */
    private function strip_deco_emoji(string $text): string {
        return preg_replace(
            '/^(\s*(?:[-*]\s*)?)(?:' . self::DECO_EMOJI . ')\s*/mu',
            '$1',
            $text
        );
    }

    /**
     * Paired full-width dashes used as an inserted clause → parentheses (item 2).
     * Single / sentence-final dashes are left alone (meaning may change).
     */
    private function normalize_dashes(string $text): string {
        $text = preg_replace('/——([^—\n]{1,60})——/u', '（$1）', $text);
        $text = preg_replace('/―([^―\n]{1,60})―/u', '（$1）', $text);
        return $text;
    }

    /**
     * Shorten the verbose "することができます" family (item 12).
     */
    private function shorten_dekiru(string $text): string {
        $text = preg_replace('/することができ(ます|る|ない|た|なかった|なくなる)/u', 'でき$1', $text);
        $text = preg_replace('/することが可能(?:です|だ)/u', 'できます', $text);
        $text = preg_replace('/することが可能になる/u', 'できるようになる', $text);
        return $text;
    }

    /**
     * Delete template-residue sentences (items 28/29/31). A sentence containing
     * any residue phrase is removed up to (and including) its full stop.
     */
    private function delete_residue_sentences(string $text): string {
        $phrases = apply_filters('rapls_humanizer_residue_phrases', [
            // チャットボットの残骸（28）
            'ご参考になれば幸いです',
            'ご不明な点がございましたら',
            'お気軽に[^。]*お申し付けください',
            'お気軽に[^。]*ご相談ください',
            '以上が[^。]*概要です',
            'ご質問があれば',
            // 過剰な共感・お世辞（29）
            '素晴らしいご質問ですね',
            'おっしゃる通り',
            // テンプレ結論の定型句（31）
            'いかがでし(?:た|たでしょう)か',
            'まとめると、本記事では',
        ]);

        foreach ($phrases as $needle) {
            // Remove the whole sentence (up to the next 。 or end) that contains it.
            $pattern = '/[^。\n]*(?:' . $needle . ')[^。]*。?/u';
            $text = preg_replace($pattern, '', $text);
        }
        return $text;
    }

    /**
     * Normalise leftover whitespace: doubled punctuation, runs of spaces, and
     * 3+ consecutive newlines. Also drop the unnatural half-width space between
     * two Japanese characters (item 2) — safe because code/URLs are masked.
     */
    private function collapse_whitespace(string $text): string {
        // Unnatural space between Japanese chars.
        $text = preg_replace(
            '/([\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}])[ \t]+([\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}])/u',
            '$1$2',
            $text
        );
        $text = preg_replace('/。\s*。/u', '。', $text);  // doubled full stops
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text); // runs of spaces
        $text = preg_replace('/\n{3,}/u', "\n\n", $text); // 3+ blank lines
        $text = preg_replace('/[ \t]+\n/u', "\n", $text); // trailing spaces
        return trim($text);
    }
}
