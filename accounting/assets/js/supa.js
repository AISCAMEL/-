/* =========================================================================
 * supa.js ― Supabase バックエンド（Auth＋RLS）クライアント（依存ゼロ・fetch）
 *   A.supa : 設定・ログイン・台帳選択・スナップショットの push/pull を提供。
 *   自前サーバー(sync.js)の代わりに、Supabaseのマネージドなクラウドを使う。
 * ========================================================================= */
window.A = window.A || {};
A.supa = (function () {
  'use strict';
  const db = A.db, S = A.store;
  const LS = 'aizu_supa_session';

  const cfg = () => { const s = S.settings.get(); return { url: (s.supaUrl || '').replace(/\/$/, ''), key: s.supaAnonKey || '' }; };
  const configured = () => { const c = cfg(); return !!(c.url && c.key); };

  const getSession = () => { try { return JSON.parse(localStorage.getItem(LS) || 'null'); } catch (e) { return null; } };
  const setSession = (s) => { try { s ? localStorage.setItem(LS, JSON.stringify(s)) : localStorage.removeItem(LS); } catch (e) {} };
  const currentEmail = () => { const s = getSession(); return s && s.user ? s.user.email : ''; };
  const isAuthed = () => { const s = getSession(); return !!(s && s.access_token); };

  const authFetch = async (path, opts) => {
    const c = cfg(); opts = opts || {};
    const r = await fetch(c.url + path, Object.assign({}, opts, { headers: Object.assign({ apikey: c.key, 'Content-Type': 'application/json' }, opts.headers || {}) }));
    let data = null; try { data = await r.json(); } catch (e) {}
    return { status: r.status, ok: r.ok, data };
  };
  const restHeaders = () => { const c = cfg(); const s = getSession(); return { apikey: c.key, Authorization: 'Bearer ' + ((s && s.access_token) || c.key), 'Content-Type': 'application/json' }; };

  /* ---- 認証 ---- */
  const signUp = async (email, password) => {
    const r = await authFetch('/auth/v1/signup', { method: 'POST', body: JSON.stringify({ email, password }) });
    if (!r.ok) return { ok: false, message: (r.data && (r.data.msg || r.data.error_description || r.data.message)) || ('HTTP ' + r.status) };
    if (r.data.access_token) { setSession(r.data); return { ok: true, session: true }; }
    return { ok: true, session: false }; // 確認メール待ち
  };
  const signIn = async (email, password) => {
    const r = await authFetch('/auth/v1/token?grant_type=password', { method: 'POST', body: JSON.stringify({ email, password }) });
    if (!r.ok || !r.data.access_token) return { ok: false, message: (r.data && (r.data.error_description || r.data.msg || r.data.message)) || 'ログインに失敗しました' };
    setSession(r.data); return { ok: true };
  };
  const refresh = async () => {
    const s = getSession(); if (!s || !s.refresh_token) return false;
    const r = await authFetch('/auth/v1/token?grant_type=refresh_token', { method: 'POST', body: JSON.stringify({ refresh_token: s.refresh_token }) });
    if (r.ok && r.data.access_token) { setSession(r.data); return true; }
    return false;
  };
  const signOut = () => { setSession(null); };

  /* ---- 台帳 ---- */
  const rpc = async (fn, args) => { const r = await fetch(cfg().url + '/rest/v1/rpc/' + fn, { method: 'POST', headers: restHeaders(), body: JSON.stringify(args || {}) }); let d = null; try { d = await r.json(); } catch (e) {} return { status: r.status, ok: r.ok, data: d }; };
  const myLedgers = async () => { const r = await rpc('my_ledgers', {}); return r.ok && Array.isArray(r.data) ? r.data : []; };
  const createLedger = async (name) => { const r = await rpc('create_ledger', { p_name: name || '会計帳簿' }); if (!r.ok) return { ok: false, message: (r.data && r.data.message) || ('HTTP ' + r.status) }; return { ok: true, id: r.data }; };
  const addMember = async (ledgerId, email) => { const r = await rpc('add_member', { p_ledger: ledgerId, p_email: email }); return r.ok ? { ok: true } : { ok: false, message: (r.data && r.data.message) || ('HTTP ' + r.status) }; };

  /* ---- 同期 ---- */
  const pull = async (ledgerId) => {
    const id = ledgerId || S.settings.get().supaLedgerId; if (!id) return { ok: false, message: '台帳が未選択です' };
    const r = await fetch(cfg().url + '/rest/v1/ledgers?id=eq.' + id + '&select=data,version,name', { headers: restHeaders() });
    const rows = await r.json().catch(() => []);
    if (!r.ok) return { ok: false, message: 'HTTP ' + r.status };
    if (!rows.length) return { ok: false, message: '台帳が見つかりません（権限またはID）' };
    const row = rows[0];
    if (row.data && row.data.data) await db.importAll(row.data, { replace: true });
    await S.settings.save({ supaLedgerId: id, supaLedgerName: row.name, supaVersion: row.version });
    return { ok: true, version: row.version };
  };
  const push = async (ledgerId) => {
    const s = S.settings.get(); const id = ledgerId || s.supaLedgerId; if (!id) return { ok: false, message: '台帳が未選択です' };
    const base = s.supaVersion || 0;
    const snapshot = await db.exportAll();
    const body = JSON.stringify({ data: snapshot, version: base + 1, updated_at: new Date().toISOString() });
    const r = await fetch(cfg().url + '/rest/v1/ledgers?id=eq.' + id + '&version=eq.' + base, { method: 'PATCH', headers: Object.assign(restHeaders(), { Prefer: 'return=representation' }), body });
    const rows = await r.json().catch(() => []);
    if (!r.ok) return { ok: false, message: 'HTTP ' + r.status + ((rows && rows.message) ? '：' + rows.message : '') };
    if (!rows.length) return { ok: false, conflict: true, message: '他の端末が先に更新しています。取得（pull）してからやり直してください。' };
    await S.settings.save({ supaVersion: rows[0].version });
    return { ok: true, version: rows[0].version };
  };

  /* ---- ログイン画面（Supabaseバックエンド有効時に起動時表示） ---- */
  const showLogin = (mount) => new Promise((resolve) => {
    const el = A.util.el, ui = A.ui;
    const email = el('input', { type: 'email', placeholder: 'メールアドレス', autocomplete: 'username' });
    const pass = el('input', { type: 'password', placeholder: 'パスワード', autocomplete: 'current-password' });
    const msg = el('div.small');
    const ledgerBox = el('div');
    const setMsg = (t, cls) => { msg.textContent = t || ''; msg.className = 'small ' + (cls || ''); };

    const chooseLedger = async () => {
      setMsg('台帳を読み込み中…', 'muted');
      const list = await myLedgers();
      ledgerBox.innerHTML = '';
      const sel = el('select');
      list.forEach((l) => sel.appendChild(el('option', { value: l.id, text: `${l.name}（更新 ${(l.updated_at || '').slice(0, 10)}）` })));
      const open = el('button.btn.primary', {
        text: list.length ? 'この台帳を開く' : '台帳がありません', disabled: !list.length,
        onclick: async () => { const r = await pull(sel.value); if (!r.ok) return setMsg(r.message, 'ng'); await S.settings.save({ cloudBackend: 'supabase' }); overlay.remove(); resolve(); },
      });
      const newName = el('input', { type: 'text', placeholder: '新しい台帳名（例：合同会社アイズ）' });
      const create = el('button.btn', {
        text: '＋ 新規作成して開始', onclick: async () => {
          const c = await createLedger(newName.value || '会計帳簿'); if (!c.ok) return setMsg(c.message, 'ng');
          const r = await push(c.id); // 現在のローカルデータを新台帳へ保存
          await S.settings.save({ cloudBackend: 'supabase', supaLedgerId: c.id });
          if (!r.ok && !r.conflict) return setMsg('作成しましたが初回保存に失敗：' + r.message, 'ng');
          overlay.remove(); resolve();
        },
      });
      ledgerBox.appendChild(el('div.card', {}, [
        el('h3', { text: '台帳を選択' }),
        list.length ? el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '既存の台帳' }), sel]), open]) : el('p.muted.small', { text: 'まだ台帳がありません。下で新規作成してください。' }),
        el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '新規台帳' }), newName]), create]),
      ]));
      setMsg('', '');
    };

    const doSignIn = async () => { setMsg('サインイン中…', 'muted'); const r = await signIn(email.value.trim(), pass.value); if (!r.ok) return setMsg(r.message, 'ng'); chooseLedger(); };
    const doSignUp = async () => {
      if (!email.value || pass.value.length < 6) return setMsg('メールと6文字以上のパスワードを入力してください', 'ng');
      setMsg('登録中…', 'muted'); const r = await signUp(email.value.trim(), pass.value);
      if (!r.ok) return setMsg(r.message, 'ng');
      if (r.session) chooseLedger(); else setMsg('確認メールを送信しました。メール内のリンクで認証後、サインインしてください。', 'ok');
    };

    const card = el('div.login-box.wide', {}, [
      el('h2', { text: 'クラウドにログイン（Supabase）' }),
      el('p.muted.small', { text: S.settings.get().name || '会計' }),
      el('label', {}, [el('span', { text: 'メールアドレス' }), email]),
      el('label', {}, [el('span', { text: 'パスワード' }), pass]),
      el('div.quick-row', {}, [el('button.btn.primary', { text: 'サインイン', onclick: doSignIn }), el('button.btn', { text: '新規登録', onclick: doSignUp })]),
      msg, ledgerBox,
      el('div.quick-row', {}, [el('button.btn.sm', { text: 'オフラインで使う（同期しない）', onclick: async () => { await S.settings.save({ cloudBackend: '' }); overlay.remove(); resolve(); } })]),
    ]);
    const overlay = el('div.login-screen', {}, [card]);
    mount.innerHTML = '';
    mount.appendChild(overlay);
    if (isAuthed()) chooseLedger();
  });

  return { cfg, configured, getSession, isAuthed, currentEmail, signUp, signIn, signOut, refresh, myLedgers, createLedger, addMember, pull, push, showLogin };
})();
