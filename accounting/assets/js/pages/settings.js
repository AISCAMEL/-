/* settings.js ― 会社情報・勘定科目マスタ・バックアップ（書出/取込） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, db = A.db;
  const CAT = A.accounts.CATEGORIES;

  /* ---- 勘定科目の追加・編集 ------------------------------------------- */
  const accEditor = (existing) => {
    const a = existing || { code: '', name: '', category: 'expense', default_tax: 'purchase10' };
    const code = el('input', { type: 'text', value: a.code, placeholder: '例：610', disabled: !!existing });
    const name = el('input', { type: 'text', value: a.name, placeholder: '科目名' });
    const cat = el('select');
    Object.keys(CAT).forEach((k) => { const o = el('option', { value: k, text: CAT[k].label }); if (k === a.category) o.selected = true; cat.appendChild(o); });
    const tax = el('select');
    Object.keys(A.accounts.TAX_CATEGORIES).forEach((k) => { const o = el('option', { value: k, text: A.accounts.TAX_CATEGORIES[k].label }); if (k === a.default_tax) o.selected = true; tax.appendChild(o); });
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label', {}, [el('span', { text: 'コード' }), code]), el('label.grow', {}, [el('span', { text: '科目名' }), name])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '区分' }), cat]), el('label', {}, [el('span', { text: '既定税区分' }), tax])]),
    ]);
    const save = async () => {
      if (!code.value || !name.value) return ui.toast('コードと科目名を入力してください', 'err');
      await S.accounts.save({ code: code.value, name: name.value, category: cat.value, default_tax: tax.value });
      m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal(existing ? '科目の編集' : '科目の追加', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', { text: '保存', onclick: save })],
    });
  };

  ui.register('settings', async () => {
    const s = S.settings.get();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('設定', []));

    /* --- 会社情報 --- */
    const f = {};
    const field = (key, label, ph) => { const i = el('input', { type: 'text', value: s[key] || '', placeholder: ph || '' }); f[key] = i; return el('label', {}, [el('span', { text: label }), i]); };
    const fsMonth = el('select');
    for (let m = 1; m <= 12; m++) { const o = el('option', { value: m, text: m + '月' }); if (m === (s.fiscalStartMonth || 4)) o.selected = true; fsMonth.appendChild(o); }
    const bank = el('textarea', { rows: 2 }); bank.value = s.bank || '';
    const addr = el('textarea', { rows: 2 }); addr.value = s.address || '';

    const companyCard = el('div.card', {}, [
      el('h2', { text: '会社情報（請求書に表示）' }),
      el('div.form-row', {}, [field('name', '会社名'), field('invoiceRegNo', '適格請求書 登録番号', 'T + 13桁')]),
      el('div.form-row', {}, [field('tel', '電話番号'), field('email', 'メール')]),
      el('label', {}, [el('span', { text: '住所' }), addr]),
      el('label', {}, [el('span', { text: '振込先（請求書の備考に表示）' }), bank]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '期首月（会計年度の開始）' }), fsMonth])]),
      el('button.btn.primary', {
        text: '会社情報を保存', onclick: async () => {
          await S.settings.save({ name: f.name.value, invoiceRegNo: f.invoiceRegNo.value, tel: f.tel.value, email: f.email.value, address: addr.value, bank: bank.value, fiscalStartMonth: Number(fsMonth.value) });
          ui.toast('保存しました', 'ok');
        },
      }),
    ]);
    wrap.appendChild(companyCard);

    /* --- 勘定科目マスタ --- */
    const accCard = el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: '勘定科目マスタ' }), el('button.btn.sm', { text: '＋ 科目を追加', onclick: () => accEditor(null) })]),
    ]);
    accCard.appendChild(ui.table([
      { key: 'code', label: 'コード', render: (r) => r.code },
      { key: 'name', label: '科目名', render: (r) => r.name },
      { key: 'cat', label: '区分', render: (r) => CAT[r.category].label },
      { key: 'tax', label: '既定税区分', render: (r) => A.accounts.TAX_CATEGORIES[r.default_tax].label },
      {
        key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
          el('button.icon-btn', { text: '✎', onclick: () => accEditor(r) }),
          el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm(`科目「${r.name}」を削除しますか？`)) { await S.accounts.remove(r.code); ui.toast('削除しました'); ui.renderRoute(); } } }),
        ]),
      },
    ], S.accounts.all(), { empty: '科目がありません' }));
    wrap.appendChild(accCard);

    /* --- バックアップ --- */
    const backupCard = el('div.card', {}, [
      el('h2', { text: 'バックアップ・データ管理' }),
      el('p.muted.small', { text: 'データはこのブラウザ内にのみ保存されます。定期的に書き出して保管してください。' }),
      el('div.quick-row', {}, [
        el('button.btn.primary', {
          text: '⬇ バックアップを書き出す', onclick: async () => {
            const data = await db.exportAll();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const a = el('a', { href: URL.createObjectURL(blob), download: `kaikei-backup-${U.today()}.json` });
            document.body.appendChild(a); a.click(); a.remove();
            ui.toast('バックアップを書き出しました', 'ok');
          },
        }),
        (() => {
          const file = el('input', { type: 'file', accept: '.json', style: 'display:none' });
          file.addEventListener('change', async () => {
            const file0 = file.files[0]; if (!file0) return;
            if (!await ui.confirm('現在のデータを、取り込むファイルの内容で置き換えます。よろしいですか？')) return;
            try {
              const payload = JSON.parse(await file0.text());
              await db.importAll(payload, { replace: true });
              await S.settings.load(); await S.accounts.loadAll();
              ui.toast('取り込みました', 'ok'); ui.go('dashboard');
            } catch (e) { ui.toast('取り込みに失敗しました: ' + e.message, 'err'); }
          });
          const btn = el('button.btn', { text: '⬆ バックアップを取り込む', onclick: () => file.click() });
          return el('span', {}, [btn, file]);
        })(),
        el('button.btn.danger', {
          text: '⚠ 全データを初期化', onclick: async () => {
            if (!await ui.confirm('すべての仕訳・請求書・取引先を削除して初期状態に戻します。取り消せません。よろしいですか？')) return;
            for (const name in db.STORES) await db.clear(name);
            await db.seedIfEmpty(); await S.settings.load(); await S.accounts.loadAll();
            ui.toast('初期化しました'); ui.go('dashboard');
          },
        }),
      ]),
    ]);
    wrap.appendChild(backupCard);

    /* --- クラウド同期（複数人・複数端末で共有） --- */
    const syncUrl = el('input', { type: 'text', value: s.syncUrl || '', placeholder: 'http://localhost:8787' });
    const syncWs = el('input', { type: 'text', value: s.syncWorkspace || '', placeholder: '例：aizu-2026' });
    const syncToken = el('input', { type: 'text', value: s.syncToken || '', placeholder: '共有の合言葉（初回に登録）' });
    const syncStatus = el('div.muted.small', { text: s.syncVersion ? `最終同期バージョン：${s.syncVersion}` : '未同期' });
    const saveSync = async () => { await S.settings.save({ syncUrl: syncUrl.value.trim(), syncWorkspace: syncWs.value.trim(), syncToken: syncToken.value.trim() }); };

    const syncCard = el('div.card', {}, [
      el('h2', { text: 'クラウド同期（複数人で共有）' }),
      el('p.muted.small', { html: '同梱の同期サーバー（<code>server/server.js</code>）を起動し、同じワークスペース名を設定すると、複数の端末・担当者で同じ帳簿を共有できます。' }),
      el('div.form-row', {}, [
        el('label.grow', {}, [el('span', { text: 'サーバーURL' }), syncUrl]),
        el('label', {}, [el('span', { text: 'ワークスペース' }), syncWs]),
        el('label', {}, [el('span', { text: 'トークン' }), syncToken]),
      ]),
      syncStatus,
      el('div.quick-row', {}, [
        el('button.btn', {
          text: '接続確認', onclick: async () => {
            await saveSync();
            try { ui.toast(await A.sync.health() ? 'サーバーに接続できました' : '接続できません', 'ok'); }
            catch (e) { ui.toast(e.message, 'err'); }
          },
        }),
        el('button.btn.primary', {
          text: '⬆ アップロード（push）', onclick: async () => {
            await saveSync();
            try {
              const r = await A.sync.push();
              if (r.ok) { ui.toast(`アップロードしました（v${r.version}）`, 'ok'); ui.renderRoute(); }
              else if (r.conflict) ui.toast('他の端末で更新済みです。先にダウンロードしてください', 'err');
              else ui.toast('失敗：' + r.message, 'err');
            } catch (e) { ui.toast(e.message, 'err'); }
          },
        }),
        el('button.btn', {
          text: '⬇ ダウンロード（pull）', onclick: async () => {
            await saveSync();
            if (!await ui.confirm('サーバーのデータで、この端末のデータを上書きします。よろしいですか？')) return;
            try {
              const r = await A.sync.pull();
              if (r.ok) { ui.toast(`ダウンロードしました（v${r.version}）`, 'ok'); ui.go('dashboard'); }
              else ui.toast('失敗：' + r.message, 'err');
            } catch (e) { ui.toast(e.message, 'err'); }
          },
        }),
      ]),
    ]);
    wrap.appendChild(syncCard);
    return wrap;
  });
})();
