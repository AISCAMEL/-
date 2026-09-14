<?php
/**
 * カーメル：似ているクルマもチェック ショートコード [carmel_similar]
 * ---------------------------------------------------------------------------
 * 車両詳細(portfolio)ページ下部に、関連在庫を3台カード表示して回遊性を高める。
 *   ・同メーカー(marker/maker)を優先、不足分は新着在庫で補完。
 *   ・画像＝アイキャッチ→ギャラリー先頭、価格＝月々概算（est_total/atamakin/nenritsu）。
 *
 * 属性：[carmel_similar count="3" title="似ているクルマもチェック"]
 * 導入：WPCode → PHP Snippet → Run Everywhere → 有効化。
 *       詳細テンプレートの一番下（販売店情報の下など）に [carmel_similar] を1行追加。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_similar_shortcode' ) ) {

	/* 月々概算（標準回数の最長で最安を「〜」表示） */
	function carmelx_similar_monthly( $pid ) {
		$num = function ( $key ) use ( $pid ) {
			$v = function_exists( 'carmel_detail_get' ) ? carmel_detail_get( $pid, $key ) : get_post_meta( $pid, $key, true );
			return (float) preg_replace( '/[^0-9.]/', '', (string) $v );
		};
		$total = $num( 'est_total' );
		if ( $total <= 0 ) { return 0; }
		$atama = $num( 'est_atamakin' );
		$nen   = $num( 'est_nenritsu' );
		$principal = max( 0, $total - $atama );
		if ( function_exists( 'carmel_plan_monthly' ) ) {
			return carmel_plan_monthly( $principal, $nen, 120 );
		}
		return (int) ( ceil( ( $principal / 120 ) / 100 ) * 100 );
	}

	/* カード画像URL */
	function carmelx_similar_img( $pid ) {
		$url = get_the_post_thumbnail_url( $pid, 'medium' );
		if ( $url ) { return $url; }
		if ( function_exists( 'carmel_get_gallery_ids' ) ) {
			$ids = carmel_get_gallery_ids( $pid );
			if ( ! empty( $ids ) ) {
				$u = wp_get_attachment_image_url( (int) $ids[0], 'medium' );
				if ( $u ) { return $u; }
			}
		}
		return '';
	}

	function carmelx_similar_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'count' => 3,
			'title' => '似ているクルマもチェック',
		), $atts, 'carmel_similar' );
		$cur = get_the_ID();
		if ( ! $cur ) { return ''; }
		$need = max( 1, (int) $atts['count'] );

		$maker = function_exists( 'carmel_detail_get_any' )
			? carmel_detail_get_any( $cur, array( 'marker', 'maker', 'メーカー' ) )
			: get_post_meta( $cur, 'marker', true );

		// 新着在庫を多めに取得して、同メーカー優先で並べ替え
		$pool = get_posts( array(
			'post_type'        => 'portfolio',
			'post_status'      => 'publish',
			'posts_per_page'   => 24,
			'post__not_in'     => array( $cur ),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		) );
		if ( empty( $pool ) ) { return ''; }

		$same = array();
		$rest = array();
		foreach ( $pool as $p ) {
			$m = function_exists( 'carmel_detail_get_any' )
				? carmel_detail_get_any( $p->ID, array( 'marker', 'maker', 'メーカー' ) )
				: get_post_meta( $p->ID, 'marker', true );
			if ( $maker && $m && $m === $maker ) { $same[] = $p; } else { $rest[] = $p; }
		}
		$picked = array_slice( array_merge( $same, $rest ), 0, $need );
		if ( empty( $picked ) ) { return ''; }

		$cards = '';
		foreach ( $picked as $p ) {
			$pid   = $p->ID;
			$link  = get_permalink( $pid );
			$img   = carmelx_similar_img( $pid );
			$ttl   = get_the_title( $pid );
			$m     = carmelx_similar_monthly( $pid );
			$thumb = $img
				? '<span class="cx-simi__im" style="background-image:url(' . esc_url( $img ) . ');"></span>'
				: '<span class="cx-simi__im cx-simi__im--ph">🚗</span>';
			$price = $m > 0 ? '<div class="cx-simi__pr">月々 ' . number_format( $m ) . '円〜</div>' : '';
			$cards .= '<a href="' . esc_url( $link ) . '">' . $thumb
				. '<div class="cx-simi__bd"><div class="cx-simi__t">' . esc_html( $ttl ) . '</div>' . $price . '</div></a>';
		}

		return '<div class="cx-sec cx-simi-sec"><div class="cx-ttl"><h2>' . esc_html( $atts['title'] ) . '</h2></div>'
			. '<div class="cx-simi">' . $cards . '</div></div>';
	}
	add_shortcode( 'carmel_similar', 'carmelx_similar_shortcode' );

	add_action( 'wp_head', function () {
		echo '<style>
		.cx-simi-sec{background:#fff;border-radius:12px;padding:24px;box-shadow:0 6px 18px rgba(0,0,0,.07);margin:18px 0;border:1px solid #eef0f3;font-family:inherit;}
		.cx-simi-sec .cx-ttl{text-align:center;margin-bottom:20px;}
		.cx-simi-sec .cx-ttl h2{font-size:24px;font-weight:800;color:#1b2935;margin:0;}
		.cx-simi-sec .cx-ttl h2::after{content:"";display:block;width:48px;height:3px;background:#f47920;margin:10px auto 0;}
		.cx-simi{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
		.cx-simi a{display:block;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .15s,transform .15s;}
		.cx-simi a:hover{box-shadow:0 8px 20px rgba(0,0,0,.10);transform:translateY(-2px);}
		.cx-simi__im{display:block;height:140px;background-size:cover;background-position:center;background-color:#eef4fa;}
		.cx-simi__im--ph{display:flex;align-items:center;justify-content:center;font-size:46px;}
		.cx-simi__bd{padding:10px 12px;}
		.cx-simi__t{font-size:13px;font-weight:700;color:#1b2935;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
		.cx-simi__pr{font-size:15px;font-weight:800;color:#2cac44;margin-top:5px;}
		@media(max-width:680px){.cx-simi{grid-template-columns:1fr 1fr;gap:10px;}.cx-simi__im{height:110px;}}
		</style>';
	} );
}
