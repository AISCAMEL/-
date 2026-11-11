/* help.js ― ヘルプ / 使い方ガイド（各機能の概要と各ページへのジャンプ） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui;

  // グループ: { title, items:[{icon,label,desc,to?}] }（to が無い項目はサーバー連携など画面なし）
  const GROUPS = [
    { title: 'はじめに', items: [
      { icon: '🧭', label: '初期セットアップ', desc: '会社名・期首月・登録番号・ロゴ・パスコードをまとめて設定。設定ページからやり直せます。', to: 'settings' },
      { icon: '💾', label: 'バックアップ', desc: 'データはブラウザ内に保存されます。設定から定期的に書き出して保管してください（消失防止）。', to: 'settings' },
    ] },
    { title: '日々の入力', items: [
      { icon: '📒', label: '仕訳帳', desc: '複式簿記で仕訳を直接入力。借方・貸方・税区分・車両を指定できます。', to: 'journal' },
      { icon: '💴', label: '経費・入出金', desc: 'かんたん入力で自動的に仕訳を生成。領収書OCRで写真から読み取りも可能。', to: 'expenses' },
      { icon: '📷', label: '領収書まとめて取込', desc: '写真・スクショを複数まとめて追加→自動読み取り→一括で仕訳に計上。画像は証憑として保存。', to: 'receipts' },
      { icon: '🏦', label: '銀行明細取込', desc: '銀行・クレジット・交通系ICのCSVを取込。過去の仕訳から科目を自動学習して推定。', to: 'import' },
      { icon: '📄', label: '請求書・見積書', desc: 'インボイス対応の請求書を作成・PDF出力。売上計上・入金記録まで。', to: 'invoices' },
      { icon: '🔁', label: '定期請求', desc: '毎月の請求をテンプレート化。アプリを開くと未発行の月を自動発行します。', to: 'recurring' },
    ] },
    { title: '車両・資産', items: [
      { icon: '🚗', label: '車両別損益', desc: '1台ごとに仕入＋諸経費→売却を紐づけ、粗利・在庫日数・在庫評価額を集計。仕入上限シミュレーターも。', to: 'vehicles' },
      { icon: '🚚', label: '固定資産', desc: '減価償却（定額・定率・一括・即時）を自動計算し、当期の償却費を計上。', to: 'assets' },
      { icon: '📦', label: '棚卸し', desc: '期末の棚卸資産を登録し、売上原価を正しく計上。', to: 'inventory' },
    ] },
    { title: 'レポート・分析', items: [
      { icon: '🏠', label: 'ダッシュボード', desc: '当期の売上・利益・現預金、やることリスト、売上/現預金の推移を一覧。', to: 'dashboard' },
      { icon: '📈', label: '月次推移', desc: '売上・費用・粗利・現預金残高の月次グラフと表。', to: 'monthly' },
      { icon: '🏬', label: '事業別損益', desc: '取引を事業（部門）に紐づけ、事業ごとの売上・利益・構成比を集計。全社合計は決算書で1箇所に。', to: 'departments' },
      { icon: '📉', label: '年度比較', desc: '前期との対比表と、売上・利益の前年同月比グラフ。', to: 'compare' },
      { icon: '📅', label: '資金繰り予定', desc: '今後12週の残高見通しをグラフ化。未入金・定期請求・予定から最低残高を警告。', to: 'cashforecast' },
      { icon: '🧾', label: '消費税集計', desc: 'インボイス対応の税率別集計と納付税額の試算（原則・簡易・2割特例）。', to: 'tax' },
      { icon: '🛡', label: 'AI監視センター', desc: '異常検知・アラーム（費用急増・資金・長期在庫・重複・未配賦など）を自動チェック。AI講評や事業の一括仕分けも。', to: 'aiwatch' },
      { icon: '🔍', label: '検索', desc: '仕訳・請求書・取引先・車両・証憑を横断検索。', to: 'search' },
    ] },
    { title: '決算・申告', items: [
      { icon: '📆', label: '決算・確定申告', desc: '年度締めチェック、損益振替、決算報告書の印刷/PDF。', to: 'closing' },
      { icon: '🗂', label: '決算パッケージ', desc: '税理士提出用の書類一式（決算書・試算表・内訳明細など）をPDF／CSV(ZIP)で一括出力。', to: 'package' },
      { icon: '🧮', label: '申告サポート', desc: '所得金額の調整（別表四の考え方）と法人税の概算、各種CSV出力。', to: 'filing' },
      { icon: '🔄', label: '期首残高・繰越', desc: '前期末残高を翌期の期首残高として繰り越し。', to: 'opening' },
    ] },
    { title: '自動化（サーバー連携・任意）', items: [
      { icon: '📧', label: 'Gmail取込', desc: 'USS精算書などのメールを自動検知し、添付PDFの金額を読み取って下書き計上（承認制）。' },
      { icon: '⚠', label: '在庫車アラート', desc: '長期在庫の車両を毎朝メールで督促（在庫日数のしきい値は設定で変更）。' },
      { icon: '📊', label: '月次レポート自動送信', desc: '毎月、売上・利益・現預金のサマリをメールで自動送信。' },
      { icon: '💳', label: '決済・口座連携', desc: 'Stripe/Square/PayPalの売上や、銀行・交通系ICのCSVを取込。詳しくは server/connectors をご覧ください。' },
    ] },
    { title: 'データ・設定', items: [
      { icon: '🏢', label: '取引先', desc: '取引先マスタ。登録番号(T番号)の妥当性を自動チェック。', to: 'partners' },
      { icon: '☁', label: 'クラウド同期', desc: '複数端末・複数人でデータを共有（同期サーバーが必要）。設定から有効化。', to: 'settings' },
      { icon: '🔒', label: 'ログインロック', desc: 'パスコードでアプリをロック。設定から変更できます。', to: 'settings' },
    ] },
  ];

  ui.register('help', async () => {
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('ヘルプ / 使い方ガイド'));
    wrap.appendChild(el('div.card', {}, [
      el('p', { text: 'このアプリは、サーバー不要でブラウザだけで動く自社会計ソフトです。データはお使いのブラウザ内に保存されます。' }),
      el('p.muted.small', { text: '各機能の「開く」で該当ページへ移動します。まずは「初期セットアップ」で会社情報を設定し、「経費・入出金」や「請求書」から使い始めるのがおすすめです。' }),
    ]));

    GROUPS.forEach((g) => {
      const card = el('div.card', {}, [el('h2', { text: g.title })]);
      g.items.forEach((it) => {
        card.appendChild(el('div.help-row', {}, [
          el('div.help-ico', { text: it.icon }),
          el('div.help-body', {}, [
            el('div.help-title', { text: it.label }),
            el('div.help-desc.muted.small', { text: it.desc }),
          ]),
          it.to ? el('button.btn.sm', { text: '開く', onclick: () => ui.go(it.to) }) : el('span.badge', { text: '連携' }),
        ]));
      });
      wrap.appendChild(card);
    });
    return wrap;
  });
})();
