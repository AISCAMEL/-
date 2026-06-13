/**
 * GmailService.gs — ラベル付きメールの検索・添付/本文取得・ラベル張替
 */

/** 監視対象ラベルの未処理メッセージを取得（処理済は除外）。 */
function fetchTargetMessages_(limit) {
  const q = 'label:' + quoteLabel_(CONFIG.LABEL.TARGET) +
            ' -label:' + quoteLabel_(CONFIG.LABEL.DONE);
  const threads = GmailApp.search(q, 0, Math.max(limit, 1) * 2);
  const msgs = [];
  for (let i = 0; i < threads.length && msgs.length < limit; i++) {
    const ms = threads[i].getMessages();
    for (let j = 0; j < ms.length && msgs.length < limit; j++) {
      msgs.push(ms[j]);
    }
  }
  return msgs;
}

/** ラベル名にスラッシュや日本語が含まれるため、検索用にクォート。 */
function quoteLabel_(name) {
  return '"' + name + '"';
}

/**
 * メッセージから抽出対象（添付Blob群 or 本文テキスト）を取り出す。
 * 添付PDF/画像を優先。無ければ本文プレーンテキスト。
 */
function extractPayload_(message) {
  const atts = message.getAttachments({ includeInlineImages: true, includeAttachments: true });
  const files = [];
  for (let i = 0; i < atts.length; i++) {
    const ct = atts[i].getContentType();
    if (/pdf|image\/(png|jpe?g|gif|webp|heic)/i.test(ct)) {
      files.push(atts[i]);
    }
  }
  const bodyText = stripHtml_(message.getBody() || message.getPlainBody() || '');
  return { files: files, bodyText: bodyText, from: message.getFrom(), date: message.getDate(), id: message.getId() };
}

function stripHtml_(html) {
  return String(html)
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    .replace(/[ \t]+/g, ' ').replace(/\n\s*\n\s*\n/g, '\n\n').trim();
}

/** ラベルを張り替える（対象を外し、指定ラベルを付与）。 */
function moveLabel_(message, toLabelName) {
  const thread = message.getThread();
  const target = GmailApp.getUserLabelByName(CONFIG.LABEL.TARGET);
  const to = getOrCreateLabel_(toLabelName);
  if (target) thread.removeLabel(target);
  thread.addLabel(to);
}

function getOrCreateLabel_(name) {
  return GmailApp.getUserLabelByName(name) || GmailApp.createLabel(name);
}
