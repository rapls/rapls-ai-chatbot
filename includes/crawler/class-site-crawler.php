<?php
/**
 * Site crawler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Site_Crawler {

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
     * Crawl all content
     */
    public function crawl_all(): array {
        $settings = get_option('wpaic_settings', []);

        if (empty($settings['crawler_enabled'])) {
            return ['skipped' => 'Crawler disabled'];
        }

        $post_types = $settings['crawler_post_types'] ?? ['post', 'page'];
        $chunk_size = $settings['crawler_chunk_size'] ?? 1000;

        // If "all" is specified, get all public post types
        if (in_array('all', $post_types, true)) {
            $post_types = get_post_types(['public' => true], 'names');
            // Exclude attachments
            unset($post_types['attachment']);
        }

        $this->chunker->set_chunk_size($chunk_size);

        $results = [
            'indexed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ];

        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ($posts as $post) {
                try {
                    $result = $this->index_post($post);
                    $results[$result]++;
                } catch (Exception $e) {
                    $results['errors']++;
                }
            }
        }

        // Save last crawl time
        update_option('wpaic_last_crawl', current_time('mysql'));
        update_option('wpaic_last_crawl_results', $results);

        return $results;
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

        // Index each chunk
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
        }

        return $is_update ? 'updated' : 'indexed';
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

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {
            try {
                $result = $this->index_post($post);
                $results[$result]++;
            } catch (Exception $e) {
                $results['errors']++;
            }
        }

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
