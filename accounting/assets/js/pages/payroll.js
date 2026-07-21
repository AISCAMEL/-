/* payroll.js ― 給与計算（従業員マスタ・給与明細・給与仕訳の自動生成） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const yenInput = (v) => {
    const i = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: v ? U.yen(v) : '' });
    i.addEventListener('blur', () => { i.value = i.value ? U.yen(U.parseYen(i.value)) : ''; });
    return i;
  };

  /* ---- 従業員マスタ ---------------------------------------------------- */
  const employeeModal = (emp) => {
    const e = emp || { name: '', base: 0, allowance: 0, commute: 0 };
    const name = el('input', { type: 'text', value: e.name, placeholder: '氏名' });
    const base = yenInput(e.base), allowance = yenInput(e.allowance), commute = yenInput(e.commute);
    const body = el('div.editor', {}, [
      el('label', {}, [el('span', { text: '氏名' }), name]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '基本給' }), base]), el('label', {}, [el('span', { text: '諸手当' }), allowance]), el('label', {}, [el('span', { text: '通勤費(非課税)' }), commute])]),
    ]);
    const m = ui.modal(emp ? '従業員の編集' : '従業員の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '保存', onclick: async () => {
          if (!name.value.trim()) return ui.toast('氏名を入力してください', 'err');
          const list = [...(S.settings.get().employees || [])];
          const rec = { id: e.id || U.uid('em'), name: name.value.trim(), base: U.parseYen(base.value), allowance: U.parseYen(allowance.value), commute: U.parseYen(commute.value) };
          const idx = list.findIndex((x) => x.id === rec.id);
          if (idx >= 0) list[idx] = rec; else list.push(rec);
          await S.settings.save({ employees: list });
          m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
        },
      })],
    });
  };

  /* ---- 給与明細の計算 -------------------------------------------------- */
  const calc = (input, rates) => {
    const gross = input.base + input.allowance + input.overtime + input.commute;
    const siBase = input.base + input.allowance + input.overtime; // 社保対象（通勤費を除く簡易）
    const health = Math.floor(siBase * rates.health / 100);
    const pension = Math.floor(siBase * rates.pension / 100);
    const employment = Math.floor(gross * rates.employment / 100);
    const social = health + pension + employment;
    const taxable = siBase - social; // 課税対象（源泉）
    const incomeTax = input.incomeTaxManual != null ? input.incomeTaxManual : Math.max(0, Math.floor(taxable * 0.05 / 10) * 10); // 概算
    const deductionTotal = social + incomeTax;
    const net = gross - deductionTotal;
    return { gross, siBase, health, pension, employment, social, incomeTax, deductionTotal, net };
  };

  const payslipModal = (existing) => {
    const s = S.settings.get();
    const employees = s.employees || [];
    if (!employees.length) return ui.toast('先に従業員を登録してください', 'err');
    const rates = { health: s.insHealthRate, pension: s.insPensionRate, employment: s.insEmploymentRate };
    const ps = existing || {};

    const empSel = el('select');
    employees.forEach((e) => { const o = el('option', { value: e.id, text: e.name }); if (e.id === ps.employeeId) o.selected = true; empSel.appendChild(o); });
    const month = el('input', { type: 'month', value: ps.month || U.today().slice(0, 7) });
    const base = yenInput(ps.base), allowance = yenInput(ps.allowance), overtime = yenInput(ps.overtime), commute = yenInput(ps.commute);
    const incomeTax = yenInput(ps.incomeTax);
    const result = el('div');

    const fillFromEmployee = () => {
      const e = employees.find((x) => x.id === empSel.value); if (!e) return;
      base.value = e.base ? U.yen(e.base) : ''; allowance.value = e.allowance ? U.yen(e.allowance) : ''; commute.value = e.commute ? U.yen(e.commute) : '';
      recompute();
    };
    const gather = () => ({ base: U.parseYen(base.value), allowance: U.parseYen(allowance.value), overtime: U.parseYen(overtime.value), commute: U.parseYen(commute.value), incomeTaxManual: incomeTax.value ? U.parseYen(incomeTax.value) : null });
    const recompute = () => {
      const c = calc(gather(), rates);
      if (!incomeTax.value) incomeTax.placeholder = '概算 ' + U.yen(c.incomeTax);
      result.innerHTML = '';
      result.appendChild(el('div.income-result', {}, [
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: '総支給額' }), el('span', { text: '¥' + U.yen(c.gross) })]),
          el('div.income-line', {}, [el('span', { text: '健康保険' }), el('span', { text: '△¥' + U.yen(c.health) })]),
          el('div.income-line', {}, [el('span', { text: '厚生年金' }), el('span', { text: '△¥' + U.yen(c.pension) })]),
          el('div.income-line', {}, [el('span', { text: '雇用保険' }), el('span', { text: '△¥' + U.yen(c.employment) })]),
        ]),
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: '源泉所得税' }), el('span', { text: '△¥' + U.yen(c.incomeTax) })]),
          el('div.income-line', {}, [el('span', { text: '控除合計' }), el('span', { text: '¥' + U.yen(c.deductionTotal) })]),
          el('div.income-line.big', {}, [el('span', { text: '差引支給額（手取）' }), el('span', { text: '¥' + U.yen(c.net) })]),
        ]),
      ]));
    };
    empSel.addEventListener('change', fillFromEmployee);
    [base, allowance, overtime, commute, incomeTax].forEach((i) => i.addEventListener('input', recompute));
    if (!existing) fillFromEmployee(); else recompute();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '従業員' }), empSel]), el('label', {}, [el('span', { text: '対象月' }), month])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '基本給' }), base]), el('label', {}, [el('span', { text: '諸手当' }), allowance]), el('label', {}, [el('span', { text: '残業手当' }), overtime]), el('label', {}, [el('span', { text: '通勤費' }), commute])]),
      el('label', {}, [el('span', { text: '源泉所得税（空欄で概算を使用）' }), incomeTax]),
      el('div.preview-box', {}, [el('div.muted', { text: '給与明細' }), result]),
    ]);

    const save = async (withJournal) => {
      const c = calc(gather(), rates);
      const e = employees.find((x) => x.id === empSel.value);
      if (c.gross <= 0) return ui.toast('支給額を入力してください', 'err');
      const rec = { id: ps.id, month: month.value, employeeId: empSel.value, name: e.name,
        base: U.parseYen(base.value), allowance: U.parseYen(allowance.value), overtime: U.parseYen(overtime.value), commute: U.parseYen(commute.value),
        gross: c.gross, health: c.health, pension: c.pension, employment: c.employment, incomeTax: c.incomeTax, deductionTotal: c.deductionTotal, net: c.net, journalId: ps.journalId };
      if (withJournal) {
        // 借)給料手当 総支給 / 貸)預り金(社保本人+源泉) / 貸)普通預金 手取
        const j = await S.journals.save({ source: 'payroll', date: month.value + '-25', description: `給与 ${rec.month} ${e.name}`, lines: [
          { side: 'debit', account: '520', tax: 'out', amount: c.gross },
          { side: 'credit', account: '230', tax: 'out', amount: c.deductionTotal, memo: '社会保険料・源泉所得税 預り' },
          { side: 'credit', account: '110', tax: 'out', amount: c.net, memo: '差引支給額' },
        ] });
        rec.journalId = j.id;
      }
      await S.payslips.save(rec);
      m.close(); ui.toast(withJournal ? '給与明細を保存し仕訳を計上しました' : '給与明細を保存しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal(existing ? '給与明細の編集' : '給与明細の作成', body, {
      footer: [
        el('button.btn', { text: 'キャンセル', onclick: () => m.close() }),
        el('button.btn', { text: '明細のみ保存', onclick: () => save(false) }),
        el('button.btn.primary', { text: '保存して仕訳計上', onclick: () => save(true) }),
      ],
    });
  };

  ui.register('payroll', async () => {
    const s = S.settings.get();
    const employees = s.employees || [];
    const payslips = await S.payslips.loadAll();

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('給与計算', [
      el('button.btn', { text: '＋ 従業員を登録', onclick: () => employeeModal(null) }),
      el('button.btn.primary', { text: '＋ 給与明細を作成', onclick: () => payslipModal(null) }),
    ]));

    // 社会保険料率の設定
    const hr = el('input', { type: 'number', step: '0.01', value: s.insHealthRate });
    const pr = el('input', { type: 'number', step: '0.01', value: s.insPensionRate });
    const er = el('input', { type: 'number', step: '0.01', value: s.insEmploymentRate });
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '社会保険料率（本人負担分・%）' }),
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '健康保険' }), hr]),
        el('label', {}, [el('span', { text: '厚生年金' }), pr]),
        el('label', {}, [el('span', { text: '雇用保険' }), er]),
        el('label', {}, [el('span', { text: ' ' }), el('button.btn', { text: '料率を保存', onclick: async () => { await S.settings.save({ insHealthRate: Number(hr.value), insPensionRate: Number(pr.value), insEmploymentRate: Number(er.value) }); ui.toast('保存しました', 'ok'); } })]),
      ]),
      el('p.muted.small', { text: '※ 料率は目安です。実際の標準報酬月額・等級・料率は協会けんぽ／年金機構の最新値をご確認ください。' }),
    ]));

    // 従業員一覧
    if (employees.length) {
      wrap.appendChild(el('div.card', {}, [
        el('h2', { text: '従業員' }),
        ui.table([
          { key: 'name', label: '氏名', render: (r) => r.name },
          { key: 'base', label: '基本給', align: 'right', render: (r) => '¥' + U.yen(r.base) },
          { key: 'allowance', label: '諸手当', align: 'right', render: (r) => '¥' + U.yen(r.allowance) },
          { key: 'commute', label: '通勤費', align: 'right', render: (r) => '¥' + U.yen(r.commute) },
          {
            key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
              el('button.icon-btn', { text: '✎', onclick: () => employeeModal(r) }),
              el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この従業員を削除しますか？')) { await S.settings.save({ employees: (S.settings.get().employees || []).filter((x) => x.id !== r.id) }); ui.toast('削除しました'); ui.renderRoute(); } } }),
            ]),
          },
        ], employees, { empty: '従業員がいません' }),
      ]));
    }

    // 給与明細一覧
    const card = el('div.card', {}, [el('h2', { text: '給与明細' })]);
    card.appendChild(ui.table([
      { key: 'month', label: '対象月', render: (r) => r.month },
      { key: 'name', label: '氏名', render: (r) => r.name },
      { key: 'gross', label: '総支給', align: 'right', render: (r) => '¥' + U.yen(r.gross) },
      { key: 'ded', label: '控除計', align: 'right', render: (r) => '¥' + U.yen(r.deductionTotal) },
      { key: 'net', label: '手取', align: 'right', render: (r) => '¥' + U.yen(r.net) },
      { key: 'j', label: '仕訳', render: (r) => r.journalId ? el('span.badge.in', { text: '計上済' }) : el('span.badge', { text: '未計上' }) },
      {
        key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
          el('button.icon-btn', { text: '✎', onclick: () => payslipModal(r) }),
          el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この給与明細（と関連仕訳）を削除しますか？')) { await S.payslips.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); } } }),
        ]),
      },
    ], payslips, { empty: '給与明細がありません' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
