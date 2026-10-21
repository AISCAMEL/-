/* receipts.js ― 領収書まとめて取込（写真/スクショ → 自動読み取り → 一括で仕訳に転写）
 * スマホのカメラで撮影 or 複数画像をドロップ → OCRで日付・金額・店名を読取 →
 * 内容を確認して一括計上。画像は証憑（電帳法）として各仕訳に自動添付。
 * AI連携が無い場合も、画像を証憑保存しつつ手入力で計上できる。
 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const TAX = A.accounts.TAX_CATEGORIES;
  const PAY = ['100', '110', '210', '230']; // 現金・普通預金・未払金・預り金

  // 画像を縮小してJPEG dataURLに（DB肥大とOCR負荷を抑制）
  const shrink = (file, maxDim) => new Promise((resolve) => {
    const r = new FileReader();
    r.onload = () => {
      const img = new Image();
      img.onload = () => {
        const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
        const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
        const c = el('canvas'); c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        try { resolve({ dataUrl: c.toDataURL('image/jpeg', 0.82), mime: 'image/jpeg' }); }
        catch (e) { resolve({ dataUrl: r.result, mime: file.type }); }
      };
      img.onerror = () => resolve({ dataUrl: r.result, mime: file.type });
      img.src = r.result;
    };
    r.readAsDataURL(file);
  });

  const accSelect = (value) => {
    const sel = el('select.sm-sel');
    sel.appendChild(el('option', { value: '', text: '（科目未設定）' }));
    S.accounts.all().filter((a) => a.category === 'expense').forEach((a) => { const o = el('option', { value: a.code, text: a.name }); if (a.code === value) o.selected = true; sel.appendChild(o); });
    return sel;
  };
  const taxSelect = (value) => { const sel = el('select.sm-sel'); Object.keys(TAX).forEach((k) => { const o = el('option', { value: k, text: TAX[k].label }); if (k === value) o.selected = true; sel.appendChild(o); }); return sel; };
  const paySelect = (value) => { const sel = el('select.sm-sel'); PAY.forEach((c) => { const o = el('option', { value: c, text: S.accounts.name(c) }); if (c === value) o.selected = true; sel.appendChild(o); }); return sel; };

  ui.register('receipts', async () => {
    const s = S.settings.get();
    const journals = await S.journals.loadAll();
    const aiReady = !!(A.sync && s.syncUrl && s.syncWorkspace);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('領収書まとめて取込（写真→自動読み取り）'));

    const fileIn = el('input', { type: 'file', accept: 'image/*', multiple: true });
    fileIn.setAttribute('capture', 'environment'); // スマホはカメラ起動
    const drop = el('div.drop-zone', { text: 'ここに領収書の写真・スクショをドロップ、またはタップして選択' });
    const rowsBox = el('div.receipt-rows');
    const rows = []; // {dataUrl, mime, filename, dateI, amtI, memoI, accSel, taxSel, paySel, status}

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '① 写真・スクショを追加' }),
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '画像（複数可・スマホはカメラ可）' }), fileIn])]),
      drop,
      el('p.muted.small', { text: aiReady ? 'AI連携が有効です。日付・金額・店名を自動で読み取り、下の表に転写します。内容を確認して一括計上してください。' : '※ AI自動読み取りは「設定」でクラウド同期＋AI連携を有効にすると使えます。無効でも、画像を証憑として保存しつつ手入力で計上できます。' }),
    ]));

    const postBtn = el('button.btn.primary', { text: '選択した領収書を一括計上', onclick: () => postAll() });
    wrap.appendChild(el('div.card', {}, [el('div.card-head', {}, [el('h2', { text: '② 内容を確認して計上' }), postBtn]), rowsBox]));

    const suggest = (vendor) => {
      const rules = s.autoRules || [];
      const rule = rules.find((r) => r.keyword && (vendor || '').includes(r.keyword));
      if (rule) return rule;
      return (A.reports && A.reports.learnAccount) ? A.reports.learnAccount(journals, vendor || '', 'out') : null;
    };

    const addRow = async (file) => {
      const { dataUrl, mime } = await shrink(file, 1600);
      const thumb = el('img.rc-thumb', { src: dataUrl });
      const dateI = el('input.sm-sel', { type: 'date', value: U.today() });
      const amtI = el('input.amt-in', { type: 'text', inputmode: 'numeric', placeholder: '金額' });
      const memoI = el('input', { type: 'text', placeholder: '店名・用途' });
      const accSel = accSelect('595'); const taxSel = taxSelect('purchase10'); const paySel = paySelect('100');
      amtI.addEventListener('blur', () => { amtI.value = amtI.value ? U.yen(U.parseYen(amtI.value)) : ''; });
      const status = el('span.muted.small', { text: aiReady ? '読み取り中…' : '手入力してください' });
      const chk = el('input', { type: 'checkbox' }); chk.checked = true;
      const rowObj = { dataUrl, mime, filename: file.name || 'receipt.jpg', dateI, amtI, memoI, accSel, taxSel, paySel, chk };
      rows.push(rowObj);
      rowsBox.appendChild(el('div.receipt-row', {}, [
        el('div.rc-left', {}, [chk, thumb]),
        el('div.rc-fields', {}, [
          el('div.form-row', {}, [el('label', {}, [el('span', { text: '日付' }), dateI]), el('label', {}, [el('span', { text: '金額(税込)' }), amtI])]),
          el('label', {}, [el('span', { text: '店名・摘要' }), memoI]),
          el('div.form-row', {}, [el('label', {}, [el('span', { text: '科目' }), accSel]), el('label', {}, [el('span', { text: '税区分' }), taxSel]), el('label', {}, [el('span', { text: '支払' }), paySel])]),
          status,
        ]),
      ]));

      if (aiReady) {
        try {
          const r = await A.sync.ocr(dataUrl, mime);
          if (r.ok) {
            const d = r.data || {};
            if (d.date) dateI.value = d.date;
            if (d.amount) amtI.value = U.yen(d.amount);
            if (d.summary || d.vendor) memoI.value = d.summary || d.vendor;
            if (d.taxRatePercent === 8) taxSel.value = 'purchase8'; else if (d.taxRatePercent === 10) taxSel.value = 'purchase10';
            const sug = suggest(d.vendor || d.summary || '');
            if (sug && sug.account) { accSel.value = sug.account; if (sug.tax) taxSel.value = sug.tax; }
            status.textContent = `読み取り完了：${d.vendor || ''} ¥${U.yen(d.amount || 0)}（要確認）`;
            status.className = 'ok small';
          } else { status.textContent = '自動読み取り不可：手入力してください'; }
        } catch (e) { status.textContent = '自動読み取りエラー：手入力してください'; }
      }
    };

    const handleFiles = async (files) => { for (const f of files) if (f.type.startsWith('image/')) await addRow(f); };
    fileIn.addEventListener('change', () => handleFiles([...fileIn.files]));
    drop.addEventListener('click', () => fileIn.click());
    drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('over'));
    drop.addEventListener('drop', (e) => { e.preventDefault(); drop.classList.remove('over'); handleFiles([...e.dataTransfer.files]); });

    const postAll = async () => {
      const targets = rows.filter((r) => r.chk.checked && U.parseYen(r.amtI.value) > 0 && r.accSel.value);
      if (!targets.length) return ui.toast('金額と科目を入力した領収書がありません', 'err');
      if (!await ui.confirm(`${targets.length}件を仕訳に計上し、画像を証憑として保存します。よろしいですか？`)) return;
      let n = 0;
      for (const r of targets) {
        const amt = U.parseYen(r.amtI.value);
        const j = await S.journals.save({
          source: 'expense', date: r.dateI.value, description: r.memoI.value || '領収書取込',
          lines: [
            { side: 'debit', account: r.accSel.value, tax: r.taxSel.value, amount: amt },
            { side: 'credit', account: r.paySel.value, tax: 'out', amount: amt },
          ],
        });
        await S.attachments.save({ journalId: j.id, date: r.dateI.value, amount: amt, partner: r.memoI.value || '', note: '領収書まとめて取込', filename: r.filename, mime: r.mime, size: (r.dataUrl || '').length, dataUrl: r.dataUrl });
        n += 1;
      }
      ui.toast(`${n}件を計上し、証憑を保存しました`, 'ok');
      ui.go('journal');
    };

    return wrap;
  });
})();
