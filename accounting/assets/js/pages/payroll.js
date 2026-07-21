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
    const e = emp || { name: '', base: 0, allowance: 0, commute: 0, dependents: 0 };
    const name = el('input', { type: 'text', value: e.name, placeholder: '氏名' });
    const base = yenInput(e.base), allowance = yenInput(e.allowance), commute = yenInput(e.commute);
    const dep = el('input', { type: 'number', min: '0', value: e.dependents || 0 });
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '氏名' }), name]), el('label', {}, [el('span', { text: '扶養親族等の数' }), dep])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '基本給' }), base]), el('label', {}, [el('span', { text: '諸手当' }), allowance]), el('label', {}, [el('span', { text: '通勤費(非課税)' }), commute])]),
    ]);
    const m = ui.modal(emp ? '従業員の編集' : '従業員の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '保存', onclick: async () => {
          if (!name.value.trim()) return ui.toast('氏名を入力してください', 'err');
          const list = [...(S.settings.get().employees || [])];
          const rec = { id: e.id || U.uid('em'), name: name.value.trim(), base: U.parseYen(base.value), allowance: U.parseYen(allowance.value), commute: U.parseYen(commute.value), dependents: Number(dep.value) || 0 };
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
    const afterSocial = siBase - social; // 社会保険料控除後の給与（源泉計算の基礎）
    // 源泉所得税＝電算機計算の特例（甲欄）。手入力があれば優先。
    const incomeTax = input.incomeTaxManual != null ? input.incomeTaxManual
      : A.payrolltax.monthlyWithholding(afterSocial, input.dependents || 0);
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
    const dep = el('input', { type: 'number', min: '0', value: ps.dependents != null ? ps.dependents : 0 });
    const incomeTax = yenInput(ps.incomeTax);
    const result = el('div');

    const fillFromEmployee = () => {
      const e = employees.find((x) => x.id === empSel.value); if (!e) return;
      base.value = e.base ? U.yen(e.base) : ''; allowance.value = e.allowance ? U.yen(e.allowance) : ''; commute.value = e.commute ? U.yen(e.commute) : '';
      dep.value = e.dependents || 0;
      recompute();
    };
    const gather = () => ({ base: U.parseYen(base.value), allowance: U.parseYen(allowance.value), overtime: U.parseYen(overtime.value), commute: U.parseYen(commute.value), dependents: Number(dep.value) || 0, incomeTaxManual: incomeTax.value ? U.parseYen(incomeTax.value) : null });
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
    [base, allowance, overtime, commute, incomeTax, dep].forEach((i) => i.addEventListener('input', recompute));
    if (!existing) fillFromEmployee(); else recompute();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '従業員' }), empSel]), el('label', {}, [el('span', { text: '対象月' }), month]), el('label', {}, [el('span', { text: '扶養人数' }), dep])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '基本給' }), base]), el('label', {}, [el('span', { text: '諸手当' }), allowance]), el('label', {}, [el('span', { text: '残業手当' }), overtime]), el('label', {}, [el('span', { text: '通勤費' }), commute])]),
      el('label', {}, [el('span', { text: '源泉所得税（空欄で電算特例の概算を使用）' }), incomeTax]),
      el('div.preview-box', {}, [el('div.muted', { text: '給与明細' }), result]),
    ]);

    const save = async (withJournal) => {
      const c = calc(gather(), rates);
      const e = employees.find((x) => x.id === empSel.value);
      if (c.gross <= 0) return ui.toast('支給額を入力してください', 'err');
      const rec = { id: ps.id, month: month.value, employeeId: empSel.value, name: e.name, dependents: Number(dep.value) || 0,
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

  /* ---- 保険料から控除額を計算 ----------------------------------------- */
  const insuranceCalc = (lifeField, quakeField) => {
    const general = yenInput(0), medical = yenInput(0), pension = yenInput(0), quake = yenInput(0), oldLong = yenInput(0);
    const out = el('div.jsummary');
    const recompute = () => {
      const lifeDed = A.payrolltax.lifeInsuranceDeduction(U.parseYen(general.value), U.parseYen(medical.value), U.parseYen(pension.value));
      const quakeDed = A.payrolltax.earthquakeInsuranceDeduction(U.parseYen(quake.value), U.parseYen(oldLong.value));
      out.innerHTML = '';
      out.appendChild(el('span', { html: `生命保険料控除 <b>¥${U.yen(lifeDed)}</b>` }));
      out.appendChild(el('span', { html: `地震保険料控除 <b>¥${U.yen(quakeDed)}</b>` }));
      out._life = lifeDed; out._quake = quakeDed;
    };
    [general, medical, pension, quake, oldLong].forEach((i) => i.addEventListener('input', recompute));
    recompute();
    const body = el('div.editor', {}, [
      el('div.muted.small', { text: '年間の支払保険料を入力してください（生命保険は新制度）。' }),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '一般生命保険料' }), general]), el('label', {}, [el('span', { text: '介護医療保険料' }), medical]), el('label', {}, [el('span', { text: '個人年金保険料' }), pension])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '地震保険料' }), quake]), el('label', {}, [el('span', { text: '旧長期損害保険料' }), oldLong])]),
      el('div.preview-box', {}, [el('div.muted', { text: '控除額' }), out]),
    ]);
    const m = ui.modal('保険料控除の計算', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '控除額を反映', onclick: () => { lifeField.value = out._life ? U.yen(out._life) : ''; quakeField.value = out._quake ? U.yen(out._quake) : ''; lifeField.dispatchEvent(new Event('input')); m.close(); },
      })],
    });
  };

  /* ---- 年末調整 -------------------------------------------------------- */
  const yearEndModal = async () => {
    const s = S.settings.get();
    const employees = s.employees || [];
    if (!employees.length) return ui.toast('先に従業員を登録してください', 'err');
    const allSlips = await S.payslips.loadAll();

    const empSel = el('select');
    employees.forEach((e) => empSel.appendChild(el('option', { value: e.id, text: e.name })));
    const yearI = el('input', { type: 'number', value: Number(U.today().slice(0, 4)) });
    const spouse = el('input', { type: 'checkbox' });
    const life = yenInput(0), quake = yenInput(0), mutual = yenInput(0), housing = yenInput(0);
    const result = el('div');

    const recompute = () => {
      const empId = empSel.value, year = String(yearI.value);
      const slips = allSlips.filter((p) => p.employeeId === empId && (p.month || '').startsWith(year));
      const emp = employees.find((e) => e.id === empId) || {};
      const salaryIncome = slips.reduce((a, p) => a + (p.gross - (p.commute || 0)), 0); // 課税支給合計
      const social = slips.reduce((a, p) => a + (p.health + p.pension + p.employment), 0);
      const withheld = slips.reduce((a, p) => a + p.incomeTax, 0);
      const r = A.payrolltax.yearEnd({ salaryIncome, socialInsurance: social, dependents: emp.dependents || 0, hasSpouse: spouse.checked, withheldTotal: withheld,
        lifeInsurance: U.parseYen(life.value), earthquakeInsurance: U.parseYen(quake.value), smallMutual: U.parseYen(mutual.value), housingCredit: U.parseYen(housing.value) });
      result.innerHTML = '';
      result.appendChild(el('div.income-result', {}, [
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: `給与収入（${slips.length}ヶ月）` }), el('span', { text: '¥' + U.yen(salaryIncome) })]),
          el('div.income-line', {}, [el('span', { text: '給与所得' }), el('span', { text: '¥' + U.yen(r.employmentIncome) })]),
          el('div.income-line', {}, [el('span', { text: '社会保険料控除' }), el('span', { text: '¥' + U.yen(social) })]),
          el('div.income-line', {}, [el('span', { text: '課税所得' }), el('span', { text: '¥' + U.yen(r.taxable) })]),
        ]),
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: '年税額' }), el('span', { text: '¥' + U.yen(r.yearTax) })]),
          el('div.income-line', {}, [el('span', { text: '源泉徴収済み' }), el('span', { text: '¥' + U.yen(r.withheld) })]),
          el('div.income-line.big', {}, [el('span', { text: r.diff >= 0 ? '過納（還付）' : '不足（追徴）' }), el('span', { text: '¥' + U.yenSigned(r.diff) })]),
        ]),
      ]));
      result._data = { r, emp };
    };
    empSel.addEventListener('change', recompute);
    yearI.addEventListener('input', recompute);
    spouse.addEventListener('change', recompute);
    [life, quake, mutual, housing].forEach((i) => i.addEventListener('input', recompute));
    recompute();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '従業員' }), empSel]), el('label', {}, [el('span', { text: '対象年' }), yearI]), el('label', {}, [el('span', { text: '配偶者控除' }), spouse])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '生命保険料控除' }), life]), el('label', {}, [el('span', { text: '地震保険料控除' }), quake]),
        el('label', {}, [el('span', { text: '　' }), el('button.btn.sm', { text: '保険料から計算', onclick: () => insuranceCalc(life, quake) })])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '小規模企業共済等(iDeco等)' }), mutual]), el('label', {}, [el('span', { text: '住宅ローン控除(税額控除)' }), housing])]),
      el('div.preview-box', {}, [el('div.muted', { text: '年末調整（電算特例に基づく概算）' }), result]),
      el('p.muted.small', { text: '※ 給与明細から自動集計した概算です。各種控除は控除額を直接入力してください。最新の税制・要件は別途ご確認ください。' }),
    ]);
    const post = async () => {
      const d = result._data; if (!d) return;
      const diff = d.r.diff; if (diff === 0) return ui.toast('過不足はありません');
      // 還付（diff>0）：借)預り金 / 貸)普通預金　追徴（diff<0）：借)普通預金 / 貸)預り金
      const amt = Math.abs(diff);
      const lines = diff > 0
        ? [{ side: 'debit', account: '230', tax: 'out', amount: amt }, { side: 'credit', account: '110', tax: 'out', amount: amt }]
        : [{ side: 'debit', account: '110', tax: 'out', amount: amt }, { side: 'credit', account: '230', tax: 'out', amount: amt }];
      await S.journals.save({ source: 'payroll', date: yearI.value + '-12-25', description: `年末調整 ${yearI.value} ${d.emp.name}（${diff > 0 ? '還付' : '追徴'}）`, lines });
      m.close(); ui.toast('年末調整の仕訳を計上しました', 'ok'); ui.renderRoute();
    };
    const m = ui.modal('年末調整', body, {
      footer: [el('button.btn', { text: '閉じる', onclick: () => m.close() }), el('button.btn.primary', { text: '過不足を仕訳計上', onclick: post })],
    });
  };

  ui.register('payroll', async () => {
    const s = S.settings.get();
    const employees = s.employees || [];
    const payslips = await S.payslips.loadAll();

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('給与計算', [
      el('button.btn', { text: '＋ 従業員を登録', onclick: () => employeeModal(null) }),
      el('button.btn', { text: '年末調整', onclick: () => yearEndModal() }),
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
