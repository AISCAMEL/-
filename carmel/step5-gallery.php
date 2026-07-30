<?php
/**
 * カーメル：STEP5 画像ギャラリー（複数画像アップ / 1枚目=アイキャッチ）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫STEP UIの中で、車の画像を複数枚アップロード・並べ替え・削除できる。
 *        保存先は post_meta 'carmel_gallery'（カンマ区切りの添付ID）。
 *        1枚目はアイキャッチに自動同期（featured-from-gallery 側で対応）。
 *        フロント表示は [carmel_gallery] ショートコード。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

/* メディアアップローダを読み込む */
add_action( 'admin_enqueue_scripts', 'carmel_step5_enqueue' );
function carmel_step5_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->post_type === 'portfolio' ) {
		wp_enqueue_media();
	}
}

/* STEP5 パネル出力 */
add_action( 'admin_footer-post.php',     'carmel_step5_gallery' );
add_action( 'admin_footer-post-new.php', 'carmel_step5_gallery' );
function carmel_step5_gallery() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) { return; }

	global $post;
	$raw = $post ? get_post_meta( $post->ID, 'carmel_gallery', true ) : '';
	$ids = $raw ? array_filter( array_map( 'intval', explode( ',', $raw ) ) ) : array();

	$thumbs = '';
	foreach ( $ids as $id ) {
		$url = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( ! $url ) { continue; }
		$thumbs .= carmel_step5_thumb_html( $id, $url );
	}
	?>
	<style>
	#cs-gallery { margin:14px 0; border:1px solid #d9dee5; border-radius:8px; background:#fff; }
	#cs-gallery .cs-g-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:8px 8px 0 0; font-weight:700; }
	#cs-gallery .cs-g-body { padding:12px 14px; }
	#cs-gallery .cs-g-add { background:#2271b1; color:#fff; border:0; padding:8px 16px; border-radius:5px; cursor:pointer; font-weight:700; }
	#cs-g-thumbs { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
	#cs-g-thumbs .cs-g-thumb { position:relative; width:110px; }
	#cs-g-thumbs .cs-g-thumb img { width:110px; height:82px; object-fit:cover; border:1px solid #ccc; border-radius:6px; display:block; }
	#cs-g-thumbs .cs-g-thumb .cs-g-badge { position:absolute; top:3px; left:3px; background:#c0392b; color:#fff; font-size:10px; padding:1px 6px; border-radius:8px; }
	#cs-g-thumbs .cs-g-thumb .cs-g-tools { display:flex; gap:4px; margin-top:3px; }
	#cs-g-thumbs .cs-g-thumb button { flex:1; font-size:11px; padding:2px 0; cursor:pointer; border:1px solid #bbb; border-radius:4px; background:#f6f7f7; }
	#cs-g-thumbs .cs-g-thumb .cs-g-del { color:#c0392b; }
	#cs-gallery .cs-g-note { font-size:11px; color:#888; margin-top:8px; }
	</style>
	<script>
	(function ($) {
		'use strict';

		function thumbHtml( id, url ) {
			return '<div class="cs-g-thumb" data-id="' + id + '">' +
				'<img src="' + url + '">' +
				'<span class="cs-g-badge" style="display:none;">1枚目</span>' +
				'<div class="cs-g-tools">' +
				'<button type="button" class="cs-g-first" title="1枚目にする">★</button>' +
				'<button type="button" class="cs-g-del" title="削除">×</button>' +
				'</div></div>';
		}

		function syncHidden() {
			var ids = [];
			$( '#cs-g-thumbs .cs-g-thumb' ).each( function () { ids.push( $( this ).data( 'id' ) ); } );
			$( '#cs-gallery-ids' ).val( ids.join( ',' ) );
			$( '#cs-g-thumbs .cs-g-thumb .cs-g-badge' ).hide();
			$( '#cs-g-thumbs .cs-g-thumb' ).first().find( '.cs-g-badge' ).show();
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui ) { return; }

			var html = '<div id="cs-gallery">' +
				'<div class="cs-g-h">📷 車両画像（複数可・1枚目がアイキャッチ）</div>' +
				'<div class="cs-g-body">' +
				'<button type="button" class="cs-g-add">＋ 画像を追加</button>' +
				'<input type="hidden" id="cs-gallery-ids" name="carmel_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">' +
				'<?php echo wp_create_nonce( 'carmel_gallery_save' ) ? '<input type="hidden" name="carmel_gallery_nonce" value="' . esc_attr( wp_create_nonce( 'carmel_gallery_save' ) ) . '">' : ''; ?>' +
				'<div id="cs-g-thumbs"><?php echo str_replace( array( "\n", "\r" ), '', addslashes( $thumbs ) ); ?></div>' +
				'<div class="cs-g-note">ドラッグ不要。★で1枚目に、×で削除。並びの1枚目がアイキャッチ＆フロント先頭になります。</div>' +
				'</div></div>';

			var $mount = $( '#cs-gallery-mount' );
			if ( $mount.length ) { $mount.html( html ); } else { $( ui ).append( html ); }
			syncHidden();

			var frame;
			$( document ).on( 'click', '.cs-g-add', function ( e ) {
				e.preventDefault();
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title: '車両画像を選択',
					multiple: true,
					library: { type: 'image' },
					button: { text: 'この画像を追加' }
				} );
				frame.on( 'select', function () {
					var sel = frame.state().get( 'selection' );
					sel.each( function ( att ) {
						var a = att.attributes;
						if ( $( '#cs-g-thumbs .cs-g-thumb[data-id="' + a.id + '"]' ).length ) { return; }
						var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
						$( '#cs-g-thumbs' ).append( thumbHtml( a.id, url ) );
					} );
					syncHidden();
				} );
				frame.open();
			} );

			$( document ).on( 'click', '.cs-g-del', function () {
				$( this ).closest( '.cs-g-thumb' ).remove();
				syncHidden();
			} );

			$( document ).on( 'click', '.cs-g-first', function () {
				var $t = $( this ).closest( '.cs-g-thumb' );
				$( '#cs-g-thumbs' ).prepend( $t );
				syncHidden();
			} );
		} );

	})( jQuery );
	</script>
	<?php
}

/* サムネイルHTML（初期表示用） */
function carmel_step5_thumb_html( $id, $url ) {
	return '<div class="cs-g-thumb" data-id="' . esc_attr( $id ) . '">' .
		'<img src="' . esc_url( $url ) . '">' .
		'<span class="cs-g-badge" style="display:none;">1枚目</span>' .
		'<div class="cs-g-tools">' .
		'<button type="button" class="cs-g-first" title="1枚目にする">★</button>' .
		'<button type="button" class="cs-g-del" title="削除">×</button>' .
		'</div></div>';
}

/* 保存 */
add_action( 'save_post_portfolio', 'carmel_step5_save', 10, 1 );
function carmel_step5_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['carmel_gallery_nonce'] ) || ! wp_verify_nonce( $_POST['carmel_gallery_nonce'], 'carmel_gallery_save' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	if ( ! isset( $_POST['carmel_gallery'] ) ) { return; }

	$ids = array_filter( array_map( 'intval', explode( ',', (string) $_POST['carmel_gallery'] ) ) );
	if ( $ids ) {
		update_post_meta( $post_id, 'carmel_gallery', implode( ',', $ids ) );
	} else {
		delete_post_meta( $post_id, 'carmel_gallery' );
	}
}

/* フロント表示 [carmel_gallery] */
add_shortcode( 'carmel_gallery', 'carmel_gallery_shortcode' );
function carmel_gallery_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	$pid  = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();
	if ( ! $pid ) { return ''; }
	$raw = get_post_meta( $pid, 'carmel_gallery', true );
	$ids = $raw ? array_filter( array_map( 'intval', explode( ',', $raw ) ) ) : array();
	if ( ! $ids ) { return ''; }

	$out = '<div class="carmel-gallery">';
	foreach ( $ids as $id ) {
		$full  = wp_get_attachment_image_url( $id, 'large' );
		$thumb = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'carmel-gallery-img', 'loading' => 'lazy' ) );
		if ( ! $thumb ) { continue; }
		$out .= $full
			? '<a class="carmel-gallery-link" href="' . esc_url( $full ) . '">' . $thumb . '</a>'
			: $thumb;
	}
	$out .= '</div>';
	return $out;
}

add_action( 'wp_head', 'carmel_gallery_style' );
function carmel_gallery_style() {
	?>
	<style>
	.carmel-gallery { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:8px; margin:16px 0; }
	.carmel-gallery img { width:100%; height:110px; object-fit:cover; border-radius:6px; display:block; }
	</style>
	<?php
}
