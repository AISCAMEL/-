<?php
/**
 * カーメル：車両ページ 仕上げ（重複・空白ボックスの除去＋余白調整）
 * ---------------------------------------------------------------------------
 * 車両詳細ページ（Dynamic Template / single-portfolio）で見た目を整える CSS を出力する。
 *
 * 直す内容：
 *   ① 緑のローンボックスの上に出ている「古い月々（固定の静的数字）」を隠す。
 *      → 下の緑ボックス内の月々と二重表示になり、金額も食い違っていたため。
 *   ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。
 *      → vc_custom_carmelplan。中身が空のときだけ隠す（将来中身が入れば自動で表示）。
 *   ③ 余白を少し詰めてバランスを整える。
 *
 * ・他スニペット/プラグインと衝突しないよう固有名＋function_exists ガード。
 * ・front のみ出力（管理画面では何もしない）。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_page_polish_css' ) ) {
	function carmelx_page_polish_css() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-page-polish">
/* === カーメル 車両ページ 仕上げ === */

/* ① 緑ボックスの上にある「古い月々（固定の静的数字）」を隠す。
      .carmel-lg を含む wpb_wrapper 内の、色付き静的テキスト列だけが対象。 */
.wpb_wrapper:has(> .wpb_text_column > .wpb_wrapper > .carmel-lg)
	> .wpb_text_column.has-custom-color {
	display: none !important;
}

/* ② 中身が空の「購入プラン」セクション（背景だけの白い箱）を隠す。
      画像/表/リンク/プラン本体のいずれも無いときだけ隠す（将来内容が入れば表示される）。 */
.vc_section.vc_custom_carmelplan:not(:has(img, table, a, li, .carmel-plan)) {
	display: none !important;
}

/* ③ 余白の調整：古い月々が消えた分、緑ボックス上の余白を軽く整える。 */
.carmel-lg { margin-top: 2px; }
</style>
		<?php
	}
	add_action( 'wp_head', 'carmelx_page_polish_css', 99 );
}
