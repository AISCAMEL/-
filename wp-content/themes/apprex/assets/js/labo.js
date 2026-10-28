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
	// アイコン（線画）。ボタン名の言葉で選ぶ。
	var IC = {
		cal: '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/>',
		book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
		ticket: '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M13 6v2M13 11v2M13 16v2"/>',
		stamp: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
		bell: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
		pin: '<path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
		card: '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
		cut: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		clip: '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
		layers: '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
		bag: '<path d="M5 8h14l-1 13H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
		spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 17l.7 1.8 1.8.7-1.8.7L19 22l-.7-1.8-1.8-.7 1.8-.7z"/>',
		coin: '<circle cx="12" cy="12" r="9"/><path d="M9 9h4a2 2 0 0 1 0 4H9V9zM9 13v4M9 15h4"/>',
		star: '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
		chat: '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12z"/>',
		home: '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		gift: '<rect x="3" y="8" width="18" height="5" rx="1"/><path d="M5 13v8h14v-8M12 8v13M12 8S9.5 3 7.5 4.5 9 8 12 8zm0 0s2.5-5 4.5-3.5S15 8 12 8z"/>'
	};
	var IC_WORDS = [[/予約|レッスン/, 'cal'], [/メニュー|コース|カタログ/, 'book'], [/クーポン|特典/, 'ticket'], [/スタンプ/, 'stamp'], [/お知らせ|ニュース/, 'bell'], [/アクセス|店舗|地図/, 'pin'], [/会員|カード/, 'card'], [/スタイル|カット|ヘア/, 'cut'], [/スタッフ|講師|先生/, 'user'], [/順番|時間|待ち/, 'clock'], [/問診|欠席|申込|申し込み/, 'clip'], [/ストア|ショップ|商品|通販/, 'bag'], [/新/, 'spark'], [/ポイント/, 'coin'], [/事例|口コミ|レビュー/, 'star'], [/問い合わせ|相談|チャット/, 'chat']];
	function ico(name) { return '<svg viewBox="0 0 24 24" aria-hidden="true">' + (IC[name] || IC.star) + '</svg>'; }
	function icoFor(word) { for (var i = 0; i < IC_WORDS.length; i++) { if (IC_WORDS[i][0].test(word)) { return ico(IC_WORDS[i][1]); } } return ico('star'); }
	function yen(p) { p = String(p || ''); return /^[\d,]+$/.test(p) ? '¥' + p : p; }
	function initial(t) { t = String(t || 'A').replace(/\s/g, ''); return esc(t.charAt(0)); }
	var STATUS = '<svg class="lp-sig" viewBox="0 0 18 12" aria-hidden="true"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="5" y="5.5" width="3" height="6.5" rx="1"/><rect x="10" y="3" width="3" height="9" rx="1"/><rect x="15" y="0" width="3" height="12" rx="1"/></svg>'
		+ '<svg class="lp-wifi" viewBox="0 0 16 12" aria-hidden="true"><path d="M8 11.5 5.6 9a3.4 3.4 0 0 1 4.8 0z"/><path d="M3.6 7a6.3 6.3 0 0 1 8.8 0l-1.3 1.3a4.5 4.5 0 0 0-6.2 0z"/><path d="M1.4 4.8a9.4 9.4 0 0 1 13.2 0l-1.3 1.3a7.6 7.6 0 0 0-10.6 0z"/></svg>'
		+ '<span class="lp-bat"><i></i></span>';

	/** リアルなスマホのアプリ画面（作った画面・見本で共通）。 */
	function phone(a, o) {
		o = o || {};
		var shop = a.shop || 'あなたのお店';
		var ph = a.photo || o.photo || '';
		var items = a.items || [];
		var news = a.news || [];
		var cpn = a.coupon || { tag: 'アプリ限定', title: '次回10%オフ' };
		var heroBg = ph ? 'background-image:url(' + esc(ph) + ')' : '';
		var pos = ['20% 45%', '55% 60%', '85% 40%'];
		var tabs = [['home', 'ホーム'], ['cal', '予約'], ['card', '会員証'], ['ticket', 'クーポン'], ['user', 'マイページ']];
		return '<div class="lp-wrap' + (o.mini ? ' mini' : '') + '">'
			+ (o.notif !== false && news.length ? '<div class="lp-notif"><i style="background:' + esc(a.color) + '">' + initial(shop) + '</i><div><p><b>' + esc(shop) + '</b><small>今</small></p><span>' + esc(news[0]) + '</span></div></div>' : '')
			+ '<div class="lp" style="--c:' + esc(a.color) + ';--s:' + esc(a.soft) + '"><span class="lp-k k1"></span><span class="lp-k k2"></span><span class="lp-k k3"></span>'
			+ '<div class="lp-scr"><div class="lp-island"></div><div class="lp-status"><b>9:41</b><span>' + STATUS + '</span></div>'
			+ '<div class="lp-scroll">'
			+ '<div class="lp-hero' + (ph ? '' : ' nophoto') + (o.loading ? ' loading' : '') + '" style="' + heroBg + '">'
			+ (ph ? '' : '<span class="lp-hero-ic">' + icoFor(a.menus && a.menus[1] || '') + '</span>')
			+ '<div class="lp-appbar"><i>' + initial(shop) + '</i><b>' + esc(shop) + '</b><span class="lp-bell">' + ico('bell') + '<em></em></span></div>'
			+ '<div class="lp-hero-t"><small>' + esc(a.label) + '</small><h3>' + esc(a.catch) + '</h3><span class="lp-cta">' + esc((a.menus && a.menus[0]) || '予約する') + '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></span></div>'
			+ (o.loading ? '<div class="lp-gen"><span></span>写真をAIで作っています…</div>' : '')
			+ '</div>'
			+ '<div class="lp-body">'
			+ '<div class="lp-mcard"><div><small>MEMBER\'S CARD</small><b>' + esc(shop) + '</b></div><div class="lp-pt"><b>1,280</b><small>pt</small></div><div class="lp-bar"><i></i></div><p>あと220ptで「' + esc(cpn.title) + '」</p></div>'
			+ '<div class="lp-grid">' + (a.menus || []).slice(0, 6).map(function (m) { return '<div><i>' + icoFor(m) + '</i><span>' + esc(m) + '</span></div>'; }).join('') + '</div>'
			+ '<div class="lp-cpn"><div><small>' + esc(cpn.tag) + '</small><b>' + esc(cpn.title) + '</b><em>有効期限：今月末まで</em></div><span>使う</span></div>'
			+ (items.length ? '<div class="lp-sec"><b>おすすめ</b><span>すべて見る</span></div><div class="lp-items">' + items.map(function (it, k) { return '<div><i class="' + (ph ? '' : 'nophoto') + '" style="' + (ph ? 'background-image:url(' + esc(ph) + ');background-position:' + pos[k % 3] : '') + '">' + (ph ? '' : icoFor(it.name)) + '</i><b>' + esc(it.name) + '</b><span>' + esc(yen(it.price)) + '</span></div>'; }).join('') + '</div>' : '')
			+ (news.length ? '<div class="lp-sec"><b>お知らせ</b><span>一覧</span></div><ul class="lp-news">' + news.map(function (n, k) { return '<li>' + (k === 0 ? '<em>NEW</em>' : '') + '<span>' + esc(n) + '</span><small>' + (k === 0 ? '今日' : '3日前') + '</small></li>'; }).join('') + '</ul>' : '')
			+ '</div></div>'
			+ '<div class="lp-tab">' + tabs.map(function (t, k) { return '<span class="' + (k === 0 ? 'on' : '') + '">' + ico(t[0]) + t[1] + '</span>'; }).join('') + '</div><div class="lp-home"></div>'
			+ '</div></div></div>';
	}
	// 見本（まだ作っていない人向け）。
	function sampleApp() {
		return { label: '飲食店', shop: '炭火焼肉 はなび', color: '#9f1d1d', soft: '#fdf1f1', catch: '炭火で焼く、特別なひととき', menus: ['ネット予約', 'メニュー', 'クーポン', 'スタンプ', 'お知らせ', 'アクセス'], items: [{ name: '上カルビ', price: '1,480' }, { name: '厚切り牛タン', price: '1,680' }, { name: 'はなび盛り', price: '3,980' }], coupon: { tag: 'アプリ限定', title: 'ドリンク1杯サービス' }, news: ['今月の限定メニューが始まりました', '週末のご予約はお早めに'], photo: C.img ? C.img + 'rn/03_case-restaurant.jpg' : '' };
	}
	function card(p, big) {
		return '<button type="button" class="lb-art' + (big ? ' big' : '') + '" data-art="' + p.id + '"><div class="lb-art-img"' + (p.thumb ? ' style="background-image:url(' + esc(p.thumb) + ')"' : '') + '>' + (p.video ? '<span class="lb-play" aria-hidden="true">▶</span>' : '') + '</div><div class="lb-art-t">' + (p.cat ? '<small>' + esc(p.cat) + (p.video ? '・動画' : '') + '</small>' : '') + '<b>' + esc(p.title) + '</b></div></button>';
	}
	var REAL = [['app-sample-golf-one.jpg', 'Golf One', 'ゴルフ・予約'], ['rn/real-zensin.webp', 'ZENSIN', '企業・社内'], ['app-sample-taka.jpg', 'TAKA', 'マッチング'], ['app-sample-legal-one.jpg', 'Legal One', '士業']];
	function vHome() {
		var nm = S.prof ? S.prof.displayName : (S.me.name || '');
		var arts = (S.posts || []).slice(0, 3);
		return '<div class="lb-hello">' + (S.prof && S.prof.pictureUrl ? '<img src="' + esc(S.prof.pictureUrl) + '" alt="">' : '<span class="lb-ava">' + ico('user') + '</span>') + '<div><small>ようこそ</small><b>' + (nm ? esc(nm) + ' さん' : 'APPREX LABOへ') + '</b></div></div>'
			+ '<section class="lb-hero"><div class="lb-hero-t"><span class="lb-badge">友だち限定・無料</span><h1>あなたのお店の<br>アプリ画面を、<em>AIで。</em></h1><p>業種と店名を入れるだけ。写真入りの、本物そっくりのアプリ画面ができます。</p><button type="button" class="lb-btn" data-go="make">' + (S.app ? '作った画面を見る' : 'いますぐ作ってみる') + '</button></div>'
			+ '<div class="lb-hero-ph">' + phone(S.app || sampleApp(), { mini: true, notif: false }) + '</div></section>'
			+ '<div class="lb-quick"><button type="button" data-go="learn"><i>' + ico('book') + '</i>学ぶ</button><button type="button" data-go="book"><i>' + ico('cal') + '</i>無料相談</button><a href="' + esc(C.cases) + '"><i>' + ico('star') + '</i>導入事例</a></div>'
			+ (C.img ? '<div class="lb-sh"><h2>APPREXで作ったアプリ</h2></div><div class="lb-real">' + REAL.map(function (r) { return '<figure><div class="lb-real-ph"><img src="' + esc(C.img + r[0]) + '" alt="' + esc(r[1]) + 'のアプリ画面" loading="lazy"></div><figcaption><b>' + esc(r[1]) + '</b><small>' + esc(r[2]) + '</small></figcaption></figure>'; }).join('') + '</div>' : '')
			+ (arts.length ? '<div class="lb-sh"><h2>新着の記事・動画</h2><button type="button" data-go="learn">すべて見る</button></div><div class="lb-list">' + arts.map(function (p) { return card(p, false); }).join('') + '</div>' : '');
	}
	var IND_IC = { food: 'book', beauty: 'cut', clinic: 'clip', school: 'layers', retail: 'bag', other: 'star' };
	var MSGS = ['お店の雰囲気を考えています…', 'メニューとクーポンを決めています…', 'アプリの画面をデザインしています…'];
	function vMake() {
		if (S.busy) {
			return '<div class="lb-making"><div class="lb-making-ph"><div class="lp-skel"></div></div><b id="lbMsg">' + MSGS[0] + '</b><p>20秒ほどかかります。このままお待ちください。</p></div>';
		}
		if (S.app && !S.remake) {
			return '<div class="lb-sh"><h1>' + esc(S.app.shop || 'あなたのお店') + 'のアプリ</h1><button type="button" data-act="remake">作り直す</button></div>'
				+ '<p class="lb-sub">画面の中はスクロールできます。' + (S.photoLoading ? '写真ができると自動で入ります。' : '') + '</p>'
				+ '<div class="lb-stage">' + phone(S.app, { loading: S.photoLoading }) + '</div>'
				+ '<div class="lb-card"><b>このアプリ、実際に作れます</b><p>作った画面のまま、担当者がオンラインでご説明します（無料）。ノーコードなら初期費用15万円＋月額19,800円〜。</p><button type="button" class="lb-btn" data-go="book">この画面で無料相談する</button><a class="lb-btn sub" href="' + esc(C.studio) + '">AIスタジオで続きを作る</a><button type="button" class="lb-btn sub" data-go="learn">まずは事例を見る</button></div>';
		}
		var inds = Object.keys(C.inds).map(function (k) { return '<button type="button" class="lb-chip ind' + (S.ind === k ? ' on' : '') + '" data-ind="' + k + '">' + ico(IND_IC[k] || 'star') + esc(C.inds[k]) + '</button>'; }).join('');
		var moods = ['高級感', 'かわいい', 'ナチュラル', '元気', '信頼感'].map(function (m) { return '<button type="button" class="lb-chip sm' + (S.mood === m ? ' on' : '') + '" data-mood="' + m + '">' + m + '</button>'; }).join('');
		return '<div><small class="lb-kicker">友だち限定の特典</small><h1 class="lb-h1">AIでアプリ画面を作る</h1><p class="lb-sub">写真もお店に合わせてAIが作ります。</p></div><div class="lb-card">'
			+ '<b><span class="lb-n">1</span>業種</b><div class="lb-chips three">' + inds + '</div>'
			+ '<label class="lb-f"><b><span class="lb-n">2</span>店名</b><input id="lbShop" value="' + esc(S.shop) + '" placeholder="例：炭火焼肉 はなび" maxlength="30"></label>'
			+ '<b><span class="lb-n">3</span>雰囲気<small>（任意）</small></b><div class="lb-chips">' + moods + '</div>'
			+ '<button type="button" class="lb-btn" data-act="make">AIで作る（無料）</button>'
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
		// スマホ画面の中をスクロールしたら、上の時計・電波を白地に。
		Array.prototype.forEach.call(main.querySelectorAll('.lp-scroll'), function (el) {
			el.addEventListener('scroll', function () { el.parentNode.classList.toggle('scrolled', el.scrollTop > 120); }, { passive: true });
		});
		if (S.busy && S.tab === 'make') { var k = 0; clearInterval(S.msgT); S.msgT = setInterval(function () { var m = document.getElementById('lbMsg'); if (!m || !S.busy) { clearInterval(S.msgT); return; } k = Math.min(k + 1, MSGS.length - 1); m.textContent = MSGS[k]; }, 3500); }
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
				S.busy = false;
				if (r && r.ok) { S.app = r.app; S.remake = false; S.photoLoading = !!r.photoToken; if (r.photoToken) { makePhoto(r.photoToken, r.app); } } else { S.err = (r && r.message) || '作れませんでした。'; }
				render(); window.scrollTo(0, 0);
			}).catch(function () { S.busy = false; S.err = '通信エラーが発生しました。'; render(); });
		}
	});
	function makePhoto(tok, app) {
		api('labo/photo', { token: tok }).then(function (r) {
			if (S.app !== app) { return; } // 作り直したあと。
			S.photoLoading = false;
			if (r && r.ok && r.photo) { S.app.photo = r.photo; }
			if (S.tab === 'make' || S.tab === 'home') { var y = window.scrollY; render(); window.scrollTo(0, y); }
		}).catch(function () { if (S.app === app) { S.photoLoading = false; render(); } });
	}
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
