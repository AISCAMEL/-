/**
 * SnsArticle.gs
 * SNS記事量産化システムの本体（生成・保存・メニュー・トリガー）
 *
 * 親システム: CarLoan_System
 * 仕様書: docs/SNS記事量産化_仕様書.md
 * 依存: SnsConfig.gs（getSnsConfig） / Config.gs（getConfig） / OpenRouter.gs（callOpenRouter・任意）
 */

// ====================================================================
// メニュー（スプレッドシート起動時に表示）
// ====================================================================

/**
 * スプレッドシートを開いたときにカスタムメニューを追加する。
 * 親システム側にも onOpen がある場合は、そちらから本関数を呼ぶ形に統合すること。
 */
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('📣 SNS記事')
    .addItem('🛠 初期セットアップ（シート作成）', 'setupSnsSheet')
    .addSeparator()
    .addItem('✍️ まとめて生成（件数指定）', 'promptGenerateSnsArticles')
    .addItem('📅 今日のバッチを生成', 'generateDailySnsBatch')
    .addItem('🔁 没記事を再生成', 'regenerateRejectedArticles')
    .addToUi();
}

// ====================================================================
// セットアップ
// ====================================================================

/**
 * ⑪ SNS記事シートを作成し、ヘッダーを設定する（冪等：既存なら作り直さない）。
 */
function setupSnsSheet() {
  var cfg = getSnsConfig();
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(cfg.sheetName);

  if (!sheet) {
    sheet = ss.insertSheet(cfg.sheetName);
  }

  // ヘッダーが未設定なら設定
  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, cfg.headers.length).setValues([cfg.headers]);
    sheet.getRange(1, 1, 1, cfg.headers.length)
      .setFontWeight('bold')
      .setBackground('#1a73e8')
      .setFontColor('#ffffff');
    sheet.setFrozenRows(1);
  }

  SpreadsheetApp.getUi().alert('✅ 「' + cfg.sheetName + '」シートを準備しました。');
}

// ====================================================================
// 生成（手動・メニュー）
// ====================================================================

/**
 * メニュー用：生成件数を入力させてから一括生成する。
 */
function promptGenerateSnsArticles() {
  var ui = SpreadsheetApp.getUi();
  var res = ui.prompt('SNS記事の一括生成', '生成する件数を入力してください（例: 20）', ui.ButtonSet.OK_CANCEL);
  if (res.getSelectedButton() !== ui.Button.OK) return;

  var count = parseInt(res.getResponseText(), 10);
  if (isNaN(count) || count <= 0) {
    ui.alert('数値を入力してください。');
    return;
  }

  var made = generateSnsArticles(count);
  ui.alert('✅ ' + made + ' 件のSNS記事を「下書き」で生成しました。');
}

/**
 * SNS記事を一括生成して⑪シートに追記する。
 * 媒体・テーマ・切り口は全組み合わせをローテーションして偏りを防ぐ。
 *
 * @param {number} count  生成件数
 * @param {Array<string>=} platforms 媒体コード配列（省略時は全媒体）
 * @param {Array<string>=} categories テーマコード配列（省略時は全カテゴリ）
 * @return {number} 実際に生成・保存した件数
 */
function generateSnsArticles(count, platforms, categories) {
  var cfg = getSnsConfig();
  var platformKeys = platforms || Object.keys(cfg.platforms);
  var categoryKeys = categories || Object.keys(cfg.categories);
  var hooks = cfg.hooks;

  var made = 0;
  for (var i = 0; i < count; i++) {
    // ローテーションで媒体・テーマ・切り口を順に選ぶ
    var platform = platformKeys[i % platformKeys.length];
    var category = categoryKeys[i % categoryKeys.length];
    var hook = hooks[i % hooks.length];

    var article = generateOneArticle_(platform, category, hook);
    if (article) {
      saveSnsArticle(platform, category, hook, article);
      made++;
    }
    // API負荷・レート制限対策
    Utilities.sleep(500);
  }
  return made;
}

/**
 * 定期トリガー用：毎日のバッチを生成する（媒体ごとに設定件数）。
 */
function generateDailySnsBatch() {
  var cfg = getSnsConfig();
  var categoryKeys = Object.keys(cfg.categories);
  var hooks = cfg.hooks;
  // 日替わりでテーマ・切り口の起点をずらす
  var dayOffset = Math.floor(Date.now() / (1000 * 60 * 60 * 24));

  var idx = 0;
  Object.keys(cfg.dailyBatch).forEach(function (platform) {
    var n = cfg.dailyBatch[platform];
    for (var i = 0; i < n; i++) {
      var category = categoryKeys[(dayOffset + idx) % categoryKeys.length];
      var hook = hooks[(dayOffset + idx) % hooks.length];
      var article = generateOneArticle_(platform, category, hook);
      if (article) saveSnsArticle(platform, category, hook, article);
      idx++;
      Utilities.sleep(500);
    }
  });
}

/**
 * 「没」ステータスの記事を読み直し、同じ媒体・テーマ・切り口で再生成する。
 */
function regenerateRejectedArticles() {
  var cfg = getSnsConfig();
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(cfg.sheetName);
  if (!sheet || sheet.getLastRow() < 2) return;

  var data = sheet.getDataRange().getValues();
  var statusCol = cfg.headers.indexOf('ステータス'); // 0-index
  var regenerated = 0;

  for (var r = 1; r < data.length; r++) {
    if (data[r][statusCol] === '没') {
      var platform = data[r][cfg.headers.indexOf('媒体')];
      var category = findCategoryCode_(cfg, data[r][cfg.headers.indexOf('テーマカテゴリ')]);
      var hook = data[r][cfg.headers.indexOf('切り口')];
      var article = generateOneArticle_(platform, category, hook);
      if (article) {
        saveSnsArticle(platform, category, hook, article);
        regenerated++;
      }
      Utilities.sleep(500);
    }
  }
  SpreadsheetApp.getUi().alert('🔁 没記事をもとに ' + regenerated + ' 件を再生成しました。');
}

// ====================================================================
// AI生成
// ====================================================================

/**
 * 1記事を生成して {title, body, hashtags, model} を返す。失敗時は null。
 * @private
 */
function generateOneArticle_(platform, category, hook) {
  var prompt = buildSnsPrompt(platform, category, hook);
  var result = callSnsAi_(prompt);
  if (!result) return null;

  var parsed = parseAiJson_(result.text);
  if (!parsed) return null;

  parsed.model = result.model;
  return parsed;
}

/**
 * 媒体・テーマ・切り口から生成プロンプトを構築する（仕様書 §7）。
 * @param {string} platform 媒体コード
 * @param {string} category テーマコード
 * @param {string} hook 切り口
 * @return {string} プロンプト本文
 */
function buildSnsPrompt(platform, category, hook) {
  var cfg = getSnsConfig();
  var p = cfg.platforms[platform];
  var theme = cfg.categories[category];

  var lines = [];
  lines.push('あなたは中古車販売業のSNSマーケティング担当者です。以下の条件でSNS投稿を1本作成してください。');
  lines.push('');
  lines.push('【事業について】');
  lines.push(cfg.businessContext);
  lines.push('');
  lines.push('【投稿する媒体】' + p.name);
  lines.push('・文字数の目安: ' + p.maxChars + '字以内');
  lines.push('・構成: ' + p.structure);
  lines.push('・ハッシュタグ: ' + p.hashtagCount);
  lines.push('');
  lines.push('【テーマ】' + theme);
  lines.push('【切り口】' + hook);
  lines.push('');
  lines.push('【守るべきルール】');
  cfg.compliance.forEach(function (c) { lines.push('・' + c); });
  lines.push('');
  lines.push('【出力形式】次のJSONのみを返してください（説明文は不要）。');
  lines.push('{"title": "冒頭のフック/タイトル", "body": "投稿本文または台本", "hashtags": "#タグ1 #タグ2"}');
  if (p.hashtagCount.indexOf('なし') >= 0) {
    lines.push('※この媒体はハッシュタグ不要なので hashtags は空文字 "" にしてください。');
  }

  return lines.join('\n');
}

/**
 * OpenRouter を呼び出す。親システムの callOpenRouter があればそれを使い、
 * なければ内蔵のフォールバック実装で直接呼び出す。
 * @private
 * @return {?{text:string, model:string}}
 */
function callSnsAi_(prompt) {
  var cfg = getSnsConfig();

  // 親システムに callOpenRouter があれば共用
  if (typeof callOpenRouter === 'function') {
    try {
      var text = callOpenRouter(prompt);
      if (text) return { text: text, model: '(callOpenRouter)' };
    } catch (e) {
      Logger.log('callOpenRouter failed, fallback: ' + e);
    }
  }

  // フォールバック：OpenRouter を直接呼ぶ
  return callOpenRouterFallback_(prompt, cfg.aiModels);
}

/**
 * OpenRouter API を直接呼び出すフォールバック実装。
 * モデルは優先順に試し、最初に成功したものを使う。
 * @private
 * @return {?{text:string, model:string}}
 */
function callOpenRouterFallback_(prompt, models) {
  var apiKey = '';
  try {
    if (typeof getConfig === 'function') {
      var c = getConfig();
      apiKey = c.OPENROUTER_API_KEY || c.openRouterApiKey || '';
    }
  } catch (e) {
    Logger.log('getConfig not available: ' + e);
  }
  if (!apiKey) {
    apiKey = PropertiesService.getScriptProperties().getProperty('OPENROUTER_API_KEY') || '';
  }
  if (!apiKey) {
    Logger.log('OpenRouter APIキーが未設定です。生成をスキップします。');
    return null;
  }

  for (var i = 0; i < models.length; i++) {
    var model = models[i];
    try {
      var payload = {
        model: model,
        messages: [{ role: 'user', content: prompt }],
        temperature: 0.9
      };
      var res = UrlFetchApp.fetch('https://openrouter.ai/api/v1/chat/completions', {
        method: 'post',
        contentType: 'application/json',
        headers: { Authorization: 'Bearer ' + apiKey },
        payload: JSON.stringify(payload),
        muteHttpExceptions: true
      });
      if (res.getResponseCode() === 200) {
        var json = JSON.parse(res.getContentText());
        var text = json.choices && json.choices[0] && json.choices[0].message.content;
        if (text) return { text: text, model: model };
      } else {
        Logger.log('OpenRouter ' + model + ' -> ' + res.getResponseCode() + ': ' + res.getContentText());
      }
    } catch (e) {
      Logger.log('OpenRouter error (' + model + '): ' + e);
    }
  }
  return null;
}

// ====================================================================
// 保存
// ====================================================================

/**
 * 生成された1記事を⑪シートに1行追記する。
 * @param {string} platform 媒体コード
 * @param {string} category テーマコード
 * @param {string} hook 切り口
 * @param {{title:string, body:string, hashtags:string, model:string}} article
 */
function saveSnsArticle(platform, category, hook, article) {
  var cfg = getSnsConfig();
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(cfg.sheetName) || ss.insertSheet(cfg.sheetName);

  // ヘッダーが無ければ作る
  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, cfg.headers.length).setValues([cfg.headers]);
    sheet.setFrozenRows(1);
  }

  var now = new Date();
  var articleId = generateSnsArticleId_(sheet, now);
  var body = article.body || '';
  var charCount = body.length;

  var row = [
    articleId,                              // 記事ID
    now,                                    // 生成日時
    cfg.platforms[platform].name,           // 媒体
    cfg.categories[category] || category,   // テーマカテゴリ
    hook,                                   // 切り口
    article.title || '',                    // タイトル/フック
    body,                                   // 本文
    article.hashtags || '',                 // ハッシュタグ
    charCount,                              // 文字数
    article.model || '',                    // 使用AIモデル
    '下書き',                               // ステータス
    '', '', '', ''                          // 投稿予定日/投稿日/担当者/備考
  ];

  sheet.appendRow(row);
}

/**
 * 記事IDを採番する（SNS-yyyyMMdd-### 形式、当日連番）。
 * @private
 */
function generateSnsArticleId_(sheet, now) {
  var dateStr = Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMMdd');
  var prefix = 'SNS-' + dateStr + '-';

  var seq = 1;
  if (sheet.getLastRow() >= 2) {
    var ids = sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getValues();
    ids.forEach(function (r) {
      var id = String(r[0]);
      if (id.indexOf(prefix) === 0) {
        var n = parseInt(id.substring(prefix.length), 10);
        if (!isNaN(n) && n >= seq) seq = n + 1;
      }
    });
  }
  return prefix + ('00' + seq).slice(-3);
}

// ====================================================================
// ユーティリティ
// ====================================================================

/**
 * AIの返答からJSONを取り出してパースする。コードブロックや前後の文章があっても抽出する。
 * @private
 * @return {?{title:string, body:string, hashtags:string}}
 */
function parseAiJson_(text) {
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch (e) {
    // 本文中の最初の { ... } を抜き出して再挑戦
    var start = text.indexOf('{');
    var end = text.lastIndexOf('}');
    if (start >= 0 && end > start) {
      try {
        return JSON.parse(text.substring(start, end + 1));
      } catch (e2) {
        Logger.log('JSON parse failed: ' + e2);
      }
    }
  }
  return null;
}

/**
 * テーマ名（日本語）からカテゴリコードを逆引きする。見つからなければ T01。
 * @private
 */
function findCategoryCode_(cfg, themeName) {
  var keys = Object.keys(cfg.categories);
  for (var i = 0; i < keys.length; i++) {
    if (cfg.categories[keys[i]] === themeName) return keys[i];
  }
  return keys[0];
}
