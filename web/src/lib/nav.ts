// サイト共通のナビゲーション（ヘッダー・スマホのメニューで共用）

export type NavLink = { href: string; label: string; icon: string };

/** ヘッダー（PC）に並べる主要ページ */
export const MAIN_NAV: NavLink[] = [
  { href: "/waves", label: "波情報", icon: "🌊" },
  { href: "/spots", label: "全国ポイント", icon: "🗾" },
  { href: "/instructors", label: "スクール", icon: "🏄" },
  { href: "/courses", label: "講座", icon: "🎓" },
  { href: "/rules", label: "海のルール", icon: "🛟" },
  { href: "/partners", label: "周辺ガイド", icon: "🚗" },
  { href: "/community", label: "コミュニティ", icon: "💬" },
];

/** スマホ下部のタブ（よく使う4つ＋メニュー） */
export const TAB_NAV: NavLink[] = [
  { href: "/", label: "ホーム", icon: "🏠" },
  { href: "/waves", label: "波情報", icon: "🌊" },
  { href: "/spots", label: "ポイント", icon: "🗾" },
  { href: "/instructors", label: "スクール", icon: "🏄" },
];

/** スマホの「メニュー」に出す全ページ */
export const MENU_NAV: NavLink[] = [
  { href: "/start", label: "はじめての方へ", icon: "🔰" },
  ...MAIN_NAV,
  { href: "/articles", label: "プロのブログ", icon: "📝" },
  { href: "/skills", label: "スキル掲示板", icon: "🤝" },
  { href: "/faq", label: "よくある質問", icon: "❓" },
  { href: "/about", label: "私たちについて", icon: "🏝" },
  { href: "/contact", label: "お問い合わせ", icon: "✉️" },
  { href: "/ads", label: "広告掲載", icon: "📣" },
];
