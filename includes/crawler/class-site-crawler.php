<?php
/**
 * Site crawler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Site_Crawler {

    /**
     * Transient key for crawl lock (prevent overlapping runs).
     */
    const LOCK_KEY = 'wpaic_crawl_lock';

    /**
     * Lock timeout in seconds (1 hour).
     */
    const LOCK_TIMEOUT = 3600;

    /**
     * Option key for incremental crawl progress.
     */
    const PROGRESS_KEY = 'wpaic_crawl_progress';

    /**
     * Content extractor
     */
    private WPAIC_Content_Extractor $extractor;

    /**
     * Content chunker
     */
    private WPAIC_Content_Chunker $chunker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->extractor = new WPAIC_Content_Extractor();
        $this->chunker = new WPAIC_Content_Chunker();
    }

    /**
     * Crawl content incrementally (N posts per cron run).
     * Uses a transient lock to prevent overlapping runs.
     */
    public function crawl_all(): array {
        $settings = get_option('wpaic_settings', []);

        if (empty($settings['crawler_enabled'])) {
            return ['skipped' => 'Crawler disabled'];
        }

        // Prevent overlapping runs
        if (get_transient(self::LOCK_KEY)) {
            return ['skipped' => 'Crawl already in progress'];
        }
        set_transient(self::LOCK_KEY, time(), self::LOCK_TIMEOUT);

        try {
            return $this->run_incremental_crawl($settings);
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * Manual crawl: process ALL content regardless of crawler_enabled.
     * Resets incremental progress and processes every post type in one go.
     */
    public function crawl_all_manual(): array {
        // Prevent overlapping runs
        if (get_transient(self::LOCK_KEY)) {
            return ['skipped' => 'Crawl already in progress'];
        }
        set_transient(self::LOCK_KEY, time(), self::LOCK_TIMEOUT);

        try {
            // Reset incremental progress so we start fresh
            delete_option(self::PROGRESS_KEY);

            $settings = get_option('wpaic_settings', []);
            $post_types = $settings['crawler_post_types'] ?? ['post', 'page'];
            $chunk_size = $settings['crawler_chunk_size'] ?? 1000;
            $exclude_ids = array_map('absint', $settings['crawler_exclude_ids'] ?? []);

            if (in_array('all', $post_types, true)) {
                $post_types = get_post_types(['public' => true], 'names');
                unset($post_types['attachment']);
            }
            $post_types = array_values($post_types);

            $this->chunker->set_chunk_size($chunk_size);

            $results = [
                'indexed' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => 0,
            ];

            foreach ($post_types as $current_type) {
                $offset = 0;
                $batch_size = 100;

                while (true) {
                    $query_args = [
                        'post_type'      => $current_type,
                        'post_status'    => 'publish',
                        'posts_per_page' => $batch_size,
                        'offset'         => $offset,
                        'fields'         => 'ids',
                        'orderby'        => 'ID',
                        'order'          => 'ASC',
                    ];
                    if (!empty($exclude_ids)) {
                        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- exclude_ids is a small bounded set of already-indexed posts
                        $query_args['post__not_in'] = $exclude_ids;
                    }
                    $post_ids = get_posts($query_args);

                    if (empty($post_ids)) {
                        break;
                    }

                    foreach ($post_ids as $post_id) {
                        $post = get_post($post_id);
                        if (!$post) {
                            continue;
                        }
                        try {
                            $result = $this->index_post($post);
                            $results[$result]++;
                        } catch (Exception $e) {
                            $results['errors']++;
                        }
                    }

                    if (count($post_ids) < $batch_size) {
                        break;
                    }
                    $offset += $batch_size;
                }
            }

            $this->finish_crawl_cycle($results);

            return $results;
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /**
     * Run one incremental batch.
     */
    private function run_incremental_crawl(array $settings): array {
        $post_types = $settings['crawler_post_types'] ?? ['post', 'page'];
        $chunk_size = $settings['crawler_chunk_size'] ?? 1000;
        $batch_size = 100;
        $exclude_ids = array_map('absint', $settings['crawler_exclude_ids'] ?? []);

        // If "all" is specified, get all public post types
        if (in_array('all', $post_types, true)) {
            $post_types = get_post_types(['public' => true], 'names');
            unset($post_types['attachment']);
        }
        $post_types = array_values($post_types);

        $this->chunker->set_chunk_size($chunk_size);

        // Resume from saved progress (or start fresh)
        $progress = get_option(self::PROGRESS_KEY, null);
        $type_index = 0;
        $offset     = 0;

        if (is_array($progress)) {
            $type_index = (int) ($progress['type_index'] ?? 0);
            $offset     = (int) ($progress['offset'] ?? 0);
        }

        $results = [
            'indexed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ];

        // Already past the last post type — full cycle done
        if ($type_index >= count($post_types)) {
            $this->finish_crawl_cycle($results);
            return $results;
        }

        $current_type = $post_types[$type_index];

        $query_args = [
            'post_type'      => $current_type,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];
        if (!empty($exclude_ids)) {
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- exclude_ids is a small bounded set of already-indexed posts
            $query_args['post__not_in'] = $exclude_ids;
        }
        $post_ids = get_posts($query_args);

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            try {
                $result = $this->index_post($post);
                $results[$result]++;
            } catch (Exception $e) {
                $results['errors']++;
            }
        }

        // Advance progress
        if (count($post_ids) < $batch_size) {
            // Finished this post type — move to next
            $type_index++;
            $offset = 0;
        } else {
            $offset += $batch_size;
        }

        if ($type_index >= count($post_types)) {
            // All post types done — cycle complete
            $this->finish_crawl_cycle($results);
        } else {
            // Save progress for next cron run
            update_option(self::PROGRESS_KEY, [
                'type_index' => $type_index,
                'offset'     => $offset,
            ], false);
        }

        return $results;
    }

    /**
     * Mark a full crawl cycle as finished.
     */
    private function finish_crawl_cycle(array $results): void {
        delete_option(self::PROGRESS_KEY);
        update_option('wpaic_last_crawl', current_time('mysql'));
        update_option('wpaic_last_crawl_results', $results);

        /**
         * Fires after a crawl cycle completes.
         *
         * @param array $results Crawl results with indexed/updated/skipped/errors counts.
         */
        do_action('wpaic_after_crawl', $results);
    }

    /**
     * Index single post
     */
    public function index_post(WP_Post $post): string {
        // Check if published
        if ($post->post_status !== 'publish') {
            return 'skipped';
        }

        // Skip attachments
        if ($post->post_type === 'attachment') {
            return 'skipped';
        }

        // Extract content
        $content = $this->extractor->extract($post);

        if (empty(trim($content))) {
            return 'skipped';
        }

        // Calculate content hash
        $content_hash = hash('sha256', $content);

        // Skip if no changes
        if (WPAIC_Content_Index::hash_exists($post->ID, $content_hash)) {
            return 'skipped';
        }

        // Check if existing index exists
        $existing = WPAIC_Content_Index::get_by_post_id($post->ID);
        $is_update = !empty($existing);

        // Delete existing index
        WPAIC_Content_Index::delete_by_post_id($post->ID);

        // Split into chunks
        $settings = get_option('wpaic_settings', []);
        $chunk_size = $settings['crawler_chunk_size'] ?? 1000;
        $this->chunker->set_chunk_size($chunk_size);
        $chunks = $this->chunker->split($content);

        // Index each chunk and collect IDs
        global $wpdb;
        $chunk_ids = [];
        foreach ($chunks as $index => $chunk) {
            WPAIC_Content_Index::create([
                'post_id'      => $post->ID,
                'post_type'    => $post->post_type,
                'title'        => $post->post_title,
                'content'      => $chunk,
                'content_hash' => $content_hash,
                'chunk_index'  => $index,
                'url'          => get_permalink($post->ID),
            ]);
            $chunk_ids[] = $wpdb->insert_id;
        }

        // Generate embeddings if configured
        $this->maybe_generate_embeddings($chunks, $chunk_ids);

        return $is_update ? 'updated' : 'indexed';
    }

    /**
     * Generate embeddings for newly indexed chunks (non-fatal on failure)
     *
     * @param string[] $chunks    Chunk texts
     * @param int[]    $chunk_ids Corresponding DB row IDs
     */
    private function maybe_generate_embeddings(array $chunks, array $chunk_ids): void {
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['embedding_enabled'])) {
            return;
        }

        $generator = new WPAIC_Embedding_Generator($settings);
        if (!$generator->is_configured()) {
            return;
        }

        try {
            $embeddings = $generator->generate_batch($chunks);
            foreach ($embeddings as $i => $emb) {
                if ($emb && isset($chunk_ids[$i]) && $chunk_ids[$i] > 0) {
                    WPAIC_Content_Index::update_embedding(
                        $chunk_ids[$i],
                        WPAIC_Vector_Search::pack_embedding($emb),
                        $generator->get_model()
                    );
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('WPAIC embedding generation error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Hook on post save
     */
    public function on_save_post(int $post_id, WP_Post $post): void {
        // Ignore autosave and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Check settings
        $settings = get_option('wpaic_settings', []);
        if (empty($settings['crawler_enabled'])) {
            return;
        }

        // Check if target post type
        $post_types = $settings['crawler_post_types'] ?? ['post', 'page'];
        if (!in_array($post->post_type, $post_types, true)) {
            return;
        }

        // Index if published, delete otherwise
        if ($post->post_status === 'publish') {
            $this->index_post($post);
        } else {
            WPAIC_Content_Index::delete_by_post_id($post_id);
        }
    }

    /**
     * Hook on post delete
     */
    public function on_delete_post(int $post_id): void {
        WPAIC_Content_Index::delete_by_post_id($post_id);
    }

    /**
     * Crawl specific post type only
     */
    public function crawl_post_type(string $post_type): array {
        $settings = get_option('wpaic_settings', []);
        $chunk_size = $settings['crawler_chunk_size'] ?? 1000;

        $this->chunker->set_chunk_size($chunk_size);

        $results = [
            'indexed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ];

        $batch_size = 100;
        $paged = 1;
        do {
            $post_ids = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'paged'          => $paged,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]);

            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }
                try {
                    $result = $this->index_post($post);
                    $results[$result]++;
                } catch (Exception $e) {
                    $results['errors']++;
                }
            }

            $paged++;
        } while (count($post_ids) === $batch_size);

        return $results;
    }

    /**
     * Clear index
     */
    public function clear_index(): bool {
        return WPAIC_Content_Index::truncate() !== false;
    }

    /**
     * Get crawl status
     */
    public function get_status(): array {
        $settings = get_option('wpaic_settings', []);
        $last_crawl = get_option('wpaic_last_crawl', '');
        $last_results = get_option('wpaic_last_crawl_results', []);

        return [
            'enabled'       => !empty($settings['crawler_enabled']),
            'post_types'    => $settings['crawler_post_types'] ?? ['post', 'page'],
            'interval'      => $settings['crawler_interval'] ?? 'daily',
            'indexed_count' => WPAIC_Content_Index::get_count(),
            'last_crawl'    => $last_crawl,
            'last_results'  => $last_results,
        ];
    }
}
