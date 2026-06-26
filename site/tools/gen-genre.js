/* ============================================================
   BUYMO 買取ジャンル ハブページ ジェネレーター
   genres.js（唯一のデータソース）を require → site/genre/index.html を静的生成
   実行: node tools/gen-genre.js  （site/ で）
   ============================================================ */
'use strict';
const fs = require('fs');
const path = require('path');
const { header, footer } = require('./_layout');
const GENRES = require('../assets/js/genres');

const SITE_URL = ''; // 公開ドメイン確定後に設定すると canonical が絶対URLに
const ROOT = path.resolve(__dirname, '..');
const rel = '../'; // /genre/ から見たアセット相対
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function cards() {
  return GENRES.map(g => {
    const soon = g.status === 'coming' || !g.url || g.url === '#';
    const badge = soon ? '<span class="genre-card-soon">準備中</span>' : '<span class="genre-card-go">買取ページへ ›</span>';
    const inner = `<div class="genre-card-ico" aria-hidden="true">${g.icon}</div><h3>${esc(g.name)}</h3><p>${esc(g.desc)}</p>${badge}`;
    return soon
      ? `<li><div class="genre-card disabled">${inner}</div></li>`
      : `<li><a class="genre-card" href="${esc(g.url)}">${inner}</a></li>`;
  }).join('\n        ');
}

const canonical = SITE_URL ? `${SITE_URL}/genre/` : './';
const title = '買取ジャンル一覧｜廃車・事故車・不動車もBUYMO';
const desc = '廃車・事故車・不動車・水没車・過走行車・軽自動車・トラック・輸入車・EVまで。状態や種類を問わず車を高価買取するBUYMOのジャンル別買取一覧。手数料無料・無料出張査定・最短即日入金。';

const html = `<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}" />
<meta name="theme-color" content="#FF6B35" />
<link rel="canonical" href="${esc(canonical)}" />
<link rel="icon" type="image/svg+xml" href="${rel}assets/img/favicon.svg" />
<meta property="og:type" content="website" />
<meta property="og:title" content="${esc(title)}" />
<meta property="og:description" content="${esc(desc)}" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&display=swap" />
<link rel="stylesheet" href="${rel}assets/css/buymo.css" />
<link rel="stylesheet" href="${rel}assets/css/buymo-area.css" />
</head>
<body>
${header(rel, 'genre')}
<main>
  <section class="page-hero area-hero" aria-labelledby="page-title">
    <div class="container">
      <nav class="breadcrumb" aria-label="パンくずリスト"><a href="${rel}buymo.html#top">ホーム</a><span aria-hidden="true">›</span><span>買取ジャンル</span></nav>
      <h1 id="page-title">あらゆる車を、<span class="hl">ジャンル別</span>に高価買取</h1>
      <p class="page-lead">廃車・事故車・不動車から軽自動車・トラック・輸入車・EVまで。状態や種類を問わず、専門の査定で1円でも高く買い取ります。気になるジャンルをお選びください。</p>
    </div>
  </section>

  <section class="genres-section" aria-label="買取ジャンル一覧">
    <div class="container">
      <ul id="genre-cards" class="genre-cards">
        ${cards()}
      </ul>
      <p class="area-note center">「準備中」のジャンルもお電話・フォームから今すぐご相談いただけます。</p>
    </div>
  </section>

  <section class="form-section" aria-labelledby="cta-title">
    <div class="container area-bottom-cta">
      <h2 id="cta-title">どのジャンルでもまずは無料査定</h2>
      <p>状態を問わず査定無料。最短即日でご連絡します。</p>
      <div class="area-cta">
        <a href="${rel}buymo-contact.html" class="btn btn-light btn-lg">無料査定を依頼</a>
        <a href="tel:05017223365" class="btn btn-tel-light">📞 0120-123-456</a>
      </div>
    </div>
  </section>
</main>
${footer(rel)}
</body>
</html>
`;

fs.mkdirSync(path.join(ROOT, 'genre'), { recursive: true });
fs.writeFileSync(path.join(ROOT, 'genre', 'index.html'), html);
console.log('generated genre hub (/genre/index.html) with', GENRES.length, 'genres');
