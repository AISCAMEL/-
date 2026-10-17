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
    wrap.appendChild(ui.pageHead('部門別損益', [
      el('button.btn', { text: '＋ 部門を追加', onclick: () => addDept() }),
    ]));

    async function addDept() {
      const nameI = el('input', { type: 'text', placeholder: '部門名（例：オークション代行）' });
      const m = ui.modal('部門の追加', el('div.editor', {}, [el('label', {}, [el('span', { text: '部門名' }), nameI])]), {
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
      if (!await ui.confirm('この部門を削除しますか？（過去の仕訳の部門指定は残ります）')) return;
      await S.settings.save({ departments: (S.settings.get().departments || []).filter((d) => d.id !== id) });
      ui.toast('削除しました'); ui.renderRoute();
    }

    if (!depts.length) {
      wrap.appendChild(el('div.card', {}, [
        el('p', { text: 'まだ部門が登録されていません。部門を追加すると、仕訳入力時に部門を選べるようになり、部門ごとの損益を集計できます。' }),
      ]));
      return wrap;
    }

    // 部門マスタ
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '部門マスタ' }),
      el('div.chip-list', {}, depts.map((d) => el('span.chip', {}, [
        el('span', { text: d.name }),
        el('button.icon-btn.del', { text: '×', onclick: () => removeDept(d.id) }),
      ]))),
    ]));

    // 部門別損益
    const sum = R.deptSummary(journals, p.start, p.end, depts);
    const card = el('div.card', {}, [
      el('h2', { text: '部門別損益' }),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]);
    card.appendChild(ui.table([
      { key: 'name', label: '部門', render: (r) => r.name },
      { key: 'revenue', label: '売上（収益）', align: 'right', render: (r) => '¥' + U.yenSigned(r.revenue) },
      { key: 'expense', label: '費用', align: 'right', render: (r) => '¥' + U.yenSigned(r.expense) },
      { key: 'profit', label: '利益', align: 'right', render: (r) => el('span' + (r.profit < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.profit) }) },
    ], sum.rows, {
      empty: 'この期間のデータがありません',
      foot: el('tr.total-row', {}, [
        el('td', { text: '合計' }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.revenue) }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.expense) }),
        el('td.right', { text: '¥' + U.yenSigned(sum.total.profit) }),
      ]),
    }));
    wrap.appendChild(card);
    return wrap;
  });
})();
