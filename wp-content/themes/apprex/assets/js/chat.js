/**
 * APPREX in-site AI chat widget (OpenRouter-backed via WP REST proxy).
 * No dependencies. Falls back gracefully on errors.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var widget = document.getElementById('apprex-chat-window');
		var form = document.getElementById('apprex-chat-form');
		if (!widget || !form) {
			return; // Native widget not on this page (or Zapier fallback in use).
		}

		var toggle = document.querySelector('.apprex-chat-toggle');
		var closeBtn = widget.querySelector('.apprex-chat__close');
		var log = document.getElementById('apprex-chat-log');
		var input = document.getElementById('apprex-chat-input');
		var quick = document.getElementById('apprex-chat-quick');
		var history = [];
		var greeted = false;
		var busy = false;
		var sessionId = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);

		var cfg = window.APPREX_REST || {};

		function renderSuggestions(list) {
			if (!list || !list.length) { return; }
			var wrap = document.createElement('div');
			wrap.className = 'apprex-chat-suggest';
			list.forEach(function (s) {
				var a = document.createElement('a');
				a.className = 'apprex-chat-suggest__btn';
				a.href = s.url;
				a.target = '_blank';
				a.rel = 'noopener';
				a.textContent = s.label;
				wrap.appendChild(a);
			});
			log.appendChild(wrap);
			log.scrollTop = log.scrollHeight;
		}

		function openWidget() {
			widget.hidden = false;
			widget.classList.add('is-open');
			toggle.classList.add('has-seen');
			toggle.setAttribute('aria-expanded', 'true');
			if (!greeted) {
				greeted = true;
				var member = cfg.member || {};
				if (member.loggedIn) {
					addMessage('assistant', (member.name || 'お客様') + ' 様、いつもありがとうございます🙂 ご契約・お支払い・更新日のご確認や、マイページのご案内ができます。お気軽にどうぞ。');
					renderSuggestions([{ label: 'マイページ', url: member.mypageUrl }]);
				} else {
					addMessage('assistant', 'こんにちは！APPREX サポートです🙂 ノーコードアプリ開発・料金・お見積りなど、お気軽にご質問ください。');
				}
			}
			setTimeout(function () { input.focus(); }, 50);
		}
		function closeWidget() {
			widget.classList.remove('is-open');
			widget.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
		}

		toggle.addEventListener('click', function () {
			if (widget.classList.contains('is-open')) { closeWidget(); } else { openWidget(); }
		});
		if (closeBtn) { closeBtn.addEventListener('click', closeWidget); }

		function addMessage(role, text) {
			var el = document.createElement('div');
			el.className = 'apprex-msg apprex-msg--' + role;
			el.textContent = text;
			log.appendChild(el);
			log.scrollTop = log.scrollHeight;
			return el;
		}

		function setTyping(on) {
			var existing = log.querySelector('.apprex-msg--typing');
			if (on && !existing) {
				var el = document.createElement('div');
				el.className = 'apprex-msg apprex-msg--assistant apprex-msg--typing';
				el.innerHTML = '<span class="apprex-typing"><span></span><span></span><span></span></span>';
				log.appendChild(el);
				log.scrollTop = log.scrollHeight;
			} else if (!on && existing) {
				existing.remove();
			}
		}

		function send(text) {
			if (busy || !text) { return; }
			busy = true;
			addMessage('user', text);
			history.push({ role: 'user', content: text });
			input.value = '';
			setTyping(true);

			fetch(cfg.root + 'chat', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ messages: history, session: sessionId })
			})
				.then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
				.then(function (res) {
					setTyping(false);
					if (res.ok && res.body && res.body.reply) {
						addMessage('assistant', res.body.reply);
						history.push({ role: 'assistant', content: res.body.reply });
						renderSuggestions(res.body.suggestions);
					} else {
						var msg = (res.body && res.body.message) ? res.body.message
							: 'うまく応答できませんでした。お手数ですがお問い合わせフォームをご利用ください。';
						addMessage('assistant', msg);
					}
				})
				.catch(function () {
					setTyping(false);
					addMessage('assistant', '通信エラーが発生しました。少し時間をおいてお試しください。');
				})
				.finally(function () { busy = false; });
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			send(input.value.trim());
		});

		if (quick) {
			quick.addEventListener('click', function (e) {
				var btn = e.target.closest('button[data-q]');
				if (btn) {
					openWidget();
					send(btn.getAttribute('data-q'));
				}
			});
		}
	});
})();
