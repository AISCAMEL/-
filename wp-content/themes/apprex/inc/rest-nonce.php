<?php
/**
 * サイトの REST の合言葉（nonce）が古くなっても止まらないようにする。
 *
 * ページが保存（キャッシュ）されたまま時間がたつと、ページに入っている合言葉の期限が切れて、
 * チャット・お問い合わせ・予約などが「Cookie チェック失敗」「不正なリクエストです」で止まる。
 * → 合言葉が古くて断られたら、新しい合言葉をもらって、もう1回だけ送り直す（全部の画面に効く）。
 * → ページを開いてから長くたっているときは、先に新しい合言葉をもらっておく。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 新しい合言葉を返す（admin-ajax は保存されないので、いつも今の合言葉になる）。
$apprex_rest_nonce_cb = function () {
	nocache_headers();
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
};
add_action( 'wp_ajax_apprex_rest_nonce', $apprex_rest_nonce_cb );
add_action( 'wp_ajax_nopriv_apprex_rest_nonce', $apprex_rest_nonce_cb );

add_action(
	'wp_head',
	function () {
		$ajax = admin_url( 'admin-ajax.php?action=apprex_rest_nonce' );
		?>
<script id="apprex-rest-nonce">
(function(){
	if (!window.fetch || window.__apxNonceFix) { return; }
	window.__apxNonceFix = 1;
	var AJAX = <?php echo wp_json_encode( $ajax ); ?>, BORN = <?php echo (int) time(); ?> * 1000, fresh = null, asking = null;
	var orig = window.fetch.bind(window);
	function getFresh() {
		if (!asking) {
			asking = orig(AJAX, { credentials: 'same-origin', cache: 'no-store' })
				.then(function (r) { return r.json(); })
				.then(function (j) { fresh = j && j.data && j.data.nonce ? j.data.nonce : fresh; asking = null; return fresh; })
				.catch(function () { asking = null; return fresh; });
		}
		return asking;
	}
	// ページを開いてから10時間以上たっていたら（保存されたページ）、先にもらっておく。
	if (Date.now() - BORN > 10 * 3600 * 1000) { getFresh(); }
	function nonceKey(h) {
		if (!h || typeof h !== 'object' || h instanceof Headers) { return null; }
		for (var k in h) { if (k.toLowerCase() === 'x-wp-nonce') { return k; } }
		return null;
	}
	window.fetch = function (url, opts) {
		var key = opts ? nonceKey(opts.headers) : null;
		if (!key) { return orig(url, opts); }
		if (fresh) { opts.headers[key] = fresh; }
		return orig(url, opts).then(function (res) {
			if (res.status !== 403 && res.status !== 401) { return res; }
			return res.clone().json().then(function (b) {
				var code = b && b.code ? String(b.code) : '';
				if (!/nonce|forbidden|cookie/i.test(code + ' ' + (b && b.message || ''))) { return res; }
				return getFresh().then(function (n) {
					if (!n || n === opts.headers[key]) { return res; }
					opts.headers[key] = n;
					return orig(url, opts);
				});
			}, function () { return res; });
		});
	};
})();
</script>
		<?php
	},
	1
);
