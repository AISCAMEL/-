export type NavItem = {
  label: string;
  href: string;
  children?: { label: string; href: string; description?: string }[];
};

export const mainNav: NavItem[] = [
  {
    label: "サービス",
    href: "/services",
    children: [
      {
        label: "自動車業界支援",
        href: "/services/automotive",
        description: "販売戦略・新規参入・DX推進",
      },
      {
        label: "創業・起業支援",
        href: "/services/startup",
        description: "資金調達・補助金・開業/運営支援",
      },
      {
        label: "Web・開発支援",
        href: "/services/web-development",
        description: "Web制作・マーケ・システム/アプリ開発",
      },
    ],
  },
  { label: "アイズについて", href: "/about" },
  { label: "理念", href: "/philosophy" },
  { label: "実績", href: "/works" },
  { label: "お知らせ", href: "/news" },
  { label: "よくある質問", href: "/faq" },
];

export const footerNav: { title: string; items: { label: string; href: string }[] }[] = [
  {
    title: "サービス",
    items: [
      { label: "自動車業界支援", href: "/services/automotive" },
      { label: "創業・起業支援", href: "/services/startup" },
      { label: "Web・開発支援", href: "/services/web-development" },
      { label: "サービス一覧", href: "/services" },
    ],
  },
  {
    title: "会社情報",
    items: [
      { label: "アイズについて", href: "/about" },
      { label: "理念", href: "/philosophy" },
      { label: "実績", href: "/works" },
      { label: "お知らせ", href: "/news" },
    ],
  },
  {
    title: "サポート",
    items: [
      { label: "よくある質問", href: "/faq" },
      { label: "お問い合わせ", href: "/contact" },
      { label: "プライバシーポリシー", href: "/privacy" },
    ],
  },
];
