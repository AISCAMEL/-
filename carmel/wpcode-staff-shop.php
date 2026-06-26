<?php
/**
 * CARMEL: [carmel_staff_shop]  (tantou staff + shop info card on the detail page)
 * ---------------------------------------------------------------------------
 * Staff photo resolution (so the DETAIL page shows a PERSON, same as /search,
 * while the shop search page keeps the LOGO as its featured image):
 *   (1) vehicle meta (tantou_photo ...)
 *   (2) per-shop staff-photo MAP keyed by shop slug  <-- same idea as /search
 *   (3) shop meta tantou_photo ...
 *   (4) carmel_staff_photo_url() fallback (may be the shop logo)
 * Staff NAME = selected shop title (shop = tantousha).
 *
 * Install: WPCode -> Add Snippet -> PHP Snippet -> paste from <?php ->
 *          Run Everywhere -> Activate.  Place [carmel_staff_shop] in template.
 * NOTE: all Japanese is written as HTML numeric entities on purpose, so the
 *       pasted code stays pure ASCII and cannot pick up corrupt characters.
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_staff_shop_shortcode' ) ) {

	/* ---- per-shop staff photo map (shop slug => image URL) -----------------
	 * EDIT HERE to set each shop's staff face photo. Keys are the shop slugs
	 * used in the vehicle "shop" field. Same photos the /search page uses. */
	function carmelx_ss_shop_photo_map() {
		return array(
			'fukushima' => 'https://carmelonline.jp/wp-content/uploads/2024/10/Gemini_Generated_Image_ladg0lladg0lladg.png',
			'chiba'     => 'https://carmelonline.jp/wp-content/uploads/2024/10/Gemini_Generated_Image_frcqqofrcqqofrcq.jpg',
			// 'odawara'   => '',
			// 'yamanashi' => '',
		);
	}

	/* first non-empty value from candidate keys (ACF -> meta) */
	function carmelx_ss_first( $pid, $keys ) {
		foreach ( (array) $keys as $k ) {
			$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
			if ( '' === $v || null === $v || false === $v ) { $v = get_post_meta( $pid, $k, true ); }
			if ( is_array( $v ) ) { $v = implode( "\xE3\x83\xBB", array_filter( $v ) ); } // nakaguro
			$v = is_string( $v ) ? trim( $v ) : $v;
			if ( '' !== $v && null !== $v ) { return $v; }
		}
		return '';
	}

	/* image value -> URL */
	function carmelx_ss_imgurl( $v ) {
		if ( function_exists( 'carmel_img_url' ) ) { return carmel_img_url( $v ); }
		if ( is_array( $v ) ) { return ! empty( $v['url'] ) ? $v['url'] : ( ! empty( $v['ID'] ) ? (string) wp_get_attachment_image_url( (int) $v['ID'], 'medium' ) : '' ); }
		if ( is_numeric( $v ) ) { return (string) wp_get_attachment_image_url( (int) $v, 'medium' ); }
		return ( is_string( $v ) && preg_match( '#^https?://#', $v ) ) ? $v : '';
	}

	/* vehicle meta shop (slug or ID) -> raw slug */
	function carmelx_ss_shop_slug( $pid ) {
		$val = get_post_meta( $pid, 'shop', true );
		return is_string( $val ) ? trim( $val ) : $val;
	}

	/* vehicle meta shop (slug or ID) -> shop post ID */
	function carmelx_ss_shop_id( $pid ) {
		$val = carmelx_ss_shop_slug( $pid );
		if ( ! $val ) { return 0; }
		$map = function_exists( 'carmel_shop_post_map' ) ? carmel_shop_post_map() : array();
		if ( isset( $map[ $val ] ) ) { return (int) $map[ $val ]; }
		if ( is_numeric( $val ) ) { return (int) $val; }
		return 0;
	}

	function carmelx_staff_shop_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'carmel_staff_shop' );
		$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
		if ( ! $pid ) { return ''; }
		$slug = carmelx_ss_shop_slug( $pid );
		$sid  = carmelx_ss_shop_id( $pid );

		/* ---- staff ---- */
		$name_keys  = array( 'tantou_name', 'tantousha_name', 'staff_name', 'tantou', 'sekinin' );
		$role_keys  = array( 'tantou_role', 'tantou_yakushoku', 'staff_role', 'position' );
		$photo_keys = array( 'tantou_photo', 'tantousha_photo', 'staff_photo', 'tantou_image', 'staff_image' );

		$st_name  = carmelx_ss_first( $pid, $name_keys );
		$st_role  = carmelx_ss_first( $pid, $role_keys );
		$st_photo = carmelx_ss_imgurl( carmelx_ss_first( $pid, $photo_keys ) ); // (1) vehicle

		if ( '' === $st_name && $sid ) { $st_name = carmelx_ss_first( $sid, $name_keys ); }
		if ( '' === $st_role && $sid ) { $st_role = carmelx_ss_first( $sid, $role_keys ); }

		// (2) per-shop staff photo map (same source idea as /search)
		if ( '' === $st_photo && $slug ) {
			$pmap = carmelx_ss_shop_photo_map();
			if ( ! empty( $pmap[ $slug ] ) ) { $st_photo = $pmap[ $slug ]; }
		}
		// (3) shop meta tantou_photo
		if ( '' === $st_photo && $sid ) { $st_photo = carmelx_ss_imgurl( carmelx_ss_first( $sid, $photo_keys ) ); }
		// (4) generic fallback (may return the shop logo)
		if ( '' === $st_photo && function_exists( 'carmel_staff_photo_url' ) ) { $st_photo = carmel_staff_photo_url( $pid ); }

		// staff post (first one) to fill missing name only
		if ( '' === $st_name && post_type_exists( 'staff' ) ) {
			$staff = get_posts( array( 'post_type' => 'staff', 'numberposts' => 1, 'post_status' => 'publish' ) );
			if ( $staff && '' === $st_name ) { $st_name = get_the_title( $staff[0] ); }
		}
		if ( '' === $st_role ) { $st_role = '&#21942;&#26989;&#25285;&#24403;'; } // eigyo tantou

		$comment = function_exists( 'carmel_detail_get' ) ? carmel_detail_get( $pid, 'comment' ) : get_post_meta( $pid, 'comment', true );

		/* ---- shop info ---- */
		$fee = function_exists( 'carmel_get_fee_settings' ) ? carmel_get_fee_settings() : array();
		$shop_name = $sid ? get_the_title( $sid ) : '';
		if ( '' === $shop_name && ! empty( $fee['shop']['name'] ) ) { $shop_name = $fee['shop']['name']; }
		$addr  = $sid ? carmelx_ss_first( $sid, array( 'address', 'jusho', 'shop_address' ) ) : '';
		if ( '' === $addr && ! empty( $fee['shop']['address'] ) ) { $addr = $fee['shop']['address']; }
		$tel   = $sid ? carmelx_ss_first( $sid, array( 'tel', 'phone', 'denwa' ) ) : '';
		if ( '' === $tel && ! empty( $fee['shop']['tel'] ) ) { $tel = $fee['shop']['tel']; }
		$hours = $sid ? carmelx_ss_first( $sid, array( 'hours', 'open_hours', 'business_hours', 'eigyo' ) ) : '';
		if ( '' === $hours ) { $hours = '10:00&#12316;18:00'; }
		$closed = $sid ? carmelx_ss_first( $sid, array( 'closed', 'holiday', 'teikyubi' ) ) : '';
		if ( '' === $closed ) { $closed = '&#12394;&#12375;&#65288;&#24180;&#20013;&#28961;&#20241;&#65289;'; } // nashi (nenju mukyu)
		$line    = $sid ? carmelx_ss_first( $sid, array( 'line_link', 'line-link' ) ) : '';
		if ( '' === $line ) { $line = carmelx_ss_first( $pid, array( 'line-link', 'line_link' ) ); }
		$contact = $sid ? carmelx_ss_first( $sid, array( 'contact-link', 'contact_link' ) ) : '';
		if ( '' === $contact ) { $contact = carmelx_ss_first( $pid, array( 'contact-link', 'contact_link' ) ); }
		$telnum = preg_replace( '/[^0-9+]/', '', (string) $tel );

		/* ---- output ---- */
		$out = '';

		// tantou staff
		$icon = $st_photo
			? '<span class="cx-staff__photo has"><img src="' . esc_url( $st_photo ) . '" alt="' . esc_attr( $st_name ) . '"></span>'
			: '<span class="cx-staff__photo">&#128104;&#8205;&#128188;</span>';
		$out .= '<div class="cx-sec"><div class="cx-ttl"><h2>&#25285;&#24403;&#12473;&#12479;&#12483;&#12501;</h2></div><div class="cx-staff">'
			. $icon . '<div class="cx-staff__body">'
			. ( $st_role ? '<span class="cx-staff__role">' . $st_role . '</span>' : '' )
			. ( $st_name ? '<div class="cx-staff__name">' . esc_html( $st_name ) . '</div>' : '' )
			. ( $shop_name ? '<div class="cx-staff__shop">' . esc_html( $shop_name ) . '</div>' : '' )
			. ( $comment ? '<div class="cx-staff__msg">' . nl2br( esc_html( $comment ) ) . '</div>' : '' )
			. '<div class="cx-staff__tags"><span>&#12525;&#12540;&#12531;&#30456;&#35527;OK</span><span>&#20302;&#19982;&#20449;&#12525;&#12540;&#12531;&#23550;&#24540;</span><span>&#20840;&#22269;&#32013;&#36554;</span></div>'
			. '</div></div></div>';

		// shop info
		$rows = '';
		if ( $addr )   { $rows .= '<tr><th>&#20303;&#25152;</th><td>' . esc_html( $addr ) . '</td></tr>'; }
		if ( $tel )    { $rows .= '<tr><th>&#38651;&#35441;&#30058;&#21495;</th><td>' . esc_html( $tel ) . '</td></tr>'; }
		if ( $hours )  { $rows .= '<tr><th>&#21942;&#26989;&#26178;&#38291;</th><td>' . $hours . '</td></tr>'; }
		if ( $closed ) { $rows .= '<tr><th>&#23450;&#20241;&#26085;</th><td>' . $closed . '</td></tr>'; }
		$rows .= '<tr><th>&#23550;&#24540;&#12456;&#12522;&#12450;</th><td>&#20840;&#22269;&#32013;&#36554;&#23550;&#24540;</td></tr>';
		$btns = '';
		if ( $tel )     { $btns .= '<a class="tel" href="tel:' . esc_attr( $telnum ) . '">&#128222; ' . esc_html( $tel ) . '</a>'; }
		if ( $line )    { $btns .= '<a class="line" href="' . esc_url( $line ) . '" target="_blank" rel="noopener">LINE&#12391;&#30456;&#35527;</a>'; }
		if ( $contact ) { $btns .= '<a class="form" href="' . esc_url( $contact ) . '" target="_blank" rel="noopener">&#12362;&#21839;&#12356;&#21512;&#12431;&#12379;</a>'; }
		$out .= '<div class="cx-sec"><div class="cx-ttl"><h2>&#36009;&#22770;&#24215;&#24773;&#22577;</h2></div>'
			. ( $shop_name ? '<div class="cx-shop__name">' . esc_html( $shop_name ) . '</div>' : '' )
			. '<table class="c-table01">' . $rows . '</table>'
			. ( $btns ? '<div class="cx-shop__btns">' . $btns . '</div>' : '' )
			. '</div>';

		return $out;
	}
	add_shortcode( 'carmel_staff_shop', 'carmelx_staff_shop_shortcode' );

	add_action( 'wp_head', function () {
		echo '<style>
		.cx-sec{background:#fff;border-radius:12px;padding:24px;box-shadow:0 6px 18px rgba(0,0,0,.07);margin:18px 0;border:1px solid #eef0f3;font-family:inherit;}
		.cx-ttl{text-align:center;margin-bottom:20px;}
		.cx-ttl h2{font-size:24px;font-weight:800;color:#1b2935;margin:0;}
		.cx-ttl h2::after{content:"";display:block;width:48px;height:3px;background:#f47920;margin:10px auto 0;}
		.cx-staff{display:flex;gap:24px;align-items:stretch;flex-wrap:wrap;}
		.cx-staff__photo{flex:0 0 150px;width:150px;min-height:150px;border-radius:12px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:74px;border:3px solid #f47920;overflow:hidden;}
		.cx-staff__photo.has img{width:100%;height:100%;object-fit:cover;display:block;}
		.cx-staff__body{flex:1 1 320px;display:flex;flex-direction:column;}
		.cx-staff__role{display:inline-block;background:#f47920;color:#fff;font-size:12px;font-weight:800;padding:4px 14px;border-radius:4px;align-self:flex-start;margin-bottom:8px;}
		.cx-staff__name{font-size:23px;font-weight:900;color:#1b2935;}
		.cx-staff__shop{font-size:13px;color:#f47920;font-weight:800;margin:4px 0 10px;}
		.cx-staff__msg{background:#e8f4fd;border-left:5px solid #f47920;border-radius:6px;padding:13px 16px;font-size:14.5px;line-height:1.85;color:#27384a;flex:1;}
		.cx-staff__tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
		.cx-staff__tags span{background:#fff;border:1px solid #f47920;color:#f47920;font-size:12px;font-weight:700;border-radius:20px;padding:4px 12px;}
		.cx-shop__name{font-size:19px;font-weight:800;color:#1b2935;margin-bottom:12px;}
		.cx-sec .c-table01{width:100%;border-collapse:collapse;font-size:14px;}
		.cx-sec .c-table01 th,.cx-sec .c-table01 td{padding:11px 14px;border:1px solid #d8d8d8;line-height:1.6;text-align:left;}
		.cx-sec .c-table01 th{width:30%;background:#e8e8e8;font-weight:700;white-space:nowrap;}
		.cx-shop__btns{display:flex;gap:14px;margin-top:18px;flex-wrap:wrap;justify-content:center;}
		.cx-shop__btns a{flex:1 1 220px;max-width:320px;text-align:center;padding:14px;border-radius:999px;font-weight:800;font-size:15px;text-decoration:none;}
		.cx-shop__btns .tel{background:#fff;color:#f47920;border:2px solid #f47920;font-size:19px;}
		.cx-shop__btns .line{background:#06c755;color:#fff;box-shadow:0 4px 0 #04a846;}
		.cx-shop__btns .form{background:#f47920;color:#fff;box-shadow:0 4px 0 #d9650d;}
		@media(max-width:680px){.cx-staff__photo{flex-basis:120px;width:120px;min-height:120px;font-size:56px;}}
		</style>';
	} );
}
