import Link from "next/link";
import { SITE } from "@/lib/site";

/** サイトのワードマーク（2段表示） */
export function Brand({ href = "/", light = false }: { href?: string; light?: boolean }) {
  return (
    <Link href={href} className="inline-flex flex-col leading-none">
      <span
        className={`text-lg font-semibold tracking-[0.2em] ${
          light ? "text-foam" : "text-navy"
        }`}
      >
        {SITE.wordmark.line1}
      </span>
      <span
        className={`text-[0.65rem] tracking-[0.45em] ${
          light ? "text-teal" : "text-ocean"
        }`}
      >
        {SITE.wordmark.line2}
      </span>
    </Link>
  );
}
