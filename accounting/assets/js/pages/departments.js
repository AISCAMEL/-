/* departments.js ― 部門マスタ管理と部門別損益 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  ui.register('departments', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const depts = s.departments || [];
    const p = A.app.period();

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('事業別損益', [
      el('button.btn', { text: '＋ 事業を追加', onclick: () => addDept() }),
    ]));

    async function addDept() {
      const nameI = el('input', { type: 'text', placeholder: '事業名（例：オークション代行 / 車両販売）' });
      const m = ui.modal('事業の追加', el('div.editor', {}, [el('label', {}, [el('span', { text: '事業名' }), nameI])]), {
        footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
          text: '追加', onclick: async () => {
            if (!nameI.value.trim()) return ui.toast('部門名を入力してください', 'err');
            const next = [...(S.settings.get().departments || []), { id: U.uid('dp'), name: nameI.value.trim() }];
            await S.settings.save({ departments: next });
            m.close(); ui.toast('追加しました', 'ok'); ui.renderRoute();
          },
        })],
      });
    }
    async function removeDept(id) {
      if (!await ui.confirm('この事業を削除しますか？（過去の仕訳の事業指定は残ります）')) return;
      await S.settings.save({ departments: (S.settings.get().departments || []).filter((d) => d.id !== id) });
      ui.toast('削除しました'); ui.renderRoute();
    }

    if (!depts.length) {
      wrap.appendChild(el('div.card', {}, [
        el('p', { text: 'まだ事業が登録されていません。事業を追加すると、仕訳・経費・請求書の入力時に事業を選べるようになり、事業ごとの損益を集計できます。' }),
        el('p.muted.small', { text: '例：「オークション代行」「車両販売」など。収益をどの事業にも割り当てなければ「未配賦」に入り、全社の合計は決算書・ダッシュボードで1箇所に集約されます。' }),
      ]));
      return wrap;
    }

    // 事業マスタ
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '事業マスタ' }),
      el('div.chip-list', {}, depts.map((d) => el('span.chip', {}, [
        el('span', { text: d.name }),
        el('button.icon-btn.del', { text: '×', onclick: () => removeDept(d.id) }),
      ]))),
    ]));

    // 事業別損益
    const sum = R.deptSummary(journals, p.start, p.end, depts);
    const totRev = sum.total.revenue || 0;
    const card = el('div.card', {}, [
      el('h2', { text: '事業別損益' }),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]);
    // 利益のグラフ（事業別）
    if (sum.rows.length) card.appendChild(ui.barChart(sum.rows, {
      labelKey: 'name', height: 180, legend: false,
      keys: [{ key: 'profit', label: '利益', color: '#2563eb' }],
    }));
    card.appendChild(ui.table([
      { key: 'name', label: '事業', render: (r) => r.name },
      { key: 'revenue', label: '売上（収益）', align: 'right', render: (r) => '¥' + U.yenSigned(r.revenue) },
      { key: 'share', label: '売上構成比', align: 'right', render: (r) => totRev ? Math.round(r.revenue / totRev * 1000) / 10 + '%' : '—' },
      { key: 'expense', label: '費用', align: 'right', render: (r) => '¥' + U.yenSigned(r.expense) },
      { key: 'profit', label: '利益', align: 'right', render: (r) => el('span' + (r.profit < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.profit) }) },
    ], sum.rows, {
      empty: 'この期間のデータがありません',
      foot: el('tr.total-row', {}, [
        el('td', { text: '全社合計' }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.revenue) }),
        el('td.right', { text: '100%' }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.expense) }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.profit) }),
      ]),
    }));
    card.appendChild(el('p.muted.small', { text: 'どの事業にも割り当てていない取引は「未配賦」に集計されます。全社の合計（1箇所）は「決算書(BS/PL)」「ダッシュボード」でも確認できます。' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
