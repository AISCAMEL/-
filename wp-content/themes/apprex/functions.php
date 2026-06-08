<?php
/**
 * APPREX theme bootstrap.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APPREX_VERSION', '1.0.0' );
define( 'APPREX_DIR', get_template_directory() );
define( 'APPREX_URI', get_template_directory_uri() );

/**
 * Theme setup: supports, menus, image sizes.
 */
function apprex_setup() {
	load_theme_textdomain( 'apprex', APPREX_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 48,
			'width'       => 180,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// 16:10 card thumbnail used across cases grid.
	add_image_size( 'apprex-card', 720, 450, true );

	register_nav_menus(
		array(
			'primary' => __( 'グローバルナビ', 'apprex' ),
			'footer'  => __( 'フッターメニュー', 'apprex' ),
		)
	);
}
add_action( 'after_setup_theme', 'apprex_setup' );

require_once APPREX_DIR . '/inc/enqueue.php';
require_once APPREX_DIR . '/inc/cpt-cases.php';
require_once APPREX_DIR . '/inc/acf-fields.php';
require_once APPREX_DIR . '/inc/template-helpers.php';

/**
 * Fallback for the primary menu before the client assigns one in wp-admin.
 * Mirrors the spec's global nav: 特徴 | 機能 | 事例 | 料金 | FAQ | 無料体験を始める
 */
function apprex_primary_menu_fallback() {
	$items = array(
		'/features'   => __( '特徴', 'apprex' ),
		'/functions'  => __( '機能', 'apprex' ),
		'/cases'      => __( '事例', 'apprex' ),
		'/pricing'    => __( '料金', 'apprex' ),
		'/faq'        => __( 'FAQ', 'apprex' ),
	);
	echo '<ul class="menu">';
	foreach ( $items as $path => $label ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( home_url( $path ) ),
			esc_html( $label )
		);
	}
	echo '</ul>';
}
