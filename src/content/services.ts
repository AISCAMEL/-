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
  icon:
    | "car"
    | "tag"
    | "key"
    | "shield"
    | "truck"
    | "store"
    | "app"
    | "code"
    | "gps"
    | "spark";
  /** カードで見せる主要メニュー */
  highlights: string[];
  /** こんな企業/事業者向け */
  audience: string[];
  /** 提供メニューの詳細（サービスページ用） */
  offerings: { title: string; description: string }[];
  /** 公式ブランドサイト等の外部URL（あれば） */
  externalUrl?: string;
  /** リリース準備中の事業 */
  comingSoon?: boolean;
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
    description:
      "販売・買取・オンライン納車・車両セキュリティ・レッカーまで、クルマに関わるすべてを。",
    isPrimary: true,
  },
  {
    id: "it",
    label: "IT・WEB事業",
    description:
      "ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、AI電話応対「AIオペレーター24」（準備中）。",
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
    tagline: "国産車の販売・全国対応",
    summary:
      "「カーメル」は、国産車を中心に、ご予算・ご用途に合わせた一台をご提案する自動車販売サービスです。全国対応で、お住まいの地域を問わずご利用いただけます。",
    icon: "car",
    highlights: [
      "国産車を幅広くご提案",
      "全国対応でお届け",
      "乗り換え・下取りのご相談",
    ],
    audience: [
      "クルマの購入・乗り換えを検討している個人・法人",
      "予算や用途に合う一台を相談しながら選びたい方",
      "社用車の導入を検討している法人・個人事業主",
    ],
    offerings: [
      {
        title: "国産車のご提案・販売",
        description:
          "ご予算・ご用途をうかがい、国産車を中心に最適な一台をご提案。全国どこからでもご相談いただけます。",
      },
      {
        title: "乗り換え・下取りのご相談",
        description:
          "今お乗りのクルマの下取りや買取（BUYMO）と合わせて、スムーズな乗り換えをサポートします。",
      },
      {
        title: "お支払いプランのご案内",
        description:
          "現金・ローンなど、ご希望に合わせたお支払い方法をご案内します。オンライン販売（CARSHICO）との組み合わせも可能です。",
      },
    ],
    externalUrl: "https://carmelonline.jp/",
    seo: {
      title: "自動車販売「カーメル」｜国産車・全国対応｜合同会社アイズ",
      description:
        "自動車販売ブランド「カーメル」。国産車を中心に、全国対応でご提案。乗り換え・下取りのご相談まで、ご予算・ご用途に合わせた一台をご提案します。",
    },
  },
  {
    slug: "buymo",
    name: "自動車買取",
    brand: "BUYMO",
    group: "mobility",
    tagline: "クルマから農機具・アルミまで全国買取",
    summary:
      "「BUYMO（バイモ）」は、国産・輸入車はもちろん、トラック・旧車から農機具・アルミまで、幅広く買取する全国対応の買取サービスです。",
    icon: "tag",
    highlights: [
      "国産・輸入車・トラック・旧車",
      "農機具・アルミなども買取",
      "全国対応・無料査定",
    ],
    audience: [
      "クルマ（国産・輸入）をできるだけ高く売りたい方",
      "トラック・旧車・農機具などの売却先を探している方",
      "アルミなどの金属を買取してほしい事業者",
    ],
    offerings: [
      {
        title: "幅広い買取対象",
        description:
          "国産・輸入車はもちろん、トラックや旧車、農機具、アルミなど、幅広い品目を買取の対象とします。まずはご相談ください。",
      },
      {
        title: "無料査定・適正買取",
        description:
          "市場相場や状態をふまえ、適正な査定額をご提示。全国対応で、お住まいの地域を問わず査定をご依頼いただけます。",
      },
      {
        title: "手続きサポート",
        description:
          "名義変更など売却に必要な手続きをサポートし、スムーズなお取引を実現します。",
      },
    ],
    seo: {
      title: "自動車買取「BUYMO」｜車・トラック・農機具・アルミ買取｜合同会社アイズ",
      description:
        "買取ブランド「BUYMO（バイモ）」。国産・輸入車、トラック・旧車から農機具・アルミまで、全国対応で買取。無料査定から手続きサポートまで承ります。",
    },
  },
  {
    slug: "carshico",
    name: "オンライン車販売",
    brand: "CARSHICO",
    group: "mobility",
    tagline: "新車をオンラインで注文、自宅にお届け",
    summary:
      "「CARSHICO（カーシコ）」は、国産・輸入車の新車を、オンラインで注文してご自宅で受け取れる、新しいクルマの買い方です。来店せずに手続きが完結します。",
    icon: "key",
    highlights: [
      "新車をオンラインで注文",
      "ご自宅まで納車",
      "国産・輸入車に対応",
    ],
    audience: [
      "来店せずにクルマを購入したい方",
      "自宅にいながら新車を受け取りたい方",
      "国産・輸入の新車を検討している個人・法人",
    ],
    offerings: [
      {
        title: "オンラインで注文",
        description:
          "スマホ・PCから新車をオンラインで注文。来店不要で、手続きまでオンラインで完結します。",
      },
      {
        title: "ご自宅まで納車",
        description:
          "ご注文いただいた新車を、ご自宅までお届け。納車のために店舗へ足を運ぶ必要はありません。",
      },
      {
        title: "国産・輸入車に対応",
        description:
          "国産車から輸入車まで、幅広い新車をお選びいただけます。ご希望の車種をお気軽にご相談ください。",
      },
    ],
    seo: {
      title: "オンライン車販売「CARSHICO」｜新車をオンラインで注文・自宅納車｜合同会社アイズ",
      description:
        "オンライン車販売ブランド「CARSHICO（カーシコ）」。国産・輸入の新車をオンラインで注文し、ご自宅にお届け。来店せずに購入手続きが完結します。",
    },
  },
  {
    slug: "tengo",
    name: "車両セキュリティ",
    brand: "天護 TENGO",
    group: "mobility",
    tagline: "GPS遠隔停止システムで愛車を守る",
    summary:
      "「天護（TENGO）」は、GPSによる位置把握と遠隔エンジン停止で、大切なクルマを盗難・不正利用から守る、個人・法人向けの車両セキュリティシステムです。",
    icon: "gps",
    highlights: [
      "GPSで車両位置を把握",
      "遠隔でエンジンを停止",
      "個人・法人どちらも対応",
    ],
    audience: [
      "クルマの盗難・不正利用が心配な方",
      "高価格帯・人気車種にお乗りの方",
      "複数の車両を管理・保全したい法人",
    ],
    offerings: [
      {
        title: "GPSによる位置把握",
        description:
          "GPSで車両の位置を把握。万一の盗難時にも、位置情報の確認に役立ちます。",
      },
      {
        title: "遠隔停止システム",
        description:
          "万一の盗難・不正利用時に、遠隔操作でエンジンを停止。被害の拡大を防ぎ、車両を保全します。",
      },
      {
        title: "個人・法人の車両管理",
        description:
          "個人の愛車から法人の複数車両まで対応。位置・稼働の把握と保全で、車両管理を支援します。",
      },
    ],
    seo: {
      title: "車両セキュリティ「天護 TENGO」｜GPS遠隔停止システム｜合同会社アイズ",
      description:
        "車両セキュリティブランド「天護（TENGO）」。GPSによる位置把握と遠隔エンジン停止で大切なクルマを守ります。個人・法人専用の車両保全システムです。",
    },
  },
  {
    slug: "towing",
    name: "レッカー事業",
    group: "mobility",
    tagline: "福島県内のレッカー・カーレスキュー",
    summary:
      "福島県内のクルマのトラブルに、レッカー手配やカーレスキューで対応します。もしものときも、アイズにお任せください。",
    icon: "truck",
    highlights: [
      "福島県内に対応",
      "レッカー移動の手配",
      "バッテリー上がり・パンク等",
    ],
    audience: [
      "福島県内で出先のクルマのトラブルに備えたい方",
      "すぐに駆けつけてほしい方",
      "事故・故障時の搬送が必要な方",
    ],
    offerings: [
      {
        title: "レッカー移動",
        description:
          "故障や事故で動かなくなったクルマを、レッカーで安全に移動・搬送します。対応エリアは福島県内です。",
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
      title: "レッカー事業・カーレスキュー｜福島県内対応｜合同会社アイズ",
      description:
        "福島県内対応のレッカー手配・カーレスキュー。バッテリー上がり・パンク・キー閉じ込み・事故時の搬送など、出先のクルマのトラブルに対応します。",
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
          "買取ブランド「BUYMO」の加盟店として、車・トラック・農機具・アルミなどの買取事業に参入いただけます。",
      },
      {
        title: "開業・運営サポート",
        description:
          "開業準備から運営まで、本部がノウハウを提供しサポートします。詳細はお問い合わせ・専用サイトをご覧ください。",
      },
    ],
    externalUrl: "https://buysellfc.carmelonline.jp/",
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
    externalUrl: "https://site.aiscompany.jp/",
    seo: {
      title: "ノーコードアプリ開発「APPREX」｜IT事業｜合同会社アイズ",
      description:
        "ノーコードアプリ開発ブランド「APPREX（アップレックス）」。スピーディかつ低コストで、アイデアをアプリへ。企画から開発・運用までサポートします。",
    },
  },
  {
    slug: "webcrews",
    name: "WEB制作",
    brand: "WEB crews",
    group: "it",
    tagline: "サブスク型のWeb制作・運用",
    summary:
      "「WEB crews（ウェブクルーズ）」は、初期費用を抑え、月額サブスクでWebサイトを制作・運用できるサービスです。スモールスタートで、公開後の更新まで任せられます。",
    icon: "code",
    highlights: [
      "サブスクで初期費用を抑制",
      "制作から運用・更新まで",
      "月額でずっとサポート",
    ],
    audience: [
      "初期費用を抑えてWebサイトを持ちたい事業者",
      "制作だけでなく公開後の更新も任せたい企業",
      "Webサイトをリニューアルしたい企業",
    ],
    offerings: [
      {
        title: "サブスク型Web制作",
        description:
          "初期費用を抑え、月額サブスクでWebサイトを制作。まとまった資金をかけずにスモールスタートできます。",
      },
      {
        title: "運用・更新サポート",
        description:
          "公開後の更新・運用も月額に含めて対応。「変更したい」「直したい」にも継続的にお応えします。",
      },
      {
        title: "デザイン・改善",
        description:
          "見やすく伝わるデザインで制作し、公開後も成果につながるよう継続的に改善していきます。",
      },
    ],
    seo: {
      title: "サブスクWeb制作「WEB crews」｜月額でサイト制作・運用｜合同会社アイズ",
      description:
        "Web制作ブランド「WEB crews（ウェブクルーズ）」。初期費用を抑え、月額サブスクでWebサイトを制作・運用。公開後の更新・改善まで継続サポートします。",
    },
  },
  {
    slug: "ai-operator-24",
    name: "AIオペレーター24",
    brand: "AIオペレーター24",
    group: "it",
    tagline: "24時間対応のAI電話応対（準備中）",
    summary:
      "「AIオペレーター24」は、電話や問い合わせへの一次対応を、AIが24時間体制で自動化するサービスです。現在リリースに向けて準備を進めています。",
    icon: "spark",
    comingSoon: true,
    highlights: [
      "AIが24時間自動で応対",
      "電話・問い合わせ対応を自動化",
      "現在リリース準備中",
    ],
    audience: [
      "電話・問い合わせ対応の負担を減らしたい事業者",
      "営業時間外の問い合わせを取りこぼしたくない方",
      "人手不足を自動化で補いたい企業",
    ],
    offerings: [
      {
        title: "24時間の自動応対",
        description:
          "AIが電話・問い合わせに24時間対応。営業時間外や繁忙時の取りこぼしを防ぎます。",
      },
      {
        title: "一次対応の自動化",
        description:
          "よくある質問や受付などの一次対応を自動化し、スタッフの負担を軽減します。",
      },
      {
        title: "導入のご相談",
        description:
          "現在リリースに向けて準備中です。導入をご検討の方は、お気軽にお問い合わせください。",
      },
    ],
    seo: {
      title: "AIオペレーター24｜24時間対応のAI電話応対（準備中）｜合同会社アイズ",
      description:
        "「AIオペレーター24」は、電話・問い合わせの一次対応をAIが24時間自動化するサービス。現在リリース準備中。導入のご相談を受け付けています。",
    },
  },
];

export function getService(slug: string): ServiceDetail | undefined {
  return services.find((s) => s.slug === slug);
}

export function getServicesByGroup(group: ServiceGroupId): ServiceDetail[] {
  return services.filter((s) => s.group === group);
}
