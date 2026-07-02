/* =========================================================
   app.js — 会員サイト（デモ）
   バックエンドの代わりに localStorage を使用し、
   会員登録・ログイン・オーダー（注文）保存を再現する。
   ※ あくまでフロント挙動の確認用デモ。実運用ではAPI連携に置換。
   ========================================================= */
(function () {
  "use strict";

  var USER_KEY = "auc_user";
  var ORDER_KEY = "auc_orders";
  var QUOTE_KEY = "auc_quotes";

  /* =======================================================
     バックエンド送信設定
     GASのウェブアプリURLをここに貼り付けると、会員登録・
     かんたんオーダー・お問い合わせが自動でスプレッドシートへ
     登録され、LINE通知＋AI要約まで走ります。
     空欄のままでもサイトはデモとして動作します（localStorage）。
     ======================================================= */
  var ENDPOINT = ""; // 例: "https://script.google.com/macros/s/XXXX/exec"

  /**
   * GASへ送信（fire-and-forget）。
   * GASウェブアプリはCORSヘッダを返さないため no-cors で送信し、
   * 画面表示はlocalStorage側で完結させる（送信可否はサーバーのシートで確認）。
   */
  function sendToBackend(payload) {
    if (!ENDPOINT) return Promise.resolve({ ok: false, skipped: true });
    return fetch(ENDPOINT, {
      method: "POST",
      mode: "no-cors",
      headers: { "Content-Type": "text/plain;charset=utf-8" }, // プリフライト回避
      body: JSON.stringify(payload)
    }).then(function () { return { ok: true }; })
      .catch(function (e) { return { ok: false, error: String(e) }; });
  }

  function read(key, fallback) {
    try { return JSON.parse(localStorage.getItem(key)) || fallback; }
    catch (e) { return fallback; }
  }
  function write(key, val) { localStorage.setItem(key, JSON.stringify(val)); }

  // 初回ログイン時に、雰囲気を出すためのサンプル注文を投入（購入＋出品）
  function seedOrders() {
    if (read(ORDER_KEY, null)) return;
    write(ORDER_KEY, [
      {
        id: "OD-2041", kind: "buy",
        car: "トヨタ ヤリス 1.5 G (2021)",
        status: "shipping", total: 1614000, progress: 75,
        date: "2026-06-02",
        memo: "落札成立・陸送手配中。6/16頃ご納車予定。"
      },
      {
        id: "SL-3007", kind: "sell",
        car: "日産 セレナ ハイウェイスター (2019)",
        status: "listing", total: 1153000, progress: 45,
        date: "2026-06-08",
        memo: "出品票を発送しました。会場へのご搬入をお願いします。"
      },
      {
        id: "OD-1987", kind: "buy",
        car: "ホンダ N-BOX カスタム (2020)",
        status: "done", total: 1180000, progress: 100,
        date: "2026-04-18",
        memo: "ご納車完了。ありがとうございました。"
      }
    ]);
  }

  var RANK_KEY = "auc_rank";
  var COUPON_KEY = "auc_coupons";
  var REFERRAL_KEY = "auc_referrals";

  var RANKS = {
    bronze: { name: "ブロンズ", min: 0, discount: 0, next: "silver", nextMin: 3, icon: "🥉" },
    silver: { name: "シルバー", min: 3, discount: 5000, next: "gold", nextMin: 7, icon: "🥈" },
    gold:   { name: "ゴールド", min: 7, discount: 15000, next: null, nextMin: null, icon: "🥇" }
  };

  function generateReferralCode() {
    var chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    var code = "AUC-";
    for (var i = 0; i < 6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    return code;
  }

  function calcRank(completedOrders) {
    if (completedOrders >= 7) return "gold";
    if (completedOrders >= 3) return "silver";
    return "bronze";
  }

  function ensureRankData() {
    var data = read(RANK_KEY, null);
    if (!data) {
      data = {
        rank: "bronze",
        completedOrders: 0,
        referralCode: generateReferralCode(),
        totalReferrals: 0,
        referredBy: null
      };
      write(RANK_KEY, data);
    }
    if (!data.referralCode) {
      data.referralCode = generateReferralCode();
      write(RANK_KEY, data);
    }
    return data;
  }

  function ensureCoupons() {
    return read(COUPON_KEY, []);
  }

  window.AucConfig = { endpoint: ENDPOINT, send: sendToBackend };

  window.AucAuth = {
    register: function (u) {
      var user = { name: u.name || "ゲスト会員", email: u.email || "", plan: u.plan || "スタンダード", since: "2026-06-13" };
      write(USER_KEY, user);
      seedOrders();
      sendToBackend({ type: "register", name: user.name, email: user.email, plan: user.plan, source: "LP" });
      return user;
    },
    login: function (email) {
      var existing = read(USER_KEY, null);
      if (!existing) {
        existing = { name: "ゲスト会員", email: email || "", plan: "スタンダード", since: "2026-06-13" };
        write(USER_KEY, existing);
      }
      seedOrders();
      return existing;
    },
    logout: function () { localStorage.removeItem(USER_KEY); },
    current: function () { return read(USER_KEY, null); },
    orders: function () { return read(ORDER_KEY, []); },
    addOrder: function (o) {
      var orders = read(ORDER_KEY, []);
      var sell = o.kind === "sell";
      o.id = (sell ? "SL-" : "OD-") + (2050 + orders.length);
      o.kind = o.kind || "buy";
      o.status = sell ? "apply" : "bid";
      o.progress = 20;
      o.date = new Date().toISOString().slice(0, 10);
      orders.unshift(o);
      write(ORDER_KEY, orders);
      var user = read(USER_KEY, {}) || {};
      // type: 購入=order / 出品=sell
      sendToBackend(Object.assign(
        { type: sell ? "sell" : "order", name: user.name, email: user.email, plan: user.plan },
        o.payload || {}, { kind: o.kind, car: o.car, total: o.total }
      ));
      return o;
    },
    // 相場見積り（買取/仕入れ）：メール/チャットで対応 → マイページに反映
    quotes: function () { return read(QUOTE_KEY, []); },
    addQuote: function (q) {
      var list = read(QUOTE_KEY, []);
      q.id = "QT-" + (4000 + list.length);
      q.status = "回答待ち";   // 回答待ち → 回答済み（スタッフが相場を回答）
      q.value = null;          // 回答後に相場額が入る
      q.date = new Date().toISOString().slice(0, 10);
      list.unshift(q);
      write(QUOTE_KEY, list);
      var user = read(USER_KEY, {}) || {};
      sendToBackend(Object.assign(
        { type: "quote", name: user.name, email: user.email },
        q
      ));
      return q;
    },
    // 会員ランク・紹介クーポン
    getRankData: function () {
      return ensureRankData();
    },
    getRankInfo: function () {
      var data = ensureRankData();
      var orders = read(ORDER_KEY, []);
      var completed = orders.filter(function (o) { return o.status === "done" || o.status === "sold"; }).length;
      data.completedOrders = completed;
      data.rank = calcRank(completed);
      write(RANK_KEY, data);
      var info = RANKS[data.rank];
      var nextInfo = info.next ? RANKS[info.next] : null;
      return {
        rank: data.rank,
        name: info.name,
        icon: info.icon,
        discount: info.discount,
        completedOrders: completed,
        nextRank: nextInfo ? nextInfo.name : null,
        nextMin: info.nextMin,
        progress: nextInfo ? Math.min(100, Math.round((completed / info.nextMin) * 100)) : 100,
        referralCode: data.referralCode,
        totalReferrals: data.totalReferrals
      };
    },
    getReferralCode: function () {
      return ensureRankData().referralCode;
    },
    applyReferralCode: function (code) {
      var data = ensureRankData();
      if (data.referredBy) return { ok: false, error: "既に紹介コードが適用されています" };
      if (code === data.referralCode) return { ok: false, error: "自分の紹介コードは使用できません" };
      data.referredBy = code;
      write(RANK_KEY, data);
      var coupons = ensureCoupons();
      coupons.unshift({
        id: "CP-" + (9000 + coupons.length),
        name: "紹介特典クーポン",
        desc: "紹介コード「" + code + "」適用",
        value: 5000,
        type: "discount",
        used: false,
        date: new Date().toISOString().slice(0, 10)
      });
      write(COUPON_KEY, coupons);
      sendToBackend({ type: "referral", code: code, email: (read(USER_KEY, {}) || {}).email });
      return { ok: true, coupon: coupons[0] };
    },
    addReferral: function () {
      var data = ensureRankData();
      data.totalReferrals = (data.totalReferrals || 0) + 1;
      write(RANK_KEY, data);
      var coupons = ensureCoupons();
      coupons.unshift({
        id: "CP-" + (9000 + coupons.length),
        name: "紹介ありがとうクーポン",
        desc: "お友達紹介の特典（" + data.totalReferrals + "人目）",
        value: 3000,
        type: "discount",
        used: false,
        date: new Date().toISOString().slice(0, 10)
      });
      write(COUPON_KEY, coupons);
    },
    getCoupons: function () {
      return ensureCoupons();
    },
    useCoupon: function (couponId) {
      var coupons = ensureCoupons();
      var found = false;
      coupons.forEach(function (c) {
        if (c.id === couponId && !c.used) { c.used = true; found = true; }
      });
      if (found) write(COUPON_KEY, coupons);
      return found;
    },
    RANKS: RANKS,

    // バックエンド（スタッフ/Slack回答）の相場額をローカルの依頼に反映
    applyQuoteAnswers: function (answers) {
      if (!answers || !answers.length) return false;
      var list = read(QUOTE_KEY, []);
      var byId = {};
      answers.forEach(function (a) { byId[a.id] = a; });
      var changed = false;
      list.forEach(function (q) {
        var a = byId[q.id];
        if (a && (q.value !== a.value || q.status !== "回答済み")) {
          q.value = a.value; q.status = "回答済み"; changed = true;
        }
      });
      if (changed) write(QUOTE_KEY, list);
      return changed;
    }
  };
})();
