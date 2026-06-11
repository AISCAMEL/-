<?php
/**
 * APPREX theme bootstrap.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APPREX_VERSION', '1.2.0' );
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
require_once APPREX_DIR . '/inc/campaign.php';
require_once APPREX_DIR . '/inc/pricing-config.php';
require_once APPREX_DIR . '/inc/openrouter-chat.php';
require_once APPREX_DIR . '/inc/integrations.php';
require_once APPREX_DIR . '/inc/orders.php';
require_once APPREX_DIR . '/inc/contracts.php';
require_once APPREX_DIR . '/inc/email.php';
require_once APPREX_DIR . '/inc/forms.php';
require_once APPREX_DIR . '/inc/ai-blog.php';
require_once APPREX_DIR . '/inc/blog.php';
require_once APPREX_DIR . '/inc/seo.php';
require_once APPREX_DIR . '/inc/installer.php';

/**
 * Fallback for the primary menu before the client assigns one in wp-admin.
 * Global nav (現行サイト準拠): 特徴 | 機能 | 料金 | 事例 | HP制作 | 会社概要 | FAQ
 */
function apprex_primary_menu_fallback() {
	$items = array(
		'/features'    => __( '特徴', 'apprex' ),
		'/functions'   => __( '機能', 'apprex' ),
		'/pricing'     => __( '料金', 'apprex' ),
		'/cases'       => __( '事例', 'apprex' ),
		'/estimate'    => __( '見積もり', 'apprex' ),
		'/hp-creation' => __( 'HP制作', 'apprex' ),
		'/blog'        => __( 'ブログ', 'apprex' ),
		'/partner'     => __( 'パートナー募集', 'apprex' ),
		'/company'     => __( '会社概要', 'apprex' ),
		'/faq'         => __( 'FAQ', 'apprex' ),
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

/**
 * Zapier chatbot URL (修正要件 §2). Filterable so it can be overridden.
 */
function apprex_chatbot_url() {
	return apply_filters( 'apprex_chatbot_url', 'https://apprex.zapier.app' );
}

/**
 * Inject the floating Zapier chatbot on every page (修正要件 §2 — 全ページ).
 */
function apprex_render_chatbot() {
	get_template_part( 'template-parts/chatbot' );
}
add_action( 'wp_footer', 'apprex_render_chatbot' );
