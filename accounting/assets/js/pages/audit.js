/* audit.js（page）― 訂正・削除履歴の閲覧（電帳法対応・読み取り専用） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui;

  const badge = (action) => {
    const cls = action === 'delete' ? 'out' : (action === 'create' ? 'in' : '');
    return el('span.badge.' + cls, { text: A.audit.ACTION[action] || action });
  };

  ui.register('audit', async () => {
    const all = await A.audit.loadAll();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('訂正・削除履歴', [
      el('span.muted', { text: `全 ${all.length} 件` }),
    ]));

    const fAction = el('select', {}, [el('option', { value: '', text: 'すべての操作' }),
      ...Object.keys(A.audit.ACTION).map((k) => el('option', { value: k, text: A.audit.ACTION[k] }))]);
    const fEntity = el('select', {}, [el('option', { value: '', text: 'すべての対象' }),
      ...Object.keys(A.audit.ENTITY).map((k) => el('option', { value: k, text: A.audit.ENTITY[k] }))]);
    const fKw = el('input', { type: 'text', placeholder: '内容で検索' });
    const listBox = el('div');
    const draw = () => {
      const kw = fKw.value.trim();
      const list = all.filter((r) =>
        (!fAction.value || r.action === fAction.value) &&
        (!fEntity.value || r.entity === fEntity.value) &&
        (!kw || (r.summary || '').includes(kw)));
      listBox.innerHTML = '';
      listBox.appendChild(ui.table([
        { key: 'ts', label: '日時', render: (r) => new Date(r.ts).toLocaleString('ja-JP') },
        { key: 'action', label: '操作', render: (r) => badge(r.action) },
        { key: 'entity', label: '対象', render: (r) => A.audit.ENTITY[r.entity] || r.entity },
        { key: 'summary', label: '内容', render: (r) => r.summary || '—' },
      ], list, { empty: '履歴がありません' }));
    };
    [fAction, fEntity, fKw].forEach((i) => i.addEventListener('input', draw));

    wrap.appendChild(el('div.card', {}, [
      el('p.muted.small', { text: '仕訳・請求書・固定資産・証憑の作成／訂正／削除を自動で記録します（電子帳簿保存法の訂正・削除履歴に対応）。この履歴は追記のみで、編集・削除はできません。' }),
      el('div.search-bar', {}, [el('span.muted', { text: '絞り込み' }), fAction, fEntity, fKw,
        el('button.btn.sm', { text: 'クリア', onclick: () => { fAction.value = ''; fEntity.value = ''; fKw.value = ''; draw(); } })]),
    ]));
    const listCard = el('div.card'); listCard.appendChild(listBox); wrap.appendChild(listCard);
    draw();
    return wrap;
  });
})();
