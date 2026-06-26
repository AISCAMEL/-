/* ============================================================
   BUYMO ジャンル別買取 — 一元管理リスト（唯一のデータソース）
   ここに1行追記すれば、以下すべてに自動反映されます：
     - フッターの「ジャンル別の買取」チップ（全ページ）
     - メインLP中段の「ジャンル別買取」カード（#genre-cards）
     - ジャンルハブ /genre/（node の gen-genre.js が require して静的生成）
   外部LPtoolsページにもこのJSを読み込めば同じ一覧が同期します。

   各ジャンルLPを公開したら：
     - url    … 公開URL（同一ドメインのサブディレクトリ推奨 例 '/genre/haisha/'）
     - status … 'live'（公開＝リンク有効） / 'coming'（準備中＝リンク無効）
   ============================================================ */
(function () {
  'use strict';

  var GENRES = [
    { name: '廃車買取',            slug: 'haisha',  icon: '♻️', url: '#', status: 'coming', desc: '動かない・価値が無いと思われた車も、まだ値段が付きます。引取・抹消手続きも無料。' },
    { name: '事故車買取',          slug: 'jiko',    icon: '🚧', url: '#', status: 'coming', desc: '修復歴・損傷のある車も専門ルートで高価買取。事故後そのままでOK。' },
    { name: '不動車買取',          slug: 'fudou',   icon: '🔧', url: '#', status: 'coming', desc: 'エンジン不動・故障車もレッカー手配で対応。動かなくても買取可能。' },
    { name: '水没車買取',          slug: 'suibotsu',icon: '🌊', url: '#', status: 'coming', desc: '冠水・水没車も部品需要で評価。被災車両のご相談もお任せください。' },
    { name: '過走行車買取',        slug: 'kasoukou',icon: '🛣️', url: '#', status: 'coming', desc: '10万km超の多走行車も独自ルートで需要あり。年式・距離で諦めないで。' },
    { name: '軽自動車買取',        slug: 'kei',     icon: '🚐', url: '#', status: 'coming', desc: '人気の軽自動車を高価買取。スライドドア車・ターボ車も歓迎。' },
    { name: 'トラック・商用車買取', slug: 'truck',   icon: '🚚', url: '#', status: 'coming', desc: 'トラック・バン・商用車も対応。法人のまとめ売却もご相談ください。' },
    { name: '高級車・輸入車買取',   slug: 'import',  icon: '🏎️', url: '#', status: 'coming', desc: '輸入車・高級車を専門査定。ブランド価値を正しく評価します。' },
    { name: 'EV・ハイブリッド買取', slug: 'ev',      icon: '🔋', url: '#', status: 'coming', desc: 'EV・PHV・ハイブリッドのバッテリー状態も加味して適正査定。' }
  ];

  // node から require された場合はデータだけ返す（ハブ静的生成用）
  if (typeof module !== 'undefined' && module.exports) { module.exports = GENRES; }

  // ブラウザ以外（document 無し）はここで終了
  if (typeof document === 'undefined') return;

  function soonOf(g) { return g.status === 'coming' || !g.url || g.url === '#'; }

  function renderFooter() {
    var el = document.getElementById('genre-nav');
    if (!el) return;
    el.innerHTML = GENRES.map(function (g) {
      var soon = soonOf(g);
      var label = g.icon + ' ' + g.name + (soon ? '<span class="soon">準備中</span>' : '');
      return soon
        ? '<li><span class="genre-link disabled" aria-disabled="true">' + label + '</span></li>'
        : '<li><a class="genre-link" href="' + g.url + '">' + label + '</a></li>';
    }).join('');
  }

  function renderCards() {
    var el = document.getElementById('genre-cards');
    if (!el) return;
    el.innerHTML = GENRES.map(function (g) {
      var soon = soonOf(g);
      var badge = soon ? '<span class="genre-card-soon">準備中</span>' : '<span class="genre-card-go">買取ページへ ›</span>';
      var inner =
        '<div class="genre-card-ico" aria-hidden="true">' + g.icon + '</div>' +
        '<h3>' + g.name + '</h3>' +
        '<p>' + g.desc + '</p>' + badge;
      return soon
        ? '<li><div class="genre-card disabled">' + inner + '</div></li>'
        : '<li><a class="genre-card" href="' + g.url + '">' + inner + '</a></li>';
    }).join('');
  }

  function run() { renderFooter(); renderCards(); }
  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
})();
