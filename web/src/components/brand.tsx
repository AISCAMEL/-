import Link from "next/link";

/** IWASAWA SURF BASE ワードマーク */
export function Brand({ href = "/", light = false }: { href?: string; light?: boolean }) {
  return (
    <Link href={href} className="inline-flex flex-col leading-none">
      <span
        className={`text-lg font-semibold tracking-[0.2em] ${
          light ? "text-foam" : "text-navy"
        }`}
      >
        IWASAWA
      </span>
      <span
        className={`text-[0.65rem] tracking-[0.45em] ${
          light ? "text-teal" : "text-ocean"
        }`}
      >
        SURF BASE
      </span>
    </Link>
  );
}
