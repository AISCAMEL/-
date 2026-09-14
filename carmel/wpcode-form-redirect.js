/**
 * カーメル：問い合わせ／審査の導線URLを共通ページに差し替え（全ページ）
 * ---------------------------------------------------------------------------
 * 目的 : サイト内のリンクのうち、
 *   (1) 審査・申込 系            → https://carmelonline.jp/shinsa-2
 *   (2) お問い合わせ・相談 系（Googleフォーム等） → https://carmelonline.jp/contact-form
 *   に自動で振り分けて差し替える。
 *
 * 対象になるリンク（現状Googleや旧URLに向いているもの）：
 *   - Googleフォーム : forms.gle/... または docs.google.com/forms/...
 *   - 旧審査ページ   : ?p=7348
 *   振り分けは「ボタンの文言」で判定（審査/申込 を含む→shinsa-2、その他→contact-form）。
 *   旧審査ページ(?p=7348)は常に shinsa-2。
 *
 * 導入 : WPCode → JavaScript Snippet → 自動挿入 ／ 場所「サイト全体のフッター」→ 有効化。
 * ---------------------------------------------------------------------------
 */
(function () {
	'use strict';

	var SHINSA_URL  = 'https://carmelonline.jp/shinsa-2';   // 審査・申込
	var CONTACT_URL = 'https://carmelonline.jp/contact-form'; // お問い合わせ・相談

	// 審査/申込 を示す文言
	var SHINSA_WORDS = /(審査|申込|申し込|仮審査)/; // 審査 / 申込 / 申し込 / 仮審査

	function isGoogleForm(href) {
		return href.indexOf('forms.gle') !== -1 || href.indexOf('docs.google.com/forms') !== -1;
	}
	function isOldShinsa(href) {
		return href.indexOf('?p=7348') !== -1 || href.indexOf('&p=7348') !== -1;
	}

	// 差し替え先を決める（対象外なら null）
	function decide(a) {
		var href = a.getAttribute('href') || '';
		if ( ! href ) { return null; }

		// 旧審査ページは常に審査フォームへ
		if ( isOldShinsa(href) ) { return SHINSA_URL; }

		// Googleフォームは文言で振り分け
		if ( isGoogleForm(href) ) {
			var t = ( (a.textContent || '') + ' ' +
			          (a.getAttribute('aria-label') || '') + ' ' +
			          (a.getAttribute('title') || '') );
			return SHINSA_WORDS.test(t) ? SHINSA_URL : CONTACT_URL;
		}

		return null; // それ以外は触らない
	}

	function rewrite() {
		var links = document.querySelectorAll('a[href]');
		for (var i = 0; i < links.length; i++) {
			var a = links[i];
			if (a.getAttribute('data-carmel-cta-fixed')) { continue; }
			var to = decide(a);
			if (!to) { continue; }
			a.setAttribute('href', to);
			a.setAttribute('data-carmel-cta-fixed', '1');
			a.removeAttribute('target'); // 同一サイト内遷移に統一
			a.removeAttribute('rel');
		}
	}

	function run() {
		rewrite();
		setTimeout(rewrite, 300);
		setTimeout(rewrite, 1200);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}

	// 後から差し込まれるボタン（チャット枠・動的生成）にも追従
	if (window.MutationObserver) {
		var mo = new MutationObserver(function () { rewrite(); });
		try {
			mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
		} catch (e) {}
	}
})();
