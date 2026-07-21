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
		'opEnabled'   => function_exists( 'apprex_chat_op_enabled' ) && apprex_chat_op_enabled(),
		'opOpen'      => function_exists( 'apprex_chat_op_within_hours' ) ? apprex_chat_op_within_hours() : true,
		'opHours'     => function_exists( 'apprex_chat_op_hours_label' ) ? apprex_chat_op_hours_label() : '',
	);

	// 会員ログイン連携：ログイン中の契約者ならチャットを本人向けに切り替える。
	if ( function_exists( 'apprex_chat_member_info' ) ) {
		$rest['member'] = apprex_chat_member_info();
	}

	// 共有REST設定は常時読み込む apprex-main に付与（フォーム等が依存するため）。
	wp_localize_script( 'apprex-main', 'APPREX_REST', $rest );

	// サイト内AIチャットは、有効時のみ読み込む（未使用JSを削減）。
	if ( apprex_chat_enabled() ) {
		wp_enqueue_script( 'apprex-chat', APPREX_URI . '/assets/js/chat.js', array( 'apprex-main' ), APPREX_VERSION, true );
	}

	// Estimate → order calculator (only where needed, but cheap to always load on front).
	wp_enqueue_script( 'apprex-estimate', APPREX_URI . '/assets/js/estimate.js', array(), APPREX_VERSION, true );
	wp_localize_script(
		'apprex-estimate',
		'APPREX_PRICING',
		array(
			'rest'       => $rest,
			'config'     => apprex_pricing_config(),
			'quotePlans' => function_exists( 'apprex_quote_plans' ) ? apprex_quote_plans() : array(),
			'meetingUrl' => function_exists( 'apprex_page_url' ) ? apprex_page_url( 'meeting' ) : '',
			'contactUrl' => home_url( '/contact/' ),
		)
	);

	// Native forms (contact / document / trial)。REST設定は apprex-main に付与済み。
	wp_enqueue_script( 'apprex-forms', APPREX_URI . '/assets/js/forms.js', array( 'apprex-main' ), APPREX_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'apprex_enqueue_assets' );

/**
 * Google Fonts へ preconnect（初回描画の高速化）。
 * gstatic.com を早期に接続開始し、日本語フォントの表示遅延を軽減。
 */
add_filter( 'wp_resource_hints', function ( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}, 10, 2 );

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
