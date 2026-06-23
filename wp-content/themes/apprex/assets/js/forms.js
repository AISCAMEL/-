/**
 * APPREX native forms (contact / document request / trial).
 * Submits to the WP REST endpoint and renders an inline confirmation.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('[data-apprex-form]');
		if (!forms.length) {
			return;
		}
		var cfg = window.APPREX_REST || {};

		forms.forEach(function (form) {
			var result = form.querySelector('.apprex-form__result');
			var btn = form.querySelector('button[type="submit"]');

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				if (!form.checkValidity()) {
					form.reportValidity();
					return;
				}
				var original = btn.textContent;
				btn.disabled = true;
				btn.textContent = '送信中…';

				var fd = new FormData(form);
				var payload = {
					type: form.getAttribute('data-type') || 'contact',
					name: fd.get('name'),
					company: fd.get('company'),
					email: fd.get('email'),
					phone: fd.get('phone'),
					message: fd.get('message'),
					meeting_at: fd.get('meeting_at') || '',
					source_url: location.href
				};

				fetch(cfg.root + 'inquiry', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify(payload)
				})
					.then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
					.then(function (res) {
						if (res.ok && res.body && res.body.ok) {
							// SNS広告のコンバージョン計測用イベント（sns-ads.js が購読）。
							try {
								document.dispatchEvent(new CustomEvent('apprex:lead', {
									detail: (res.body.lead || { type: payload.type })
								}));
							} catch (err) {}
							form.querySelectorAll('input, textarea, button').forEach(function (el) { el.style.display = 'none'; });
							var html = '<div class="apprex-form__success"><h4>✅ 送信が完了しました</h4><p>' +
								(res.body.message || '') + '</p>';
							if (res.body.download) {
								html += '<p><a class="btn btn--primary" href="' + res.body.download + '" target="_blank" rel="noopener">資料をダウンロード</a></p>';
							}
							if (res.body.meeting) {
								html += '<p><a class="btn btn--primary" href="' + res.body.meeting + '" target="_blank" rel="noopener">ミーティングを予約する（Web面談）</a></p>';
							}
							if (res.body.line) {
								html += '<p><a class="line-cta" href="' + res.body.line + '" target="_blank" rel="noopener">LINEで相談する</a></p>';
							}
							html += '</div>';
							result.innerHTML = html;
							result.hidden = false;
							result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
						} else {
							alert((res.body && res.body.message) ? res.body.message : '送信に失敗しました。時間をおいて再度お試しください。');
							btn.disabled = false;
							btn.textContent = original;
						}
					})
					.catch(function () {
						alert('通信エラーが発生しました。時間をおいて再度お試しください。');
						btn.disabled = false;
						btn.textContent = original;
					});
			});
		});
	});
})();
