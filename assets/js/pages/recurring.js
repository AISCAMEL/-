/* recurring.js ― 請求書の定期自動作成（毎月のテンプレートから一括発行） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const RATE_OPTS = [{ v: 10, t: '10%' }, { v: 8, t: '8%(軽減)' }, { v: 0, t: '非課税/対象外' }];

  const templates = () => S.settings.get().recurringInvoices || [];
  const saveTemplates = (list) => S.settings.save({ recurringInvoices: list });

  /* ---- 月の計算ユーティリティ ---------------------------------------- */
  const curMonth = () => U.today().slice(0, 7);          // 'YYYY-MM'
  const dateOf = (ym, day) => `${ym}-${String(Math.min(28, Math.max(1, day || 1))).padStart(2, '0')}`;
  const nextMonth = (ym) => { let [y, m] = ym.split('-').map(Number); m += 1; if (m > 12) { y += 1; m = 1; } return `${y}-${String(m).padStart(2, '0')}`; };
  const monthLE = (a, b) => a <= b; // 'YYYY-MM' は辞書順＝時系列順

  // あるテンプレートの「未発行で、発行日が到来済み」の対象月を列挙（過去遡及はしない）
  const dueMonths = (t, today, nowYm) => {
    const months = [];
    let ym = t.lastGenerated ? nextMonth(t.lastGenerated) : nowYm; // 初回は当月から（過去は作らない）
    while (monthLE(ym, nowYm)) {
      if (dateOf(ym, t.day) <= today) months.push(ym); // 発行日が到来している月のみ
      if (ym === nowYm) break;
      ym = nextMonth(ym);
    }
    return months;
  };

  /* ---- 自動発行（アプリ起動時などに呼ぶ） -----------------------------
   * 設定 recurringAuto が false のときは何もしない。
   * 未発行かつ発行日到来済みの月について請求書を作成し、lastGenerated を更新。
   * 作成した請求書は未計上（下書き）。売上計上は請求書ページで確認して行う（承認制）。
   * 戻り値: { count, details:[{partnerName, month}] }
   * ------------------------------------------------------------------- */
  const autoIssue = async () => {
    const s = S.settings.get();
    if (s.recurringAuto === false) return { count: 0, details: [] };
    const list = (s.recurringInvoices || []).map((x) => ({ ...x }));
    if (!list.length) return { count: 0, details: [] };
    const today = U.today(), nowYm = curMonth();
    let count = 0; const details = [];
    for (const t of list) {
      for (const ym of dueMonths(t, today, nowYm)) {
        await S.invoices.save({ type: 'invoice', date: dateOf(ym, t.day), partnerName: t.partnerName, items: t.items, note: t.note, recurringId: t.id });
        t.lastGenerated = ym; count += 1; details.push({ partnerName: t.partnerName, month: ym });
      }
    }
    if (count) await saveTemplates(list);
    return { count, details };
  };

  A.recurring = { autoIssue };

  const itemRow = (it) => {
    it = it || { name: '', qty: 1, unitPrice: 0, taxRate: 10 };
    const name = el('input', { type: 'text', value: it.name || '', placeholder: '品目' });
    const qty = el('input.qty-in', { type: 'number', value: it.qty != null ? it.qty : 1 });
    const price = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: it.unitPrice ? U.yen(it.unitPrice) : '' });
    const rate = el('select.rate-sel'); RATE_OPTS.forEach((r) => { const o = el('option', { value: r.v, text: r.t }); if (r.v === Number(it.taxRate)) o.selected = true; rate.appendChild(o); });
    price.addEventListener('blur', () => { price.value = price.value ? U.yen(U.parseYen(price.value)) : ''; });
    const row = el('div.item-row', {}, [name, qty, price, rate, el('span'), el('button.icon-btn.del', { text: '×', onclick: () => row.remove() })]);
    row._read = () => ({ name: name.value, qty: Number(qty.value) || 0, unitPrice: U.parseYen(price.value), taxRate: Number(rate.value) });
    return row;
  };

  const editor = (existing) => {
    const t = existing || { partnerName: '', day: 1, items: [{ name: '', qty: 1, unitPrice: 0, taxRate: 10 }], note: '' };
    const pn = el('input', { type: 'text', value: t.partnerName || '', placeholder: '取引先名' });
    const day = el('input', { type: 'number', min: '1', max: '28', value: t.day || 1 });
    const itemsBox = el('div.items-box');
    (t.items || []).forEach((it) => itemsBox.appendChild(itemRow(it)));
    const note = el('textarea', { rows: 2 }); note.value = t.note || '';
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '取引先名' }), pn]), el('label', {}, [el('span', { text: '発行日(毎月)' }), day])]),
      el('div.items-head', {}, [el('span', { text: '品目' }), el('span', { text: '数量' }), el('span', { text: '単価' }), el('span', { text: '税率' }), el('span'), el('span')]),
      itemsBox,
      el('button.btn.sm', { text: '＋ 明細を追加', onclick: () => itemsBox.appendChild(itemRow()) }),
      el('label', {}, [el('span', { text: '備考' }), note]),
    ]);
    const m = ui.modal(existing ? '定期請求の編集' : '定期請求の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '保存', onclick: async () => {
          if (!pn.value.trim()) return ui.toast('取引先名を入力してください', 'err');
          const items = [...itemsBox.querySelectorAll('.item-row')].map((r) => r._read()).filter((i) => i.name || i.unitPrice);
          if (!items.length) return ui.toast('明細を入力してください', 'err');
          const list = templates().map((x) => ({ ...x }));
          const rec = { id: t.id || U.uid('rc'), partnerName: pn.value.trim(), day: Math.min(28, Math.max(1, Number(day.value) || 1)), items, note: note.value, lastGenerated: t.lastGenerated || '' };
          const idx = list.findIndex((x) => x.id === rec.id);
          if (idx >= 0) list[idx] = rec; else list.push(rec);
          await saveTemplates(list); m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
        },
      })],
    });
  };

  ui.register('recurring', async () => {
    const list = templates();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('定期請求（自動作成）', [el('button.btn.primary', { text: '＋ 定期請求を登録', onclick: () => editor(null) })]));

    // 対象月＆一括作成
    const ym = el('input', { type: 'month', value: U.today().slice(0, 7) });
    const genAll = async () => {
      const target = ym.value; if (!target) return;
      const pending = list.filter((t) => t.lastGenerated !== target);
      if (!pending.length) return ui.toast('対象月の未作成テンプレートはありません');
      if (!await ui.confirm(`${target} 分の請求書を ${pending.length} 件作成します。よろしいですか？`)) return;
      let n = 0;
      const next = list.map((x) => ({ ...x }));
      for (const t of pending) {
        const date = `${target}-${String(t.day || 1).padStart(2, '0')}`;
        await S.invoices.save({ type: 'invoice', date, partnerName: t.partnerName, items: t.items, note: t.note, recurringId: t.id });
        const idx = next.findIndex((x) => x.id === t.id); if (idx >= 0) next[idx].lastGenerated = target;
        n += 1;
      }
      await saveTemplates(next);
      ui.toast(`${n}件の請求書を作成しました`, 'ok');
      ui.go('invoices');
    };

    // 自動発行トグル（アプリを開いた時に未発行分を自動作成）
    const autoChk = el('input', { type: 'checkbox' });
    autoChk.checked = S.settings.get().recurringAuto !== false;
    autoChk.addEventListener('change', async () => { await S.settings.save({ recurringAuto: autoChk.checked }); ui.toast(autoChk.checked ? '自動発行をオンにしました' : '自動発行をオフにしました', 'ok'); });

    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('div.inline', {}, [el('span.muted', { text: '対象月' }), ym]), el('button.btn.primary', { text: '対象月の請求書をまとめて作成', onclick: genAll })]),
      el('label.inline', { style: 'gap:.4rem;cursor:pointer' }, [autoChk, el('span', { text: 'アプリを開いた時に、未発行の月を自動で発行する' })]),
      el('p.muted.small', { text: '登録したテンプレートから、毎月の請求書をまとめて作成できます（同じ月に二重作成しないよう「最終作成月」で管理）。自動発行は、発行日が到来した未発行の月だけを対象にします（過去には遡りません）。作成後は請求書ページで確認・PDF出力・売上計上できます。' }),
    ]));

    const card = el('div.card');
    card.appendChild(ui.table([
      { key: 'pn', label: '取引先', render: (r) => r.partnerName },
      { key: 'items', label: '品目', render: (r) => (r.items || []).map((i) => i.name).filter(Boolean).join('、') || '—' },
      { key: 'amt', label: '金額(税込)', align: 'right', render: (r) => '¥' + U.yen(S.invoices.calc(r).total) },
      { key: 'day', label: '発行日', render: (r) => '毎月' + r.day + '日' },
      { key: 'last', label: '最終作成', render: (r) => r.lastGenerated || '—' },
      {
        key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
          el('button.icon-btn', { text: '✎', onclick: () => editor(r) }),
          el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この定期請求を削除しますか？')) { await saveTemplates(templates().filter((x) => x.id !== r.id)); ui.toast('削除しました'); ui.renderRoute(); } } }),
        ]),
      },
    ], list, { empty: '定期請求のテンプレートがありません' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
