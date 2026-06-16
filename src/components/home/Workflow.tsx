import { Section, SectionHeading } from "@/components/ui/Section";
import { workflow } from "@/content/home";

export function Workflow() {
  return (
    <Section tone="muted">
      <SectionHeading
        eyebrow="Flow"
        title={workflow.heading}
        lead={workflow.lead}
        align="center"
      />
      <ol className="mt-12 grid gap-4 md:grid-cols-5">
        {workflow.steps.map((step, i) => (
          <li key={step.no} className="relative">
            <div className="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-card">
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                {i + 1}
              </span>
              <h3 className="mt-4 text-base font-bold text-ink-900">{step.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-ink-600">{step.body}</p>
            </div>
          </li>
        ))}
      </ol>
    </Section>
  );
}
