/* 実挙動検証：jsdom で各HTMLを読み込みインラインJSを実行して結果を検証
   外部<script src>は順序通りにインライン展開してから解析（読み込み順の保証）。 */
const fs = require("fs");
const path = require("path");
const { JSDOM, VirtualConsole } = require("jsdom");

const SITE = require("path").join(__dirname, "..");
let pass = 0, fail = 0;
function ok(name, cond, extra) {
  if (cond) { pass++; console.log("  ✓ " + name); }
  else { fail++; console.log("  ✗ " + name + (extra ? "  → " + extra : "")); }
}

function inlineScripts(html) {
  return html.replace(/<script\s+src="([^"]+)"\s*><\/script>/g, (m, src) => {
    const p = path.join(SITE, src);
    if (!fs.existsSync(p)) return m;
    const code = fs.readFileSync(p, "utf8").replace(/<\/script>/g, "<\\/script>");
    return "<script>\n" + code + "\n</script>";
  });
}

function load(file, { storage } = {}) {
  const html = inlineScripts(fs.readFileSync(path.join(SITE, file), "utf8"));
  const vc = new VirtualConsole();
  let pageError = null;
  vc.on("jsdomError", e => {
    // ナビゲーション未実装は想定内（未ログイン時のlocation遷移）
    if (!/Not implemented:.*navigation/.test(e.message)) pageError = e.message;
  });
  const dom = new JSDOM(html, {
    runScripts: "dangerously",
    url: "https://example.com/" + file,
    virtualConsole: vc,
    beforeParse(win) {
      if (storage) for (const k in storage) win.localStorage.setItem(k, storage[k]);
      win.fetch = () => Promise.resolve({ ok: true });
      win.HTMLElement.prototype.scrollIntoView = () => {};
    }
  });
  return { win: dom.window, doc: dom.window.document, getError: () => pageError };
}

function fire(win, el, type) { el.dispatchEvent(new win.Event(type, { bubbles: true })); }

(() => {
  console.log("index.html（購入総額シミュ）");
  {
    const { win, doc, getError } = load("index.html");
    ok("AucSim 公開", !!win.AucSim);
    const bid = doc.getElementById("bid");
    bid.value = "1400000"; fire(win, bid, "input");
    ok("総額が計算される (#rTotal)", /¥1,5\d\d,\d00/.test(doc.getElementById("rTotal").textContent), doc.getElementById("rTotal").textContent);
    ok("おトク額が表示", /万円/.test(doc.getElementById("rSave").textContent));
    // 納車先で陸送費が変わる（ゼロ社水準）
    doc.getElementById("region").value = "大阪"; fire(win, doc.getElementById("region"), "change");
    ok("納車先で陸送費が反映 (#rShip)", doc.getElementById("rShip").textContent === "¥82,000", doc.getElementById("rShip").textContent);
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("sell.html（出品手取りシミュ）");
  {
    const { win, doc, getError } = load("sell.html");
    const med = doc.getElementById("sMed");
    med.value = "1200000"; fire(win, med, "input");
    ok("手取りが計算される (#sNet=¥1,129,200)", doc.getElementById("sNet").textContent === "¥1,129,200", doc.getElementById("sNet").textContent);
    ok("相場レンジ表示", /〜/.test(doc.getElementById("sRange").textContent));
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("shipping.html（陸送シミュ）");
  {
    const { win, doc, getError } = load("shipping.html");
    doc.getElementById("from").value = "東京";
    doc.getElementById("to").value = "北海道";
    doc.querySelector('input[name="cls"][value="suv"]').checked = true;
    fire(win, doc.getElementById("to"), "change");
    ok("陸送費が計算される (東京→北海道/SUV=¥167,000)", doc.getElementById("rCost").textContent === "¥167,000", doc.getElementById("rCost").textContent);
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("loan.html（返済シミュ）");
  {
    const { win, doc, getError } = load("loan.html");
    ok("AucSim.loanSimulate 公開", !!(win.AucSim && win.AucSim.loanSimulate));
    // 150万円・36回・6.9%・頭金/ボーナスなし → 月々は約46,200円前後
    doc.getElementById("price").value = "1500000";
    doc.getElementById("down").value = "0";
    doc.getElementById("bonus").value = "0";
    fire(win, doc.getElementById("price"), "input");
    ok("月々返済額が計算される (#rMonthly)", /¥4[0-9],\d{3}/.test(doc.getElementById("rMonthly").textContent), doc.getElementById("rMonthly").textContent);
    ok("借入元金=車両価格（頭金0）", doc.getElementById("rPrincipal").textContent === "¥1,500,000", doc.getElementById("rPrincipal").textContent);
    ok("返済予定表が3年分（36回）", doc.querySelectorAll("#rSchedule tr").length === 3, String(doc.querySelectorAll("#rSchedule tr").length));
    ok("利息総額が表示", /¥[\d,]+/.test(doc.getElementById("rInterest").textContent));
    // 頭金を入れると借入元金が下がる
    doc.getElementById("down").value = "300000"; fire(win, doc.getElementById("down"), "input");
    ok("頭金で借入元金が減る (#rPrincipal=¥1,200,000)", doc.getElementById("rPrincipal").textContent === "¥1,200,000", doc.getElementById("rPrincipal").textContent);
    // ボーナス払いを設定するとボーナス加算が表示される
    doc.getElementById("bonus").value = "300000"; fire(win, doc.getElementById("bonus"), "input");
    ok("ボーナス月加算が表示 (#rBonus)", /¥[\d,]+/.test(doc.getElementById("rBonus").textContent) && doc.getElementById("rBonus").textContent !== "—", doc.getElementById("rBonus").textContent);
    // 金利0%相当の検算：総返済額＝借入元金（利息総額0）
    const z = win.AucSim.loanSimulate({ price: 1200000, down: 0, ratePct: 0, months: 24, bonus: 0 });
    ok("年率0%なら利息0・月々均等", z.interest === 0 && z.monthly === 50000, "monthly=" + z.monthly + " interest=" + z.interest);
    // 120回（10年）・年率18% に対応：返済計画は10年分、金利が上がると月々も上がる
    doc.getElementById("down").value = "0"; doc.getElementById("bonus").value = "0";
    doc.getElementById("term").value = "120";
    doc.getElementById("rate").value = "18";
    fire(win, doc.getElementById("rate"), "input");
    ok("返済計画が120回=10年分", doc.querySelectorAll("#rSchedule tr").length === 10, String(doc.querySelectorAll("#rSchedule tr").length));
    ok("年率18%まで指定できる (#rateOut)", /18\.0%/.test(doc.getElementById("rateOut").textContent), doc.getElementById("rateOut").textContent);
    const hi = win.AucSim.loanSimulate({ price: 1500000, down: 0, ratePct: 18, months: 120, bonus: 0 });
    const lo = win.AucSim.loanSimulate({ price: 1500000, down: 0, ratePct: 3.9, months: 120, bonus: 0 });
    ok("金利が高いほど月々・利息が増える", hi.monthly > lo.monthly && hi.interest > lo.interest, "hi=" + hi.monthly + " lo=" + lo.monthly);
    // オンライン仮申込：サマリー連動・AI判定・送信
    ok("申込サマリーに月々が連動 (#sumMonthly)", /¥[\d,]+/.test(doc.getElementById("sumMonthly").textContent), doc.getElementById("sumMonthly").textContent);
    doc.getElementById("aJob").value = "正社員";
    doc.getElementById("aIncome").value = "600"; fire(win, doc.getElementById("aIncome"), "input");
    ok("AI一次判定が表示 (#aGrade)", /[ABCD]（/.test(doc.getElementById("aGrade").textContent), doc.getElementById("aGrade").textContent);
    // 借入目安診断（いくら借りられる？）
    const cap = win.AucSim.borrowCapacity({ income: 600, job: "正社員", years: 5, months: 0, otherMonthly: 0, termMonths: 60, ratePct: 6.9 });
    ok("借入可能額が算出される", cap.borrowable > 0 && cap.provisional > 0, "b=" + cap.borrowable + " p=" + cap.provisional);
    ok("仮申込金額は10万円単位・借入可能額以下", cap.provisional % 100000 === 0 && cap.provisional <= cap.borrowable, String(cap.provisional));
    const capLow = win.AucSim.borrowCapacity({ income: 300, job: "契約", years: 1, months: 0, otherMonthly: 0, termMonths: 60, ratePct: 6.9 });
    ok("年収・属性が下がると借入可能額も下がる", capLow.borrowable < cap.borrowable, capLow.borrowable + " < " + cap.borrowable);
    const capDebt = win.AucSim.borrowCapacity({ income: 600, job: "正社員", years: 5, months: 0, otherMonthly: 30000, termMonths: 60, ratePct: 6.9 });
    ok("他社借入があると借入可能額が減る", capDebt.borrowable < cap.borrowable, capDebt.borrowable + " < " + cap.borrowable);
    // UI連動：目安表示と「この金額でシミュ」反映（金利を6.9%に戻して算出）
    doc.getElementById("rate").value = "6.9"; fire(win, doc.getElementById("rate"), "input");
    doc.getElementById("capIncome").value = "600";
    doc.getElementById("capYears").value = "5";
    fire(win, doc.getElementById("capIncome"), "input");
    ok("目安UIに借入可能額を表示 (#capBorrow)", /¥[\d,]+/.test(doc.getElementById("capBorrow").textContent) && doc.getElementById("capBorrow").textContent !== "¥0", doc.getElementById("capBorrow").textContent);
    doc.getElementById("capApply").click();
    ok("『この金額でシミュ』で車両価格へ反映", Number(doc.getElementById("price").value) === cap.provisional, doc.getElementById("price").value + " vs " + cap.provisional);
    // 未入力ではバリデーションで止まる
    fire(win, doc.getElementById("applyForm"), "submit");
    ok("氏名/メール未入力は受付しない", /ご入力/.test(doc.getElementById("applyAlert").textContent), doc.getElementById("applyAlert").textContent);
    // 入力して送信 → 受付完了
    doc.getElementById("aName").value = "山田太郎";
    doc.getElementById("aEmail").value = "taro@example.com";
    fire(win, doc.getElementById("applyForm"), "submit");
    ok("仮申込で受付完了を表示", /受け付け/.test(doc.getElementById("applyAlert").textContent), doc.getElementById("applyAlert").textContent);
    ok("審査申込ページへの導線を表示", /carmelonline\.jp\/shinsa-2/.test(doc.getElementById("applyAlert").innerHTML), doc.getElementById("applyAlert").innerHTML.slice(0, 120));
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("login.html（登録フロー）");
  {
    const { win, getError } = load("login.html");
    ok("AucAuth 公開", !!win.AucAuth);
    win.AucAuth.register({ name: "テスト太郎", email: "t@example.com", plan: "スタンダード" });
    ok("登録でユーザー保存", win.AucAuth.current() && win.AucAuth.current().name === "テスト太郎");
    ok("サンプル注文が投入(購入+出品)", win.AucAuth.orders().length >= 3);
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("mypage.html（ログイン済み・出品申込）");
  {
    const storage = {
      auc_user: JSON.stringify({ name: "山田花子", email: "h@example.com", plan: "スタンダード", since: "2026-06-13" }),
      auc_orders: JSON.stringify([{ id: "OD-2041", kind: "buy", car: "ヤリス", status: "shipping", total: 1614000, progress: 75, date: "2026-06-02", memo: "" }])
    };
    const { win, doc, getError } = load("mypage.html", { storage });
    ok("ログイン名を表示", /山田花子/.test(doc.getElementById("hello").textContent), doc.getElementById("hello").textContent);
    ok("注文一覧を描画", doc.querySelectorAll("#orderList .order").length >= 1);
    doc.getElementById("zCar").value = "セレナ";
    doc.getElementById("zMed").value = "1000000";
    fire(win, doc.getElementById("zMed"), "input");
    ok("出品手取り即時計算 (#zNet)", /¥\d{3},\d00/.test(doc.getElementById("zNet").textContent), doc.getElementById("zNet").textContent);
    fire(win, doc.getElementById("sellForm"), "submit");
    ok("出品申込で注文追加(SL-)", /SL-/.test(doc.getElementById("orderList").innerHTML));
    ok("種別バッジ(購入/出品)表示", /出品/.test(doc.getElementById("orderList").innerHTML) && /購入/.test(doc.getElementById("orderList").innerHTML));
    // ローン仮申込：月々計算とAI一次判定
    doc.getElementById("lAmt").value = "1500000"; fire(win, doc.getElementById("lAmt"), "input");
    ok("ローン月々支払い計算 (#lMonthly)", /¥[\d,]+ \/月/.test(doc.getElementById("lMonthly").textContent), doc.getElementById("lMonthly").textContent);
    ok("ローンAI一次判定表示 (#lGrade)", /[ABCD]（/.test(doc.getElementById("lGrade").textContent), doc.getElementById("lGrade").textContent);
    fire(win, doc.getElementById("loanForm"), "submit");
    ok("ローン仮申込で受付表示", /受け付け/.test(doc.getElementById("loanAlert").textContent), doc.getElementById("loanAlert").textContent);
    // 相場見積り依頼：マイページに反映（一覧に追加）
    doc.getElementById("qCar").value = "アルファード 2020 4万km";
    fire(win, doc.getElementById("quoteForm"), "submit");
    ok("相場見積り依頼が一覧に反映(QT-)", /QT-/.test(doc.getElementById("quoteList").innerHTML), doc.getElementById("quoteList").innerHTML.slice(0,60));
    ok("相場見積りの状態=回答待ち", /回答待ち/.test(doc.getElementById("quoteList").innerHTML));
    ok("ページエラーなし", !getError(), getError());
  }

  console.log("mypage.html（未ログイン→誘導）");
  {
    const { win } = load("mypage.html");
    ok("未ログイン判定", !win.AucAuth.current());
  }

  console.log("\n==== 結果: " + pass + " passed, " + fail + " failed ====");
  process.exit(fail ? 1 : 0);
})();
