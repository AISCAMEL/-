/* =========================================================================
 * sync.js ― クラウド同期クライアント（サーバーとデータをやり取り）
 * server/server.js と通信し、全データのスナップショットを push/pull する。
 * ========================================================================= */
window.A = window.A || {};

A.sync = (function () {
  'use strict';
  const db = A.db, S = A.store;

  const cfg = () => {
    const s = S.settings.get();
    return { url: (s.syncUrl || '').replace(/\/$/, ''), ws: s.syncWorkspace || '', token: s.syncToken || '', version: s.syncVersion || 0 };
  };
  const post = async (path, body) => {
    const c = cfg();
    if (!c.url) throw new Error('サーバーURLが未設定です');
    const res = await fetch(c.url + path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    let data = {};
    try { data = await res.json(); } catch (e) {}
    return { status: res.status, data };
  };

  const health = async () => {
    const c = cfg();
    if (!c.url) throw new Error('サーバーURLが未設定です');
    const res = await fetch(c.url + '/api/health');
    return res.ok;
  };

  // ローカル → サーバー
  const push = async () => {
    const c = cfg();
    if (!c.ws) throw new Error('ワークスペースが未設定です');
    const snapshot = await db.exportAll();
    const r = await post('/api/push', { workspace: c.ws, token: c.token, baseVersion: c.version, data: snapshot });
    if (r.status === 409) return { ok: false, conflict: true, serverVersion: r.data.version, message: r.data.error };
    if (r.status !== 200) return { ok: false, message: (r.data && r.data.error) || ('HTTP ' + r.status) };
    const patch = { syncVersion: r.data.version };
    if (r.data.token && !c.token) patch.syncToken = r.data.token; // 新規作成時に発行されたトークンを保存
    await S.settings.save(patch);
    return { ok: true, version: r.data.version, token: patch.syncToken };
  };

  // サーバー → ローカル（上書き）
  const pull = async () => {
    const c = cfg();
    if (!c.ws) throw new Error('ワークスペースが未設定です');
    const r = await post('/api/pull', { workspace: c.ws, token: c.token });
    if (r.status === 404) return { ok: false, message: 'サーバーにこのワークスペースのデータがありません' };
    if (r.status !== 200) return { ok: false, message: (r.data && r.data.error) || ('HTTP ' + r.status) };
    const snapshot = r.data.data; // {_app,...,data:{stores}}
    // 取り込み前に接続設定を退避（共有データで上書きされても手元の接続先を保つ）
    const keep = { syncUrl: c.url, syncWorkspace: c.ws, syncToken: c.token };
    await db.importAll(snapshot, { replace: true });
    await S.settings.save({ ...keep, syncVersion: r.data.version });
    await S.accounts.loadAll();
    return { ok: true, version: r.data.version };
  };

  return { health, push, pull };
})();
