import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { PwaRegister } from "@/components/pwa-register";
import { ChatWidget } from "@/components/chat/chat-widget";
import { MobileTabBar } from "@/components/mobile-tab-bar";
import { SITE, SITE_URL } from "@/lib/site";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  // 子ページはタイトルを裸で定義（例 "ログイン"）→ ここで自動的にサフィックス付与
  metadataBase: new URL(SITE_URL),
  title: { default: `${SITE.name}｜${SITE.tagline}`, template: `%s｜${SITE.name}` },
  description: SITE.description,
  openGraph: {
    siteName: SITE.name,
    locale: "ja_JP",
    type: "website",
    images: [{ url: "/og.jpg", width: 1200, height: 630, alt: `${SITE.name}｜${SITE.region.beach}` }],
  },
  twitter: { card: "summary_large_image" },
  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: SITE.shortName,
  },
};

export const viewport: Viewport = {
  themeColor: "#0B2540",
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="ja"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col pb-[calc(3.75rem+env(safe-area-inset-bottom))] lg:pb-0">
        {children}
        <PwaRegister />
        <ChatWidget />
        <MobileTabBar />
      </body>
    </html>
  );
}
