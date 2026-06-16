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
