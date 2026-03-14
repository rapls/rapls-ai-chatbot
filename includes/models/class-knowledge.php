<?php
/**
 * Knowledge base model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Knowledge {

    /**
     * Table name — whitelist-validated via wpaic_validated_table().
     */
    private static function get_table_name(): string {
        return trim(wpaic_validated_table('aichat_knowledge'), '`');
    }

    /**
     * Create table
     */
    private static function create_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            priority INT(11) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active),
            KEY priority (priority)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    /**
     * Run schema migration via Activator (activation/upgrade path).
     * Guarded by option flag so it runs at most once per schema version.
     */
    private static function maybe_migrate_schema($table) {
        $schema_version = 2; // Increment when adding new migrations
        $option_key = 'wpaic_knowledge_schema_version';

        if ((int) get_option($option_key, 0) >= $schema_version) {
            return; // Already migrated
        }

        // Transient lock to prevent race conditions on concurrent requests
        $lock_key = 'wpaic_knowledge_migration_lock';
        if (get_transient($lock_key)) {
            return; // Another process is running the migration
        }
        set_transient($lock_key, 1, 30); // 30-second lock

        // Delegate to Activator which handles all schema changes
        if (class_exists('WPAIC_Activator')) {
            WPAIC_Activator::upgrade_columns();
        }

        update_option($option_key, $schema_version, true);
        delete_transient($lock_key);
    }


    /**
     * Ensure status/type columns exist at runtime.
     * Delegates to maybe_migrate_schema() which is idempotent and version-gated.
     */
    private static function maybe_add_columns(string $table): void {
        self::maybe_migrate_schema($table);
    }

    /**
     * Create knowledge
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if (!$table_exists) {
            // Try to create table if not exists
            self::create_table();
        }

        // Schema migration runs only once, guarded by option flag
        self::maybe_migrate_schema($table);

        $type = isset($data['type']) && in_array($data['type'], ['qa', 'template'], true) ? $data['type'] : 'qa';

        $insert_data = [
            'title'     => sanitize_text_field($data['title']),
            'content'   => wp_kses_post($data['content']),
            'category'  => sanitize_text_field($data['category'] ?? ''),
            'priority'  => isset($data['priority']) ? (int) $data['priority'] : 0,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'status'    => isset($data['status']) && in_array($data['status'], ['published', 'draft'], true) ? $data['status'] : 'published',
            'type'      => $type,
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $insert_data, ['%s', '%s', '%s', '%d', '%d', '%s', '%s']);
        wpaic_log_db_error('Knowledge::create');

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save to database.', 'rapls-ai-chatbot'));
        }

        return self::get_by_id($wpdb->insert_id);
    }

    /**
     * Get knowledge by ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get knowledge list
     */
    public static function get_list($args = []) {
        global $wpdb;
        $table = self::get_table_name();

        // Ensure columns exist
        self::maybe_add_columns($table);

        $defaults = [
            'per_page'  => 20,
            'page'      => 1,
            'category'  => '',
            'is_active' => null,
            'status'    => '',
            'type'      => '',
            'orderby'   => 'priority',
            'order'     => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = '1=1';
        $params = [];

        if (!empty($args['category'])) {
            $where .= ' AND category = %s';
            $params[] = $args['category'];
        }

        if ($args['is_active'] !== null) {
            $where .= ' AND is_active = %d';
            $params[] = (int) $args['is_active'];
        }

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['type'])) {
            $where .= ' AND type = %s';
            $params[] = sanitize_text_field($args['type']);
        }

        // Default sort: priority DESC, then created_at DESC
        // Sanitize order direction
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $order);
        if (!$orderby) {
            $orderby = 'priority DESC, created_at DESC';
        } elseif ($args['orderby'] === 'priority') {
            $orderby = 'priority ' . $order . ', created_at DESC';
        }

        $params[] = $args['per_page'];
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, WHERE and ORDER BY are safe internal values
        $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Get total knowledge count
     */
    public static function get_count($category = '', $is_active = null, $status = '', $type = '') {
        global $wpdb;
        $table = self::get_table_name();

        $where = '1=1';
        $params = [];

        if (!empty($category)) {
            $where .= ' AND category = %s';
            $params[] = $category;
        }

        if ($is_active !== null) {
            $where .= ' AND is_active = %d';
            $params[] = (int) $is_active;
        }

        if (!empty($status)) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($status);
        }

        if (!empty($type)) {
            $where .= ' AND type = %s';
            $params[] = sanitize_text_field($type);
        }

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE {$where}",
                ...$params
            ));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    /**
     * Get active templates (for operator mode)
     *
     * @return array List of template entries
     */
    public static function get_templates(): array {
        global $wpdb;
        $table = self::get_table_name();

        // Ensure columns exist
        self::maybe_add_columns($table);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            "SELECT id, title, content, category FROM `{$table}` WHERE type = 'template' AND is_active = 1 AND status = 'published' ORDER BY category, priority DESC, title",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get draft count
     */
    public static function get_draft_count() {
        return self::get_count('', null, 'draft');
    }

    /**
     * Update knowledge
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = [];
        $format = [];

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
            $format[] = '%s';
        }

        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
            $format[] = '%s';
        }

        if (isset($data['category'])) {
            $update_data['category'] = sanitize_text_field($data['category']);
            $format[] = '%s';
        }

        if (isset($data['priority'])) {
            $update_data['priority'] = (int) $data['priority'];
            $format[] = '%d';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int) $data['is_active'];
            $format[] = '%d';
        }

        if (isset($data['status']) && in_array($data['status'], ['published', 'draft'], true)) {
            $update_data['status'] = $data['status'];
            $format[] = '%s';
        }

        if (isset($data['type']) && in_array($data['type'], ['qa', 'template'], true)) {
            $update_data['type'] = $data['type'];
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        // Allow Pro to save version history before update
        do_action('wpaic_knowledge_before_update', (int) $id, $data);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
    }

    /**
     * Delete knowledge
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );

        if ($result !== false) {
            do_action('wpaic_knowledge_deleted', (int) $id);
        }

        return $result;
    }

    /**
     * Delete all knowledge
     */
    public static function delete_all() {
        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->query("TRUNCATE TABLE `{$table}`");
        if ($result === false) {
            // Fallback: TRUNCATE may fail due to DB permissions or configuration
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $wpdb->query("DELETE FROM `{$table}`");
        }
        return $result;
    }

    /**
     * Get category list
     */
    public static function get_categories() {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_col(
            "SELECT DISTINCT category FROM `{$table}` WHERE category != '' ORDER BY category ASC"
        );
    }

    /**
     * Update embedding for a knowledge entry
     *
     * @param int    $id               Knowledge row ID
     * @param string $packed_embedding  Binary packed float32 embedding
     * @param string $model             Embedding model name
     * @return bool True on success
     */
    public static function update_embedding(int $id, string $packed_embedding, string $model): bool {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            [
                'embedding'       => $packed_embedding,
                'embedding_model' => $model,
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get embedding stats for knowledge entries
     *
     * @return array{total: int, embedded: int, embedding_model: string|null}
     */
    public static function get_embedding_stats(): array {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE is_active = 1");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_col = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'embedding'"));
        if (!$has_col) {
            return ['total' => $total, 'embedded' => 0, 'embedding_model' => null];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $embedded = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE embedding IS NOT NULL AND is_active = 1");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $model = $wpdb->get_var("SELECT embedding_model FROM `{$table}` WHERE embedding IS NOT NULL LIMIT 1");

        return ['total' => $total, 'embedded' => $embedded, 'embedding_model' => $model];
    }

    /**
     * Get knowledge entries without embeddings
     *
     * @param int $limit Max entries to return
     * @return array Array of rows with id, title, content
     */
    public static function get_unembedded_entries(int $limit = 100): array {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_col = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'embedding'"));
        if (!$has_col) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content FROM `{$table}` WHERE embedding IS NULL AND is_active = 1 ORDER BY id ASC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Clear all embeddings
     */
    public static function clear_all_embeddings(): bool {
        global $wpdb;
        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_col = !empty($wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'embedding'"));
        if (!$has_col) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->query("UPDATE `{$table}` SET embedding = NULL, embedding_model = NULL");

        return $result !== false;
    }

    /**
     * Import knowledge from file
     */
    /**
     * Maximum import file size (5MB)
     */
    const IMPORT_MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Maximum CSV rows to import
     */
    const IMPORT_MAX_CSV_ROWS = 1000;

    public static function import_from_file($file, $category = '') {
        // Check file upload error
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return new WP_Error('upload_error', __('File upload failed.', 'rapls-ai-chatbot'));
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload error.', 'rapls-ai-chatbot'));
        }

        if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            return new WP_Error('file_error', __('Cannot read uploaded file.', 'rapls-ai-chatbot'));
        }

        // Defense-in-depth file size check
        if (filesize($file['tmp_name']) > self::IMPORT_MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', __('File size must be 5MB or less.', 'rapls-ai-chatbot'));
        }

        $content = '';
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // MIME type validation — prevents extension spoofing
        $allowed_mimes = [
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'md'   => 'text/plain',
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $filetype = wp_check_filetype($file['name'], $allowed_mimes);
        if (empty($filetype['ext'])) {
            return new WP_Error('invalid_file_type', __('Unsupported file type. Supported: TXT, CSV, MD, PDF, DOCX', 'rapls-ai-chatbot'));
        }

        // Generate title from filename
        $title = pathinfo($file['name'], PATHINFO_FILENAME);

        switch ($extension) {
            case 'txt':
            case 'md':
                $content = file_get_contents($file['tmp_name']);
                if ($content === false) {
                    return new WP_Error('read_error', __('Failed to read file.', 'rapls-ai-chatbot'));
                }
                break;

            case 'csv':
                $content = self::parse_csv_file($file['tmp_name']);
                break;

            case 'pdf':
                $content = WPAIC_PDF_Parser::extract_text($file['tmp_name']);
                if (empty($content)) {
                    return new WP_Error(
                        'pdf_no_text',
                        __('Could not extract text from this PDF. Only text-based PDFs are supported. Scanned image PDFs and encrypted PDFs cannot be processed.', 'rapls-ai-chatbot')
                    );
                }
                break;

            case 'docx':
                if (!class_exists('ZipArchive')) {
                    return new WP_Error(
                        'docx_not_supported',
                        __('DOCX import requires the PHP ZipArchive extension, which is not available on this server.', 'rapls-ai-chatbot')
                    );
                }
                $content = WPAIC_DOCX_Parser::extract_text($file['tmp_name']);
                if (empty($content)) {
                    return new WP_Error(
                        'docx_parse_error',
                        __('Could not extract text from this DOCX file. The file may be corrupted or empty.', 'rapls-ai-chatbot')
                    );
                }
                break;

            default:
                return new WP_Error('invalid_file_type', __('Unsupported file type. Supported: TXT, CSV, MD, PDF, DOCX', 'rapls-ai-chatbot'));
        }

        if (empty($content)) {
            return new WP_Error('empty_content', __('File content is empty.', 'rapls-ai-chatbot'));
        }

        // Convert to UTF-8 (only if mbstring extension is available)
        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            // CP932 (Windows-31J) is common in Japanese Excel exports
            $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS', 'CP932', 'SJIS-win', 'EUC-JP', 'ISO-8859-1'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
                if ($converted !== false) {
                    $content = $converted;
                }
            }
        } else {
            // mbstring unavailable: warn if content looks non-UTF-8
            if (!preg_match('//u', $content)) {
                return new WP_Error(
                    'encoding_error',
                    __('The file does not appear to be UTF-8 encoded. Please save the file as UTF-8 (with BOM recommended for Excel) and try again.', 'rapls-ai-chatbot')
                );
            }
        }

        return self::create([
            'title'    => $title,
            'content'  => $content,
            'category' => $category,
        ]);
    }

    /**
     * Export knowledge entries for given filters
     */
    public static function export(array $filters = []): array {
        $args = array_merge($filters, ['per_page' => 10000, 'page' => 1]);
        return self::get_list($args);
    }

    /**
     * Format knowledge entries for CSV export
     */
    public static function format_for_csv(array $items): array {
        $rows = [];

        // Header row
        $rows[] = [
            __('ID', 'rapls-ai-chatbot'),
            __('Question', 'rapls-ai-chatbot'),
            __('Answer', 'rapls-ai-chatbot'),
            __('Category', 'rapls-ai-chatbot'),
            __('Priority', 'rapls-ai-chatbot'),
            __('Active', 'rapls-ai-chatbot'),
            __('Created At', 'rapls-ai-chatbot'),
        ];

        foreach ($items as $item) {
            $rows[] = [
                $item['id'],
                self::csv_safe_cell($item['title']),
                self::csv_safe_cell($item['content']),
                self::csv_safe_cell($item['category'] ?? ''),
                $item['priority'] ?? 0,
                !empty($item['is_active']) ? 'Yes' : 'No',
                $item['created_at'],
            ];
        }

        return $rows;
    }

    /**
     * Sanitize a cell value to prevent CSV injection.
     */
    private static function csv_safe_cell($value): string {
        $s = str_replace("\r\n", "\n", (string) $value);
        // Check for formula chars after stripping leading whitespace (Excel ignores leading spaces)
        $trimmed = ltrim($s);
        if ($trimmed !== '' && preg_match('/^[=+\-@\t]/', $trimmed)) {
            return "'" . $s;
        }
        return $s;
    }

    /**
     * Parse CSV file
     */
    private static function parse_csv_file($filepath) {
        $content = [];
        $row_count = 0;

        if (($handle = fopen($filepath, 'r')) !== false) {
            // Strip UTF-8 BOM if present (common in Excel CSV exports)
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = null;

            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = $row;
                    continue;
                }

                $row_count++;
                if ($row_count > self::IMPORT_MAX_CSV_ROWS) {
                    break;
                }

                // Combine headers and values into text
                $line_parts = [];
                foreach ($row as $index => $value) {
                    $header = $headers[$index] ?? "Column" . ($index + 1);
                    if (!empty($value)) {
                        $line_parts[] = "{$header}: {$value}";
                    }
                }
                if (!empty($line_parts)) {
                    $content[] = implode("\n", $line_parts);
                }
            }
            fclose($handle);
        }

        return implode("\n\n---\n\n", $content);
    }
}
