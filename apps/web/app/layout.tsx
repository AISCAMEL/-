import type { ReactNode } from "react";
import { M_PLUS_Rounded_1c } from "next/font/google";
import LogoutButton from "./logout-button";
import Logo from "./logo";
import "./globals.css";

const font = M_PLUS_Rounded_1c({
  weight: ["400", "500", "700"],
  subsets: ["latin"],
  display: "swap",
  preload: false,
});

export const metadata = {
  title: "necorope - 猫グッズ無在庫販売ハブ",
  description: "猫グッズ 無在庫販売 管理ダッシュボード",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="ja" className={font.className}>
      <body>
        <header
          style={{
            padding: "10px 24px",
            background: "linear-gradient(135deg, #fce7f3 0%, #ede9fe 50%, #d1fae5 100%)",
            borderBottom: "2px solid #fde2d4",
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
                width: 44, height: 44, borderRadius: 14, display: "grid", placeItems: "center",
                background: "#fff",
                boxShadow: "0 2px 8px rgba(244,114,182,0.15)",
              }}
            >
              <Logo size={34} />
            </span>
            <span style={{ display: "flex", flexDirection: "column" }}>
              <strong style={{ fontSize: 17, letterSpacing: "0.02em" }}>necorope</strong>
              <span style={{ color: "var(--muted)", fontSize: 11 }}>猫グッズ 無在庫販売ハブ</span>
            </span>
          </a>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <span style={{ fontSize: 20 }}>🐾</span>
            <LogoutButton />
          </div>
        </header>
        <main style={{ padding: 24, maxWidth: 1200, margin: "0 auto" }}>{children}</main>
        <footer style={{ textAlign: "center", padding: "24px 0", color: "var(--muted)", fontSize: 12 }}>
          🐱 necorope &copy; 2024 — 猫グッズで世界をハッピーに
        </footer>
      </body>
    </html>
  );
}
