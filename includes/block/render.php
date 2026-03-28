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

// Allow-list must match chatbot-widget.php template elements.
// Update this list when adding new HTML elements/attributes to the widget template.
// Cached in a static variable to avoid rebuilding on every block render.
static $raplsaich_allowed_tags = null;
if (null === $raplsaich_allowed_tags) {
	$raplsaich_allowed_tags = array_merge(
		wp_kses_allowed_html('post'),
		raplsaich_get_svg_allowed_tags(),
		[
			'div'    => [
				'id' => true, 'class' => true, 'style' => true,
				'data-state' => true, 'data-position' => true, 'role' => true,
				'aria-live' => true, 'aria-label' => true,
			],
			'button' => [
				'class' => true, 'type' => true, 'style' => true, 'hidden' => true,
				'aria-label' => true, 'disabled' => true, 'title' => true,
				'data-action' => true,
			],
			'input'  => [
				'type' => true, 'id' => true, 'class' => true, 'name' => true,
				'placeholder' => true, 'maxlength' => true, 'required' => true,
				'accept' => true, 'style' => true, 'aria-label' => true,
				'autocomplete' => true,
			],
			'textarea' => [
				'id' => true, 'class' => true, 'placeholder' => true,
				'rows' => true, 'maxlength' => true, 'aria-label' => true,
				'autocomplete' => true, 'spellcheck' => true,
			],
			'label'  => ['for' => true, 'class' => true],
			'span'   => ['class' => true, 'style' => true, 'hidden' => true, 'id' => true],
			'img'    => ['src' => true, 'alt' => true, 'class' => true, 'style' => true],
			'form'   => ['class' => true, 'id' => true, 'autocomplete' => true, 'novalidate' => true],
			'select' => ['class' => true, 'id' => true, 'name' => true],
			'option' => ['value' => true, 'selected' => true],
		]
	);
}
echo wp_kses(do_shortcode('[rapls_chatbot' . $raplsaich_shortcode_atts . ']'), $raplsaich_allowed_tags);
