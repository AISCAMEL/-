<?php
/**
 * Custom post type "case" (導入事例) + "industry" taxonomy (業種).
 *
 * Spec §8: managed as a custom post type so cases can be extended/updated.
 * Archive lives at /cases (rewrite slug), single at /cases/{slug}.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the "case" post type.
 */
function apprex_register_cases_cpt() {
	$labels = array(
		'name'               => __( '導入事例', 'apprex' ),
		'singular_name'      => __( '導入事例', 'apprex' ),
		'add_new'            => __( '新規追加', 'apprex' ),
		'add_new_item'       => __( '導入事例を追加', 'apprex' ),
		'edit_item'          => __( '導入事例を編集', 'apprex' ),
		'new_item'           => __( '新規導入事例', 'apprex' ),
		'view_item'          => __( '導入事例を表示', 'apprex' ),
		'search_items'       => __( '導入事例を検索', 'apprex' ),
		'not_found'          => __( '導入事例が見つかりません', 'apprex' ),
		'not_found_in_trash' => __( 'ゴミ箱に導入事例はありません', 'apprex' ),
		'all_items'          => __( '導入事例一覧', 'apprex' ),
		'menu_name'          => __( '導入事例', 'apprex' ),
	);

	register_post_type(
		'case',
		array(
			'labels'        => $labels,
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-portfolio',
			'menu_position' => 5,
			'show_in_rest'  => true, // Gutenberg / headless friendly.
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'rewrite'       => array( 'slug' => 'cases', 'with_front' => false ),
			'taxonomies'    => array( 'industry' ),
		)
	);
}
add_action( 'init', 'apprex_register_cases_cpt' );

/**
 * Register the "industry" taxonomy (業種) used to filter the cases grid.
 */
function apprex_register_industry_taxonomy() {
	$labels = array(
		'name'          => __( '業種', 'apprex' ),
		'singular_name' => __( '業種', 'apprex' ),
		'search_items'  => __( '業種を検索', 'apprex' ),
		'all_items'     => __( 'すべての業種', 'apprex' ),
		'edit_item'     => __( '業種を編集', 'apprex' ),
		'add_new_item'  => __( '業種を追加', 'apprex' ),
		'menu_name'     => __( '業種', 'apprex' ),
	);

	register_taxonomy(
		'industry',
		array( 'case' ),
		array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'industry' ),
		)
	);
}
add_action( 'init', 'apprex_register_industry_taxonomy' );

/**
 * Flush rewrite rules once on theme switch so /cases works without a manual
 * Settings > Permalinks save.
 */
function apprex_rewrite_flush() {
	apprex_register_cases_cpt();
	apprex_register_industry_taxonomy();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'apprex_rewrite_flush' );
