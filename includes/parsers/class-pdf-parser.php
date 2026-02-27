<?php
/**
 * Pure PHP PDF text extractor
 *
 * Supports text-based PDFs with FlateDecode streams.
 * Does NOT support: scanned-image PDFs, encrypted PDFs, CIDFont/Type0 fonts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_PDF_Parser {

    /**
     * Extract text content from a PDF file.
     *
     * @param string $filepath Path to the PDF file.
     * @return string Extracted text, or empty string if extraction fails.
     */
    public static function extract_text(string $filepath): string {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents($filepath);
        if ($content === false || strlen($content) < 5) {
            return '';
        }

        // Verify PDF header
        if (strpos($content, '%PDF-') !== 0) {
            return '';
        }

        // Check for encryption (encrypted PDFs cannot be parsed)
        if (preg_match('/\/Encrypt\s/', $content)) {
            return '';
        }

        $text_parts = [];

        // Extract all stream blocks
        if (preg_match_all('/stream\r?\n(.+?)\r?\nendstream/s', $content, $stream_matches)) {
            foreach ($stream_matches[1] as $stream_data) {
                $decoded = self::decode_stream($stream_data, $content);
                if ($decoded === '') {
                    continue;
                }

                $extracted = self::extract_text_from_content($decoded);
                if ($extracted !== '') {
                    $text_parts[] = $extracted;
                }
            }
        }

        $result = implode("\n\n", $text_parts);

        // Clean up the result
        $result = self::clean_text($result);

        return $result;
    }

    /**
     * Attempt to decode a PDF stream.
     *
     * @param string $stream_data Raw stream data.
     * @param string $full_content Full PDF content (for filter detection).
     * @return string Decoded stream, or empty string on failure.
     */
    private static function decode_stream(string $stream_data, string $full_content): string {
        // Try FlateDecode (zlib) first — most common in modern PDFs
        if (function_exists('gzuncompress')) {
            $decoded = @gzuncompress($stream_data);
            if ($decoded !== false) {
                return $decoded;
            }

            // Try with zlib header variations
            $decoded = @gzinflate($stream_data);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        // If stream is already plain text (no compression), check for text operators
        if (preg_match('/\b(BT|ET|Tj|TJ)\b/', $stream_data)) {
            return $stream_data;
        }

        return '';
    }

    /**
     * Extract text from decoded PDF content stream.
     *
     * Parses BT...ET blocks and extracts text from Tj, TJ, ', '' operators.
     *
     * @param string $content Decoded content stream.
     * @return string Extracted text.
     */
    private static function extract_text_from_content(string $content): string {
        $text = '';

        // Extract text from BT...ET blocks (text objects)
        if (!preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $bt_matches)) {
            return '';
        }

        foreach ($bt_matches[1] as $text_block) {
            $block_text = '';

            // Tj operator: (string) Tj
            if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $text_block, $tj_matches)) {
                foreach ($tj_matches[1] as $str) {
                    $block_text .= self::decode_pdf_string($str);
                }
            }

            // TJ operator: [(string) num (string) ...] TJ
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $text_block, $tj_array_matches)) {
                foreach ($tj_array_matches[1] as $array_content) {
                    if (preg_match_all('/\(([^)]*)\)/', $array_content, $str_matches)) {
                        foreach ($str_matches[1] as $str) {
                            $block_text .= self::decode_pdf_string($str);
                        }
                    }
                    // Check for large negative kerning = word space
                    $block_text = preg_replace_callback(
                        '/\)\s*(-?\d+)\s*\(/',
                        function ($m) {
                            // Large negative values typically represent word spacing
                            return (abs((int) $m[1]) > 100) ? ' ' : '';
                        },
                        $block_text
                    );
                }
            }

            // ' operator: (string) '  — move to next line and show text
            if (preg_match_all("/\(([^)]*)\)\s*'/s", $text_block, $quote_matches)) {
                foreach ($quote_matches[1] as $str) {
                    $block_text .= "\n" . self::decode_pdf_string($str);
                }
            }

            // '' operator: num num (string) ''
            if (preg_match_all("/\(([^)]*)\)\s*''/s", $text_block, $dquote_matches)) {
                foreach ($dquote_matches[1] as $str) {
                    $block_text .= "\n" . self::decode_pdf_string($str);
                }
            }

            // Check for Td/TD (text positioning) to detect line breaks
            // Large Y offset often means new line
            if (preg_match('/\d+\s+(-?\d+\.?\d*)\s+Td/i', $text_block, $td_match)) {
                $y_offset = (float) $td_match[1];
                if (abs($y_offset) > 1) {
                    $block_text = "\n" . $block_text;
                }
            }

            if ($block_text !== '') {
                $text .= $block_text . ' ';
            }
        }

        return $text;
    }

    /**
     * Decode PDF string escape sequences.
     *
     * @param string $str PDF string content (without enclosing parentheses).
     * @return string Decoded string.
     */
    private static function decode_pdf_string(string $str): string {
        // Handle PDF escape sequences
        $replacements = [
            '\\n'  => "\n",
            '\\r'  => "\r",
            '\\t'  => "\t",
            '\\b'  => "\x08",
            '\\f'  => "\x0C",
            '\\('  => '(',
            '\\)'  => ')',
            '\\\\' => '\\',
        ];

        $str = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $str
        );

        // Handle octal character codes (\ddd)
        $str = preg_replace_callback('/\\\\(\d{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $str);

        return $str;
    }

    /**
     * Clean up extracted text.
     *
     * @param string $text Raw extracted text.
     * @return string Cleaned text.
     */
    private static function clean_text(string $text): string {
        // Remove null bytes
        $text = str_replace("\0", '', $text);

        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // Remove excessive whitespace within lines
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Collapse multiple blank lines into one
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim each line
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);

        return trim($text);
    }
}
