<?php
/**
 * カーメル：フィールドマップ診断（一時利用）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫(portfolio)編集画面に存在する
 *          ① ACFフィールド（ラベル / data-name / 種類 / select候補）
 *          ② 車両入力STEP UI(#carmel_step_ui)内の入力要素（id / ラベル）
 *        を一覧でダンプする。これを丸ごとコピペしてもらえば、
 *        「StepUIの項目 ↔ 実フィールド」の正しい対応表が作れる。
 *
 * 導入 : WPCode →「PHP Snippet」/「自動挿入・管理画面」で貼り付けて有効化。
 *        車両を1台 編集画面で開くと、画面上部に黒いボックスが出る。
 *        中身を全選択コピーして貼ってください。確認できたら無効化してOK。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_footer-post.php',     'carmel_field_map_debug' );
add_action( 'admin_footer-post-new.php', 'carmel_field_map_debug' );

function carmel_field_map_debug() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<style>
	#cs-fmap { position:relative; margin:16px 0; border:2px solid #1f2d3d; border-radius:8px;
		background:#0f1722; color:#cfe3ff; font:12px/1.5 monospace; }
	#cs-fmap h3 { margin:0; padding:8px 12px; background:#1f2d3d; color:#fff; font-size:13px; }
	#cs-fmap .cs-fmap-body { padding:10px 12px; max-height:420px; overflow:auto; white-space:pre; }
	#cs-fmap .cs-fmap-copy { position:absolute; top:6px; right:10px; cursor:pointer;
		background:#f0c36d; color:#1f2d3d; border:0; border-radius:5px; padding:4px 10px; font-weight:700; }
	</style>
	<div id="cs-fmap">
		<h3>🔎 カーメル フィールドマップ診断（この内容を全部コピーして送ってください）</h3>
		<button type="button" class="cs-fmap-copy" onclick="(function(){var t=document.getElementById('cs-fmap-out');t.select();document.execCommand('copy');this.textContent='コピーしました';}).call(this)">コピー</button>
		<div class="cs-fmap-body"><textarea id="cs-fmap-out" style="width:100%;height:380px;background:#0f1722;color:#cfe3ff;border:0;font:12px/1.5 monospace;" readonly></textarea></div>
	</div>
	<script>
	(function ($) {
		'use strict';
		function txt( el ) { return ( el ? ( el.textContent || '' ) : '' ).replace( /\s+/g, ' ' ).trim(); }

		$( function () {
			var out = [];
			out.push( '===== ACF FIELDS (portfolio edit screen) =====' );
			out.push( 'label\t| data-name\t| type\t| choices' );
			$( '.acf-field[data-name]' ).each( function () {
				var $f   = $( this );
				var name = $f.attr( 'data-name' ) || '';
				if ( ! name || name.charAt( 0 ) === '_' ) { return; } // 見出し系は除外
				var label = txt( $f.find( '> .acf-label label' ).get( 0 ) ) || txt( $f.find( '.acf-label' ).get( 0 ) );
				var type  = $f.attr( 'data-type' ) || '';
				var choices = '';
				var $sel = $f.find( 'select' ).first();
				if ( $sel.length ) {
					var opts = [];
					$sel.find( 'option' ).each( function () { var v = $.trim( $( this ).text() ); if ( v ) { opts.push( v ); } } );
					choices = opts.slice( 0, 30 ).join( ' / ' );
				}
				if ( $f.find( 'input[type="checkbox"]' ).length ) { type = type + '(checkbox)'; }
				out.push( label + '\t| ' + name + '\t| ' + type + '\t| ' + choices );
			} );

			out.push( '' );
			out.push( '===== STEP UI INPUTS (#carmel_step_ui) =====' );
			out.push( 'id\t| tag\t| nearby-label/placeholder' );
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ui ) {
				$( ui ).find( 'input, select, textarea' ).each( function () {
					var el  = this;
					var id  = el.id || '(no-id)';
					var tag = el.tagName.toLowerCase() + ( el.type ? ':' + el.type : '' );
					var lab = '';
					if ( el.id ) { lab = txt( ui.querySelector( 'label[for="' + el.id + '"]' ) ); }
					if ( ! lab ) { lab = txt( el.closest( 'label' ) ); }
					if ( ! lab ) { lab = el.placeholder || ''; }
					out.push( id + '\t| ' + tag + '\t| ' + lab );
				} );
			} else {
				out.push( '(#carmel_step_ui が見つかりません＝STEP UIが描画されていない画面です)' );
			}

			document.getElementById( 'cs-fmap-out' ).value = out.join( '\n' );
		} );
	})( jQuery );
	</script>
	<?php
}
