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

  function read(key, fallback) {
    try { return JSON.parse(localStorage.getItem(key)) || fallback; }
    catch (e) { return fallback; }
  }
  function write(key, val) { localStorage.setItem(key, JSON.stringify(val)); }

  // 初回ログイン時に、雰囲気を出すためのサンプル注文を投入
  function seedOrders() {
    if (read(ORDER_KEY, null)) return;
    write(ORDER_KEY, [
      {
        id: "OD-2041",
        car: "トヨタ ヤリス 1.5 G (2021)",
        status: "shipping",
        total: 1614000,
        progress: 75,
        date: "2026-06-02",
        memo: "落札成立・陸送手配中。6/16頃ご納車予定。"
      },
      {
        id: "OD-1987",
        car: "ホンダ N-BOX カスタム (2020)",
        status: "done",
        total: 1180000,
        progress: 100,
        date: "2026-04-18",
        memo: "ご納車完了。ありがとうございました。"
      }
    ]);
  }

  window.AucAuth = {
    register: function (u) {
      var user = { name: u.name || "ゲスト会員", email: u.email || "", plan: u.plan || "スタンダード", since: "2026-06-13" };
      write(USER_KEY, user);
      seedOrders();
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
      o.id = "OD-" + (2050 + orders.length);
      o.status = "bid";
      o.progress = 20;
      o.date = new Date().toISOString().slice(0, 10);
      orders.unshift(o);
      write(ORDER_KEY, orders);
      return o;
    }
  };
})();
