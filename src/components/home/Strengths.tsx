import { Section, SectionHeading } from "@/components/ui/Section";
import { Reveal } from "@/components/ui/Reveal";
import { strengths } from "@/content/home";

export function Strengths() {
  return (
    <Section tone="dark">
      <SectionHeading
        eyebrow="Why AIS"
        title={strengths.heading}
        lead={strengths.lead}
        align="center"
        invert
      />
      <div className="mt-12 grid gap-5 sm:grid-cols-2">
        {strengths.items.map((item, i) => (
          <Reveal
            key={item.no}
            delay={i * 80}
            className="rounded-2xl border border-white/10 bg-white/[0.04] p-7 backdrop-blur"
          >
            <div className="flex items-baseline gap-3">
              <span className="text-3xl font-bold text-accent-400">{item.no}</span>
              <h3 className="text-lg font-bold text-white">{item.title}</h3>
            </div>
            <p className="mt-3 text-sm leading-relaxed text-slate-300">{item.body}</p>
          </Reveal>
        ))}
      </div>
    </Section>
  );
}
