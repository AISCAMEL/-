(function () {
	"use strict";
	if (!window.CARMEL_CB) return;

	var cfg = window.CARMEL_CB;
	var history = [];
	var sessionId = "ccb-" + Date.now() + "-" + Math.random().toString(36).slice(2, 8);

	var launcher, win, msgBox, input, sendBtn, closeBtn, opened = false, greeted = false;
	var interacted = false, autoTimer = null, chatStarted = false;

	function seenThisSession() { try { return window.sessionStorage.getItem("ccb_seen") === "1"; } catch (e) { return false; } }
	function markSeen() { try { window.sessionStorage.setItem("ccb_seen", "1"); } catch (e) {} }

	// 最初に提示するきっかけ候補（タップで会話開始）
	var STARTERS = ["審査が不安です", "頭金がなくても大丈夫？", "他社で断られたけど…", "どんな車があるか見たい"];

	// 有人ライブ（Slack双方向）の状態
	var ho = { live: false, sid: null, poll: null, giveup: null, busy: null, hardstop: null, nudge: null, connected: false };
	// 会話ミラー＆常時ライブ割り込み（Slack）／無操作の再打診
	var convo = { poll: null, engaged: false, started: false };
	var idle = { timer: null, count: 0 };
	// 開始時のお客様情報（名前・メール）確認
	var visitor = { name: "", email: "", done: false };

	document.addEventListener("DOMContentLoaded", init);
	if (document.readyState !== "loading") init();

	function init() {
		launcher = document.getElementById("carmel-cb-launcher");
		win = document.getElementById("carmel-cb-window");
		msgBox = document.getElementById("carmel-cb-messages");
		input = document.getElementById("carmel-cb-input");
		sendBtn = document.getElementById("carmel-cb-send");
		closeBtn = document.getElementById("carmel-cb-close");
		if (!launcher || win.dataset.ready) return;
		win.dataset.ready = "1";

		launcher.addEventListener("click", userToggle);
		closeBtn.addEventListener("click", userToggle);
		sendBtn.addEventListener("click", send);
		// 日本語入力(IME)の変換確定Enterでは送信しない（e.isComposing / keyCode 229 を除外）
		input.addEventListener("keydown", function (e) {
			if (e.key === "Enter" && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
				e.preventDefault();
				send();
			}
		});
		input.addEventListener("input", autoGrow);

		// 添付ファイル（担当者ライブ中のみ表示）
		var fileInput = document.getElementById("carmel-cb-file");
		if (fileInput) fileInput.addEventListener("change", onFilePicked);

		var hoBtn = document.getElementById("carmel-cb-handoff");
		if (hoBtn) hoBtn.addEventListener("click", function () { if (opened) openHandoff(); else { userToggle(); openHandoff(); } });

		// イントロ（女性大きく表示）：タップ/「相談をはじめる」で会話開始、×で閉じる
		var intro = document.querySelector('[data-role="intro"]');
		if (intro) {
			intro.addEventListener("click", function () { startChat(); });
			var ic = intro.querySelector('[data-role="intro-close"]');
			if (ic) ic.addEventListener("click", function (e) { e.stopPropagation(); userToggle(); });
		}

		// 直リンク/埋め込み版：ランチャーもイントロ動画も出さず、すぐにチャット画面を表示。
		if (cfg.standalone) {
			opened = true;
			win.hidden = false;
			var sroot = document.getElementById("carmel-cb-root");
			if (sroot) sroot.classList.add("is-open");
			launcher.style.display = "none";
			startChat();
			return;
		}

		// 自動オープンは廃止：右下アイコンをタップした時だけ開く（タップのみ仕様）
	}

	// ユーザーが自分で開閉したとき：自動オープンを止め、以後セッション中は自動で開かない
	function userToggle() {
		interacted = true;
		if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
		markSeen();
		toggle();
	}

	function toggle() {
		opened = !opened;
		win.hidden = !opened;
		var root = document.getElementById("carmel-cb-root");
		if (root) root.classList.toggle("is-open", opened); // 開いたら吹き出しを隠す
		launcher.style.display = opened ? "none" : "flex";
		// 開いた直後はイントロ（女性大きく）。既に会話開始済みなら直接チャット表示。
		win.classList.toggle("is-started", opened && chatStarted);
		if (opened && chatStarted) setTimeout(function () { input.focus(); }, 50);
	}

	// 会話開始：女性を小さくして（ヘッダーへ）チャットを始める
	function startChat(topic) {
		chatStarted = true;
		win.classList.add("is-started");
		if (!greeted) {
			greeted = true;
			addBubble("bot", cfg.welcome);
			// お名前・メール確認モード：先に連絡先を伺い、その後に用件へ進む
			if (cfg.intakeOn && !visitor.done) {
				showVisitorForm();
				setTimeout(function () { focusInput(); }, 100);
				return;
			}
			greetGuide();
		}
		if (topic) sendText(topic);
		setTimeout(function () { focusInput(); }, 100);
	}

	function focusInput() { try { if (!input.disabled) input.focus(); } catch (e) {} }

	// あいさつ後のガイド（STARTERS＋1秒後の案内）— お名前確認が無効／完了後に使用
	function greetGuide(intro) {
		renderChoices(STARTERS);
		setTimeout(function () {
			if (ho.live || convo.engaged) { return; }
			addBubble("bot", intro || "本日はどのようなご相談ですか？下のメッセージ入力にて、その場でお答えします😊\n\nご希望の回答が得られない場合は、下の「担当者に相談」ボタンからお進みください。オペレーターが対応いたします。");
			focusInput();
		}, intro ? 200 : 1000);
	}

	// 開始時：お名前・メールを確認するフォーム（入力するまで会話は始めない）
	function showVisitorForm() {
		clearChoices();
		addBubble("bot", cfg.intakeMsg || "まずお名前とメールアドレスをご入力ください😊");
		// 会話入力欄は一旦ロック（先に連絡先を入れてもらう）
		input.disabled = true; sendBtn.disabled = true;
		input.placeholder = "先にお名前・メールをご入力ください";

		var form = document.createElement("div");
		form.className = "ccb-msg bot";
		form.innerHTML =
			'<div class="ccb-lead" data-type="intake">' +
			'<div class="ccb-lead-title">お客様情報</div>' +
			'<div class="ccb-lead-fields">' +
				'<input class="ccb-li" data-k="name" placeholder="お名前（必須）" autocomplete="name">' +
				'<input class="ccb-li" data-k="email" inputmode="email" placeholder="メールアドレス（必須）" autocomplete="email">' +
				'<button type="button" class="ccb-lead-next">この内容ではじめる</button>' +
				'<div class="ccb-lead-err" role="alert"></div>' +
			'</div>' +
			'</div>';
		msgBox.appendChild(form);
		msgBox.scrollTop = msgBox.scrollHeight;

		var nextBtn = form.querySelector(".ccb-lead-next");
		var err = form.querySelector(".ccb-lead-err");
		var get = function (k) { var el = form.querySelector('[data-k="' + k + '"]'); return el ? el.value.trim() : ""; };
		var fail = function (m) { err.textContent = m; err.style.display = "block"; };
		var emailOk = function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); };

		nextBtn.addEventListener("click", function () {
			var name = get("name"), email = get("email");
			if (!name) { return fail("お名前をご入力ください。"); }
			if (!email || !emailOk(email)) { return fail("メールアドレスを正しくご入力ください。"); }
			err.style.display = "none";
			nextBtn.disabled = true; nextBtn.textContent = "送信中…";

			visitor.name = name; visitor.email = email; visitor.done = true;
			// サーバーへ登録（Slack/通知先へ「誰から来たか」を通知）
			if (cfg.visitorUrl) {
				fetch(cfg.visitorUrl, {
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ session_id: sessionId, name: name, email: email, page: location.href })
				}).catch(function () {});
			}

			form.remove();
			// 入力欄をアンロックして会話へ
			input.disabled = false; sendBtn.disabled = false;
			input.placeholder = "メッセージを入力…";
			var after = (cfg.intakeAfterMsg || "ありがとうございます、{name}様。本日はどのようなご用件でしょうか？下のメッセージ入力にて、その場でお答えします😊").replace(/\{name\}/g, name);
			greetGuide(after + "\n\nご希望の回答が得られない場合は、下の「担当者に相談」ボタンからお進みください。オペレーターが対応いたします。");
		});

		// Enterキーでも進めるように
		form.addEventListener("keydown", function (e) {
			if (e.key === "Enter" && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); nextBtn.click(); }
		});
	}

	// 入力欄の高さはCSSで固定（テーマ干渉での巨大化を防止）。autoGrowは無効化。
	function autoGrow() {}

	function addBubble(role, text) {
		var wrap = document.createElement("div");
		wrap.className = "ccb-msg " + (role === "user" ? "user" : "bot");
		var b = document.createElement("div");
		b.className = "ccb-bubble";
		b.innerHTML = linkify(escapeHtml(text));
		wrap.appendChild(b);
		msgBox.appendChild(wrap);
		msgBox.scrollTop = msgBox.scrollHeight;
		return b;
	}

	function showTyping() {
		var wrap = document.createElement("div");
		wrap.className = "ccb-msg bot";
		wrap.id = "ccb-typing-row";
		wrap.innerHTML = '<div class="ccb-bubble"><span class="ccb-typing"><span></span><span></span><span></span></span></div>';
		msgBox.appendChild(wrap);
		msgBox.scrollTop = msgBox.scrollHeight;
	}
	function hideTyping() {
		var t = document.getElementById("ccb-typing-row");
		if (t) t.remove();
	}

	function send() {
		sendText(input.value);
	}

	function sendText(raw) {
		var text = (raw || "").trim();
		if (!text || sendBtn.disabled) return;
		// お名前・メール未確認のうちは会話を始めない（intake優先）
		if (cfg.intakeOn && !visitor.done && !ho.live && !convo.engaged) { return; }
		input.value = "";
		autoGrow();
		clearIdle(); // 送信したので「無操作の再打診」タイマーを止める

		// 担当者ライブ中（旧handoff）：Slackへ中継
		if (ho.live) {
			clearChoices();
			addBubble("user", text);
			fetch(cfg.handoffSendUrl, {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ session_id: ho.sid, text: text })
			}).catch(function () {});
			return;
		}

		// 担当者が会話スレッドで対応中：AIではなくスレッドへ中継
		if (convo.engaged) {
			clearChoices();
			addBubble("user", text);
			if (cfg.convoSendUrl) {
				fetch(cfg.convoSendUrl, {
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ session_id: sessionId, text: text })
				}).catch(function () {});
			}
			return;
		}

		clearChoices(); // 前回の候補チップを片付ける
		addBubble("user", text);
		history.push({ role: "user", content: text });
		sendBtn.disabled = true;
		showTyping();

		fetch(cfg.endpoint, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ messages: history, session_id: sessionId, page: location.href })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				hideTyping();
				var reply = (data && data.reply) ? data.reply : "すみません、もう一度お願いします。";
				if (data && data.session_id) sessionId = data.session_id;
				addBubble("bot", reply);
				if (data && data.cars) renderCars(data.cars);            // 実在庫カード
				// 会話の流れでAIが選んだ“入口”だけを出す（審査/問い合わせ/在庫/担当者/LINE）
				if (data && data.action) renderCTA(data.action);
				else if (data && data.cta) renderCTA("");
				if (data && data.suggestions) renderChoices(data.suggestions); // 候補チップ
				history.push({ role: "assistant", content: reply });
				startConvoPoll();   // 会話をSlackへミラー中：担当者の割り込みを常時監視
				scheduleIdle();     // 一定時間無操作なら再打診
			})
			.catch(function () {
				hideTyping();
				addBubble("bot", "通信エラーが発生しました。お手数ですがLINEでご相談ください → " + cfg.lineUrl);
			})
			.finally(function () {
				sendBtn.disabled = false;
				input.focus();
			});
	}

	// 実在庫のカード（画像・価格・月々・詳細リンク）
	function renderCars(cars) {
		if (!cars || !cars.length) return;
		var row = document.createElement("div");
		row.className = "ccb-msg bot";
		var list = document.createElement("div");
		list.className = "ccb-cars";
		cars.forEach(function (c) {
			var a = document.createElement("a");
			a.className = "ccb-car";
			a.href = c.url; a.target = "_blank"; a.rel = "noopener";
			var meta = [c.year, c.mileage].filter(Boolean).join(" / ");
			var price = c.monthly ? ("月々 " + c.monthly + "〜 / 本体 " + c.price) : c.price;
			a.innerHTML =
				(c.thumb ? '<span class="ccb-car-img" style="background-image:url(\'' + c.thumb.replace(/'/g, "%27") + '\')"></span>' : '<span class="ccb-car-img ccb-car-noimg">🚗</span>') +
				'<span class="ccb-car-body">' +
					'<span class="ccb-car-title"></span>' +
					'<span class="ccb-car-meta"></span>' +
					'<span class="ccb-car-price"></span>' +
					'<span class="ccb-car-link">詳細を見る →</span>' +
				'</span>';
			a.querySelector(".ccb-car-title").textContent = c.title;
			a.querySelector(".ccb-car-meta").textContent = meta;
			a.querySelector(".ccb-car-price").textContent = price;
			list.appendChild(a);
		});
		row.appendChild(list);
		msgBox.appendChild(row);
		msgBox.scrollTop = msgBox.scrollHeight;
	}

	// タップで会話を進める候補チップ
	function clearChoices() {
		var old = msgBox.querySelectorAll(".ccb-choices");
		for (var i = 0; i < old.length; i++) { old[i].remove(); }
	}
	function renderChoices(list) {
		if (!list || !list.length) return;
		var row = document.createElement("div");
		row.className = "ccb-choices";
		list.forEach(function (s) {
			var b = document.createElement("button");
			b.type = "button";
			b.className = "ccb-choice";
			b.textContent = s;
			b.addEventListener("click", function () { sendText(s); });
			row.appendChild(b);
		});
		msgBox.appendChild(row);
		msgBox.scrollTop = msgBox.scrollHeight;
	}

	// 会話ミラーのスレッドを常時監視（担当者が返信したら表示し、以後スレッド対応に切替）
	function startConvoPoll() {
		if (convo.started || !cfg.convoPollUrl) { return; }
		convo.started = true;
		convo.poll = setInterval(pollConvo, 6000);
	}
	function pollConvo() {
		fetch(cfg.convoPollUrl + "?session_id=" + encodeURIComponent(sessionId))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				var msgs = (d && d.messages) ? d.messages : [];
				if (!msgs.length) { return; }
				if (!convo.engaged) {
					convo.engaged = true;
					clearIdle();
					var att = document.getElementById("carmel-cb-attach");
					if (att) att.style.display = "flex"; // 担当者対応中はファイルも送れる
					addBubble("bot", "担当者が対応しています。このままご相談ください😊");
				}
				msgs.forEach(function (m) {
					if (m.text) addOperator(m.text);
					if (m.files && m.files.length) { m.files.forEach(function (f) { addOperatorMedia(f); }); }
				});
			})
			.catch(function () {});
	}

	// 一定時間 無操作なら「何かご質問ありますか？」と再打診（離脱・取りこぼし防止）
	function clearIdle() { if (idle.timer) { clearTimeout(idle.timer); idle.timer = null; } }
	function scheduleIdle() {
		clearIdle();
		if (idle.count >= 2) { return; } // 打診は最大2回まで（しつこくしない）
		idle.timer = setTimeout(function () {
			if (!opened || convo.engaged || ho.live) { return; }
			idle.count++;
			addBubble("bot", "その後いかがでしょうか？ご不明な点や、気になる車・お支払いのご相談など、お気軽にどうぞ😊");
			renderChoices(["審査について相談したい", "在庫・車種を見たい", "お支払い・頭金について", "担当者に相談したい"]);
		}, 50000); // 約50秒
	}

	// 担当者に相談：会話スレッドに「担当者希望」を投げる（会話は常時ポーリング中）
	function openHandoff() {
		clearChoices();
		clearIdle();
		showTyping();
		var last = (function () { for (var i = history.length - 1; i >= 0; i--) { if (history[i].role === "user") return history[i].content; } return "（担当者希望）"; })();
		fetch(cfg.convoHandoffUrl, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ session_id: sessionId, question: last, page: location.href })
		})
			.then(function (r) { return r.json(); })
			.then(function (d) {
				hideTyping();
				if (d && d.mode === "live") {
					startConvoPoll(); // 担当者の返信を待つ（常時ポーリング）
					addBubble("bot", d.waitMsg || "担当者におつなぎしています。つながるまで少々お待ちください…");
					// 混雑案内 → フォーム提示（担当者が入れば pollConvo が「対応しています」を表示）
					if (d.busyMsg) { ho.busy = setTimeout(function () { if (!convo.engaged) addBubble("bot", d.busyMsg); }, d.busyMs || 15000); }
					ho.giveup = setTimeout(function () {
						if (convo.engaged) { return; }
						addBubble("bot", "担当者の応答に少しお時間がかかっています。このままお待ちいただけば、担当者が入り次第このチャットに表示されます。お急ぎの場合は下記にご連絡先を残していただければ折り返します。");
						showHandoffForm("notify");
					}, d.timeout || 45000);
				}
				else if (d && d.mode === "offhours") { showOffHours(d.msg); }
				else { showHandoffForm("notify"); }
			})
			.catch(function () { hideTyping(); showHandoffForm("notify"); });
	}

	// 営業時間外：担当者は不在。AIが自動対応する旨を案内し、そのまま会話を続けられるようにする。
	// 希望者だけ「折り返し」フォームを開ける（任意）。
	function showOffHours(msg) {
		addBubble("bot", msg || "ただいま営業時間外のため、担当者の対応ができません。このままAIが自動で対応いたしますので、お気軽にご質問ください😊");
		var row = document.createElement("div");
		row.className = "ccb-choices";
		var b = document.createElement("button");
		b.type = "button";
		b.className = "ccb-choice";
		b.textContent = "翌営業日の折り返しを希望する";
		b.addEventListener("click", function () { row.remove(); showHandoffForm("offhours"); });
		row.appendChild(b);
		msgBox.appendChild(row);
		msgBox.scrollTop = msgBox.scrollHeight;
		input.focus();
	}

	// ライブ（担当者がSlackで返信→ここに表示）
	// 待機案内 → 約15秒つながらなければ「混雑」案内 → 最終的にフォームへ
	function startLive(sid, d) {
		d = d || {};
		ho.live = true; ho.sid = sid; ho.connected = false;
		var att = document.getElementById("carmel-cb-attach");
		if (att) att.style.display = "flex"; // 添付ボタンを表示
		addBubble("bot", d.waitMsg || "担当者におつなぎしています。つながるまで少々お待ちください…");
		// 2秒後に、用件を書いてもらう案内＋選択肢を出す（担当者が先につながっていれば出さない）
		ho.nudge = setTimeout(function () {
			if (!ho.live || ho.connected) { return; }
			addBubble("bot", "お待ちの間に、ご相談内容やご希望を下の欄にご入力ください。先に教えていただくと、担当者がスムーズにご案内できます😊");
			renderChoices(["審査について相談したい", "在庫・車種について聞きたい", "お支払い・頭金について", "お電話で相談したい"]);
			try { input.focus(); } catch (e) {}
		}, 2000);
		ho.poll = setInterval(pollOperator, 2500);
		var busyMs = d.busyMs || 15000;
		var timeoutMs = d.timeout || 45000;
		if (d.busyMsg) {
			ho.busy = setTimeout(function () {
				if (ho.live && !ho.connected) { addBubble("bot", d.busyMsg); }
			}, busyMs);
		}
		ho.giveup = setTimeout(onOperatorTimeout, timeoutMs);
		// 保険：長時間（10分）返信が無ければ自動で待機を終了
		ho.hardstop = setTimeout(function () { if (ho.live && !ho.connected) { stopLive(); } }, 600000);
	}
	function stopLive() {
		ho.live = false;
		var att = document.getElementById("carmel-cb-attach");
		if (att) att.style.display = "none";
		if (ho.poll) clearInterval(ho.poll);
		if (ho.giveup) clearTimeout(ho.giveup);
		if (ho.busy) clearTimeout(ho.busy);
		if (ho.hardstop) clearTimeout(ho.hardstop);
		if (ho.nudge) clearTimeout(ho.nudge);
	}
	function pollOperator() {
		if (!ho.live) return;
		fetch(cfg.handoffPollUrl + "?session_id=" + encodeURIComponent(ho.sid))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				(d && d.messages ? d.messages : []).forEach(function (m) {
					if (!ho.connected) {
						ho.connected = true;
						clearTimeout(ho.giveup); clearTimeout(ho.busy); clearTimeout(ho.nudge);
						addBubble("bot", "担当者につながりました。このままご相談ください。");
					}
					if (m.text) addOperator(m.text);
					if (m.files && m.files.length) { m.files.forEach(function (f) { addOperatorMedia(f); }); }
				});
			})
			.catch(function () {});
	}
	// 一定時間つながらない時：待機は切らずに継続（後から担当者が返信しても拾えるように）、
	// 希望者だけ「連絡先を残す」フォームも提示する。
	function onOperatorTimeout() {
		if (ho.connected) return;
		addBubble("bot", "担当者の応答に少しお時間がかかっています。このままお待ちいただけば、担当者が入り次第このチャットに表示されます。お急ぎ・お手すきでない場合は、下記にご連絡先を残していただければ折り返します。");
		showHandoffForm("notify");
		// ← stopLive() しない：ポーリング継続。後から返信が来たら「担当者につながりました」と表示。
	}
	// 担当者メッセージの器（アイコン＋名前＋空の吹き出し）を作る
	function opRow() {
		var w = document.createElement("div");
		w.className = "ccb-msg bot ccb-op-row";
		// まず絵文字アイコンで表示。画像URLが実際に読めたら背景画像に差し替える（読めなければ絵文字のまま）
		w.innerHTML =
			'<span class="ccb-op-avatar ccb-op-avatar--none">🧑‍💼</span>' +
			'<span class="ccb-op-main">' +
				'<span class="ccb-op-label"></span>' +
				'<span class="ccb-bubble ccb-op"></span>' +
			'</span>';
		w.querySelector(".ccb-op-label").textContent = cfg.operatorName || "担当者";
		if (cfg.operatorAvatar) {
			var av = w.querySelector(".ccb-op-avatar");
			var probe = new Image();
			probe.onload = function () {
				av.textContent = "";
				av.classList.remove("ccb-op-avatar--none");
				av.style.backgroundImage = "url('" + cfg.operatorAvatar.replace(/'/g, "%27") + "')";
			};
			probe.onerror = function () { /* 読めなければ絵文字のまま */ };
			probe.src = cfg.operatorAvatar;
		}
		return w;
	}
	function addOperator(text) {
		var w = opRow();
		w.querySelector(".ccb-bubble").innerHTML = linkify(escapeHtml(text));
		msgBox.appendChild(w); msgBox.scrollTop = msgBox.scrollHeight;
	}
	function addOperatorMedia(f) {
		var w = opRow();
		var b = w.querySelector(".ccb-bubble");
		b.className += " ccb-bubble-media";
		b.innerHTML = mediaHtml(f);
		msgBox.appendChild(w); msgBox.scrollTop = msgBox.scrollHeight;
	}

	// 画像/ファイルのHTML（画像はサムネ、その他はリンク）
	function mediaHtml(f) {
		var url = String(f.url || "");
		if (f.isImage) {
			return '<a href="' + url + '" target="_blank" rel="noopener"><img class="ccb-media-img skip-lazy no-lazy" loading="eager" src="' + url + '" alt=""></a>';
		}
		return '<a class="ccb-media-file" href="' + url + '" target="_blank" rel="noopener">📎 ' + escapeHtml(f.name || "ファイル") + '</a>';
	}
	function addUserMedia(f) {
		var w = document.createElement("div");
		w.className = "ccb-msg user";
		var b = document.createElement("div");
		b.className = "ccb-bubble ccb-bubble-media";
		b.innerHTML = mediaHtml(f);
		w.appendChild(b); msgBox.appendChild(w); msgBox.scrollTop = msgBox.scrollHeight;
	}

	// 添付ファイルを選んだとき：担当者ライブ中はSlackへ送る
	function onFilePicked(e) {
		var inp = e.target;
		var file = inp.files && inp.files[0];
		if (!file) { return; }
		if (!ho.live && !convo.engaged) { inp.value = ""; return; }
		if (file.size > 15 * 1024 * 1024) { addBubble("bot", "ファイルが大きすぎます（15MBまで）。"); inp.value = ""; return; }
		var fd = new FormData();
		fd.append("session_id", ho.live ? ho.sid : sessionId);
		fd.append("file", file);
		addBubble("bot", "送信中… 📎 " + file.name);
		fetch(cfg.handoffUploadUrl, { method: "POST", body: fd })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d && d.ok) { addUserMedia({ url: d.url, name: d.name, isImage: d.isImage }); }
				else { addBubble("bot", "送信できませんでした（" + ((d && d.error) || "error") + "）。画像またはPDF・15MBまで対応です。"); }
			})
			.catch(function () { addBubble("bot", "送信に失敗しました。もう一度お試しください。"); })
			.finally(function () { inp.value = ""; });
	}

	// 通知フォーム（連絡先を残してもらう）
	function showHandoffForm(mode) {
		clearChoices();
		var intro = (mode === "offhours")
			? "ただいま営業時間外のため、担当者の対応ができません。ご用件とご連絡先をいただければ、翌営業日に担当者よりご連絡します（このままAIにもご相談いただけます）😊"
			: "担当者におつなぎします。ご用件とご連絡先をいただければ、営業時間内に折り返します。";
		addBubble("bot", intro);

		var form = document.createElement("div");
		form.className = "ccb-msg bot";
		form.innerHTML =
			'<div class="ccb-handoff">' +
			'<input class="ccb-hi" data-k="name" placeholder="お名前（任意）" autocomplete="name">' +
			'<textarea class="ccb-hi" data-k="note" rows="2" placeholder="お問い合わせ内容"></textarea>' +
			'<div class="ccb-hi-label">ご希望の連絡方法（メール・電話・LINEのいずれか1つ以上）</div>' +
			'<input class="ccb-hi" data-k="email" type="email" inputmode="email" placeholder="✉️ メール（任意）" autocomplete="email">' +
			'<input class="ccb-hi" data-k="tel" inputmode="tel" placeholder="📞 電話番号（任意）" autocomplete="tel">' +
			'<input class="ccb-hi" data-k="line" placeholder="💬 LINE ID（任意）">' +
			'<button type="button" class="ccb-handoff-send">この内容で送信する</button>' +
			'<div class="ccb-handoff-err" role="alert"></div>' +
			"</div>";
		msgBox.appendChild(form);
		msgBox.scrollTop = msgBox.scrollHeight;

		var btn = form.querySelector(".ccb-handoff-send");
		var err = form.querySelector(".ccb-handoff-err");
		var get = function (k) { var el = form.querySelector('[data-k="' + k + '"]'); return el ? el.value.trim() : ""; };
		var fail = function (m) { err.textContent = m; err.style.display = "block"; };

		btn.addEventListener("click", function () {
			var name = get("name"), note = get("note"), email = get("email"), tel = get("tel"), line = get("line");
			// 連絡先が1つも無ければ送信不可
			if (!email && !tel && !line) { return fail("メール・電話・LINE のいずれか1つはご入力ください。"); }
			err.style.display = "none";
			var parts = [];
			if (tel) parts.push("電話: " + tel);
			if (email) parts.push("メール: " + email);
			if (line) parts.push("LINE: " + line);
			var contact = parts.join(" / ");
			btn.disabled = true; btn.textContent = "送信中…";
			fetch(cfg.handoffUrl, {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ name: name, contact: contact, note: note, messages: history, page: location.href })
			})
				.then(function (r) { return r.json(); })
				.then(function (d) {
					form.remove();
					var msg = (d && d.status === "offhours")
						? "ありがとうございます。ご用件を担当者に共有しました。翌営業日にご連絡します。引き続きAIにもご相談いただけます😊"
						: "ありがとうございます！担当者に通知しました。営業時間内に順次ご連絡します。お急ぎの場合はLINEからどうぞ。";
					addBubble("bot", msg);
					renderCTA();
				})
				.catch(function () {
					btn.disabled = false; btn.textContent = "この内容で送信する";
					fail("送信に失敗しました。お手数ですがLINEまたはお電話でご連絡ください。");
				});
		});
	}

	// 会話の流れに合わせて“入口”を出す。
	// action: "apply"=審査 / "contact"=問い合わせ / "handoff"=担当者 / "stock"=在庫一覧 / "line"=LINE / ""=汎用
	function renderCTA(action) {
		var formMode = cfg.ctaMode !== "link"; // 既定はチャット内フォーム
		action = action || "";

		// この入口を出す、という希望リスト（順序＝表示順）
		var want = { apply: false, contact: false, handoff: false, stock: false, line: false, tel: false };
		if (action === "apply") { want.apply = true; want.line = true; }
		else if (action === "contact") { want.contact = true; want.line = true; }
		else if (action === "handoff") { want.handoff = true; want.line = true; }
		else if (action === "stock") { want.stock = true; want.line = true; }
		else if (action === "line") { want.line = true; want.tel = true; }
		else { want.apply = true; want.contact = true; want.line = true; } // 汎用

		// 設定・値が無いものは落とす
		var canApply = formMode ? cfg.applyFormOn : !!cfg.applyUrl;
		var show = {
			apply:   want.apply   && canApply,
			contact: want.contact && (formMode ? cfg.contactFormOn : !!cfg.contactUrl),
			handoff: want.handoff && !!cfg.handoffOn,
			stock:   want.stock   && !!cfg.stockUrl,
			line:    want.line    && !!cfg.lineUrl,
			tel:     want.tel     && !!cfg.tel
		};
		// 仮審査は専用ページ(applyUrl=/shinsa-2)へ移行させる。URLがあればフォームモードでもリンク優先。
		var applyToPage = !!cfg.applyUrl;
		show.apply = want.apply && ( applyToPage || ( formMode ? cfg.applyFormOn : false ) );
		if (!show.apply && !show.contact && !show.handoff && !show.stock && !show.line && !show.tel) return;

		var row = document.createElement("div");
		row.className = "ccb-msg bot";
		var wrap = document.createElement("div");
		wrap.className = "ccb-cta";

		function addBtnLink(cls, text, href, sameTab) {
			var a = document.createElement("a");
			a.className = "ccb-cta-btn " + cls;
			a.href = href;
			if (!sameTab) { a.target = "_blank"; a.rel = "noopener"; }
			a.textContent = text;
			wrap.appendChild(a);
		}
		function addBtnAct(cls, text, fn) {
			var b = document.createElement("button");
			b.type = "button"; b.className = "ccb-cta-btn " + cls;
			b.textContent = text;
			b.addEventListener("click", fn);
			wrap.appendChild(b);
		}

		if (show.apply) {
			// 仮審査になったら /shinsa-2 に移行（同じタブで遷移）。URL未設定のときだけチャット内フォーム。
			if (applyToPage) addBtnLink("ccb-cta-apply", "📝 仮審査を申し込む", cfg.applyUrl, true);
			else addBtnAct("ccb-cta-apply", "📝 かんたん審査", function () { showLeadForm("apply"); });
		}
		if (show.contact) {
			if (formMode) addBtnAct("ccb-cta-contact", "✉️ お問い合わせ", function () { showLeadForm("contact"); });
			else addBtnLink("ccb-cta-contact", "✉️ お問い合わせ", cfg.contactUrl);
		}
		if (show.stock) addBtnLink("ccb-cta-stock", "🚗 在庫を見る", cfg.stockUrl);
		if (show.handoff) addBtnAct("ccb-cta-handoff", "🙋 担当者に相談", function () { openHandoff(); });
		if (show.line) addBtnLink("ccb-cta-line", "💬 LINEで相談", cfg.lineUrl);
		if (show.tel) {
			var t = document.createElement("a");
			t.className = "ccb-cta-btn ccb-cta-tel";
			t.href = "tel:" + cfg.tel;
			t.textContent = "📞 " + cfg.tel;
			wrap.appendChild(t);
		}

		row.appendChild(wrap);
		msgBox.appendChild(row);
		msgBox.scrollTop = msgBox.scrollHeight;
	}

	// チャット内フォーム（かんたん審査 / お問い合わせ）：離脱せず連絡先を受け付ける
	function showLeadForm(type) {
		var isApply = type === "apply";
		clearChoices();
		addBubble("bot", isApply
			? "かんたん審査ですね。お名前とご連絡先をいただければ、無理のないお支払いプランを担当者からご案内します。約1分で完了します😊"
			: "お問い合わせありがとうございます。お名前・ご連絡先・ご相談内容をご記入ください。担当者より順次ご連絡します😊");

		var consent = cfg.consentText
			? '<label class="ccb-lead-consent"><input type="checkbox" data-k="consent"> <span>' + escapeHtml(cfg.consentText) +
			  (cfg.consentUrl ? ' <a href="' + cfg.consentUrl + '" target="_blank" rel="noopener">詳細</a>' : '') + '</span></label>'
			: '';

		// 「両方」対応：審査フォームの下に、本格審査ページ(applyUrl=/shinsa-2)への案内リンク
		var fullLink = (isApply && cfg.applyUrl)
			? '<a class="ccb-lead-full" href="' + cfg.applyUrl + '" target="_blank" rel="noopener">🖊 くわしく申し込む（本格審査フォーム）→</a>'
			: '';

		var form = document.createElement("div");
		form.className = "ccb-msg bot";
		form.innerHTML =
			'<div class="ccb-lead" data-type="' + type + '">' +
			'<div class="ccb-lead-title">' + (isApply ? "かんたん審査申し込み" : "お問い合わせフォーム") + '</div>' +
			'<div class="ccb-lead-fields">' +
				'<input class="ccb-li" data-k="name" placeholder="お名前（必須）" autocomplete="name">' +
				'<input class="ccb-li" data-k="tel" inputmode="tel" placeholder="電話番号（必須）" autocomplete="tel">' +
				'<input class="ccb-li" data-k="email" inputmode="email" placeholder="メール（任意）" autocomplete="email">' +
				(isApply ? '<input class="ccb-li" data-k="wish" placeholder="ご希望の車種・ご予算（任意）">' : '') +
				'<textarea class="ccb-li" data-k="note" rows="2" placeholder="' + (isApply ? "ご相談・ご質問（任意）" : "お問い合わせ内容") + '"></textarea>' +
				consent +
				'<button type="button" class="ccb-lead-next">確認へ進む</button>' +
				'<div class="ccb-lead-err" role="alert"></div>' +
			'</div>' +
			'<div class="ccb-lead-confirm" style="display:none">' +
				'<div class="ccb-lead-confirm-ttl">この内容でよろしいですか？</div>' +
				'<dl class="ccb-lead-review"></dl>' +
				'<div class="ccb-lead-confirm-btns">' +
					'<button type="button" class="ccb-lead-back">修正する</button>' +
					'<button type="button" class="ccb-lead-send">' + (isApply ? "この内容で申し込む" : "この内容で送信する") + '</button>' +
				'</div>' +
			'</div>' +
			fullLink +
			'</div>';
		msgBox.appendChild(form);
		msgBox.scrollTop = msgBox.scrollHeight;

		var fieldsBox = form.querySelector(".ccb-lead-fields");
		var confirmBox = form.querySelector(".ccb-lead-confirm");
		var review = form.querySelector(".ccb-lead-review");
		var nextBtn = form.querySelector(".ccb-lead-next");
		var backBtn = form.querySelector(".ccb-lead-back");
		var sendBtn2 = form.querySelector(".ccb-lead-send");
		var err = form.querySelector(".ccb-lead-err");
		var label = sendBtn2.textContent;

		var get = function (k) {
			var el = form.querySelector('[data-k="' + k + '"]');
			if (!el) return "";
			return el.type === "checkbox" ? el.checked : el.value.trim();
		};
		var fail = function (m) { err.textContent = m; err.style.display = "block"; };
		var addRow = function (rlabel, val) {
			if (!val) return;
			var dt = document.createElement("dt"); dt.textContent = rlabel;
			var dd = document.createElement("dd"); dd.textContent = val;
			review.appendChild(dt); review.appendChild(dd);
		};

		// 入力 → 確認画面へ
		nextBtn.addEventListener("click", function () {
			var name = get("name"), tel = get("tel"), email = get("email");
			if (!name) { return fail("お名前をご入力ください。"); }
			if (!tel && !email) { return fail("電話番号またはメールをご入力ください。"); }
			if (cfg.consentText && !get("consent")) { return fail("個人情報の取扱いにご同意のうえ送信してください。"); }
			err.style.display = "none";
			review.innerHTML = "";
			addRow("お名前", name);
			addRow("電話番号", tel);
			addRow("メール", email);
			if (isApply) addRow("ご希望", get("wish"));
			addRow(isApply ? "ご相談" : "お問い合わせ", get("note"));
			fieldsBox.style.display = "none";
			confirmBox.style.display = "block";
			msgBox.scrollTop = msgBox.scrollHeight;
		});

		// 確認 → 修正に戻る
		backBtn.addEventListener("click", function () {
			confirmBox.style.display = "none";
			fieldsBox.style.display = "";
			msgBox.scrollTop = msgBox.scrollHeight;
		});

		// 確認 → 送信
		sendBtn2.addEventListener("click", function () {
			sendBtn2.disabled = true; backBtn.disabled = true; sendBtn2.textContent = "送信中…";
			fetch(cfg.leadUrl, {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ type: type, name: get("name"), tel: get("tel"), email: get("email"), wish: get("wish"), note: get("note"), messages: history, page: location.href })
			})
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (!d || !d.ok) {
						sendBtn2.disabled = false; backBtn.disabled = false; sendBtn2.textContent = label;
						confirmBox.style.display = "none"; fieldsBox.style.display = "";
						return fail("送信できませんでした。お手数ですがLINEまたはお電話でご連絡ください。");
					}
					form.remove();
					addBubble("bot", d.thanks || "送信しました。ありがとうございます！");
					if (cfg.lineUrl || cfg.tel) renderCTA();
				})
				.catch(function () {
					sendBtn2.disabled = false; backBtn.disabled = false; sendBtn2.textContent = label;
					confirmBox.style.display = "none"; fieldsBox.style.display = "";
					fail("通信に失敗しました。もう一度お試しください。");
				});
		});
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
		});
	}
	function linkify(s) {
		// マークダウンのリンク [表示文](URL) → クリック可能なリンク
		s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
		// 素のURL（既にリンク化された箇所は除外）
		s = s.replace(/(^|[^"'>=])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
		return s;
	}
})();
