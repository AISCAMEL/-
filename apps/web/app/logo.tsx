/** necorope の猫ブランドマーク（SVG・テーマカラー追従）。 */
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
      <path d="M14 20 L20 5 L31 19 Z" fill="#e8612e" />
      <path d="M50 20 L44 5 L33 19 Z" fill="#e8612e" />
      <path d="M17 18 L21 9 L27 18 Z" fill="#fbd0bd" />
      <path d="M47 18 L43 9 L37 18 Z" fill="#fbd0bd" />
      {/* 顔 */}
      <circle cx="32" cy="37" r="21" fill="#e8612e" />
      {/* 目 */}
      <circle cx="24" cy="35" r="3" fill="#fff" />
      <circle cx="40" cy="35" r="3" fill="#fff" />
      <circle cx="24.6" cy="35.6" r="1.4" fill="#3a2e29" />
      <circle cx="40.6" cy="35.6" r="1.4" fill="#3a2e29" />
      {/* 鼻 */}
      <path d="M29.5 41 L34.5 41 L32 44.2 Z" fill="#fff" />
      {/* ひげ */}
      <g stroke="#fff" strokeWidth="1.4" strokeLinecap="round">
        <line x1="14" y1="39" x2="22" y2="40" />
        <line x1="14" y1="43" x2="22" y2="43" />
        <line x1="50" y1="39" x2="42" y2="40" />
        <line x1="50" y1="43" x2="42" y2="43" />
      </g>
    </svg>
  );
}
