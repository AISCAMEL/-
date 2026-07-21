/* import.js ― 銀行明細CSVの取込と自動仕訳 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const TAX = A.accounts.TAX_CATEGORIES;

  /* ---- CSVパーサ（RFC4180簡易版） ------------------------------------- */
  const parseCsv = (text) => {
    const rows = []; let row = [], cur = '', q = false;
    for (let i = 0; i < text.length; i++) {
      const c = text[i];
      if (q) {
        if (c === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else q = false; }
        else cur += c;
      } else if (c === '"') q = true;
      else if (c === ',') { row.push(cur); cur = ''; }
      else if (c === '\r') { /* skip */ }
      else if (c === '\n') { row.push(cur); rows.push(row); row = []; cur = ''; }
      else cur += c;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return rows.filter((r) => r.some((c) => (c || '').trim() !== ''));
  };
  // 日付文字列を YYYY-MM-DD に正規化
  const normDate = (s) => {
    s = (s || '').trim();
    let m = s.match(/(\d{4})[\/\-.年](\d{1,2})[\/\-.月](\d{1,2})/);
    if (m) return `${m[1]}-${String(m[2]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`;
    m = s.match(/^(\d{4})(\d{2})(\d{2})$/);
    if (m) return `${m[1]}-${m[2]}-${m[3]}`;
    return s;
  };
  const num = (s) => U.parseYen(String(s || '').replace(/[^\d.\-]/g, ''));

  const accSelect = (cats, value) => {
    const sel = el('select.sm-sel');
    sel.appendChild(el('option', { value: '', text: '（未設定）' }));
    S.accounts.all().filter((a) => cats.includes(a.category)).forEach((a) => {
      const o = el('option', { value: a.code, text: a.name }); if (a.code === value) o.selected = true; sel.appendChild(o);
    });
    return sel;
  };
  const taxSelect = (value) => {
    const sel = el('select.sm-sel');
    Object.keys(TAX).forEach((k) => { const o = el('option', { value: k, text: TAX[k].label }); if (k === value) o.selected = true; sel.appendChild(o); });
    return sel;
  };

  // 摘要からルールで科目を推定
  const suggest = (desc) => {
    const rules = S.settings.get().autoRules || [];
    const hit = rules.find((r) => r.keyword && desc.includes(r.keyword));
    return hit || null;
  };

  /* ---- 自動仕訳ルールの管理 ------------------------------------------- */
  const rulesModal = () => {
    const s = S.settings.get();
    const rules = JSON.parse(JSON.stringify(s.autoRules || []));
    const listBox = el('div.rules-list');
    const draw = () => {
      listBox.innerHTML = '';
      rules.forEach((r, idx) => {
        listBox.appendChild(el('div.rule-row', {}, [
          el('span', { text: `「${r.keyword}」→ ${S.accounts.name(r.account)}（${TAX[r.tax] ? TAX[r.tax].label : ''}）` }),
          el('button.icon-btn.del', { text: '×', onclick: () => { rules.splice(idx, 1); draw(); } }),
        ]));
      });
      if (!rules.length) listBox.appendChild(el('div.muted.small', { text: 'ルールがありません' }));
    };
    draw();
    const kw = el('input', { type: 'text', placeholder: 'キーワード（摘要に含む文字）' });
    const acc = accSelect(['expense', 'revenue', 'asset', 'liability']);
    const tax = taxSelect('purchase10');
    const body = el('div.editor', {}, [
      listBox,
      el('div.rule-add', {}, [kw, acc, tax, el('button.btn.sm', {
        text: '＋追加', onclick: () => { if (!kw.value || !acc.value) return ui.toast('キーワードと科目を入力', 'err'); rules.push({ keyword: kw.value, account: acc.value, tax: tax.value }); kw.value = ''; draw(); },
      })]),
    ]);
    const m = ui.modal('自動仕訳ルール', body, {
      footer: [el('button.btn', { text: '閉じる', onclick: () => m.close() }), el('button.btn.primary', { text: '保存', onclick: async () => { await S.settings.save({ autoRules: rules }); m.close(); ui.toast('ルールを保存しました', 'ok'); } })],
    });
  };

  ui.register('import', async () => {
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('銀行明細の取込（CSV）', [
      el('button.btn', { text: '⚙ 自動仕訳ルール', onclick: () => rulesModal() }),
    ]));

    const fileIn = el('input', { type: 'file', accept: '.csv,text/csv' });
    const encSel = el('select', {}, [el('option', { value: 'utf-8', text: 'UTF-8' }), el('option', { value: 'shift_jis', text: 'Shift_JIS（銀行標準）' })]);
    const hasHeader = el('input', { type: 'checkbox' }); hasHeader.checked = true;
    const previewBox = el('div');

    const setup = el('div.card', {}, [
      el('h2', { text: '① CSVファイルを選択' }),
      el('div.form-row', {}, [
        el('label.grow', {}, [el('span', { text: 'CSVファイル' }), fileIn]),
        el('label', {}, [el('span', { text: '文字コード' }), encSel]),
        el('label', {}, [el('span', { text: '1行目は見出し' }), hasHeader]),
      ]),
      el('p.muted.small', { text: '各行を「日付・摘要・出金/入金」の列に対応づけて取り込みます。相手科目は自動仕訳ルールで推定できます。' }),
    ]);
    wrap.appendChild(setup);
    wrap.appendChild(previewBox);

    fileIn.addEventListener('change', async () => {
      const f = fileIn.files[0]; if (!f) return;
      const buf = await f.arrayBuffer();
      let text;
      try { text = new TextDecoder(encSel.value).decode(buf); }
      catch (e) { text = new TextDecoder('utf-8').decode(buf); }
      const rows = parseCsv(text);
      if (!rows.length) return ui.toast('データが読み取れませんでした', 'err');
      buildMapping(rows);
    });

    const buildMapping = (rows) => {
      previewBox.innerHTML = '';
      const header = hasHeader.checked ? rows[0] : rows[0].map((_, i) => '列' + (i + 1));
      const dataRows = hasHeader.checked ? rows.slice(1) : rows;
      const colOpts = (sel) => { const s = el('select'); s.appendChild(el('option', { value: '', text: '（なし）' })); header.forEach((h, i) => { const o = el('option', { value: i, text: `${i + 1}: ${h}` }); s.appendChild(o); }); return s; };
      const dateCol = colOpts(); const descCol = colOpts(); const outCol = colOpts(); const inCol = colOpts(); const amtCol = colOpts();
      // 見出しから自動推定
      const guess = (kw) => header.findIndex((h) => kw.some((k) => (h || '').includes(k)));
      const setIf = (sel, idx) => { if (idx >= 0) sel.value = idx; };
      setIf(dateCol, guess(['日付', '取引日', '年月日', 'date', 'Date']));
      setIf(descCol, guess(['摘要', '内容', '取引', '備考', 'メモ']));
      setIf(outCol, guess(['出金', '支払', 'お支払', '引出']));
      setIf(inCol, guess(['入金', '預入', 'お預']));
      setIf(amtCol, guess(['金額']));
      const bankAcc = accSelect(['asset'], '110');

      const map = el('div.card', {}, [
        el('h2', { text: '② 列の対応づけ' }),
        el('div.form-row', {}, [
          el('label', {}, [el('span', { text: '日付列' }), dateCol]),
          el('label', {}, [el('span', { text: '摘要列' }), descCol]),
          el('label', {}, [el('span', { text: '口座科目' }), bankAcc]),
        ]),
        el('div.form-row', {}, [
          el('label', {}, [el('span', { text: '出金列' }), outCol]),
          el('label', {}, [el('span', { text: '入金列' }), inCol]),
          el('label', {}, [el('span', { text: 'または 金額列(±)' }), amtCol]),
        ]),
        el('button.btn.primary', { text: 'プレビュー生成', onclick: () => buildPreview() }),
      ]);
      previewBox.appendChild(map);
      const previewArea = el('div'); previewBox.appendChild(previewArea);

      const buildPreview = () => {
        previewArea.innerHTML = '';
        const items = [];
        dataRows.forEach((r) => {
          const date = normDate(r[dateCol.value] || '');
          const desc = (r[descCol.value] || '').trim();
          let dir, amount;
          if (amtCol.value !== '') {
            const v = num(r[amtCol.value]);
            dir = v < 0 ? 'out' : 'in'; amount = Math.abs(v);
          } else {
            const out = num(r[outCol.value]); const inn = num(r[inCol.value]);
            if (out > 0) { dir = 'out'; amount = out; } else { dir = 'in'; amount = inn; }
          }
          if (!amount) return;
          const sug = suggest(desc);
          items.push({ date, desc, dir, amount, account: sug ? sug.account : '', tax: sug ? sug.tax : (dir === 'out' ? 'purchase10' : 'sales10'), include: true });
        });
        if (!items.length) { previewArea.appendChild(el('div.card', { text: '取り込める明細がありませんでした。列の対応を確認してください。' })); return; }

        const rowsEls = items.map((it) => {
          const chk = el('input', { type: 'checkbox' }); chk.checked = it.include; chk.addEventListener('change', () => it.include = chk.checked);
          const accSel = accSelect(it.dir === 'out' ? ['expense', 'asset', 'liability'] : ['revenue', 'asset', 'liability'], it.account);
          accSel.addEventListener('change', () => { it.account = accSel.value; const a = S.accounts.byCode(accSel.value); if (a) { it.tax = a.default_tax; taxS.value = a.default_tax; } });
          const taxS = taxSelect(it.tax); taxS.addEventListener('change', () => it.tax = taxS.value);
          return el('tr', {}, [
            el('td', {}, [chk]),
            el('td', { text: U.fmtDate(it.date) }),
            el('td', {}, [it.dir === 'out' ? el('span.badge.out', { text: '出金' }) : el('span.badge.in', { text: '入金' })]),
            el('td', { text: it.desc }),
            el('td.right', { text: '¥' + U.yen(it.amount) }),
            el('td', {}, [accSel]),
            el('td', {}, [taxS]),
          ]);
        });
        const table = el('table.grid', {}, [
          el('thead', {}, [el('tr', {}, ['取込', '日付', '区分', '摘要', '金額', '相手科目', '税区分'].map((h) => el('th', { text: h })))]),
          el('tbody', {}, rowsEls),
        ]);
        const post = el('button.btn.primary', {
          text: '選択した明細を仕訳に計上', onclick: async () => {
            const bank = bankAcc.value || '110';
            const targets = items.filter((it) => it.include && it.account && it.amount > 0);
            if (!targets.length) return ui.toast('相手科目を設定した明細がありません', 'err');
            if (!await ui.confirm(`${targets.length}件を仕訳に計上します。よろしいですか？`)) return;
            const journals = targets.map((it) => ({
              source: 'import', date: it.date, description: it.desc,
              lines: it.dir === 'out'
                ? [{ side: 'debit', account: it.account, tax: it.tax, amount: it.amount }, { side: 'credit', account: bank, tax: 'out', amount: it.amount }]
                : [{ side: 'debit', account: bank, tax: 'out', amount: it.amount }, { side: 'credit', account: it.account, tax: it.tax, amount: it.amount }],
            }));
            await S.journals.saveMany(journals);
            ui.toast(`${journals.length}件を計上しました`, 'ok');
            ui.go('journal');
          },
        });
        previewArea.appendChild(el('div.card', {}, [
          el('div.card-head', {}, [el('h2', { text: `③ プレビュー（${items.length}件）` }), post]),
          el('p.muted.small', { text: '相手科目が空の行は計上されません。「自動仕訳ルール」を登録すると次回から自動で推定されます。' }),
          table,
        ]));
      };
      buildPreview();
    };
    return wrap;
  });
})();
