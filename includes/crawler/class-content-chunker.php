<?php
/**
 * Content chunker class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Content_Chunker {

    /**
     * Default chunk size (characters)
     */
    private int $chunk_size = 1000;

    /**
     * Overlap size (characters)
     */
    private int $overlap = 100;

    /**
     * Set chunk size
     */
    public function set_chunk_size(int $size): void {
        $this->chunk_size = $size;
    }

    /**
     * Set overlap size
     */
    public function set_overlap(int $overlap): void {
        $this->overlap = $overlap;
    }

    /**
     * Split text into chunks
     */
    public function split(string $text, ?int $chunk_size = null): array {
        $chunk_size = $chunk_size ?? $this->chunk_size;

        // Return as-is if text is short
        if (mb_strlen($text) <= $chunk_size) {
            return [$text];
        }

        // Try to split by paragraphs
        $chunks = $this->split_by_paragraphs($text, $chunk_size);

        // Split by sentences if any chunk is too large
        $final_chunks = [];
        foreach ($chunks as $chunk) {
            if (mb_strlen($chunk) > $chunk_size * 1.5) {
                $sub_chunks = $this->split_by_sentences($chunk, $chunk_size);
                $final_chunks = array_merge($final_chunks, $sub_chunks);
            } else {
                $final_chunks[] = $chunk;
            }
        }

        return $final_chunks;
    }

    /**
     * Split by paragraphs
     */
    private function split_by_paragraphs(string $text, int $chunk_size): array {
        $paragraphs = preg_split('/\n\n+/', $text);
        $chunks = [];
        $current_chunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            $potential_chunk = $current_chunk . ($current_chunk ? "\n\n" : '') . $paragraph;

            if (mb_strlen($potential_chunk) <= $chunk_size) {
                $current_chunk = $potential_chunk;
            } else {
                if (!empty($current_chunk)) {
                    $chunks[] = $current_chunk;
                }
                $current_chunk = $paragraph;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Split by sentences
     */
    private function split_by_sentences(string $text, int $chunk_size): array {
        // Japanese and English sentence end patterns
        $sentences = preg_split('/(?<=[。！？.!?])\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current_chunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            $potential_chunk = $current_chunk . ($current_chunk ? ' ' : '') . $sentence;

            if (mb_strlen($potential_chunk) <= $chunk_size) {
                $current_chunk = $potential_chunk;
            } else {
                if (!empty($current_chunk)) {
                    $chunks[] = $current_chunk;
                }

                // Force split if single sentence exceeds chunk size
                if (mb_strlen($sentence) > $chunk_size) {
                    $forced_chunks = $this->force_split($sentence, $chunk_size);
                    $chunks = array_merge($chunks, array_slice($forced_chunks, 0, -1));
                    $current_chunk = end($forced_chunks);
                } else {
                    $current_chunk = $sentence;
                }
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Force split (last resort)
     */
    private function force_split(string $text, int $chunk_size): array {
        $chunks = [];
        $length = mb_strlen($text);
        $position = 0;

        while ($position < $length) {
            $chunk = mb_substr($text, $position, $chunk_size);
            $chunks[] = $chunk;
            $position += $chunk_size - $this->overlap;
        }

        return $chunks;
    }

    /**
     * Create chunks with overlap
     */
    public function split_with_overlap(string $text, ?int $chunk_size = null): array {
        $chunk_size = $chunk_size ?? $this->chunk_size;
        $chunks = $this->split($text, $chunk_size);

        // No processing needed if only one chunk
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $overlapped_chunks = [];

        for ($i = 0; $i < count($chunks); $i++) {
            $chunk = $chunks[$i];

            // Add end of previous chunk
            if ($i > 0 && $this->overlap > 0) {
                $prev_chunk = $chunks[$i - 1];
                $overlap_text = mb_substr($prev_chunk, -$this->overlap);
                $chunk = $overlap_text . ' ... ' . $chunk;
            }

            $overlapped_chunks[] = $chunk;
        }

        return $overlapped_chunks;
    }
}
