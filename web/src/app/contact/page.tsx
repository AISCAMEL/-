import type { Metadata } from "next";
import { SitePage } from "@/components/site-page";
import { ContactForm } from "@/components/contact-form";

export const metadata: Metadata = {
  title: "お問い合わせ｜IWASAWA SURF BASE",
};

export default function ContactPage() {
  return (
    <SitePage
      title="お問い合わせ"
      lead="サービス・スクール・不具合など、お気軽にご連絡ください。"
    >
      <div className="grid gap-8 md:grid-cols-[1.4fr_1fr]">
        <ContactForm />

        <aside className="space-y-4">
          <a
            href="https://line.me/"
            target="_blank"
            rel="noreferrer"
            className="block rounded-2xl bg-teal/10 p-5 text-sm text-navy/80 transition hover:bg-teal/20"
          >
            <p className="font-medium text-teal">LINEで相談する 🌊</p>
            <p className="mt-1 text-xs text-navy/60">
              波情報・イベントもLINEでお届けします。友だち追加はこちら。
            </p>
          </a>
          <div className="rounded-2xl border border-navy/10 bg-white p-5 text-sm text-navy/70">
            <p className="font-medium text-navy">運営情報</p>
            <p className="mt-2 text-xs leading-relaxed text-navy/50">
              福島県双葉郡広野町・岩沢海岸エリア
              <br />
              事業者情報は{" "}
              <a href="/legal/tokushoho" className="text-ocean hover:underline">
                特定商取引法表記
              </a>{" "}
              をご覧ください。
            </p>
          </div>
        </aside>
      </div>
    </SitePage>
  );
}
