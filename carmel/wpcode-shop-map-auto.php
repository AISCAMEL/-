<?php
/**
 * CARMEL: auto-extend the shop slug->ID map with every published shop post.
 * ---------------------------------------------------------------------------
 * Future-proofing: the plugin ships a hardcoded 4-shop map but exposes the
 * 'carmel_shop_post_map' filter. This snippet adds EVERY published shop post
 * (keyed by its slug = post_name) so a newly added 加盟店 flows through
 * automatically. ONLY NEEDED ONCE YOU ADD A 5TH SHOP — the first 4 already
 * work without it.
 *
 * SAFE VERSION: reads slugs with a direct $wpdb query (no get_posts(), so it
 * cannot trigger query hooks) and guards against re-entrancy, so it can never
 * recurse or fatal. The previous get_posts()-based version could recurse.
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
		static $busy  = false;
		if ( ! is_array( $map ) ) { $map = array(); }

		if ( null === $cache ) {
			if ( $busy ) { return $map; }            // re-entry guard: never recurse
			$busy  = true;
			$cache = array();
			global $wpdb;
			$rows = $wpdb->get_results(
				"SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'shop' AND post_status = 'publish'"
			);
			if ( $rows ) {
				foreach ( $rows as $r ) {
					if ( ! empty( $r->post_name ) ) { $cache[ $r->post_name ] = (int) $r->ID; }
				}
			}
			$busy = false;
		}

		foreach ( $cache as $slug => $id ) {
			if ( ! isset( $map[ $slug ] ) ) { $map[ $slug ] = $id; }
		}
		return $map;
	}
	add_filter( 'carmel_shop_post_map', 'carmelx_shop_map_auto', 20 );
}
