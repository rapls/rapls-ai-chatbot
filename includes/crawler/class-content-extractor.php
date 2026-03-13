<?php
/**
 * Content extractor class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Content_Extractor {

    /**
     * Extract text from post
     */
    public function extract(WP_Post $post): string {
        $content = $post->post_content;

        // Process Gutenberg blocks
        if (has_blocks($content)) {
            $content = $this->process_blocks($content);
        }

        // Expand shortcodes
        $content = do_shortcode($content);

        // Check if enhanced extraction is enabled (Pro feature)
        $settings = get_option('wpaic_settings', []);
        $pro_features = $settings['pro_features'] ?? [];
        $enhanced = !empty($pro_features['enhanced_content_extraction'])
            && class_exists('WPAIC_Pro_Features')
            && WPAIC_Pro_Features::get_instance()->is_pro();

        if ($enhanced) {
            $content = $this->enhanced_strip_html($content);
        } else {
            $content = $this->strip_html($content);
        }

        // Normalize whitespace
        $content = $this->normalize_whitespace($content);

        // Add meta information
        $result = $this->build_content($post, $content);

        // Extract meta tags if enhanced mode (append to result)
        if ($enhanced) {
            $meta_info = $this->extract_meta_tags($post);
            if (!empty($meta_info)) {
                $result .= "\n\n## Meta Information\n" . $meta_info;
            }
        }

        return $result;
    }

    /**
     * Process Gutenberg blocks
     */
    private function process_blocks(string $content): string {
        // Render blocks
        $blocks = parse_blocks($content);
        $output = '';

        foreach ($blocks as $block) {
            $output .= render_block($block);
        }

        return $output;
    }

    /**
     * Strip HTML tags and extract text
     */
    private function strip_html(string $content): string {
        // Completely remove scripts and styles
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        // Convert specific tags to newlines (maintain paragraph structure)
        $content = preg_replace('/<\/(p|div|h[1-6]|li|br|tr)>/i', "\n", $content);
        $content = preg_replace('/<(br|hr)[^>]*>/i', "\n", $content);

        // Remove remaining HTML tags
        $content = wp_strip_all_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $content;
    }

    /**
     * Enhanced HTML extraction preserving structure (Pro feature)
     * Converts HTML tags to readable text format instead of stripping them
     */
    private function enhanced_strip_html(string $content): string {
        // Fallback if DOMDocument is not available
        if (!class_exists('DOMDocument')) {
            return $this->strip_html($content);
        }

        // Remove scripts and styles
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Wrap in root element for DOMDocument
        $html = '<html><head><meta charset="UTF-8"></head><body>' . $content . '</body></html>';

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        // Use mb_encode_numericentity instead of deprecated HTML-ENTITIES (PHP 8.2+)
        $converted = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $doc->loadHTML($converted, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            // Fallback to basic extraction
            return $this->strip_html($content);
        }

        $output = $this->dom_to_text($body);

        // Decode entities
        $output = html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $output;
    }

    /**
     * Recursively convert DOM nodes to structured text
     */
    private function dom_to_text(DOMNode $node): string {
        $output = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = $child->textContent;
                // Normalize whitespace in text nodes
                $text = preg_replace('/\s+/', ' ', $text);
                $output .= $text;
                continue;
            }

            if (!($child instanceof DOMElement)) {
                continue;
            }

            $tag = strtolower($child->tagName);

            switch ($tag) {
                // Headings → Markdown headings
                case 'h1':
                    $output .= "\n\n# " . trim($this->dom_to_text($child)) . "\n";
                    break;
                case 'h2':
                    $output .= "\n\n## " . trim($this->dom_to_text($child)) . "\n";
                    break;
                case 'h3':
                    $output .= "\n\n### " . trim($this->dom_to_text($child)) . "\n";
                    break;
                case 'h4':
                case 'h5':
                case 'h6':
                    $output .= "\n\n#### " . trim($this->dom_to_text($child)) . "\n";
                    break;

                // Paragraphs and divs
                case 'p':
                case 'div':
                case 'section':
                case 'article':
                case 'main':
                case 'aside':
                    $inner = trim($this->dom_to_text($child));
                    if (!empty($inner)) {
                        $output .= "\n" . $inner . "\n";
                    }
                    break;

                // Lists
                case 'ul':
                case 'ol':
                    $output .= "\n" . $this->extract_list($child, $tag === 'ol') . "\n";
                    break;
                case 'li':
                    $output .= '- ' . trim($this->dom_to_text($child)) . "\n";
                    break;

                // Tables
                case 'table':
                    $output .= "\n" . $this->extract_table($child) . "\n";
                    break;

                // Code blocks
                case 'pre':
                    $inner = trim($child->textContent);
                    if (!empty($inner)) {
                        $output .= "\n```\n" . $inner . "\n```\n";
                    }
                    break;
                case 'code':
                    $parent_tag = $child->parentNode ? strtolower($child->parentNode->tagName ?? '') : '';
                    if ($parent_tag === 'pre') {
                        // Already handled by <pre>
                        $output .= $child->textContent;
                    } else {
                        $output .= '`' . trim($child->textContent) . '`';
                    }
                    break;

                // Line breaks
                case 'br':
                    $output .= "\n";
                    break;
                case 'hr':
                    $output .= "\n---\n";
                    break;

                // Inline elements → just extract text
                case 'span':
                case 'strong':
                case 'b':
                case 'em':
                case 'i':
                case 'a':
                case 'mark':
                case 'small':
                case 'sub':
                case 'sup':
                case 'abbr':
                case 'cite':
                case 'q':
                case 'time':
                case 'label':
                    $output .= $this->dom_to_text($child);
                    break;

                // Blockquote
                case 'blockquote':
                    $inner = trim($this->dom_to_text($child));
                    if (!empty($inner)) {
                        $lines = explode("\n", $inner);
                        $quoted = array_map(function ($line) {
                            return '> ' . $line;
                        }, $lines);
                        $output .= "\n" . implode("\n", $quoted) . "\n";
                    }
                    break;

                // Definition list
                case 'dt':
                    $output .= "\n**" . trim($this->dom_to_text($child)) . "**: ";
                    break;
                case 'dd':
                    $output .= trim($this->dom_to_text($child)) . "\n";
                    break;

                // Skip hidden/irrelevant
                case 'script':
                case 'style':
                case 'noscript':
                case 'svg':
                case 'canvas':
                case 'iframe':
                case 'form':
                case 'input':
                case 'button':
                case 'select':
                case 'textarea':
                case 'nav':
                case 'footer':
                    break;

                // Other elements → recurse
                default:
                    $output .= $this->dom_to_text($child);
                    break;
            }
        }

        return $output;
    }

    /**
     * Extract list items with proper formatting
     */
    private function extract_list(DOMElement $list, bool $ordered = false): string {
        $output = '';
        $index = 1;

        foreach ($list->childNodes as $child) {
            if (!($child instanceof DOMElement) || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $prefix = $ordered ? ($index . '. ') : '- ';
            $text = trim($this->dom_to_text($child));
            if (!empty($text)) {
                $output .= $prefix . $text . "\n";
                $index++;
            }
        }

        return $output;
    }

    /**
     * Extract table data in structured format
     */
    private function extract_table(DOMElement $table): string {
        $rows = [];
        $headers = [];

        // Find all rows (in thead, tbody, or direct children)
        $tr_nodes = $table->getElementsByTagName('tr');

        foreach ($tr_nodes as $tr) {
            $cells = [];
            $is_header = false;

            foreach ($tr->childNodes as $cell) {
                if (!($cell instanceof DOMElement)) {
                    continue;
                }
                $tag = strtolower($cell->tagName);
                if ($tag === 'th') {
                    $is_header = true;
                    $cells[] = trim($cell->textContent);
                } elseif ($tag === 'td') {
                    $cells[] = trim($cell->textContent);
                }
            }

            if (empty($cells)) {
                continue;
            }

            if ($is_header && empty($headers)) {
                $headers = $cells;
            } else {
                $rows[] = $cells;
            }
        }

        if (empty($rows) && empty($headers)) {
            return '';
        }

        // Format as readable text
        $output = '';
        if (!empty($headers)) {
            $output .= '[Table: ' . implode(' | ', $headers) . "]\n";
        }
        foreach ($rows as $row) {
            if (!empty($headers) && count($headers) === count($row)) {
                // Key-value format if headers match
                $pairs = [];
                foreach ($headers as $i => $header) {
                    if (!empty($row[$i])) {
                        $pairs[] = $header . ': ' . $row[$i];
                    }
                }
                $output .= implode(', ', $pairs) . "\n";
            } else {
                $output .= implode(' | ', $row) . "\n";
            }
        }

        return $output;
    }

    /**
     * Extract meta tags (description, keywords, og tags) from post
     */
    private function extract_meta_tags(WP_Post $post): string {
        // Get the rendered page URL and extract meta from post content context
        $parts = [];

        // SEO description from popular plugins
        $seo_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (empty($seo_desc)) {
            $seo_desc = get_post_meta($post->ID, '_aioseo_description', true);
        }
        if (empty($seo_desc)) {
            $seo_desc = get_post_meta($post->ID, 'rank_math_description', true);
        }
        if (!empty($seo_desc)) {
            $parts[] = 'Description: ' . sanitize_text_field($seo_desc);
        }

        // SEO keywords
        $seo_keywords = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
        if (empty($seo_keywords)) {
            $seo_keywords = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        }
        if (!empty($seo_keywords)) {
            $parts[] = 'Keywords: ' . sanitize_text_field($seo_keywords);
        }

        return implode("\n", $parts);
    }

    /**
     * Normalize whitespace
     */
    private function normalize_whitespace(string $content): string {
        // Multiple spaces to one
        $content = preg_replace('/[ \t]+/', ' ', $content);

        // More than 3 newlines to 2
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // Trim each line
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);

        // Remove empty lines
        $content = preg_replace('/^\s*$/m', '', $content);

        return trim($content);
    }

    /**
     * Extract WooCommerce product data as structured text for AI context.
     * Returns empty string if WooCommerce is not active or post is not a product.
     */
    private function extract_woocommerce_data(WP_Post $post): string {
        if (!class_exists('WooCommerce') || $post->post_type !== 'product') {
            return '';
        }

        $product = wc_get_product($post->ID);
        if (!$product) {
            return '';
        }

        $parts = ['## Product Details'];

        // Price
        $price = $product->get_price();
        if ($price !== '') {
            $parts[] = 'Price: ' . wp_strip_all_tags(wc_price($price));
            if ($product->is_on_sale()) {
                $regular = $product->get_regular_price();
                if ($regular !== '') {
                    $parts[] = 'Regular Price: ' . wp_strip_all_tags(wc_price($regular));
                }
            }
        }

        // SKU
        $sku = $product->get_sku();
        if (!empty($sku)) {
            $parts[] = 'SKU: ' . $sku;
        }

        // Stock status
        if ($product->is_in_stock()) {
            $qty = $product->get_stock_quantity();
            $parts[] = 'Stock: In stock' . ($qty !== null ? " ({$qty})" : '');
        } else {
            $parts[] = 'Stock: Out of stock';
        }

        // Weight
        $weight = $product->get_weight();
        if (!empty($weight)) {
            $parts[] = 'Weight: ' . $weight . get_option('woocommerce_weight_unit', 'kg');
        }

        // Dimensions
        if ($product->has_dimensions()) {
            $parts[] = 'Dimensions: ' . wc_format_dimensions($product->get_dimensions(false));
        }

        // Categories
        $categories = wc_get_product_category_list($product->get_id());
        if (!empty($categories)) {
            $parts[] = 'Category: ' . wp_strip_all_tags($categories);
        }

        // Tags
        $tags = wc_get_product_tag_list($product->get_id());
        if (!empty($tags)) {
            $parts[] = 'Tags: ' . wp_strip_all_tags($tags);
        }

        // Attributes
        $attributes = $product->get_attributes();
        foreach ($attributes as $attr) {
            if ($attr instanceof WC_Product_Attribute && $attr->get_visible()) {
                $name = wc_attribute_label($attr->get_name(), $product);
                $values = [];
                if ($attr->is_taxonomy()) {
                    $terms = wc_get_product_terms($product->get_id(), $attr->get_name(), ['fields' => 'names']);
                    if (!is_wp_error($terms)) {
                        $values = $terms;
                    }
                } else {
                    $values = $attr->get_options();
                }
                if (!empty($values)) {
                    $parts[] = $name . ': ' . implode(', ', $values);
                }
            }
        }

        // Variable product: variation info
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $count = count($variations);
            if ($count > 0) {
                $parts[] = "Variations: {$count} options";
                $min = $product->get_variation_price('min');
                $max = $product->get_variation_price('max');
                if ($min !== '' && $max !== '' && $min !== $max) {
                    $parts[] = 'Price Range: ' . wp_strip_all_tags(wc_price($min)) . ' - ' . wp_strip_all_tags(wc_price($max));
                }
            }
        }

        // External/affiliate product
        if ($product->is_type('external')) {
            $url = $product->get_product_url();
            if (!empty($url)) {
                $parts[] = 'External URL: ' . $url;
            }
        }

        return count($parts) > 1 ? implode("\n", $parts) : '';
    }

    /**
     * Build final content
     */
    private function build_content(WP_Post $post, string $body): string {
        $parts = [];

        // Title
        $parts[] = '# ' . $post->post_title;

        // Add excerpt if exists
        if (!empty($post->post_excerpt)) {
            $parts[] = 'Summary: ' . $post->post_excerpt;
        }

        // Post type name
        $post_type_obj = get_post_type_object($post->post_type);
        if ($post_type_obj && $post->post_type !== 'post' && $post->post_type !== 'page') {
            $parts[] = 'Content Type: ' . $post_type_obj->labels->singular_name;
        }

        // Get all taxonomies
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            // Skip private taxonomies
            if (!$taxonomy->public) {
                continue;
            }
            
            $terms = get_the_terms($post->ID, $taxonomy->name);
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_names = array_map(fn($term) => $term->name, $terms);
                $parts[] = $taxonomy->labels->singular_name . ': ' . implode(', ', $term_names);
            }
        }

        // Get custom fields
        $custom_fields = get_post_meta($post->ID);
        if (!empty($custom_fields)) {
            $cf_parts = [];
            foreach ($custom_fields as $key => $values) {
                // Skip internal fields (starting with _)
                if (strpos($key, '_') === 0) {
                    continue;
                }

                foreach ($values as $value) {
                    // Skip serialized data and empty values
                    if (empty($value) || is_serialized($value)) {
                        continue;
                    }

                    // Convert array to string
                    if (is_array($value)) {
                        $value = implode(', ', array_filter($value, 'is_string'));
                    }

                    // Add string values only
                    if (is_string($value) && !empty(trim($value))) {
                        // Truncate long values
                        if (wpaic_mb_strlen($value) > 500) {
                            $value = wpaic_mb_substr($value, 0, 500) . '...';
                        }
                        $cf_parts[] = $key . ': ' . $value;
                    }
                }
            }
            if (!empty($cf_parts)) {
                $parts[] = '';
                $parts[] = '## Additional Info';
                $parts = array_merge($parts, $cf_parts);
            }
        }

        // WooCommerce product data (if applicable)
        $wc_data = $this->extract_woocommerce_data($post);
        if (!empty($wc_data)) {
            $parts[] = '';
            $parts[] = $wc_data;
        }

        // Body
        $parts[] = '';
        $parts[] = '## Content';
        $parts[] = $body;

        return implode("\n", $parts);
    }

}
