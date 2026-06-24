<?php
/**
 * カーメル：STEP6 確認（全体図）
 * ---------------------------------------------------------------------------
 * 目的 : STEP1〜5で入力した内容を1画面で一覧確認できる「全体図」を表示。
 *        保存（更新）前の最終チェック用。現在のACF/STEPの値をその場で集計表示。
 *
 * 表示 : タイトル / 基本情報 / 装備一覧 / 見積もり / 担当店舗 / 画像枚数
 *
 * 設置 : STEP6領域に <div id="cs-step6-mount"></div> を置くとそこに表示。
 *        無ければ STEP UI 末尾に表示。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step6_review' );
add_action( 'admin_footer-post-new.php', 'carmel_step6_review' );
function carmel_step6_review() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) { return; }
	?>
	<style>
	#cs-review { margin:14px 0; border:2px solid #1f2d3d; border-radius:8px; background:#fff; }
	#cs-review .cs-rv-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:6px 6px 0 0;
		font-weight:700; display:flex; justify-content:space-between; align-items:center; }
	#cs-review .cs-rv-refresh { background:#3a4a5d; color:#fff; border:0; padding:5px 12px; border-radius:5px; cursor:pointer; font-size:12px; }
	#cs-review .cs-rv-body { padding:12px 14px; }
	#cs-review .cs-rv-sec { margin-bottom:14px; }
	#cs-review .cs-rv-t { font-weight:700; color:#1f2d3d; border-left:4px solid #c0392b; padding-left:8px; margin-bottom:6px; }
	#cs-review .cs-rv-grid { display:grid; grid-template-columns:140px 1fr; gap:2px 10px; font-size:13px; }
	#cs-review .cs-rv-grid .k { color:#666; }
	#cs-review .cs-rv-grid .v { color:#111; font-weight:600; }
	#cs-review .cs-rv-badges { display:flex; flex-wrap:wrap; gap:5px; }
	#cs-review .cs-rv-badge { background:#f3f6fa; border:1px solid #d9e0e8; border-radius:12px; padding:3px 9px; font-size:12px; }
	#cs-review .cs-rv-money { font-size:18px; font-weight:700; color:#c0392b; }
	#cs-review .cs-rv-warn { color:#c0392b; font-size:12px; }
	#cs-review .cs-rv-note { margin-top:6px; padding:8px 12px; background:#fff7e6; border:1px solid #f0c36d; border-radius:6px; font-size:12px; }
	</style>
	<script>
	(function ($) {
		'use strict';

		function acfVal( name ) {
			var $f = $( '.acf-field[data-name="' + name + '"]' ).first();
			if ( ! $f.length ) { return ''; }
			var $sel = $f.find( 'select' ).first();
			if ( $sel.length ) { return $.trim( $sel.find( 'option:selected' ).text() ); }
			var $i = $f.find( 'input[type="text"], input[type="number"], input[type="url"], textarea' ).first();
			return $i.length ? $.trim( $i.val() ) : '';
		}
		function acfRadio( name ) {
			var $f = $( '.acf-field[data-name="' + name + '"]' ).first();
			return $f.length ? $.trim( $f.find( 'input[type="radio"]:checked' ).closest( 'label' ).text() ) : '';
		}
		function yen( s ) {
			var d = String( s == null ? '' : s ).replace( /[^0-9]/g, '' );
			return d ? Number( d ).toLocaleString() + '円' : '—';
		}
		function row( k, v ) {
			return '<div class="k">' + k + '</div><div class="v">' + ( v ? v : '—' ) + '</div>';
		}

		function checkedEquip() {
			var out = [];
			$( '.acf-field input[type="checkbox"]:checked' ).each( function () {
				var t = $.trim( $( this ).closest( 'label' ).text() );
				if ( t && out.indexOf( t ) === -1 ) { out.push( t ); }
			} );
			return out;
		}

		function build() {
			var basic = row( 'メーカー', acfVal( 'marker' ) ) + row( '車種・型式', acfVal( 'type' ) ) +
				row( '年式', acfVal( 'year' ) ) + row( '走行距離', acfVal( 'mileage' ) ) +
				row( '色', acfVal( 'color' ) ) + row( '排気量', acfVal( 'displacement' ) ) +
				row( '車検', acfVal( 'inspection' ) ) + row( 'ミッション', acfVal( 'mission' ) ) +
				row( '駆動', acfVal( 'kudou' ) ) + row( '管理番号', acfVal( 'kanri_bango' ) ) +
				row( 'ステータス', acfRadio( 'stauts' ) || acfVal( 'status' ) );

			var eq = checkedEquip();
			var eqHtml = eq.length
				? '<div class="cs-rv-badges">' + eq.map( function ( e ) { return '<span class="cs-rv-badge">' + e + '</span>'; } ).join( '' ) + '</div>'
				: '<span class="cs-rv-warn">装備が未選択です</span>';

			var total = acfVal( 'est_total' );
			var getsu = acfVal( 'est_getsugaku' );
			var estHtml = '<div class="cs-rv-grid">' +
				'<div class="k">支払総額</div><div class="v cs-rv-money">' + yen( total ) + '</div>' +
				'<div class="k">月々支払</div><div class="v cs-rv-money">' + yen( getsu ) + '</div></div>';

			var shop = row( '販売店', acfVal( 'shop' ) ) + row( 'TEL', acfVal( 'tel' ) ) +
				row( 'LINE', acfVal( 'line-link' ) ) + row( '問い合わせ', acfVal( 'contact-link' ) );

			var gids = ( $( '#cs-gallery-ids' ).val() || '' ).split( ',' ).filter( function ( x ) { return x; } );

			var html =
				'<div class="cs-rv-sec"><div class="cs-rv-t">タイトル</div><div class="v">' +
					( $.trim( $( '#title' ).val() ) || '<span class="cs-rv-warn">未入力</span>' ) + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">基本情報</div><div class="cs-rv-grid">' + basic + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">装備（' + eq.length + '件）</div>' + eqHtml + '</div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">見積もり</div>' + estHtml + '</div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">担当店舗</div><div class="cs-rv-grid">' + shop + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">画像</div><div class="v">' + gids.length + ' 枚' +
					( gids.length ? '' : ' <span class="cs-rv-warn">（未登録）</span>' ) + '</div></div>' +
				'<div class="cs-rv-note">内容を確認したら、ページ右上の「更新」または「公開」を押して保存してください。</div>';

			$( '#cs-rv-content' ).html( html );
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui ) { return; }

			var shell = '<div id="cs-review">' +
				'<div class="cs-rv-h"><span>📋 STEP6 確認（全体図）</span>' +
				'<button type="button" class="cs-rv-refresh">🔄 最新の内容を表示</button></div>' +
				'<div class="cs-rv-body"><div id="cs-rv-content"></div></div></div>';

			var $mount = $( '#cs-step6-mount' );
			if ( $mount.length ) { $mount.html( shell ); } else { $( ui ).append( shell ); }

			build();
			$( document ).on( 'click', '.cs-rv-refresh', build );
			$( document ).on( 'click', '.cs-nav-btn, .cs-btn-next, .cs-btn-back', function () { setTimeout( build, 120 ); } );
		} );

	})( jQuery );
	</script>
	<?php
}
