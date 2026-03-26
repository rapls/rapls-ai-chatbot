<?php
/**
 * Vector similarity search using cosine similarity
 */

if (!defined('ABSPATH')) {
    exit;
}
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from raplsaich_require_table() cannot use placeholders

class RAPLSAICH_Vector_Search {

    /**
     * Pack a float array into binary string (float32)
     *
     * @param float[] $floats Embedding vector
     * @return string Binary packed data
     */
    public static function pack_embedding(array $floats): string {
        return pack('f*', ...$floats);
    }

    /**
     * Unpack binary string back to float array
     *
     * @param string $binary Packed embedding data
     * @return float[] Embedding vector
     */
    public static function unpack_embedding(string $binary): array {
        return array_values(unpack('f*', $binary));
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * Note: OpenAI embeddings are normalized, so dot product = cosine similarity.
     * We compute full cosine similarity for Gemini compatibility.
     *
     * @param float[] $a First vector
     * @param float[] $b Second vector
     * @return float Similarity score (0.0 to 1.0)
     */
    public static function cosine_similarity(array $a, array $b): float {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dot    += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }

        $denom = sqrt($norm_a) * sqrt($norm_b);
        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * Search the index table by vector similarity
     *
     * @param float[] $query_embedding Query vector
     * @param int     $limit           Max results to return
     * @return array Search results with vector_score
     */
    public function search_index(array $query_embedding, int $limit = 10): array {
        global $wpdb;
        $table = trim(raplsaich_validated_table('raplsaich_index'), '`');

        if ($table === '') {
            return [];
        }

        // Phase 1: Score embeddings in memory-efficient batches (load only id + embedding).
        $offset = 0;
        $batch_size = 500;
        $top_ids = []; // id => vector_score (keep only top N×3 candidates)
        $keep_count = max($limit * 3, 30);

        while (true) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, embedding FROM `{$table}` WHERE embedding IS NOT NULL LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ), ARRAY_A);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if (empty($row['embedding'])) {
                    continue;
                }
                $emb   = self::unpack_embedding($row['embedding']);
                $score = self::cosine_similarity($query_embedding, $emb);
                $top_ids[(int) $row['id']] = $score;
            }
            $fetched_count = count($rows);
            unset($rows); // free embedding data immediately

            // Prune to top candidates to cap memory growth
            if (count($top_ids) > $keep_count * 2) {
                arsort($top_ids);
                $top_ids = array_slice($top_ids, 0, $keep_count, true);
            }

            if ($fetched_count < $batch_size) {
                break;
            }
            $offset += $batch_size;
        }

        if (empty($top_ids)) {
            return [];
        }

        // Keep only top candidates
        arsort($top_ids);
        $top_ids = array_slice($top_ids, 0, $keep_count, true);

        // Phase 2: Fetch metadata only for top candidates (no embedding column).
        $id_list = implode(',', array_map('intval', array_keys($top_ids)));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $meta_rows = $wpdb->get_results(
            "SELECT id, post_id, post_type, title, content, url FROM `{$table}` WHERE id IN ({$id_list})",
            ARRAY_A
        );

        $scored = [];
        foreach ($meta_rows as $row) {
            $row_id = (int) $row['id'];
            $scored[] = [
                'type'         => 'index',
                'post_id'      => (int) $row['post_id'],
                'post_type'    => $row['post_type'],
                'title'        => $row['title'],
                'content'      => $row['content'],
                'url'          => $row['url'],
                'score'        => 0.0,
                'vector_score' => $top_ids[$row_id] ?? 0.0,
            ];
        }

        // Sort by vector score descending
        usort($scored, fn($a, $b) => $b['vector_score'] <=> $a['vector_score']);

        // Group by post_id (keep best score per post)
        $grouped = [];
        foreach ($scored as $item) {
            $pid = $item['post_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = $item;
            } else {
                // Combine content, keep highest score
                $grouped[$pid]['content'] .= "\n" . $item['content'];
                if ($item['vector_score'] > $grouped[$pid]['vector_score']) {
                    $grouped[$pid]['vector_score'] = $item['vector_score'];
                }
            }
        }

        return array_slice(array_values($grouped), 0, $limit);
    }

    /**
     * Search the knowledge table by vector similarity
     *
     * @param float[] $query_embedding Query vector
     * @param int     $limit           Max results to return
     * @return array Search results with vector_score
     */
    public function search_knowledge(array $query_embedding, int $limit = 5): array {
        global $wpdb;
        $table = trim(raplsaich_validated_table('raplsaich_knowledge'), '`');

        if ($table === '') {
            return [];
        }

        // Check if embedding column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_embedding = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'embedding'"));
        if (!$has_embedding) {
            return [];
        }

        // Load all knowledge embeddings (knowledge tables are typically small)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results(
            "SELECT id, title, content, category, priority, embedding FROM `{$table}` WHERE embedding IS NOT NULL AND is_active = 1",
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $scored = [];
        foreach ($rows as $row) {
            if (empty($row['embedding'])) {
                continue;
            }

            $emb = self::unpack_embedding($row['embedding']);
            $score = self::cosine_similarity($query_embedding, $emb);
            $priority = (int) ($row['priority'] ?? 0);

            $scored[] = [
                'type'         => 'knowledge',
                'title'        => $row['title'],
                'content'      => $row['content'],
                'category'     => $row['category'] ?? '',
                'priority'     => $priority,
                'url'          => null,
                'score'        => 0.0,
                'vector_score' => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['vector_score'] <=> $a['vector_score']);

        return array_slice($scored, 0, $limit);
    }
}
