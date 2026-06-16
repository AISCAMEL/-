import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { Reveal } from "@/components/ui/Reveal";
import { serviceGroups, getServicesByGroup } from "@/content/services";

/** 事業の全体像を図解する構造マップ（AIS → 3事業グループ → 各ブランド） */
export function BusinessMap() {
  return (
    <Section tone="muted">
      <Reveal>
        <SectionHeading
          eyebrow="Business Map"
          title="事業の全体像"
          lead="自動車事業を主軸に、IT・WEB事業、FC事業を展開。1社で複数の領域をつなぎ、総合的に課題解決をお手伝いします。"
          align="center"
        />
      </Reveal>

      {/* 中心ノード */}
      <Reveal delay={80}>
        <div className="mt-12 flex flex-col items-center">
          <div className="inline-flex flex-col items-center rounded-2xl bg-ink-900 px-8 py-5 text-center text-white shadow-card">
            <span className="text-xs font-semibold tracking-widest text-accent-400">
              AIS
            </span>
            <span className="mt-1 text-lg font-bold">合同会社アイズ</span>
            <span className="mt-0.5 text-[11px] text-slate-400">
              Always Innovation Solutions
            </span>
          </div>
          {/* 縦のコネクタ */}
          <span className="h-8 w-px bg-slate-300" aria-hidden="true" />
        </div>
      </Reveal>

      {/* 3グループ */}
      <div className="grid gap-5 md:grid-cols-3">
        {serviceGroups.map((group, gi) => {
          const items = getServicesByGroup(group.id);
          return (
            <Reveal key={group.id} delay={120 + gi * 90}>
              <div
                className={`flex h-full flex-col rounded-2xl border bg-white p-6 shadow-card ${
                  group.isPrimary
                    ? "border-brand-200 ring-1 ring-inset ring-brand-100"
                    : "border-slate-200"
                }`}
              >
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-base font-bold text-ink-900">{group.label}</h3>
                  {group.isPrimary && (
                    <span className="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">
                      主力
                    </span>
                  )}
                </div>

                <ul className="mt-4 space-y-2">
                  {items.map((s) => (
                    <li
                      key={s.slug}
                      className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2"
                    >
                      <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                        <Icon name={s.icon} className="h-4 w-4" />
                      </span>
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-semibold text-ink-900">
                          {s.brand ?? s.name}
                        </span>
                        <span className="block truncate text-[11px] text-ink-500">
                          {s.name}
                        </span>
                      </span>
                      {s.comingSoon && (
                        <span className="ml-auto flex-none rounded-full bg-accent-50 px-1.5 py-0.5 text-[9px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">
                          準備中
                        </span>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            </Reveal>
          );
        })}
      </div>
    </Section>
  );
}
