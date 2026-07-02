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
	echo '<script>' .
		'document.addEventListener("DOMContentLoaded",function(){' .

			/* LINEで在庫確認ボタンを非表示 */
			'document.querySelectorAll("a").forEach(function(a){' .
				'if(a.textContent.trim().indexOf("LINEで在庫確認")!==-1){' .
					'a.style.setProperty("display","none","important");' .
				'}' .
			'});' .

			/* このクルマに関するお問い合わせ セクションを非表示 */
			'document.querySelectorAll("*").forEach(function(el){' .
				'var t=el.textContent.trim();' .
				'if(t==="このクルマに関するお問い合わせ"){' .
					'var w=el.closest("section")||el.closest("[class*=\'contact\']")||el.closest("[class*=\'inquiry\']")||el.parentElement;' .
					'if(w&&w.tagName!=="BODY"&&w.tagName!=="MAIN"&&w.tagName!=="ARTICLE"){' .
						'w.style.setProperty("display","none","important");' .
					'}' .
				'}' .
			'});' .

			/* 空の枠（黒枠など）を非表示 */
			'document.querySelectorAll("body.single-portfolio *").forEach(function(el){' .
				'var skip=["BODY","MAIN","ARTICLE","HEADER","FOOTER","SECTION","NAV","SCRIPT","STYLE","NOSCRIPT","TABLE","TR","TD","TH"];' .
				'if(skip.indexOf(el.tagName)!==-1){return;}' .
				'var txt=el.textContent.replace(/[ ​\s]+/g,"");' .
				'if(txt!==""){return;}' .
				'if(el.querySelector("img,video,iframe,svg,canvas,input,select,textarea")){return;}' .
				'var s=window.getComputedStyle(el);' .
				'if(s.display==="none"||s.visibility==="hidden"){return;}' .
				'var bw=parseFloat(s.borderTopWidth)||0;' .
				'if(el.offsetWidth>10&&el.offsetHeight>0&&bw>0){el.style.setProperty("display","none","important");}' .
			'});' .

		'});' .
	'</script>';
} );

add_action( 'wp_head', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }
	echo '<style>' .
		'.carmel-lg{margin:10px 0 6px !important;}' .
		'.wp-block-group:empty,.elementor-widget-container:empty{display:none !important;}' .
	'</style>';
} );
