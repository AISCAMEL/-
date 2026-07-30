<?php
/**
 * CARMEL: [carmel_photos] vehicle photo gallery (main image + thumbnails).
 * ---------------------------------------------------------------------------
 * Shows ALL images entered in the STEP UI gallery (carmel_gallery): featured
 * (1st) plus every following image. Main image + thumbnail strip; click a
 * thumb or the < > arrows to switch. Counter bottom-left = "current / total"
 * where total = the actual number of images.
 *
 * ROBUST BUILD: no JSON blob in markup; each thumb carries data-full and the
 * JS (printed once in the footer) reads it from the DOM. Pure ASCII.
 *
 * Place [carmel_photos] in the detail template where the main image goes.
 * Install: WPCode -> Add Snippet -> PHP Snippet -> Run Everywhere -> Activate.
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_photos_shortcode' ) ) {

	function carmelx_photos_ids( $pid ) {
		$ids = array();
		if ( function_exists( 'carmel_get_gallery_ids' ) ) {
			$ids = (array) carmel_get_gallery_ids( $pid );
		}
		if ( empty( $ids ) ) {
			$raw = get_post_meta( $pid, 'carmel_gallery', true );
			if ( ! empty( $raw ) ) { $ids = explode( ',', (string) $raw ); }
		}
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			$fid = (int) get_post_thumbnail_id( $pid );
			if ( $fid > 0 ) { $ids = array( $fid ); }
		}
		return $ids;
	}

	function carmelx_photos_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'carmel_photos' );
		$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
		if ( ! $pid ) { return ''; }

		$ids = carmelx_photos_ids( $pid );
		if ( empty( $ids ) ) { return ''; }

		$thumbs = '';
		$first  = '';
		$total  = 0;
		foreach ( $ids as $id ) {
			$full = wp_get_attachment_image_url( $id, 'large' );
			if ( ! $full ) { $full = wp_get_attachment_image_url( $id, 'full' ); }
			if ( ! $full ) { continue; }
			$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( ! $thumb ) { $thumb = $full; }
			$active = ( 0 === $total ) ? ' is-active' : '';
			if ( '' === $first ) { $first = $full; }
			$thumbs .= '<button type="button" class="cx-gal__t' . $active . '" data-full="' . esc_url( $full ) . '">'
				. '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy"></button>';
			$total++;
		}
		if ( 0 === $total ) { return ''; }

		$nav = $total > 1
			? '<button type="button" class="cx-gal__nav prev" aria-label="prev">&#8249;</button>'
			. '<button type="button" class="cx-gal__nav next" aria-label="next">&#8250;</button>'
			: '';

		$out  = '<div class="cx-gal">';
		$out .= '<div class="cx-gal__main"><img class="cx-gal__img" src="' . esc_url( $first ) . '" alt="">'
			. $nav
			. '<span class="cx-gal__count"><span class="cur">1</span> / ' . (int) $total . '</span>'
			. '</div>';
		if ( $total > 1 ) { $out .= '<div class="cx-gal__thumbs">' . $thumbs . '</div>'; }
		$out .= '</div>';
		return $out;
	}
	add_shortcode( 'carmel_photos', 'carmelx_photos_shortcode' );

	/* one global initializer for every .cx-gal on the page */
	add_action( 'wp_footer', function () {
		echo '<script>(function(){'
			. 'function init(r){if(r.dataset.cxReady)return;r.dataset.cxReady=1;'
			. 'var img=r.querySelector(".cx-gal__img");var cur=r.querySelector(".cur");'
			. 'var ts=r.querySelectorAll(".cx-gal__t");var i=0;'
			. 'function show(n){var L=ts.length;if(!L)return;i=(n+L)%L;'
			. 'var f=ts[i].getAttribute("data-full");if(f&&img)img.src=f;if(cur)cur.textContent=i+1;'
			. 'for(var k=0;k<L;k++){ts[k].className="cx-gal__t"+(k===i?" is-active":"");}}'
			. 'for(var k=0;k<ts.length;k++){(function(b,idx){b.addEventListener("click",function(){show(idx);});})(ts[k],k);}'
			. 'var p=r.querySelector(".prev"),n=r.querySelector(".next");'
			. 'if(p)p.addEventListener("click",function(){show(i-1);});'
			. 'if(n)n.addEventListener("click",function(){show(i+1);});}'
			. 'var all=document.querySelectorAll(".cx-gal");for(var j=0;j<all.length;j++){init(all[j]);}'
			. '})();</script>';
	}, 99 );

	add_action( 'wp_head', function () {
		echo '<style>
		.cx-gal{background:#fff;border-radius:12px;padding:16px;box-shadow:0 6px 18px rgba(0,0,0,.07);border:1px solid #eef0f3;margin:18px 0;font-family:inherit;}
		.cx-gal__main{position:relative;width:100%;aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:#0b0f14;}
		.cx-gal__main img{width:100%;height:100%;object-fit:contain;display:block;}
		.cx-gal__count{position:absolute;left:12px;bottom:12px;background:rgba(0,0,0,.65);color:#fff;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;}
		.cx-gal__nav{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.85);color:#1b2935;font-size:22px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1;}
		.cx-gal__nav.prev{left:10px;}.cx-gal__nav.next{right:10px;}
		.cx-gal__thumbs{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:12px;}
		.cx-gal__t{padding:0;border:2px solid transparent;border-radius:8px;overflow:hidden;cursor:pointer;background:#f3f4f6;aspect-ratio:4/3;}
		.cx-gal__t.is-active{border-color:#f47920;}
		.cx-gal__t img{width:100%;height:100%;object-fit:cover;display:block;}
		@media(max-width:680px){.cx-gal__thumbs{grid-template-columns:repeat(4,1fr);}}
		</style>';
	} );
}
