<?php
/**
 * Search engine class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Search_Engine {

    /**
     * Cached schema info to avoid repeated schema queries per request
     */
    private static array $schema_cache = [];

    /**
     * Cached FULLTEXT index availability (per request)
     */
    private static ?bool $has_fulltext_index = null;

    /**
     * Table name
     */
    private function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'aichat_index';
    }

    /**
     * Knowledge table name
     */
    private function get_knowledge_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aichat_knowledge';
    }

    /**
     * Get knowledge table schema info (cached per request)
     *
     * @return array{exists: bool, has_priority: bool, has_is_active: bool}
     */
    private function get_knowledge_schema(): array {
        $table = $this->get_knowledge_table();
        if (isset(self::$schema_cache[$table])) {
            return self::$schema_cache[$table];
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        // If knowledge schema migration has completed, columns are guaranteed to exist
        $schema_version = (int) get_option('wpaic_knowledge_schema_version', 0);
        $has_priority  = $table_exists && $schema_version >= 2;
        $has_is_active = $table_exists && $schema_version >= 2;

        // Fallback: check columns directly if migration hasn't run yet
        if ($table_exists && $schema_version < 2) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $has_priority = !empty($wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'priority'"));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $has_is_active = !empty($wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'is_active'"));
        }

        self::$schema_cache[$table] = [
            'exists'        => $table_exists,
            'has_priority'  => $has_priority,
            'has_is_active' => $has_is_active,
        ];

        return self::$schema_cache[$table];
    }

    /**
     * Search related content
     */
    public function search(string $query, int $limit = 3): array {
        $results = [];

        // Always get knowledge (regardless of keywords)
        // Get more and narrow down by limit at the end
        $knowledge_limit = max($limit * 2, 5);
        $priority_knowledge = $this->get_high_priority_knowledge($knowledge_limit);
        $results = array_merge($results, $priority_knowledge);

        // Search knowledge base by keywords (additional match)
        $knowledge_results = $this->search_knowledge($query, $limit);
        // Merge excluding duplicates (keyword match gets higher score)
        foreach ($knowledge_results as $kr) {
            $exists = false;
            foreach ($results as &$r) {
                if ($r['type'] === 'knowledge' && $r['title'] === $kr['title']) {
                    // Update score if already exists (keyword match bonus)
                    $r['score'] = max($r['score'], $kr['score']);
                    $exists = true;
                    break;
                }
            }
            unset($r);
            if (!$exists) {
                $results[] = $kr;
            }
        }

        // Search from index
        $index_results = $this->search_index($query, $limit);
        $results = array_merge($results, $index_results);

        // Sort by score and return top results
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // Include at least 1 knowledge item
        $final_results = array_slice($results, 0, max($limit, 5));

        // Force add knowledge if not included in results
        $has_knowledge = false;
        foreach ($final_results as $r) {
            if ($r['type'] === 'knowledge') {
                $has_knowledge = true;
                break;
            }
        }

        if (!$has_knowledge && !empty($priority_knowledge)) {
            // Add first knowledge
            array_unshift($final_results, $priority_knowledge[0]);
        }

        return $final_results;
    }

    /**
     * Search knowledge/FAQ only (no site index)
     * Used when message limit is reached to provide FAQ-based answers
     */
    public function search_knowledge_only(string $query, int $limit = 5): array {
        $results = [];

        // Get high priority knowledge
        $priority_knowledge = $this->get_high_priority_knowledge($limit);
        $results = array_merge($results, $priority_knowledge);

        // Search knowledge base by keywords
        $knowledge_results = $this->search_knowledge($query, $limit);
        foreach ($knowledge_results as $kr) {
            $exists = false;
            foreach ($results as &$r) {
                if ($r['type'] === 'knowledge' && $r['title'] === $kr['title']) {
                    $r['score'] = max($r['score'], $kr['score']);
                    $exists = true;
                    break;
                }
            }
            unset($r);
            if (!$exists) {
                $results[] = $kr;
            }
        }

        // Sort by score and filter low-quality matches
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_filter($results, fn($r) => $r['score'] >= 15);

        return array_slice(array_values($results), 0, $limit);
    }

    /**
     * Get high priority knowledge (always referenced regardless of keywords)
     * Prioritize knowledge with priority > 0, otherwise get latest knowledge
     */
    private function get_high_priority_knowledge(int $limit): array {
        global $wpdb;
        $table = $this->get_knowledge_table();
        $schema = $this->get_knowledge_schema();

        if (!$schema['exists']) {
            return [];
        }

        $has_priority = $schema['has_priority'];
        $has_is_active = $schema['has_is_active'];

        $active_condition = $has_is_active ? 'WHERE is_active = 1' : '';
        $priority_column = $has_priority ? 'priority' : '0 as priority';
        $order_by = $has_priority ? 'ORDER BY priority DESC, created_at DESC' : 'ORDER BY created_at DESC';

        // Get high priority knowledge (including priority 0 = all active knowledge)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and column names are safe internal values
        $sql = $wpdb->prepare(
            "SELECT id, title, content, category, {$priority_column}
             FROM {$table}
             {$active_condition}
             {$order_by}
             LIMIT %d",
            $limit
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (empty($results)) {
            return [];
        }

        return array_map(function($item) {
            $priority = (int) ($item['priority'] ?? 0);
            $priority_boost = $priority * 20;
            // Priority 0 also has base score 50 (lower than keyword match, but always referenced)
            $base_score = $priority > 0 ? 100 : 50;
            return [
                'type'     => 'knowledge',
                'title'    => $item['title'],
                'content'  => $item['content'],
                'category' => $item['category'] ?? '',
                'priority' => $priority,
                'url'      => null,
                'score'    => $base_score + $priority_boost,
            ];
        }, $results);
    }

    /**
     * Search index
     */
    private function search_index(string $query, int $limit): array {
        global $wpdb;
        $table = $this->get_table_name();

        // Extract keywords
        $keywords = $this->extract_keywords($query);

        if (empty($keywords)) {
            return [];
        }

        // Try FULLTEXT search
        $fulltext_results = $this->fulltext_search($table, $query, $limit);
        if (!empty($fulltext_results)) {
            return $fulltext_results;
        }

        // Fallback: LIKE search
        return $this->like_search($table, $keywords, $limit);
    }

    /**
     * Check if FULLTEXT index exists on the index table
     */
    private function has_fulltext_index(string $table): bool {
        if (self::$has_fulltext_index !== null) {
            return self::$has_fulltext_index;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Index_type = 'FULLTEXT'", ARRAY_A);
        self::$has_fulltext_index = !empty($indexes);

        return self::$has_fulltext_index;
    }

    /**
     * FULLTEXT search
     */
    private function fulltext_search(string $table, string $query, int $limit): array {
        global $wpdb;

        // Skip FULLTEXT if index doesn't exist (e.g. MariaDB version mismatch, InnoDB limitations)
        if (!$this->has_fulltext_index($table)) {
            return [];
        }

        $search_query = $this->prepare_fulltext_query($query);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value
        $sql = $wpdb->prepare(
            "SELECT post_id, title, content, url,
                    MATCH(title, content) AGAINST(%s IN NATURAL LANGUAGE MODE) as score
             FROM {$table}
             WHERE MATCH(title, content) AGAINST(%s IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC
             LIMIT %d",
            $search_query,
            $search_query,
            $limit * 2  // Get more considering duplicates
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($sql, ARRAY_A);

        // If FULLTEXT query caused a DB error, disable for this request and fall back to LIKE
        if ($results === null && !empty($wpdb->last_error)) {
            self::$has_fulltext_index = false;
            return [];
        }

        // Group by post_id (combine multiple chunks of same post)
        $grouped = $this->group_by_post($results);

        return array_map(function($item) {
            return [
                'type'    => 'index',
                'title'   => $item['title'],
                'content' => $item['content'],
                'url'     => $item['url'],
                'score'   => (float) $item['score'],
            ];
        }, array_slice($grouped, 0, $limit));
    }

    /**
     * LIKE search (fallback)
     */
    private function like_search(string $table, array $keywords, int $limit): array {
        global $wpdb;

        $where_clauses = [];
        $params = [];

        foreach ($keywords as $keyword) {
            $where_clauses[] = "(title LIKE %s OR content LIKE %s)";
            $like_param = '%' . $wpdb->esc_like($keyword) . '%';
            $params[] = $like_param;
            $params[] = $like_param;
        }

        if (empty($where_clauses)) {
            return [];
        }

        $where = implode(' OR ', $where_clauses);
        $params[] = $limit * 2;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE clauses are safe internal values
        $sql = $wpdb->prepare(
            "SELECT post_id, title, content, url, 1 as score
             FROM {$table}
             WHERE {$where}
             LIMIT %d",
            ...$params
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($sql, ARRAY_A);
        $grouped = $this->group_by_post($results);

        // Calculate score by keyword match count
        foreach ($grouped as &$item) {
            $item['score'] = $this->calculate_keyword_score($item, $keywords);
        }

        usort($grouped, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(function($item) {
            return [
                'type'    => 'index',
                'title'   => $item['title'],
                'content' => $item['content'],
                'url'     => $item['url'],
                'score'   => $item['score'],
            ];
        }, array_slice($grouped, 0, $limit));
    }

    /**
     * Search knowledge base
     */
    private function search_knowledge(string $query, int $limit): array {
        global $wpdb;
        $table = $this->get_knowledge_table();
        $schema = $this->get_knowledge_schema();

        if (!$schema['exists']) {
            return [];
        }

        $has_priority = $schema['has_priority'];
        $has_is_active = $schema['has_is_active'];

        // Add filter only if is_active column exists
        $active_condition = $has_is_active ? 'is_active = 1 AND' : '';
        $priority_column = $has_priority ? 'priority' : '0 as priority';
        $order_by = $has_priority ? 'ORDER BY priority DESC' : '';

        $keywords = $this->extract_keywords($query);

        // Add original query to search (symbol removal only)
        $original_query = trim(preg_replace('/[、。！？「」『』（）\[\]【】・,.!?\'"：:；;]+/u', '', $query));
        if (wpaic_mb_strlen($original_query) >= 2 && !in_array($original_query, $keywords, true)) {
            array_unshift($keywords, $original_query);
        }

        if (empty($keywords)) {
            return [];
        }

        $where_clauses = [];
        $params = [];

        foreach ($keywords as $keyword) {
            if (wpaic_mb_strlen($keyword) < 2) {
                continue;
            }
            $where_clauses[] = "(title LIKE %s OR content LIKE %s)";
            $like_param = '%' . $wpdb->esc_like($keyword) . '%';
            $params[] = $like_param;
            $params[] = $like_param;
        }

        if (empty($where_clauses)) {
            return [];
        }

        $where = implode(' OR ', $where_clauses);
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and SQL parts are safe internal values
        $sql = $wpdb->prepare(
            "SELECT id, title, content, category, {$priority_column}
             FROM {$table}
             WHERE {$active_condition} ({$where})
             {$order_by}
             LIMIT %d",
            ...$params
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (empty($results)) {
            return [];
        }

        return array_map(function($item) use ($keywords) {
            // Priority adds significant boost (each priority point = 20 score)
            $priority_boost = ((int) ($item['priority'] ?? 0)) * 20;
            return [
                'type'     => 'knowledge',
                'title'    => $item['title'],
                'content'  => $item['content'],
                'category' => $item['category'] ?? '',
                'priority' => (int) ($item['priority'] ?? 0),
                'url'      => null,
                'score'    => $this->calculate_keyword_score($item, $keywords) + 10 + $priority_boost, // Prioritize knowledge + priority boost
            ];
        }, $results);
    }

    /**
     * Extract keywords
     */
    private function extract_keywords(string $text): array {
        // Remove symbols
        $text = preg_replace('/[、。！？「」『』（）\[\]【】・,.!?\'"：:；;]+/u', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return [];
        }

        // Japanese stopwords (remove as substring)
        $jp_stopwords = ['について', 'に関して', 'とは', 'ですか', 'ください', 'ありますか', 'でしょうか', 'ですが', 'ですね', 'ですよ', 'ません', 'ました', 'します', 'される', 'している', 'された', 'できる', 'できます', 'ありません', 'おしえて', '教えて', 'どうやって', 'どのように', 'なぜ', 'どこ', 'いつ', 'だれ', 'なに', '何'];

        // Japanese particles/auxiliaries (remove at word boundary)
        $jp_particles = ['の', 'は', 'が', 'を', 'に', 'で', 'と', 'も', 'や', 'へ', 'から', 'まで', 'より', 'など', 'って', 'だと', 'では', 'には', 'とか', 'けど', 'でも'];

        // English stopwords
        $en_stopwords = ['what', 'is', 'the', 'a', 'an', 'and', 'or', 'how', 'do', 'does', 'can', 'will', 'would', 'should', 'could', 'please', 'tell', 'me', 'about', 'know'];

        $keywords = [];

        // Remove Japanese stopwords
        $cleaned = $text;
        foreach ($jp_stopwords as $sw) {
            $cleaned = str_replace($sw, ' ', $cleaned);
        }

        // Split by spaces
        $words = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $word = trim($word);

            // Exclude particle-only words
            if (in_array($word, $jp_particles, true)) {
                continue;
            }

            // Exclude English stopwords
            if (in_array(wpaic_mb_strtolower($word), $en_stopwords, true)) {
                continue;
            }

            // Remove leading/trailing particles
            foreach ($jp_particles as $p) {
                if (wpaic_mb_strlen($word) > wpaic_mb_strlen($p)) {
                    // Remove trailing particle
                    if (wpaic_mb_substr($word, -wpaic_mb_strlen($p)) === $p) {
                        $word = wpaic_mb_substr($word, 0, -wpaic_mb_strlen($p));
                    }
                    // Remove leading particle
                    if (wpaic_mb_substr($word, 0, wpaic_mb_strlen($p)) === $p) {
                        $word = wpaic_mb_substr($word, wpaic_mb_strlen($p));
                    }
                }
            }

            // Add keywords with 2+ characters
            if (wpaic_mb_strlen($word) >= 2) {
                $keywords[] = $word;
            }
        }

        // If no keywords found, clean up and use original text
        if (empty($keywords)) {
            $cleaned = trim(preg_replace('/\s+/u', '', $cleaned));
            if (wpaic_mb_strlen($cleaned) >= 2) {
                $keywords[] = $cleaned;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Prepare query for FULLTEXT search
     */
    private function prepare_fulltext_query(string $query): string {
        $keywords = $this->extract_keywords($query);
        return implode(' ', $keywords);
    }

    /**
     * 投稿IDでグループ化
     */
    private function group_by_post(array $results): array {
        $grouped = [];

        foreach ($results as $item) {
            $post_id = $item['post_id'];

            if (!isset($grouped[$post_id])) {
                $grouped[$post_id] = $item;
            } else {
                // Combine content if another chunk of same post
                $grouped[$post_id]['content'] .= "\n" . $item['content'];
                // Use higher score
                if ($item['score'] > $grouped[$post_id]['score']) {
                    $grouped[$post_id]['score'] = $item['score'];
                }
            }
        }

        return array_values($grouped);
    }

    /**
     * キーワードマッチ数でスコア計算
     */
    private function calculate_keyword_score(array $item, array $keywords): float {
        $score = 0;
        $text = wpaic_mb_strtolower($item['title'] . ' ' . $item['content']);

        foreach ($keywords as $keyword) {
            $keyword_lower = wpaic_mb_strtolower($keyword);

            // Bonus if matched in title
            if (wpaic_mb_strpos(wpaic_mb_strtolower($item['title']), $keyword_lower) !== false) {
                $score += 3;
            }

            // Match count in body
            $count = wpaic_mb_substr_count($text, $keyword_lower);
            $score += min($count, 5); // Max 5 points
        }

        return $score;
    }

    /**
     * コンテキスト文字列を構築
     * max_length: 最大文字数（デフォルト50000文字 ≒ 約25000トークン）
     * 現代のLLMは128K〜1Mトークンに対応しているため、十分な余裕を持たせる
     */
    public function build_context(array $results, int $max_length = 50000, string $query = ''): string {
        if (empty($results)) {
            return '';
        }

        $context_parts = [];
        $current_length = 0;

        foreach ($results as $result) {
            $content = $result['content'];
            $priority = $result['priority'] ?? 0;
            $is_qa = $this->is_qa_format($content);

            // Check if Q&A format content and extract relevant Q&A
            if (!empty($query) && $is_qa) {
                $relevant_qa = $this->extract_relevant_qa($content, $query);
                if (!empty($relevant_qa)) {
                    $content = $relevant_qa;
                }
            }

            // Adjust content length based on priority
            // Highest priority(100): Full text (max 10000 chars)
            // High priority(75): Max 7000 chars
            // Medium priority(50): Max 5000 chars
            // Low priority(25): Max 3000 chars
            // Normal(0): Max 2000 chars
            if ($priority >= 100) {
                $content_limit = 10000;
            } elseif ($priority >= 75) {
                $content_limit = 7000;
            } elseif ($priority >= 50) {
                $content_limit = 5000;
            } elseif ($priority >= 25) {
                $content_limit = 3000;
            } else {
                $content_limit = 2000;
            }
            $content = wpaic_mb_substr($content, 0, $content_limit);

            // Add "..." if truncated
            if (wpaic_mb_strlen($result['content']) > $content_limit && wpaic_mb_strlen($content) >= $content_limit) {
                $content .= '...';
            }

            // Simpler format for Q&A (for smaller models)
            if ($is_qa) {
                $part = $content;
            } else {
                // Add priority label
                $priority_label = $priority > 0 ? " [Priority: {$priority}]" : '';

                $part = sprintf(
                    "【%s%s】\n%s",
                    $result['title'],
                    $priority_label,
                    $content
                );

                if (!empty($result['url'])) {
                    $part .= sprintf("\n(Source: %s)", $result['url']);
                }
            }

            $part_length = wpaic_mb_strlen($part);

            if ($current_length + $part_length > $max_length) {
                break;
            }

            $context_parts[] = $part;
            $current_length += $part_length;
        }

        return implode("\n\n---\n\n", $context_parts);
    }

    /**
     * Check if content is Q&A format
     */
    private function is_qa_format(string $content): bool {
        // Check if Question: and Answer: patterns exist, or separated by ---
        return (
            preg_match('/Question\s*[:：]/ui', $content) && preg_match('/Answer\s*[:：]/ui', $content)
        ) || (
            preg_match('/Q\s*[:：]/u', $content) && preg_match('/A\s*[:：]/u', $content)
        ) || (
            strpos($content, '---') !== false && (
                preg_match('/[:：]\s*.+\n/u', $content)
            )
        );
    }

    /**
     * Extract relevant Q&A from Q&A format content
     */
    private function extract_relevant_qa(string $content, string $query): string {
        // Normalize query
        $query_normalized = $this->normalize_text($query);
        $query_semantic = $this->normalize_semantic($query);  // Semantic normalization
        $query_lower = wpaic_mb_strtolower($query);

        // Extract keywords from query
        $query_keywords = $this->extract_keywords($query);

        // Extract important nouns from query (considering state before removing particles/stopwords)
        $important_nouns = $this->extract_important_nouns($query);

        // Split Q&A blocks (support multiple patterns)
        // Separated by "---" or 2+ empty lines
        $blocks = preg_split('/\n\s*-{2,}\s*\n|\r?\n\r?\n+/u', $content);

        if (empty($blocks)) {
            return '';
        }

        $scored_blocks = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            // Extract Question line (support multiple patterns)
            $question_line = '';
            if (preg_match('/(?:Question|Q)\s*[:：]\s*(.+?)(?:\n|$)/ui', $block, $matches)) {
                $question_line = trim($matches[1]);
            }

            // Calculate score
            $score = 0;
            $block_lower = wpaic_mb_strtolower($block);
            $question_lower = wpaic_mb_strtolower($question_line);
            $question_normalized = $this->normalize_text($question_line);
            $question_semantic = $this->normalize_semantic($question_line);  // Semantic normalization

            // Semantic normalization exact match (highest priority)
            if (!empty($question_semantic) && $question_semantic === $query_semantic) {
                $score += 1000; // Semantic exact match
            }
            // Normalized text exact match
            elseif (!empty($question_normalized) && $question_normalized === $query_normalized) {
                $score += 950; // Character exact match
            }
            // Semantic normalization contains
            elseif (!empty($question_semantic) && wpaic_mb_strpos($question_semantic, $query_semantic) !== false) {
                $score += 850;
            }
            elseif (!empty($query_semantic) && wpaic_mb_strpos($query_semantic, $question_semantic) !== false) {
                $score += 800;
            }
            // Normalized text contains
            elseif (!empty($question_normalized) && wpaic_mb_strpos($question_normalized, $query_normalized) !== false) {
                $score += 750;
            }
            elseif (!empty($query_normalized) && wpaic_mb_strpos($query_normalized, $question_normalized) !== false) {
                $score += 700;
            }
            // Full query contained in Question line
            elseif (!empty($question_line) && wpaic_mb_strpos($question_lower, $query_lower) !== false) {
                $score += 500;
            }
            // Full query contained in block
            elseif (wpaic_mb_strpos($block_lower, $query_lower) !== false) {
                $score += 200;
            }

            // Important noun match (high weight)
            foreach ($important_nouns as $noun) {
                $noun_lower = wpaic_mb_strtolower($noun);
                $noun_normalized = $this->normalize_text($noun);

                // Search in normalized Question line (also use semantic normalization)
                if (!empty($question_semantic) && wpaic_mb_strpos($question_semantic, $noun_normalized) !== false) {
                    $score += 150; // Higher score for semantic normalization match
                } elseif (!empty($question_normalized) && wpaic_mb_strpos($question_normalized, $noun_normalized) !== false) {
                    $score += 120; // Normalized match
                } elseif (wpaic_mb_strpos($question_lower, $noun_lower) !== false) {
                    $score += 100; // High score if in Question line
                } elseif (wpaic_mb_strpos($block_lower, $noun_lower) !== false) {
                    $score += 30;
                }
            }

            // Check keyword matches
            foreach ($query_keywords as $keyword) {
                $keyword_lower = wpaic_mb_strtolower($keyword);
                if (wpaic_mb_strpos($block_lower, $keyword_lower) !== false) {
                    $score += 20;

                    // Bonus if in Question line
                    if (wpaic_mb_strpos($question_lower, $keyword_lower) !== false) {
                        $score += 50;
                    }
                }
            }

            if ($score > 0) {
                $scored_blocks[] = [
                    'content' => $block,
                    'score' => $score,
                    'question' => $question_line,
                ];
            }
        }

        if (empty($scored_blocks)) {
            // If no matching Q&A, return first few
            return implode("\n\n---\n\n", array_slice($blocks, 0, 5));
        }

        // Sort by score
        usort($scored_blocks, fn($a, $b) => $b['score'] <=> $a['score']);

        // If highest score is very high (exact match or high similarity), return only that Q&A
        // This prevents smaller models from mixing with other information
        if (!empty($scored_blocks) && $scored_blocks[0]['score'] >= 700) {
            // Return only exact match or very high match Q&A
            return "[EXACT MATCH - USE THIS ANSWER ONLY]\n" . $scored_blocks[0]['content'];
        }

        // Place highest score Q&A first (important for smaller models)
        $top_blocks = array_slice($scored_blocks, 0, 6);

        // Highlight highest score
        $result_parts = [];
        foreach ($top_blocks as $i => $block) {
            if ($i === 0 && $block['score'] >= 500) {
                // Mark most relevant Q&A
                $result_parts[] = "[BEST MATCH]\n" . $block['content'];
            } else {
                $result_parts[] = $block['content'];
            }
        }

        return implode("\n\n---\n\n", $result_parts);
    }

    /**
     * Normalize text (for comparison)
     */
    private function normalize_text(string $text): string {
        // Remove whitespace, symbols, question marks, etc.
        $normalized = preg_replace('/[\s\t\r\n]+/u', '', $text);
        $normalized = preg_replace('/[、。！？「」『』（）\[\]【】・,.!?\'"：:；;？！\s]+/u', '', $normalized);
        // Convert to lowercase
        $normalized = wpaic_mb_strtolower($normalized);
        return $normalized;
    }

    /**
     * Semantic normalization (unify similar expressions)
     */
    private function normalize_semantic(string $text): string {
        // Unify similar expressions
        $replacements = [
            // Unify question patterns
            '/どのような|どんな|何の|なんの/u' => 'どんな',
            '/事|こと|もの/u' => 'こと',
            '/できますか|出来ますか|可能ですか/u' => 'できますか',
            '/ありますか|有りますか/u' => 'ありますか',
            // Unify work-related terms (to match "会計業務" → "会計")
            '/業務|作業|仕事/u' => '',
        ];

        $normalized = $text;
        foreach ($replacements as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        // Remove whitespace and symbols
        $normalized = preg_replace('/[\s\t\r\n、。！？「」『』（）\[\]【】・,.!?\'"：:；;？！]+/u', '', $normalized);
        $normalized = wpaic_mb_strtolower($normalized);

        return $normalized;
    }

    /**
     * Extract important nouns from query
     */
    private function extract_important_nouns(string $query): array {
        $nouns = [];

        // Remove common question patterns and extract nouns
        $patterns = [
            '/(?:は|が|を|に|で|と|も|の)?(?:どのような|どんな|何|なに)(?:事|こと|もの)?(?:を|が|に)?/u',
            '/(?:について|に関して|とは|ですか|ください|できますか|ありますか)/u',
            '/(?:教えて|おしえて|知りたい|したい)/u',
            '/(?:業務|作業|仕事)(?:は|が|を|に|で)?/u',  // "accounting work" → "accounting"
        ];

        $cleaned = $query;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, ' ', $cleaned);
        }

        // Remove symbols
        $cleaned = preg_replace('/[、。！？「」『』（）\[\]【】・,.!?\'"：:；;]+/u', ' ', $cleaned);
        $cleaned = trim($cleaned);

        // Split by spaces and extract words with 2+ characters
        $words = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $word = trim($word);
            if (wpaic_mb_strlen($word) >= 2) {
                $nouns[] = $word;
            }
        }

        return array_values(array_unique($nouns));
    }
}
