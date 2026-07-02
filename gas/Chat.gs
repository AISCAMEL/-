/**
 * Chat.gs — AIチャットボット応答ハンドラー
 *
 * WebApp.gs の doPost switch に case "chat" を追加して利用。
 * フロントの chatbot.js から送信されたメッセージに対し、
 * Anthropic Claude API でAUC-AGENTに関する回答を生成する。
 */

var CHAT_SYSTEM_PROMPT_ =
  "あなたは「AUC-AGENT」の公式AIアシスタントです。AUC-AGENTは合同会社アイズが運営する中古車オークション代行サービスです。\n\n" +
  "【会社情報】\n" +
  "・運営: 合同会社アイズ\n" +
  "・所在地: 福島県いわき市\n" +
  "・電話: 050-1722-3365\n" +
  "・メール: info@aisjaltd.com\n" +
  "・営業時間: 平日 8:00〜17:00（土日祝休み）\n\n" +
  "【サービス概要】\n" +
  "・購入代行: お客様の希望車種をオートオークションで落札代行（ライト¥49,800/スタンダード¥69,800/プレミアム¥89,800）\n" +
  "・出品代行: お客様の車両をオークションに出品代行（手数料¥39,800）\n" +
  "・オートローン: オリコ提携ローン（最長84回払い）\n" +
  "・陸送: 全国対応の車両配送\n" +
  "・会員登録無料、紹介プログラムあり\n\n" +
  "【回答ルール】\n" +
  "・日本語で丁寧に回答してください\n" +
  "・AUC-AGENTのサービスに関係ない質問には、丁重にサービス範囲外とお伝えください\n" +
  "・正確な情報がわからない場合は、お電話またはメールでの問い合わせを案内してください\n" +
  "・回答は簡潔に、200文字以内を目安にしてください";

/**
 * チャットメッセージを処理し、AI応答を返す
 * @param {Object} data - {type:"chat", message:string, history:Array}
 * @return {Object} {ok:true, reply:string}
 */
function handleChat_(data) {
  var cfg = getConfig();
  var userMsg = String(data.message || "").trim();

  if (!userMsg) {
    return { ok: false, error: "メッセージが空です" };
  }

  // メッセージ履歴を構築
  var messages = [];
  var history = data.history || [];
  for (var i = 0; i < history.length; i++) {
    var role = history[i].role === "user" ? "user" : "assistant";
    messages.push({ role: role, content: String(history[i].content || "") });
  }
  // 最新のユーザーメッセージを追加
  messages.push({ role: "user", content: userMsg });

  // 直近10往復に制限
  if (messages.length > 20) {
    messages = messages.slice(messages.length - 20);
  }

  // Anthropic API
  if (cfg.ANTHROPIC_API_KEY) {
    try {
      var res = UrlFetchApp.fetch("https://api.anthropic.com/v1/messages", {
        method: "post",
        contentType: "application/json",
        headers: {
          "x-api-key": cfg.ANTHROPIC_API_KEY,
          "anthropic-version": "2023-06-01"
        },
        payload: JSON.stringify({
          model: cfg.ANTHROPIC_MODEL || "claude-haiku-4-5-20251001",
          max_tokens: 512,
          system: CHAT_SYSTEM_PROMPT_,
          messages: messages
        }),
        muteHttpExceptions: true
      });

      var body = JSON.parse(res.getContentText());
      var reply = body.content && body.content[0] && body.content[0].text || "";
      if (reply) {
        return { ok: true, reply: reply };
      }
    } catch (err) {
      Logger.log("Chat Claude API エラー: " + err);
    }
  }

  // OpenRouter fallback
  if (cfg.OPENROUTER_API_KEY) {
    try {
      var orMessages = [{ role: "system", content: CHAT_SYSTEM_PROMPT_ }].concat(messages);
      var res2 = UrlFetchApp.fetch("https://openrouter.ai/api/v1/chat/completions", {
        method: "post",
        contentType: "application/json",
        headers: { Authorization: "Bearer " + cfg.OPENROUTER_API_KEY },
        payload: JSON.stringify({
          model: cfg.OPENROUTER_MODEL,
          messages: orMessages,
          temperature: 0.4
        }),
        muteHttpExceptions: true
      });

      var body2 = JSON.parse(res2.getContentText());
      var reply2 = body2.choices && body2.choices[0] && body2.choices[0].message.content || "";
      if (reply2) {
        return { ok: true, reply: reply2 };
      }
    } catch (err2) {
      Logger.log("Chat OpenRouter API エラー: " + err2);
    }
  }

  // ルールベースフォールバック
  return { ok: true, reply: chatRuleFallback_(userMsg) };
}

/**
 * AIが利用できない場合のルールベース応答
 */
function chatRuleFallback_(msg) {
  var text = msg.toLowerCase();

  if (/料金|手数料|費用|いくら|価格/.test(text)) {
    return "購入代行の手数料はライト¥49,800〜、出品代行は¥39,800（税込）です。詳しくはサイトの料金表をご確認ください。";
  }
  if (/納車|届く|購入|買う|流れ/.test(text)) {
    return "会員登録→オーダー→落札→検査・整備→陸送→納車の流れです。落札からお届けまで通常2〜3週間です。";
  }
  if (/出品|売る|売却|買取|査定/.test(text)) {
    return "出品申込→無料査定→出品票作成→オークション出品→お振込みの流れです。出品ページからお申込みください。";
  }
  if (/ローン|分割|月々/.test(text)) {
    return "オリコ提携のオートローンをご利用いただけます。最長84回払い、Web上でシミュレーション可能です。";
  }
  if (/会員|登録/.test(text)) {
    return "会員登録は無料です。メールまたはGoogleアカウントで登録でき、マイページから各種サービスをご利用いただけます。";
  }
  if (/陸送|配送|送料/.test(text)) {
    return "全国対応（離島除く）で、近県¥20,000〜、遠方¥50,000〜が目安です。詳しくは陸送ページをご確認ください。";
  }
  if (/営業|時間|休み|電話|連絡/.test(text)) {
    return "営業時間は平日8:00〜17:00、土日祝休みです。電話: 050-1722-3365、メール: info@aisjaltd.com";
  }

  return "ご質問ありがとうございます。詳しくはお電話（050-1722-3365）またはメール（info@aisjaltd.com）でお問い合わせください。";
}
