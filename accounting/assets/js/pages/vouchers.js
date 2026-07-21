/* vouchers.js ― 証憑（領収書・請求書など）の保存と検索（電子帳簿保存法対応） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const readAsDataUrl = (file) => new Promise((resolve, reject) => {
    const r = new FileReader(); r.onload = () => resolve(r.result); r.onerror = () => reject(r.error); r.readAsDataURL(file);
  });
  const fmtSize = (n) => n > 1e6 ? (n / 1e6).toFixed(1) + 'MB' : Math.ceil(n / 1024) + 'KB';

  const preview = (att) => {
    let content;
    if ((att.mime || '').startsWith('image/')) content = el('img.att-preview', { src: att.dataUrl });
    else if ((att.mime || '') === 'application/pdf') content = el('iframe.att-preview', { src: att.dataUrl });
    else content = el('p', { text: 'この形式はプレビューできません。ダウンロードしてご確認ください。' });
    ui.modal(att.filename, el('div', {}, [
      content,
      el('div.att-meta', { html: `取引日 ${U.fmtDate(att.date)}　金額 ¥${U.yen(att.amount)}　取引先 ${U.esc(att.partner || '—')}<br>登録日時 ${new Date(att.createdAt).toLocaleString('ja-JP')}` }),
    ]), {
      footer: [el('a.btn', { href: att.dataUrl, download: att.filename, text: '⬇ ダウンロード' })],
    });
  };

  // アップロードフォーム
  const uploadModal = () => {
    const file = el('input', { type: 'file', accept: 'image/*,application/pdf' });
    const date = el('input', { type: 'date', value: U.today() });
    const amount = el('input.amt-in', { type: 'text', inputmode: 'numeric', placeholder: '取引金額' });
    const partner = el('input', { type: 'text', placeholder: '取引先' });
    const note = el('input', { type: 'text', placeholder: '摘要（任意）' });
    amount.addEventListener('blur', () => { amount.value = amount.value ? U.yen(U.parseYen(amount.value)) : ''; });
    const body = el('div.editor', {}, [
      el('label', {}, [el('span', { text: '証憑ファイル（画像・PDF）' }), file]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '取引年月日' }), date]), el('label', {}, [el('span', { text: '取引金額' }), amount])]),
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '取引先' }), partner]), el('label.grow', {}, [el('span', { text: '摘要' }), note])]),
      el('p.muted.small', { text: '電子帳簿保存法の検索要件（日付・金額・取引先）に対応して保存します。' }),
    ]);
    const save = async () => {
      const f = file.files[0];
      if (!f) return ui.toast('ファイルを選択してください', 'err');
      if (f.size > 15e6) return ui.toast('ファイルが大きすぎます（15MBまで）', 'err');
      const dataUrl = await readAsDataUrl(f);
      await S.attachments.save({ date: date.value, amount: U.parseYen(amount.value), partner: partner.value, note: note.value, filename: f.name, mime: f.type, size: f.size, dataUrl });
      m.close(); ui.toast('証憑を保存しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal('証憑をアップロード', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', { text: '保存', onclick: save })],
    });
  };

  ui.register('vouchers', async () => {
    const all = await S.attachments.loadAll();
    const journals = await S.journals.loadAll();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('証憑（電帳法対応）', [
      el('button.btn.primary', { text: '＋ 証憑をアップロード', onclick: () => uploadModal() }),
    ]));

    // 検索（日付・金額・取引先/ファイル名）
    const fFrom = el('input', { type: 'date' });
    const fTo = el('input', { type: 'date' });
    const fAmt = el('input.amt-in', { type: 'text', inputmode: 'numeric', placeholder: '金額' });
    const fKw = el('input', { type: 'text', placeholder: '取引先・ファイル名・摘要' });
    const listBox = el('div');
    const draw = () => {
      const amt = U.parseYen(fAmt.value);
      const kw = fKw.value.trim();
      const list = all.filter((a) =>
        U.inRange(a.date, fFrom.value || null, fTo.value || null) &&
        (!amt || a.amount === amt) &&
        (!kw || [a.partner, a.filename, a.note].some((x) => (x || '').includes(kw))));
      listBox.innerHTML = '';
      listBox.appendChild(ui.table([
        { key: 'date', label: '取引日', render: (r) => U.fmtDate(r.date) },
        { key: 'partner', label: '取引先', render: (r) => r.partner || '—' },
        { key: 'amount', label: '金額', align: 'right', render: (r) => r.amount ? '¥' + U.yen(r.amount) : '—' },
        { key: 'file', label: 'ファイル', render: (r) => r.filename },
        { key: 'size', label: 'サイズ', align: 'right', render: (r) => fmtSize(r.size) },
        { key: 'link', label: '仕訳', render: (r) => r.journalId && journals.find((j) => j.id === r.journalId) ? el('span.badge.in', { text: '連携' }) : '' },
        { key: 'reg', label: '登録日時', render: (r) => new Date(r.createdAt).toLocaleDateString('ja-JP') },
        {
          key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
            el('button.icon-btn', { text: '👁', title: 'プレビュー', onclick: () => preview(r) }),
            el('a.icon-btn', { href: r.dataUrl, download: r.filename, text: '⬇', title: 'ダウンロード' }),
            el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この証憑を削除しますか？')) { await S.attachments.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); } } }),
          ]),
        },
      ], list, { empty: '証憑がありません' }));
    };
    [fFrom, fTo, fAmt, fKw].forEach((i) => i.addEventListener('input', draw));

    wrap.appendChild(el('div.card', {}, [
      el('div.search-bar', {}, [
        el('span.muted', { text: '検索' }),
        fFrom, el('span', { text: '〜' }), fTo,
        fAmt, fKw,
        el('button.btn.sm', { text: 'クリア', onclick: () => { [fFrom, fTo, fAmt, fKw].forEach((i) => i.value = ''); draw(); } }),
      ]),
    ]));
    const listCard = el('div.card'); listCard.appendChild(listBox); wrap.appendChild(listCard);
    draw();
    return wrap;
  });

  // 仕訳・経費エディタから使う添付ヘルパー（保存済み仕訳に証憑を紐づける）
  A.attachToJournal = async (journal, file) => {
    if (!file) return;
    const readAsDataUrl = (f) => new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = () => rej(r.error); r.readAsDataURL(f); });
    const dataUrl = await readAsDataUrl(file);
    const amount = S.journals.totals(journal).debit;
    await S.attachments.save({ journalId: journal.id, date: journal.date, amount, partner: '', note: journal.description || '', filename: file.name, mime: file.type, size: file.size, dataUrl });
  };
})();
