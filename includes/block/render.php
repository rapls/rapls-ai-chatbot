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

$shortcode_atts = '';

if (!empty($attributes['height'])) {
    $shortcode_atts .= ' height="' . esc_attr($attributes['height']) . '"';
}
if (!empty($attributes['theme'])) {
    $shortcode_atts .= ' theme="' . esc_attr($attributes['theme']) . '"';
}
if (!empty($attributes['bot'])) {
    $shortcode_atts .= ' bot="' . esc_attr($attributes['bot']) . '"';
}

echo do_shortcode('[rapls_chatbot' . $shortcode_atts . ']');
