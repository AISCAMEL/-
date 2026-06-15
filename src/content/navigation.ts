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
      { label: "自動車販売（カーメル）", href: "/services/carmel", description: "国産車の販売・全国対応" },
      { label: "自動車買取（BUYMO）", href: "/services/buymo", description: "車・農機具・アルミ等を全国買取" },
      { label: "オンライン車販売（CARSHICO）", href: "/services/carshico", description: "新車をオンライン注文・自宅納車" },
      { label: "車両セキュリティ（天護 TENGO）", href: "/services/tengo", description: "GPS遠隔停止システム" },
      { label: "レッカー事業", href: "/services/towing", description: "福島県内のレッカー・カーレスキュー" },
      { label: "FC事業（カーメル／BUYMO）", href: "/services/fc", description: "フランチャイズ加盟募集" },
      { label: "IT事業（APPREX）", href: "/services/apprex", description: "ノーコードアプリ開発" },
      { label: "WEB制作（WEB crews）", href: "/services/webcrews", description: "サブスク型のWeb制作・運用" },
      { label: "AIオペレーター24", href: "/services/ai-operator-24", description: "24時間対応のAI電話応対（準備中）" },
    ],
  },
  { label: "ブランド一覧", href: "/brands" },
  {
    label: "アイズについて",
    href: "/about",
    children: [
      { label: "会社概要", href: "/about", description: "会社情報・価値観" },
      { label: "代表メッセージ", href: "/message", description: "代表からのごあいさつ" },
      { label: "理念", href: "/philosophy", description: "Always Innovation Solutions" },
    ],
  },
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
      { label: "オンライン車販売（CARSHICO）", href: "/services/carshico" },
      { label: "車両セキュリティ（天護）", href: "/services/tengo" },
      { label: "レッカー事業", href: "/services/towing" },
    ],
  },
  {
    title: "IT・WEB・FC",
    items: [
      { label: "IT事業（APPREX）", href: "/services/apprex" },
      { label: "WEB制作（WEB crews）", href: "/services/webcrews" },
      { label: "AIオペレーター24", href: "/services/ai-operator-24" },
      { label: "FC事業", href: "/services/fc" },
      { label: "ブランド一覧", href: "/brands" },
    ],
  },
  {
    title: "会社情報・サポート",
    items: [
      { label: "アイズについて", href: "/about" },
      { label: "代表メッセージ", href: "/message" },
      { label: "理念", href: "/philosophy" },
      { label: "実績", href: "/works" },
      { label: "お知らせ", href: "/news" },
      { label: "よくある質問", href: "/faq" },
      { label: "お問い合わせ", href: "/contact" },
      { label: "プライバシーポリシー", href: "/privacy" },
    ],
  },
];
