<?php
/**
 * カーメル：ギャラリー1枚目を自動でアイキャッチ（featured image）に
 * ---------------------------------------------------------------------------
 * 目的 : 在庫(portfolio)を保存したとき、画像ギャラリーの1枚目を
 *        アイキャッチ画像として自動設定する。
 *
 * 対応ギャラリー（自動判定）:
 *   1) Easy Image Gallery プラグイン（meta: _easy_image_gallery / カンマ区切りID）
 *   2) ACF の画像ギャラリー(gallery)フィールド
 *   3) フィルタ carmel_gallery_ids で独自指定も可
 *
 * 挙動 : 保存時、ギャラリーに画像があれば「常に1枚目」をアイキャッチに同期。
 *        （手動アイキャッチを優先したい場合は CARMEL_FEATURED_OVERWRITE を false に）
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'CARMEL_FEATURED_OVERWRITE' ) ) {
	define( 'CARMEL_FEATURED_OVERWRITE', true ); // true=常に1枚目に同期 / false=未設定時のみ
}

add_action( 'save_post_portfolio', 'carmel_featured_from_gallery', 20, 1 );
function carmel_featured_from_gallery( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }

	if ( ! CARMEL_FEATURED_OVERWRITE && has_post_thumbnail( $post_id ) ) { return; }

	$ids = carmel_get_gallery_ids( $post_id );
	if ( empty( $ids ) ) { return; }

	$first = (int) $ids[0];
	if ( $first > 0 && 'attachment' === get_post_type( $first ) ) {
		set_post_thumbnail( $post_id, $first );
	}
}

/* ギャラリーの添付ID配列を取得（自動判定） */
function carmel_get_gallery_ids( $post_id ) {
	// 1) Easy Image Gallery（カンマ区切りID）
	$eig = get_post_meta( $post_id, '_easy_image_gallery', true );
	if ( ! empty( $eig ) ) {
		if ( is_array( $eig ) ) {
			return array_values( array_filter( array_map( 'intval', $eig ) ) );
		}
		return array_values( array_filter( array_map( 'intval', explode( ',', $eig ) ) ) );
	}

	// 2) ACF の gallery フィールド
	if ( function_exists( 'get_field_objects' ) ) {
		$fields = get_field_objects( $post_id );
		if ( is_array( $fields ) ) {
			foreach ( $fields as $f ) {
				if ( isset( $f['type'] ) && 'gallery' === $f['type'] && ! empty( $f['value'] ) ) {
					$out = array();
					foreach ( (array) $f['value'] as $img ) {
						if ( is_array( $img ) && isset( $img['ID'] ) ) { $out[] = (int) $img['ID']; }
						elseif ( is_numeric( $img ) ) { $out[] = (int) $img; }
					}
					if ( $out ) { return $out; }
				}
			}
		}
	}

	// 3) 独自指定（必要なら add_filter で）
	return (array) apply_filters( 'carmel_gallery_ids', array(), $post_id );
}
