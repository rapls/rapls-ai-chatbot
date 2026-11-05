<?php
/**
 * Link guard — removes links to own-site pages that do not exist.
 *
 * LLMs sometimes make up plausible URLs for a site's posts. When the
 * "remove_unknown_links" setting is on, every link to this site in an AI
 * answer is checked before the answer is saved or shown; links to pages that
 * neither the site-learning index nor WordPress knows are unlinked (Markdown
 * and HTML links keep their text, bare URLs are dropped). Links to other
 * sites are left alone.
 *
 * @package Rapls_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAPLSAICH_Link_Guard {

    /**
     * Characters that end a bare URL when they trail it (sentence punctuation,
     * closing brackets, Japanese punctuation).
     */
    const TRAILING_PUNCT = '.,;:!?)]}>\'"。、，．！？）」』】';

    /**
     * Remove links to own-site pages that do not exist.
     *
     * @param string $content AI answer (Markdown or HTML).
     * @return array{content:string, removed:string[]}
     */
    public static function clean(string $content): array {
        $result = ['content' => $content, 'removed' => []];
        if ($content === '' || (strpos($content, '://') === false && strpos($content, '](/') === false && stripos($content, 'href=') === false)) {
            return $result;
        }

        $hosts = self::own_hosts();
        if (empty($hosts)) {
            return $result;
        }

        $verdicts = [];
        $is_valid = function (string $url) use (&$verdicts, $hosts): bool {
            $absolute = self::absolutize($url);
            if ($absolute === '' || !self::is_own_url($absolute, $hosts)) {
                return true; // other sites and non-HTTP links are not ours to judge
            }
            if (!array_key_exists($absolute, $verdicts)) {
                $valid = self::page_exists($absolute);
                /**
                 * Filter whether a link to this site in an AI answer is kept.
                 *
                 * @param bool   $valid True when the page was found.
                 * @param string $url   Absolute URL from the answer.
                 */
                $verdicts[$absolute] = (bool) apply_filters('raplsaich_link_guard_is_valid_url', $valid, $absolute);
            }
            return $verdicts[$absolute];
        };

        $removed = [];
        $text    = $content;

        // 1. HTML anchors: keep the link text.
        $text = (string) preg_replace_callback(
            '/<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            function ($m) use ($is_valid, &$removed) {
                if ($is_valid(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return $m[0];
                }
                $removed[] = $m[2];
                return self::link_text($m[3], $m[2]);
            },
            $text
        );

        // 2. Markdown links [text](url "title"): keep the text.
        $text = (string) preg_replace_callback(
            '/(!?)\[([^\]\n]*)\]\(\s*<?((?:https?:\/\/|\/(?!\/))[^\s)>]*)>?(?:\s+"[^"]*")?\s*\)/i',
            function ($m) use ($is_valid, &$removed) {
                if ($is_valid($m[3])) {
                    return $m[0];
                }
                $removed[] = $m[3];
                return $m[1] === '!' ? '' : self::link_text($m[2], $m[3]);
            },
            $text
        );

        // 3. Bare URLs not inside an attribute or a kept Markdown link.
        $text = (string) preg_replace_callback(
            '/(?<![\w"\'=\/])(?<!\]\()(?<!\]\(<)https?:\/\/[^\s<>"\'\]\[()（）「」]+/iu',
            function ($m) use ($is_valid, &$removed) {
                $url  = $m[0];
                $tail = '';
                while ($url !== '' && self::is_trailing_punct(mb_substr($url, -1))) {
                    $tail = mb_substr($url, -1) . $tail;
                    $url  = mb_substr($url, 0, -1);
                }
                if ($url === '' || $is_valid($url)) {
                    return $m[0];
                }
                $removed[] = $url;
                return $tail;
            },
            $text
        );

        if (empty($removed)) {
            return $result;
        }

        // Tidy what removed bare URLs leave behind: empty brackets, a dangling
        // "URL:" label, doubled spaces.
        $text = (string) preg_replace('/[ \t]*(?:\(\s*\)|（\s*）|<\s*>)/u', '', $text);
        $text = (string) preg_replace('/(?:URL|Link|Links|リンク)\s*[:：]\s*$/mu', '', $text);
        $text = (string) preg_replace('/\s*[:：]\s*([.。])/u', '$1', $text);
        $text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = (string) preg_replace('/[ \t]+$/m', '', $text);

        $removed = array_values(array_unique($removed));

        /**
         * Fires after links to non-existent pages were removed from an answer.
         *
         * @param string[] $removed URLs that were removed.
         * @param string   $content The cleaned answer.
         */
        do_action('raplsaich_link_guard_removed', $removed, $text);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[RAPLSAICH] Link guard removed ' . count($removed) . ' link(s): ' . implode(', ', $removed));
        }

        return ['content' => $text, 'removed' => $removed];
    }

    /**
     * Text to keep in place of an unlinked link.
     */
    private static function link_text(string $label, string $url): string {
        $label = trim(wp_strip_all_tags($label));
        // A label that is just the URL would bring the made-up URL back.
        if ($label === '' || stripos($label, 'http') === 0 || $label === $url || strpos($label, '/') === 0) {
            return '';
        }
        return $label;
    }

    private static function is_trailing_punct(string $char): bool {
        return $char !== '' && mb_strpos(self::TRAILING_PUNCT, $char) !== false;
    }

    /**
     * Hosts that count as this site, without "www.".
     *
     * @return string[]
     */
    private static function own_hosts(): array {
        $hosts = [];
        foreach ([home_url('/'), site_url('/')] as $u) {
            $h = wp_parse_url($u, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $hosts[] = self::bare_host($h);
            }
        }
        return array_values(array_unique($hosts));
    }

    private static function bare_host(string $host): string {
        $host = strtolower($host);
        return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
    }

    /**
     * Turn a root-relative path into an absolute URL; '' for anything that is
     * not http(s).
     */
    private static function absolutize(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return home_url($url);
        }
        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    private static function is_own_url(string $url, array $hosts): bool {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return is_string($host) && in_array(self::bare_host($host), $hosts, true);
    }

    /**
     * Whether an own-site URL points at something that exists.
     */
    private static function page_exists(string $url): bool {
        $parts = wp_parse_url($url);
        $path  = isset($parts['path']) ? rawurldecode($parts['path']) : '/';

        // Home page, with or without a query string.
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        if (untrailingslashit($path) === untrailingslashit($home_path) && empty($parts['query'])) {
            return true;
        }

        if (self::in_index($url)) {
            return true;
        }

        $post_id = url_to_postid($url);
        if ($post_id > 0 && get_post_status($post_id) === 'publish') {
            return true;
        }

        if (self::upload_exists($url)) {
            return true;
        }

        return self::archive_exists($path, $home_path);
    }

    /**
     * Whether the site-learning index has this URL, ignoring scheme, "www.",
     * trailing slash and fragment.
     */
    private static function in_index(string $url): bool {
        $table = raplsaich_validated_table('raplsaich_index');
        if ($table === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        $host  = self::bare_host((string) ($parts['host'] ?? ''));
        $path  = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        $variants = [];
        foreach (['https://', 'http://'] as $scheme) {
            foreach ([$host, 'www.' . $host] as $h) {
                foreach ([untrailingslashit($path), trailingslashit($path)] as $p) {
                    $variants[] = $scheme . $h . $p . $query;
                }
            }
        }
        $variants = array_values(array_unique($variants));

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($variants), '%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE url IN ({$placeholders}) LIMIT 1",
            ...$variants
        ));
        return !empty($found);
    }

    /**
     * Whether the URL is a file inside the uploads folder that exists.
     */
    private static function upload_exists(string $url): bool {
        $uploads = wp_get_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return false;
        }
        $base_path = (string) wp_parse_url($uploads['baseurl'], PHP_URL_PATH);
        $path      = rawurldecode((string) wp_parse_url($url, PHP_URL_PATH));
        if ($base_path === '' || strpos($path, trailingslashit($base_path)) !== 0) {
            return false;
        }
        $relative = substr($path, strlen(trailingslashit($base_path)));
        $file     = realpath(trailingslashit($uploads['basedir']) . $relative);
        $basedir  = realpath($uploads['basedir']);
        return $file !== false && $basedir !== false && strpos($file, $basedir) === 0 && is_file($file);
    }

    /**
     * Whether the path is a term archive (category, tag, custom taxonomy) or
     * a post type archive that exists. Compares against the links WordPress
     * builds itself, so permalink prefixes such as /blog/ are handled.
     */
    private static function archive_exists(string $path, string $home_path): bool {
        $relative = trim(substr($path, strlen(untrailingslashit($home_path))), '/');
        if ($relative === '') {
            return false;
        }
        $wanted = untrailingslashit($path);
        $same_path = function ($link) use ($wanted): bool {
            if (!is_string($link) || $link === '') {
                return false;
            }
            return untrailingslashit(rawurldecode((string) wp_parse_url($link, PHP_URL_PATH))) === $wanted;
        };

        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if (!empty($type->has_archive) && $same_path(get_post_type_archive_link($type->name))) {
                return true;
            }
        }

        $segments = explode('/', $relative);
        $last     = sanitize_title(end($segments));
        foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
            $term = get_term_by('slug', $last, $taxonomy);
            if ($term && !is_wp_error($term) && $same_path(get_term_link($term))) {
                return true;
            }
        }

        return false;
    }
}
