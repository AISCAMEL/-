import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'AIオペレーター24｜自動車業界専門のAI電話受付',
  description: '自動車販売・買取・整備/車検・板金に特化したAI電話受付。査定・車検予約・在庫問い合わせを24時間AIが対応し、要約して通知。商談中の電話も逃しません。',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ja">
      <head>
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
