/**
 * AI.gs — OpenRouterで申込内容を要約・優先度づけ
 *
 * Config.gs に OPENROUTER_API_KEY を入れると有効。
 * 空欄のときはルールベースの簡易判定にフォールバックします。
 * 返り値: { summary: string, priority: "高"|"中"|"低" }
 */
function aiSummarize_(d) {
  var cfg = getConfig();

  // キー未設定 → 簡易ルールで優先度を決める
  if (!cfg.OPENROUTER_API_KEY) {
    return { summary: ruleSummary_(d), priority: rulePriority_(d) };
  }

  try {
    var prompt =
      "あなたは中古車オークション代行の受付担当です。次の申込を1〜2文で要約し、" +
      "対応優先度を 高/中/低 で判定してください。出力は JSON のみ：{\"summary\":\"...\",\"priority\":\"高|中|低\"}\n\n" +
      "希望車種: " + (d.car || "未記入") + "\n" +
      "予算: " + (d.bid || 0) + "円\n" +
      "クラス: " + (d.clsLabel || d.cls || "") + "\n" +
      "プラン: " + (d.plan || "") + "\n" +
      "想定総額: " + (d.total || 0) + "円";

    var res = UrlFetchApp.fetch("https://openrouter.ai/api/v1/chat/completions", {
      method: "post",
      contentType: "application/json",
      headers: { Authorization: "Bearer " + cfg.OPENROUTER_API_KEY },
      payload: JSON.stringify({
        model: cfg.OPENROUTER_MODEL,
        messages: [{ role: "user", content: prompt }],
        temperature: 0.2
      }),
      muteHttpExceptions: true
    });

    var body = JSON.parse(res.getContentText());
    var text = body.choices && body.choices[0] && body.choices[0].message.content || "";
    var parsed = JSON.parse(text.replace(/```json|```/g, "").trim());
    return {
      summary: parsed.summary || ruleSummary_(d),
      priority: parsed.priority || rulePriority_(d)
    };
  } catch (err) {
    Logger.log("AI要約エラー: " + err);
    return { summary: ruleSummary_(d), priority: rulePriority_(d) };
  }
}

/**
 * チャットボット応答（doGet action=bot から呼ばれる）
 * OpenRouter（Config.OPENROUTER_API_KEY）で社内情報を根拠に回答。
 * キー未設定／失敗時は空文字を返し、クライアント側のルールベースにフォールバック。
 */
function botAnswer_(mode, q) {
  q = String(q || "").slice(0, 500);
  if (!q) return "";
  var cfg = getConfig();
  if (!cfg.OPENROUTER_API_KEY) return ""; // 未設定 → クライアントのルールベース回答に任せる

  var partner = (mode === "partner");
  var ctx = partner
    ? "BUYMOは車買取のフランチャイズ本部。加盟店向け：出品は『申込→査定→出品票作成→会場搬入→落札』。報酬/ロイヤリティはプラン別で本部の個別説明にて案内。相場は査定シミュレーター/オークション実績を参照。案件は看板ボードで管理し対応履歴を残す。集客は本部がLP・地域SEO・広告で送客。研修はアカデミー（動画＋テキスト・修了テスト）。"
    : "BUYMOは車買取サービス。査定・出張・各種手続きは無料。事故車/不動車/廃車/水没車/過走行車もOK。買取代金は契約・必要書類確認後、最短即日〜数営業日で振込。全国47都道府県対応。契約時の必要書類は車検証・自賠責保険証・本人確認書類・印鑑。査定後のキャンセルは無料。ローン残債ありでも買取可。電話 0120-123-456（受付 平日8:00〜17:00）。";
  var sys = "あなたはBUYMO（車買取）の" + (partner ? "加盟店向け" : "お客様向け") +
    "サポート担当です。次の社内情報だけを根拠に、日本語で簡潔（最大3文）かつ丁寧に回答してください。" +
    "情報に無いことは断定せず「" + (partner ? "本部へご確認ください" : "お問い合わせください（0120-123-456）") + "」と案内。\n社内情報:\n" + ctx;

  try {
    var res = UrlFetchApp.fetch("https://openrouter.ai/api/v1/chat/completions", {
      method: "post", contentType: "application/json",
      headers: { Authorization: "Bearer " + cfg.OPENROUTER_API_KEY },
      payload: JSON.stringify({
        model: cfg.OPENROUTER_MODEL,
        messages: [{ role: "system", content: sys }, { role: "user", content: q }],
        temperature: 0.3
      }),
      muteHttpExceptions: true
    });
    var body = JSON.parse(res.getContentText());
    return (body.choices && body.choices[0] && body.choices[0].message.content || "").trim();
  } catch (err) { Logger.log("botAnswer_: " + err); return ""; }
}

function ruleSummary_(d) {
  return (d.car || "車種未指定") + " / 予算 " + Math.round((Number(d.bid) || 0) / 10000) + "万円 / " + (d.plan || "プラン未選択");
}

// 予算が大きいほど優先度高（成約単価が高いため）
function rulePriority_(d) {
  var bid = Number(d.bid) || 0;
  if (bid >= 2000000) return "高";
  if (bid >= 800000) return "中";
  return "低";
}
