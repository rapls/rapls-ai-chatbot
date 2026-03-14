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
     * Cached FULLTEXT index availability (per table, per request)
     */
    private static array $fulltext_cache = [];

    /**
     * Bot-specific knowledge category filter (empty = all categories)
     * @var string[]
     */
    private array $knowledge_categories = [];

    /**
     * Whether to include site crawl index in search results
     */
    private bool $use_site_crawl = true;

    /**
     * Whether to include knowledge base in search results
     */
    private bool $use_knowledge = true;

    /**
     * Set bot-specific search filters.
     *
     * @param string[] $categories     Knowledge categories to include (empty = all).
     * @param bool     $use_crawl      Whether to search the site crawl index.
     * @param bool     $use_knowledge  Whether to search the knowledge base.
     */
    public function set_bot_filters(array $categories = [], bool $use_crawl = true, bool $use_knowledge = true): void {
        $this->knowledge_categories = array_filter(array_map('trim', $categories));
        $this->use_site_crawl = $use_crawl;
        $this->use_knowledge = $use_knowledge;
    }

    /**
     * Table name
     */
    private function get_table_name(): string {
        return trim(wpaic_validated_table('aichat_index'), '`');
    }

    /**
     * Knowledge table name — whitelist-validated.
     */
    private function get_knowledge_table(): string {
        return trim(wpaic_validated_table('aichat_knowledge'), '`');
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
        $has_status    = $table_exists && $schema_version >= 2;

        // Fallback: check columns directly if migration hasn't run yet.
        // $table is whitelist-validated via get_knowledge_table() → wpaic_validated_table().
        // Column names ('priority', 'is_active', 'status') are hardcoded literals — no external input.
        if ($table_exists && $schema_version < 2) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $has_priority = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'priority'"));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $has_is_active = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'is_active'"));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $has_status = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'status'"));
        }

        self::$schema_cache[$table] = [
            'exists'        => $table_exists,
            'has_priority'  => $has_priority,
            'has_is_active' => $has_is_active,
            'has_status'    => $has_status,
        ];

        return self::$schema_cache[$table];
    }

    /**
     * Search related content (hybrid: keyword + vector)
     */
    public function search(string $query, int $limit = 3): array {
        $results = [];

        // Knowledge base search — skip when bot disables knowledge
        $priority_knowledge = [];
        if ($this->use_knowledge) {
            // Always get knowledge (regardless of keywords)
            $knowledge_limit = max($limit * 2, 5);
            $priority_knowledge = $this->get_high_priority_knowledge($knowledge_limit);
            // Priority knowledge is not keyword-matched
            foreach ($priority_knowledge as &$pk) {
                $pk['keyword_matched'] = false;
            }
            unset($pk);
            $results = array_merge($results, $priority_knowledge);

            // Search knowledge base by keywords (additional match)
            $knowledge_results = $this->search_knowledge($query, $limit);
            foreach ($knowledge_results as $kr) {
                $kr['keyword_matched'] = true;
                $exists = false;
                foreach ($results as &$r) {
                    if ($r['type'] === 'knowledge' && $r['title'] === $kr['title']) {
                        $r['score'] = max($r['score'], $kr['score']);
                        $r['keyword_matched'] = true;
                        $exists = true;
                        break;
                    }
                }
                unset($r);
                if (!$exists) {
                    $results[] = $kr;
                }
            }
        }

        // Search from index (keyword) — skip for greetings/chitchat or when bot disables site crawl
        $index_results = [];
        if ($this->use_site_crawl && !$this->is_greeting($query)) {
            $index_results = $this->search_index($query, $limit);
            foreach ($index_results as &$ir) {
                $ir['keyword_matched'] = true;
            }
            unset($ir);
        }
        $results = array_merge($results, $index_results);

        // Vector search (if embedding is configured)
        $vector_results = $this->vector_search($query, $limit);

        // Merge keyword + vector results
        if (!empty($vector_results)) {
            $results = $this->merge_hybrid_results($results, $vector_results, $limit);
        }

        // Sort by score and return top results
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

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
            array_unshift($final_results, $priority_knowledge[0]);
        }

        return $final_results;
    }

    /**
     * Perform vector similarity search if embeddings are enabled
     *
     * @param string $query User query
     * @param int    $limit Max results
     * @return array Vector search results (empty if not configured)
     */
    private function vector_search(string $query, int $limit): array {
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['embedding_enabled'])) {
            return [];
        }

        $embedding_generator = new WPAIC_Embedding_Generator($settings);
        if (!$embedding_generator->is_configured()) {
            return [];
        }

        $query_embedding = $embedding_generator->generate($query);
        if (!$query_embedding) {
            return [];
        }

        $vector_search = new WPAIC_Vector_Search();
        $vector_index = $this->use_site_crawl ? $vector_search->search_index($query_embedding, $limit * 3) : [];
        $vector_knowledge = $this->use_knowledge ? $vector_search->search_knowledge($query_embedding, $limit) : [];

        // Filter vector knowledge by category if bot filter is set
        if (!empty($vector_knowledge) && !empty($this->knowledge_categories)) {
            $allowed = $this->knowledge_categories;
            $vector_knowledge = array_filter($vector_knowledge, function($item) use ($allowed) {
                return in_array($item['category'] ?? '', $allowed, true);
            });
        }

        return array_merge($vector_index, array_values($vector_knowledge));
    }

    /**
     * Merge keyword search results with vector search results using hybrid scoring
     *
     * Score formula:
     *   - Both keyword + vector: 0.4 * norm_keyword + 0.6 * vector_score
     *   - Keyword only: 0.4 * norm_keyword
     *   - Vector only: 0.6 * vector_score
     *   - Knowledge priority bonus preserved from existing logic
     *
     * @param array $keyword_results Keyword search results (with 'score')
     * @param array $vector_results  Vector search results (with 'vector_score')
     * @param int   $limit           Max results
     * @return array Merged results with hybrid scores
     */
    private function merge_hybrid_results(array $keyword_results, array $vector_results, int $limit): array {
        // Normalize keyword scores to 0-1 range
        $max_keyword = 0;
        foreach ($keyword_results as $r) {
            if ($r['score'] > $max_keyword) {
                $max_keyword = $r['score'];
            }
        }

        // Build lookup: key = "type:title" for matching
        $merged = [];

        foreach ($keyword_results as $r) {
            $key = $r['type'] . ':' . $r['title'];
            $norm_score = $max_keyword > 0 ? $r['score'] / $max_keyword : 0;
            $merged[$key] = $r;
            $merged[$key]['_keyword_norm'] = $norm_score;
            $merged[$key]['_vector_score'] = 0.0;
        }

        foreach ($vector_results as $vr) {
            $key = $vr['type'] . ':' . $vr['title'];
            $vs = $vr['vector_score'] ?? 0.0;

            if (isset($merged[$key])) {
                // Both keyword + vector: hybrid score
                $merged[$key]['_vector_score'] = $vs;
            } else {
                // Vector only
                $merged[$key] = $vr;
                $merged[$key]['_keyword_norm'] = 0.0;
                $merged[$key]['_vector_score'] = $vs;
            }
        }

        // Calculate final hybrid scores
        foreach ($merged as &$item) {
            $kw = $item['_keyword_norm'] ?? 0.0;
            $vs = $item['_vector_score'] ?? 0.0;

            // Preserve existing priority bonus for knowledge items
            $priority_bonus = 0;
            if ($item['type'] === 'knowledge') {
                $priority = (int) ($item['priority'] ?? 0);
                $priority_bonus = $priority * 20;
                // Keep high base score for priority knowledge
                if ($priority > 0 && $kw > 0) {
                    $priority_bonus += 100;
                }
            }

            $hybrid = (0.4 * $kw) + (0.6 * $vs);
            // Scale hybrid back to a comparable range + priority bonus
            $item['score'] = ($hybrid * $max_keyword) + $priority_bonus;

            unset($item['_keyword_norm'], $item['_vector_score']);
        }
        unset($item);

        return array_values($merged);
    }

    /**
     * Search knowledge/FAQ only (no site index)
     * Used when message limit is reached to provide FAQ-based answers
     */
    public function search_knowledge_only(string $query, int $limit = 5): array {
        if (!$this->use_knowledge) {
            return [];
        }

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

        $has_priority  = $schema['has_priority'];
        $has_is_active = $schema['has_is_active'];
        $has_status    = $schema['has_status'];

        $where_parts = [];
        if ($has_is_active) {
            $where_parts[] = 'is_active = 1';
        }
        if ($has_status) {
            $where_parts[] = "status = 'published'";
        }
        // Bot-specific category filter
        if (!empty($this->knowledge_categories)) {
            $placeholders = implode(',', array_fill(0, count($this->knowledge_categories), '%s'));
            $where_parts[] = $wpdb->prepare("category IN ({$placeholders})", ...$this->knowledge_categories); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        $where_clause = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';
        $priority_column = $has_priority ? 'priority' : '0 as priority';
        $order_by = $has_priority ? 'ORDER BY priority DESC, created_at DESC' : 'ORDER BY created_at DESC';

        // Get high priority knowledge (including priority 0 = all active knowledge)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and column names are safe internal values
        $sql = $wpdb->prepare(
            "SELECT id, title, content, category, {$priority_column}
             FROM {$table}
             {$where_clause}
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
                'type'      => 'knowledge',
                'source_id' => (int) ($item['id'] ?? 0),
                'title'     => $item['title'],
                'content'   => $item['content'],
                'category'  => $item['category'] ?? '',
                'priority'  => $priority,
                'url'       => null,
                'score'     => $base_score + $priority_boost,
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
        if (isset(self::$fulltext_cache[$table])) {
            return self::$fulltext_cache[$table];
        }

        global $wpdb;

        // $table is whitelist-validated via get_table_name()/get_knowledge_table() → wpaic_validated_table().
        // 'FULLTEXT' is a hardcoded literal — no external input in this query.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Index_type = 'FULLTEXT'", ARRAY_A);
        self::$fulltext_cache[$table] = !empty($indexes);

        return self::$fulltext_cache[$table];
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
            "SELECT post_id, post_type, title, content, url,
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
            self::$fulltext_cache[$table] = false;
            return [];
        }

        // Group by post_id (combine multiple chunks of same post)
        $grouped = $this->group_by_post($results);

        return array_map(function($item) {
            return [
                'type'      => 'index',
                'post_id'   => (int) ($item['post_id'] ?? 0),
                'post_type' => $item['post_type'] ?? '',
                'title'     => $item['title'],
                'content'   => $item['content'],
                'url'       => $item['url'],
                'score'     => (float) $item['score'],
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
            "SELECT post_id, post_type, title, content, url, 1 as score
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
        unset($item);

        usort($grouped, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(function($item) {
            return [
                'type'      => 'index',
                'post_id'   => (int) ($item['post_id'] ?? 0),
                'post_type' => $item['post_type'] ?? '',
                'title'     => $item['title'],
                'content'   => $item['content'],
                'url'       => $item['url'],
                'score'     => $item['score'],
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

        $has_priority  = $schema['has_priority'];
        $has_is_active = $schema['has_is_active'];
        $has_status    = $schema['has_status'];

        // Build conditions
        $pre_conditions = [];
        if ($has_is_active) {
            $pre_conditions[] = 'is_active = 1';
        }
        if ($has_status) {
            $pre_conditions[] = "status = 'published'";
        }
        // Bot-specific category filter
        if (!empty($this->knowledge_categories)) {
            $placeholders = implode(',', array_fill(0, count($this->knowledge_categories), '%s'));
            $pre_conditions[] = $wpdb->prepare("category IN ({$placeholders})", ...$this->knowledge_categories); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        $active_condition = !empty($pre_conditions) ? implode(' AND ', $pre_conditions) . ' AND' : '';
        $priority_column = $has_priority ? 'priority' : '0 as priority';
        $order_by = $has_priority ? 'ORDER BY priority DESC, created_at DESC' : 'ORDER BY created_at DESC';

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
                'type'        => 'knowledge',
                'source_id'   => (int) ($item['id'] ?? 0),
                'title'       => $item['title'],
                'content'     => $item['content'],
                'category'    => $item['category'] ?? '',
                'priority'    => (int) ($item['priority'] ?? 0),
                'url'         => null,
                'score'       => $this->calculate_keyword_score($item, $keywords) + 10 + $priority_boost, // Prioritize knowledge + priority boost
            ];
        }, $results);
    }

    /**
     * Check if the query is a greeting/chitchat (not an information-seeking question)
     */
    private function is_greeting(string $query): bool {
        $normalized = trim(wpaic_mb_strtolower($query));
        // Remove trailing punctuation
        $normalized = preg_replace('/[。！？!?.…]+$/u', '', $normalized);
        $normalized = trim($normalized);

        $greetings = [
            'こんにちは', 'こんばんは', 'おはよう', 'おはようございます',
            'はじめまして', 'よろしく', 'よろしくお願いします',
            'ありがとう', 'ありがとうございます', 'どうも',
            'すみません', 'おつかれ', 'おつかれさま', 'お疲れ様',
            'やあ', 'ども', 'はーい', 'はい', 'うん',
            'hello', 'hi', 'hey', 'good morning', 'good evening',
            'good afternoon', 'thanks', 'thank you', 'bye', 'goodbye',
        ];

        return in_array($normalized, $greetings, true);
    }

    private function extract_keywords(string $text): array {
        // Remove symbols
        $text = preg_replace('/[、。！？「」『』（）\[\]【】・,.!?\'"：:；;]+/u', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return [];
        }

        // Japanese stopwords (remove as substring)
        $jp_stopwords = ['について', 'に関して', 'とは', 'ですか', 'ください', 'ありますか', 'でしょうか', 'ですが', 'ですね', 'ですよ', 'ません', 'ました', 'します', 'される', 'している', 'された', 'できる', 'できます', 'ありません', 'おしえて', '教えて', 'どうやって', 'どのように', 'なぜ', 'どこ', 'いつ', 'だれ', 'なに', '何'];

        // Japanese particles used for splitting compound phrases
        $jp_split_particles = ['の', 'は', 'が', 'を', 'に', 'で', 'と', 'も', 'や', 'へ'];

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

        // Split long Japanese keywords on particles to get sub-phrases
        // e.g. "AIチャットボットの料金プラン" → ["AIチャットボット", "料金プラン"]
        $sub_keywords = [];
        foreach ($keywords as $kw) {
            if (wpaic_mb_strlen($kw) >= 6 && preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $kw)) {
                $particle_pattern = '/(' . implode('|', $jp_split_particles) . ')/u';
                $parts = preg_split($particle_pattern, $kw, -1, PREG_SPLIT_NO_EMPTY);
                if (count($parts) > 1) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (wpaic_mb_strlen($part) >= 2 && !in_array($part, $keywords, true)) {
                            $sub_keywords[] = $part;
                        }
                    }
                }
            }
        }
        $keywords = array_merge($keywords, $sub_keywords);

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
     * Get all distinct indexed page URLs
     */
    public function get_indexed_urls(int $limit = 20): array {
        return array_column($this->get_indexed_pages($limit), 'url');
    }

    /**
     * Get all distinct indexed pages with metadata (for content cards in "all" mode)
     */
    public function get_indexed_pages(int $limit = 20): array {
        global $wpdb;
        $table = $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT MIN(post_id) AS post_id, MIN(post_type) AS post_type, MIN(title) AS title, MIN(content) AS content, url
             FROM {$table}
             WHERE url IS NOT NULL AND url != ''
             GROUP BY url
             ORDER BY post_id DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        if (empty($results)) {
            return [];
        }

        return array_map(function ($item) {
            return [
                'type'      => 'index',
                'post_id'   => (int) ($item['post_id'] ?? 0),
                'post_type' => $item['post_type'] ?? '',
                'title'     => $item['title'] ?? '',
                'content'   => $item['content'] ?? '',
                'url'       => $item['url'],
                'score'     => 0,
            ];
        }, $results);
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
        // Check if explicit Question/Answer patterns exist
        // Must have actual Q&A markers — generic "---" separators with colons are NOT sufficient
        return (
            preg_match('/Question\s*[:：]/ui', $content) && preg_match('/Answer\s*[:：]/ui', $content)
        ) || (
            preg_match('/(?:^|\n)\s*Q\s*[:：]/u', $content) && preg_match('/(?:^|\n)\s*A\s*[:：]/u', $content)
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
            if (!empty($question_semantic) && !empty($query_semantic) && $question_semantic === $query_semantic) {
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
