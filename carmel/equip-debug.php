<?php
/**
 * カーメル：装備マッチング診断ツール（一時的に使用）
 * ---------------------------------------------------------------------------
 * 目的 : STEP2 の装備名と ACF の装備チェックボックス名の一致状況を可視化する。
 *        「どれが一致／不一致」「ACFに無い装備」が一覧で分かる。
 *        → 結果のスクショをもらえれば、別名表を完成＆ACF不足分の対応を決められる。
 *
 * 使い方 : WPCode PHP Snippet（Run Everywhere）で一時的に有効化 →
 *          在庫編集画面を開く → STEP UI の下に「装備マッチ診断」パネルが出る →
 *          スクショを送る → 確認後このスニペットは無効化/削除してOK。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_equip_debug' );
add_action( 'admin_footer-post-new.php', 'carmel_equip_debug' );

function carmel_equip_debug() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<style>
	#cs-equip-debug { margin:14px 0; border:2px solid #c0392b; border-radius:8px; background:#fff; font-size:13px; }
	#cs-equip-debug h3 { margin:0; padding:8px 12px; background:#c0392b; color:#fff; border-radius:6px 6px 0 0; }
	#cs-equip-debug .cs-dbg-body { padding:10px 12px; }
	#cs-equip-debug table { border-collapse:collapse; width:100%; margin-bottom:10px; }
	#cs-equip-debug th, #cs-equip-debug td { border:1px solid #e0e0e0; padding:3px 8px; text-align:left; }
	#cs-equip-debug .ok { color:#1e7e34; font-weight:700; }
	#cs-equip-debug .ng { color:#c0392b; font-weight:700; }
	#cs-equip-debug .cs-dbg-list { font-size:12px; color:#555; word-break:break-all; }
	</style>
	<script>
	(function ($) {
		'use strict';

		function norm( s ) {
			return ( s == null ? '' : String( s ) ).replace( /[\s　]+/g, '' ).toLowerCase();
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui ) return;

			// ACF チェックボックスのラベル索引
			var acfLabels = [];
			var acfIndex = {};
			$( '.acf-field input[type="checkbox"]' ).each( function () {
				var $cb = $( this );
				var label = $.trim( $cb.closest( 'label' ).text() );
				var dn = $cb.closest( '.acf-field' ).attr( 'data-name' ) || '';
				if ( label ) {
					acfLabels.push( label + ( dn ? ' [' + dn + ']' : '' ) );
					if ( ! ( norm( label ) in acfIndex ) ) acfIndex[ norm( label ) ] = label;
				}
				if ( $cb.val() && ! ( norm( $cb.val() ) in acfIndex ) ) acfIndex[ norm( $cb.val() ) ] = label || $cb.val();
			} );

			// STEP2 装備
			var rows = '';
			var step2 = [];
			$( '.cs-equip-check' ).each( function () {
				var $c = $( this );
				var name = $.trim( $c.val() ) || $.trim( $c.closest( 'label' ).text() );
				if ( ! name ) return;
				step2.push( name );
				var hit = acfIndex[ norm( name ) ];
				rows += '<tr><td>' + name + '</td>' +
					( hit ? '<td class="ok">✅ 一致 → ' + hit + '</td>'
					      : '<td class="ng">❌ ACFに無い／名前違い</td>' ) + '</tr>';
			} );

			var html = '<div id="cs-equip-debug">' +
				'<h3>🔧 装備マッチ診断（確認後このスニペットは無効化してください）</h3>' +
				'<div class="cs-dbg-body">' +
				'<p><b>STEP2の装備（' + step2.length + '件）と ACF の一致状況：</b></p>' +
				'<table><thead><tr><th>STEP2の装備名</th><th>判定</th></tr></thead><tbody>' +
				( rows || '<tr><td colspan="2">.cs-equip-check が見つかりません（STEP2のチェック要素のクラスが違う可能性）</td></tr>' ) +
				'</tbody></table>' +
				'<p><b>ACFに存在する装備チェック（' + acfLabels.length + '件）：</b></p>' +
				'<div class="cs-dbg-list">' + ( acfLabels.join( ' ／ ' ) || '（無し）' ) + '</div>' +
				'</div></div>';

			$( ui ).after( html );
		} );

	})( jQuery );
	</script>
	<?php
}
