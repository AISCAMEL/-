/* =========================================================
   simulator.js — 乗り出し総額シミュレーション
   index.html のシミュレーターと、ヒーローカードの連動を担当。
   会員サイト(mypage)のオーダー見積もりからも再利用できるよう、
   計算ロジックは window.AucSim として公開する。
   ========================================================= */
(function () {
  "use strict";

  // --- クラス別の係数・固定費（税込ベースの参考値） ---
  // fee: 代行手数料 / recycle: リサイクル料等の預り目安
  // dealerMargin: 店頭購入時に上乗せされる中間コスト率（おトク額の試算に使用）
  var CLASS = {
    kei:     { label: "軽自動車",       fee: 30000, recycle: 12000, dealerMargin: 0.18 },
    compact: { label: "コンパクト",     fee: 40000, recycle: 18000, dealerMargin: 0.20 },
    sedan:   { label: "セダン/ミニバン", fee: 45000, recycle: 22000, dealerMargin: 0.22 },
    suv:     { label: "SUV/大型",       fee: 50000, recycle: 26000, dealerMargin: 0.23 },
    import:  { label: "輸入車",         fee: 69000, recycle: 35000, dealerMargin: 0.25 }
  };

  var AUCTION_FEE = 11000; // オークション落札料・出品取り扱い（一律目安）

  var OPTIONS = {
    optReg:     25000, // 名義変更・登録代行
    optGarage:  12000, // 車庫証明取得代行
    optInspect: 35000, // 12ヶ月点検・整備パック
    optWarranty:28000  // 6ヶ月保証パック
  };

  function yen(n) {
    return "¥" + Math.round(n).toLocaleString("ja-JP");
  }

  // 万円表記（おトク額バッジ用）
  function man(n) {
    var m = n / 10000;
    if (m >= 100) return "約 " + Math.round(m / 10) * 10 + "万円";
    return "約 " + Math.round(m) + "万円";
  }

  /**
   * 総額計算ロジック（再利用可能）
   * @param {object} p {bid, cls, region, options:{}}
   * @returns {object} 明細と合計
   */
  function calculate(p) {
    var c = CLASS[p.cls] || CLASS.compact;
    var bid = Number(p.bid) || 0;
    var region = Number(p.region) || 20000;

    var optTotal = 0;
    var opts = p.options || {};
    Object.keys(OPTIONS).forEach(function (k) {
      if (opts[k]) optTotal += OPTIONS[k];
    });

    var subtotal = bid + c.fee + AUCTION_FEE + c.recycle + region + optTotal;

    // 店頭購入時の想定総額との差額（おトク額）
    var dealerEstimate = bid * (1 + c.dealerMargin) + region * 0.5;
    var savings = Math.max(0, dealerEstimate - subtotal);

    return {
      classLabel: c.label,
      bid: bid,
      fee: c.fee,
      auctionFee: AUCTION_FEE,
      recycle: c.recycle,
      ship: region,
      options: optTotal,
      total: subtotal,
      savings: savings
    };
  }

  // window へ公開（mypage 等からの再利用用）
  window.AucSim = { calculate: calculate, yen: yen, CLASS: CLASS, OPTIONS: OPTIONS };

  // ===== ここから index.html 用の DOM 連動 =====
  var bid = document.getElementById("bid");
  if (!bid) return; // シミュレーターが無いページでは何もしない

  function readForm() {
    var clsEl = document.querySelector('input[name="cls"]:checked');
    return {
      bid: bid.value,
      cls: clsEl ? clsEl.value : "compact",
      region: document.getElementById("region").value,
      options: {
        optReg: document.getElementById("optReg").checked,
        optGarage: document.getElementById("optGarage").checked,
        optInspect: document.getElementById("optInspect").checked,
        optWarranty: document.getElementById("optWarranty").checked
      }
    };
  }

  function render() {
    var r = calculate(readForm());

    document.getElementById("bidOut").textContent = yen(r.bid);
    document.getElementById("rBid").textContent = yen(r.bid);
    document.getElementById("rFee").textContent = yen(r.fee);
    document.getElementById("feeTag").textContent = r.classLabel;
    document.getElementById("rAuc").textContent = yen(r.auctionFee);
    document.getElementById("rRecycle").textContent = yen(r.recycle);
    document.getElementById("rShip").textContent = yen(r.ship);
    document.getElementById("rOpt").textContent = yen(r.options);
    document.getElementById("rTotal").textContent = yen(r.total);
    document.getElementById("rSave").textContent = man(r.savings);

    // ヒーローカードも連動
    var hcBid = document.getElementById("hcBid");
    if (hcBid) {
      hcBid.textContent = yen(r.bid);
      document.getElementById("hcFee").textContent = yen(r.fee);
      document.getElementById("hcOther").textContent =
        yen(r.auctionFee + r.recycle + r.ship + r.options);
      document.getElementById("hcTotal").textContent = yen(r.total);
    }
  }

  // イベント登録
  ["input", "change"].forEach(function (ev) {
    document.getElementById("simulator").addEventListener(ev, render);
  });

  render();
})();
