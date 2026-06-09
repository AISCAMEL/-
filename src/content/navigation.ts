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
        label: "自動車事業",
        href: "/services/automotive",
        description: "販売・買取・リース",
      },
      {
        label: "アプリ事業",
        href: "/services/app",
        description: "自社アプリ・Web・システム開発",
      },
      {
        label: "GPS事業",
        href: "/services/gps",
        description: "GPSを活用したサービス",
      },
      {
        label: "FC事業",
        href: "/services/fc",
        description: "フランチャイズ展開",
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
    title: "事業・サービス",
    items: [
      { label: "自動車事業", href: "/services/automotive" },
      { label: "アプリ事業", href: "/services/app" },
      { label: "GPS事業", href: "/services/gps" },
      { label: "FC事業", href: "/services/fc" },
      { label: "事業一覧", href: "/services" },
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
