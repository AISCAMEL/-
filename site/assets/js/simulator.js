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
    optDrive:   22000, // ドラレコ等 取付
    optShaken:  79800  // 車検整備（車検なし車両の点検整備／法定費用別）
  };

  // 内外装クリーニング：車種サイズ別
  var CLEAN_BY_CLASS = {
    kei: 12000, compact: 16000, sedan: 22000, suv: 26000, import: 30000
  };

  /* =========================================================
     陸送シミュレーション（都道府県＋距離ベース）
     ※ 株式会社ゼロ（ZERO）「マイカー陸送」のドアtoドア料金水準に準拠した概算。
        各都道府県に列島の南北軸の概算位置(km)を割り当て、距離=|軸差|を
        距離区分の料金へ変換。北海道・沖縄は航送加算。
        正確な区間料金はゼロ社の料金検索（zero-group.co.jp/mycar）に準拠して確定する。
     ========================================================= */
  // axis: 札幌方面を起点とした概算ロード位置(km) / ferry: 航送区分
  var PREF = {
    "北海道": { axis: 0,    ferry: "hokkaido" },
    "青森": { axis: 780 }, "岩手": { axis: 880 }, "秋田": { axis: 920 },
    "宮城": { axis: 1000 }, "山形": { axis: 1030 }, "福島": { axis: 1120 },
    "新潟": { axis: 1150 }, "茨城": { axis: 1230 }, "栃木": { axis: 1230 },
    "群馬": { axis: 1260 }, "埼玉": { axis: 1280 }, "千葉": { axis: 1300 },
    "東京": { axis: 1300 }, "神奈川": { axis: 1320 }, "山梨": { axis: 1380 },
    "長野": { axis: 1370 }, "静岡": { axis: 1400 }, "富山": { axis: 1450 },
    "石川": { axis: 1480 }, "岐阜": { axis: 1480 }, "愛知": { axis: 1500 },
    "福井": { axis: 1520 }, "三重": { axis: 1560 }, "滋賀": { axis: 1600 },
    "奈良": { axis: 1680 }, "京都": { axis: 1640 }, "大阪": { axis: 1700 },
    "兵庫": { axis: 1720 }, "和歌山": { axis: 1740 }, "岡山": { axis: 1820 },
    "鳥取": { axis: 1850 }, "徳島": { axis: 1800 }, "香川": { axis: 1820 },
    "広島": { axis: 1950 }, "島根": { axis: 1950 }, "高知": { axis: 1920 },
    "愛媛": { axis: 1950 }, "山口": { axis: 2080 }, "福岡": { axis: 2200 },
    "佐賀": { axis: 2250 }, "大分": { axis: 2280 }, "熊本": { axis: 2300 },
    "長崎": { axis: 2330 }, "宮崎": { axis: 2400 }, "鹿児島": { axis: 2480 },
    "沖縄": { axis: 3500, ferry: "okinawa" }
  };
  // 距離区分→料金（ゼロ社マイカー陸送＝ドアtoドア・普通車の水準に準拠）
  var SHIP_KM_TIERS = [
    { km: 30,   fee: 18000 },
    { km: 80,   fee: 24000 },
    { km: 150,  fee: 30000 },
    { km: 300,  fee: 42000 },
    { km: 550,  fee: 65000 },
    { km: 800,  fee: 82000 },
    { km: 1200, fee: 98000 },
    { km: 1800, fee: 120000 }
  ];
  var SHIP_KM_OVER = 150000;       // 1800km超
  var SHIP_FERRY_HOKKAIDO = 25000; // 青函航送 加算
  var SHIP_FERRY_OKINAWA = 55000;  // 沖縄航送 加算
  // 車種サイズ係数（距離主体のため差は小さめ）
  var SIZE_MULT = { kei: 0.9, compact: 1.0, sedan: 1.05, suv: 1.15, import: 1.2 };

  var HOME_PREF = "福島"; // 事業拠点（陸送の出発地）

  function shipFeeByKm(km) {
    for (var i = 0; i < SHIP_KM_TIERS.length; i++) {
      if (km <= SHIP_KM_TIERS[i].km) return SHIP_KM_TIERS[i].fee;
    }
    return SHIP_KM_OVER;
  }

  function estimateShipping(p) {
    var from = PREF[p.from] || PREF["東京"];
    var to = PREF[p.to] || PREF["東京"];
    var fromName = PREF[p.from] ? p.from : "東京";
    var toName = PREF[p.to] ? p.to : "東京";
    var mult = SIZE_MULT[p.cls] || 1.0;

    var km = Math.abs(from.axis - to.axis);
    var cost = shipFeeByKm(km);
    if (from.ferry === "hokkaido" || to.ferry === "hokkaido") cost += SHIP_FERRY_HOKKAIDO;
    if (from.ferry === "okinawa" || to.ferry === "okinawa") cost += SHIP_FERRY_OKINAWA;
    cost = cost * mult;

    return {
      fromLabel: fromName, toLabel: toName,
      km: km,
      cost: Math.round(cost / 1000) * 1000
    };
  }

  /* =========================================================
     落札・出品シミュレーション（売却側）
     想定落札価格をもとに、出品代行手数料を引いた「手取り」を概算。
     ※[要確認] 手数料率・成約料は市場調査で確定。
     ========================================================= */
  // 出品代行手数料：購入代行と同じ価格帯別の定額（¥39,800スタート）
  var SELL_TIERS = [
    { max: 500000,  fee: 39800 },  // 〜50万円
    { max: 1000000, fee: 49800 },  // 50〜100万円
    { max: 1500000, fee: 59800 },  // 100〜150万円
    { max: 2000000, fee: 69800 },  // 150〜200万円
    { max: 3000000, fee: 79800 }   // 200〜300万円
  ];
  var SELL_OVER_RATE = 0.03;   // 300万円超は落札価格の3%
  var SELL_SETTLE = 11000;     // 出品料・成約料（会場）一律目安
  var SELL_RELIST = 0;         // 流札・再出品料：無料

  function sellFeeByPrice(p) {
    for (var i = 0; i < SELL_TIERS.length; i++) {
      if (p < SELL_TIERS[i].max) return SELL_TIERS[i].fee;
    }
    return Math.round(p * SELL_OVER_RATE); // 300万円超
  }
  function sellBandLabel(p) {
    var t = [50, 100, 150, 200, 300]; // 万円
    for (var i = 0; i < SELL_TIERS.length; i++) {
      if (p < SELL_TIERS[i].max) return i === 0 ? "〜50万円" : t[i - 1] + "〜" + t[i] + "万円";
    }
    return "300万円超（3%）";
  }
  // 車両状態（評価点）による相場レンジ係数
  var COND = {
    good:   { label: "良好（評価点4.5以上）", lo: 0.95, hi: 1.10, adj: 1.03 },
    normal: { label: "標準（評価点3.5〜4）",  lo: 0.90, hi: 1.06, adj: 1.00 },
    low:    { label: "難あり（評価点3以下）",  lo: 0.80, hi: 0.98, adj: 0.92 }
  };

  function estimateSell(p) {
    var median = Number(p.median) || 0;     // 想定落札価格（手数料帯の基準）
    var c = COND[p.cond] || COND.normal;
    var carrierFee = p.carrierIn ? 18000 : 0;          // 代理搬入
    var shakenFee = p.shakenIn ? OPTIONS.optShaken : 0; // 車検整備（車検なし）
    var cleanFee = p.cleanIn ? (CLEAN_BY_CLASS[p.cls] || CLEAN_BY_CLASS.compact) : 0; // 内外装クリーニング（サイズ別）
    var optTotal = carrierFee + shakenFee + cleanFee;

    // 相場レンジ（状態による振れ幅の目安）
    var low = Math.round(median * c.lo / 1000) * 1000;
    var high = Math.round(median * c.hi / 1000) * 1000;

    var sellFee = sellFeeByPrice(median);   // 価格帯別の定額
    var net = median - sellFee - SELL_SETTLE - optTotal;

    return {
      condLabel: c.label,
      band: sellBandLabel(median),
      rangeLow: low, rangeHigh: high, mid: median,
      sellFee: sellFee, settle: SELL_SETTLE,
      carrierFee: carrierFee, shakenFee: shakenFee, cleanFee: cleanFee, optTotal: optTotal,
      relist: SELL_RELIST,
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
   * オートローン 月々支払い（元利均等）
   * @param {number} principal 借入元金（円）
   * @param {number} ratePct 実質年率（%）
   * @param {number} months 支払回数
   */
  function loanMonthly(principal, ratePct, months) {
    principal = Number(principal) || 0;
    months = Number(months) || 1;
    var r = (Number(ratePct) || 0) / 100 / 12;
    var m = r === 0 ? principal / months : principal * r / (1 - Math.pow(1 + r, -months));
    return {
      monthly: Math.round(m),
      total: Math.round(m) * months,
      interest: Math.round(m) * months - principal
    };
  }

  // 元利均等の1回あたり支払額（端数処理前の実数）
  function annuity(principal, ratePerPeriod, periods) {
    if (periods <= 0) return 0;
    if (ratePerPeriod === 0) return principal / periods;
    return principal * ratePerPeriod / (1 - Math.pow(1 + ratePerPeriod, -periods));
  }

  /**
   * 返済シミュレーション（頭金・ボーナス払い・返済予定表つき）
   *
   * 元金を「月払い分」と「ボーナス払い分」に分け、それぞれ元利均等で計算する。
   * ボーナスは半年ごと（6ヶ月周期）に支払う一般的なオートローンの方式に準拠。
   *
   * @param {object} p
   *   @param {number} p.price     車両価格／総額（円）
   *   @param {number} p.down      頭金（円）
   *   @param {number} p.ratePct   実質年率（%）
   *   @param {number} p.months    支払回数（月）
   *   @param {number} p.bonus     ボーナス払いに充当する元金（円・任意）
   * @returns {object} 明細・返済予定表
   */
  function loanSimulate(p) {
    var price = Math.max(0, Number(p.price) || 0);
    var down = Math.min(price, Math.max(0, Number(p.down) || 0));
    var months = Math.max(1, Math.round(Number(p.months) || 1));
    var rate = Math.max(0, Number(p.ratePct) || 0);
    var rMonthly = rate / 100 / 12;
    var rBonus = rate / 100 / 2; // 半年（6ヶ月）あたり

    var principal = price - down;            // 借入元金
    var bonusPrincipal = Math.min(principal, Math.max(0, Number(p.bonus) || 0));
    var monthlyPrincipal = principal - bonusPrincipal;

    var bonusCount = Math.floor(months / 6); // ボーナス支払い回数
    if (bonusCount === 0) { monthlyPrincipal = principal; bonusPrincipal = 0; }

    var monthlyPay = Math.round(annuity(monthlyPrincipal, rMonthly, months));
    var bonusPay = bonusCount > 0 ? Math.round(annuity(bonusPrincipal, rBonus, bonusCount)) : 0;

    var monthlyTotal = monthlyPay * months;
    var bonusTotal = bonusPay * bonusCount;
    var totalPay = monthlyTotal + bonusTotal;
    var interest = totalPay - principal;

    // 年ごとの返済予定表（残高推移）を生成
    var schedule = [];
    var balMonthly = monthlyPrincipal;
    var balBonus = bonusPrincipal;
    var years = Math.ceil(months / 12);
    var paidCum = 0;
    for (var y = 1; y <= years; y++) {
      var yearPaid = 0;
      for (var mo = (y - 1) * 12 + 1; mo <= Math.min(y * 12, months); mo++) {
        // 月払い分の元金充当
        var iM = balMonthly * rMonthly;
        var pM = Math.min(balMonthly, monthlyPay - iM);
        balMonthly = Math.max(0, balMonthly - pM);
        yearPaid += monthlyPay;
        // ボーナス月（6の倍数）はボーナス分も充当
        if (bonusCount > 0 && mo % 6 === 0) {
          var iB = balBonus * rBonus;
          var pB = Math.min(balBonus, bonusPay - iB);
          balBonus = Math.max(0, balBonus - pB);
          yearPaid += bonusPay;
        }
      }
      paidCum += yearPaid;
      // 端数処理の累積で最終回にわずかな残差が出るため、完済年は残高0に丸める
      var bal = (y === years) ? 0 : Math.max(0, Math.round(balMonthly + balBonus));
      schedule.push({
        year: y,
        paid: Math.round(yearPaid),
        paidCum: Math.round(paidCum),
        balance: bal
      });
    }

    return {
      price: price,
      down: down,
      principal: principal,
      months: months,
      ratePct: rate,
      monthly: monthlyPay,
      bonus: bonusPay,
      bonusCount: bonusCount,
      bonusPrincipal: bonusPrincipal,
      total: totalPay,
      interest: interest,
      schedule: schedule
    };
  }

  /* =========================================================
     借入目安診断（いくら借りられる？）
     年収・雇用形態・勤続年数/月数・他社借入から、返済負担率
     （DTI）ベースで「借入可能額の目安」と「仮申込金額（仮）」、
     その金額での月々返済額の目安を算出する。
     ※ あくまで目安。実際の与信は提携信販会社（オリコ）の審査による。
     ========================================================= */
  // 雇用形態別の返済負担率（年間返済 ÷ 年収）の上限目安
  var DTI_BY_JOB = {
    "正社員": 0.35, "法人": 0.33, "個人事業": 0.30, "契約": 0.28, "その他": 0.25
  };
  // 勤続年数（年）による補正係数
  function tenureFactor(totalYears) {
    if (totalYears < 1) return 0.70;
    if (totalYears < 2) return 0.85;
    if (totalYears < 3) return 0.95;
    if (totalYears < 5) return 1.00;
    return 1.05;
  }

  /**
   * 借入可能額の目安を算出
   * @param {object} p
   *   @param {number} p.income      年収（万円）
   *   @param {string} p.job         雇用形態キー（正社員/法人/個人事業/契約/その他）
   *   @param {number} p.years       勤続年数（年）
   *   @param {number} p.months      勤続月数（0〜11）
   *   @param {number} p.otherMonthly 他社借入の月々返済（円）
   *   @param {number} p.termMonths  希望返済期間（月）
   *   @param {number} p.ratePct     実質年率（%）
   */
  function borrowCapacity(p) {
    var incomeYen = Math.max(0, Number(p.income) || 0) * 10000;
    var job = p.job || "正社員";
    var years = Math.max(0, Number(p.years) || 0);
    var months = Math.max(0, Math.min(11, Number(p.months) || 0));
    var otherMonthly = Math.max(0, Number(p.otherMonthly) || 0);
    var termMonths = Math.max(1, Math.round(Number(p.termMonths) || 60));
    var rate = Math.max(0, Number(p.ratePct) || 0);

    var dti = DTI_BY_JOB[job] || 0.25;
    var tYears = years + months / 12;
    var tf = tenureFactor(tYears);

    var annualCap = incomeYen * dti * tf;               // 年間の返済上限目安
    var netAnnual = Math.max(0, annualCap - otherMonthly * 12); // 他社返済を控除
    var monthlyCap = netAnnual / 12;                    // 月々の返済上限目安

    // 月々返済上限から、期間・金利に応じた借入可能元金を逆算（元利均等の現価）
    var r = rate / 100 / 12;
    var borrowable = r === 0 ? monthlyCap * termMonths
      : monthlyCap * (1 - Math.pow(1 + r, -termMonths)) / r;

    var CAP_MAX = 10000000; // 上限（シミュレーターの上限に合わせる）
    borrowable = Math.min(Math.max(0, borrowable), CAP_MAX);

    // 仮申込金額（仮）＝10万円単位に切り下げ
    var provisional = Math.floor(borrowable / 100000) * 100000;
    var conservative = Math.floor(borrowable * 0.8 / 100000) * 100000; // 保守的な下限目安

    // 仮申込金額での月々返済額（元利均等）
    var monthlyPay = provisional > 0 ? Math.round(annuity(provisional, r, termMonths)) : 0;

    return {
      income: incomeYen,
      job: job,
      tenureYears: tYears,
      dti: dti,
      tenureFactor: tf,
      annualCap: Math.round(annualCap),
      netAnnual: Math.round(netAnnual),
      monthlyCap: Math.round(monthlyCap),
      borrowable: Math.round(borrowable),
      conservative: conservative,
      provisional: provisional,
      monthlyPay: monthlyPay,
      termMonths: termMonths,
      ratePct: rate,
      eligible: incomeYen > 0 && tYears >= 0.5 && provisional > 0
    };
  }

  /**
   * 総額計算ロジック（再利用可能）
   * @param {object} p {bid, cls, region, options:{}}
   * @returns {object} 明細と合計
   */
  function calculate(p) {
    var c = CLASS[p.cls] || CLASS.compact;
    var bid = Number(p.bid) || 0;
    var region = Number(p.region);            // 0（ゼロ陸送）も有効値
    if (isNaN(region)) region = 20000;

    var optTotal = 0;
    var opts = p.options || {};
    Object.keys(OPTIONS).forEach(function (k) {
      if (opts[k]) optTotal += OPTIONS[k];
    });
    // 内外装クリーニングは車種サイズ別
    if (opts.optClean) optTotal += CLEAN_BY_CLASS[p.cls] || CLEAN_BY_CLASS.compact;

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
    CLASS: CLASS, OPTIONS: OPTIONS, CLEAN_BY_CLASS: CLEAN_BY_CLASS, FEE_TIERS: FEE_TIERS,
    loanMonthly: loanMonthly, loanSimulate: loanSimulate, borrowCapacity: borrowCapacity,
    AREAS: PREF, PREF: PREF, HOME_PREF: HOME_PREF, estimateShipping: estimateShipping,
    COND: COND, estimateSell: estimateSell,
    SELL_TIERS: SELL_TIERS, sellFeeByPrice: sellFeeByPrice
  };

  // ===== ここから index.html 用の DOM 連動 =====
  var bid = document.getElementById("bid");
  if (!bid) return; // シミュレーターが無いページでは何もしない

  // 納車先（都道府県）を福島拠点からの配送として生成
  var regionSel = document.getElementById("region");
  if (regionSel && !regionSel.options.length) {
    Object.keys(PREF).forEach(function (k) {
      var o = document.createElement("option");
      o.value = k; o.textContent = k;
      regionSel.appendChild(o);
    });
    regionSel.value = "福島";
  }

  function readForm() {
    var clsEl = document.querySelector('input[name="cls"]:checked');
    var cls = clsEl ? clsEl.value : "compact";
    var dest = regionSel ? regionSel.value : "福島";
    var ship = estimateShipping({ from: HOME_PREF, to: dest, cls: cls }).cost;
    return {
      bid: bid.value,
      cls: cls,
      region: ship,
      options: {
        optReg: document.getElementById("optReg").checked,
        optGarage: document.getElementById("optGarage").checked,
        optInspect: document.getElementById("optInspect").checked,
        optWarranty: document.getElementById("optWarranty").checked,
        optShaken: document.getElementById("optShaken").checked,
        optClean: document.getElementById("optClean").checked
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
