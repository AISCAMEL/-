import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { Icon } from "@/components/ui/Icon";

const trustPoints = [
  "販売・買取・オンライン納車・セキュリティ・レッカー",
  "ノーコードアプリ「APPREX」・サブスクWeb制作",
  "FC（カーメル／BUYMO）加盟募集",
];

export function Hero() {
  return (
    <section className="relative overflow-hidden bg-ink-900 text-white">
      {/* 背景: 未来感のあるグラデーション＋グリッド */}
      <div
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            "radial-gradient(70% 60% at 75% 10%, rgba(6,182,212,0.20), transparent 55%), radial-gradient(60% 70% at 10% 90%, rgba(37,99,235,0.28), transparent 55%)",
        }}
        aria-hidden="true"
      />
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.07]"
        style={{
          backgroundImage:
            "linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px)",
          backgroundSize: "56px 56px",
          maskImage: "radial-gradient(80% 80% at 50% 30%, black, transparent)",
        }}
        aria-hidden="true"
      />

      <Container className="relative">
        <div className="grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-12">
          <div className="lg:col-span-7">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-accent-400 ring-1 ring-inset ring-white/15">
              <Icon name="spark" className="h-4 w-4" />
              Always Innovation Solutions
            </span>

            <h1 className="mt-6 text-3xl font-bold leading-tight sm:text-4xl md:text-5xl lg:text-[3.25rem]">
              クルマのことを、
              <span className="bg-gradient-to-r from-accent-400 to-brand-400 bg-clip-text text-transparent">
                一社でまるごと。
              </span>
            </h1>

            <p className="mt-6 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
              合同会社アイズは、自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカーまで、クルマに関わるすべてを手がける会社です。
              さらに、ノーコードアプリ開発「APPREX」やサブスクWeb制作「WEB crews」、FC事業まで。あなたのニーズにワンストップでお応えします。
            </p>

            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
              <Button href="/contact" size="lg">
                無料で相談する
                <Icon name="arrow-right" className="h-4 w-4" />
              </Button>
              <Button href="/services" size="lg" variant="ghost">
                サービスを見る
              </Button>
            </div>

            <ul className="mt-10 flex flex-wrap gap-x-6 gap-y-3">
              {trustPoints.map((t) => (
                <li key={t} className="flex items-center gap-2 text-sm text-slate-300">
                  <Icon name="check" className="h-4 w-4 flex-none text-accent-400" />
                  {t}
                </li>
              ))}
            </ul>
          </div>

          {/* 右: 事業の視覚的サマリー（自動車を主力として強調） */}
          <div className="lg:col-span-5">
            <div className="grid gap-3">
              {[
                { icon: "car" as const, label: "自動車事業", desc: "販売・買取・オンライン納車・セキュリティ・レッカー", primary: true },
                { icon: "app" as const, label: "IT・WEB事業", desc: "APPREX／WEB crews／AIオペレーター24" },
                { icon: "store" as const, label: "FC事業", desc: "カーメル／BUYMO 加盟募集" },
              ].map((c) => (
                <div
                  key={c.label}
                  className={`flex items-center gap-4 rounded-2xl border p-5 backdrop-blur ${
                    c.primary
                      ? "border-accent-400/40 bg-white/[0.10] ring-1 ring-inset ring-accent-400/30"
                      : "border-white/10 bg-white/[0.06]"
                  }`}
                >
                  <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-brand-600/30 text-accent-400 ring-1 ring-inset ring-white/10">
                    <Icon name={c.icon} className="h-6 w-6" />
                  </span>
                  <div>
                    <p className="flex items-center gap-2 font-semibold text-white">
                      {c.label}
                      {c.primary && (
                        <span className="rounded-full bg-accent-400/20 px-2 py-0.5 text-[10px] font-bold text-accent-300">
                          主力事業
                        </span>
                      )}
                    </p>
                    <p className="text-sm text-slate-400">{c.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}
