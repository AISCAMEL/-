import { SVGProps } from "react";

export type IconName =
  | "car"
  | "app"
  | "gps"
  | "store"
  | "rocket"
  | "code"
  | "arrow-right"
  | "check"
  | "chevron-down"
  | "menu"
  | "close"
  | "mail"
  | "phone"
  | "spark";

const paths: Record<IconName, JSX.Element> = {
  car: (
    <>
      <path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11" />
      <path d="M3 11h18v5a1 1 0 0 1-1 1h-1a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H4a1 1 0 0 1-1-1v-5z" />
      <path d="M7 14h.01M17 14h.01" />
    </>
  ),
  app: (
    <>
      <rect x="7" y="3" width="10" height="18" rx="2.5" />
      <path d="M11 18h2" />
    </>
  ),
  gps: (
    <>
      <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z" />
      <circle cx="12" cy="10" r="2.5" />
    </>
  ),
  store: (
    <>
      <path d="M3.5 9l1.4-4.2A1 1 0 0 1 5.85 4h12.3a1 1 0 0 1 .95.8L20.5 9" />
      <path d="M5 9v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9" />
      <path d="M9.5 19v-4.5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V19" />
    </>
  ),
  rocket: (
    <>
      <path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2" />
      <path d="M9 13c-1.6.8-3 2.4-3 2.4S7.6 17 8.4 15.4" />
      <path d="M14.5 4.5C18 3 21 6 19.5 9.5L14 15l-5-5 5.5-5.5z" />
      <path d="M9 10l-4 1 2 2 1-3z" opacity="0" />
      <circle cx="15" cy="9" r="1.3" />
    </>
  ),
  code: (
    <>
      <path d="M8 8l-4 4 4 4" />
      <path d="M16 8l4 4-4 4" />
      <path d="M13 6l-2 12" />
    </>
  ),
  "arrow-right": (
    <>
      <path d="M5 12h14" />
      <path d="M13 6l6 6-6 6" />
    </>
  ),
  check: <path d="M5 12l4 4 10-10" />,
  "chevron-down": <path d="M6 9l6 6 6-6" />,
  menu: (
    <>
      <path d="M4 7h16" />
      <path d="M4 12h16" />
      <path d="M4 17h16" />
    </>
  ),
  close: (
    <>
      <path d="M6 6l12 12" />
      <path d="M18 6L6 18" />
    </>
  ),
  mail: (
    <>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 7l9 6 9-6" />
    </>
  ),
  phone: (
    <path d="M5 4h3l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z" />
  ),
  spark: (
    <path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5z" />
  ),
};

export function Icon({
  name,
  ...props
}: { name: IconName } & SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      {...props}
    >
      {paths[name]}
    </svg>
  );
}
