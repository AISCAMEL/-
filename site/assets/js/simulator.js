/* =========================================================
   simulator.js — 乗り出し総額シミュレーション
   index.html のシミュレーターと、ヒーローカードの連動を担当。
   会員サイト(mypage)のオーダー見積もりからも再利用できるよう、
   計算ロジックは window.AucSim として公開する。
   ========================================================= */
(function () {
  "use strict";

  // --- 代行手数料：落札価格帯別の定額制（全国平均ベース・税込） ---
  // 国内代行業者の公開料金の中央値に合わせた価格帯別定額。
  // 300万円超は定額では割安になりすぎるため料率（3%）に切り替え。
  var FEE_TIERS = [
    { max: 500000,  fee: 39800 },  // 〜50万円
    { max: 1000000, fee: 49800 },  // 50〜100万円
    { max: 1500000, fee: 59800 },  // 100〜150万円
    { max: 2000000, fee: 69800 },  // 150〜200万円
    { max: 3000000, fee: 79800 }   // 200〜300万円
  ];
  var FEE_RATE_OVER = 0.03; // 300万円超は落札価格の3%

  function feeByBid(bid) {
    for (var i = 0; i < FEE_TIERS.length; i++) {
      if (bid < FEE_TIERS[i].max) return FEE_TIERS[i].fee;
    }
    return Math.round(bid * FEE_RATE_OVER); // 300万円〜
  }

  // 価格帯ラベル（例: "100〜150万円帯"）
  function feeBandLabel(bid) {
    var bounds = [0, 50, 100, 150, 200, 300]; // 万円
    for (var i = 0; i < FEE_TIERS.length; i++) {
      if (bid < FEE_TIERS[i].max) {
        return i === 0 ? "〜50万円帯" : bounds[i] + "〜" + bounds[i + 1] + "万円帯";
      }
    }
    return "300万円〜（3%）";
  }

  // --- クラス別の係数・固定費（税込ベースの参考値） ---
  // surcharge: 輸入車・大型の手数料割増 / recycle: リサイクル料等の預り目安
  // dealerMargin: 店頭購入時に上乗せされる中間コスト率（おトク額の試算に使用）
  var CLASS = {
    kei:     { label: "軽自動車",       surcharge: 0,     recycle: 12000, dealerMargin: 0.18 },
    compact: { label: "コンパクト",     surcharge: 0,     recycle: 18000, dealerMargin: 0.20 },
    sedan:   { label: "セダン/ミニバン", surcharge: 0,     recycle: 22000, dealerMargin: 0.22 },
    suv:     { label: "SUV/大型",       surcharge: 5000,  recycle: 26000, dealerMargin: 0.23 },
    import:  { label: "輸入車",         surcharge: 20000, recycle: 35000, dealerMargin: 0.25 }
  };

  var AUCTION_FEE = 11000; // オークション落札料・出品取り扱い（一律目安）

  var OPTIONS = {
    optReg:     25000, // 名義変更・登録代行
    optGarage:  12000, // 車庫証明取得代行
    optInspect: 35000, // 12ヶ月点検・整備パック
    optWarranty:28000, // 6ヶ月保証パック
    optNumber:  8000,  // 希望ナンバー取得
    optCoat:    44000, // ボディコーティング
    optDrive:   22000  // ドラレコ等 取付
  };

  /* =========================================================
     陸送シミュレーション
     出発エリア→納車エリアを地域ゾーンの距離で概算。
     ※[要確認] 実際の会場×エリアの料金表で最終調整。
     ========================================================= */
  var AREAS = {
    hokkaido: { label: "北海道",        zone: 0, ferry: true },
    tohoku:   { label: "東北",          zone: 1 },
    kanto:    { label: "関東",          zone: 2 },
    chubu:    { label: "中部・東海",     zone: 3 },
    kansai:   { label: "関西",          zone: 4 },
    chugoku:  { label: "中国",          zone: 5 },
    shikoku:  { label: "四国",          zone: 5 },
    kyushu:   { label: "九州",          zone: 6 },
    okinawa:  { label: "沖縄",          zone: 7, ferry: true }
  };
  var SHIP_BASE = 12000;       // 同一ゾーン内の基準
  var SHIP_PER_ZONE = 8000;    // ゾーン差1あたり加算
  var SHIP_FERRY = 40000;      // 北海道・沖縄の航送加算
  var SIZE_MULT = { kei: 0.85, compact: 1.0, sedan: 1.1, suv: 1.25, import: 1.3 };

  function estimateShipping(p) {
    var from = AREAS[p.from] || AREAS.kanto;
    var to = AREAS[p.to] || AREAS.kanto;
    var mult = SIZE_MULT[p.cls] || 1.0;
    var diff = Math.abs(from.zone - to.zone);
    var cost = SHIP_BASE + diff * SHIP_PER_ZONE;
    if (from.ferry || to.ferry) cost += SHIP_FERRY;
    cost = cost * mult;
    return {
      fromLabel: from.label, toLabel: to.label,
      cost: Math.round(cost / 1000) * 1000
    };
  }

  /* =========================================================
     落札・出品シミュレーション（売却側）
     想定落札価格をもとに、出品代行手数料を引いた「手取り」を概算。
     ※[要確認] 手数料率・成約料は市場調査で確定。
     ========================================================= */
  // 出品代行手数料：落札価格の2.5% と 定額¥22,000 の高い方（市場調査ベース）
  // 出典：出品代行料は「2万円 or 成約価格の2.5%の高い方」が一般的、出品料9千〜2万/週。
  var SELL_RATE = 0.025;       // 出品代行手数料：落札価格の2.5%
  var SELL_FEE_MIN = 22000;    // 手数料の下限（定額）
  var SELL_SETTLE = 11000;     // 出品料・成約料（会場）一律目安
  // 車両状態（評価点）による相場レンジ係数
  var COND = {
    good:   { label: "良好（評価点4.5以上）", lo: 0.95, hi: 1.10, adj: 1.03 },
    normal: { label: "標準（評価点3.5〜4）",  lo: 0.90, hi: 1.06, adj: 1.00 },
    low:    { label: "難あり（評価点3以下）",  lo: 0.80, hi: 0.98, adj: 0.92 }
  };

  function estimateSell(p) {
    var median = Number(p.median) || 0;     // 想定落札価格（中央値）
    var c = COND[p.cond] || COND.normal;
    var carrierIn = !!p.carrierIn;          // 代理搬入オプション
    var carrierFee = carrierIn ? 18000 : 0;

    var mid = median * c.adj;
    var low = Math.round(median * c.lo / 1000) * 1000;
    var high = Math.round(median * c.hi / 1000) * 1000;

    var sellFee = Math.max(SELL_FEE_MIN, Math.round(mid * SELL_RATE));
    var net = mid - sellFee - SELL_SETTLE - carrierFee;

    return {
      condLabel: c.label,
      rangeLow: low, rangeHigh: high, mid: Math.round(mid),
      sellFee: sellFee, settle: SELL_SETTLE, carrierFee: carrierFee,
      net: Math.round(net)
    };
  }

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

    var fee = feeByBid(bid) + c.surcharge; // 価格帯別定額 ＋ クラス割増
    var subtotal = bid + fee + AUCTION_FEE + c.recycle + region + optTotal;

    // 店頭購入時の想定総額との差額（おトク額）
    var dealerEstimate = bid * (1 + c.dealerMargin) + region * 0.5;
    var savings = Math.max(0, dealerEstimate - subtotal);

    return {
      classLabel: c.label,
      feeBand: feeBandLabel(bid),
      bid: bid,
      fee: fee,
      auctionFee: AUCTION_FEE,
      recycle: c.recycle,
      ship: region,
      options: optTotal,
      total: subtotal,
      savings: savings
    };
  }

  // window へ公開（mypage 等からの再利用用）
  window.AucSim = {
    calculate: calculate, feeByBid: feeByBid, yen: yen, man: man,
    CLASS: CLASS, OPTIONS: OPTIONS, FEE_TIERS: FEE_TIERS,
    AREAS: AREAS, estimateShipping: estimateShipping,
    COND: COND, estimateSell: estimateSell
  };

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
    document.getElementById("feeTag").textContent = r.feeBand + "・" + r.classLabel;
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
