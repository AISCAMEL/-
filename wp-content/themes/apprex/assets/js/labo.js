/**
 * APPREX LABO（LINEの中で開くミニアプリ）。inc/labo.php の /labo で使う。
 * タブ：ホーム／つくる（特典）／学ぶ／予約／マイページ。
 */
(function () {
	'use strict';
	var root = document.getElementById('labo');
	if (!root) { return; }
	var C = JSON.parse(root.getAttribute('data-cfg') || '{}');
	var S = { tab: 'home', idt: '', prof: null, me: {}, app: null, posts: null, cats: [], cat: '', art: null, ind: 'food', shop: '', mood: '', busy: false, days: null, day: 0, at: 0, tlabel: '', booked: null, err: '' };
	var main = document.getElementById('lbMain');
	var src = (new URLSearchParams(location.search)).get('src') || '';
	try { if (src) { sessionStorage.setItem('labo_src', src); } else { src = sessionStorage.getItem('labo_src') || ''; } } catch (e) {}

	function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function api(path, body, get) {
		var o = get ? { credentials: 'same-origin' } : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(Object.assign({ idt: S.idt }, body || {})) };
		return fetch(C.rest + path, o).then(function (r) { return r.json(); });
	}
	function go(tab) { S.tab = tab; S.art = null; S.err = ''; render(); window.scrollTo(0, 0); }

	/* ---------- 画面 ---------- */
	function phone(a) {
		return '<div class="lb-phone"><div class="lb-ph-top" style="background:' + esc(a.color) + '"><small>' + esc(a.label) + '</small><b>' + esc(a.shop || 'あなたのお店') + '</b><span>' + esc(a.catch) + '</span></div>'
			+ '<div class="lb-ph-grid">' + a.menus.map(function (m) { return '<div style="background:' + esc(a.soft) + '"><i style="background:' + esc(a.color) + '"></i>' + esc(m) + '</div>'; }).join('') + '</div>'
			+ '<div class="lb-ph-cpn">クーポン：次回10％オフ</div><div class="lb-ph-news"><b>お知らせ</b><span>' + esc(a.shop || 'お店') + 'のアプリができました</span></div><div class="lb-ph-tab"><span>ホーム</span><span>予約</span><span>会員証</span><span>お知らせ</span></div></div>';
	}
	function card(p, big) {
		return '<button type="button" class="lb-art' + (big ? ' big' : '') + '" data-art="' + p.id + '"><div class="lb-art-img"' + (p.thumb ? ' style="background-image:url(' + esc(p.thumb) + ')"' : '') + '>' + (p.video ? '<span class="lb-play" aria-hidden="true">▶</span>' : '') + '</div><div class="lb-art-t">' + (p.cat ? '<small>' + esc(p.cat) + (p.video ? '・動画' : '') + '</small>' : '') + '<b>' + esc(p.title) + '</b></div></button>';
	}
	function vHome() {
		var nm = S.prof ? S.prof.displayName : (S.me.name || '');
		var arts = (S.posts || []).slice(0, 3);
		return '<div class="lb-hello">' + (S.prof && S.prof.pictureUrl ? '<img src="' + esc(S.prof.pictureUrl) + '" alt="">' : '<span class="lb-ava">L</span>') + '<div><small>ようこそ</small><b>' + (nm ? esc(nm) + ' さん' : 'APPREX LABOへ') + '</b></div></div>'
			+ '<section class="lb-hero"><span class="lb-badge">友だち限定の特典</span><h1>AIで、あなたのお店の<br>アプリ画面を無料で作る</h1><p>業種と店名を入れるだけ。スマホで見られるアプリの画面ができます。</p><button type="button" class="lb-btn" data-go="make">' + (S.app ? '作った画面を見る' : 'いますぐ作ってみる') + '</button></section>'
			+ '<div class="lb-quick"><button type="button" data-go="learn"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h11a3 3 0 0 1 3 3v11H7a3 3 0 0 1-3-3z"/><path d="M8 9h6M8 13h4"/></svg>学ぶ</button><button type="button" data-go="book"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>無料相談</button><a href="' + esc(C.cases) + '"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/></svg>導入事例</a></div>'
			+ (arts.length ? '<div class="lb-sh"><h2>新着の記事・動画</h2><button type="button" data-go="learn">すべて見る</button></div><div class="lb-list">' + arts.map(function (p) { return card(p, false); }).join('') + '</div>' : '');
	}
	function vMake() {
		if (S.app && !S.remake) {
			return '<div class="lb-sh"><h1>できました</h1><button type="button" data-act="remake">作り直す</button></div>' + phone(S.app)
				+ '<div class="lb-card"><b>このアプリ、実際に作れます</b><p>作った画面のまま、担当者がオンラインでご説明します（無料）。</p><button type="button" class="lb-btn" data-go="book">この画面で無料相談する</button><a class="lb-btn sub" href="' + esc(C.studio) + '">AIスタジオで続きを作る</a><button type="button" class="lb-btn sub" data-go="learn">まずは事例を見る</button></div>';
		}
		var inds = Object.keys(C.inds).map(function (k) { return '<button type="button" class="lb-chip' + (S.ind === k ? ' on' : '') + '" data-ind="' + k + '">' + esc(C.inds[k]) + '</button>'; }).join('');
		var moods = ['高級感', 'かわいい', 'ナチュラル', '元気', '信頼感'].map(function (m) { return '<button type="button" class="lb-chip sm' + (S.mood === m ? ' on' : '') + '" data-mood="' + m + '">' + m + '</button>'; }).join('');
		return '<div><small class="lb-kicker">特典</small><h1 class="lb-h1">AIでアプリ画面を作る</h1></div><div class="lb-card">'
			+ '<b>1. 業種</b><div class="lb-chips three">' + inds + '</div>'
			+ '<label class="lb-f"><b>2. 店名</b><input id="lbShop" value="' + esc(S.shop) + '" placeholder="例：炭火焼肉 はなび" maxlength="30"></label>'
			+ '<b>3. 雰囲気<small>（任意）</small></b><div class="lb-chips">' + moods + '</div>'
			+ '<button type="button" class="lb-btn" data-act="make"' + (S.busy ? ' disabled' : '') + '>' + (S.busy ? 'AIが作っています…' : 'AIで作る（無料）') + '</button>'
			+ (S.err ? '<p class="lb-err">' + esc(S.err) + '</p>' : '') + '</div>';
	}
	function vLearn() {
		if (S.art) {
			var a = S.art, cta = a.cta || 'make';
			return '<button type="button" class="lb-back" data-act="back">← 記事一覧</button>'
				+ (a.embed ? '<div class="lb-video"><iframe src="' + esc(a.embed) + '" title="' + esc(a.title) + '" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' : (a.thumb ? '<img class="lb-eyec" src="' + esc(a.thumb) + '" alt="">' : ''))
				+ (a.cat ? '<small class="lb-kicker">' + esc(a.cat) + '</small>' : '') + '<h1 class="lb-h1">' + esc(a.title) + '</h1><div class="lb-body">' + a.html + '</div>'
				+ (cta !== 'none' ? '<div class="lb-card warm"><b>あなたのお店の場合は？</b>' + (cta === 'seminar' ? '<a class="lb-btn" href="' + esc(C.seminar) + '">代理店説明会を予約する</a>' : (cta === 'book' ? '<button type="button" class="lb-btn" data-go="book">無料相談を予約する</button>' : '<button type="button" class="lb-btn" data-go="make">AIでアプリ画面を作ってみる</button>')) + (cta === 'make' ? '<button type="button" class="lb-btn sub" data-go="book">無料相談を予約する</button>' : '<button type="button" class="lb-btn sub" data-go="make">AIでアプリ画面を作ってみる</button>') + '</div>' : '');
		}
		if (S.posts === null) { return '<div class="lb-loading"><span></span>読み込んでいます…</div>'; }
		var cats = ['すべて'].concat(S.cats).map(function (c) { var v = c === 'すべて' ? '' : c; return '<button type="button" class="lb-chip sm' + (S.cat === v ? ' on' : '') + '" data-cat="' + esc(v) + '">' + esc(c) + '</button>'; }).join('');
		var list = S.posts.filter(function (p) { return !S.cat || p.cat === S.cat; });
		return '<h1 class="lb-h1">学ぶ</h1>' + (S.cats.length ? '<div class="lb-chips">' + cats + '</div>' : '')
			+ (list.length ? '<div class="lb-list">' + list.map(function (p) { return card(p, true); }).join('') + '</div>' : '<div class="lb-card"><p>記事・動画は準備中です。もうしばらくお待ちください。</p></div>');
	}
	function vBook() {
		if (S.booked) {
			return '<div class="lb-card center"><div class="lb-ok">✓</div><h1 class="lb-h1">予約が確定しました</h1><div class="lb-box">' + esc(S.booked.when) + '<br>オンライン（Google Meet）</div><p>' + (S.booked.line ? '確認をこのLINEとメールにお送りしました。' : '確認のメールをお送りしました。') + '<br>前日と1時間前にもお知らせします。</p><button type="button" class="lb-btn sub" data-go="my">マイページで確認する</button></div>';
		}
		if (!C.meet) {
			return '<h1 class="lb-h1">無料オンライン相談</h1><div class="lb-card"><p>空いている日時を選んでご予約ください。</p><a class="lb-btn" href="' + esc(C.meetUrl) + '">予約ページを開く</a></div>';
		}
		if (S.days === null) { loadSlots(); return '<div class="lb-loading"><span></span>空き枠を読み込んでいます…</div>'; }
		if (!S.days.length) { return '<h1 class="lb-h1">無料オンライン相談</h1><div class="lb-card"><p>いま予約できる日時がありません。このLINEで「相談したい」と送ってください。</p></div>'; }
		var d = S.days[S.day];
		var dh = S.days.map(function (x, i) { var n = x.times.filter(function (t) { return t.ok; }).length; return '<button type="button" class="mb-day' + (i === S.day ? ' on' : '') + '" data-day="' + i + '"' + (n ? '' : ' disabled') + '><b>' + esc(x.label.replace(/\(.\)/, '')) + '</b><span>' + esc((x.label.match(/\((.)\)/) || ['', ''])[1]) + '</span><em>' + (n ? '残り' + n : '満席') + '</em></button>'; }).join('');
		var th = d.times.map(function (t) { return '<button type="button" class="mb-time' + (t.at === S.at ? ' on' : '') + '" data-at="' + t.at + '" data-t="' + esc(t.t) + '"' + (t.ok ? '' : ' disabled') + '>' + esc(t.t) + (t.ok ? '' : '<small>予約済み</small>') + '</button>'; }).join('');
		var nm = S.prof ? S.prof.displayName : '';
		return '<div><h1 class="lb-h1">無料オンライン相談</h1><small class="lb-sub">Google Meet・60分。作った画面を見ながらご説明します。</small></div>'
			+ '<div class="mb-card"><div class="mb-h"><span>1</span>日にち</div><div class="mb-days">' + dh + '</div><div class="mb-h"><span>2</span>時間</div><div class="mb-times">' + th + '</div></div>'
			+ '<form class="mb-card mb" id="lbBook" novalidate><div class="mb-h"><span>3</span>お名前・ご連絡先</div><div class="mb-sel' + (S.at ? ' on' : '') + '">' + (S.at ? '<b>' + esc(d.label) + ' ' + esc(S.tlabel) + '〜</b>でご予約します' : '日時を選んでください') + '</div>'
			+ '<label>お名前<em>*</em><input name="name" value="' + esc(nm) + '" required></label><label>メールアドレス<em>*</em><input type="email" name="email" inputmode="email" required></label><label>会社名・屋号<small>（任意）</small><input name="company"></label><label>相談したいこと<small>（任意）</small><textarea name="message" rows="3">' + (S.app ? esc('AIで作った画面：' + (S.app.shop || '') + '（' + S.app.label + '）') : '') + '</textarea></label>'
			+ '<button type="submit" class="mb-btn"' + (S.at && !S.busy ? '' : ' disabled') + '>' + (S.busy ? '予約しています…' : 'この日時で予約する') + '</button>' + (S.err ? '<p class="mb-err">' + esc(S.err) + '</p>' : '') + (C.pp ? '<p class="mb-note">予約すると<a href="' + esc(C.pp) + '" target="_blank" rel="noopener">プライバシーポリシー</a>に同意したものとします。</p>' : '') + '</form>'
			+ (C.seminar ? '<a class="lb-link" href="' + esc(C.seminar) + '">代理店に興味がある方は、代理店説明会へ →</a>' : '');
	}
	function vMy() {
		var bk = (S.me.bookings || []).slice();
		if (S.booked && !bk.some(function (b) { return b.when === S.booked.when; })) { bk.unshift({ kind: 'オンライン相談', when: S.booked.when }); }
		var steps = [['友だち追加', true], ['AIでアプリ画面を作成', !!S.app], ['記事・動画を見る', (S.me.views || 0) > 0], ['無料相談の予約', bk.length > 0], ['お見積り・制作', !!S.me.member]];
		return '<h1 class="lb-h1">マイページ</h1><div class="lb-card"><b>いまの進み具合</b><ol class="lb-steps">' + steps.map(function (s, i) { return '<li class="' + (s[1] ? 'on' : '') + '"><span>' + (i + 1) + '</span>' + esc(s[0]) + '</li>'; }).join('') + '</ol></div>'
			+ '<div class="lb-card"><b>作ったアプリ画面</b><p>' + (S.app ? esc((S.app.shop || 'あなたのお店') + '（' + S.app.label + '）') : 'まだ作っていません') + '</p><button type="button" class="lb-link" data-go="make">' + (S.app ? '画面を見る・作り直す' : 'つくってみる') + ' →</button></div>'
			+ '<div class="lb-card"><b>ご予約</b>' + (bk.length ? bk.map(function (b) { return '<p>' + esc(b.kind) + '：' + esc(b.when) + '</p>'; }).join('') : '<p>まだ予約していません</p>') + '</div>'
			+ '<div class="lb-card"><b>ご契約中の方</b><p>制作の進捗・ご請求・担当者とのメッセージは、会員マイページで見られます。</p><a class="lb-btn sub" href="' + esc(C.mypage) + '">会員マイページを開く</a></div>'
			+ (!S.idt ? '<p class="lb-note">LINEの中で開くと、記録がLINEアカウントに保存されます。</p>' : '');
	}
	function render() {
		var v = { home: vHome, make: vMake, learn: vLearn, book: vBook, my: vMy }[S.tab] || vHome;
		main.innerHTML = v();
		Array.prototype.forEach.call(document.querySelectorAll('#lbNav [data-tab]'), function (b) { b.classList.toggle('on', b.getAttribute('data-tab') === S.tab); });
		if (S.tab === 'learn' && S.posts === null) { loadPosts(); }
	}

	/* ---------- データ ---------- */
	function loadPosts() {
		api('labo/posts', null, true).then(function (r) { S.posts = (r && r.posts) || []; S.cats = (r && r.cats) || []; if (S.tab === 'learn' || S.tab === 'home') { render(); } }).catch(function () { S.posts = []; render(); });
	}
	function loadSlots() {
		fetch(C.rest + 'meeting/slots', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (b) {
			S.days = (b && b.days) || []; S.day = 0; for (var i = 0; i < S.days.length; i++) { if (S.days[i].times.some(function (t) { return t.ok; })) { S.day = i; break; } }
			S.at = 0; render();
		}).catch(function () { S.days = []; render(); });
	}
	function hello() {
		api('labo/hello', { src: src }).then(function (r) { S.me = r || {}; if (r && r.app && !S.app) { S.app = r.app; } render(); }).catch(function () { render(); });
	}

	/* ---------- 操作 ---------- */
	root.addEventListener('click', function (e) {
		var b = e.target.closest('button, a'); if (!b || b.disabled) { return; }
		if (b.dataset.tab) { go(b.dataset.tab); return; }
		if (b.dataset.go) { S.remake = false; go(b.dataset.go); return; }
		if (b.dataset.ind) { S.ind = b.dataset.ind; render(); return; }
		if (b.dataset.mood) { S.mood = S.mood === b.dataset.mood ? '' : b.dataset.mood; keepShop(); render(); return; }
		if (b.dataset.cat != null) { S.cat = b.dataset.cat; render(); return; }
		if (b.dataset.art) {
			S.art = { id: +b.dataset.art, title: '', html: '<div class="lb-loading"><span></span>読み込んでいます…</div>' }; S.tab = 'learn'; render(); window.scrollTo(0, 0);
			api('labo/post', { id: +b.dataset.art }).then(function (r) { if (r && r.ok) { S.art = r.post; S.me.views = (S.me.views || 0) + 1; } else { S.art = null; } render(); });
			return;
		}
		if (b.dataset.day != null) { S.day = +b.dataset.day; S.at = 0; render(); return; }
		if (b.dataset.at) { S.at = +b.dataset.at; S.tlabel = b.dataset.t; keepForm(); render(); restoreForm(); return; }
		var act = b.dataset.act;
		if (act === 'back') { S.art = null; render(); return; }
		if (act === 'remake') { S.remake = true; render(); return; }
		if (act === 'make') {
			keepShop(); S.busy = true; S.err = ''; render();
			api('labo/make', { ind: S.ind, shop: S.shop, mood: S.mood }).then(function (r) {
				S.busy = false; if (r && r.ok) { S.app = r.app; S.remake = false; } else { S.err = (r && r.message) || '作れませんでした。'; } render(); window.scrollTo(0, 0);
			}).catch(function () { S.busy = false; S.err = '通信エラーが発生しました。'; render(); });
		}
	});
	var draft = {};
	function keepShop() { var i = document.getElementById('lbShop'); if (i) { S.shop = i.value.trim(); } }
	function keepForm() { var f = document.getElementById('lbBook'); if (f) { ['name', 'email', 'company', 'message'].forEach(function (k) { draft[k] = f[k].value; }); } }
	function restoreForm() { var f = document.getElementById('lbBook'); if (f) { ['name', 'email', 'company', 'message'].forEach(function (k) { if (draft[k] != null) { f[k].value = draft[k]; } }); } }
	root.addEventListener('submit', function (e) {
		if (e.target.id !== 'lbBook') { return; }
		e.preventDefault(); keepForm();
		var f = e.target, d = { at: S.at, name: f.name.value.trim(), email: f.email.value.trim(), company: f.company.value.trim(), message: f.message.value.trim(), id_token: S.idt };
		if (!d.name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) { S.err = 'お名前とメールアドレスをご入力ください。'; render(); restoreForm(); return; }
		S.busy = true; S.err = ''; render(); restoreForm();
		fetch(C.rest + 'meeting/book', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(d) })
			.then(function (r) { return r.json(); })
			.then(function (r) { S.busy = false; if (!r || !r.ok) { throw new Error((r && r.message) || '予約できませんでした。'); } S.booked = r; render(); window.scrollTo(0, 0); })
			.catch(function (x) { S.busy = false; S.err = x.message; S.days = null; render(); });
	});

	/* ---------- はじめ：LINEログイン ---------- */
	function start() { loadPosts(); hello(); render(); }
	if (C.liff && window.liff) {
		liff.init({ liffId: C.liff }).then(function () {
			if (!liff.isLoggedIn()) { liff.login({ redirectUri: location.href }); return; }
			S.idt = liff.getIDToken() || '';
			liff.getProfile().then(function (p) { S.prof = p; render(); }).catch(function () {});
			start();
		}).catch(function () { start(); });
	} else {
		start();
	}
})();
