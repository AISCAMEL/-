/* =========================================================================
 * auth.js ― 簡易ログイン（パスコードロック）
 * 共有端末での不用意なアクセスを防ぐための簡易ロック。
 * パスコードはハッシュ化して保存（平文は保持しない）。セッション中のみ解錠状態を保持。
 * ※ これは端末ローカルの簡易ロックです。複数ユーザーの本格認証は同期サーバー側で行います。
 * ========================================================================= */
window.A = window.A || {};

A.auth = (function () {
  'use strict';
  const S = A.store;
  const SKEY = 'kaikei_authed';

  // SHA-256（利用不可な環境ではFNVフォールバック）
  const fnv = (str) => { let h = 0x811c9dc5; for (let i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = (h * 0x01000193) >>> 0; } return ('00000000' + h.toString(16)).slice(-8).repeat(4); };
  const sha = async (text) => {
    try {
      if (window.crypto && crypto.subtle) {
        const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
        return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
      }
    } catch (e) { /* fallthrough */ }
    return 'fnv:' + fnv(text);
  };
  const randSalt = () => {
    try { return [...crypto.getRandomValues(new Uint8Array(8))].map((b) => b.toString(16).padStart(2, '0')).join(''); }
    catch (e) { return String(Date.now()) + Math.random().toString(16).slice(2); }
  };

  const isEnabled = () => { const s = S.settings.get(); return !!(s.lockEnabled && s.passHash); };
  const isAuthed = () => { try { return sessionStorage.getItem(SKEY) === '1'; } catch (e) { return false; } };
  const markAuthed = () => { try { sessionStorage.setItem(SKEY, '1'); } catch (e) {} };

  const setPasscode = async (pass) => {
    const salt = randSalt();
    const hash = await sha(salt + pass);
    await S.settings.save({ lockEnabled: true, passSalt: salt, passHash: hash });
  };
  const disable = async () => { await S.settings.save({ lockEnabled: false, passHash: '', passSalt: '' }); };
  const verify = async (pass) => {
    const s = S.settings.get();
    if (!s.passHash) return true;
    return (await sha((s.passSalt || '') + pass)) === s.passHash;
  };
  const logout = () => { try { sessionStorage.removeItem(SKEY); } catch (e) {} location.reload(); };

  // ログイン画面を表示し、正しいパスコードで解錠されたら resolve
  const showLogin = (mount, companyName) => new Promise((resolve) => {
    const el = A.util.el;
    const input = el('input.login-input', { type: 'password', inputmode: 'numeric', placeholder: 'パスコード', autofocus: true });
    const err = el('div.login-err');
    const submit = async () => {
      err.textContent = '';
      if (await verify(input.value)) { markAuthed(); resolve(); }
      else { err.textContent = 'パスコードが正しくありません'; input.value = ''; input.focus(); }
    };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
    const box = el('div.login-box', {}, [
      el('div.login-brand', {}, [el('div.brand-mark', { text: '会計' }), el('div.login-title', { text: companyName || 'クラウド会計' })]),
      el('div.login-sub', { text: 'ログイン' }),
      input, err,
      el('button.btn.primary.login-btn', { text: 'ログイン', onclick: submit }),
    ]);
    mount.innerHTML = '';
    mount.appendChild(el('div.login-screen', {}, [box]));
    setTimeout(() => input.focus(), 50);
  });

  return { isEnabled, isAuthed, markAuthed, setPasscode, disable, verify, logout, showLogin };
})();
