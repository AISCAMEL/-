/* assets.js ― 固定資産・減価償却（定額法・直接法） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  // 資産科目（BSの資産のうち償却対象になりうるもの）
  const assetAccounts = () => S.accounts.all().filter((a) => a.category === 'asset');

  // 指定資産の、これまでに計上済みの減価償却累計（仕訳から集計）
  const postedAccum = (journals, assetId) => journals
    .filter((j) => j.source === 'depreciation' && j.refId === assetId)
    .reduce((s, j) => s + S.journals.totals(j).debit, 0);
  const postedInFy = (journals, assetId, fyStart, fyEnd) => journals
    .some((j) => j.source === 'depreciation' && j.refId === assetId && U.inRange(j.date, fyStart, fyEnd));

  const METHODS = {
    straight: '定額法',
    declining: '定率法（200%）',
    lump3: '一括償却資産（3年均等）',
    immediate: '少額減価償却資産（即時償却）',
  };

  const editor = (existing) => {
    const a = existing || { name: '', accountCode: '180', acquireDate: U.today(), startDate: U.today(), acquireCost: 0, usefulLife: 5, residual: 0, method: 'straight', note: '' };
    const name = el('input', { type: 'text', value: a.name, placeholder: '例：営業車（軽トラック）' });
    const accSel = el('select');
    assetAccounts().forEach((x) => { const o = el('option', { value: x.code, text: x.name }); if (x.code === a.accountCode) o.selected = true; accSel.appendChild(o); });
    const methodSel = el('select');
    Object.keys(METHODS).forEach((k) => { const o = el('option', { value: k, text: METHODS[k] }); if (k === (a.method || 'straight')) o.selected = true; methodSel.appendChild(o); });
    const acqDate = el('input', { type: 'date', value: a.acquireDate });
    const startDate = el('input', { type: 'date', value: a.startDate || a.acquireDate });
    const cost = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: a.acquireCost ? U.yen(a.acquireCost) : '' });
    const life = el('input', { type: 'number', min: '1', value: a.usefulLife || 5 });
    const residual = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: a.residual ? U.yen(a.residual) : '0' });
    const note = el('input', { type: 'text', value: a.note || '', placeholder: '備考' });
    const lifeLabel = el('label', {}, [el('span', { text: '耐用年数(年)' }), life]);
    const residualLabel = el('label', {}, [el('span', { text: '残存価額' }), residual]);
    const preview = el('div.jsummary');
    const curMethod = () => methodSel.value;
    const applyMethodVis = () => {
      const m = curMethod();
      lifeLabel.style.display = (m === 'lump3' || m === 'immediate') ? 'none' : '';
      residualLabel.style.display = (m === 'straight' || m === 'declining') ? '' : 'none';
    };
    const refresh = () => {
      applyMethodVis();
      const asset = { acquireCost: U.parseYen(cost.value), residual: U.parseYen(residual.value), usefulLife: Number(life.value), method: curMethod(), acquireDate: acqDate.value, startDate: startDate.value };
      const s = S.settings.get();
      const sch = R.depSchedule(asset, s.fiscalStartMonth || 4);
      preview.innerHTML = '';
      if (!sch.length) { preview.appendChild(el('span.muted', { text: '取得価額を入力すると償却予定を表示' })); return; }
      preview.appendChild(el('span', { html: `初年度 <b>¥${U.yen(sch[0].amount)}</b>` }));
      preview.appendChild(el('span', { html: `償却終了 <b>${sch[sch.length - 1].fyYear}年度</b>` }));
      preview.appendChild(el('span.muted', { text: `${METHODS[curMethod()]}・${sch.length}年度` }));
    };
    [cost, life, residual, acqDate, startDate].forEach((i) => i.addEventListener('input', refresh));
    methodSel.addEventListener('change', refresh);
    cost.addEventListener('blur', () => { cost.value = cost.value ? U.yen(U.parseYen(cost.value)) : ''; });
    residual.addEventListener('blur', () => { residual.value = U.yen(U.parseYen(residual.value)); });
    refresh();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '資産名' }), name]), el('label', {}, [el('span', { text: '資産科目' }), accSel])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '取得日' }), acqDate]), el('label', {}, [el('span', { text: '事業供用日' }), startDate])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '償却方法' }), methodSel]), lifeLabel, residualLabel]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '取得価額' }), cost])]),
      el('label', {}, [el('span', { text: '備考' }), note]),
      el('div.preview-box', {}, [el('div.muted', { text: '減価償却の見込み' }), preview]),
    ]);
    const save = async () => {
      if (!name.value) return ui.toast('資産名を入力してください', 'err');
      if (U.parseYen(cost.value) <= 0) return ui.toast('取得価額を入力してください', 'err');
      await S.assets.save({ ...a, name: name.value, accountCode: accSel.value, acquireDate: acqDate.value, startDate: startDate.value, acquireCost: U.parseYen(cost.value), usefulLife: Number(life.value), residual: U.parseYen(residual.value), method: curMethod(), note: note.value });
      m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal(existing ? '固定資産の編集' : '固定資産の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', { text: '保存', onclick: save })],
    });
  };

  const showSchedule = (asset) => {
    const s = S.settings.get();
    const sch = R.depSchedule(asset, s.fiscalStartMonth || 4);
    const body = ui.table([
      { key: 'fy', label: '年度', render: (r) => r.fyYear + '年度' },
      { key: 'months', label: '月数', align: 'right', render: (r) => r.months + 'ヶ月' },
      { key: 'amt', label: '償却額', align: 'right', render: (r) => '¥' + U.yen(r.amount) },
      { key: 'book', label: '期末帳簿価額', align: 'right', render: (r) => '¥' + U.yen(r.bookAfter) },
    ], sch, { empty: '償却予定なし' });
    ui.modal(`償却スケジュール：${asset.name}`, body, { footer: [] });
  };

  const postDepreciation = async (asset, fyStart, fyEnd) => {
    const s = S.settings.get();
    const row = R.depForFiscalYear(asset, s.fiscalStartMonth || 4, fyStart);
    if (!row) return ui.toast('この年度の償却はありません', 'err');
    await S.journals.save({
      refId: asset.id, source: 'depreciation', date: fyEnd,
      description: `減価償却：${asset.name}（${row.fyYear}年度）`,
      lines: [
        { side: 'debit', account: '590', tax: 'out', amount: row.amount },
        { side: 'credit', account: asset.accountCode, tax: 'out', amount: row.amount },
      ],
    });
    ui.toast(`減価償却 ¥${U.yen(row.amount)} を計上しました`, 'ok');
    ui.renderRoute();
  };

  ui.register('assets', async (q) => {
    if (q.new) setTimeout(() => editor(null), 0);
    const list = await S.assets.loadAll();
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('固定資産・減価償却', [
      el('span.muted', { text: `対象年度：${fy.start.slice(0, 4)}年度` }),
      el('button.btn.primary', { text: '＋ 固定資産を登録', onclick: () => editor(null) }),
    ]));

    // 当期分を一括計上
    const bulk = el('button.btn', {
      text: '当期の減価償却をまとめて計上', onclick: async () => {
        let n = 0;
        for (const asset of list) {
          if (asset.disposed) continue;
          if (postedInFy(journals, asset.id, fy.start, fy.end)) continue;
          const row = R.depForFiscalYear(asset, s.fiscalStartMonth || 4, fy.start);
          if (!row) continue;
          await S.journals.save({ refId: asset.id, source: 'depreciation', date: fy.end, description: `減価償却：${asset.name}（${row.fyYear}年度）`, lines: [{ side: 'debit', account: '590', tax: 'out', amount: row.amount }, { side: 'credit', account: asset.accountCode, tax: 'out', amount: row.amount }] });
          n += 1;
        }
        ui.toast(n ? `${n}件の減価償却を計上しました` : '計上対象はありませんでした', n ? 'ok' : '');
        ui.renderRoute();
      },
    });

    const card = el('div.card', {}, [el('div.card-head', {}, [el('span.muted', { text: '定額法・直接法（借：減価償却費／貸：資産科目）' }), bulk])]);
    card.appendChild(ui.table([
      { key: 'name', label: '資産名', render: (r) => r.name },
      { key: 'acc', label: '科目', render: (r) => S.accounts.name(r.accountCode) },
      { key: 'method', label: '方法', render: (r) => (METHODS[r.method || 'straight'] || '').replace(/（.*）/, '') },
      { key: 'acq', label: '取得日', render: (r) => U.fmtDate(r.acquireDate) },
      { key: 'cost', label: '取得価額', align: 'right', render: (r) => '¥' + U.yen(r.acquireCost) },
      { key: 'book', label: '帳簿価額', align: 'right', render: (r) => '¥' + U.yen(r.acquireCost - postedAccum(journals, r.id)) },
      {
        key: 'fydep', label: '当期償却', align: 'right', render: (r) => {
          const row = R.depForFiscalYear(r, s.fiscalStartMonth || 4, fy.start);
          if (!row) return el('span.muted', { text: '—' });
          const done = postedInFy(journals, r.id, fy.start, fy.end);
          return el('span', { html: `¥${U.yen(row.amount)}${done ? ' <span class="badge in">計上済</span>' : ''}` });
        },
      },
      {
        key: 'act', label: '', align: 'right', render: (r) => {
          const box = el('div.row-actions');
          const row = R.depForFiscalYear(r, s.fiscalStartMonth || 4, fy.start);
          if (row && !postedInFy(journals, r.id, fy.start, fy.end)) box.appendChild(el('button.btn.sm', { text: '当期計上', onclick: () => postDepreciation(r, fy.start, fy.end) }));
          box.appendChild(el('button.icon-btn', { text: '📅', title: '償却予定', onclick: () => showSchedule(r) }));
          box.appendChild(el('button.icon-btn', { text: '✎', onclick: () => editor(r) }));
          box.appendChild(el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この資産と関連する減価償却仕訳を削除しますか？')) { await S.assets.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); } } }));
          return box;
        },
      },
    ], list, { empty: '固定資産が登録されていません' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
