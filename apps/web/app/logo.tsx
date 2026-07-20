export default function Logo({ size = 38 }: { size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      role="img"
      aria-label="necorope ロゴ"
      xmlns="http://www.w3.org/2000/svg"
    >
      {/* 耳 */}
      <path d="M12 22 L19 4 L30 20 Z" fill="#f472b6" />
      <path d="M52 22 L45 4 L34 20 Z" fill="#f472b6" />
      <path d="M16 19 L20 9 L26 19 Z" fill="#fce7f3" />
      <path d="M48 19 L44 9 L38 19 Z" fill="#fce7f3" />
      {/* 顔 */}
      <circle cx="32" cy="38" r="22" fill="#f472b6" />
      {/* ほっぺ */}
      <circle cx="18" cy="42" r="5" fill="#fda4af" opacity="0.5" />
      <circle cx="46" cy="42" r="5" fill="#fda4af" opacity="0.5" />
      {/* 目 */}
      <ellipse cx="24" cy="35" rx="3.5" ry="4" fill="#fff" />
      <ellipse cx="40" cy="35" rx="3.5" ry="4" fill="#fff" />
      <circle cx="24.8" cy="34.5" r="2" fill="#4a3728" />
      <circle cx="40.8" cy="34.5" r="2" fill="#4a3728" />
      <circle cx="25.5" cy="33.5" r="0.8" fill="#fff" />
      <circle cx="41.5" cy="33.5" r="0.8" fill="#fff" />
      {/* 鼻 */}
      <ellipse cx="32" cy="41" rx="2.5" ry="1.8" fill="#fce7f3" />
      {/* 口 */}
      <path d="M32 42.5 Q29 46 27 44" fill="none" stroke="#fce7f3" strokeWidth="1.2" strokeLinecap="round" />
      <path d="M32 42.5 Q35 46 37 44" fill="none" stroke="#fce7f3" strokeWidth="1.2" strokeLinecap="round" />
      {/* ひげ */}
      <g stroke="#fce7f3" strokeWidth="1.2" strokeLinecap="round">
        <line x1="12" y1="38" x2="21" y2="40" />
        <line x1="11" y1="43" x2="21" y2="43" />
        <line x1="52" y1="38" x2="43" y2="40" />
        <line x1="53" y1="43" x2="43" y2="43" />
      </g>
    </svg>
  );
}
