import type { ReactNode } from "react";
import { M_PLUS_Rounded_1c } from "next/font/google";
import LogoutButton from "./logout-button";
import "./globals.css";

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
      <body>
        <header
          style={{
            padding: "12px 24px",
            background: "var(--surface)",
            borderBottom: "1px solid var(--card-border)",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            position: "sticky",
            top: 0,
            zIndex: 10,
          }}
        >
          <a href="/" style={{ display: "flex", alignItems: "center", gap: 10, textDecoration: "none", color: "var(--ink)" }}>
            <span
              style={{
                width: 38, height: 38, borderRadius: 12, display: "grid", placeItems: "center",
                background: "var(--primary-weak)", fontSize: 20,
              }}
            >
              🐱
            </span>
            <span>
              <strong style={{ fontSize: 16 }}>necorope</strong>
              <span style={{ color: "var(--muted)", marginLeft: 8, fontSize: 13 }}>猫グッズ 無在庫販売ハブ</span>
            </span>
          </a>
          <LogoutButton />
        </header>
        <main style={{ padding: 24, maxWidth: 1200, margin: "0 auto" }}>{children}</main>
      </body>
    </html>
  );
}
