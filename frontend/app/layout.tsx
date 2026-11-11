import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'AIオペレーター24｜自動車業界専門のAI電話受付',
  description: '自動車販売・買取・整備/車検・板金に特化したAI電話受付。査定・車検予約・在庫問い合わせを24時間AIが対応し、要約して通知。商談中の電話も逃しません。',
};

// ブランドテーマの判定（描画前にdata-brandを確定＝色のチラつき防止）。
// 優先順: ?brand= → localStorage → NEXT_PUBLIC_BRAND → ホスト名(car./auto等) → 既定(allmighty)
const BRAND_ENV = process.env.NEXT_PUBLIC_BRAND ?? '';
const brandInitScript = `(function(){try{
var q=new URLSearchParams(location.search).get('brand');
if(q){try{localStorage.setItem('brand',q)}catch(e){}}
var ls=null;try{ls=localStorage.getItem('brand')}catch(e){}
var env=${JSON.stringify(BRAND_ENV)};
var host=location.hostname||'';
var byHost=/(^|\\.)car\\.|jidosha|kuruma|-auto|auto-/i.test(host)?'automotive':'';
var b=q||ls||env||byHost||'allmighty';
if(b!=='automotive'&&b!=='allmighty')b='allmighty';
document.documentElement.setAttribute('data-brand',b);
}catch(e){document.documentElement.setAttribute('data-brand','allmighty');}})();`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ja" data-brand="allmighty" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: brandInitScript }} />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&family=Zen+Kaku+Gothic+New:wght@500;700;900&display=swap"
          rel="stylesheet"
        />
      </head>
      <body>{children}</body>
    </html>
  );
}
