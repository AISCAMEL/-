/**
 * lib/chat-log.js  (CommonJS / サーバー共有)
 * ------------------------------------------------------------------
 * 相談チャットの会話ログを JSONL で保存する（任意機能）。
 * 目的: うまく答えられなかった質問を運用者が後から data/knowledge.csv へ
 *       手動追加していくための "材料集め"。
 *
 * プライバシー方針:
 *   - 既定では無効。環境変数 CHAT_LOG を真値にした時のみ記録する。
 *     (仕様定義書 17. セキュリティ/プライバシー配慮：既定で会話を保存しない)
 *   - IPアドレス等は記録しない。保存先は CHAT_LOG_DIR で変更可。
 *   - ログ書き込みの失敗はチャット応答に影響させない（握りつぶす）。
 */

'use strict';

const fs = require('fs');
const path = require('path');

/** ログ機能が有効か（CHAT_LOG=1/true/on で有効）。 */
function isEnabled() {
  const v = (process.env.CHAT_LOG || '').toLowerCase();
  return v === '1' || v === 'true' || v === 'on' || v === 'yes';
}

function getLogDir() {
  return process.env.CHAT_LOG_DIR || path.join(__dirname, '..', 'data', 'chat-logs');
}

/** YYYY-MM-DD（ローカル時刻）。日付ごとにファイルを分ける。 */
function dateStamp(d = new Date()) {
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

/**
 * 1往復分の会話を追記する。CHAT_LOG 無効時は何もしない。
 * @param {Object} rec
 * @param {Array}  rec.messages クライアントから届いた会話履歴（直近）
 * @param {string} rec.answer   今回のAI回答
 * @param {string} [rec.model]  応答に用いたモデルID
 */
function appendLog(rec) {
  if (!isEnabled()) return;
  try {
    const dir = getLogDir();
    fs.mkdirSync(dir, { recursive: true });

    const msgs = Array.isArray(rec.messages) ? rec.messages : [];
    const lastUser = [...msgs].reverse().find((m) => m && m.role === 'user');

    const entry = {
      ts: new Date().toISOString(),
      model: rec.model || null,
      turns: msgs.length,
      question: lastUser ? String(lastUser.content || '').slice(0, 2000) : '',
      answer: String(rec.answer || '').slice(0, 4000)
    };

    fs.appendFileSync(path.join(dir, `${dateStamp()}.jsonl`), JSON.stringify(entry) + '\n');
  } catch (_e) {
    /* ログ失敗はチャット本体に影響させない */
  }
}

module.exports = { appendLog, isEnabled, getLogDir, dateStamp };
