/**
 * リニューアル版ページの補助スクリプト。
 * 送信そのものは forms.js（[data-apprex-form]）が担当し、ここでは見た目の切り替えだけを行う。
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		// 資料請求フォーム：「資料がほしい／見積り相談したい」で送信種別とボタン文言を切り替える。
		document.querySelectorAll('.rn form[data-apprex-form]').forEach(function (form) {
			var btn = form.querySelector('button[type="submit"]');
			form.querySelectorAll('input[name="kind"]').forEach(function (r) {
				r.addEventListener('change', function () {
					if (!r.checked) {
						return;
					}
					form.setAttribute('data-type', r.value);
					var label = btn && btn.getAttribute('data-label-' + r.value);
					if (label) {
						btn.textContent = label;
					}
				});
			});
		});

		// 複数の選択欄（data-label）を、送信用の message 欄にまとめる。
		// （forms.js が先に送信処理を行うため、submit ではなく入力のたびに更新する）
		document.querySelectorAll('.rn form[data-compose]').forEach(function (form) {
			var msg = form.querySelector('[name="message"]');
			var head = msg ? msg.value : '';
			var compose = function () {
				var lines = [head];
				form.querySelectorAll('[data-label]').forEach(function (el) {
					if (el.value) {
						lines.push(el.getAttribute('data-label') + '：' + el.value);
					}
				});
				msg.value = lines.join('\n');
			};
			if (msg) {
				form.addEventListener('change', compose);
				compose();
			}
		});

		// 送信完了後は、forms.js が隠さない選択欄・説明も隠す。
		// （apprex:lead は完了表示の直前に発火するため、表示後に判定する）
		document.addEventListener('apprex:lead', function () {
			setTimeout(function () {
				document.querySelectorAll('.rn form[data-apprex-form]').forEach(function (form) {
					var result = form.querySelector('.apprex-form__result');
					if (result && !result.hidden) {
						form.querySelectorAll('select, .seg, .fnote').forEach(function (el) { el.style.display = 'none'; });
					}
				});
			}, 0);
		});
	});
})();
