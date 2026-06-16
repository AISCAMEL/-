import { Section } from "@/components/ui/Section";
import { Button } from "@/components/ui/Button";
import { message } from "@/content/home";

export function Message() {
  return (
    <Section tone="light">
      <div className="mx-auto max-w-3xl text-center">
        <span className="eyebrow mx-auto justify-center">Message</span>
        <h2 className="mt-4 text-3xl font-bold text-ink-900 sm:text-4xl">
          {message.heading}
        </h2>
        <div className="mt-6 space-y-4">
          {message.body.map((p, i) => (
            <p key={i} className="text-base leading-relaxed text-ink-600">
              {p}
            </p>
          ))}
        </div>
        <div className="mt-8">
          <Button href="/philosophy" variant="secondary">
            私たちの理念を見る
          </Button>
        </div>
      </div>
    </Section>
  );
}
