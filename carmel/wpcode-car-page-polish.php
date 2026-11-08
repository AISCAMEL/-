<?php
/**
 * カーメル：車両ページ 仕上げ（重複除去＋空白ボックス除去＋問い合わせ刷新＋シミュ微調整）
 * ---------------------------------------------------------------------------
 * 車両詳細ページ（Dynamic Template / single-portfolio）の見た目を、承認済みの
 * 「完成イメージ（モック）」に寄せて整える。CSS と最小限の JS を出力する。
 *
 *   ① 緑ローンボックス上の「古い月々（静的二重表示）」を隠す。
 *   ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。
 *   ③ 問い合わせ欄を、低解像度のバナー画像から「くっきりSVGアイコン」版に作り替える。
 *      （電話/メールのリンク先はそのまま流用。見た目だけ刷新）
 *   ④ 返済回数シミュレーションの配色・サイズをモックに合わせて微調整。
 *   ⑤ 余白の調整。
 *
 * ・他スニペット/プラグインと衝突しないよう固有名＋function_exists ガード。
 * ・front のみ出力。作り替えは一度だけ（data 属性で二重実行を防止）。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_page_polish_css' ) ) {
	function carmelx_page_polish_css() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-page-polish">
/* === カーメル 車両ページ 仕上げ === */

/* ① 緑ボックスの上にある「古い月々（固定の静的数字）」を隠す。 */
.wpb_wrapper:has(> .wpb_text_column > .wpb_wrapper > .carmel-lg)
	> .wpb_text_column.has-custom-color {
	display: none !important;
}

/* ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。 */
.vc_section.vc_custom_carmelplan:not(:has(img, table, a, li, .carmel-plan)) {
	display: none !important;
}

/* ③ 問い合わせ欄（JSで作り替えたSVG版）の見た目 */
.cmx-contact { text-align: left; }
.cmx-contact h3 {
	margin: 0 0 10px; font-size: 13px; text-align: center;
	color: #6a7572; font-weight: 800;
}
.cmx-c {
	display: flex; align-items: center; gap: 11px;
	padding: 12px 15px; margin-bottom: 9px;
	border: 1.5px solid #e2e8e4; border-radius: 12px;
	text-decoration: none; color: #20292b;
	font-weight: 800; font-size: 14.5px; line-height: 1.3;
	transition: box-shadow .15s ease, transform .15s ease;
}
.cmx-c:last-child { margin-bottom: 0; }
.cmx-c svg { width: 22px; height: 22px; flex-shrink: 0; color: #2cac44; }
.cmx-c__t { display: flex; flex-direction: column; }
.cmx-c__t small { font-size: 11px; font-weight: 600; color: #6a7572; margin-top: 2px; }
.cmx-c--tel { background: #fffdf4; border-color: #f0e4b4; }
.cmx-c--tel svg { color: #e8a400; }
.cmx-c--mail { background: #fff; }
.cmx-c:hover { box-shadow: 0 5px 16px -7px rgba(0,0,0,.3); transform: translateY(-1px); }

/* ④ 返済回数シミュレーション：モックに寄せた微調整（安全な範囲のみ） */
.carmel-lg__sim-fullwide { max-width: 1040px; margin-left: auto; margin-right: auto; }
.carmel-lg__sim-badge { background: #2cac44 !important; color: #fff !important; }
.carmel-lg__sim-amount b { color: #2cac44 !important; }

/* ⑤ 余白の調整 */
.carmel-lg { margin-top: 2px; }
</style>
<script id="carmel-page-polish-js">
(function(){
	function run(){
		/* 問い合わせブロックを「くっきりSVG」版に作り替える */
		var head = null;
		var cols = document.querySelectorAll('.wpb_wrapper > .wpb_text_column');
		for (var i = 0; i < cols.length; i++) {
			if (cols[i].textContent.indexOf('このクルマに関するお問い合わせ') !== -1) { head = cols[i]; break; }
		}
		if (!head) { return; }
		var wrap = head.parentNode; /* 問い合わせブロックを内包する wpb_wrapper */
		if (!wrap || wrap.getAttribute('data-cmx-contact') === '1') { return; }

		var telA  = wrap.querySelector('a[href^="tel:"]');
		var mailA = wrap.querySelector('a[href*="forms.gle"], a[href*="forms.google"], a[href^="mailto:"]');
		var tel   = telA  ? telA.getAttribute('href')  : 'tel:050-1793-5554';
		var mail  = mailA ? mailA.getAttribute('href') : '#';
		var mailExternal = (mail.indexOf('http') === 0);

		var phoneSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>';
		var mailSvg  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>';

		var html =
			'<div class="cmx-contact">' +
			  '<h3>このクルマのお問い合わせ</h3>' +
			  '<a class="cmx-c cmx-c--tel" href="' + tel + '">' + phoneSvg +
			    '<span class="cmx-c__t">電話で問い合わせ<small>受付 10:00〜18:00</small></span></a>' +
			  '<a class="cmx-c cmx-c--mail" href="' + mail + '"' +
			    (mailExternal ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + mailSvg +
			    '<span class="cmx-c__t">メールで相談<small>24時間受付OK</small></span></a>' +
			'</div>';

		wrap.setAttribute('data-cmx-contact', '1');
		wrap.innerHTML = html;
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); }
	else { run(); }
})();
</script>
		<?php
	}
	add_action( 'wp_head', 'carmelx_page_polish_css', 99 );
}
