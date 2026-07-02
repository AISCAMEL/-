<?php
/**
 * カーメル：車両詳細ページ UI 整理
 * ---------------------------------------------------------------------------
 * - 「LINEで在庫確認はこちら」ボタンを非表示
 * - 「このクルマに関するお問い合わせ」セクションを非表示
 *   （スティッキーバー carmel-cta-bar + ローン概算ボックスCTAで代替済み）
 * - 空白ブロック非表示
 *
 * 導入 : WPCode → 新規 PHP Snippet → Run Everywhere → 有効化
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {

		/* 「LINEで在庫確認はこちら」ボタンを非表示 */
		document.querySelectorAll('a').forEach(function (a) {
			if ( a.textContent.trim().indexOf('LINEで在庫確認') !== -1 ) {
				a.style.setProperty('display', 'none', 'important');
			}
		});

		/* 「このクルマに関するお問い合わせ」セクションを非表示 */
		document.querySelectorAll('*').forEach(function (el) {
			var t = el.textContent.trim();
			if ( t === 'このクルマに関するお問い合わせ' ) {
				/* 最も近い section か 大きめの div を取得して丸ごと隠す */
				var wrap = el.closest('section') || el.closest('[class*="contact"]') || el.closest('[class*="inquiry"]') || el.parentElement;
				/* 親が body や main に近すぎる場合はスキップ */
				if ( wrap && wrap.tagName !== 'BODY' && wrap.tagName !== 'MAIN' && wrap.tagName !== 'ARTICLE' ) {
					wrap.style.setProperty('display', 'none', 'important');
				}
			}
		});

		/* 空の枠（タイトル下の白いボックス等）を非表示 */
		document.querySelectorAll('.entry-content > *').forEach(function (el) {
			if ( el.textContent.trim() === '' ) {
				el.style.setProperty('display', 'none', 'important');
			}
		});

	});
	</script>
	<?php
} );

add_action( 'wp_head', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }
	?>
	<style>
	/* ローン概算ボックス上下余白 */
	.carmel-lg { margin: 10px 0 6px !important; }
	/* 空のWordPressブロック */
	.wp-block-group:empty,
	.elementor-widget-container:empty { display: none !important; }
	</style>
	<?php
} );
