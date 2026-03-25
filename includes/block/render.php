<?php
/**
 * Server-side rendering for the AI Chatbot block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused).
 * @param WP_Block $block      Block instance.
 * @return string Rendered HTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

$raplsaich_shortcode_atts = '';

if (!empty($attributes['height'])) {
    $raplsaich_shortcode_atts .= ' height="' . esc_attr($attributes['height']) . '"';
}
if (!empty($attributes['theme'])) {
    $raplsaich_shortcode_atts .= ' theme="' . esc_attr($attributes['theme']) . '"';
}
if (!empty($attributes['bot'])) {
    $raplsaich_shortcode_atts .= ' bot="' . esc_attr($attributes['bot']) . '"';
}

echo do_shortcode('[rapls_chatbot' . $raplsaich_shortcode_atts . ']');
