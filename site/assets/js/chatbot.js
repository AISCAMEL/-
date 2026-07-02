/**
 * chatbot.js — AUC-AGENT AIチャットボットウィジェット
 *
 * 自己完結型：DOM要素を自動生成し、FAQまたはGASバックエンド経由で回答。
 * window.AucConfig.endpoint が設定されていればバックエンドへ送信、
 * 空の場合はルールベースのFAQフォールバックで回答する。
 */
(function () {
  "use strict";

  var STORAGE_KEY = "aucChatHistory";
  var MAX_HISTORY = 50;

  /* ---------- FAQ ルールベース ---------- */
  var FAQ = [
    {
      keywords: ["料金", "手数料", "費用", "いくら", "価格", "プラン"],
      answer: "AUC-AGENTの料金プランは以下の通りです：\n\n" +
        "【購入代行】\n" +
        "・ライトプラン：手数料 ¥49,800（税込）\n" +
        "・スタンダードプラン：手数料 ¥69,800（税込）\n" +
        "・プレミアムプラン：手数料 ¥89,800（税込）\n\n" +
        "【出品代行】\n" +
        "・出品手数料 ¥39,800（税込）\n\n" +
        "詳しくはトップページの料金表をご確認ください。"
    },
    {
      keywords: ["納車", "届く", "日数", "流れ", "購入", "買う", "オーダー"],
      answer: "ご購入（納車）までの流れ：\n\n" +
        "1. 会員登録・ご希望車種のオーダー\n" +
        "2. 専任スタッフがオークションで車両を落札\n" +
        "3. 車両検査・整備\n" +
        "4. 陸送手配（全国対応）\n" +
        "5. 納車\n\n" +
        "落札からお届けまで通常2〜3週間程度です。お急ぎの場合はご相談ください。"
    },
    {
      keywords: ["出品", "売る", "売却", "買取", "査定"],
      answer: "出品（売却）の流れ：\n\n" +
        "1. 出品申込フォームに車両情報を入力\n" +
        "2. 無料査定・相場のご案内\n" +
        "3. 出品票の作成（自動生成）\n" +
        "4. オークションに出品・落札\n" +
        "5. お振込み\n\n" +
        "出品ページから簡単にお申込みいただけます。"
    },
    {
      keywords: ["ローン", "分割", "月々", "オリコ", "支払"],
      answer: "オートローンについて：\n\n" +
        "・オリコ提携のオートローンをご利用いただけます\n" +
        "・最長84回払い対応\n" +
        "・Web上でかんたんシミュレーション可能\n" +
        "・お申込み後、最短即日で審査結果をご連絡\n\n" +
        "マイページのローンシミュレーターでお試しください。"
    },
    {
      keywords: ["会員", "登録", "サインアップ", "アカウント"],
      answer: "会員登録について：\n\n" +
        "・登録は無料です\n" +
        "・メールアドレスまたはGoogleアカウントで登録可能\n" +
        "・会員限定のマイページで注文管理・相場確認ができます\n" +
        "・紹介コードでお友達を紹介すると双方にクーポンプレゼント\n\n" +
        "トップページの「無料会員登録」ボタンからどうぞ。"
    },
    {
      keywords: ["陸送", "配送", "送料", "運搬", "輸送", "届け"],
      answer: "陸送（車両配送）について：\n\n" +
        "・全国対応（離島を除く）\n" +
        "・料金は距離に応じて変動します\n" +
        "・目安：近県 ¥20,000〜 / 遠方 ¥50,000〜\n" +
        "・納車日のご相談も承ります\n\n" +
        "詳しい料金は陸送ページをご確認ください。"
    },
    {
      keywords: ["営業", "時間", "休み", "定休", "電話", "連絡", "問い合わせ"],
      answer: "営業時間・お問い合わせ先：\n\n" +
        "・営業時間：平日 8:00〜17:00\n" +
        "・定休日：土日祝\n" +
        "・電話：050-1722-3365\n" +
        "・メール：info@aisjaltd.com\n" +
        "・所在地：福島県いわき市\n" +
        "・運営：合同会社アイズ"
    }
  ];

  var GREETING = "こんにちは！AUC-AGENTへようこそ。\n" +
    "オークション代行に関するご質問にお答えします。\n\n" +
    "よくあるご質問：\n" +
    "・料金・手数料について\n" +
    "・納車までの流れ\n" +
    "・出品の流れ\n" +
    "・オートローン\n" +
    "・会員登録\n" +
    "・陸送について\n" +
    "・営業時間\n\n" +
    "お気軽にご質問ください！";

  var FALLBACK = "申し訳ありません、ご質問の内容を理解できませんでした。\n\n" +
    "以下のトピックについてお答えできます：\n" +
    "・料金、納車の流れ、出品の流れ、ローン、会員登録、陸送、営業時間\n\n" +
    "または、お電話（050-1722-3365）・メール（info@aisjaltd.com）でもお気軽にお問い合わせください。";

  /* ---------- FAQ検索 ---------- */
  function findFaqAnswer(msg) {
    var text = msg.toLowerCase();
    var bestMatch = null;
    var bestScore = 0;
    for (var i = 0; i < FAQ.length; i++) {
      var score = 0;
      for (var k = 0; k < FAQ[i].keywords.length; k++) {
        if (text.indexOf(FAQ[i].keywords[k]) !== -1) {
          score++;
        }
      }
      if (score > bestScore) {
        bestScore = score;
        bestMatch = FAQ[i];
      }
    }
    return bestMatch ? bestMatch.answer : FALLBACK;
  }

  /* ---------- セッション履歴 ---------- */
  function loadHistory() {
    try {
      var data = sessionStorage.getItem(STORAGE_KEY);
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  function saveHistory(history) {
    try {
      if (history.length > MAX_HISTORY) {
        history = history.slice(history.length - MAX_HISTORY);
      }
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
    } catch (e) { /* ignore */ }
  }

  /* ---------- DOM構築 ---------- */
  function createWidget() {
    // トグルボタン
    var toggle = document.createElement("button");
    toggle.className = "chatbot-toggle";
    toggle.setAttribute("aria-label", "チャットを開く");
    toggle.innerHTML =
      '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>' +
      '</svg>';

    // パネル
    var panel = document.createElement("div");
    panel.className = "chatbot-panel";
    panel.style.display = "none";
    panel.innerHTML =
      '<div class="chatbot-header">' +
        '<span class="chatbot-title">AUC-AGENT チャット</span>' +
        '<button class="chatbot-close" aria-label="閉じる">&times;</button>' +
      '</div>' +
      '<div class="chatbot-messages" id="chatbotMessages"></div>' +
      '<div class="chatbot-input-area">' +
        '<input type="text" class="chatbot-input" id="chatbotInput" placeholder="ご質問をどうぞ..." />' +
        '<button class="chatbot-send" id="chatbotSend" aria-label="送信">' +
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>' +
        '</button>' +
      '</div>';

    document.body.appendChild(toggle);
    document.body.appendChild(panel);

    return { toggle: toggle, panel: panel };
  }

  /* ---------- メッセージ表示 ---------- */
  function appendMessage(container, role, text) {
    var div = document.createElement("div");
    div.className = "chatbot-msg " + role;

    // テキストを改行対応で表示
    var lines = text.split("\n");
    for (var i = 0; i < lines.length; i++) {
      if (i > 0) div.appendChild(document.createElement("br"));
      div.appendChild(document.createTextNode(lines[i]));
    }

    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  function showTyping(container) {
    var div = document.createElement("div");
    div.className = "chatbot-msg bot chatbot-typing";
    div.innerHTML = '<span class="chatbot-dot"></span><span class="chatbot-dot"></span><span class="chatbot-dot"></span>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  /* ---------- バックエンド送信 ---------- */
  function sendToBackend(message, history, callback) {
    var endpoint = window.AucConfig && window.AucConfig.endpoint;
    if (!endpoint) {
      // FAQフォールバック
      setTimeout(function () {
        callback(findFaqAnswer(message));
      }, 400 + Math.random() * 600);
      return;
    }

    // GASバックエンドへ送信
    var apiHistory = [];
    for (var i = 0; i < history.length; i++) {
      apiHistory.push({
        role: history[i].role === "user" ? "user" : "assistant",
        content: history[i].text
      });
    }

    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "text/plain" },
      mode: "no-cors",
      body: JSON.stringify({
        type: "chat",
        message: message,
        history: apiHistory
      })
    }).then(function (res) {
      // no-cors returns opaque response; try to parse if possible
      return res.text();
    }).then(function (text) {
      try {
        var data = JSON.parse(text);
        callback(data.ok ? data.reply : findFaqAnswer(message));
      } catch (e) {
        // no-cors opaque response fallback
        callback(findFaqAnswer(message));
      }
    }).catch(function () {
      callback(findFaqAnswer(message));
    });
  }

  /* ---------- 初期化 ---------- */
  function init() {
    var els = createWidget();
    var toggle = els.toggle;
    var panel = els.panel;
    var messagesEl = panel.querySelector("#chatbotMessages");
    var inputEl = panel.querySelector("#chatbotInput");
    var sendBtn = panel.querySelector("#chatbotSend");
    var closeBtn = panel.querySelector(".chatbot-close");
    var isOpen = false;
    var history = loadHistory();

    // 履歴を復元
    if (history.length === 0) {
      appendMessage(messagesEl, "bot", GREETING);
    } else {
      for (var i = 0; i < history.length; i++) {
        appendMessage(messagesEl, history[i].role, history[i].text);
      }
    }

    // 開閉
    toggle.addEventListener("click", function () {
      isOpen = !isOpen;
      panel.style.display = isOpen ? "flex" : "none";
      toggle.classList.toggle("chatbot-toggle--active", isOpen);
      if (isOpen) {
        inputEl.focus();
        messagesEl.scrollTop = messagesEl.scrollHeight;
      }
    });

    closeBtn.addEventListener("click", function () {
      isOpen = false;
      panel.style.display = "none";
      toggle.classList.remove("chatbot-toggle--active");
    });

    // 送信処理
    function handleSend() {
      var msg = inputEl.value.trim();
      if (!msg) return;

      inputEl.value = "";
      appendMessage(messagesEl, "user", msg);
      history.push({ role: "user", text: msg });
      saveHistory(history);

      var typing = showTyping(messagesEl);

      sendToBackend(msg, history, function (reply) {
        if (typing.parentNode) typing.parentNode.removeChild(typing);
        appendMessage(messagesEl, "bot", reply);
        history.push({ role: "bot", text: reply });
        saveHistory(history);
      });
    }

    sendBtn.addEventListener("click", handleSend);
    inputEl.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.keyCode === 13) {
        e.preventDefault();
        handleSend();
      }
    });
  }

  // DOM準備後に初期化
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
