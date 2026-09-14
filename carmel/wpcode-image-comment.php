<?php
/**
 * カーメル：画像ギャラリー（2枚目以降）＋担当者コメント（店舗=担当者）自動反映
 * ---------------------------------------------------------------------------
 * 1) 画像保存時：1枚目＝アイキャッチ（メイン）、2枚目以降＝Image Gallery
 *    （Total 投稿ギャラリー wpex_post_gallery_ids）へ自動反映。
 * 2) [carmel_comment]：担当者名を「選択中の店舗」から自動反映（店舗＝担当者）。
 *    顔写真も店舗から解決（既存 carmel_staff_photo_url を再利用）。
 *
 * 導入 : WPCode →「+ スニペットを追加」→「PHP Snippet」→ <?php 以降を貼り付け
 *        → Auto Insert / Run Everywhere → 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ===== 1) 画像：1枚目=アイキャッチ／2枚目以降=Image Gallery ===== */
/* プラグイン本体（priority 20）が全画像を入れた後（priority 25）に、
   投稿ギャラリーを「2枚目以降」だけへ上書きする。 */
add_action( 'save_post_portfolio', 'carmelx_gallery_rest_only', 25, 1 );
function carmelx_gallery_rest_only( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	if ( ! function_exists( 'carmel_get_gallery_ids' ) ) { return; }

	$ids = carmel_get_gallery_ids( $post_id );
	$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	if ( empty( $ids ) ) { return; }

	// 1枚目＝アイキャッチ（メイン画像）
	$first = (int) $ids[0];
	if ( $first > 0 && 'attachment' === get_post_type( $first ) ) {
		set_post_thumbnail( $post_id, $first );
	}

	// 2枚目以降＝Image Gallery（Total 投稿ギャラリー）
	$rest = array_slice( $ids, 1 );
	$csv  = implode( ',', $rest );
	if ( $rest ) {
		update_post_meta( $post_id, 'wpex_post_gallery_ids',  $csv );
		update_post_meta( $post_id, '_wpex_post_gallery_ids', $csv );
	} else {
		delete_post_meta( $post_id, 'wpex_post_gallery_ids' );
		delete_post_meta( $post_id, '_wpex_post_gallery_ids' );
	}
}

/* ===== 2) 担当者コメント：店舗＝担当者を自動反映 ===== */
add_action( 'init', function () {
	if ( shortcode_exists( 'carmel_comment' ) ) { remove_shortcode( 'carmel_comment' ); }
	add_shortcode( 'carmel_comment', 'carmelx_comment_shortcode' );
}, 20 );

/* 選択中の店舗の投稿IDを解決（slug→ID / 数値ID / どちらでも可） */
function carmelx_resolve_shop_id( $pid ) {
	$shopval = get_post_meta( $pid, 'shop', true );
	if ( ! $shopval ) { return 0; }
	$map = function_exists( 'carmel_shop_post_map' ) ? carmel_shop_post_map() : array();
	if ( isset( $map[ $shopval ] ) ) { return (int) $map[ $shopval ]; }
	if ( is_numeric( $shopval ) ) { return (int) $shopval; }
	return 0;
}

function carmelx_comment_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0, 'name' => '', 'photo' => '' ), $atts, 'carmel_comment' );
	$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
	if ( ! $pid ) { return ''; }

	$c = function_exists( 'carmel_detail_get' ) ? carmel_detail_get( $pid, 'comment' ) : get_post_meta( $pid, 'comment', true );
	if ( '' === $c ) { return ''; }

	// 担当者名 ＝ 選択中の店舗名（店舗＝担当者を自動反映）
	$name = $atts['name'];
	if ( '' === $name ) {
		$sid = carmelx_resolve_shop_id( $pid );
		if ( $sid ) {
			$sname = get_the_title( $sid );
			if ( $sname ) { $name = $sname; }
		}
	}
	if ( '' === $name ) { $name = '担当者より'; }

	// 顔写真 ＝ 店舗から解決（既存ロジックを再利用）
	$photo = '';
	if ( $atts['photo'] && function_exists( 'carmel_img_url' ) ) {
		$photo = carmel_img_url( $atts['photo'] );
	} elseif ( function_exists( 'carmel_staff_photo_url' ) ) {
		$photo = carmel_staff_photo_url( $pid );
	}
	$icon = $photo
		? '<span class="carmel-comment__icon has-photo"><img src="' . esc_url( $photo ) . '" alt="担当者"></span>'
		: '<span class="carmel-comment__icon">🧑‍💼</span>';

	return '<div class="carmel-comment">' . $icon
		. '<div class="carmel-comment__bubble">'
		. '<div class="carmel-comment__name">' . esc_html( $name ) . '</div>'
		. nl2br( esc_html( $c ) )
		. '</div></div>';
}
