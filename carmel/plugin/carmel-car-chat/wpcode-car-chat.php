<?php
/**
 * カーメル：車両ページの「LINEで相談」を「チャットで相談」に切り替える
 * ---------------------------------------------------------------------------
 * 車両ページ（portfolio）の本文・ローン概算・販売店・下部CTA・固定バーにある
 * LINE ボタンを、サイトのAIチャット（カーメル AIチャットボット）を開くボタンに変える。
 *   ・押すとチャットが開き、入力欄に「この車について相談したいです：車名（管理番号）」が入る。
 *     お客様はそのまま送るか、書き足して送る。
 *   ・チャットは車両ページの番号を毎回サーバーへ送っている（page_car_id）。
 *     さらに最初の一言に車名と管理番号が入るので、別のページへ移っても同じ車の話を続けられる。
 *   ・ヘッダー・フッター・スマホメニューの LINE と、チャットの中の「LINEで直接相談する」は残す。
 *   ・チャットが無いページ・チャットが動いていない時は、LINE ボタンをそのまま残す（壊さない）。
 *   ・画像のバナー（LINE の画像）は文字を変えられないので対象外。
 * 止めたい時：プラグインを無効化すれば元の LINE ボタンに戻る。
 * 固有名＋function_exists ガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 車名の短い表示（例：スズキ キザシ 2014年（CM-0251）） */
if ( ! function_exists( 'carmelx_carchat_label' ) ) {
	function carmelx_carchat_label( $post_id ) {
		$get = function ( $k ) use ( $post_id ) {
			$v = function_exists( 'get_field' ) ? get_field( $k, $post_id ) : get_post_meta( $post_id, $k, true );
			if ( is_array( $v ) ) { $v = reset( $v ); }
			return trim( wp_strip_all_tags( (string) $v ) );
		};
		$maker = $get( 'marker' );
		$type  = $get( 'type' );
		$year  = $get( 'year' );
		$stock = $get( 'kanri_bango' );
		$name  = trim( preg_replace( '/\s+/u', ' ', $maker . ' ' . $type ) );
		$name  = trim( preg_replace( '/(^|\s)(不明|-|－|なし)(?=\s|$)/u', ' ', $name ) ); // グレード「不明」などは外す
		$name  = trim( preg_replace( '/\s+/u', ' ', $name ) );
		if ( '' === $name ) {
			// 項目が無い時はタイトルから（「信用回復ローン」と末尾の管理番号を外す）
			$name = trim( preg_replace( '/^信用回復ローン\s*/u', '', wp_strip_all_tags( get_the_title( $post_id ) ) ) );
			$name = mb_substr( $name, 0, 60 );
		} elseif ( '' !== $year ) {
			$name .= ' ' . ( preg_match( '/年/u', $year ) ? $year : $year . '年' );
		}
		if ( '' !== $stock && false === mb_strpos( $name, $stock ) ) { $name .= '（' . $stock . '）'; }
		return $name;
	}
}

if ( ! function_exists( 'carmelx_carchat_footer' ) ) {
	function carmelx_carchat_footer() {
		if ( is_admin() || ! is_singular( 'portfolio' ) ) { return; }
		$post_id = get_queried_object_id();
		if ( ! $post_id ) { return; }
		$msg = 'この車について相談したいです：' . carmelx_carchat_label( $post_id );
		$msg = (string) apply_filters( 'carmelx_carchat_message', $msg, $post_id );
		?>
<style id="carmelx-carchat-css">
a.carmelx-carchat{background-color:var(--carmelx-carchat,#009de0)!important;border-color:var(--carmelx-carchat,#009de0)!important;color:#fff!important;}
a.carmelx-carchat:hover{filter:brightness(1.08);}
</style>
<script id="carmelx-carchat-js">
(function () {
	var MSG = <?php echo wp_json_encode( $msg ); ?>;
	// 車両ページの中の LINE ボタン（ヘッダー・フッター・スマホメニュー・チャット内は対象外）
	var SEL = [
		'a.carmel-lg__btn--line',          // ローン概算の「LINE で相談」
		'a.carmel-cta-bar__line',          // 画面下の固定バー
		'.cx-shop__btns a.line',           // 販売店の「LINEで相談」
		'a.carmel-common-cta__btn--line',  // 下部の「LINEで無料相談する」
		'.vcex-button[href*="lin.ee"]',    // 本文の「LINEで在庫確認はこちら」
		'.vcex-button[href*="line.me"]'
	].join(',');
	function q(s) { return document.querySelector(s); }
	function chatReady() { return !!(q('#carmel-cb-launcher') && q('#carmel-cb-window') && q('#carmel-cb-input')); }
	function relabel(a) {
		var walker = document.createTreeWalker(a, NodeFilter.SHOW_TEXT, null, false), n, hit = false;
		while ((n = walker.nextNode())) {
			if (/LINE\s*/.test(n.nodeValue)) { n.nodeValue = n.nodeValue.replace(/LINE\s*/g, 'チャット'); hit = true; }
		}
		if (!hit) { a.appendChild(document.createTextNode('チャットで相談')); }
		var imgs = a.querySelectorAll('img[alt*="LINE"],img[src*="line"]');
		for (var i = 0; i < imgs.length; i++) { imgs[i].style.display = 'none'; }
		a.setAttribute('aria-label', 'チャットで相談');
	}
	function fill() {
		var input = q('#carmel-cb-input');
		if (!input || input.disabled) { return false; }
		if (input.value && input.value.indexOf(MSG) !== -1) { input.focus(); return true; }
		input.value = MSG;
		try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
		input.focus();
		try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
		return true;
	}
	function openChat() {
		var win = q('#carmel-cb-window'), launcher = q('#carmel-cb-launcher');
		if (win.hidden) { launcher.click(); }
		// まだ会話を始めていない時は、開始の画面（イントロ）を押して会話を始める
		setTimeout(function () {
			var w = q('#carmel-cb-window');
			if (w && !w.classList.contains('is-started')) {
				var intro = q('[data-role="intro"]');
				if (intro) { intro.click(); }
			}
			// あいさつ文が出た後に入力欄へ（少し待つ）。入力欄が使えない時は数回やり直す
			var tries = 0;
			(function wait() {
				if (fill() || ++tries > 8) { return; }
				setTimeout(wait, 250);
			})();
		}, 150);
	}
	function init() {
		if (!chatReady()) { return; } // チャットが無い時は LINE のまま
		var color = '';
		try { color = (window.CARMEL_CB && window.CARMEL_CB.primaryColor) || ''; } catch (e) {}
		if (color) { document.documentElement.style.setProperty('--carmelx-carchat', color); }
		var links = document.querySelectorAll(SEL);
		for (var i = 0; i < links.length; i++) {
			var a = links[i];
			if (a.classList.contains('carmelx-carchat') || a.closest('#carmel-cb-root')) { continue; }
			a.classList.add('carmelx-carchat');
			a.setAttribute('data-line-href', a.getAttribute('href') || '');
			a.setAttribute('href', '#chat');
			a.removeAttribute('target');
			relabel(a);
			a.addEventListener('click', function (e) { e.preventDefault(); openChat(); });
		}
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
		<?php
	}
	// チャットボットの部品（wp_footer）より後に動かす
	add_action( 'wp_footer', 'carmelx_carchat_footer', 999 );
}
