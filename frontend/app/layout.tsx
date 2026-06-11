import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'AIオペレーター24',
  description: 'もう、電話を取り逃がさない。AIが24時間、あなたの会社の電話受付に。',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ja">
      <body>{children}</body>
    </html>
  );
}
