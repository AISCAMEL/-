<?php
/**
 * カーメル：車両ページ 仕上げ（モック“完成イメージ”へ全体を寄せる総合スタイル）
 * ---------------------------------------------------------------------------
 * 車両詳細ページ（Dynamic Template / single-portfolio）の全セクションの見た目を、
 * 承認済みの「完成イメージ（モック）」に合わせて整える。CSS ＋ 最小限の JS を出力。
 *
 *   ① 緑ローンボックス上の「古い月々（静的二重表示）」を隠す。
 *   ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。
 *   ③ 問い合わせ欄を「くっきりSVGアイコン」版に作り替える（リンク先は流用）。
 *   ④ ローンボックス（.carmel-lg）をモック準拠の緑カードに。
 *   ⑤ 基本情報・スペック表（.c-table01）をモック準拠の「緑の見出しセル」表に。
 *   ⑥ 返済回数シミュレーションの配色をモックに合わせ微調整。
 *   ⑦ 装備タグ・スタッフカード・余白の調整。
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
/* === カーメル 車両ページ 仕上げ（モック準拠） ===================== */
/* 配色：緑 #2cac44/#1c7a3a、薄緑 #f1f9f4、枠線 #cfe7d8/#e2e8e4、文字 #20292b、補助 #6a7572、橙 #e8500a */

/* ① 緑ボックスの上にある「古い月々（固定の静的数字）」を隠す。 */
.wpb_wrapper:has(> .wpb_text_column > .wpb_wrapper > .carmel-lg)
	> .wpb_text_column.has-custom-color {
	display: none !important;
}

/* ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。 */
.vc_section.vc_custom_carmelplan:not(:has(img, table, a, li, .carmel-plan)) {
	display: none !important;
}

/* ─────────────────────────────────────────────
   ④ ローンボックス（モック準拠の緑カード）
   ───────────────────────────────────────────── */
.carmel-lg {
	background: #fff !important;
	border: 1px solid #cfe7d8 !important;
	border-radius: 16px !important;
	overflow: hidden !important;
	box-shadow: 0 6px 22px -14px rgba(28,122,58,.45) !important;
	margin-top: 2px;
	padding: 0 !important;
}
.carmel-lg__price {
	background: linear-gradient(135deg,#1c7a3a,#2cac44) !important;
	color: #fff !important;
	padding: 15px 18px !important;
	margin: 0 !important;
	display: flex !important;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
}
.carmel-lg__price-label { font-size: 11px; font-weight: 700; opacity: .9; letter-spacing: .05em; color:#fff !important; }
.carmel-lg__price-val { font-size: 27px; font-weight: 900; line-height: 1; color:#fff !important; }
.carmel-lg__price-val small { font-size: 13px; font-weight: 700; opacity: .9; margin-left: 2px; }
.carmel-lg__t { font-size: 12px; font-weight: 800; color: #1c7a3a !important; margin: 14px 18px 8px !important; }
.carmel-lg__rows { display: flex !important; gap: 7px; margin: 0 18px !important; padding: 0 !important; }
.carmel-lg__row {
	flex: 1; background: #f1f9f4 !important; border: 1px solid #cfe7d8 !important;
	border-radius: 10px; padding: 9px 4px !important; text-align: center;
	display: flex; flex-direction: column; gap: 2px; line-height: 1.25;
	font-size: 14px; font-weight: 900; color: #20292b !important;
}
.carmel-lg__row b { font-size: 11px; color: #1c7a3a !important; font-weight: 800; }
.carmel-lg__note { margin: 9px 18px 0 !important; font-size: 11px; color: #6a7572 !important; }
.carmel-lg__warranty-slots { margin: 0 18px; }
/* CTA ボタン */
.carmel-lg__cta { margin: 14px 18px 18px !important; display: flex; flex-direction: column; gap: 8px; }
.carmel-lg__btn { border-radius: 10px !important; font-weight: 800 !important; }
.carmel-lg__btn--shinsa { background: #e8500a !important; color:#fff !important; }
.carmel-lg__btn--line { background: #06c755 !important; color:#fff !important; }
.carmel-lg__cta-sub { color:#6a7572 !important; font-size:11px !important; text-align:center; }

/* ─────────────────────────────────────────────
   ⑤ 基本情報・スペック表（モック準拠：緑の見出しセル）
   ───────────────────────────────────────────── */
.c-table01 { width: 100%; border-collapse: collapse; }
.c-table01 th {
	background: #f1f9f4 !important; color: #6a7572 !important;
	font-weight: 700 !important; font-size: 13px; text-align: left;
	padding: 10px 12px !important; border: 1px solid #e2e8e4 !important;
	white-space: nowrap; width: 42%;
}
.c-table01 td {
	font-size: 13px; font-weight: 700 !important; color: #20292b !important;
	padding: 10px 12px !important; border: 1px solid #e2e8e4 !important;
}
.p-single-sec2__table { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
@media (max-width: 640px) { .p-single-sec2__table { grid-template-columns: 1fr; } }
.c-table01.mt, .c-table01.u-mb30 { margin-top: 0; }

/* ─────────────────────────────────────────────
   ⑥ 返済回数シミュレーション（モック準拠）
   ───────────────────────────────────────────── */
.carmel-lg__sim-fullwide { max-width: 1040px; margin-left: auto; margin-right: auto; }
.carmel-lg__sim-badge { background: #2cac44 !important; color: #fff !important; }
.carmel-lg__sim-amount b { color: #2cac44 !important; }
/* タイトル2重を解消：元のタイトルを隠し、全幅ラッパーのタイトルだけ残す */
.carmel-lg__sim-fullwide .carmel-lg__sim-title { display: none !important; }

/* ─────────────────────────────────────────────
   ⑧ テンプレ内の不要要素を外す・CTAボタンの適正化
   ───────────────────────────────────────────── */
/* 「LINEで在庫確認はこちら」の緑バーを外す */
.vc_custom_1729509516503 { display: none !important; }
/* お問い合わせ箱の黒枠（border 3px #333333）を外し、やわらかいカードに */
.vc_custom_1707390645994 {
	border: 1px solid #e2e8e4 !important;
	border-radius: 14px !important;
	margin-top: 18px !important;
	background: #fff !important;
	box-shadow: 0 4px 16px -12px rgba(0,0,0,.25) !important;
}
/* CTA（審査/LINE）ボタンを適正サイズに */
.carmel-lg__cta { margin: 14px 18px 18px !important; display: flex !important; flex-direction: column; gap: 8px; }
.carmel-lg__btn {
	display: block !important; text-align: center !important; width: auto !important;
	padding: 13px !important; font-size: 14.5px !important; line-height: 1.25 !important;
	border-radius: 10px !important;
}

/* ─────────────────────────────────────────────
   ⑦ 装備タグ・スタッフカードの微調整
   ───────────────────────────────────────────── */
.cx-staff__role { background: #e8500a !important; color:#fff !important; }
.cx-staff__name { font-weight: 900 !important; }

/* ─────────────────────────────────────────────
   ③ 問い合わせ欄（JSで作り替えたSVG版）の見た目
   ───────────────────────────────────────────── */
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
