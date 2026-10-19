/**
 * APPREX デモアプリ。
 * AIスタジオで作ったデザイン（APPREX_DEMO）を、実際に触れるアプリとして動かす。
 * 操作の結果（スタンプ・予約・クーポン使用など）はこの端末だけに保存されるデモ用の動きです。
 */
(function () {
	'use strict';

	var CFG = window.APPREX_DEMO || {};
	var DATA = window.APPREX_STUDIO_DATA || {};
	var IND = DATA.IND, COLORS = DATA.COLORS, TONES = DATA.TONES;
	var root = document.getElementById('demoRoot');
	if (!root || !IND) { return; }

	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	var SAMPLE = { food: ['luxury', 1], beauty: ['pop', 3], golf: ['sporty', 2], clinic: ['trust', 0], school: ['pop', 5], retail: ['natural', 2] };

	/* ---------- デザイン設定 ---------- */
	var d = CFG.d || null;
	if (!d || !IND[d.ind]) {
		var ind = (CFG.ind && IND[CFG.ind]) ? CFG.ind : 'food', sm = SAMPLE[ind];
		d = { ind: ind, name: '', tone: sm[0], color: sm[1], custom: null, variant: 'A', cols: 3, fs: 1, tall: false, resBig: true };
	}
	var base = IND[d.ind];
	var info = {
		l: base.l,
		name: (d.name || '').trim() || base.name,
		catch: (d.ov && d.ov.catch) || base.catch,
		menus: (d.ov && d.ov.menus && d.ov.menus.length) ? d.ov.menus : base.menus,
		items: base.items,
		cps: base.cps
	};
	var feat = CFG.feat || (function () { var f = {}; base.feat.forEach(function (k) { f[k] = true; }); return f; })();
	function shade(hex, k) { var n = parseInt(hex.slice(1), 16), r = n >> 16, g = n >> 8 & 255, b = n & 255; return '#' + ((1 << 24) + (Math.round(r * k) << 16) + (Math.round(g * k) << 8) + Math.round(b * k)).toString(16).slice(1); }
	var c2 = d.custom || (COLORS[d.color] || COLORS[1])[2];
	var c1 = d.custom ? shade(d.custom, 0.45) : (COLORS[d.color] || COLORS[1])[1];
	var tone = TONES[d.tone] || TONES.trust;
	var IMG = CFG.images || {};
	var hasStamp = !!(feat.stamp || feat.card);

	/* ---------- 端末に保存するデモの状態 ---------- */
	var KEY = 'apprex-demo-' + (CFG.url || '').replace(/[^a-z0-9]/gi, '').slice(-40);
	var st = { stamps: 3, points: 1250, used: {}, rewards: [], res: [], read: {}, pushed: false };
	try { var raw = localStorage.getItem(KEY); if (raw) { var o = JSON.parse(raw); for (var k in o) { st[k] = o[k]; } } } catch (e) {}
	function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }

	/* ---------- データ ---------- */
	var NEWS = [
		{ id: 'n1', t: info.cps[0][0] + 'のクーポンを配信中', b: info.cps[0][1] + '。クーポン画面からご利用ください。', date: ago(0), go: 'coupon' },
		{ id: 'n2', t: info.name + 'の公式アプリができました', b: 'アプリ会員の方だけの特典やお知らせをお届けします。' + (hasStamp ? 'ご来店ごとにスタンプが貯まります。' : ''), date: ago(3) },
		{ id: 'n3', t: '営業時間のお知らせ', b: '平日 11:00〜22:00 ／ 土日祝 10:00〜22:00（デモの表示です）', date: ago(8) }
	];
	function ago(n) { var t = new Date(Date.now() - n * 864e5); return (t.getMonth() + 1) + '/' + t.getDate(); }
	function coupons() {
		var list = info.cps.map(function (c, i) { return { id: 'c' + i, t: c[0], s: c[1], until: '今月末まで' }; });
		return list.concat(st.rewards.map(function (r) { return { id: r.id, t: r.t, s: 'スタンプ満了の特典', until: '発行から30日', reward: true }; }));
	}
	function action(label) {
		if (/予約|順番/.test(label)) { return feat.reserve === false ? 'info' : 'reserve'; }
		if (/クーポン/.test(label)) { return 'coupon'; }
		if (/スタンプ|ポイント|会員/.test(label)) { return 'card'; }
		if (/メニュー|お品書き|料金|コース|商品|ストア|プラン|スタイル|おすすめ|講師|新商品|案内/.test(label)) { return 'menu'; }
		if (/お知らせ|新着|ニュース|コンペ|イベント/.test(label)) { return 'news'; }
		if (/アクセス|地図|店舗|診療時間/.test(label)) { return 'access'; }
		return 'info';
	}
	var TABS = [['home', 'ホーム', '⌂']];
	TABS.push(feat.coupon !== false ? ['coupon', 'クーポン', '✂'] : ['menu', 'メニュー', '☰']);
	if (feat.reserve) { TABS.push(['reserve', '予約', '◷']); } else { TABS.push(['news', 'お知らせ', '✉']); }
	TABS.push(hasStamp ? ['card', '会員証', '▣'] : ['access', 'アクセス', '⌖']);
	TABS.push(['more', 'その他', '…']);

	/* ---------- 画面 ---------- */
	var cur = 'home', stack = [];
	function head(title, back) {
		var logo = IMG.logo ? '<img src="' + esc(IMG.logo) + '" alt="">' : esc(info.name.charAt(0));
		var unread = NEWS.filter(function (n) { return !st.read[n.id]; }).length;
		return '<header class="ah">' + (back ? '<button type="button" class="ah-back" data-act="back" aria-label="戻る">‹</button>' : '<span class="ah-logo">' + logo + '</span>')
			+ '<b>' + esc(title || info.name) + '</b><span class="ah-demo">DEMO</span>'
			+ '<button type="button" class="ah-bell" data-go="news" aria-label="お知らせ">🔔' + (unread ? '<i>' + unread + '</i>' : '') + '</button></header>';
	}
	function vHome() {
		var heroImg = IMG.photo || IMG.hero;
		var hero = '<div class="hero' + (d.tall || d.variant === 'C' ? ' tall' : '') + (heroImg ? ' photo' : '') + '"' + (heroImg ? ' style="background-image:url(' + esc(heroImg) + ')"' : '') + '><small>WELCOME</small><b>' + esc(info.catch) + '</b></div>';
		var res = (d.resBig && feat.reserve) ? '<button type="button" class="bigres" data-go="reserve">今すぐ予約する</button>' : '';
		var btns;
		if (d.variant === 'B') {
			btns = '<div class="mlist">' + info.menus.map(function (m) { return '<button type="button" class="mli" data-go="' + action(m) + '" data-label="' + esc(m) + '"><i>' + esc(m.charAt(0)) + '</i>' + esc(m) + '<span>›</span></button>'; }).join('') + '</div>';
		} else {
			var cols = d.variant === 'C' ? 2 : (d.cols || 3), menus = d.variant === 'C' ? info.menus.slice(0, 4) : info.menus;
			btns = '<div class="mgrid" style="grid-template-columns:repeat(' + cols + ',1fr)">' + menus.map(function (m) { return '<button type="button" class="mbtn" data-go="' + action(m) + '" data-label="' + esc(m) + '"><i>' + esc(m.charAt(0)) + '</i>' + esc(m) + '</button>'; }).join('') + '</div>';
		}
		var cp = coupons()[0];
		var teaser = cp ? '<button type="button" class="teaser" data-go="coupon"><b>' + esc(cp.t) + '</b><span>' + esc(cp.s) + '</span><em>クーポンを見る ›</em></button>' : '';
		var news = '<div class="sec-h">お知らせ</div>' + NEWS.slice(0, 2).map(newsRow).join('');
		return head() + '<div class="body">' + hero + res + btns + teaser + news + '</div>';
	}
	function newsRow(n) { return '<button type="button" class="nrow' + (st.read[n.id] ? '' : ' unread') + '" data-news="' + n.id + '"><time>' + n.date + '</time><span>' + esc(n.t) + '</span></button>'; }
	function vNews() { return head('お知らせ', stack.length) + '<div class="body">' + NEWS.map(newsRow).join('') + '</div>'; }
	function vCoupon() {
		var list = coupons().map(function (c) {
			var used = st.used[c.id];
			return '<div class="cpn' + (used ? ' used' : '') + (c.reward ? ' reward' : '') + '"><div><b>' + esc(c.t) + '</b><small>' + esc(c.s) + '　／　' + esc(c.until) + '</small></div>'
				+ (used ? '<em>使用済み</em>' : '<button type="button" data-use="' + c.id + '">使う</button>') + '</div>';
		}).join('');
		return head('クーポン', stack.length) + '<div class="body"><p class="lead">お会計の前に「使う」を押して、画面をスタッフに見せてください。</p>' + list + '<p class="note">新しいクーポンはプッシュ通知でお知らせします。</p></div>';
	}
	function vMenu() {
		return head('メニュー', stack.length) + '<div class="body">' + info.items.map(function (it, i) {
			return '<button type="button" class="item" data-item="' + i + '"><i style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></i><div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span></div><em>›</em></button>';
		}).join('') + '</div>';
	}
	var R = { day: 0, time: '', item: 0, done: false };
	function days() { var out = [], t = new Date(); t.setHours(0, 0, 0, 0); for (var i = 0; i < 7; i++) { var x = new Date(t); x.setDate(t.getDate() + i); out.push(x); } return out; }
	var WD = '日月火水木金土';
	function vReserve() {
		var ds = days();
		var dH = ds.map(function (x, i) { return '<button type="button" class="day' + (R.day === i ? ' on' : '') + '" data-day="' + i + '"><small>' + WD.charAt(x.getDay()) + '</small>' + x.getDate() + '</button>'; }).join('');
		var times = ['10:00', '11:00', '13:00', '14:00', '17:00', '18:00', '19:00'];
		var tH = times.map(function (t, i) { var full = ((R.day + i * 3) % 5) === 0; return '<button type="button" class="time' + (R.time === t ? ' on' : '') + '" data-time="' + t + '"' + (full ? ' disabled' : '') + '>' + t + (full ? '<small>満席</small>' : '') + '</button>'; }).join('');
		var opts = info.items.map(function (it, i) { return '<option value="' + i + '"' + (R.item === i ? ' selected' : '') + '>' + esc(it[0]) + '（' + esc(it[1]) + '）</option>'; }).join('');
		var mine = st.res.length ? '<div class="sec-h">ご予約</div>' + st.res.map(function (r, i) { return '<div class="resv"><div><b>' + esc(r.when) + '</b><small>' + esc(r.item) + '</small></div><button type="button" data-cancel="' + i + '">キャンセル</button></div>'; }).join('') : '';
		return head('ご予約', stack.length) + '<div class="body">' + mine + '<div class="sec-h">日付</div><div class="days">' + dH + '</div><div class="sec-h">時間</div><div class="times">' + tH + '</div>'
			+ '<div class="sec-h">メニュー</div><select class="sel" id="resItem">' + opts + '</select>'
			+ '<button type="button" class="cta" data-act="book"' + (R.time ? '' : ' disabled') + '>' + (R.time ? (ds[R.day].getMonth() + 1) + '/' + ds[R.day].getDate() + ' ' + R.time + 'で予約する' : '時間を選んでください') + '</button></div>';
	}
	function barcode(seed) {
		var x = 0, bars = '';
		for (var i = 0; i < 46; i++) { var w = ((seed * (i + 7)) % 3) + 1; if (i % 2 === 0) { bars += '<rect x="' + x + '" y="0" width="' + w + '" height="44"/>'; } x += w + 1; }
		return '<svg class="bc" viewBox="0 0 ' + x + ' 44" preserveAspectRatio="none" aria-hidden="true">' + bars + '</svg>';
	}
	function vCard() {
		var cells = '';
		for (var i = 0; i < 10; i++) { cells += '<span class="stp' + (i < st.stamps ? ' on' : '') + '">' + (i < st.stamps ? '★' : i + 1) + '</span>'; }
		var rank = st.points >= 3000 ? 'ゴールド' : st.points >= 1500 ? 'シルバー' : 'レギュラー';
		return head('会員証', stack.length) + '<div class="body"><div class="mcard"><div class="mc-top"><b>' + esc(info.name) + '</b><span>' + rank + '会員</span></div>'
			+ '<div class="mc-pt"><small>保有ポイント</small><strong>' + st.points.toLocaleString('ja-JP') + '</strong>pt</div>' + barcode(7) + '<small class="mc-no">No. 0001-2345-6789（デモ）</small></div>'
			+ (hasStamp ? '<div class="sec-h">スタンプカード（' + st.stamps + ' / 10）</div><div class="stamps">' + cells + '</div><p class="note">10個たまると特典クーポンをプレゼント。</p><button type="button" class="cta" data-act="stamp">来店スタンプを押す（デモ）</button><p class="note">本番では、お店のQRコードを読み取るとスタンプが押されます。</p>' : '') + '</div>';
	}
	function vAccess() {
		return head('アクセス', stack.length) + '<div class="body"><div class="map" aria-hidden="true"><span class="pin">📍</span></div><div class="kv"><b>' + esc(info.name) + '</b><span>〒000-0000 （デモの住所です）</span><span>営業時間 11:00〜22:00 ／ 定休日 なし</span></div>'
			+ '<button type="button" class="cta ghost" data-act="soon">地図アプリで開く</button><button type="button" class="cta ghost" data-act="soon">電話をかける</button></div>';
	}
	function vMore() {
		var perm = ('Notification' in window) ? Notification.permission : 'unsupported';
		return head('その他', stack.length) + '<div class="body"><div class="list">'
			+ '<button type="button" data-go="news">お知らせ<span>›</span></button>'
			+ '<button type="button" data-go="menu">メニュー<span>›</span></button>'
			+ '<button type="button" data-go="access">アクセス・営業時間<span>›</span></button>'
			+ (hasStamp ? '<button type="button" data-go="card">会員証・スタンプ<span>›</span></button>' : '')
			+ '</div><div class="sec-h">プッシュ通知</div><div class="list">'
			+ '<button type="button" data-act="pushDemo">通知のデモを受け取る<span>3秒後</span></button>'
			+ (perm !== 'unsupported' ? '<button type="button" data-act="pushReal">この端末に通知を送る<span>' + (perm === 'granted' ? '許可済み' : perm === 'denied' ? 'ブロック中' : '許可が必要') + '</span></button>' : '')
			+ '</div><div class="sec-h">デモ</div><div class="list"><button type="button" data-act="reset">デモの状態をリセット<span>›</span></button></div>'
			+ '<p class="note">これはAPPREXのデモアプリです。実際のアプリはiPhone・Androidのアプリとして制作し、App Store・Google Playで公開します。</p></div>';
	}
	var VIEWS = { home: vHome, news: vNews, coupon: vCoupon, menu: vMenu, reserve: vReserve, card: vCard, access: vAccess, more: vMore };

	/* ---------- 骨組み ---------- */
	var hostPhone = window.innerWidth >= 760;
	function shell() {
		var tabs = TABS.map(function (t) { return '<button type="button" class="tab" data-tab="' + t[0] + '"><i>' + t[2] + '</i>' + t[1] + '</button>'; }).join('');
		var app = '<div class="app" id="app" style="--c1:' + c1 + ';--c2:' + c2 + ';--r:' + tone.r + 'px;--ff:' + tone.ff + ';--fs:' + (d.fs || 1) + '">'
			+ '<div class="screen" id="screen"></div><nav class="tabs" aria-label="メニュー">' + tabs + '</nav>'
			+ '<div class="push" id="push" hidden></div><div class="sheet" id="sheet" hidden></div></div>';
		var side = '';
		if (hostPhone) {
			var inds = CFG.sample && CFG.inds ? '<div class="d-inds">' + Object.keys(CFG.inds).map(function (k) { return '<a href="?ind=' + k + '"' + (k === d.ind ? ' aria-current="page"' : '') + '>' + esc(CFG.inds[k]) + '</a>'; }).join('') + '</div>' : '';
			side = '<aside class="dinfo"><span class="d-badge">デモアプリ</span><h1>' + esc(info.name) + '</h1>'
				+ '<p>' + (CFG.sample ? 'APPREXで作れるアプリのサンプルです。ボタンを押して、実際の動きを試してください。' : 'AIスタジオで作ったデザインを、そのまま触れるアプリにしました。') + '</p>'
				+ inds + '<div class="d-qr"><div id="qr"></div><div><b>スマホで開く</b><span>カメラでQRコードを読み取ると、お手元のスマホで試せます。ホーム画面に追加すると、アプリのように全画面で使えます。</span></div></div>'
				+ '<ul class="d-try"><li>クーポンの「使う」</li><li>会員証で来店スタンプ</li>' + (feat.reserve ? '<li>日時を選んで予約</li>' : '') + '<li>その他 → 通知のデモ</li></ul>'
				+ '<a class="d-cta" href="' + esc(CFG.studioUrl) + '">' + (CFG.owner ? 'AIスタジオでデザインを直す' : 'AIスタジオで自分のアプリを作る（無料）') + '</a></aside>';
		}
		root.innerHTML = side + '<div class="dev' + (hostPhone ? ' framed' : '') + '">' + app + '</div>';
		if (hostPhone && window.qrcode) {
			try { var q = window.qrcode(0, 'M'); q.addData(CFG.url || location.href); q.make(); document.getElementById('qr').innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true }); } catch (e) {}
		}
	}
	function render() {
		var s = document.getElementById('screen');
		s.innerHTML = (VIEWS[cur] || vHome)();
		s.scrollTop = 0;
		document.querySelectorAll('#app .tab').forEach(function (t) { t.classList.toggle('on', t.dataset.tab === cur); });
	}
	function go(v, push) { if (push !== false && v !== cur) { stack.push(cur); } cur = v; if (v === 'reserve') { R.done = false; } render(); }
	function tab(v) { stack = []; cur = v; render(); }

	/* ---------- シート・通知 ---------- */
	var timer = null;
	function sheet(html) { var sh = document.getElementById('sheet'); sh.innerHTML = '<div class="sh-bg" data-act="close"></div><div class="sh-body" role="dialog">' + html + '</div>'; sh.hidden = false; }
	function closeSheet() { var sh = document.getElementById('sheet'); sh.hidden = true; sh.innerHTML = ''; clearInterval(timer); }
	function toast(title, body, goTo) {
		var p = document.getElementById('push');
		p.innerHTML = '<button type="button" class="push-in" data-push="' + (goTo || '') + '"><span class="p-ic">' + (IMG.icon ? '<img src="' + esc(IMG.icon) + '" alt="">' : esc(info.name.charAt(0))) + '</span><span><b>' + esc(title) + '</b><small>' + esc(body) + '</small></span><em>今</em></button>';
		p.hidden = false; void p.offsetWidth; p.classList.add('in');
		setTimeout(function () { p.classList.remove('in'); setTimeout(function () { p.hidden = true; }, 400); }, 6000);
	}
	function useCoupon(id) {
		var c = coupons().filter(function (x) { return x.id === id; })[0]; if (!c) { return; }
		var left = 600;
		sheet('<div class="use"><small>スタッフに画面を見せてください</small><b>' + esc(c.t) + '</b><span>' + esc(c.s) + '</span><div class="use-t" id="useT">10:00</div><button type="button" class="cta" data-done="' + id + '">使用済みにする</button><button type="button" class="cta ghost" data-act="close">閉じる</button></div>');
		timer = setInterval(function () { left--; var el = document.getElementById('useT'); if (!el || left < 0) { clearInterval(timer); return; } el.textContent = Math.floor(left / 60) + ':' + ('0' + left % 60).slice(-2); }, 1000);
	}

	/* ---------- 操作 ---------- */
	root.addEventListener('click', function (e) {
		var t = e.target.closest('button,a[data-go]'); if (!t || !root.contains(t)) { return; }
		var ds = t.dataset;
		if (ds.tab) { tab(ds.tab); return; }
		if (ds.go) {
			if (ds.go === 'info') { sheet('<div class="use"><b>' + esc(ds.label || '') + '</b><span>この画面の内容は、制作のときにお店に合わせて作り込みます（デモでは表示のみ）。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); return; }
			go(ds.go); return;
		}
		if (ds.news) {
			var n = NEWS.filter(function (x) { return x.id === ds.news; })[0]; st.read[n.id] = 1; save(); render();
			sheet('<div class="use left"><small>' + n.date + '</small><b>' + esc(n.t) + '</b><span>' + esc(n.b) + '</span>' + (n.go ? '<button type="button" class="cta" data-goclose="' + n.go + '">クーポンを見る</button>' : '') + '<button type="button" class="cta ghost" data-act="close">閉じる</button></div>');
			return;
		}
		if (ds.goclose) { closeSheet(); go(ds.goclose); return; }
		if (ds.item) { var it = info.items[+ds.item]; sheet('<div class="use left"><div class="ph-img" style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span>' + (feat.reserve ? '<button type="button" class="cta" data-goclose="reserve">このメニューで予約する</button>' : '') + '<button type="button" class="cta ghost" data-act="close">閉じる</button></div>'); R.item = +ds.item; return; }
		if (ds.use) { useCoupon(ds.use); return; }
		if (ds.done) { st.used[ds.done] = 1; save(); closeSheet(); render(); toast(info.name, 'クーポンを使用しました。ご利用ありがとうございます。'); return; }
		if (ds.day) { R.day = +ds.day; R.time = ''; render(); return; }
		if (ds.time) { R.time = ds.time; render(); return; }
		if (ds.cancel) { st.res.splice(+ds.cancel, 1); save(); render(); return; }
		if (t.classList.contains('push-in')) { var p = document.getElementById('push'); p.classList.remove('in'); p.hidden = true; if (ds.push) { go(ds.push); } return; }
		var a = ds.act;
		if (a === 'back') { cur = stack.pop() || 'home'; render(); }
		else if (a === 'close') { closeSheet(); }
		else if (a === 'book') {
			var x = days()[R.day], sel = document.getElementById('resItem'); if (sel) { R.item = +sel.value; }
			var when = (x.getMonth() + 1) + '月' + x.getDate() + '日(' + WD.charAt(x.getDay()) + ') ' + R.time;
			st.res.unshift({ when: when, item: info.items[R.item][0] }); save(); R.time = ''; render();
			sheet('<div class="use"><span class="ok">✓</span><b>ご予約を受け付けました</b><span>' + esc(when) + '<br>' + esc(info.items[R.item][0]) + '</span><span class="note">前日にプッシュ通知でお知らせします（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>');
		}
		else if (a === 'stamp') {
			st.stamps++; st.points += 100;
			if (st.stamps >= 10) { st.stamps = 0; st.rewards.push({ id: 'r' + Date.now(), t: '10スタンプ達成 特典クーポン' }); save(); render(); toast(info.name, 'スタンプが10個たまりました！特典クーポンをプレゼント', 'coupon'); }
			else { save(); render(); var cells = document.querySelectorAll('.stp.on'); if (cells.length) { cells[cells.length - 1].classList.add('pop'); } }
		}
		else if (a === 'pushDemo') { setTimeout(function () { toast(info.name, info.cps[0][0] + 'のクーポンが届きました。タップして確認', 'coupon'); }, 3000); t.querySelector('span').textContent = '送信しました'; }
		else if (a === 'pushReal') {
			Notification.requestPermission().then(function (p) {
				if (p === 'granted') { try { new Notification(info.name, { body: info.cps[0][0] + 'のクーポンが届きました（デモ）', icon: IMG.icon || '' }); } catch (err) { toast(info.name, 'この端末ではアプリ内の通知で表示します', 'coupon'); } }
				render();
			});
		}
		else if (a === 'reset') { if (window.confirm('スタンプ・予約・クーポンの状態を最初に戻します。')) { st = { stamps: 3, points: 1250, used: {}, rewards: [], res: [], read: {}, pushed: false }; save(); tab('home'); } }
		else if (a === 'soon') { sheet('<div class="use"><b>デモでは開けません</b><span>本番のアプリでは、地図アプリや電話につながります。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); }
	});
	root.addEventListener('change', function (e) { if (e.target.id === 'resItem') { R.item = +e.target.value; } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSheet(); } });

	shell();
	render();
	// 初回は少し後にプッシュ通知のデモを表示。
	if (!st.pushed) { st.pushed = true; save(); setTimeout(function () { toast(info.name, info.cps[0][0] + 'のクーポンが届きました。タップして確認', 'coupon'); }, 4000); }
})();
