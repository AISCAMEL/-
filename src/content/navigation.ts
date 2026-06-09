export type NavItem = {
  label: string;
  href: string;
  children?: { label: string; href: string; description?: string }[];
};

export const mainNav: NavItem[] = [
  {
    label: "事業紹介",
    href: "/services",
    children: [
      { label: "自動車販売（カーメル）", href: "/services/carmel", description: "新車・中古車の販売" },
      { label: "自動車買取（BUYMO）", href: "/services/buymo", description: "愛車の買取・査定" },
      { label: "カーリース（CARSHICO）", href: "/services/carshico", description: "月々定額のカーリース" },
      { label: "車両セキュリティ（天護 TENGO）", href: "/services/tengo", description: "GPS・盗難対策" },
      { label: "レッカー事業", href: "/services/towing", description: "レッカー・カーレスキュー" },
      { label: "FC事業（カーメル／BUYMO）", href: "/services/fc", description: "フランチャイズ加盟募集" },
      { label: "IT事業（APPREX）", href: "/services/apprex", description: "ノーコードアプリ開発" },
      { label: "WEB開発（WEB crews）", href: "/services/webcrews", description: "Web制作・システム開発" },
    ],
  },
  { label: "ブランド一覧", href: "/brands" },
  { label: "アイズについて", href: "/about" },
  { label: "実績", href: "/works" },
  { label: "お知らせ", href: "/news" },
  { label: "よくある質問", href: "/faq" },
];

export const footerNav: { title: string; items: { label: string; href: string }[] }[] = [
  {
    title: "自動車事業",
    items: [
      { label: "自動車販売（カーメル）", href: "/services/carmel" },
      { label: "自動車買取（BUYMO）", href: "/services/buymo" },
      { label: "カーリース（CARSHICO）", href: "/services/carshico" },
      { label: "車両セキュリティ（天護）", href: "/services/tengo" },
      { label: "レッカー事業", href: "/services/towing" },
    ],
  },
  {
    title: "IT・WEB・FC",
    items: [
      { label: "IT事業（APPREX）", href: "/services/apprex" },
      { label: "WEB開発（WEB crews）", href: "/services/webcrews" },
      { label: "FC事業", href: "/services/fc" },
      { label: "ブランド一覧", href: "/brands" },
    ],
  },
  {
    title: "会社情報・サポート",
    items: [
      { label: "アイズについて", href: "/about" },
      { label: "理念", href: "/philosophy" },
      { label: "実績", href: "/works" },
      { label: "お知らせ", href: "/news" },
      { label: "よくある質問", href: "/faq" },
      { label: "お問い合わせ", href: "/contact" },
      { label: "プライバシーポリシー", href: "/privacy" },
    ],
  },
];
