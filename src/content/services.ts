export type ServiceGroupId = "mobility" | "business" | "it";

export type ServiceDetail = {
  /** URLスラッグ（/services/[slug]） */
  slug: string;
  /** 事業名（ナビ・カード見出し） */
  name: string;
  /** ブランド名（あれば） */
  brand?: string;
  /** 事業グループ */
  group: ServiceGroupId;
  /** カードのサブコピー */
  tagline: string;
  /** トップページ等での要約 */
  summary: string;
  /** アイコン識別子（components/ui/Icon.tsx で描画） */
  icon: "car" | "tag" | "key" | "shield" | "truck" | "store" | "app" | "code";
  /** カードで見せる主要メニュー */
  highlights: string[];
  /** こんな企業/事業者向け */
  audience: string[];
  /** 提供メニューの詳細（サービスページ用） */
  offerings: { title: string; description: string }[];
  /** SEO */
  seo: { title: string; description: string };
};

/** 事業グループ（トップ・一覧での見せ方） */
export const serviceGroups: {
  id: ServiceGroupId;
  label: string;
  description: string;
  isPrimary?: boolean;
}[] = [
  {
    id: "mobility",
    label: "自動車事業",
    description: "販売・買取・リース・車両セキュリティ・レッカーまで、クルマに関わるすべてを。",
    isPrimary: true,
  },
  {
    id: "it",
    label: "IT・WEB事業",
    description: "ノーコードアプリ開発「APPREX」とWeb・システム開発「WEB crews」。",
  },
  {
    id: "business",
    label: "FC事業",
    description: "「カーメル」「BUYMO」のフランチャイズ加盟を募集しています。",
  },
];

export const services: ServiceDetail[] = [
  // ── 自動車事業（主力） ──
  {
    slug: "carmel",
    name: "自動車販売",
    brand: "カーメル",
    group: "mobility",
    tagline: "新車・中古車の販売",
    summary:
      "「カーメル」は、新車から良質な中古車まで、ご予算・ご用途に合わせた一台をご提案する自動車販売サービスです。",
    icon: "car",
    highlights: [
      "新車・中古車の販売",
      "幅広い車種からご提案",
      "乗り換え・下取りのご相談",
    ],
    audience: [
      "クルマの購入・乗り換えを検討している個人・法人",
      "予算や用途に合う一台を相談しながら選びたい方",
      "社用車の導入を検討している法人・個人事業主",
    ],
    offerings: [
      {
        title: "車両のご提案・販売",
        description:
          "ご予算・ご用途をうかがい、最適な一台をご提案。新車から良質な中古車まで、幅広い車種をお取り扱いします。",
      },
      {
        title: "乗り換え・下取りのご相談",
        description:
          "今お乗りのクルマの下取りや買取（BUYMO）と合わせて、スムーズな乗り換えをサポートします。",
      },
      {
        title: "お支払いプランのご案内",
        description:
          "現金・ローン・カーリース（CARSHICO）など、ご希望に合わせたお支払い方法をご案内します。",
      },
    ],
    seo: {
      title: "自動車販売「カーメル」｜新車・中古車｜合同会社アイズ",
      description:
        "自動車販売ブランド「カーメル」。新車・中古車の販売から乗り換え・下取りのご相談まで、ご予算・ご用途に合わせた一台をご提案します。",
    },
  },
  {
    slug: "buymo",
    name: "自動車買取",
    brand: "BUYMO",
    group: "mobility",
    tagline: "愛車の買取・査定",
    summary:
      "「BUYMO（バイモ）」は、市場相場をふまえた適正査定で、あなたの愛車を買取する車買取サービスです。",
    icon: "tag",
    highlights: [
      "無料査定・適正買取",
      "乗り換えと合わせた相談",
      "面倒な手続きもサポート",
    ],
    audience: [
      "今の愛車をできるだけ高く売りたい方",
      "乗り換えと合わせて買取を相談したい方",
      "売却の手続きをまかせたい方",
    ],
    offerings: [
      {
        title: "無料査定",
        description:
          "市場相場や車両の状態をふまえ、適正な査定額をご提示します。まずはお気軽に査定をご依頼ください。",
      },
      {
        title: "買取",
        description:
          "ご納得いただいたうえで買取いたします。自動車販売（カーメル）と合わせた乗り換えのご相談も可能です。",
      },
      {
        title: "手続きサポート",
        description:
          "名義変更など売却に必要な手続きをサポートし、スムーズなお取引を実現します。",
      },
    ],
    seo: {
      title: "自動車買取「BUYMO」｜愛車の査定・買取｜合同会社アイズ",
      description:
        "車買取ブランド「BUYMO（バイモ）」。適正査定で愛車を買取。無料査定から手続きサポートまで、乗り換えと合わせたご相談も承ります。",
    },
  },
  {
    slug: "carshico",
    name: "カーリース",
    brand: "CARSHICO",
    group: "mobility",
    tagline: "月々定額のカーリース",
    summary:
      "「CARSHICO（カーシコ）」は、まとまった初期費用をかけず、月々定額でクルマに乗れるカーリースサービスです。",
    icon: "key",
    highlights: [
      "初期費用を抑えて導入",
      "月々定額でわかりやすい",
      "個人・法人どちらも対応",
    ],
    audience: [
      "初期費用を抑えてクルマを導入したい方",
      "費用の見通しを立てやすくしたい法人・個人事業主",
      "社用車の導入を検討している法人",
    ],
    offerings: [
      {
        title: "個人向けカーリース",
        description:
          "まとまった初期費用をかけずに、月々定額でクルマをご利用いただけます。ライフスタイルに合わせたプランをご提案します。",
      },
      {
        title: "法人向けカーリース",
        description:
          "社用車を月々定額で導入。費用の見通しが立てやすく、経費管理の面でもメリットがあります。",
      },
      {
        title: "プランのご提案",
        description:
          "ご予算・ご利用期間・走行距離などをうかがい、最適なリースプランをご提案します。",
      },
    ],
    seo: {
      title: "カーリース「CARSHICO」｜月々定額でクルマに乗る｜合同会社アイズ",
      description:
        "カーリースブランド「CARSHICO（カーシコ）」。初期費用を抑えて、月々定額でクルマに乗れます。個人・法人どちらにも最適なプランをご提案します。",
    },
  },
  {
    slug: "tengo",
    name: "車両セキュリティ",
    brand: "天護 TENGO",
    group: "mobility",
    tagline: "GPS・盗難対策で愛車を守る",
    summary:
      "「天護（TENGO）」は、GPSや車両セキュリティで、大切なクルマを盗難・トラブルから守るサービスです。",
    icon: "shield",
    highlights: [
      "GPSによる位置の把握",
      "盗難対策・セキュリティ",
      "法人の車両管理にも",
    ],
    audience: [
      "クルマの盗難が心配な方",
      "高価格帯・人気車種にお乗りの方",
      "複数の車両を管理したい法人",
    ],
    offerings: [
      {
        title: "車両GPS",
        description:
          "GPSで車両の位置を把握。万一の盗難時にも、位置情報の確認に役立ちます。",
      },
      {
        title: "盗難対策・セキュリティ",
        description:
          "大切なクルマを盗難やいたずらから守るためのセキュリティ対策をご提案します。",
      },
      {
        title: "法人向け車両管理",
        description:
          "複数の社用車の位置・稼働状況を把握し、車両管理の効率化を支援します。",
      },
    ],
    seo: {
      title: "車両セキュリティ「天護 TENGO」｜GPS・盗難対策｜合同会社アイズ",
      description:
        "車両セキュリティブランド「天護（TENGO）」。GPSや盗難対策で大切なクルマを守ります。個人の愛車から法人の車両管理まで対応します。",
    },
  },
  {
    slug: "towing",
    name: "レッカー事業",
    group: "mobility",
    tagline: "もしもの時のレッカー・カーレスキュー",
    summary:
      "出先のクルマのトラブルに、レッカー手配やカーレスキューで対応します。もしものときも、アイズにお任せください。",
    icon: "truck",
    highlights: [
      "レッカー移動の手配",
      "バッテリー上がり・パンク等",
      "緊急時の駆けつけ対応",
    ],
    audience: [
      "出先のクルマのトラブルに備えたい方",
      "すぐに駆けつけてほしい方",
      "事故・故障時の搬送が必要な方",
    ],
    offerings: [
      {
        title: "レッカー移動",
        description:
          "故障や事故で動かなくなったクルマを、レッカーで安全に移動・搬送します。",
      },
      {
        title: "応急対応（カーレスキュー）",
        description:
          "バッテリー上がり・パンク・キー閉じ込みなど、出先のトラブルに応急対応します。",
      },
      {
        title: "事故・故障時の搬送",
        description:
          "万一の事故や故障の際にも、状況に応じて適切な搬送・対応をご案内します。",
      },
    ],
    seo: {
      title: "レッカー事業・カーレスキュー｜合同会社アイズ",
      description:
        "レッカー手配・カーレスキュー。バッテリー上がり・パンク・キー閉じ込み・事故時の搬送など、出先のクルマのトラブルに対応します。",
    },
  },
  // ── FC事業 ──
  {
    slug: "fc",
    name: "FC事業",
    brand: "カーメル／BUYMO",
    group: "business",
    tagline: "フランチャイズ加盟募集",
    summary:
      "自動車販売「カーメル」・買取「BUYMO」のフランチャイズ（FC）加盟を募集しています。自動車事業への参入・拡大をサポートします。",
    icon: "store",
    highlights: [
      "「カーメル」FC加盟",
      "「BUYMO」FC加盟",
      "開業・運営をサポート",
    ],
    audience: [
      "自動車販売・買取事業に新規参入したい方",
      "既存事業に車の販売・買取を加えたい事業者",
      "ブランドを活かして独立開業したい方",
    ],
    offerings: [
      {
        title: "「カーメル」フランチャイズ",
        description:
          "自動車販売ブランド「カーメル」の加盟店として、車販売事業を立ち上げ・運営いただけます。",
      },
      {
        title: "「BUYMO」フランチャイズ",
        description:
          "車買取ブランド「BUYMO」の加盟店として、買取事業に参入いただけます。",
      },
      {
        title: "開業・運営サポート",
        description:
          "開業準備から運営まで、本部がノウハウを提供しサポートします。詳細はお問い合わせください。",
      },
    ],
    seo: {
      title: "FC（フランチャイズ）事業｜カーメル・BUYMO加盟募集｜合同会社アイズ",
      description:
        "自動車販売「カーメル」・買取「BUYMO」のフランチャイズ加盟募集。自動車事業への新規参入・拡大を、開業から運営までサポートします。",
    },
  },
  // ── IT・WEB事業 ──
  {
    slug: "apprex",
    name: "IT事業",
    brand: "APPREX",
    group: "it",
    tagline: "ノーコードアプリ開発",
    summary:
      "「APPREX（アップレックス）」は、ノーコードでアプリを企画・開発・リリースするIT事業です。スピーディに低コストで、アイデアを形にします。",
    icon: "app",
    highlights: [
      "ノーコードでアプリ開発",
      "スピーディ・低コスト",
      "リリース後の運用・改善",
    ],
    audience: [
      "アプリを早く・低コストで立ち上げたい事業者",
      "アイデアはあるが開発手段に悩んでいる方",
      "公開後の運用・改善まで任せたい企業",
    ],
    offerings: [
      {
        title: "ノーコードアプリ開発",
        description:
          "ノーコードでアプリを開発・リリース。専門的な開発知識がなくても、スピーディかつ低コストでアプリを形にします。",
      },
      {
        title: "企画・要件整理",
        description:
          "「何を作るか」の整理から伴走。実現したいことをうかがい、アプリの形に落とし込みます。",
      },
      {
        title: "運用・改善",
        description:
          "リリース後の運用・改善まで継続的にサポートし、サービスの成長を支えます。",
      },
    ],
    seo: {
      title: "ノーコードアプリ開発「APPREX」｜IT事業｜合同会社アイズ",
      description:
        "ノーコードアプリ開発ブランド「APPREX（アップレックス）」。スピーディかつ低コストで、アイデアをアプリへ。企画から開発・運用までサポートします。",
    },
  },
  {
    slug: "webcrews",
    name: "WEB開発",
    brand: "WEB crews",
    group: "it",
    tagline: "Web制作・システム開発",
    summary:
      "「WEB crews（ウェブクルーズ）」は、Webサイト制作からシステム開発までを手がけるWeb開発事業です。",
    icon: "code",
    highlights: [
      "Webサイト制作・UI/UX",
      "システム開発",
      "公開後の運用・保守",
    ],
    audience: [
      "Webサイトを制作・リニューアルしたい企業",
      "業務システムの開発を相談したい企業",
      "制作後の運用・保守まで任せたい企業",
    ],
    offerings: [
      {
        title: "Webサイト制作",
        description:
          "戦略設計からデザイン、実装まで。見やすく伝わり、成果につながるWebサイトを制作します。",
      },
      {
        title: "システム開発",
        description:
          "要件定義・設計から開発、テスト・品質保証まで一貫して対応。業務に合わせたシステムを構築します。",
      },
      {
        title: "運用・保守",
        description:
          "公開後の運用・改善・保守まで継続的にサポートし、安定した稼働を支えます。",
      },
    ],
    seo: {
      title: "Web制作・システム開発「WEB crews」｜合同会社アイズ",
      description:
        "Web開発ブランド「WEB crews（ウェブクルーズ）」。Webサイト制作からシステム開発、運用・保守まで。成果につながるサイト・システムを構築します。",
    },
  },
];

export function getService(slug: string): ServiceDetail | undefined {
  return services.find((s) => s.slug === slug);
}

export function getServicesByGroup(group: ServiceGroupId): ServiceDetail[] {
  return services.filter((s) => s.group === group);
}
