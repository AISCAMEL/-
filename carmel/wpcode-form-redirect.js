/**
 * カーメル：問い合わせ導線のGoogleフォームを共通審査フォームに差し替え（全ページ）
 * ---------------------------------------------------------------------------
 * 目的 : サイト内のあらゆるリンクのうち、Googleフォーム
 *        （forms.gle/... または docs.google.com/forms/...）を指しているものを、
 *        共通の審査フォーム https://carmelonline.jp/contact-form に書き換える。
 *
 * 導入 : WPCode →「＋スニペットを追加」→「コードを追加（JavaScript Snippet）」→
 *        このコードを貼り付け → 挿入方法「自動挿入」／場所「サイト全体のフッター」→
 *        有効化。（フッター配置なので優先順位10の停止問題の影響を受けません）
 * ---------------------------------------------------------------------------
 */
(function () {
	'use strict';

	var FORM_URL = 'https://carmelonline.jp/contact-form';

	// Googleフォーム判定（forms.gle / docs.google.com/forms のどちらか）
	function isGoogleForm(href) {
		if (!href) { return false; }
		return href.indexOf('forms.gle') !== -1 ||
			href.indexOf('docs.google.com/forms') !== -1;
	}

	function rewrite() {
		var links = document.querySelectorAll('a[href]');
		for (var i = 0; i < links.length; i++) {
			var a = links[i];
			if (a.getAttribute('data-carmel-form-fixed')) { continue; }
			var h = a.getAttribute('href') || '';
			if (isGoogleForm(h)) {
				a.setAttribute('href', FORM_URL);
				a.setAttribute('data-carmel-form-fixed', '1');
				// 別タブ指定を外して同一サイト内遷移に統一（不要なら次の2行を削除可）
				a.removeAttribute('target');
				a.removeAttribute('rel');
			}
		}
	}

	function run() {
		rewrite();
		// 後から差し込まれるボタン（チャット枠・動的生成）にも対応
		setTimeout(rewrite, 300);
		setTimeout(rewrite, 1200);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}

	// 動的にリンクが追加されるページにも追従（保険）
	if (window.MutationObserver) {
		var mo = new MutationObserver(function () { rewrite(); });
		try {
			mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
		} catch (e) {}
	}
})();
