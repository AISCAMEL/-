/**
 * APPREX デモアプリ。
 * AIスタジオで作ったデザインとメニュー構成（APPREX_DEMO）を、アプリの基盤（APPREX管理画面）と同じ
 * 「TOP画像＋アイコン／フッター横メニュー／縦メニュー」の形で、実際に触れるアプリとして動かす。
 * 操作の結果（スタンプ・予約・クーポン使用など）はこの端末だけに保存されるデモ用の動きです。
 */
(function () {
	'use strict';

	var CFG = window.APPREX_DEMO || {};
	var DATA = window.APPREX_STUDIO_DATA || {};
	var IND = DATA.IND, COLORS = DATA.COLORS, TONES = DATA.TONES, PAGES = DATA.PAGES;
	var root = document.getElementById('demoRoot');
	if (!root || !IND || !PAGES) { return; }

	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	var SAMPLE = { food: ['luxury', 1], beauty: ['pop', 3], golf: ['sporty', 2], clinic: ['trust', 0], school: ['pop', 5], retail: ['natural', 2] };

	/* ---------- デザインとメニュー構成 ---------- */
	var d = CFG.d || null;
	if (!d || !IND[d.ind]) {
		var ind0 = (CFG.ind && IND[CFG.ind]) ? CFG.ind : 'food', sm = SAMPLE[ind0];
		d = { ind: ind0, name: '', tone: sm[0], color: sm[1], custom: null, variant: 'A', cols: 3, fs: 1, tall: false, resBig: true };
	}
	var base = IND[d.ind];
	var app = (CFG.app && CFG.app.menu && CFG.app.menu.length) ? CFG.app : DATA.defaultApp(d.ind);
	app.menu = app.menu.filter(function (k) { return PAGES[k]; });
	app.footer = (app.footer || []).filter(function (k) { return k === 'home' || PAGES[k]; });
	var name = function (k) { return DATA.pageName(app, k); };
	var has = function (k) { return app.menu.indexOf(k) > -1; };
	var info = { l: base.l, name: (d.name || '').trim() || base.name, catch: (d.ov && d.ov.catch) || base.catch, items: base.items, cps: base.cps };
	function shade(hex, k) { var n = parseInt(hex.slice(1), 16), r = n >> 16, g = n >> 8 & 255, b = n & 255; return '#' + ((1 << 24) + (Math.round(r * k) << 16) + (Math.round(g * k) << 8) + Math.round(b * k)).toString(16).slice(1); }
	var c2 = d.custom || (COLORS[d.color] || COLORS[1])[2];
	var c1 = d.custom ? shade(d.custom, 0.45) : (COLORS[d.color] || COLORS[1])[1];
	var tone = TONES[d.tone] || TONES.trust;
	var IMG = CFG.images || {};
	var ICON = { web: '🌐', page: '📄', staff: '👤', menu: '🍽', history: '🕰', access: '📍', coupon: '🎟', card: '💳', rally: '🚩', friend: '🤝', form: '📝', reserve: '📅', shop: '🛍', fav: '♡', news: '📰', movie: '▶', gallery: '🖼', catalog: '📖', tel: '📞', mail: '✉', sns: '📷', chat: '💬', schedule: '🗓', skill: '⭐', info: 'ℹ' };
	var icon = function (k) { return k === 'home' ? '⌂' : (ICON[PAGES[k].type] || '•'); };

	/* ---------- 端末に保存するデモの状態 ---------- */
	var KEY = 'apprex-demo-' + (CFG.url || '').replace(/[^a-z0-9]/gi, '').slice(-40);
	var INIT = function () { return { stamps: 3, points: 1250, used: {}, rewards: [], res: [], read: {}, pushed: false, cart: [], fav: {}, rally: {}, member: false, chat: [] }; };
	var st = INIT();
	try { var raw = localStorage.getItem(KEY); if (raw) { var o = JSON.parse(raw); for (var k in o) { st[k] = o[k]; } } } catch (e) {}
	function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }

	/* ---------- データ ---------- */
	function ago(n) { var t = new Date(Date.now() - n * 864e5); return (t.getMonth() + 1) + '/' + t.getDate(); }
	var NEWS = [
		{ id: 'n1', t: info.cps[0][0] + 'のクーポンを配信中', b: info.cps[0][1] + '。クーポン画面からご利用ください。', date: ago(0), go: has('newcoupon') ? 'newcoupon' : '' },
		{ id: 'n2', t: info.name + 'の公式アプリができました', b: 'アプリ会員の方だけの特典やお知らせをお届けします。', date: ago(3) },
		{ id: 'n3', t: '営業時間のお知らせ', b: '平日 11:00〜22:00 ／ 土日祝 10:00〜22:00（デモの表示です）', date: ago(8) }
	];
	var BLOG = [
		{ id: 'b1', t: 'スタッフおすすめの楽しみ方', b: '人気のメニューと、より楽しんでいただくためのコツをご紹介します。', date: ago(2) },
		{ id: 'b2', t: '季節のおすすめが始まりました', b: '今だけの限定メニューをご用意しています。', date: ago(9) }
	];
	function coupons() {
		var list = info.cps.map(function (c, i) { return { id: 'c' + i, t: c[0], s: c[1], until: '今月末まで' }; });
		return list.concat(st.rewards.map(function (r) { return { id: r.id, t: r.t, s: '特典', until: '発行から30日', reward: true }; }));
	}
	var STAFF = [['店長', '佐藤', 'お客様に合わせたご提案が得意です。'], ['スタッフ', '鈴木', '丁寧な説明を心がけています。'], ['スタッフ', '高橋', '初めての方もお気軽にどうぞ。']];
	var price = function (s) { var m = String(s).replace(/,/g, '').match(/\d+/); return m ? +m[0] : 0; };
	var yen = function (n) { return n.toLocaleString('ja-JP') + '円'; };

	/* ---------- 画面の部品 ---------- */
	var cur = 'home', stack = [];
	function head(title, back) {
		var logo = IMG.logo ? '<img src="' + esc(IMG.logo) + '" alt="">' : esc(info.name.charAt(0));
		var newsKey = has('news') ? 'news' : (has('blog') ? 'blog' : '');
		var unread = newsKey === 'news' ? NEWS.filter(function (n) { return !st.read[n.id]; }).length : 0;
		return '<header class="ah">' + (back ? '<button type="button" class="ah-back" data-act="back" aria-label="戻る">‹</button>' : '<button type="button" class="ah-menu" data-act="drawer" aria-label="メニュー">☰</button>')
			+ (back ? '' : '<span class="ah-logo">' + logo + '</span>') + '<b>' + esc(title || info.name) + '</b><span class="ah-demo">DEMO</span>'
			+ (newsKey ? '<button type="button" class="ah-bell" data-go="' + newsKey + '" aria-label="お知らせ">🔔' + (unread ? '<i>' + unread + '</i>' : '') + '</button>' : '') + '</header>';
	}
	function wrap(k, body) { return head(name(k), stack.length) + '<div class="body">' + body + '</div>'; }
	var heroBg = function (tall) { var im = IMG.photo || IMG.hero; return '<div class="hero' + (tall ? ' tall' : '') + (im ? ' photo' : '') + '"' + (im ? ' style="background-image:url(' + esc(im) + ')"' : '') + '>'; };

	/* ---------- ホーム（TOP画像＋アイコン） ---------- */
	function vHome() {
		var hero = heroBg(d.tall || d.variant === 'C') + '<small>WELCOME</small><b>' + esc(info.catch) + '</b></div>';
		var res = (d.resBig && has('reser')) ? '<button type="button" class="bigres" data-go="reser">今すぐ' + esc(name('reser')) + 'する</button>' : '';
		var top = app.menu.slice(0, 6), btns;
		if (d.variant === 'B') {
			btns = '<div class="mlist">' + top.map(function (k) { return '<button type="button" class="mli" data-go="' + k + '"><i>' + icon(k) + '</i>' + esc(name(k)) + '<span>›</span></button>'; }).join('') + '</div>';
		} else {
			var cols = d.variant === 'C' ? 2 : (d.cols || 3), list = d.variant === 'C' ? top.slice(0, 4) : top;
			btns = '<div class="mgrid" style="grid-template-columns:repeat(' + cols + ',1fr)">' + list.map(function (k) { return '<button type="button" class="mbtn" data-go="' + k + '"><i>' + icon(k) + '</i>' + esc(name(k)) + '</button>'; }).join('') + '</div>';
		}
		var cp = coupons()[0];
		var teaser = (cp && has('newcoupon')) ? '<button type="button" class="teaser" data-go="newcoupon"><b>' + esc(cp.t) + '</b><span>' + esc(cp.s) + '</span><em>' + esc(name('newcoupon')) + 'を見る ›</em></button>' : '';
		var news = has('news') ? '<div class="sec-h">' + esc(name('news')) + '</div>' + NEWS.slice(0, 2).map(newsRow).join('') : '';
		var more = app.menu.length > 6 ? '<button type="button" class="allm" data-act="drawer">すべてのメニュー（' + app.menu.length + '）</button>' : '';
		return head() + '<div class="body">' + hero + res + btns + more + teaser + news + '</div>';
	}

	/* ---------- ページの種類ごとの画面 ---------- */
	function newsRow(n) { return '<button type="button" class="nrow' + (st.read[n.id] ? '' : ' unread') + '" data-news="' + n.id + '"><time>' + n.date + '</time><span>' + esc(n.t) + '</span></button>'; }
	var T = {};
	T.news = function (k) { var list = k === 'blog' ? BLOG : NEWS; return wrap(k, list.map(newsRow).join('')); };
	T.coupon = function (k) {
		return wrap(k, '<p class="lead">お会計の前に「使う」を押して、画面をスタッフに見せてください。</p>' + coupons().map(function (c) {
			var used = st.used[c.id];
			return '<div class="cpn' + (used ? ' used' : '') + (c.reward ? ' reward' : '') + '"><div><b>' + esc(c.t) + '</b><small>' + esc(c.s) + '　／　' + esc(c.until) + '</small></div>' + (used ? '<em>使用済み</em>' : '<button type="button" data-use="' + c.id + '">使う</button>') + '</div>';
		}).join('') + '<p class="note">新しいクーポンはプッシュ通知でお知らせします。</p>');
	};
	function barcode(seed) { var x = 0, bars = ''; for (var i = 0; i < 46; i++) { var w = ((seed * (i + 7)) % 3) + 1; if (i % 2 === 0) { bars += '<rect x="' + x + '" y="0" width="' + w + '" height="44"/>'; } x += w + 1; } return '<svg class="bc" viewBox="0 0 ' + x + ' 44" preserveAspectRatio="none" aria-hidden="true">' + bars + '</svg>'; }
	T.card = function (k) {
		var rank = st.points >= 3000 ? 'ゴールド' : st.points >= 1500 ? 'シルバー' : 'レギュラー';
		var card = '<div class="mcard"><div class="mc-top"><b>' + esc(info.name) + '</b><span>' + rank + '会員</span></div><div class="mc-pt"><small>保有ポイント</small><strong>' + st.points.toLocaleString('ja-JP') + '</strong>pt</div>' + barcode(7) + '<small class="mc-no">No. 0001-2345-6789（デモ）</small></div>';
		if (k === 'stamp') {
			var cells = ''; for (var i = 0; i < 10; i++) { cells += '<span class="stp' + (i < st.stamps ? ' on' : '') + '">' + (i < st.stamps ? '★' : i + 1) + '</span>'; }
			return wrap(k, '<div class="sec-h">スタンプカード（' + st.stamps + ' / 10）</div><div class="stamps">' + cells + '</div><p class="note">10個たまると特典クーポンをプレゼント。</p><button type="button" class="cta" data-act="stamp">来店スタンプを押す（デモ）</button><p class="note">本番では、お店のQRコードを読み取るとスタンプが押されます。</p>');
		}
		if (k === 'point') {
			return wrap(k, card + '<div class="sec-h">ポイント交換</div>' + [['500pt', 'ドリンク1杯'], ['1,000pt', '500円割引'], ['3,000pt', '特別メニュー']].map(function (x) { return '<div class="cpn"><div><b>' + x[1] + '</b><small>' + x[0] + 'で交換</small></div><button type="button" data-act="exchange" data-pt="' + price(x[0]) + '">交換</button></div>'; }).join('') + '<button type="button" class="cta ghost" data-act="addpt">ポイントを付ける（デモ +100pt）</button>');
		}
		return wrap(k, card + '<p class="note">お会計のときに会員証を見せると、ポイントが付きます。</p>');
	};
	T.reserve = function (k) {
		var ds = days(), dH = ds.map(function (x, i) { return '<button type="button" class="day' + (R.day === i ? ' on' : '') + '" data-day="' + i + '"><small>' + WD.charAt(x.getDay()) + '</small>' + x.getDate() + '</button>'; }).join('');
		var times = ['10:00', '11:00', '13:00', '14:00', '17:00', '18:00', '19:00'];
		var tH = times.map(function (t, i) { var full = ((R.day + i * 3) % 5) === 0; return '<button type="button" class="time' + (R.time === t ? ' on' : '') + '" data-time="' + t + '"' + (full ? ' disabled' : '') + '>' + t + (full ? '<small>満席</small>' : '') + '</button>'; }).join('');
		var opts = info.items.map(function (it, i) { return '<option value="' + i + '"' + (R.item === i ? ' selected' : '') + '>' + esc(it[0]) + '（' + esc(it[1]) + '）</option>'; }).join('');
		var mine = st.res.length ? '<div class="sec-h">ご予約</div>' + st.res.map(function (r, i) { return '<div class="resv"><div><b>' + esc(r.when) + '</b><small>' + esc(r.item) + '</small></div><button type="button" data-cancel="' + i + '">キャンセル</button></div>'; }).join('') : '';
		return wrap(k, mine + '<div class="sec-h">日付</div><div class="days">' + dH + '</div><div class="sec-h">時間</div><div class="times">' + tH + '</div><div class="sec-h">メニュー</div><select class="sel" id="resItem">' + opts + '</select><button type="button" class="cta" data-act="book"' + (R.time ? '' : ' disabled') + '>' + (R.time ? (ds[R.day].getMonth() + 1) + '/' + ds[R.day].getDate() + ' ' + R.time + 'で予約する' : '時間を選んでください') + '</button>');
	};
	T.menu = function (k) {
		var order = k === 'takeout';
		return wrap(k, (order ? '<p class="lead">商品を選んで、受け取り時間を指定して注文できます。</p>' : '') + info.items.map(function (it, i) {
			return '<div class="item"><i style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></i><div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span></div>'
				+ '<button type="button" class="fav' + (st.fav['i' + i] ? ' on' : '') + '" data-fav="i' + i + '" aria-label="お気に入り">' + (st.fav['i' + i] ? '♥' : '♡') + '</button>'
				+ (order ? '<button type="button" class="add" data-cart="' + i + '">追加</button>' : '<button type="button" class="more" data-item="' + i + '" aria-label="詳しく">›</button>') + '</div>';
		}).join('') + (order ? cartBar() : ''));
	};
	function cartBar() { var n = st.cart.length, sum = st.cart.reduce(function (a, i) { return a + price(info.items[i][1]); }, 0); return n ? '<button type="button" class="cta" data-act="checkout">注文内容を確認（' + n + '点・' + yen(sum) + '）</button>' : ''; }
	T.shop = function (k) {
		if (k === 'cart') {
			var sum = st.cart.reduce(function (a, i) { return a + price(info.items[i][1]); }, 0);
			return wrap(k, st.cart.length ? st.cart.map(function (i, j) { return '<div class="resv"><div><b>' + esc(info.items[i][0]) + '</b><small>' + esc(info.items[i][1]) + '</small></div><button type="button" data-uncart="' + j + '">削除</button></div>'; }).join('') + '<div class="kv"><b>合計 ' + yen(sum) + '</b><span>送料・決済方法は本番で設定します（デモ）</span></div><button type="button" class="cta" data-act="checkout">注文する</button>' : '<p class="lead">買い物カゴは空です。</p>');
		}
		return wrap(k, '<div class="pgrid">' + info.items.map(function (it, i) { return '<div class="prod"><i style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></i><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span><button type="button" data-cart="' + i + '">カートに入れる</button></div>'; }).join('') + '</div>' + cartBar());
	};
	T.fav = function (k) {
		var list = info.items.map(function (it, i) { return st.fav['i' + i] ? '<div class="item"><i style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></i><div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span></div><button type="button" class="fav on" data-fav="i' + i + '">♥</button></div>' : ''; }).join('');
		return wrap(k, list || '<p class="lead">メニューや商品の ♡ を押すと、ここに集まります。</p>');
	};
	var PAGE_TEXT = {
		company: [['会社名', info.name], ['所在地', '〒000-0000（デモの住所）'], ['設立', '2015年4月'], ['事業内容', info.l + 'の運営']],
		store: [['営業時間', '11:00〜22:00'], ['定休日', 'なし（年末年始を除く）'], ['席数・設備', '40席・駐車場あり'], ['お支払い', '現金・クレジットカード・電子マネー']],
		business: [['本店', '〒000-0000（デモ）／ 00-0000-0000'], ['駅前店', '〒000-0000（デモ）／ 00-0000-0000']]
	};
	T.page = function (k) {
		if (PAGE_TEXT[k]) { return wrap(k, heroBg(false) + '<b>' + esc(name(k)) + '</b></div><div class="kv tbl">' + PAGE_TEXT[k].map(function (r) { return '<div><small>' + esc(r[0]) + '</small><span>' + esc(r[1]) + '</span></div>'; }).join('') + '</div>'); }
		if (k === 'ceo') { return wrap(k, '<div class="ceo"><span class="ava">代</span><div><small>代表</small><b>山田 太郎</b></div></div><p class="txt">いつも' + esc(info.name) + 'をご利用いただき、ありがとうございます。アプリでは、お得な情報やご予約をもっと便利にお届けします。これからもよろしくお願いいたします。</p>'); }
		if (k === 'philo') { return wrap(k, '<div class="quote">「' + esc(info.catch) + '」</div>' + ['お客様の時間を大切にする', '地域とともに歩む', '毎日、少しずつ良くする'].map(function (t, i) { return '<div class="val"><b>' + (i + 1) + '</b><span>' + t + '</span></div>'; }).join('')); }
		if (k === 'intro') { return wrap(k, info.items.slice(0, 3).map(function (it) { return '<div class="item"><i style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></i><div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span></div></div>'; }).join('') + '<p class="note">事業・サービスの内容を、写真と文章で紹介するページです。</p>'); }
		return wrap(k, heroBg(false) + '<b>' + esc(name(k)) + '</b></div><p class="txt">このページは、管理画面で自由に文章や画像を配置して作れます（デモの表示です）。</p>');
	};
	T.staff = function (k) { return wrap(k, STAFF.map(function (s) { return '<div class="staff"><span class="ava">' + s[1].charAt(0) + '</span><div><small>' + s[0] + '</small><b>' + s[1] + '</b><span>' + s[2] + '</span></div>' + (has('reser') ? '<button type="button" data-go="reser">指名</button>' : '') + '</div>'; }).join('')); };
	T.history = function (k) { return wrap(k, '<ol class="tline">' + [['2015', '創業'], ['2018', 'リニューアル'], ['2022', '2号店オープン'], [String(new Date().getFullYear()), '公式アプリ公開']].map(function (r) { return '<li><b>' + r[0] + '</b><span>' + r[1] + '</span></li>'; }).join('') + '</ol>'); };
	T.access = function (k) { return wrap(k, '<div class="map" aria-hidden="true"><span class="pin">📍</span></div><div class="kv"><b>' + esc(info.name) + '</b><span>〒000-0000 （デモの住所です）</span><span>営業時間 11:00〜22:00</span></div><button type="button" class="cta ghost" data-act="soon">地図アプリで開く</button>'); };
	T.rally = function (k) {
		var spots = ['本店', '駅前店', '公園前', 'イベント会場', 'パートナー店'], done = spots.filter(function (s, i) { return st.rally['s' + i]; }).length;
		return wrap(k, '<p class="lead">5か所を回ってチェックインすると、特典がもらえます（' + done + ' / 5）。</p>' + spots.map(function (s, i) { var on = st.rally['s' + i]; return '<div class="resv"><div><b>' + (on ? '✅ ' : '') + s + '</b><small>' + (on ? 'チェックイン済み' : '位置情報またはQRでチェックイン') + '</small></div>' + (on ? '' : '<button type="button" data-rally="' + i + '">チェックイン</button>') + '</div>'; }).join(''));
	};
	T.friend = function (k) { return wrap(k, '<div class="mcard"><small>あなたの紹介コード</small><strong class="code">APP-' + (1000 + st.points % 9000) + '</strong><span>紹介した方・された方の両方に特典（デモ）</span></div><button type="button" class="cta" data-act="share">友達に送る</button>'); };
	var FORMS = {
		registration: [['お名前', 'text'], ['メールアドレス', 'email'], ['生年月日', 'date']],
		survey: [['ご来店のきっかけ', 'radio:SNS|ご紹介|通りがかり|その他'], ['満足度', 'radio:とても満足|満足|ふつう|不満'], ['ご意見', 'textarea']],
		application: [['お名前', 'text'], ['メールアドレス', 'email'], ['お申し込み内容', 'textarea']],
		inquiry: [['お名前', 'text'], ['メールアドレス', 'email'], ['お問い合わせ内容', 'textarea']]
	};
	T.form = function (k) {
		if (k === 'registration' && st.member) { return wrap(k, '<div class="use"><span class="ok">✓</span><b>会員登録済みです</b><span>会員限定のクーポンやポイントが使えます。</span></div>'); }
		var f = (FORMS[k] || FORMS.inquiry).map(function (x, i) {
			var t = x[1];
			if (t.indexOf('radio:') === 0) { return '<fieldset class="fs"><legend>' + x[0] + '</legend>' + t.slice(6).split('|').map(function (o) { return '<label><input type="radio" name="q' + i + '"> ' + o + '</label>'; }).join('') + '</fieldset>'; }
			return '<label class="fl"><span>' + x[0] + '</span>' + (t === 'textarea' ? '<textarea rows="3"></textarea>' : '<input type="' + t + '">') + '</label>';
		}).join('');
		return wrap(k, '<form class="dform" data-form="' + k + '">' + f + '<button type="submit" class="cta">' + (k === 'survey' ? '回答する' : k === 'registration' ? '登録する' : '送信する') + '</button><p class="note">デモのため、入力内容は送信されません。</p></form>');
	};
	T.movie = function (k) { return wrap(k, ['お店の紹介', 'おすすめの楽しみ方', 'スタッフ紹介'].map(function (t, i) { return '<button type="button" class="vid" data-act="play"><span class="vth" style="background:linear-gradient(135deg,' + c1 + ',' + c2 + ')"><i>▶</i></span><b>' + t + '</b><small>' + (2 + i) + ':' + (10 + i * 7) + '</small></button>'; }).join('')); };
	T.gallery = function (k) { var im = IMG.photo || IMG.hero; var tiles = ''; for (var i = 0; i < 9; i++) { tiles += '<button type="button" class="gt" data-act="photo" style="' + (im && i === 0 ? 'background-image:url(' + esc(im) + ')' : 'background:linear-gradient(' + (40 * i) + 'deg,' + c1 + ',' + c2 + ')') + '"></button>'; } return wrap(k, '<div class="gal">' + tiles + '</div>'); };
	var CAT = 0;
	T.catalog = function (k) { var pages = ['表紙：' + info.name, info.items[0][0] + ' / ' + info.items[1][0], info.items[2][0] + ' / ' + info.items[3][0], 'ご来店をお待ちしています']; return wrap(k, '<div class="book"><div class="bpage" style="background:linear-gradient(160deg,' + c1 + ',' + c2 + ')"><small>' + (CAT + 1) + ' / 4</small><b>' + esc(pages[CAT]) + '</b></div><div class="bnav"><button type="button" data-cat="-1"' + (CAT ? '' : ' disabled') + '>‹ 前へ</button><button type="button" data-cat="1"' + (CAT < 3 ? '' : ' disabled') + '>次へ ›</button></div></div><p class="note">PDFや画像から、めくれる電子カタログを作れます。</p>'); };
	T.web = function (k) { return wrap(k, '<div class="webv"><div class="url">https://（設定したWebページ）</div><div class="wbody"><b>' + esc(name(k)) + '</b><span>ホームページや予約サイトなど、指定したWebページをアプリの中に表示します。</span></div></div>'); };
	T.chat = function (k) {
		var msgs = [['staff', 'こんにちは。' + info.name + 'です。ご質問をどうぞ。']].concat(st.chat);
		return wrap(k, '<div class="chat">' + msgs.map(function (m) { return '<div class="cm ' + m[0] + '">' + esc(m[1]) + '</div>'; }).join('') + '</div><form class="cin" data-chat="1"><input type="text" placeholder="メッセージを入力" aria-label="メッセージ"><button type="submit">送信</button></form>');
	};
	T.schedule = function (k) {
		var dd = days().slice(0, 7), rows = ['10:00', '13:00', '17:00'];
		return wrap(k, '<div class="sched"><div class="sh-row head"><span></span>' + dd.map(function (x) { return '<span>' + WD.charAt(x.getDay()) + '<br>' + x.getDate() + '</span>'; }).join('') + '</div>' + rows.map(function (r, ri) { return '<div class="sh-row"><span>' + r + '</span>' + dd.map(function (x, i) { var on = (i + ri) % 3 !== 0; return '<span class="' + (on ? 'on' : '') + '">' + (on ? (k === 'attendance' ? STAFF[(i + ri) % 3][1].charAt(0) : '○') : '−') + '</span>'; }).join('') + '</div>'; }).join('') + '</div>');
	};
	T.skill = function (k) { return wrap(k, [['デザイン相談', '3,000円〜'], ['写真撮影', '5,000円〜'], ['SNS運用', '8,000円〜']].map(function (s, i) { return '<div class="staff"><span class="ava">' + STAFF[i][1].charAt(0) + '</span><div><small>' + STAFF[i][1] + '</small><b>' + s[0] + '</b><span>' + s[1] + '</span></div><button type="button" data-act="request">依頼</button></div>'; }).join('')); };

	function render() {
		var s = document.getElementById('screen'), h;
		if (cur === 'home') { h = vHome(); }
		else { var p = PAGES[cur]; var fn = p && T[p.type]; h = fn ? fn(cur) : vHome(); }
		s.innerHTML = h; s.scrollTop = 0;
		document.querySelectorAll('#app .tab').forEach(function (t) { t.classList.toggle('on', t.dataset.tab === cur); });
	}
	// 画面を開く（電話・メール・SNS・位置情報などはシートで案内）。
	function go(k) {
		var p = PAGES[k];
		if (k !== 'home' && !p) { return; }
		if (p && p.type === 'tel') { sheet('<div class="use"><b>' + esc(info.name) + 'に電話をかけますか？</b><span>00-0000-0000（デモ）</span><button type="button" class="cta" data-act="soon">電話をかける</button><button type="button" class="cta ghost" data-act="close">キャンセル</button></div>'); return; }
		if (p && p.type === 'mail') { sheet('<div class="use"><b>メールを作成します</b><span>メールアプリが開き、宛先が入った状態になります（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>'); return; }
		if (p && p.type === 'sns') { sheet('<div class="use"><b>' + esc(name(k)) + 'を開きます</b><span>設定したアカウントのページが開きます（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>'); return; }
		if (p && p.type === 'info') { sheet('<div class="use"><b>' + esc(name(k)) + '</b><span>お店の近くに来たお客様に、自動でお知らせを届けます（デモでは表示のみ）。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); return; }
		closeDrawer();
		if (k !== cur) { stack.push(cur); }
		cur = k; if (k === 'reser') { R.time = ''; } if (k === 'catalog') { CAT = 0; }
		render();
	}
	function tab(k) { var p = PAGES[k]; if (p && ['tel', 'mail', 'sns', 'info'].indexOf(p.type) > -1) { go(k); return; } stack = []; cur = k; render(); }

	/* ---------- 予約 ---------- */
	var R = { day: 0, time: '', item: 0 };
	var WD = '日月火水木金土';
	function days() { var out = [], t = new Date(); t.setHours(0, 0, 0, 0); for (var i = 0; i < 7; i++) { var x = new Date(t); x.setDate(t.getDate() + i); out.push(x); } return out; }

	/* ---------- 骨組み ---------- */
	var hostPhone = window.innerWidth >= 760;
	function shell() {
		var tabs = app.footer.slice(0, 4).map(function (k) { return '<button type="button" class="tab" data-tab="' + k + '"><i>' + icon(k) + '</i>' + esc(name(k)) + '</button>'; }).join('');
		var drawer = '<div class="drawer" id="drawer" hidden><div class="dr-bg" data-act="drawerClose"></div><nav class="dr-body" aria-label="縦メニュー"><div class="dr-h"><b>' + esc(info.name) + '</b><button type="button" data-act="drawerClose" aria-label="閉じる">×</button></div>'
			+ '<button type="button" class="dr-i" data-go="home"><i>⌂</i>ホーム</button>' + app.menu.map(function (k) { return '<button type="button" class="dr-i" data-go="' + k + '"><i>' + icon(k) + '</i>' + esc(name(k)) + '</button>'; }).join('')
			+ '<div class="dr-sep">デモ</div><button type="button" class="dr-i" data-act="pushDemo"><i>🔔</i>通知のデモを受け取る</button>'
			+ (('Notification' in window) ? '<button type="button" class="dr-i" data-act="pushReal"><i>📲</i>この端末に通知を送る</button>' : '')
			+ '<button type="button" class="dr-i" data-act="reset"><i>↺</i>デモの状態をリセット</button><p class="dr-note">これはAPPREXのデモアプリです。実際のアプリはiPhone・Androidのアプリとして制作し、ストアで公開します。</p></nav></div>';
		var appEl = '<div class="app" id="app" style="--c1:' + c1 + ';--c2:' + c2 + ';--r:' + tone.r + 'px;--ff:' + tone.ff + ';--fs:' + (d.fs || 1) + '">'
			+ '<div class="screen" id="screen"></div><nav class="tabs" aria-label="フッター横メニュー">' + tabs + '</nav>' + drawer
			+ '<div class="push" id="push" hidden></div><div class="sheet" id="sheet" hidden></div></div>';
		var side = '';
		if (hostPhone) {
			var inds = CFG.sample && CFG.inds ? '<div class="d-inds">' + Object.keys(CFG.inds).map(function (k) { return '<a href="?ind=' + k + '"' + (k === d.ind ? ' aria-current="page"' : '') + '>' + esc(CFG.inds[k]) + '</a>'; }).join('') + '</div>' : '';
			var pv = CFG.preview && (CFG.preview.ios || CFG.preview.android) ? '<div class="d-pv"><b>本番の画面は「プレビューアプリ」で</b><span>制作が始まると、管理画面で作ったアプリを実機で確認できます。</span><div>' + (CFG.preview.ios ? '<a href="' + esc(CFG.preview.ios) + '" target="_blank" rel="noopener">App Store</a>' : '') + (CFG.preview.android ? '<a href="' + esc(CFG.preview.android) + '" target="_blank" rel="noopener">Google Play</a>' : '') + '</div></div>' : '';
			side = '<aside class="dinfo"><span class="d-badge">デモアプリ</span><h1>' + esc(info.name) + '</h1>'
				+ '<p>' + (CFG.sample ? 'APPREXで作れるアプリのサンプルです。左上の ☰ で全メニュー、下のタブで主な画面に移れます。' : 'AIスタジオで作ったデザインとメニュー構成を、そのまま触れるアプリにしました。') + '</p>'
				+ inds + '<div class="d-qr"><div id="qr"></div><div><b>スマホで開く</b><span>カメラでQRコードを読み取ると、お手元のスマホで試せます。ホーム画面に追加すると、アプリのように全画面で使えます。</span></div></div>'
				+ '<ul class="d-try"><li>ホームのアイコン・☰ の縦メニュー</li><li>クーポンの「使う」・スタンプ・ポイント</li>' + (has('reser') ? '<li>日時を選んで予約</li>' : '') + '<li>☰ → 通知のデモ</li></ul>' + pv
				+ '<a class="d-cta" href="' + esc(CFG.studioUrl) + '">' + (CFG.owner ? 'AIスタジオでデザインを直す' : 'AIスタジオで自分のアプリを作る（無料）') + '</a></aside>';
		}
		root.innerHTML = side + '<div class="dev' + (hostPhone ? ' framed' : '') + '">' + appEl + '</div>';
		if (hostPhone && window.qrcode) {
			try { var q = window.qrcode(0, 'M'); q.addData(CFG.url || location.href); q.make(); document.getElementById('qr').innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true }); } catch (e) {}
		}
	}
	function openDrawer() { var dr = document.getElementById('drawer'); dr.hidden = false; void dr.offsetWidth; dr.classList.add('in'); }
	function closeDrawer() { var dr = document.getElementById('drawer'); if (!dr || dr.hidden) { return; } dr.classList.remove('in'); setTimeout(function () { dr.hidden = true; }, 250); }

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
	var couponKey = has('newcoupon') ? 'newcoupon' : '';
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
		if (ds.go) { go(ds.go); return; }
		if (ds.news) {
			var n = NEWS.concat(BLOG).filter(function (x) { return x.id === ds.news; })[0]; st.read[n.id] = 1; save(); render();
			sheet('<div class="use left"><small>' + n.date + '</small><b>' + esc(n.t) + '</b><span>' + esc(n.b) + '</span>' + (n.go ? '<button type="button" class="cta" data-goclose="' + n.go + '">クーポンを見る</button>' : '') + '<button type="button" class="cta ghost" data-act="close">閉じる</button></div>');
			return;
		}
		if (ds.goclose) { closeSheet(); go(ds.goclose); return; }
		if (ds.item) { var it = info.items[+ds.item]; R.item = +ds.item; sheet('<div class="use left"><div class="ph-img" style="background:linear-gradient(135deg,' + c2 + ',' + c1 + ')"></div><b>' + esc(it[0]) + '</b><span>' + esc(it[1]) + '</span>' + (has('reser') ? '<button type="button" class="cta" data-goclose="reser">このメニューで予約する</button>' : '') + '<button type="button" class="cta ghost" data-act="close">閉じる</button></div>'); return; }
		if (ds.use) { useCoupon(ds.use); return; }
		if (ds.done) { st.used[ds.done] = 1; save(); closeSheet(); render(); toast(info.name, 'クーポンを使用しました。ご利用ありがとうございます。'); return; }
		if (ds.day) { R.day = +ds.day; R.time = ''; render(); return; }
		if (ds.time) { R.time = ds.time; render(); return; }
		if (ds.cancel) { st.res.splice(+ds.cancel, 1); save(); render(); return; }
		if (ds.fav) { st.fav[ds.fav] = !st.fav[ds.fav]; save(); render(); return; }
		if (ds.cart) { st.cart.push(+ds.cart); save(); render(); toast(info.name, info.items[+ds.cart][0] + 'を追加しました'); return; }
		if (ds.uncart) { st.cart.splice(+ds.uncart, 1); save(); render(); return; }
		if (ds.rally) { st.rally['s' + ds.rally] = 1; var cnt = Object.keys(st.rally).length; if (cnt >= 5) { st.rewards.push({ id: 'r' + Date.now(), t: 'スタンプラリー達成 特典' }); toast(info.name, 'スタンプラリー達成！特典クーポンをプレゼント', couponKey); } save(); render(); return; }
		if (ds.cat) { CAT = Math.max(0, Math.min(3, CAT + (+ds.cat))); render(); return; }
		if (t.classList.contains('push-in')) { var p = document.getElementById('push'); p.classList.remove('in'); p.hidden = true; if (ds.push) { go(ds.push); } return; }
		var a = ds.act;
		if (a === 'back') { cur = stack.pop() || 'home'; render(); }
		else if (a === 'drawer') { openDrawer(); }
		else if (a === 'drawerClose') { closeDrawer(); }
		else if (a === 'close') { closeSheet(); }
		else if (a === 'book') {
			var x = days()[R.day], sel = document.getElementById('resItem'); if (sel) { R.item = +sel.value; }
			var when = (x.getMonth() + 1) + '月' + x.getDate() + '日(' + WD.charAt(x.getDay()) + ') ' + R.time;
			st.res.unshift({ when: when, item: info.items[R.item][0] }); save(); R.time = ''; render();
			sheet('<div class="use"><span class="ok">✓</span><b>ご予約を受け付けました</b><span>' + esc(when) + '<br>' + esc(info.items[R.item][0]) + '</span><span class="note">前日にプッシュ通知でお知らせします（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>');
		}
		else if (a === 'stamp') {
			st.stamps++; st.points += 100;
			if (st.stamps >= 10) { st.stamps = 0; st.rewards.push({ id: 'r' + Date.now(), t: '10スタンプ達成 特典クーポン' }); save(); render(); toast(info.name, 'スタンプが10個たまりました！特典クーポンをプレゼント', couponKey); }
			else { save(); render(); var cells = document.querySelectorAll('.stp.on'); if (cells.length) { cells[cells.length - 1].classList.add('pop'); } }
		}
		else if (a === 'addpt') { st.points += 100; save(); render(); }
		else if (a === 'exchange') { var pt = +ds.pt; if (st.points < pt) { sheet('<div class="use"><b>ポイントが足りません</b><span>あと' + (pt - st.points) + 'ptで交換できます。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); return; } st.points -= pt; st.rewards.push({ id: 'r' + Date.now(), t: 'ポイント交換：' + t.closest('.cpn').querySelector('b').textContent }); save(); render(); toast(info.name, 'ポイントを交換しました。クーポンに追加されています', couponKey); }
		else if (a === 'checkout') { if (!st.cart.length) { return; } st.cart = []; save(); render(); sheet('<div class="use"><span class="ok">✓</span><b>ご注文を受け付けました</b><span>準備ができたらプッシュ通知でお知らせします（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>'); }
		else if (a === 'share') { sheet('<div class="use"><b>友達に送る</b><span>LINE・メール・SNSで紹介コードを送れます（デモ）。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); }
		else if (a === 'play') { sheet('<div class="use"><b>動画を再生します</b><span>設定したYouTubeなどの動画がここで再生されます（デモ）。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); }
		else if (a === 'photo') { sheet('<div class="use"><div class="ph-img big" style="' + t.getAttribute('style') + '"></div><button type="button" class="cta ghost" data-act="close">閉じる</button></div>'); }
		else if (a === 'request') { sheet('<div class="use"><span class="ok">✓</span><b>依頼を送りました</b><span>提供者から返事が届くと通知でお知らせします（デモ）。</span><button type="button" class="cta" data-act="close">OK</button></div>'); }
		else if (a === 'pushDemo') { closeDrawer(); setTimeout(function () { toast(info.name, info.cps[0][0] + 'のクーポンが届きました。タップして確認', couponKey); }, 3000); }
		else if (a === 'pushReal') {
			Notification.requestPermission().then(function (perm) {
				if (perm === 'granted') { try { new Notification(info.name, { body: info.cps[0][0] + 'のクーポンが届きました（デモ）', icon: IMG.icon || '' }); } catch (err) { toast(info.name, 'この端末ではアプリ内の通知で表示します', couponKey); } }
				closeDrawer();
			});
		}
		else if (a === 'reset') { if (window.confirm('スタンプ・予約・クーポンなどの状態を最初に戻します。')) { st = INIT(); save(); closeDrawer(); tab('home'); } }
		else if (a === 'soon') { sheet('<div class="use"><b>デモでは開けません</b><span>本番のアプリでは、地図アプリや電話につながります。</span><button type="button" class="cta" data-act="close">閉じる</button></div>'); }
	});
	root.addEventListener('submit', function (e) {
		var f = e.target; e.preventDefault();
		if (f.dataset.chat) {
			var inp = f.querySelector('input'), v = inp.value.trim(); if (!v) { return; }
			st.chat.push(['me', v]); st.chat.push(['staff', 'ありがとうございます。担当者から順にお返事します（デモの自動返信）。']); save(); render();
			var c = document.querySelector('.chat'); if (c) { c.scrollTop = c.scrollHeight; }
			return;
		}
		if (f.dataset.form) {
			if (f.dataset.form === 'registration') { st.member = true; st.points += 300; save(); }
			render();
			sheet('<div class="use"><span class="ok">✓</span><b>' + (f.dataset.form === 'survey' ? 'ご回答ありがとうございました' : f.dataset.form === 'registration' ? '会員登録が完了しました' : '送信しました') + '</b><span>' + (f.dataset.form === 'registration' ? '登録特典として300ptをプレゼントしました（デモ）。' : 'デモのため、実際には送信されていません。') + '</span><button type="button" class="cta" data-act="close">OK</button></div>');
		}
	});
	root.addEventListener('change', function (e) { if (e.target.id === 'resItem') { R.item = +e.target.value; } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSheet(); closeDrawer(); } });

	shell();
	render();
	if (!st.pushed) { st.pushed = true; save(); setTimeout(function () { toast(info.name, info.cps[0][0] + 'のクーポンが届きました。タップして確認', couponKey); }, 4000); }
})();
