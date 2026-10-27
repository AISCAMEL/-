/**
 * ミーティング予約（日にち → 時間 → お名前・メール）。inc/meeting-booking.php の画面で使う。
 * LINEの中（LIFF）で開いたときは、LINEログインで名前を入れ、確認をLINEにも送る。
 */
(function () {
	'use strict';
	var root = document.getElementById('apprexMeeting');
	if (!root) { return; }
	var C = JSON.parse(root.getAttribute('data-cfg') || '{}');
	var S = { days: [], day: 0, at: 0, label: '', idt: '', inLine: false };
	function $(id) { return document.getElementById(id); }
	function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	function renderDays() {
		if (!S.days.length) { $('mbDays').innerHTML = '<p class="mb-note">いま予約できる日時がありません。お手数ですが、お問い合わせフォームからご連絡ください。</p>'; return; }
		$('mbDays').innerHTML = S.days.map(function (d, i) {
			var n = d.times.filter(function (t) { return t.ok; }).length;
			return '<button type="button" class="mb-day' + (i === S.day ? ' on' : '') + '" data-day="' + i + '"' + (n ? '' : ' disabled') + '><b>' + esc(d.label.replace(/\(.\)/, '')) + '</b><span>' + esc((d.label.match(/\((.)\)/) || ['', ''])[1]) + '</span><em>' + (n ? '残り' + n : '満席') + '</em></button>';
		}).join('');
	}
	function renderTimes() {
		var d = S.days[S.day];
		if (!d) { return; }
		$('mbTimes').innerHTML = d.times.map(function (t) {
			return '<button type="button" class="mb-time' + (t.at === S.at ? ' on' : '') + '" data-at="' + t.at + '" data-t="' + esc(t.t) + '"' + (t.ok ? '' : ' disabled') + '>' + esc(t.t) + (t.ok ? '' : '<small>予約済み</small>') + '</button>';
		}).join('');
	}
	function renderSel() {
		var d = S.days[S.day];
		$('mbSel').innerHTML = S.at ? '<b>' + esc(d.label) + ' ' + esc(S.label) + '〜</b>でご予約します' : '日時を選んでください';
		$('mbSel').classList.toggle('on', !!S.at);
		$('mbBtn').disabled = !S.at;
	}
	function load() {
		fetch(C.slots, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (b) {
			S.days = (b && b.days) || [];
			var first = 0;
			for (var i = 0; i < S.days.length; i++) { if (S.days[i].times.some(function (t) { return t.ok; })) { first = i; break; } }
			S.day = first; S.at = 0;
			renderDays(); renderTimes(); renderSel();
		}).catch(function () { $('mbDays').innerHTML = '<p class="mb-err">空き枠を読み込めませんでした。ページを開き直してください。</p>'; });
	}
	root.addEventListener('click', function (e) {
		var b = e.target.closest('button');
		if (!b || b.disabled) { return; }
		if (b.dataset.day != null) { S.day = +b.dataset.day; S.at = 0; renderDays(); renderTimes(); renderSel(); }
		if (b.dataset.at) { S.at = +b.dataset.at; S.label = b.dataset.t; renderTimes(); renderSel(); }
	});

	// LINEの中で開いたとき。
	if (C.liff && window.liff) {
		liff.init({ liffId: C.liff }).then(function () {
			S.inLine = liff.isInClient();
			if (!liff.isLoggedIn()) { if (S.inLine) { liff.login(); } return; }
			S.idt = liff.getIDToken() || '';
			liff.getProfile().then(function (p) {
				var n = root.querySelector('input[name=name]'); if (n && !n.value) { n.value = p.displayName || ''; }
				$('mbWho').innerHTML = '<div class="mb-who">' + (p.pictureUrl ? '<img src="' + esc(p.pictureUrl) + '" alt="">' : '') + '<span><b>' + esc(p.displayName) + '</b> さんのLINEで予約します。確認とお知らせはLINEにも届きます。</span></div>';
			}).catch(function () {});
		}).catch(function () {});
	}

	$('mbForm').addEventListener('submit', function (e) {
		e.preventDefault();
		var f = e.target, er = $('mbErr'); er.hidden = true;
		var d = { at: S.at, name: f.name.value.trim(), company: f.company.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim(), message: f.message.value.trim(), id_token: S.idt };
		if (!d.at) { er.textContent = '日時を選んでください。'; er.hidden = false; return; }
		if (!d.name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) { er.textContent = 'お名前とメールアドレスをご入力ください。'; er.hidden = false; return; }
		var btn = $('mbBtn'); btn.disabled = true; btn.textContent = '予約しています…';
		fetch(C.book, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(d) })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (!r || !r.ok) { throw new Error((r && r.message) || '予約できませんでした。'); }
				done(r);
			})
			.catch(function (x) { er.textContent = x.message; er.hidden = false; btn.disabled = false; btn.textContent = 'この日時で予約する'; load(); });
	});

	function gcal(r) {
		var z = function (t) { return new Date(t * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); };
		return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent('APPREX オンライン相談') + '&dates=' + z(r.at) + '/' + z(r.at + (r.min || 60) * 60) + '&details=' + encodeURIComponent(r.url ? '参加URL：' + r.url : 'APPREX オンライン相談（Google Meet）') + (r.url ? '&location=' + encodeURIComponent(r.url) : '');
	}
	function done(r) {
		var h = '<div class="mb-card mb-done"><div class="mb-ok">✓</div><h2>ご予約が確定しました</h2>'
			+ '<div class="mb-box"><b>' + esc(r.when) + '</b><br>オンライン（Google Meet）</div>'
			+ '<p>' + (r.line ? '確認をLINEとメールにお送りしました。' : '確認のメールをお送りしました。') + '<br>前日と1時間前にもお知らせします。</p>'
			+ (r.url ? '<a class="mb-btn" href="' + esc(r.url) + '" target="_blank" rel="noopener">参加URLを開く（当日）</a>' : '')
			+ '<a class="mb-btn mb-sub" href="' + esc(gcal(r)) + '" target="_blank" rel="noopener">Googleカレンダーに追加</a>'
			+ (S.inLine ? '<button type="button" class="mb-btn mb-sub" id="mbClose">LINEに戻る</button>' : (C.line ? '<a class="mb-btn mb-line" href="' + esc(C.line) + '" target="_blank" rel="noopener">LINEの友だち追加（お知らせを受け取る）</a>' : ''))
			+ '</div>';
		root.innerHTML = h;
		var cl = $('mbClose'); if (cl) { cl.onclick = function () { try { liff.closeWindow(); } catch (x) {} }; }
		try { document.dispatchEvent(new CustomEvent('apprex:lead', { detail: { type: 'meeting' } })); } catch (x) {}
		root.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}
	load();
})();
