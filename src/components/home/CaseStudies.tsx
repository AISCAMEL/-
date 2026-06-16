import Link from "next/link";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { works } from "@/content/works";

export function CaseStudies() {
  return (
    <Section tone="light">
      <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
        <SectionHeading
          eyebrow="Works"
          title="支援実績・事例"
          lead="お客様の事業フェーズに合わせた支援事例をご紹介します。"
        />
        <Button href="/works" variant="secondary" className="flex-none">
          実績一覧を見る
        </Button>
      </div>

      {/* ダミーデータ明示の注記 */}
      {works.some((w) => w.isPlaceholder) && (
        <p className="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
          ※ 以下は構成確認用のサンプル（ダミー）です。実績が確定し次第、内容を差し替えてください。
        </p>
      )}

      <div className="mt-8 grid gap-6 lg:grid-cols-3">
        {works.map((w) => (
          <Link
            key={w.slug}
            href={`/works/${w.slug}`}
            className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition-all hover:-translate-y-1 hover:shadow-card-hover"
          >
            {/* サムネイル（画像未設定のためグラデーションのプレースホルダー） */}
            <div className="relative aspect-[16/9] bg-gradient-to-br from-brand-600 to-ink-900">
              <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-brand-700">
                {w.categoryLabel}
              </span>
              {w.isPlaceholder && (
                <span className="absolute right-4 top-4 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-bold text-amber-950">
                  サンプル
                </span>
              )}
            </div>
            <div className="flex flex-1 flex-col p-6">
              <h3 className="text-base font-bold text-ink-900">{w.title}</h3>
              <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{w.summary}</p>
              <p className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-accent-600">
                <Icon name="spark" className="h-4 w-4" />
                {w.result}
              </p>
            </div>
          </Link>
        ))}
      </div>
    </Section>
  );
}
