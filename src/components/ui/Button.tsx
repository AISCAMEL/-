import Link from "next/link";
import { ReactNode } from "react";

type Variant = "primary" | "secondary" | "ghost";
type Size = "md" | "lg";

const base =
  "inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 disabled:pointer-events-none";

const variants: Record<Variant, string> = {
  primary:
    "bg-brand-600 text-white shadow-card hover:bg-brand-700 hover:shadow-card-hover focus-visible:ring-brand-500",
  secondary:
    "bg-white text-brand-700 ring-1 ring-inset ring-brand-200 hover:bg-brand-50 focus-visible:ring-brand-500",
  ghost:
    "bg-white/10 text-white ring-1 ring-inset ring-white/30 hover:bg-white/20 focus-visible:ring-white",
};

const sizes: Record<Size, string> = {
  md: "px-5 py-2.5 text-sm",
  lg: "px-7 py-3.5 text-base",
};

type CommonProps = {
  children: ReactNode;
  variant?: Variant;
  size?: Size;
  className?: string;
};

export function Button({
  href,
  children,
  variant = "primary",
  size = "md",
  className = "",
  ...rest
}: CommonProps & {
  href: string;
} & React.AnchorHTMLAttributes<HTMLAnchorElement>) {
  const cls = `${base} ${variants[variant]} ${sizes[size]} ${className}`;
  const isExternal = href.startsWith("http") || href.startsWith("mailto:") || href.startsWith("tel:");
  if (isExternal) {
    return (
      <a href={href} className={cls} {...rest}>
        {children}
      </a>
    );
  }
  return (
    <Link href={href} className={cls} {...rest}>
      {children}
    </Link>
  );
}
