/**
 * AI.gs — AIで申込内容を要約・優先度づけ
 *
 * 対応AIプロバイダー（Config.gs で設定）:
 *   1. OpenRouter（OPENROUTER_API_KEY）— デフォルト
 *   2. Anthropic Claude API（ANTHROPIC_API_KEY）— 高精度
 *
 * 両方のキーが入っている場合は Anthropic を優先します。
 * 両方空欄のときはルールベースの簡易判定にフォールバックします。
 * 返り値: { summary: string, priority: "高"|"中"|"低" }
 */

var AI_PROMPT_ =
  "あなたは中古車オークション代行の受付担当です。次の申込を1〜2文で要約し、" +
  "対応優先度を 高/中/低 で判定してください。出力は JSON のみ：{\"summary\":\"...\",\"priority\":\"高|中|低\"}\n\n";

function aiSummarize_(d) {
  var cfg = getConfig();
  var context =
    "希望車種: " + (d.car || "未記入") + "\n" +
    "予算: " + (d.bid || 0) + "円\n" +
    "クラス: " + (d.clsLabel || d.cls || "") + "\n" +
    "プラン: " + (d.plan || "") + "\n" +
    "想定総額: " + (d.total || 0) + "円";

  if (cfg.ANTHROPIC_API_KEY) {
    return aiClaude_(cfg, context);
  }
  if (cfg.OPENROUTER_API_KEY) {
    return aiOpenRouter_(cfg, context);
  }
  return { summary: ruleSummary_(d), priority: rulePriority_(d) };
}

function aiClaude_(cfg, context) {
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
        max_tokens: 256,
        messages: [{ role: "user", content: AI_PROMPT_ + context }]
      }),
      muteHttpExceptions: true
    });

    var body = JSON.parse(res.getContentText());
    var text = body.content && body.content[0] && body.content[0].text || "";
    var parsed = JSON.parse(text.replace(/```json|```/g, "").trim());
    return {
      summary: parsed.summary || context.split("\n")[0],
      priority: parsed.priority || "中"
    };
  } catch (err) {
    Logger.log("Claude API エラー: " + err);
    return { summary: context.split("\n")[0], priority: "中" };
  }
}

function aiOpenRouter_(cfg, context) {
  try {
    var res = UrlFetchApp.fetch("https://openrouter.ai/api/v1/chat/completions", {
      method: "post",
      contentType: "application/json",
      headers: { Authorization: "Bearer " + cfg.OPENROUTER_API_KEY },
      payload: JSON.stringify({
        model: cfg.OPENROUTER_MODEL,
        messages: [{ role: "user", content: AI_PROMPT_ + context }],
        temperature: 0.2
      }),
      muteHttpExceptions: true
    });

    var body = JSON.parse(res.getContentText());
    var text = body.choices && body.choices[0] && body.choices[0].message.content || "";
    var parsed = JSON.parse(text.replace(/```json|```/g, "").trim());
    return {
      summary: parsed.summary || context.split("\n")[0],
      priority: parsed.priority || "中"
    };
  } catch (err) {
    Logger.log("OpenRouter API エラー: " + err);
    return { summary: context.split("\n")[0], priority: "中" };
  }
}

function ruleSummary_(d) {
  return (d.car || "車種未指定") + " / 予算 " + Math.round((Number(d.bid) || 0) / 10000) + "万円 / " + (d.plan || "プラン未選択");
}

function rulePriority_(d) {
  var bid = Number(d.bid) || 0;
  if (bid >= 2000000) return "高";
  if (bid >= 800000) return "中";
  return "低";
}
