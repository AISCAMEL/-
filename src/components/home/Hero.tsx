import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { Icon } from "@/components/ui/Icon";

const trustPoints = [
  "自動車業界 × IT/DX",
  "戦略から実行まで一気通貫",
  "創業〜開発〜集客まで対応",
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
              自動車業界のDX・販売支援を、
              <span className="bg-gradient-to-r from-accent-400 to-brand-400 bg-clip-text text-transparent">
                戦略から実行まで伴走。
              </span>
            </h1>

            <p className="mt-6 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
              合同会社アイズは、自動車販売の現場知見とITの実装力を掛け合わせるパートナーです。
              新規参入・販売拡大・DX化から、創業支援、Web制作・システム/アプリ開発まで。
              構想だけで終わらせず、成果が出るまで寄り添います。
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

          {/* 右: 3本柱の視覚的サマリー */}
          <div className="lg:col-span-5">
            <div className="grid gap-3">
              {[
                { icon: "car" as const, label: "自動車業界支援", desc: "販売戦略・新規参入・DX推進" },
                { icon: "rocket" as const, label: "創業・起業支援", desc: "資金調達・補助金・開業支援" },
                { icon: "code" as const, label: "Web・開発支援", desc: "制作・マーケ・システム/アプリ" },
              ].map((c) => (
                <div
                  key={c.label}
                  className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.06] p-5 backdrop-blur"
                >
                  <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-brand-600/30 text-accent-400 ring-1 ring-inset ring-white/10">
                    <Icon name={c.icon} className="h-6 w-6" />
                  </span>
                  <div>
                    <p className="font-semibold text-white">{c.label}</p>
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
