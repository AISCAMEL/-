import type { ReactNode } from "react";
import { M_PLUS_Rounded_1c } from "next/font/google";
import LogoutButton from "./logout-button";

const font = M_PLUS_Rounded_1c({
  weight: ["400", "500", "700"],
  subsets: ["latin"],
  display: "swap",
  preload: false,
});

export const metadata = {
  title: "Dropshipping Hub",
  description: "中国輸入 無在庫販売 ハブ — 管理ダッシュボード",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="ja" className={font.className}>
      <body style={{ margin: 0 }}>
        <header style={{ padding: "16px 24px", borderBottom: "1px solid #eee", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <span>
            <strong>🛒 Dropshipping Hub</strong>
            <span style={{ color: "#888", marginLeft: 12 }}>BASE × Alibaba / THE CKB</span>
          </span>
          <LogoutButton />
        </header>
        <main style={{ padding: 24 }}>{children}</main>
      </body>
    </html>
  );
}
