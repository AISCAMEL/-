// ============================================================
// 全国サーフポイント・ガイド
// ------------------------------------------------------------
// 一般に公開・周知されている国内の代表的なサーフポイントの「概要」情報。
// ライブの波情報（/waves）は現在 岩沢海岸のみ対応。ここは全国を俯瞰する
// リファレンスとして、エリア別に見やすく並べる。
// ※ 概要は一般公開情報にもとづくガイド。実際のコンディション・アクセス・
//   ローカルルールは現地／専門サイトで各自ご確認ください。
// ============================================================

export type SpotRegion =
  | "tohoku"
  | "kanto"
  | "tokai"
  | "kansai_shikoku"
  | "kyushu"
  | "nihonkai";

export const SPOT_REGIONS: { key: SpotRegion; label: string }[] = [
  { key: "tohoku", label: "北海道・東北" },
  { key: "kanto", label: "関東" },
  { key: "tokai", label: "東海" },
  { key: "kansai_shikoku", label: "関西・四国" },
  { key: "kyushu", label: "九州・南" },
  { key: "nihonkai", label: "日本海" },
];

export const SPOT_REGION_LABEL: Record<SpotRegion, string> = Object.fromEntries(
  SPOT_REGIONS.map((r) => [r.key, r.label]),
) as Record<SpotRegion, string>;

export function isSpotRegion(v: string): v is SpotRegion {
  return SPOT_REGIONS.some((r) => r.key === v);
}

export type SpotType = "beach" | "reef" | "point" | "rivermouth";
export const SPOT_TYPE_LABEL: Record<SpotType, string> = {
  beach: "ビーチ",
  reef: "リーフ",
  point: "ポイント",
  rivermouth: "河口",
};

export type SpotLevel = "beginner" | "intermediate" | "advanced";
export const SPOT_LEVEL_LABEL: Record<SpotLevel, string> = {
  beginner: "初心者〜",
  intermediate: "中級者〜",
  advanced: "上級者向け",
};

export type SurfSpot = {
  id: string;
  name: string;
  reading?: string;
  prefecture: string;
  region: SpotRegion;
  type: SpotType;
  level: SpotLevel;
  bestSeason: string;
  summary: string;
  /** 当プラットフォームの拠点ポイント（岩沢海岸）を強調表示するフラグ */
  home?: boolean;
};

// 一般に公開・周知されている代表的な国内ポイントの概要。
export const SURF_SPOTS: SurfSpot[] = [
  // 北海道・東北
  {
    id: "iwasawa",
    name: "岩沢海岸",
    reading: "いわさわかいがん",
    prefecture: "福島県",
    region: "tohoku",
    type: "beach",
    level: "beginner",
    bestSeason: "春〜秋",
    summary: "当プラットフォームの拠点。メローな日が多く、海デビューに向くビーチ。ライブ波情報に対応。",
    home: true,
  },
  {
    id: "sendai-shinko",
    name: "仙台新港",
    reading: "せんだいしんこう",
    prefecture: "宮城県",
    region: "tohoku",
    type: "beach",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "東北を代表するビーチブレイク。パワーのある波が立ちやすく、腕自慢が集まる人気エリア。",
  },
  {
    id: "kujiragahama",
    name: "鯨波",
    reading: "くじらなみ",
    prefecture: "新潟県",
    region: "nihonkai",
    type: "beach",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "日本海側の老舗ポイント。冬の北西うねりでサイズアップする。",
  },
  // 関東
  {
    id: "kugenuma",
    name: "鵠沼海岸",
    reading: "くげぬまかいがん",
    prefecture: "神奈川県",
    region: "kanto",
    type: "beach",
    level: "beginner",
    bestSeason: "通年",
    summary: "湘南を代表する首都圏屈指の人気ビーチ。アクセス良好で初心者・スクールも多い。",
  },
  {
    id: "shidashita",
    name: "志田下（釣ヶ崎海岸）",
    reading: "しだした",
    prefecture: "千葉県",
    region: "kanto",
    type: "beach",
    level: "advanced",
    bestSeason: "秋〜春",
    summary: "東京2020オリンピック サーフィン会場。日本を代表するハイパフォーマンス・ビーチブレイク。",
  },
  {
    id: "ichinomiya",
    name: "一宮",
    reading: "いちのみや",
    prefecture: "千葉県",
    region: "kanto",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "千葉・外房の中心エリア。良質な波でコンテストも多数開催される定番ポイント。",
  },
  {
    id: "oarai",
    name: "大洗",
    reading: "おおあらい",
    prefecture: "茨城県",
    region: "kanto",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "茨城の人気ビーチ。北関東からアクセスしやすく、幅広いレベルが楽しめる。",
  },
  // 東海
  {
    id: "omaezaki",
    name: "御前崎",
    reading: "おまえざき",
    prefecture: "静岡県",
    region: "tokai",
    type: "point",
    level: "intermediate",
    bestSeason: "通年",
    summary: "岬地形で多方向のうねりを拾う。複数のポイントがあり風・うねりで選べる。",
  },
  {
    id: "irako",
    name: "伊良湖",
    reading: "いらご",
    prefecture: "愛知県",
    region: "tokai",
    type: "point",
    level: "beginner",
    bestSeason: "通年",
    summary: "ロングボードの聖地として知られる長い乗り味のポイント。伊良湖岬周辺。",
  },
  // 関西・四国
  {
    id: "isonoura",
    name: "磯ノ浦",
    reading: "いそのうら",
    prefecture: "和歌山県",
    region: "kansai_shikoku",
    type: "beach",
    level: "beginner",
    bestSeason: "通年",
    summary: "関西を代表する人気ビーチ。電車でアクセスでき、初心者や学生に親しまれる。",
  },
  {
    id: "nuomi",
    name: "生見",
    reading: "いくみ",
    prefecture: "高知県",
    region: "kansai_shikoku",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "四国・東洋町の有名ポイント。温暖な気候で年間を通じて波を楽しめる。",
  },
  // 九州・南
  {
    id: "okuragahama",
    name: "お倉ヶ浜",
    reading: "おくらがはま",
    prefecture: "宮崎県",
    region: "kyushu",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "宮崎・日向の代表的なビーチブレイク。良質な波質で全国から人が集まる。",
  },
  {
    id: "kizakihama",
    name: "木崎浜",
    reading: "きさきはま",
    prefecture: "宮崎県",
    region: "kyushu",
    type: "beach",
    level: "advanced",
    bestSeason: "通年",
    summary: "国内トップクラスのコンテストが行われるビーチ。パワフルで質の高い波で知られる。",
  },
];
