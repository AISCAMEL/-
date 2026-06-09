<?php
/**
 * Asset enqueue.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end styles & scripts.
 */
function apprex_enqueue_assets() {
	// Google Fonts (Noto Sans JP). Loaded async-friendly via standard handle.
	wp_enqueue_style(
		'apprex-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'apprex-style',
		get_stylesheet_uri(),
		array( 'apprex-fonts' ),
		APPREX_VERSION
	);

	wp_enqueue_script(
		'apprex-main',
		APPREX_URI . '/assets/js/main.js',
		array(),
		APPREX_VERSION,
		true
	);

	// Shared REST config (nonce + endpoints) for chat & estimate.
	$rest = array(
		'root'        => esc_url_raw( rest_url( 'apprex/v1/' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'chatEnabled' => apprex_chat_enabled(),
	);

	// In-site AI chatbot.
	wp_enqueue_script( 'apprex-chat', APPREX_URI . '/assets/js/chat.js', array(), APPREX_VERSION, true );
	wp_localize_script( 'apprex-chat', 'APPREX_REST', $rest );

	// Estimate → order calculator (only where needed, but cheap to always load on front).
	wp_enqueue_script( 'apprex-estimate', APPREX_URI . '/assets/js/estimate.js', array(), APPREX_VERSION, true );
	wp_localize_script(
		'apprex-estimate',
		'APPREX_PRICING',
		array(
			'rest'     => $rest,
			'config'   => apprex_pricing_config(),
		)
	);

	// Native forms (contact / document / trial).
	wp_enqueue_script( 'apprex-forms', APPREX_URI . '/assets/js/forms.js', array( 'apprex-chat' ), APPREX_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'apprex_enqueue_assets' );

/**
 * Native lazy-loading is already added by WP core to content images.
 * Ensure attachment images served by the theme also get loading="lazy".
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
	if ( empty( $attr['loading'] ) ) {
		$attr['loading'] = 'lazy';
	}
	$attr['decoding'] = 'async';
	return $attr;
} );
