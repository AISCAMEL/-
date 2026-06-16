import type { ReactNode } from "react";

export const metadata = {
  title: "Dropshipping Hub",
  description: "中国輸入 無在庫販売 ハブ — 管理ダッシュボード",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="ja">
      <body style={{ fontFamily: "system-ui, sans-serif", margin: 0 }}>
        <header style={{ padding: "16px 24px", borderBottom: "1px solid #eee" }}>
          <strong>🛒 Dropshipping Hub</strong>
          <span style={{ color: "#888", marginLeft: 12 }}>BASE × Alibaba / THE CKB</span>
        </header>
        <main style={{ padding: 24 }}>{children}</main>
      </body>
    </html>
  );
}
