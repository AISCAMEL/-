/* import.js ― 明細CSVの取込と自動仕訳（銀行/クレジット/交通系IC プリセット＋Webhook受信） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const TAX = A.accounts.TAX_CATEGORIES;

  /* ---- 取込プリセット --------------------------------------------------
   * source ごとに、文字コード・相手勘定（口座/未払金/現金）・列見出しの推定語・
   * 既定の相手科目/税区分・入出金の扱いを設定する。
   * ------------------------------------------------------------------- */
  const PRESETS = {
    bank: {
      label: '銀行明細', enc: 'shift_jis', bank: '110', mode: 'inout', defAccount: '', defTax: 'purchase10',
      date: ['日付', '取引日', '年月日', 'date', 'Date'], desc: ['摘要', '内容', '取引', '備考', 'メモ'],
      out: ['出金', '支払', 'お支払', '引出'], in: ['入金', '預入', 'お預'], amt: ['金額'],
      hint: '銀行のCSV。出金/入金（または±金額）で口座残高の増減を記帳します。',
    },
    credit: {
      label: 'クレジットカード', enc: 'shift_jis', bank: '210', mode: 'out', defAccount: '', defTax: 'purchase10',
      date: ['利用日', 'ご利用日', '日付', '取引日'], desc: ['利用先', 'ご利用先', '店名', '利用店名', 'ご利用内容', '摘要'],
      out: [], in: [], amt: ['利用金額', 'ご利用金額', '金額', '支払金額'],
      hint: 'カード会社のCSV。各利用を「借）費用 ／ 貸）未払金」で計上します（引落しは別途、未払金の支払として記帳）。',
    },
    transit: {
      label: '交通系IC（Suica等）', enc: 'utf-8', bank: '100', mode: 'out', defAccount: '540', defTax: 'purchase10',
      date: ['日付', '年月日', '利用日'], desc: ['種別', '摘要', '駅', '利用', '入場', '出場', '利用駅'],
      out: [], in: [], amt: ['利用額', '金額', '差引金額', '支払額'],
      hint: 'ICカードリーダーやNFCアプリで読み出した履歴CSV。既定で旅費交通費に計上します（現金/チャージ支払）。',
    },
  };

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
  const normDate = (s) => {
    s = (s || '').trim();
    let m = s.match(/(\d{4})[\/\-.年](\d{1,2})[\/\-.月](\d{1,2})/);
    if (m) return `${m[1]}-${String(m[2]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`;
    m = s.match(/^(\d{4})(\d{2})(\d{2})$/);
    if (m) return `${m[1]}-${m[2]}-${m[3]}`;
    return s || U.today();
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
  const suggest = (desc) => {
    const rules = S.settings.get().autoRules || [];
    return rules.find((r) => r.keyword && (desc || '').includes(r.keyword)) || null;
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

  /* ---- プレビュー＆計上（CSV・Webnook 共通） --------------------------- */
  const renderPreview = (container, items, preset) => {
    container.innerHTML = '';
    if (!items.length) { container.appendChild(el('div.card', { text: '取り込める明細がありませんでした。' })); return; }
    const bankAcc = accSelect(['asset', 'liability'], preset.bank);

    const rowsEls = items.map((it) => {
      const chk = el('input', { type: 'checkbox' }); chk.checked = it.include !== false; chk.addEventListener('change', () => it.include = chk.checked);
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
        const bank = bankAcc.value || preset.bank;
        const targets = items.filter((it) => it.include !== false && it.account && it.amount > 0);
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
    container.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: `プレビュー（${items.length}件）` }), post]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: preset.mode === 'out' && preset.bank === '210' ? '貸方（未払金）' : '相手（口座）科目' }), bankAcc])]),
      el('p.muted.small', { text: '相手科目が空の行は計上されません。「自動仕訳ルール」を登録すると次回から自動で推定されます。' }),
      table,
    ]));
  };

  // 明細行→取込アイテムへ（ルール/プリセットで相手科目・税区分を補完）
  const toItem = (date, desc, dir, amount, preset) => {
    const sug = suggest(desc);
    return {
      date: normDate(date), desc: (desc || '').trim(), dir, amount: Math.abs(amount),
      account: sug ? sug.account : (preset.defAccount || ''),
      tax: sug ? sug.tax : (dir === 'out' ? (preset.defTax || 'purchase10') : 'sales10'),
      include: true,
    };
  };

  ui.register('import', async (q) => {
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('明細の取込（CSV / Webhook）', [
      el('button.btn', { text: '⚙ 自動仕訳ルール', onclick: () => rulesModal() }),
    ]));

    // プリセット選択
    const presetSel = el('select');
    Object.keys(PRESETS).forEach((k) => presetSel.appendChild(el('option', { value: k, text: PRESETS[k].label })));
    const fileIn = el('input', { type: 'file', accept: '.csv,text/csv' });
    const encSel = el('select', {}, [el('option', { value: 'utf-8', text: 'UTF-8' }), el('option', { value: 'shift_jis', text: 'Shift_JIS' })]);
    const hasHeader = el('input', { type: 'checkbox' }); hasHeader.checked = true;
    const hintText = el('p.muted.small');
    const previewBox = el('div');
    const applyPreset = () => { const p = PRESETS[presetSel.value]; encSel.value = p.enc; hintText.textContent = p.hint; };
    presetSel.addEventListener('change', applyPreset);

    const setup = el('div.card', {}, [
      el('h2', { text: '① 種類とCSVファイルを選択' }),
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '取込元' }), presetSel]),
        el('label.grow', {}, [el('span', { text: 'CSVファイル' }), fileIn]),
        el('label', {}, [el('span', { text: '文字コード' }), encSel]),
        el('label', {}, [el('span', { text: '1行目は見出し' }), hasHeader]),
      ]),
      hintText,
    ]);
    wrap.appendChild(setup);

    // クラウド受信（Webhook）取込
    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: 'クラウド受信データの取込（Webhook）' }),
        el('button.btn', { text: '📥 受信データを取得', onclick: () => pullInbox() })]),
      el('p.muted.small', { html: '同期サーバーの受信キュー（外部システムが <code>/api/inbox</code> にPOSTした仕訳データ）を取り込みます。設定でサーバーURL・ワークスペース・トークンを登録してください。' }),
    ]));
    wrap.appendChild(previewBox);
    applyPreset();

    const pullInbox = async () => {
      if (!A.sync) return ui.toast('同期モジュールがありません', 'err');
      try {
        const r = await A.sync.pullInbox();
        if (!r.ok) return ui.toast('取得失敗：' + r.message, 'err');
        if (!r.items.length) return ui.toast('受信データはありません');
        const preset = PRESETS[presetSel.value];
        const items = r.items.map((x) => {
          const dir = x.dir || (Number(x.amount) < 0 ? 'out' : 'in');
          const base = toItem(x.date || U.today(), x.description || x.desc || '', dir, Number(x.amount) || 0, preset);
          if (x.account) base.account = x.account;
          if (x.tax) base.tax = x.tax;
          return base;
        });
        renderPreview(previewBox, items, preset);
        ui.toast(`${items.length}件を受信しました`, 'ok');
      } catch (e) { ui.toast(e.message, 'err'); }
    };

    fileIn.addEventListener('change', async () => {
      const f = fileIn.files[0]; if (!f) return;
      const buf = await f.arrayBuffer();
      let text;
      try { text = new TextDecoder(encSel.value).decode(buf); } catch (e) { text = new TextDecoder('utf-8').decode(buf); }
      const rows = parseCsv(text);
      if (!rows.length) return ui.toast('データが読み取れませんでした', 'err');
      buildMapping(rows);
    });

    const buildMapping = (rows) => {
      previewBox.innerHTML = '';
      const preset = PRESETS[presetSel.value];
      const header = hasHeader.checked ? rows[0] : rows[0].map((_, i) => '列' + (i + 1));
      const dataRows = hasHeader.checked ? rows.slice(1) : rows;
      const colOpts = () => { const s = el('select'); s.appendChild(el('option', { value: '', text: '（なし）' })); header.forEach((h, i) => s.appendChild(el('option', { value: i, text: `${i + 1}: ${h}` }))); return s; };
      const dateCol = colOpts(), descCol = colOpts(), outCol = colOpts(), inCol = colOpts(), amtCol = colOpts();
      const guess = (kw) => header.findIndex((h) => kw.some((k) => (h || '').includes(k)));
      const setIf = (sel, idx) => { if (idx >= 0) sel.value = idx; };
      setIf(dateCol, guess(preset.date)); setIf(descCol, guess(preset.desc));
      setIf(outCol, guess(preset.out)); setIf(inCol, guess(preset.in)); setIf(amtCol, guess(preset.amt));

      const map = el('div.card', {}, [
        el('h2', { text: '② 列の対応づけ' }),
        el('div.form-row', {}, [
          el('label', {}, [el('span', { text: '日付列' }), dateCol]),
          el('label', {}, [el('span', { text: '摘要列' }), descCol]),
        ]),
        preset.mode === 'inout'
          ? el('div.form-row', {}, [el('label', {}, [el('span', { text: '出金列' }), outCol]), el('label', {}, [el('span', { text: '入金列' }), inCol]), el('label', {}, [el('span', { text: 'または 金額列(±)' }), amtCol])])
          : el('div.form-row', {}, [el('label', {}, [el('span', { text: '金額列（利用額）' }), amtCol])]),
        el('button.btn.primary', { text: 'プレビュー生成', onclick: () => buildPreview() }),
      ]);
      previewBox.appendChild(map);
      const previewArea = el('div'); previewBox.appendChild(previewArea);

      const buildPreview = () => {
        const items = [];
        dataRows.forEach((r) => {
          const date = r[dateCol.value] || '';
          const desc = r[descCol.value] || '';
          let dir, amount;
          if (preset.mode === 'out') { // クレジット・交通系＝支出のみ
            amount = num(r[amtCol.value]); dir = 'out';
          } else if (amtCol.value !== '') {
            const v = num(r[amtCol.value]); dir = v < 0 ? 'out' : 'in'; amount = v;
          } else {
            const out = num(r[outCol.value]), inn = num(r[inCol.value]);
            if (out > 0) { dir = 'out'; amount = out; } else { dir = 'in'; amount = inn; }
          }
          if (!amount) return;
          items.push(toItem(date, desc, dir, amount, preset));
        });
        renderPreview(previewArea, items, preset);
      };
      buildPreview();
    };
    return wrap;
  });
})();
