<?php
/**
 * Humanizer Rules — static data for the B-layer detector (Japanese AI-smell).
 *
 * Vocabulary, phrase lists, regular expressions, and scoring weights live here
 * so the detector stays logic-only. Every list is filterable, so site owners
 * can tune detection without touching code. Nothing here performs I/O.
 *
 * Categories mirror the humanizer-ja item set (detection-only subset). The
 * plugin's own "glossary" is a proper-noun *protection* list (terms to keep),
 * which is the opposite of a banned list, so it is intentionally NOT reused.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Humanizer_Rules {

    /** Hard cap on input length scored, to bound regex cost (ReDoS guard). */
    const MAX_INPUT_CHARS = 50000;

    /** Per category, cap how many matched substrings we keep for highlighting. */
    const MAX_MATCHES_PER_CATEGORY = 50;

    /**
     * Banned / over-emphatic / flowery vocabulary (items 8/13/20).
     * Dictionary match, counted. Filterable.
     *
     * @return string[]
     */
    public static function banned_vocab(): array {
        $words = [
            // 過剰強調・誇張
            '圧倒的', '究極', '革命的', '劇的に', '飛躍的', '最先端', '唯一無二',
            '他にはない', '間違いなく', '確実に', '必ず', '絶対に',
            // 美辞麗句・空疎な称揚
            '魅力的', '素晴らしい', '優れた', 'パワフル', 'シームレス', '洗練された',
            '画期的', '充実した', '豊富な', '多彩な', '幅広い',
            // AI が好む抽象語
            '活用', '実現', '提供', '構築', '最適化', '効率化', '促進', '強化',
        ];
        return array_values(array_unique(apply_filters('rapls_humanizer_banned_vocab', $words)));
    }

    /**
     * Paragraph-initial connectives (item 7) — counted when they pile up.
     *
     * @return string[]
     */
    public static function connectives(): array {
        return apply_filters('rapls_humanizer_connectives', [
            'また', 'さらに', '加えて', 'したがって', '一方で', 'しかしながら',
            'このように', 'そのため', 'つまり', 'なお', 'ただし',
        ]);
    }

    /**
     * Vague-authority phrases (item 18).
     *
     * @return string[]
     */
    public static function authority_phrases(): array {
        return apply_filters('rapls_humanizer_authority_phrases', [
            '専門家によると', '専門家は', '多くの研究', '研究によると',
            '業界関係者の間では', '一般的に言われ', 'と言われています', 'とされています',
        ]);
    }

    /**
     * "Challenges and outlook" template phrases (item 19).
     *
     * @return string[]
     */
    public static function outlook_phrases(): array {
        return apply_filters('rapls_humanizer_outlook_phrases', [
            '課題はあるものの', '今後の発展が期待', '今後の展開が', 'さらなる進化が',
            '将来的には', '期待が高まっています', '注目されています',
        ]);
    }

    /**
     * Preachy preamble phrases (item 27).
     *
     * @return string[]
     */
    public static function preamble_phrases(): array {
        return apply_filters('rapls_humanizer_preamble_phrases', [
            'ここで重要なのは', '注意すべき点として', '理解しておく必要があります',
            '押さえておきたいのは', '忘れてはならないのは', '大切なのは',
        ]);
    }

    /**
     * Excessive hedging phrases (item 30).
     *
     * @return string[]
     */
    public static function hedging_phrases(): array {
        return apply_filters('rapls_humanizer_hedging_phrases', [
            'かもしれません', '可能性があります', 'と考えられます', 'ではないでしょうか',
            'と言えるでしょう', 'かもしれない', 'と思われます',
        ]);
    }

    /**
     * Flowery / over-praise opening phrases (item 29-ish, detection only).
     *
     * @return string[]
     */
    public static function flattery_phrases(): array {
        return apply_filters('rapls_humanizer_flattery_phrases', [
            '素晴らしいご質問', 'おっしゃる通り', 'いい質問ですね', 'ご指摘の通り',
        ]);
    }

    /**
     * Default per-category scoring weights. Filterable.
     *
     * @return array<string,int>
     */
    public static function weights(): array {
        $w = [
            'koreniyori'   => 8, // これにより
            'banned'       => 5, // 禁止語彙・過剰強調・美辞麗句
            'dakedenaku'   => 4, // だけでなく
            'meaning'      => 5, // 意義づけの付け足し
            'negparallel'  => 4, // 否定並列
            'hedging'      => 3, // 過剰ヘッジング
            'connective'   => 3, // 接続詞連打
            'katakana'     => 3, // カタカナ連続
            'monotone'     => 4, // 文末の単調さ
            'authority'    => 4, // 曖昧な権威づけ
            'outlook'      => 4, // 課題と展望テンプレ
            'preamble'     => 3, // 説教くさい前置き
            'colon'        => 2, // コロン多用
            'flattery'     => 4, // 過剰な共感・お世辞
        ];
        return apply_filters('rapls_humanizer_score_weights', $w);
    }

    /**
     * Human-readable category labels for the UI (translated at call time).
     *
     * @return array<string,string>
     */
    public static function category_labels(): array {
        return [
            'koreniyori'  => __('"これにより"', 'rapls-ai-chatbot'),
            'banned'      => __('Banned / over-emphatic vocabulary', 'rapls-ai-chatbot'),
            'dakedenaku'  => __('"だけでなく"', 'rapls-ai-chatbot'),
            'meaning'     => __('Tacked-on significance', 'rapls-ai-chatbot'),
            'negparallel' => __('Negative parallelism', 'rapls-ai-chatbot'),
            'hedging'     => __('Excessive hedging', 'rapls-ai-chatbot'),
            'connective'  => __('Connective pile-up', 'rapls-ai-chatbot'),
            'katakana'    => __('Long katakana runs', 'rapls-ai-chatbot'),
            'monotone'    => __('Monotone sentence endings', 'rapls-ai-chatbot'),
            'authority'   => __('Vague authority', 'rapls-ai-chatbot'),
            'outlook'     => __('"Challenges & outlook" template', 'rapls-ai-chatbot'),
            'preamble'    => __('Preachy preamble', 'rapls-ai-chatbot'),
            'colon'       => __('Colon overuse', 'rapls-ai-chatbot'),
            'flattery'    => __('Over-praise / flattery', 'rapls-ai-chatbot'),
        ];
    }
}
