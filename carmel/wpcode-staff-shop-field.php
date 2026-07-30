<?php
/**
 * CARMEL: add a "tantou shop" selector to the スタッフ (staff) edit screen.
 * ---------------------------------------------------------------------------
 * Goal: franchise staff manage everything in the スタッフ screen, no code.
 *   - name  = staff post title
 *   - photo = staff post featured image (アイキャッチ)
 *   - shop  = this dropdown (stored in meta 'staff_shop' as the shop slug)
 * The detail-page card ([carmel_staff_shop]) then auto-picks the staff whose
 * 'staff_shop' matches the vehicle's shop.
 *
 * Install: WPCode -> Add Snippet -> PHP Snippet -> paste from <?php ->
 *          Run Everywhere -> Activate.
 * NOTE: pure ASCII (Japanese as HTML numeric entities) on purpose, so the
 *       pasted code cannot pick up corrupt characters. Shop names in the
 *       dropdown come from the DB, so they always display correctly.
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_staff_shop_field_box' ) ) {

	/* shop slug => label, built from the existing shop map (DB titles) */
	function carmelx_staff_shop_choices() {
		$out = array();
		if ( function_exists( 'carmel_shop_post_map' ) ) {
			foreach ( carmel_shop_post_map() as $slug => $sid ) {
				$title = get_the_title( (int) $sid );
				$out[ $slug ] = $title ? $title : $slug;
			}
		}
		return $out;
	}

	add_action( 'add_meta_boxes', function () {
		if ( ! post_type_exists( 'staff' ) ) { return; }
		add_meta_box(
			'carmelx_staff_shop_box',
			'&#25285;&#24403;&#24215;&#33303;', // tantou shop
			'carmelx_staff_shop_field_box',
			'staff',
			'side',
			'high'
		);
	} );

	function carmelx_staff_shop_field_box( $post ) {
		$cur = (string) get_post_meta( $post->ID, 'staff_shop', true );
		wp_nonce_field( 'carmelx_staff_shop_save', 'carmelx_staff_shop_nonce' );
		echo '<p style="margin:0 0 6px;color:#666;font-size:12px;">'
			. '&#12371;&#12398;&#12473;&#12479;&#12483;&#12501;&#12434;&#34920;&#31034;&#12377;&#12427;&#36009;&#22770;&#24215;&#12434;&#36984;&#12435;&#12391;&#12367;&#12384;&#12373;&#12356;&#12290;' // choose the shop to show this staff
			. '</p>';
		echo '<select name="carmelx_staff_shop" style="width:100%;">';
		echo '<option value="">' . '&#9472;&#9472; &#26410;&#35373;&#23450; &#9472;&#9472;' . '</option>'; // -- unset --
		foreach ( carmelx_staff_shop_choices() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cur, $slug, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p style="margin:8px 0 0;color:#999;font-size:11px;">'
			. '&#9989; &#21517;&#21069;&#65309;&#12479;&#12452;&#12488;&#12523; / &#20889;&#30495;&#65309;&#12450;&#12452;&#12461;&#12515;&#12483;&#12481;' // name=title / photo=featured image
			. '</p>';
	}

	add_action( 'save_post_staff', function ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST['carmelx_staff_shop_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['carmelx_staff_shop_nonce'] ) ), 'carmelx_staff_shop_save' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$val = isset( $_POST['carmelx_staff_shop'] ) ? sanitize_text_field( wp_unslash( $_POST['carmelx_staff_shop'] ) ) : '';
		if ( '' === $val ) { delete_post_meta( $post_id, 'staff_shop' ); }
		else { update_post_meta( $post_id, 'staff_shop', $val ); }
	} );
}
