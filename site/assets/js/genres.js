/* ============================================================
   BUYMO ジャンル別買取 — 一元管理リスト
   ここに1行追記すれば、全ページのフッター「ジャンル別の買取」に
   自動で反映されます（外部LPtoolsページにもこのJSを読み込めば同期）。

   各ジャンルLPを公開したら：
     - url    … 公開URL（同一ドメインのサブディレクトリ推奨 例 '/genre/haisha/'）
     - status … 'live'（公開＝リンク有効） / 'coming'（準備中＝リンク無効）
   ============================================================ */
(function () {
  'use strict';

  var GENRES = [
    { name: '廃車買取',            icon: '♻️', url: '#', status: 'coming' },
    { name: '事故車買取',          icon: '🚧', url: '#', status: 'coming' },
    { name: '不動車買取',          icon: '🔧', url: '#', status: 'coming' },
    { name: '水没車買取',          icon: '🌊', url: '#', status: 'coming' },
    { name: '過走行車買取',        icon: '🛣️', url: '#', status: 'coming' },
    { name: '軽自動車買取',        icon: '🚐', url: '#', status: 'coming' },
    { name: 'トラック・商用車買取', icon: '🚚', url: '#', status: 'coming' },
    { name: '高級車・輸入車買取',   icon: '🏎️', url: '#', status: 'coming' },
    { name: 'EV・ハイブリッド買取', icon: '🔋', url: '#', status: 'coming' }
  ];

  function render() {
    var el = document.getElementById('genre-nav');
    if (!el) return;
    el.innerHTML = GENRES.map(function (g) {
      var soon = (g.status === 'coming' || !g.url || g.url === '#');
      var label = g.icon + ' ' + g.name + (soon ? '<span class="soon">準備中</span>' : '');
      if (soon) {
        return '<li><span class="genre-link disabled" aria-disabled="true">' + label + '</span></li>';
      }
      return '<li><a class="genre-link" href="' + g.url + '">' + label + '</a></li>';
    }).join('');
  }

  if (document.readyState !== 'loading') render();
  else document.addEventListener('DOMContentLoaded', render);
})();
