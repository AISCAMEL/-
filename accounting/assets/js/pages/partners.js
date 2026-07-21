/* partners.js ― 取引先マスタ */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const editor = (existing) => {
    const p = existing || { name: '', kind: 'customer', regNo: '', address: '', tel: '', email: '', note: '' };
    const f = {};
    const field = (key, label, ph) => {
      const inp = el('input', { type: 'text', value: p[key] || '', placeholder: ph || '' });
      f[key] = inp;
      return el('label', {}, [el('span', { text: label }), inp]);
    };
    const kind = el('select', {}, [
      el('option', { value: 'customer', text: '得意先（売上）' }),
      el('option', { value: 'supplier', text: '仕入先（支払）' }),
      el('option', { value: 'both', text: '両方' }),
    ]);
    kind.value = p.kind || 'customer';
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [field('name', '取引先名', '例：株式会社〇〇'), el('label', {}, [el('span', { text: '区分' }), kind])]),
      el('div.form-row', {}, [field('regNo', '登録番号(T番号)', 'T1234567890123'), field('tel', '電話番号')]),
      field('address', '住所'),
      field('email', 'メール'),
      field('note', '備考'),
    ]);
    const save = async () => {
      if (!f.name.value) return ui.toast('取引先名を入力してください', 'err');
      await S.partners.save({ ...p, name: f.name.value, kind: kind.value, regNo: f.regNo.value, address: f.address.value, tel: f.tel.value, email: f.email.value, note: f.note.value });
      m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal(existing ? '取引先の編集' : '取引先の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', { text: '保存', onclick: save })],
    });
  };

  const KIND = { customer: '得意先', supplier: '仕入先', both: '両方' };

  ui.register('partners', async () => {
    const list = await S.partners.loadAll();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('取引先', [el('button.btn.primary', { text: '＋ 取引先を登録', onclick: () => editor(null) })]));
    const card = el('div.card');
    card.appendChild(ui.table([
      { key: 'name', label: '取引先名', render: (r) => r.name },
      { key: 'kind', label: '区分', render: (r) => KIND[r.kind] || '—' },
      { key: 'regNo', label: '登録番号', render: (r) => r.regNo || '—' },
      { key: 'tel', label: '電話', render: (r) => r.tel || '—' },
      {
        key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
          el('button.icon-btn', { text: '✎', onclick: () => editor(r) }),
          el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('削除しますか？')) { await S.partners.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); } } }),
        ]),
      },
    ], list, { empty: '取引先が登録されていません' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
