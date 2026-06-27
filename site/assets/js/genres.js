/* ============================================================
   BUYMO ジャンル別買取 — メイン(大分類)→サブ(小分類) 一元管理
   ここを編集すれば、以下すべてに自動反映：
     - フッターの「ジャンル別の買取」（全ページ）
     - メインLP中段の「ジャンル別買取」カード（#genre-cards）
     - ジャンルハブ /genre/（node の gen-genre.js が require して静的生成）
   各サブジャンルを公開したら：
     - url    … 公開URL（例 '/genre/haisha/'）
     - status … 'live'（リンク有効） / 'coming'（準備中）
   ============================================================ */
(function () {
  'use strict';

  // メイン(大分類) → サブ(小分類)
  var GROUPS = [
    { cat: '状態・お悩みで買取', icon: '♻️', items: [
      { name: '廃車買取',        slug: 'haisha',   icon: '♻️', desc: '動かない・価値が無いと思われた車も0円以上。引取・抹消も無料。' },
      { name: '事故車買取',      slug: 'jiko',     icon: '🚧', desc: '修復歴・損傷車も専門ルートで高価買取。事故後そのままでOK。' },
      { name: '不動車買取',      slug: 'fudou',    icon: '🔧', desc: 'エンジン不動・故障車もレッカー手配で対応。動かなくてもOK。' },
      { name: '水没車買取',      slug: 'suibotsu', icon: '🌊', desc: '冠水・水没車も部品需要で評価。被災車両もご相談ください。' },
      { name: '過走行車買取',    slug: 'kasoukou', icon: '🛣️', desc: '10万km超の多走行車も独自ルートで需要あり。' },
      { name: 'ローン中の車買取', slug: 'loan',     icon: '💳', desc: 'ローン残債ありでも買取可能。残債精算もサポート。' }
    ]},
    { cat: '人気車種で買取', icon: '⭐', items: [
      { name: 'ハイエース買取',     slug: 'hiace',       icon: '🚐', desc: '商用・キャンパーで需要大。ハイエースは高値が付きやすい。' },
      { name: 'ランドクルーザー買取', slug: 'landcruiser', icon: '🚙', desc: 'ランクルは国内外で人気。年式不問で高価買取。' },
      { name: 'アルファード買取',   slug: 'alphard',     icon: '🚐', desc: '高級ミニバンの代表格。グレード・装備を高評価。' },
      { name: 'プリウス買取',       slug: 'prius',       icon: '🔋', desc: '定番ハイブリッド。台数が多くても安定査定。' },
      { name: 'ジムニー買取',       slug: 'jimny',       icon: '🚙', desc: '高い人気で値落ちしにくい。旧型ジムニーも歓迎。' },
      { name: '軽トラ買取',         slug: 'keitora',     icon: '🛻', desc: '軽トラック・農用車も需要安定。過走行でもOK。' }
    ]},
    { cat: 'タイプ・区分で買取', icon: '🚗', items: [
      { name: '軽自動車買取',        slug: 'kei',    icon: '🚐', desc: '人気の軽自動車を高価買取。ターボ・スライドドアも。' },
      { name: 'SUV買取',            slug: 'suv',    icon: '🚙', desc: '需要の高いSUVを好条件で。' },
      { name: 'ミニバン買取',        slug: 'minivan',icon: '🚐', desc: 'ファミリー需要のミニバンを高評価。' },
      { name: 'セダン買取',          slug: 'sedan',  icon: '🚘', desc: '定番セダンも安定買取。' },
      { name: 'トラック・商用車買取', slug: 'truck',  icon: '🚚', desc: 'トラック・バン・商用車、法人まとめ売却も。' },
      { name: '高級車・輸入車買取',   slug: 'import', icon: '🏎️', desc: 'ブランド価値を正しく評価。輸入車専門査定。' },
      { name: 'EV・ハイブリッド買取', slug: 'ev',     icon: '🔋', desc: 'バッテリー状態も加味して適正査定。' }
    ]},
    { cat: '旧車・希少車で買取', icon: '🏁', items: [
      { name: '旧車買取',           slug: 'kyusha', icon: '🏁', desc: '旧車・クラシックカーは希少価値で高評価。不動でもOK。' },
      { name: '絶版・ネオクラ買取', slug: 'zeppan', icon: '📻', desc: '絶版車・ネオクラシックも専門ルートで買取。' }
    ]},
    { cat: 'パーツ・用品買取', icon: '🔩', items: [
      { name: 'アルミホイール買取', slug: 'wheel',  icon: '🛞', desc: '社外・純正アルミホイールを単体でも買取。タイヤ付きも。' },
      { name: 'タイヤ買取',         slug: 'tire',   icon: '🛞', desc: '夏・スタッドレス、ホイールセットも査定。' },
      { name: 'カー用品・パーツ買取', slug: 'parts', icon: '📟', desc: 'カーナビ・エアロ・マフラー等のパーツも買取。' }
    ]}
  ];

  // 各サブに既定値（url/status/cat）を付与し、フラット配列も作る
  var LIST = [];
  GROUPS.forEach(function (g) {
    g.items.forEach(function (it) {
      if (it.url == null) it.url = '#';
      if (it.status == null) it.status = 'coming';
      it.cat = g.cat;
      LIST.push(it);
    });
  });

  // node から require された場合はデータを返す（ハブ静的生成用）
  if (typeof module !== 'undefined' && module.exports) { module.exports = { groups: GROUPS, list: LIST }; }
  if (typeof document === 'undefined') return;

  function soonOf(g) { return g.status === 'coming' || !g.url || g.url === '#'; }
  function chip(g) {
    var soon = soonOf(g);
    var label = g.icon + ' ' + g.name + (soon ? '<span class="soon">準備中</span>' : '');
    return soon
      ? '<span class="genre-link disabled" aria-disabled="true">' + label + '</span>'
      : '<a class="genre-link" href="' + g.url + '">' + label + '</a>';
  }
  function card(g) {
    var soon = soonOf(g);
    var badge = soon ? '<span class="genre-card-soon">準備中</span>' : '<span class="genre-card-go">買取ページへ ›</span>';
    var inner = '<div class="genre-card-ico" aria-hidden="true">' + g.icon + '</div><h3>' + g.name + '</h3><p>' + g.desc + '</p>' + badge;
    return soon ? '<li><div class="genre-card disabled">' + inner + '</div></li>'
                : '<li><a class="genre-card" href="' + g.url + '">' + inner + '</a></li>';
  }

  function renderFooter() {
    var el = document.getElementById('genre-nav');
    if (!el) return;
    el.innerHTML = GROUPS.map(function (g) {
      return '<li class="genre-group"><span class="genre-cat">' + g.icon + ' ' + g.cat + '</span>' +
        g.items.map(chip).join('') + '</li>';
    }).join('');
  }

  function renderCards() {
    var el = document.getElementById('genre-cards');
    if (!el) return;
    el.innerHTML = GROUPS.map(function (g) {
      return '<div class="genre-group-block"><h3 class="genre-cat-title">' + g.icon + ' ' + g.cat + '</h3>' +
        '<ul class="genre-cards-grid">' + g.items.map(card).join('') + '</ul></div>';
    }).join('');
  }

  function run() { renderFooter(); renderCards(); }
  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
})();
