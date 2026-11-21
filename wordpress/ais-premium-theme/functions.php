<?php
/**
 * AIS Premium テーマ functions
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AIS_THEME_VER', '1.0.0' );

/** フロント用アセット（フォント / CSS / JS）を読み込み */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'ais-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;800&family=Noto+Serif+JP:wght@500;600;700&display=swap',
		array(), null
	);
	$css = get_theme_file_path( 'assets/css/ais.css' );
	wp_enqueue_style( 'ais-main', get_theme_file_uri( 'assets/css/ais.css' ), array( 'ais-fonts' ), file_exists( $css ) ? filemtime( $css ) : AIS_THEME_VER );
	// 親スタイル（テーマヘッダー）
	wp_enqueue_style( 'ais-style', get_stylesheet_uri(), array( 'ais-main' ), AIS_THEME_VER );

	$js = get_theme_file_path( 'assets/js/ais.js' );
	wp_enqueue_script( 'ais-main', get_theme_file_uri( 'assets/js/ais.js' ), array(), file_exists( $js ) ? filemtime( $js ) : AIS_THEME_VER, true );
}, 20 );

/** テーマサポート */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/ais.css' );
} );

/** ブロックパターンのカテゴリ */
add_action( 'init', function () {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ais', array( 'label' => 'AIS Premium' ) );
	}
} );

/** アセットURLをインラインHTML（wp:html）から参照できるよう公開 */
add_action( 'wp_head', function () {
	echo '<script>window.AIS_THEME_URI=' . wp_json_encode( get_template_directory_uri() ) . ';</script>' . "\n";
}, 1 );
