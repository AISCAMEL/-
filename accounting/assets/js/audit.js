/* =========================================================================
 * audit.js ― 訂正・削除履歴（監査ログ）
 * 電子帳簿保存法が求める「訂正・削除の事実と内容の記録」を残す。
 * 記録自体は追記のみ（ユーザーからは削除・改変できない）。
 * ========================================================================= */
window.A = window.A || {};

A.audit = (function () {
  'use strict';
  const db = A.db;

  const ENTITY = { journal: '仕訳', invoice: '請求/見積', asset: '固定資産', attachment: '証憑', payslip: '給与', settings: '設定', opening: '期首残高' };
  const ACTION = { create: '作成', update: '訂正', delete: '削除' };

  // action: create|update|delete, entity, entityId, summary（内容の要約）
  const log = async (action, entity, entityId, summary) => {
    try {
      await db.put('auditlog', {
        id: 'lg_' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36),
        ts: new Date().toISOString(),
        action, entity, entityId: entityId || '', summary: summary || '',
      });
    } catch (e) { /* ログ失敗は本処理を止めない */ }
  };

  const loadAll = async () => {
    const list = await db.all('auditlog');
    list.sort((a, b) => (b.ts || '').localeCompare(a.ts || ''));
    return list;
  };

  return { log, loadAll, ENTITY, ACTION };
})();
