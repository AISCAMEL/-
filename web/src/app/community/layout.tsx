import { CommunityHeader } from "@/components/community/community-header";

export default function CommunityLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      {children}
      {/* LINE CTA（強い導線として常設） */}
      <div className="mx-auto max-w-3xl px-4 py-10">
        <a
          href="https://line.me/"
          target="_blank"
          rel="noreferrer"
          className="block rounded-2xl bg-teal/10 p-5 text-center text-sm text-navy/80 transition hover:bg-teal/20"
        >
          LINEでも、岩沢の波情報やイベントをお届けします 🌊
          <span className="mt-1 block font-medium text-teal">
            友だち追加する →
          </span>
        </a>
      </div>
    </div>
  );
}
