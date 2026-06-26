<?php
/**
 * CARMEL: auto-extend the shop slug->ID map with every published shop post.
 * ---------------------------------------------------------------------------
 * Future-proofing: the plugin ships a hardcoded 4-shop map but exposes the
 * 'carmel_shop_post_map' filter. This snippet adds EVERY published shop post
 * (keyed by its slug = post_name) so that when a new 加盟店 (franchise shop)
 * is added, it automatically flows through everywhere the map is used:
 *   - the 担当店舗 dropdown on the スタッフ screen
 *   - the detail-page staff card resolution
 *   - staff photo / shop info lookups
 * Hardcoded defaults are kept authoritative (never overwritten).
 *
 * Install: WPCode -> Add Snippet -> PHP Snippet -> paste from <?php ->
 *          Run Everywhere -> Activate.  (Pure ASCII; no Japanese literals.)
 *
 * Requirement for a NEW shop to link up automatically:
 *   the shop post slug must equal the value stored in the vehicle "shop"
 *   field (the existing shops use slugs like fukushima / chiba / odawara).
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_shop_map_auto' ) ) {

	function carmelx_shop_map_auto( $map ) {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array();
			$shops = get_posts( array(
				'post_type'   => 'shop',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
				'fields'      => 'all',
			) );
			foreach ( $shops as $s ) {
				if ( ! empty( $s->post_name ) ) { $cache[ $s->post_name ] = (int) $s->ID; }
			}
		}
		if ( ! is_array( $map ) ) { $map = array(); }
		// Add any shop not already present; keep hardcoded defaults authoritative.
		foreach ( $cache as $slug => $id ) {
			if ( ! isset( $map[ $slug ] ) ) { $map[ $slug ] = $id; }
		}
		return $map;
	}
	add_filter( 'carmel_shop_post_map', 'carmelx_shop_map_auto', 20 );
}
