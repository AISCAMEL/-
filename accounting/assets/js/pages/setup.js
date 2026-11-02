/* setup.js ― 初期セットアップ・ウィザード（初回起動時に基本情報をまとめて設定） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const shrink = (file, max) => new Promise((res) => {
    const r = new FileReader();
    r.onload = () => { const im = new Image(); im.onload = () => { const sc = Math.min(1, max / Math.max(im.width, im.height)); const w = Math.round(im.width * sc), h = Math.round(im.height * sc); const c = el('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(im, 0, 0, w, h); try { res(c.toDataURL('image/png')); } catch (e) { res(r.result); } }; im.onerror = () => res(r.result); im.src = r.result; };
    r.readAsDataURL(file);
  });

  const show = () => new Promise((resolve) => {
    const s = S.settings.get();
    const name = el('input', { type: 'text', value: (s.name && s.name !== '会計ソフト') ? s.name : '', placeholder: '例：合同会社アイズ' });
    const fs = el('select'); for (let m = 1; m <= 12; m++) { const o = el('option', { value: m, text: m + '月' }); if (m === (s.fiscalStartMonth || 4)) o.selected = true; fs.appendChild(o); }
    const reg = el('input', { type: 'text', value: (s.invoiceRegNo && s.invoiceRegNo !== 'T0000000000000') ? s.invoiceRegNo : '', placeholder: 'T + 13桁（任意）' });
    const regMsg = el('div.small');
    reg.addEventListener('input', () => { const v = U.checkInvoiceRegNo(reg.value); regMsg.textContent = reg.value ? v.msg : ''; regMsg.className = 'small ' + (v.valid ? 'ok' : 'ng'); });
    const logoImg = el('img', { style: 'max-height:50px;max-width:160px;object-fit:contain;display:none' });
    const logoFile = el('input', { type: 'file', accept: 'image/*' });
    let logoUrl = '';
    logoFile.addEventListener('change', async () => { const f = logoFile.files[0]; if (!f) return; logoUrl = await shrink(f, 300); logoImg.src = logoUrl; logoImg.style.display = 'inline-block'; });
    const pass = el('input', { type: 'password', placeholder: '4桁以上（任意）' });
    const pass2 = el('input', { type: 'password', placeholder: '確認' });

    const body = el('div.editor', {}, [
      el('p.muted.small', { text: '最初に基本情報を設定します。あとで「設定」からいつでも変更できます。' }),
      el('label', {}, [el('span', { text: '会社名 *' }), name]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '期首月（会計年度の開始）' }), fs]), el('label.grow', {}, [el('span', { text: '適格請求書 登録番号' }), reg])]),
      regMsg,
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: 'ロゴ（請求書に表示・任意）' }), logoFile]), logoImg]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: 'ログインパスコード（任意）' }), pass]), el('label', {}, [el('span', { text: '確認' }), pass2])]),
      el('p.muted.small', { text: 'パスコードを設定すると、次回以降アプリを開く際にロックがかかります。' }),
    ]);

    const finish = async (skip) => {
      if (!skip) {
        if (!name.value.trim()) return ui.toast('会社名を入力してください', 'err');
        if (pass.value) {
          if (pass.value.length < 4) return ui.toast('パスコードは4桁以上にしてください', 'err');
          if (pass.value !== pass2.value) return ui.toast('パスコードが一致しません', 'err');
        }
      }
      const patch = { setupDone: true };
      if (!skip) {
        patch.name = name.value.trim() || s.name;
        patch.fiscalStartMonth = Number(fs.value) || 4;
        if (reg.value.trim()) patch.invoiceRegNo = reg.value.trim();
        if (logoUrl) patch.logo = logoUrl;
      }
      await S.settings.save(patch);
      if (!skip && pass.value && A.auth) { await A.auth.setPasscode(pass.value); A.auth.markAuthed(); }
      m.close();
      resolve();
    };

    const m = ui.modal('はじめにセットアップ', body, {
      footer: [
        el('button.btn', { text: 'スキップ', onclick: () => finish(true) }),
        el('button.btn.primary', { text: '保存して開始', onclick: () => finish(false) }),
      ],
    });
  });

  // 初回のみ（未完了かつデータが空）ウィザードを表示。既存データありは完了扱い。
  const maybeRun = async () => {
    const s = S.settings.get();
    if (s.setupDone) return;
    const journals = await S.journals.loadAll();
    if (journals.length) { await S.settings.save({ setupDone: true }); return; }
    await show();
  };

  A.setup = { show, maybeRun };
})();
