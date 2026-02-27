<?php
/**
 * DOCX text extractor using ZipArchive + DOMDocument
 *
 * Requires PHP ZipArchive extension (bundled with PHP 7.4+).
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_DOCX_Parser {

    /**
     * Extract text content from a DOCX file.
     *
     * @param string $filepath Path to the DOCX file.
     * @return string Extracted text, or empty string if extraction fails.
     */
    public static function extract_text(string $filepath): string {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            return '';
        }

        // Read the main document content
        $xml_content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml_content === false || $xml_content === '') {
            return '';
        }

        return self::parse_document_xml($xml_content);
    }

    /**
     * Parse word/document.xml and extract text.
     *
     * @param string $xml_content Raw XML content.
     * @return string Extracted text.
     */
    private static function parse_document_xml(string $xml_content): string {
        // Suppress XML parsing warnings (malformed docs)
        $prev_use_errors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadXML($xml_content);

        libxml_clear_errors();
        libxml_use_internal_errors($prev_use_errors);

        $paragraphs = $dom->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'p'
        );

        $text_lines = [];

        foreach ($paragraphs as $paragraph) {
            $line = self::extract_paragraph_text($paragraph);
            $text_lines[] = $line;
        }

        // Join paragraphs with newlines, collapse excessive blank lines
        $text = implode("\n", $text_lines);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extract text from a single <w:p> paragraph element.
     *
     * @param DOMElement $paragraph The paragraph node.
     * @return string Paragraph text.
     */
    private static function extract_paragraph_text(DOMElement $paragraph): string {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $text = '';

        // Iterate over child nodes to maintain order
        foreach ($paragraph->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            // <w:r> (run) — contains text and formatting
            if ($child->localName === 'r') {
                $text .= self::extract_run_text($child, $ns);
            }

            // <w:hyperlink> — may contain runs
            if ($child->localName === 'hyperlink') {
                foreach ($child->childNodes as $hchild) {
                    if ($hchild->nodeType === XML_ELEMENT_NODE && $hchild->localName === 'r') {
                        $text .= self::extract_run_text($hchild, $ns);
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Extract text from a single <w:r> run element.
     *
     * @param DOMElement $run The run node.
     * @param string     $ns  Word processing namespace.
     * @return string Run text.
     */
    private static function extract_run_text(DOMElement $run, string $ns): string {
        $text = '';

        foreach ($run->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            switch ($node->localName) {
                case 't': // <w:t> — text content
                    $text .= $node->textContent;
                    break;

                case 'br': // <w:br/> — line break
                    $text .= "\n";
                    break;

                case 'tab': // <w:tab/> — tab character
                    $text .= "\t";
                    break;
            }
        }

        return $text;
    }
}
