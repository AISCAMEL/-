/* aiwatch.js ― AI監視センター（異常検知・アラーム・AI講評・事業の一括仕分け） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const levelLabel = { bad: '要対応', warn: '注意', info: 'お知らせ' };

  ui.register('aiwatch', async () => {
    const journals = await S.journals.loadAll();
    const invoices = await S.invoices.loadAll();
    const vehicles = await S.vehicles.loadAll();
    const assets = await S.assets.loadAll();
    const partners = await S.partners.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);

    const findings = R.watchdog({ journals, invoices, vehicles, assets, partners, settings: s, fy });
    const cnt = { bad: 0, warn: 0, info: 0 };
    findings.forEach((f) => (cnt[f.level] += 1));

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('AI監視センター', [el('span.muted', { text: `${fy.start.slice(0, 4)}年度` })]));

    // サマリ
    wrap.appendChild(el('div.stat-grid', {}, [
      el('div.stat.' + (cnt.bad ? 'bad' : 'good'), {}, [el('div.stat-label', { text: '要対応' }), el('div.stat-value', { text: cnt.bad + ' 件' })]),
      el('div.stat.' + (cnt.warn ? 'warn' : 'good'), {}, [el('div.stat-label', { text: '注意' }), el('div.stat-value', { text: cnt.warn + ' 件' })]),
      el('div.stat', {}, [el('div.stat-label', { text: 'お知らせ' }), el('div.stat-value', { text: cnt.info + ' 件' })]),
    ]));

    // 検知結果リスト
    const listCard = el('div.card', {}, [el('h2', { text: '検知結果' })]);
    if (!findings.length) listCard.appendChild(el('p.result-line.ok', { text: '✓ 現在、特に注意すべき事項は見つかりませんでした。' }));
    else listCard.appendChild(el('div.todo-list', {}, findings.map((f) => el('div.todo-item.' + f.level, { onclick: () => f.to && ui.go(f.to) }, [
      el('span.todo-dot'),
      el('div', {}, [el('div', { html: `<b>[${levelLabel[f.level]}]</b> ${U.esc(f.title)}` }), el('div.muted.small', { text: f.detail || '' })]),
      f.to ? el('span.todo-go', { text: '→' }) : el('span'),
    ]))));
    wrap.appendChild(listCard);

    // AI講評（サーバーAI連携時）＋ 事業の一括仕分け
    const aiReady = !!(A.sync && s.syncUrl && s.syncWorkspace);
    const aiOut = el('div');
    const askAI = async () => {
      aiOut.innerHTML = ''; aiOut.appendChild(el('p.muted.small', { text: 'AIが講評を作成中…' }));
      try {
        const st = R.statements(journals, fy.start, fy.end);
        const tax = R.taxSummary(journals, fy.start, fy.end);
        const d = R.dashboard(journals, p.start, p.end);
        const metrics = { revenue: st.pl.revenue.total, expense: st.pl.expense.total, net: st.pl.netIncome, cash: d.cash, receivable: 0, payable: 0, taxableSalesNet: tax.salesNet };
        const r = await A.sync.ai('検知された事項をふまえ、経営者向けに今月注意すべき点と打ち手を3つ、簡潔に挙げてください。', { metrics, alerts: findings.map((f) => ({ text: f.title })) });
        aiOut.innerHTML = '';
        if (r.ok) aiOut.appendChild(el('div.card', { style: 'background:#f8fafc', html: U.esc(r.answer).replace(/\n/g, '<br>') }));
        else aiOut.appendChild(el('p.result-line.bad', { text: 'AI講評の取得に失敗：' + r.message }));
      } catch (e) { aiOut.innerHTML = ''; aiOut.appendChild(el('p.result-line.bad', { text: 'エラー：' + e.message })); }
    };

    const bulkTag = async () => {
      if (!(s.departments || []).length) return ui.toast('先に「事業別損益」で事業を登録してください', 'err');
      const targets = journals.filter((j) => !j.dept && j.source !== 'closing' && j.source !== 'depreciation');
      const plan = [];
      targets.forEach((j) => { const g = R.learnDept(journals, j.description); if (g && g.dept) plan.push({ j, dept: g.dept }); });
      if (!plan.length) return ui.toast('過去の実績から自動仕分けできる取引が見つかりませんでした', 'err');
      if (!await ui.confirm(`過去の実績をもとに、${plan.length}件の取引に事業を自動割当します。よろしいですか？`)) return;
      for (const { j, dept } of plan) { await S.journals.save({ ...j, dept }); }
      ui.toast(`${plan.length}件を自動仕分けしました`, 'ok');
      ui.renderRoute();
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: 'AIによる管理' }),
      el('div.quick-row', {}, [
        aiReady ? el('button.btn.primary', { text: '🤖 AIに今月の講評を依頼', onclick: askAI })
          : el('span.muted.small', { text: '🤖 AI講評は「設定」でクラウド同期＋AI連携を有効にすると使えます。' }),
        el('button.btn', { text: '🏷 AIで事業を一括仕分け', onclick: bulkTag }),
      ]),
      el('p.muted.small', { text: '「事業を一括仕分け」は、過去に同じ摘要で使った事業をもとに、未設定の取引へ自動で事業を割り当てます（確認後に実行）。' }),
      aiOut,
    ]));

    return wrap;
  });
})();
