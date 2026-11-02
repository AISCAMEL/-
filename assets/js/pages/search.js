/* search.js ― グローバル検索（仕訳・請求書・取引先・車両・証憑を横断検索） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const monthRange = (date) => {
    const [y, m] = date.split('-').map(Number);
    const end = new Date(y, m, 0); const pad = (x) => String(x).padStart(2, '0');
    return { start: `${y}-${pad(m)}-01`, end: `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}` };
  };
  const inc = (hay, q, lc) => { const s = String(hay || ''); return s.includes(q) || s.toLowerCase().includes(lc); };

  ui.register('search', async (params) => {
    const [journals, invoices, partners, vehicles, attachments] = await Promise.all([
      S.journals.loadAll(), S.invoices.loadAll(), S.partners.loadAll(), S.vehicles.loadAll(), S.attachments.loadAll(),
    ]);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('検索'));
    const input = el('input', { type: 'search', value: params.q || '', placeholder: '摘要・金額・取引先・番号・車名などで検索' });
    input.style.cssText = 'width:100%;font-size:1.05rem;padding:.6rem .8rem';
    const resultsBox = el('div');
    wrap.appendChild(el('div.card', {}, [input, el('p.muted.small', { text: '仕訳・請求書・取引先・車両・証憑をまとめて検索します。結果をクリックすると該当ページへ移動します。' })]));
    wrap.appendChild(resultsBox);

    const group = (title, rows) => {
      if (!rows.length) return null;
      const card = el('div.card', {}, [el('h2', { text: `${title}（${rows.length}）` })]);
      rows.slice(0, 30).forEach((r) => {
        card.appendChild(el('div.search-row', { onclick: r.go }, [
          el('div', {}, [el('div.sr-main', { text: r.main }), el('div.sr-sub.muted.small', { text: r.sub || '' })]),
          el('span.todo-go', { text: '→' }),
        ]));
      });
      if (rows.length > 30) card.appendChild(el('p.muted.small', { text: `ほか ${rows.length - 30} 件…（キーワードを絞ってください）` }));
      return card;
    };

    const run = () => {
      const q = input.value.trim();
      resultsBox.innerHTML = '';
      if (!q) { resultsBox.appendChild(el('div.card.muted', { text: 'キーワードを入力してください。' })); return; }
      const lc = q.toLowerCase();
      const qNum = /^[\d,]+$/.test(q) ? Number(q.replace(/,/g, '')) : null;

      const jr = journals.filter((j) => inc(j.description, q, lc) || inc(j.date, q, lc)
        || (j.lines || []).some((l) => inc(S.accounts.name(l.account), q, lc) || (qNum != null && l.amount === qNum)))
        .sort((a, b) => b.date.localeCompare(a.date))
        .map((j) => ({ main: `${U.fmtDate(j.date)}　${j.description || '（摘要なし）'}`, sub: '仕訳　¥' + U.yen(S.journals.totals(j).debit), go: () => { A.app.setPeriod(monthRange(j.date)); ui.go('journal'); } }));

      const iv = invoices.filter((i) => inc(i.partnerName, q, lc) || inc(S.invoices.displayNo(i), q, lc)
        || (i.items || []).some((it) => inc(it.name, q, lc)) || (qNum != null && S.invoices.calc(i).total === qNum))
        .map((i) => ({ main: `${S.invoices.displayNo(i)}　${i.partnerName || ''}`, sub: (i.type === 'estimate' ? '見積' : '請求') + `　¥${U.yen(S.invoices.calc(i).total)}　${U.fmtDate(i.date)}`, go: () => ui.go(i.type === 'estimate' ? 'estimates' : 'invoices') }));

      const pr = partners.filter((p) => inc(p.name, q, lc) || inc(p.regNo, q, lc) || inc(p.tel, q, lc) || inc(p.email, q, lc))
        .map((p) => ({ main: p.name, sub: '取引先　' + (p.regNo || ''), go: () => ui.go('partners') }));

      const vh = vehicles.filter((v) => inc(v.name, q, lc) || inc(v.carNo, q, lc) || inc(v.auctionSite, q, lc) || inc(v.maker, q, lc))
        .map((v) => ({ main: v.name, sub: '車両　' + (v.auctionSite || ''), go: () => ui.go('vehicles') }));

      const at = attachments.filter((a) => inc(a.filename, q, lc) || inc(a.partner, q, lc) || inc(a.note, q, lc) || inc(a.date, q, lc) || (qNum != null && a.amount === qNum))
        .map((a) => ({ main: `${a.filename}　¥${U.yen(a.amount || 0)}`, sub: '証憑　' + U.fmtDate(a.date), go: () => ui.go('vouchers') }));

      const groups = [group('仕訳', jr), group('請求書・見積書', iv), group('取引先', pr), group('車両', vh), group('証憑', at)].filter(Boolean);
      if (!groups.length) { resultsBox.appendChild(el('div.card.muted', { text: `「${q}」に一致する結果はありませんでした。` })); return; }
      groups.forEach((g) => resultsBox.appendChild(g));
    };

    let t; input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 120); });
    run();
    setTimeout(() => input.focus(), 0);
    return wrap;
  });
})();
