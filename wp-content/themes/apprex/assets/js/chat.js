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
		var muteBtn = document.getElementById('apprex-chat-mute');
		var log = document.getElementById('apprex-chat-log');
		var input = document.getElementById('apprex-chat-input');
		var quick = document.getElementById('apprex-chat-quick');
		var opBtn = document.getElementById('apprex-chat-operator');
		var hint = document.getElementById('apprex-chat-hint');
		var hintX = document.getElementById('apprex-chat-hint-x');
		var mailForm = document.getElementById('apprex-chat-mailform');
		var mailOpenBtn = document.getElementById('apprex-chat-mail');
		var mailCancel = document.getElementById('apprex-chat-mail-cancel');
		var mailResult = document.getElementById('apprex-chat-mail-result');

		// 受信音（Web Audio：音声ファイル不要）。ミュートは localStorage に保存。
		var soundOn = true;
		try { soundOn = (localStorage.getItem('apprexChatMute') !== '1'); } catch (e) {}
		var audioCtx = null;
		function ensureAudio() {
			try {
				var AC = window.AudioContext || window.webkitAudioContext;
				if (!audioCtx && AC) { audioCtx = new AC(); }
				if (audioCtx && audioCtx.state === 'suspended') { audioCtx.resume(); }
			} catch (e) {}
		}
		function playDing() {
			if (!soundOn || !audioCtx) { return; }
			try {
				var t = audioCtx.currentTime;
				var o = audioCtx.createOscillator();
				var g = audioCtx.createGain();
				o.type = 'sine';
				o.frequency.setValueAtTime(880, t);
				o.frequency.exponentialRampToValueAtTime(1320, t + 0.09);
				g.gain.setValueAtTime(0.0001, t);
				g.gain.exponentialRampToValueAtTime(0.16, t + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, t + 0.34);
				o.connect(g); g.connect(audioCtx.destination);
				o.start(t); o.stop(t + 0.36);
			} catch (e) {}
		}
		function updateMuteIcon() { if (muteBtn) { muteBtn.textContent = soundOn ? '🔔' : '🔕'; } }
		updateMuteIcon();
		if (muteBtn) {
			muteBtn.addEventListener('click', function () {
				soundOn = !soundOn;
				try { localStorage.setItem('apprexChatMute', soundOn ? '0' : '1'); } catch (e) {}
				updateMuteIcon();
				if (soundOn) { ensureAudio(); playDing(); }
			});
		}
		var history = [];
		var greeted = false;
		var busy = false;
		var human = false;
		var lastUserText = '';
		var mailHintShown = false;
		var pollCursor = 0;
		var pollTimer = null;
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
			hideHint();
			ensureAudio(); // ユーザー操作の瞬間に音声を有効化（ブラウザ制約対策）。
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
			startPolling();
		}
		function closeWidget() {
			widget.classList.remove('is-open');
			widget.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			stopPolling();
		}

		toggle.addEventListener('click', function () {
			if (widget.classList.contains('is-open')) { closeWidget(); } else { openWidget(); }
		});
		if (closeBtn) { closeBtn.addEventListener('click', closeWidget); }

		function addMessage(role, text) {
			var el = document.createElement('div');
			el.className = 'apprex-msg apprex-msg--' + role;
			if (role === 'operator') {
				var tag = document.createElement('span');
				tag.className = 'apprex-msg__tag';
				tag.textContent = '担当者';
				el.appendChild(tag);
				el.appendChild(document.createTextNode(text));
			} else {
				el.textContent = text;
			}
			log.appendChild(el);
			log.scrollTop = log.scrollHeight;
			if (role !== 'user') { playDing(); } // 受信時のみ音を鳴らす。
			return el;
		}

		function startPolling() {
			if (pollTimer || !cfg.root || !cfg.opEnabled) { return; }
			pollTimer = setInterval(poll, 4000);
		}
		function stopPolling() {
			if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
		}
		function poll() {
			fetch(cfg.root + 'chat/poll?session=' + encodeURIComponent(sessionId) + '&after=' + pollCursor, {
				headers: { 'X-WP-Nonce': cfg.nonce }
			})
				.then(function (r) { return r.json(); })
				.then(function (b) {
					if (!b) { return; }
					if (typeof b.cursor === 'number') { pollCursor = b.cursor; }
					human = !!b.human;
					if (b.messages && b.messages.length) {
						setTyping(false);
						b.messages.forEach(function (m) {
							if (m.who === 'system') { addMessage('system', m.text); }
							else { addMessage('operator', m.text); }
						});
					}
				})
				.catch(function () { /* silent */ });
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
			lastUserText = text;
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
					if (res.body && res.body.human) {
						human = true;
						poll(); // 担当者の返信をすぐ取りに行く。
						return; // 有人対応中はAIの空応答を表示しない。
					}
					if (res.ok && res.body && res.body.reply) {
						addMessage('assistant', res.body.reply);
						history.push({ role: 'assistant', content: res.body.reply });
						renderSuggestions(res.body.suggestions);
						maybeMailHint(); // 解決しない場合のメール誘導を一度だけ提示。
					} else {
						var msg = (res.body && res.body.message) ? res.body.message
							: 'うまく応答できませんでした。お手数ですがメールでご相談ください。';
						addMessage('assistant', msg);
						openMail(); // 応答できない時はそのままメールフォームへ誘導。
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

		if (opBtn) {
			opBtn.addEventListener('click', function () {
				if (opBtn.disabled) { return; }
				opBtn.disabled = true;
				addMessage('system', '担当者におつなぎしています。少々お待ちください…');
				startPolling();
				fetch(cfg.root + 'chat/operator', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify({ session: sessionId })
				})
					.then(function (r) { return r.json(); })
					.then(function (b) {
						if (!b || !b.ok) {
							addMessage('system', 'ただいま担当者につなげませんでした。お手数ですがお問い合わせフォームをご利用ください。');
						}
					})
					.catch(function () {
						addMessage('system', '通信エラーが発生しました。少し時間をおいてお試しください。');
					})
					.finally(function () {
						setTimeout(function () { opBtn.disabled = false; }, 8000);
					});
			});
		}

		// --- あいさつ吹き出し（数秒後にふわっと表示。閉じたら記憶） -------------
		function hideHint() {
			if (!hint) { return; }
			hint.hidden = true;
			hint.classList.remove('is-in');
			try { sessionStorage.setItem('apprexHintDismissed', '1'); } catch (e) {}
		}
		if (hint) {
			var hintDismissed = false;
			try { hintDismissed = sessionStorage.getItem('apprexHintDismissed') === '1'; } catch (e) {}
			if (!hintDismissed) {
				setTimeout(function () {
					if (!widget.classList.contains('is-open')) {
						hint.hidden = false;
						// reflow → アニメーションを確実に発火
						void hint.offsetWidth;
						hint.classList.add('is-in');
					}
				}, 5000);
			}
			hint.addEventListener('click', function () { openWidget(); });
			if (hintX) {
				hintX.addEventListener('click', function (e) { e.stopPropagation(); hideHint(); });
			}
		}

		// --- 未解決時のメール誘導フォーム ----------------------------------------
		function openMail() {
			if (!mailForm) { return; }
			mailForm.hidden = false;
			if (quick) { quick.style.display = 'none'; }
			if (mailResult) { mailResult.hidden = true; mailResult.textContent = ''; }
			if (lastUserText) {
				var ta = mailForm.querySelector('[name="message"]');
				if (ta && !ta.value) { ta.value = lastUserText; }
			}
			mailForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			var nameEl = mailForm.querySelector('[name="name"]');
			if (nameEl) { setTimeout(function () { nameEl.focus(); }, 80); }
		}
		function closeMail() {
			if (!mailForm) { return; }
			mailForm.hidden = true;
			if (quick) { quick.style.display = ''; }
		}
		// AIが応答したら一度だけ「解決しない場合はメールで相談」を提示。
		function maybeMailHint() {
			if (mailHintShown || !mailForm) { return; }
			mailHintShown = true;
			var wrap = document.createElement('div');
			wrap.className = 'apprex-chat-resolve';
			var span = document.createElement('span');
			span.textContent = '解決しませんでしたか？';
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'apprex-chat-resolve__btn';
			btn.textContent = '✉ メールで相談';
			btn.addEventListener('click', openMail);
			wrap.appendChild(span);
			wrap.appendChild(btn);
			log.appendChild(wrap);
			log.scrollTop = log.scrollHeight;
		}
		if (mailOpenBtn) { mailOpenBtn.addEventListener('click', openMail); }
		if (mailCancel) { mailCancel.addEventListener('click', closeMail); }
		if (mailForm) {
			mailForm.addEventListener('submit', function (e) {
				e.preventDefault();
				if (!mailForm.checkValidity()) { mailForm.reportValidity(); return; }
				var btn = mailForm.querySelector('button[type="submit"]');
				var orig = btn.textContent;
				btn.disabled = true;
				btn.textContent = '送信中…';
				var fd = new FormData(mailForm);
				fetch(cfg.root + 'inquiry', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify({
						type: 'contact',
						name: fd.get('name'),
						email: fd.get('email'),
						message: fd.get('message'),
						source_url: location.href
					})
				})
					.then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
					.then(function (res) {
						if (res.ok && res.body && res.body.ok) {
							mailForm.reset();
							closeMail();
							addMessage('system', '✅ 送信しました。担当者よりメールでご返信いたします。');
						} else {
							if (mailResult) {
								mailResult.hidden = false;
								mailResult.textContent = (res.body && res.body.message) ? res.body.message : '送信に失敗しました。時間をおいて再度お試しください。';
							}
							btn.disabled = false;
							btn.textContent = orig;
						}
					})
					.catch(function () {
						if (mailResult) {
							mailResult.hidden = false;
							mailResult.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
						}
						btn.disabled = false;
						btn.textContent = orig;
					});
			});
		}
	});
})();
