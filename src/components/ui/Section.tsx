import { ReactNode } from "react";
import { Container } from "./Container";

export function Section({
  children,
  id,
  className = "",
  tone = "light",
}: {
  children: ReactNode;
  id?: string;
  className?: string;
  tone?: "light" | "muted" | "dark";
}) {
  const tones = {
    light: "bg-white text-ink-800",
    muted: "bg-slate-50 text-ink-800",
    dark: "bg-ink-900 text-white",
  };
  return (
    <section id={id} className={`py-16 sm:py-24 ${tones[tone]} ${className}`}>
      <Container>{children}</Container>
    </section>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  lead,
  align = "left",
  invert = false,
}: {
  eyebrow?: string;
  title: ReactNode;
  lead?: string;
  align?: "left" | "center";
  invert?: boolean;
}) {
  return (
    <div
      className={`max-w-3xl ${align === "center" ? "mx-auto text-center" : ""}`}
    >
      {eyebrow && (
        <span className={`eyebrow ${invert ? "text-accent-400 before:bg-accent-400" : ""}`}>
          {eyebrow}
        </span>
      )}
      <h2
        className={`mt-3 text-2xl sm:text-3xl md:text-4xl leading-tight ${
          invert ? "text-white" : "text-ink-900"
        }`}
      >
        {title}
      </h2>
      {lead && (
        <p
          className={`mt-4 text-base leading-relaxed ${
            invert ? "text-slate-300" : "text-ink-600"
          }`}
        >
          {lead}
        </p>
      )}
    </div>
  );
}
