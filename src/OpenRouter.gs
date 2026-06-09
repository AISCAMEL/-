/**
 * OpenRouter.gs
 * AI API呼び出し・プロンプト生成
 */

function callOpenRouter(userMessage, lineId) {
  try {
    const config = getConfig();
    const url = 'https://openrouter.ai/api/v1/chat/completions';

    const prompt = buildPrompt(userMessage, lineId);

    const payload = {
      model: config.OPENROUTER.MODEL_FREE,
      max_tokens: 1000,
      messages: [
        { role: 'system', content: prompt.system },
        { role: 'user', content: prompt.user }
      ]
    };

    const options = {
      method: 'post',
      contentType: 'application/json',
      headers: {
        'Authorization': 'Bearer ' + config.OPENROUTER.API_KEY,
        'HTTP-Referer': 'https://carmel-loan.jp',
        'X-Title': 'CarLoan_System'
      },
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };

    const response = UrlFetchApp.fetch(url, options);
    const data = JSON.parse(response.getContentText());

    if (data.choices && data.choices[0]) {
      return data.choices[0].message.content;
    }

    Logger.log('OpenRouterエラー：' + JSON.stringify(data));
    return getDefaultReply();

  } catch(err) {
    Logger.log('callOpenRouterエラー：' + err.toString());
    return getDefaultReply();
  }
}

function buildPrompt(userMessage, lineId) {
  const system = `あなたはカーメルのローン・買取相談AIアシスタントです。
以下のルールを必ず守って回答してください。

【役割】
・顧客の不安や疑問を丁寧に解消する
・カーローン・車買取の専門知識で回答する
・最終的に申込フォームまたは査定フォームへ誘導する

【カーメルの特徴】
・複数の信販会社と提携（14社）
・他社で断られた方も対応可能
・審査・相談は完全無料
・最短即日審査結果
・個人・法人・個人事業主対応
・買取は2026年中古車相場高騰中で高値買取可能

【回答ルール】
・200文字以内で簡潔に回答する
・専門用語は使わず分かりやすく説明する
・不安を共感してから解決策を提示する
・最後に申込または査定を促す一言を添える
・絵文字は使わない

【担当者へ繋ぐケース】
・具体的な審査結果の保証を求める場合
・クレーム・トラブル対応
・複雑な法人案件
→ 「担当者からご連絡いたします」と伝える

【回答してはいけないこと】
・審査通過を保証する発言
・他社の悪口
・個人情報の取り扱いに関する詳細`;

  const user = userMessage;

  return { system, user };
}

function getDefaultReply() {
  return 'ご質問ありがとうございます。担当者より改めてご連絡いたします。';
}

function testOpenRouter() {
  const reply = callOpenRouter('他社で断られたのですが審査できますか？', 'test_user');
  Logger.log('AI返答：' + reply);
}
