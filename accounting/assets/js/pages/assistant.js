/* assistant.js ― AI会計アシスタント（財務診断・用語Q&A・任意のLLM相談） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  /* ---- ローカル知識ベース（APIキー不要・オフラインで回答） ------------- */
  const KB = [
    { k: ['仕訳', 'しわけ'], a: '仕訳とは、取引を「借方」と「貸方」に分けて記録することです。例：現金で売上5,500円 → 借）現金 5,500 ／ 貸）売上高 5,500。左右の合計は必ず一致します。「仕訳帳」から入力できます。' },
    { k: ['借方', '貸方', 'かりかた', 'かしかた'], a: '借方（左）は資産の増加・費用の発生、貸方（右）は負債/純資産/収益の増加を表します。資産・費用が増えたら借方、収益・負債が増えたら貸方が基本です。' },
    { k: ['消費税', 'インボイス', '適格請求書'], a: '消費税は課税売上に係る税（仮受）から課税仕入に係る税（仮払）を差し引いて納付します。インボイス制度では、仕入税額控除に適格請求書（登録番号 T+13桁）が必要です。当アプリは10%/8%区分・原則/簡易/2割特例に対応しています（消費税集計ページ）。' },
    { k: ['減価償却', 'げんかしょうきゃく'], a: '減価償却は、固定資産の取得費を耐用年数にわたって費用配分する処理です。定額法・定率法・一括償却・少額特例に対応しています（固定資産ページ）。当期分は自動計算して仕訳計上できます。' },
    { k: ['青色申告', '白色申告'], a: '青色申告は、複式簿記での記帳等を要件に、最大65万円の特別控除や赤字の繰越などの特典があります。当アプリは複式簿記で記帳するため青色申告の帳簿要件に対応しやすい構成です。' },
    { k: ['経費', '損金', 'けいひ'], a: '事業に必要な支出は経費（法人では損金）になります。家賃・通信費・旅費交通費・消耗品費など。自宅兼事務所は事業使用割合で按分します。私的支出は経費になりません。' },
    { k: ['決算', '損益振替'], a: '決算では、当期の収益・費用を締めて当期純利益を確定し、繰越利益剰余金へ振り替えます（損益振替）。「決算・確定申告」ページで損益振替の計上と決算報告書の出力ができます。' },
    { k: ['源泉', '源泉徴収', '年末調整'], a: '給与からは源泉所得税を天引きし、年末に年末調整で精算します。当アプリは電子計算機計算の特例で月次源泉を概算し、年末調整（各種控除・過不足）も計算できます（給与計算ページ）。' },
    { k: ['貸借対照表', 'BS', 'ばらんす'], a: '貸借対照表(B/S)は、ある時点の「資産＝負債＋純資産」を表す財務諸表です。会社の財政状態がわかります（決算書ページ）。' },
    { k: ['損益計算書', 'PL', '利益'], a: '損益計算書(P/L)は、一定期間の「収益−費用＝利益」を表す財務諸表です。会社の経営成績がわかります（決算書ページ）。' },
    { k: ['売掛金', '買掛金'], a: '売掛金は「後で受け取るお金（掛け売り）」、買掛金は「後で支払うお金（掛け仕入）」です。入金・支払時に消し込みます。' },
    { k: ['簿記', 'ぼき'], a: '簿記は取引を記録・計算・整理する技術です。当アプリは複式簿記（借方＝貸方）で、仕訳から試算表・決算書まで自動集計します。' },
  ];
  const localAnswer = (q, ctx) => {
    const s = (q || '');
    // 数字を聞かれたらデータで答える
    if (/売上|収益/.test(s)) return `当期の売上（収益）は ¥${U.yen(ctx.revenue)} です。費用は ¥${U.yen(ctx.expense)}、当期純利益は ¥${U.yenSigned(ctx.net)}（利益率 ${Math.round(ctx.profitRate * 100)}%）です。`;
    if (/利益|儲/.test(s)) return `当期純利益は ¥${U.yenSigned(ctx.net)}（利益率 ${Math.round(ctx.profitRate * 100)}%）です。売上 ¥${U.yen(ctx.revenue)}／費用 ¥${U.yen(ctx.expense)}。`;
    if (/現金|預金|残高|キャッシュ/.test(s)) return `現預金残高は ¥${U.yen(ctx.cash)} です。売掛金 ¥${U.yen(ctx.receivable)}、買掛金 ¥${U.yen(ctx.payable)}。`;
    if (/消費税.*(いくら|納付|いくら)/.test(s) || /納税|納付/.test(s)) return `課税売上（税抜）は ¥${U.yen(ctx.taxableSalesNet)} です。納付額の試算は「消費税集計」ページで原則/簡易/2割特例を比較できます。`;
    const hit = KB.find((e) => e.k.some((k) => s.includes(k)));
    if (hit) return hit.a;
    return null;
  };

  ui.register('assistant', async () => {
    const journals = await S.journals.loadAll();
    const invoices = await S.invoices.loadAll();
    const assets = await S.assets.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);
    const adv = R.advisor(journals, invoices, assets, s, fy.start, fy.end);
    const ctx = adv.metrics;

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('AI会計アシスタント', [
      el('span.muted', { text: `${fy.start.slice(0, 4)}年度の診断` }),
    ]));

    // 財務指標
    const m = adv.metrics;
    wrap.appendChild(el('div.stat-grid', {}, [
      (() => el('div.stat.good', {}, [el('div.stat-label', { text: '売上' }), el('div.stat-value', { text: '¥' + U.yen(m.revenue) })]))(),
      (() => el('div.stat.' + (m.net >= 0 ? 'good' : 'bad'), {}, [el('div.stat-label', { text: '当期純利益' }), el('div.stat-value', { text: '¥' + U.yenSigned(m.net) })]))(),
      (() => el('div.stat', {}, [el('div.stat-label', { text: '利益率' }), el('div.stat-value', { text: Math.round(m.profitRate * 100) + '%' })]))(),
      (() => el('div.stat', {}, [el('div.stat-label', { text: '流動比率' }), el('div.stat-value', { text: m.currentRatio === null ? '—' : Math.round(m.currentRatio * 100) + '%' })]))(),
    ]));

    // 健全性チェック
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '健全性チェック' }),
      adv.alerts.length
        ? el('div.todo-list', {}, adv.alerts.map((a) => el('div.todo-item.' + a.level, {}, [el('span.todo-dot'), el('span', { text: a.text })])))
        : el('p.muted', { text: '大きな問題は見つかりませんでした。' }),
    ]));

    // アドバイス
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '改善・節税のヒント' }),
      el('ul.tips', {}, adv.tips.map((t) => el('li', { text: t }))),
    ]));

    // Q&A / AI相談
    const aiConfigured = !!(s.syncUrl && s.syncWorkspace);
    const log = el('div.chat-log');
    const input = el('input', { type: 'text', placeholder: '質問を入力（例：消費税とは？ 今期の利益は？）' });
    const useAi = el('input', { type: 'checkbox' }); useAi.checked = aiConfigured;
    const pushMsg = (who, text) => { log.appendChild(el('div.chat-msg.' + who, {}, [el('div.chat-bubble', { text })])); log.scrollTop = log.scrollHeight; };

    const send = async () => {
      const q = input.value.trim(); if (!q) return;
      pushMsg('user', q); input.value = '';
      const local = localAnswer(q, ctx);
      if (useAi.checked && aiConfigured && A.sync && A.sync.ai) {
        pushMsg('ai', '考え中…');
        const thinking = log.lastChild;
        try {
          const r = await A.sync.ai(q, adv);
          thinking.remove();
          if (r.ok) pushMsg('ai', r.answer);
          else { pushMsg('ai', (local || 'うまく回答できませんでした') + `\n（AI連携エラー：${r.message}）`); }
        } catch (e) { thinking.remove(); pushMsg('ai', (local || '回答できませんでした') + `\n（AI連携エラー：${e.message}）`); }
      } else {
        pushMsg('ai', local || 'この質問はローカル知識ベースに該当がありませんでした。設定でクラウド同期サーバー＋AI連携を有効にすると、より詳しく回答できます。用語（仕訳・消費税・減価償却 など）や数値（売上・利益・現金）について聞いてみてください。');
      }
    };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') send(); });

    pushMsg('ai', 'こんにちは。会計・税務の質問にお答えします。財務データに基づく数値（売上・利益・現金）や、用語の説明ができます。' + (aiConfigured ? '「AIで回答」をオンにすると、AI連携で詳しく相談できます。' : '（設定でAI連携を有効にすると、より高度な相談ができます）'));

    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: 'AIに相談' }),
        aiConfigured ? el('label.inline', {}, [useAi, el('span.small', { text: 'AIで回答（サーバー連携）' })]) : el('span.muted.small', { text: 'ローカル回答モード' })]),
      log,
      el('div.chat-input', {}, [input, el('button.btn.primary', { text: '送信', onclick: send })]),
      el('p.muted.small', { text: '※ 本アシスタントの回答は参考情報です。税務・会計の最終判断は税理士等の専門家にご確認ください。' }),
    ]));
    return wrap;
  });
})();
