// ============================================================
//  BUYMO バックエンド GAS v3
//  ① お問い合わせフォーム受信（type:"buymo"）
//  ② コラム投稿・取得（type:"column" / doGet）
// ============================================================

// ▼ 設定 ――――――――――――――――――――――――――――――――――――――――
var SHEET_NAME        = '問い合わせ';
var COL_SHEET_NAME    = 'コラム';
var NOTIFY_EMAIL      = 'info@aisjaltd.com';
var DRIVE_FOLDER_NAME = 'BUYMO査定写真';
// ▲ 設定 ――――――――――――――――――――――――――――――――――――――――

/* ============================================================
   CORS ヘルパー
   ============================================================ */
function cors(output) {
  return output
    .setMimeType(ContentService.MimeType.JSON);
}

/* ============================================================
   doGet — コラム一覧 / 単件取得
   ?action=list[&cat=XX&page=1&limit=12]
   ?action=get&id=XX
   ?action=check&title=XX   (重複チェック)
   ============================================================ */
function doGet(e) {
  var p      = e && e.parameter ? e.parameter : {};
  var action = p.action || 'list';

  try {
    if (action === 'list')  return cors(ContentService.createTextOutput(JSON.stringify(getColumnList(p))));
    if (action === 'get')   return cors(ContentService.createTextOutput(JSON.stringify(getColumnById(p.id))));
    if (action === 'check') return cors(ContentService.createTextOutput(JSON.stringify(checkDuplicate(p.title, p.body || ''))));
    return cors(ContentService.createTextOutput(JSON.stringify({ error: 'unknown action' })));
  } catch (err) {
    return cors(ContentService.createTextOutput(JSON.stringify({ error: err.message })));
  }
}

/* ============================================================
   doPost — フォーム受信 / コラム投稿
   ============================================================ */
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);

    if (data.type === 'column') return cors(ContentService.createTextOutput(JSON.stringify(postColumn(data))));
    return cors(ContentService.createTextOutput(JSON.stringify(handleContact(data))));

  } catch (err) {
    return cors(ContentService.createTextOutput(JSON.stringify({ status: 'error', message: err.message })));
  }
}

/* ============================================================
   コラム： ユーティリティ
   ============================================================ */
function getColSheet() {
  var ss    = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(COL_SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(COL_SHEET_NAME);
    sheet.appendRow(['id', '投稿日時', 'タイトル', 'スラッグ', 'カテゴリ', '本文', 'タグ', '状態', '投稿者']);
    sheet.getRange(1, 1, 1, 9).setFontWeight('bold').setBackground('#0A6B3C').setFontColor('#ffffff');
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function normalizeForDup(str) {
  return (str || '').toString()
    .toLowerCase()
    .replace(/[ａ-ｚＡ-Ｚ０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); })
    .replace(/[　\s　、。！？「」【】・…]/g, '')
    .trim();
}

function titleSimilarity(a, b) {
  var na = normalizeForDup(a), nb = normalizeForDup(b);
  if (na === nb) return 1;
  var shorter = na.length < nb.length ? na : nb;
  var longer  = na.length < nb.length ? nb : na;
  if (longer.indexOf(shorter) !== -1 && shorter.length >= 6) return 0.85;
  // Bigram overlap
  function bigrams(s) {
    var bg = {};
    for (var i = 0; i < s.length - 1; i++) bg[s.slice(i, i + 2)] = true;
    return bg;
  }
  var bA = bigrams(na), bB = bigrams(nb);
  var keysA = Object.keys(bA), keysB = Object.keys(bB);
  if (keysA.length === 0 || keysB.length === 0) return 0;
  var common = keysA.filter(function (k) { return bB[k]; }).length;
  return (2 * common) / (keysA.length + keysB.length);
}

/* ============================================================
   コラム： 重複チェック
   ============================================================ */
function checkDuplicate(title, body) {
  var sheet = getColSheet();
  var rows  = sheet.getDataRange().getValues();
  var similar = [];
  for (var i = 1; i < rows.length; i++) {
    var r = rows[i];
    if (r[7] === '削除') continue;
    var sim = titleSimilarity(title, r[2]);
    if (sim >= 0.75) {
      similar.push({ id: r[0], title: r[2], similarity: Math.round(sim * 100), date: r[1] });
    }
  }
  return { isDuplicate: similar.length > 0, similar: similar.slice(0, 3) };
}

/* ============================================================
   コラム： 投稿
   ============================================================ */
function postColumn(data) {
  // 重複チェック
  var dup = checkDuplicate(data.title || '', data.body || '');
  if (dup.isDuplicate && !data.forceSave) {
    return { status: 'duplicate', similar: dup.similar };
  }

  var sheet = getColSheet();
  var ts    = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd HH:mm:ss');
  var id    = 'col_' + new Date().getTime();
  var slug  = (data.title || 'column').replace(/[^a-zA-Z0-9ぁ-んァ-ン一-龥]/g, '-').replace(/-+/g, '-').toLowerCase().slice(0, 60);

  sheet.appendRow([
    id,
    ts,
    data.title   || '',
    slug,
    data.category || '未分類',
    data.body    || '',
    (data.tags   || []).join(','),
    data.status  || '公開',
    data.author  || 'スタッフ'
  ]);

  return { status: 'ok', id: id, slug: slug, title: data.title };
}

/* ============================================================
   コラム： 一覧取得
   ============================================================ */
function getColumnList(p) {
  var sheet  = getColSheet();
  var rows   = sheet.getDataRange().getValues();
  var cat    = p.cat    || '';
  var limit  = Math.min(parseInt(p.limit  || '12', 10), 50);
  var page   = Math.max(parseInt(p.page   || '1',  10), 1);
  var cols   = [];

  for (var i = 1; i < rows.length; i++) {
    var r = rows[i];
    if (r[7] !== '公開') continue;
    if (cat && r[4] !== cat) continue;
    var bodyStr = (r[5] || '').toString();
    cols.push({
      id:       r[0],
      date:     r[1] ? Utilities.formatDate(new Date(r[1]), 'Asia/Tokyo', 'yyyy/MM/dd') : '',
      title:    r[2],
      slug:     r[3],
      category: r[4],
      excerpt:  bodyStr.replace(/<[^>]+>/g, '').slice(0, 80) + (bodyStr.length > 80 ? '…' : ''),
      tags:     r[6] ? r[6].toString().split(',') : []
    });
  }

  // 新しい順
  cols.sort(function (a, b) { return a.date < b.date ? 1 : -1; });
  var total  = cols.length;
  var start  = (page - 1) * limit;
  var items  = cols.slice(start, start + limit);
  var cats   = [];
  cols.forEach(function (c) { if (c.category && cats.indexOf(c.category) < 0) cats.push(c.category); });

  return { total: total, page: page, limit: limit, pages: Math.ceil(total / limit), items: items, categories: cats };
}

/* ============================================================
   コラム： 単件取得
   ============================================================ */
function getColumnById(id) {
  var sheet = getColSheet();
  var rows  = sheet.getDataRange().getValues();
  for (var i = 1; i < rows.length; i++) {
    var r = rows[i];
    if (r[0] === id || r[3] === id) {
      return {
        id:       r[0],
        date:     r[1] ? Utilities.formatDate(new Date(r[1]), 'Asia/Tokyo', 'yyyy/MM/dd') : '',
        title:    r[2],
        slug:     r[3],
        category: r[4],
        body:     r[5],
        tags:     r[6] ? r[6].toString().split(',') : [],
        status:   r[7],
        author:   r[8]
      };
    }
  }
  return { error: 'not found' };
}

/* ============================================================
   お問い合わせフォーム（既存）
   ============================================================ */
function getOrCreateFolder(parentId, name) {
  var parent = parentId ? DriveApp.getFolderById(parentId) : DriveApp.getRootFolder();
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

function savePhotosToDrive(photos, label) {
  if (!photos || photos.length === 0) return [];
  var root  = getOrCreateFolder(null, DRIVE_FOLDER_NAME);
  var today = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyyMMdd');
  var day   = getOrCreateFolder(root.getId(), today);
  var sub   = getOrCreateFolder(day.getId(), label || 'noname');
  var urls  = [];
  photos.forEach(function (p, i) {
    try {
      var blob = Utilities.newBlob(Utilities.base64Decode(p.data), p.type || 'image/jpeg', (i + 1) + '_' + (p.name || 'photo.jpg'));
      var file = sub.createFile(blob);
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      urls.push(file.getUrl());
    } catch (e) {
      urls.push('（保存失敗: ' + e.message + '）');
    }
  });
  return urls;
}

function handleContact(data) {
  var ts = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd HH:mm:ss');

  var photoUrls = [];
  if (data.photos && data.photos.length > 0) {
    var label = (data.name || 'noname').replace(/[\/\\:\*\?\"\<\>\|]/g, '_');
    photoUrls = savePhotosToDrive(data.photos, label);
  }

  var ss    = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.appendRow(['受信日時', '氏名', 'メール', '電話', 'ジャンル', '流入元', '写真枚数', '写真リンク', 'メッセージ']);
    sheet.getRange(1, 1, 1, 9).setFontWeight('bold').setBackground('#0A6B3C').setFontColor('#ffffff');
    sheet.setFrozenRows(1);
  }

  sheet.appendRow([ts, data.name || '', data.email || '', data.phone || '', data.genre || '',
                   data.source || '', photoUrls.length, photoUrls.join('\n'), data.message || '']);

  var subject = '【BUYMO】新しいお問い合わせ：' + (data.name || '（氏名未入力）');
  var photoSection = photoUrls.length > 0
    ? '写真：' + photoUrls.length + '枚\n' + photoUrls.map(function (u, i) { return '  写真' + (i + 1) + ': ' + u; }).join('\n')
    : '写真：なし';

  var body = [
    '■ BUYMOに新しいお問い合わせが届きました', '',
    '受信日時　：' + ts, '氏名　　　：' + (data.name || '—'),
    'メール　　：' + (data.email || '—'), '電話番号　：' + (data.phone || '—'),
    'ジャンル　：' + (data.genre || '—'), '流入元　　：' + (data.source || '—'),
    photoSection, '', '─── メッセージ ───', data.message || '（内容なし）', '',
    'スプレッドシート：', SpreadsheetApp.getActiveSpreadsheet().getUrl()
  ].join('\n');

  MailApp.sendEmail({ to: NOTIFY_EMAIL, subject: subject, body: body });
  return { status: 'ok', photos: photoUrls.length };
}

// テスト
function testColumn() {
  Logger.log(JSON.stringify(postColumn({ title: 'プリウスを高く売る5つのコツ', category: '売り方', body: '本文テスト', tags: ['プリウス', '高額査定'] })));
  Logger.log(JSON.stringify(getColumnList({})));
}
