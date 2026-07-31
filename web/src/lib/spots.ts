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
  /** 詳細ページ用の補足（一般公開情報にもとづく概要）。任意 */
  details?: string;
  /** 当プラットフォームの拠点ポイント（岩沢海岸）を強調表示するフラグ */
  home?: boolean;
};

// 一般に公開・周知されている代表的な国内ポイントの概要。
export const SURF_SPOTS: SurfSpot[] = [
  // ── 北海道・東北 ─────────────────────────────
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
    details:
      "福島県双葉郡広野町の岩沢海岸は、穏やかな日が多く、はじめての一本を安心して迎えやすいビーチブレイク。当プラットフォームではスクール・ギアレンタル・移動サポート・ローカルガイドを通じて、初めての方の海デビューを支えています。当サイトではこのポイントのライブ波情報を確認できます。",
    home: true,
  },
  {
    id: "hama-atsuma",
    name: "浜厚真",
    reading: "はまあつま",
    prefecture: "北海道",
    region: "tohoku",
    type: "beach",
    level: "intermediate",
    bestSeason: "夏〜秋",
    summary: "北海道・苫小牧近くを代表するビーチブレイク。太平洋のうねりを受ける道内の人気エリア。",
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
    id: "torinoumi",
    name: "鳥の海",
    reading: "とりのうみ",
    prefecture: "宮城県",
    region: "tohoku",
    type: "rivermouth",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "宮城・亘理の河口周辺ポイント。地形により波がまとまりやすい。",
  },
  {
    id: "harahama",
    name: "原釜",
    reading: "はらがま",
    prefecture: "福島県",
    region: "tohoku",
    type: "beach",
    level: "beginner",
    bestSeason: "春〜秋",
    summary: "福島・相馬の親しみやすいビーチ。ゆるい日は初心者の練習にも向く。",
  },
  // ── 関東 ─────────────────────────────────────
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
    details:
      "神奈川県藤沢市の鵠沼海岸は、湘南でも特に知名度の高いビーチブレイク。電車でのアクセスがよく、スクールやレンタルも充実しているため、はじめての人から上級者まで幅広く集まります。人気ゆえに混み合いやすいので、時間帯やマナーへの配慮が大切なエリアです。",
  },
  {
    id: "tsujido",
    name: "辻堂",
    reading: "つじどう",
    prefecture: "神奈川県",
    region: "kanto",
    type: "beach",
    level: "beginner",
    bestSeason: "通年",
    summary: "湘南・辻堂のビーチ。ファミリーや初心者にも親しまれる穏やかな日が多いエリア。",
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
    details:
      "千葉県一宮町の釣ヶ崎海岸（通称・志田下）は、東京2020オリンピックのサーフィン競技会場となった日本屈指のビーチブレイク。質の高い波が立つことで知られ、国内トップサーファーが集まります。パワーのある波が入るため、コンディションによっては上級者向けとなります。",
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
    id: "taito",
    name: "太東",
    reading: "たいとう",
    prefecture: "千葉県",
    region: "kanto",
    type: "point",
    level: "intermediate",
    bestSeason: "通年",
    summary: "外房・岬地形のポイント。うねりの反応がよく、幅広いコンディションで楽しめる。",
  },
  {
    id: "kamogawa",
    name: "鴨川（マルキ）",
    reading: "かもがわ",
    prefecture: "千葉県",
    region: "kanto",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "南房総・鴨川の有名ビーチ。マルキポイントとして広く知られる定番エリア。",
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
  {
    id: "hasaki",
    name: "波崎",
    reading: "はさき",
    prefecture: "茨城県",
    region: "kanto",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "茨城・神栖の広いビーチブレイク。うねりを受けやすく多くのサーファーが集まる。",
  },
  // ── 東海 ─────────────────────────────────────
  {
    id: "shizunami",
    name: "静波",
    reading: "しずなみ",
    prefecture: "静岡県",
    region: "tokai",
    type: "beach",
    level: "beginner",
    bestSeason: "通年",
    summary: "静岡・牧之原の親しみやすいビーチ。穏やかな日が多く初心者にも人気。",
  },
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
    details:
      "愛知県田原市の伊良湖は、長くゆったりと乗れる波質からロングボードの聖地として親しまれてきたポイント。渥美半島の先端・伊良湖岬周辺に位置し、コンディションが揃うと質の高い波が続きます。周辺には複数のポイントがあり、うねりと風で選び分けられます。",
  },
  {
    id: "akabane",
    name: "赤羽根",
    reading: "あかばね",
    prefecture: "愛知県",
    region: "tokai",
    type: "beach",
    level: "advanced",
    bestSeason: "通年",
    summary: "渥美半島・太平洋岸のビーチ。パワフルな波でコンテストも開催される実力派エリア。",
  },
  // ── 関西・四国 ───────────────────────────────
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
    details:
      "和歌山市の磯ノ浦は、関西でもっとも知られるビーチブレイクのひとつ。南海電車でアクセスできる利便性から、初心者や学生を含め多くのサーファーで賑わいます。穏やかな日が多い一方、うねりが強まるとサイズも出るため、コンディションに応じた判断が必要です。",
  },
  {
    id: "ikumi",
    name: "生見",
    reading: "いくみ",
    prefecture: "高知県",
    region: "kansai_shikoku",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "四国・東洋町の有名ポイント。温暖な気候で年間を通じて波を楽しめる。",
  },
  {
    id: "shishikui",
    name: "宍喰",
    reading: "ししくい",
    prefecture: "徳島県",
    region: "kansai_shikoku",
    type: "beach",
    level: "intermediate",
    bestSeason: "通年",
    summary: "徳島・海陽町のビーチ。生見と並ぶ四国南部の人気エリアで、温暖で波数も多い。",
  },
  // ── 九州・南 ─────────────────────────────────
  {
    id: "aoshima",
    name: "青島",
    reading: "あおしま",
    prefecture: "宮崎県",
    region: "kyushu",
    type: "reef",
    level: "intermediate",
    bestSeason: "通年",
    summary: "宮崎・青島周辺の有名ポイント。温暖なサーフタウンとして全国から人が集まる。",
  },
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
    details:
      "宮崎県日向市のお倉ヶ浜は、南国・宮崎を代表するビーチブレイクのひとつ。安定して質の高い波が立つことで知られ、全国からサーファーが訪れます。温暖な気候で年間を通して楽しめるのも魅力です。",
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
    details:
      "宮崎市の木崎浜は、国内トップクラスのコンテストが数多く開催されるハイクオリティなビーチブレイク。パワーのある良質な波で知られ、コンディションが揃うと国内屈指の波が立ちます。実力を試す上級者に人気のエリアです。",
  },
  {
    id: "tanegashima",
    name: "種子島",
    reading: "たねがしま",
    prefecture: "鹿児島県",
    region: "kyushu",
    type: "beach",
    level: "advanced",
    bestSeason: "通年",
    summary: "多彩なポイントを持つ南の島。ワールドクラスの波が立つことで世界的に知られる。",
    details:
      "鹿児島県の種子島は、島内に多彩なポイントを持ち、コンディションが揃うと世界レベルの波が立つことで国内外に知られるサーフアイランド。うねりと風の条件でポイントを選べる一方、パワーのある波が入るため中〜上級者向けのエリアが多くなります。",
  },
  // ── 日本海 ───────────────────────────────────
  {
    id: "yunohama",
    name: "湯野浜",
    reading: "ゆのはま",
    prefecture: "山形県",
    region: "nihonkai",
    type: "beach",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "山形・庄内の温泉地に隣接するビーチ。冬の北西うねりでサイズアップする。",
  },
  {
    id: "kujiranami",
    name: "鯨波",
    reading: "くじらなみ",
    prefecture: "新潟県",
    region: "nihonkai",
    type: "beach",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "日本海側の老舗ポイント。冬の北西うねりでサイズアップする。",
  },
  {
    id: "hatchohama",
    name: "八丁浜",
    reading: "はっちょうはま",
    prefecture: "京都府",
    region: "nihonkai",
    type: "beach",
    level: "intermediate",
    bestSeason: "秋〜冬",
    summary: "京都・京丹後の日本海ビーチ。関西勢に親しまれ、冬のうねりで賑わう。",
  },
];

/** id からポイントを引く（詳細ページ用） */
export function getSpotById(id: string): SurfSpot | undefined {
  return SURF_SPOTS.find((s) => s.id === id);
}
