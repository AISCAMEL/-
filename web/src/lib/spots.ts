// ============================================================
// 全国サーフポイント・ガイド（地方 → エリア → ポイント）
// ------------------------------------------------------------
// 一般に公開・周知されている国内の代表的なサーフポイントの「概要」情報。
// エリアごとに「海岸の向き・良いうねり・オフショア風・シーズン」を持たせ、
// 各ポイントは座標から Open-Meteo のライブ波予報を表示する。
// ※ 概要・座標は一般公開情報にもとづく目安。実際のコンディション・アクセス・
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
  { key: "kyushu", label: "九州・沖縄" },
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

/** レベル別のバッジ色 */
export const SPOT_LEVEL_STYLE: Record<SpotLevel, string> = {
  beginner: "bg-teal/15 text-teal",
  intermediate: "bg-ocean/15 text-ocean",
  advanced: "bg-navy/10 text-navy",
};

export type SpotBottom = "sand" | "rock" | "reef" | "mixed";
export const BOTTOM_LABEL: Record<SpotBottom, string> = {
  sand: "砂",
  rock: "岩・玉石",
  reef: "リーフ（岩礁・サンゴ）",
  mixed: "砂＋岩",
};

export type SpotCrowd = "low" | "mid" | "high";
export const CROWD_LABEL: Record<SpotCrowd, string> = {
  low: "空いている",
  mid: "ふつう",
  high: "混雑しやすい",
};

// ---- エリア ------------------------------------------------------

export type SurfArea = {
  id: string;
  name: string;
  prefecture: string;
  region: SpotRegion;
  /** 海岸が向いている方角（度：北=0, 東=90, 南=180, 西=270） */
  facingDeg: number;
  /** 波が立ちやすいうねりの向き */
  goodSwell: string;
  season: string;
  summary: string;
  tips: string;
  /** エリア代表点（波予報用・目安） */
  lat: number;
  lon: number;
};

export const SURF_AREAS: SurfArea[] = [
  // ── 北海道・東北 ─────────────────────────────
  {
    id: "tomakomai", name: "胆振・苫小牧", prefecture: "北海道", region: "tohoku",
    facingDeg: 180, goodSwell: "南〜南東", season: "夏〜秋",
    summary: "太平洋に面した北海道の主要サーフエリア。夏〜秋の南うねりで波が立ちやすい。",
    tips: "水温は夏でも低め。5/3mm以上のフルスーツ推奨、冬はブーツ・グローブ必須。",
    lat: 42.58, lon: 141.85,
  },
  {
    id: "sanriku", name: "三陸", prefecture: "岩手県", region: "tohoku",
    facingDeg: 90, goodSwell: "東〜南東", season: "夏〜秋",
    summary: "リアス式海岸の湾内に点在するビーチ。台風や低気圧の東うねりに反応する。",
    tips: "湾ごとに地形が大きく違う。初めての場所は地元情報を確認してから入る。",
    lat: 39.37, lon: 141.96,
  },
  {
    id: "sendai", name: "仙台湾", prefecture: "宮城県", region: "tohoku",
    facingDeg: 120, goodSwell: "東〜南東", season: "秋〜冬",
    summary: "仙台市内からアクセスしやすい東北最大級のエリア。低気圧・台風のうねりでサイズアップ。",
    tips: "港・堤防の周りは流れが強い。立入禁止区域には入らない。",
    lat: 38.2, lon: 141.05,
  },
  {
    id: "soso", name: "相双（浜通り北部）", prefecture: "福島県", region: "tohoku",
    facingDeg: 90, goodSwell: "東〜南東", season: "春〜秋",
    summary: "広野〜相馬に続く福島の浜通り。メローな日が多く、初心者の練習にも向く。",
    tips: "朝は風が弱い日が多い。昼から南〜東寄りの風が入りやすいので朝イチが狙い目。",
    lat: 37.5, lon: 141.03,
  },
  {
    id: "iwaki", name: "いわき", prefecture: "福島県", region: "tohoku",
    facingDeg: 110, goodSwell: "東〜南東", season: "春〜秋",
    summary: "福島県南部の人気エリア。複数のビーチがあり、うねりと風で入る場所を選べる。",
    tips: "週末はやや混雑。駐車・着替えのマナーを守る。",
    lat: 37.03, lon: 141.0,
  },
  // ── 関東 ─────────────────────────────────────
  {
    id: "ibaraki_north", name: "茨城県北（日立・ひたちなか）", prefecture: "茨城県", region: "kanto",
    facingDeg: 100, goodSwell: "東〜南東（北東も可）", season: "通年",
    summary: "首都圏から北へ。河原子・阿字ヶ浦などのビーチが並ぶエリア。",
    tips: "北東うねりにも反応。冬は西風（オフショア）で面が整いやすい。",
    lat: 36.48, lon: 140.66,
  },
  {
    id: "kashimanada", name: "鹿島灘", prefecture: "茨城県", region: "kanto",
    facingDeg: 90, goodSwell: "東〜南東", season: "通年",
    summary: "大洗から波崎まで続く長いビーチ。うねりを拾いやすく波数が多い。",
    tips: "広いビーチは離岸流ができやすい。流れの速い筋を避けてエントリー。",
    lat: 36.05, lon: 140.7,
  },
  {
    id: "kujukuri", name: "九十九里", prefecture: "千葉県", region: "kanto",
    facingDeg: 100, goodSwell: "東〜南東", season: "通年",
    summary: "約60kmの砂浜が続く関東屈指のビーチエリア。初心者から上級者まで。",
    tips: "同じ九十九里でも場所で波質が違う。堤防（ヘッドランド）の横は流れに注意。",
    lat: 35.5, lon: 140.49,
  },
  {
    id: "sotobo", name: "外房・一宮", prefecture: "千葉県", region: "kanto",
    facingDeg: 100, goodSwell: "東〜南東", season: "通年",
    summary: "東京2020五輪会場を含む日本屈指のエリア。良質な波が年間を通して立つ。",
    tips: "人気ポイントは混雑必至。優先権とローカルルールを最優先に。",
    lat: 35.3, lon: 140.42,
  },
  {
    id: "minamiboso", name: "南房総", prefecture: "千葉県", region: "kanto",
    facingDeg: 150, goodSwell: "南〜南東", season: "通年（特に台風シーズン）",
    summary: "房総半島南部。南うねりに反応し、外房が大きすぎる日の選択肢にもなる。",
    tips: "岩場やリーフが混じるポイントも。干潮時の浅瀬に注意。",
    lat: 35.0, lon: 140.02,
  },
  {
    id: "shonan", name: "湘南", prefecture: "神奈川県", region: "kanto",
    facingDeg: 180, goodSwell: "南〜南東", season: "夏〜秋（台風うねり）",
    summary: "都心から最も近い人気エリア。普段は小波が多く、初心者の練習に最適。",
    tips: "とても混雑する。人の多い日はピークを外す・時間帯をずらすのがコツ。",
    lat: 35.3, lon: 139.47,
  },
  // ── 東海 ─────────────────────────────────────
  {
    id: "izu", name: "伊豆（下田）", prefecture: "静岡県", region: "tokai",
    facingDeg: 120, goodSwell: "南東〜東", season: "通年",
    summary: "透明度の高い海と白い砂浜。下田周辺に良質なビーチが集まる。",
    tips: "うねりが大きい日は岩場に寄せられないよう、ポジションに注意。",
    lat: 34.68, lon: 138.98,
  },
  {
    id: "enshu", name: "遠州灘・御前崎", prefecture: "静岡県", region: "tokai",
    facingDeg: 180, goodSwell: "南〜南西", season: "通年",
    summary: "遠州灘に面し、うねりを拾いやすい。御前崎は風向きで入る場所を選べる。",
    tips: "風が強い地域。北寄りの風（オフショア）の日が狙い目。",
    lat: 34.65, lon: 138.23,
  },
  {
    id: "atsumi", name: "渥美半島", prefecture: "愛知県", region: "tokai",
    facingDeg: 180, goodSwell: "南〜南東", season: "通年",
    summary: "太平洋に面した東海の一大エリア。ロングに向く伊良湖からパワフルな赤羽根まで。",
    tips: "冬は西風が強い日が多い。風の影響が少ない場所を探すのがコツ。",
    lat: 34.59, lon: 137.12,
  },
  {
    id: "ise_shima", name: "伊勢志摩", prefecture: "三重県", region: "tokai",
    facingDeg: 120, goodSwell: "南東〜南", season: "夏〜秋",
    summary: "東海・関西から通いやすいビーチ。南東うねりで波が立つ。",
    tips: "サイズアップ時は流れが強くなる。無理をしない。",
    lat: 34.33, lon: 136.89,
  },
  // ── 関西・四国 ───────────────────────────────
  {
    id: "wakayama", name: "和歌山北部", prefecture: "和歌山県", region: "kansai_shikoku",
    facingDeg: 260, goodSwell: "南西〜西（台風・低気圧時）", season: "夏〜秋",
    summary: "大阪から近い関西の拠点エリア。台風や低気圧の通過時にうねりが入る。",
    tips: "普段は小波。うねりが入る日は非常に混雑する。",
    lat: 34.25, lon: 135.09,
  },
  {
    id: "shikoku_se", name: "四国東南（徳島・高知東部）", prefecture: "徳島県・高知県", region: "kansai_shikoku",
    facingDeg: 150, goodSwell: "南〜南東", season: "通年",
    summary: "温暖で波数の多い四国のサーフタウン。内妻・宍喰・生見が並ぶ。",
    tips: "地元サーファーが多いエリア。挨拶とマナーを大切に。",
    lat: 33.53, lon: 134.33,
  },
  {
    id: "hata", name: "高知西部（幡多）", prefecture: "高知県", region: "kansai_shikoku",
    facingDeg: 180, goodSwell: "南〜南西", season: "通年",
    summary: "黒潮の恩恵を受ける温暖なエリア。広い砂浜で比較的空いている。",
    tips: "遠浅で広い。離岸流の位置を確認してから入る。",
    lat: 33.02, lon: 133.04,
  },
  // ── 九州・沖縄 ───────────────────────────────
  {
    id: "hyuga", name: "日向", prefecture: "宮崎県", region: "kyushu",
    facingDeg: 90, goodSwell: "東〜南東", season: "通年",
    summary: "宮崎北部のサーフタウン。お倉ヶ浜・金ヶ浜など良質なビーチが続く。",
    tips: "サイズがある日はパワフル。自分のレベルに合ったピークを選ぶ。",
    lat: 32.42, lon: 131.66,
  },
  {
    id: "miyazaki_city", name: "宮崎市周辺", prefecture: "宮崎県", region: "kyushu",
    facingDeg: 100, goodSwell: "東〜南東", season: "通年",
    summary: "空港から近く、全国規模の大会も開かれる九州の中心エリア。",
    tips: "人気ポイントは混雑。青島周辺は岩場・リーフに注意。",
    lat: 31.85, lon: 131.48,
  },
  {
    id: "tanegashima", name: "種子島", prefecture: "鹿児島県", region: "kyushu",
    facingDeg: 90, goodSwell: "東・西（風向きで東西を選ぶ）", season: "通年（特に冬〜春）",
    summary: "島の東西にポイントがあり、風向きに合わせて選べるサーフアイランド。",
    tips: "パワフルな波が多い。島内の移動は車が必須。",
    lat: 30.55, lon: 131.03,
  },
  {
    id: "okinawa", name: "沖縄本島（北谷）", prefecture: "沖縄県", region: "kyushu",
    facingDeg: 270, goodSwell: "西〜北西（冬）／南（夏の台風）", season: "冬",
    summary: "サンゴ礁のリーフブレイク。冬の北西うねりで波が立つ。",
    tips: "浅いリーフ。干潮時は入らない、ブーツ推奨。サンゴを傷つけない。",
    lat: 26.32, lon: 127.74,
  },
  // ── 日本海 ───────────────────────────────────
  {
    id: "shonai", name: "庄内", prefecture: "山形県", region: "nihonkai",
    facingDeg: 270, goodSwell: "西〜北西", season: "秋〜冬",
    summary: "日本海に面した山形のエリア。冬の季節風でうねりが入る。",
    tips: "冬は強風・低水温。ブーツ・グローブ必須。風が落ちた日が狙い目。",
    lat: 38.78, lon: 139.71,
  },
  {
    id: "niigata", name: "新潟（柏崎）", prefecture: "新潟県", region: "nihonkai",
    facingDeg: 300, goodSwell: "北西〜西", season: "秋〜冬",
    summary: "日本海の老舗エリア。冬の北西うねりでサイズアップする。",
    tips: "季節風が強い日は荒れやすい。天気図で低気圧の位置を確認する。",
    lat: 37.35, lon: 138.51,
  },
  {
    id: "tango", name: "京丹後", prefecture: "京都府", region: "nihonkai",
    facingDeg: 0, goodSwell: "北〜北西", season: "秋〜冬",
    summary: "関西から通う日本海サーファーの拠点。冬場に良い波が立つ。",
    tips: "冬は雪・低水温。日没が早いので早めに上がる。",
    lat: 35.69, lon: 135.03,
  },
  {
    id: "itoshima", name: "糸島", prefecture: "福岡県", region: "nihonkai",
    facingDeg: 315, goodSwell: "北西〜北", season: "秋〜冬",
    summary: "福岡市から近い玄界灘のエリア。冬の季節風でうねりが入る。",
    tips: "夏は波が少ない。冬の北西うねりが入る日を狙う。",
    lat: 33.63, lon: 130.18,
  },
];

// ---- ポイント ----------------------------------------------------

export type SurfSpot = {
  id: string;
  name: string;
  reading?: string;
  prefecture: string;
  region: SpotRegion;
  /** 所属エリア（SURF_AREAS の id） */
  area: string;
  type: SpotType;
  level: SpotLevel;
  bestSeason: string;
  crowd: SpotCrowd;
  /** 波予報用の座標（沖側の目安） */
  lat: number;
  lon: number;
  summary: string;
  /** 詳細ページ用の補足（一般公開情報にもとづく概要）。任意 */
  details?: string;
  /** エリアと向きが違う場合のみ指定 */
  facingDeg?: number;
  /** 種別からの推定と違う場合のみ指定 */
  bottom?: SpotBottom;
  /** 当プラットフォームの拠点ポイント（岩沢海岸）を強調表示するフラグ */
  home?: boolean;
};

export const SURF_SPOTS: SurfSpot[] = [
  // ── 北海道・東北 ─────────────────────────────
  {
    id: "hama-atsuma", name: "浜厚真", reading: "はまあつま", prefecture: "北海道",
    region: "tohoku", area: "tomakomai", type: "beach", level: "intermediate",
    bestSeason: "夏〜秋", crowd: "mid", lat: 42.58, lon: 141.85,
    summary: "北海道・苫小牧近くを代表するビーチブレイク。太平洋のうねりを受ける道内の人気エリア。",
  },
  {
    id: "namiita", name: "浪板海岸", reading: "なみいたかいがん", prefecture: "岩手県",
    region: "tohoku", area: "sanriku", type: "beach", level: "intermediate",
    bestSeason: "夏〜秋", crowd: "low", lat: 39.37, lon: 141.96,
    summary: "三陸・大槌の湾に面したビーチ。東うねりが入ると湾内に波が立つ。",
  },
  {
    id: "sendai-shinko", name: "仙台新港", reading: "せんだいしんこう", prefecture: "宮城県",
    region: "tohoku", area: "sendai", type: "beach", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "mid", lat: 38.27, lon: 141.05,
    summary: "東北を代表するビーチブレイク。パワーのある波が立ちやすく、腕自慢が集まる人気エリア。",
  },
  {
    id: "shobutahama", name: "菖蒲田浜", reading: "しょうぶたはま", prefecture: "宮城県",
    region: "tohoku", area: "sendai", type: "beach", level: "beginner",
    bestSeason: "夏〜秋", crowd: "mid", lat: 38.3, lon: 141.09,
    summary: "七ヶ浜の砂浜ビーチ。仙台から近く、穏やかな日は初心者の練習にも。",
  },
  {
    id: "torinoumi", name: "鳥の海", reading: "とりのうみ", prefecture: "宮城県",
    region: "tohoku", area: "sendai", type: "rivermouth", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "low", lat: 38.03, lon: 140.94,
    summary: "宮城・亘理の河口周辺ポイント。地形により波がまとまりやすい。",
  },
  {
    id: "iwasawa", name: "岩沢海岸", reading: "いわさわかいがん", prefecture: "福島県",
    region: "tohoku", area: "soso", type: "beach", level: "beginner",
    bestSeason: "春〜秋", crowd: "low", lat: 37.215, lon: 141.01, home: true,
    summary: "当プラットフォームの拠点。メローな日が多く、海デビューに向くビーチ。ライブ波情報に対応。",
    details:
      "福島県双葉郡広野町の岩沢海岸は、穏やかな日が多く、はじめての一本を安心して迎えやすいビーチブレイク。当プラットフォームではスクール・ギアレンタル・移動サポート・ローカルガイドを通じて、初めての方の海デビューを支えています。",
  },
  {
    id: "harahama", name: "原釜", reading: "はらがま", prefecture: "福島県",
    region: "tohoku", area: "soso", type: "beach", level: "beginner",
    bestSeason: "春〜秋", crowd: "low", lat: 37.83, lon: 140.99,
    summary: "福島・相馬の親しみやすいビーチ。ゆるい日は初心者の練習にも向く。",
  },
  {
    id: "yotsukura", name: "四倉", reading: "よつくら", prefecture: "福島県",
    region: "tohoku", area: "iwaki", type: "beach", level: "beginner",
    bestSeason: "春〜秋", crowd: "mid", lat: 37.1, lon: 141.0,
    summary: "いわき北部の広い砂浜。波が比較的やさしく、ファミリーや初心者にも人気。",
  },
  {
    id: "toyoma", name: "豊間", reading: "とよま", prefecture: "福島県",
    region: "tohoku", area: "iwaki", type: "beach", level: "intermediate",
    bestSeason: "春〜秋", crowd: "mid", lat: 36.98, lon: 140.99,
    summary: "いわきの代表的なビーチの一つ。南東うねりに反応し、サイズが出る日もある。",
  },
  // ── 関東 ─────────────────────────────────────
  {
    id: "kawarago", name: "河原子", reading: "かわらご", prefecture: "茨城県",
    region: "kanto", area: "ibaraki_north", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "mid", lat: 36.56, lon: 140.69,
    summary: "日立市の海水浴場を兼ねたビーチ。比較的穏やかで初心者も入りやすい。",
  },
  {
    id: "ajigaura", name: "阿字ヶ浦", reading: "あじがうら", prefecture: "茨城県",
    region: "kanto", area: "ibaraki_north", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 36.39, lon: 140.64,
    summary: "ひたちなかの広いビーチ。うねりを拾いやすく、茨城の定番ポイント。",
  },
  {
    id: "oarai", name: "大洗", reading: "おおあらい", prefecture: "茨城県",
    region: "kanto", area: "kashimanada", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 36.31, lon: 140.6,
    summary: "茨城の人気ビーチ。北関東からアクセスしやすく、幅広いレベルが楽しめる。",
  },
  {
    id: "otake", name: "大竹海岸", reading: "おおたけかいがん", prefecture: "茨城県",
    region: "kanto", area: "kashimanada", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 36.13, lon: 140.62,
    summary: "鉾田の鹿島灘に面したビーチ。うねりの反応が良く波数が多い。",
  },
  {
    id: "hasaki", name: "波崎", reading: "はさき", prefecture: "茨城県",
    region: "kanto", area: "kashimanada", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 35.75, lon: 140.88,
    summary: "茨城・神栖の広いビーチブレイク。うねりを受けやすく多くのサーファーが集まる。",
  },
  {
    id: "katakai", name: "片貝", reading: "かたかい", prefecture: "千葉県",
    region: "kanto", area: "kujukuri", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "mid", lat: 35.53, lon: 140.49,
    summary: "九十九里の中央部。ヘッドランドで区切られたビーチが続き、初心者から楽しめる。",
  },
  {
    id: "ichinomiya", name: "一宮", reading: "いちのみや", prefecture: "千葉県",
    region: "kanto", area: "sotobo", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "high", lat: 35.37, lon: 140.41,
    summary: "千葉・外房の中心エリア。良質な波でコンテストも多数開催される定番ポイント。",
  },
  {
    id: "shidashita", name: "志田下（釣ヶ崎海岸）", reading: "しだした", prefecture: "千葉県",
    region: "kanto", area: "sotobo", type: "beach", level: "advanced",
    bestSeason: "秋〜春", crowd: "high", lat: 35.35, lon: 140.41,
    summary: "東京2020オリンピック サーフィン会場。日本を代表するハイパフォーマンス・ビーチブレイク。",
    details:
      "千葉県一宮町の釣ヶ崎海岸（通称・志田下）は、東京2020オリンピックのサーフィン競技会場となった日本屈指のビーチブレイク。質の高い波が立つことで知られ、国内トップサーファーが集まります。パワーのある波が入るため、コンディションによっては上級者向けとなります。",
  },
  {
    id: "torami", name: "東浪見", reading: "とらみ", prefecture: "千葉県",
    region: "kanto", area: "sotobo", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "high", lat: 35.33, lon: 140.41,
    summary: "一宮の南に続く人気ビーチ。波数が多く、平日でもサーファーが多い。",
  },
  {
    id: "taito", name: "太東", reading: "たいとう", prefecture: "千葉県",
    region: "kanto", area: "sotobo", type: "point", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 35.3, lon: 140.42,
    summary: "外房・岬地形のポイント。うねりの反応がよく、幅広いコンディションで楽しめる。",
  },
  {
    id: "onjuku", name: "御宿", reading: "おんじゅく", prefecture: "千葉県",
    region: "kanto", area: "sotobo", type: "beach", level: "beginner",
    bestSeason: "夏〜秋", crowd: "mid", lat: 35.18, lon: 140.37,
    summary: "白い砂浜が広がる外房南部のビーチ。穏やかな日は初心者も入りやすい。",
  },
  {
    id: "kamogawa", name: "鴨川（マルキ）", reading: "かもがわ", prefecture: "千葉県",
    region: "kanto", area: "minamiboso", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 35.1, lon: 140.12,
    summary: "南房総・鴨川の有名ビーチ。マルキポイントとして広く知られる定番エリア。",
  },
  {
    id: "chikura", name: "千倉", reading: "ちくら", prefecture: "千葉県",
    region: "kanto", area: "minamiboso", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 34.95, lon: 139.97,
    summary: "房総半島南端の東側。南うねりに反応し、外房と違う顔を見せる。",
    bottom: "mixed",
  },
  {
    id: "heisaura", name: "平砂浦", reading: "へいさうら", prefecture: "千葉県",
    region: "kanto", area: "minamiboso", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "low", lat: 34.92, lon: 139.84, facingDeg: 200,
    summary: "館山の約6kmに及ぶ砂浜。広くて空いており、のびのび練習できる。",
  },
  {
    id: "kugenuma", name: "鵠沼海岸", reading: "くげぬまかいがん", prefecture: "神奈川県",
    region: "kanto", area: "shonan", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "high", lat: 35.3, lon: 139.48,
    summary: "湘南を代表する首都圏屈指の人気ビーチ。アクセス良好で初心者・スクールも多い。",
    details:
      "神奈川県藤沢市の鵠沼海岸は、湘南でも特に知名度の高いビーチブレイク。電車でのアクセスがよく、スクールやレンタルも充実しているため、はじめての人から上級者まで幅広く集まります。人気ゆえに混み合いやすいので、時間帯やマナーへの配慮が大切なエリアです。",
  },
  {
    id: "tsujido", name: "辻堂", reading: "つじどう", prefecture: "神奈川県",
    region: "kanto", area: "shonan", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "high", lat: 35.31, lon: 139.45,
    summary: "湘南・辻堂のビーチ。ファミリーや初心者にも親しまれる穏やかな日が多いエリア。",
  },
  {
    id: "chigasaki", name: "茅ヶ崎", reading: "ちがさき", prefecture: "神奈川県",
    region: "kanto", area: "shonan", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "high", lat: 35.3, lon: 139.4,
    summary: "サーフ文化の根づく街。ヘッドランド周辺など複数のピークがある。",
  },
  {
    id: "shichirigahama", name: "七里ヶ浜", reading: "しちりがはま", prefecture: "神奈川県",
    region: "kanto", area: "shonan", type: "beach", level: "intermediate",
    bestSeason: "夏〜秋", crowd: "high", lat: 35.29, lon: 139.51, bottom: "mixed",
    summary: "鎌倉の景勝地。南うねりが入ると形の良い波が立つ。",
  },
  {
    id: "inamuragasaki", name: "稲村ヶ崎", reading: "いなむらがさき", prefecture: "神奈川県",
    region: "kanto", area: "shonan", type: "point", level: "advanced",
    bestSeason: "夏〜秋（台風時）", crowd: "high", lat: 35.29, lon: 139.53,
    summary: "大きな台風うねりの時だけ割れる伝説的なポイント。普段はほとんど波がない。",
  },
  // ── 東海 ─────────────────────────────────────
  {
    id: "shirahama-shimoda", name: "白浜（下田）", reading: "しらはま", prefecture: "静岡県",
    region: "tokai", area: "izu", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "mid", lat: 34.7, lon: 139.0,
    summary: "白い砂と透明な海が美しい伊豆の人気ビーチ。",
  },
  {
    id: "tatadohama", name: "多々戸浜", reading: "たたどはま", prefecture: "静岡県",
    region: "tokai", area: "izu", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 34.65, lon: 138.96,
    summary: "下田の小さな湾のビーチ。大会も開かれる伊豆の定番ポイント。",
  },
  {
    id: "shizunami", name: "静波", reading: "しずなみ", prefecture: "静岡県",
    region: "tokai", area: "enshu", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "mid", lat: 34.7, lon: 138.24,
    summary: "静岡・牧之原の親しみやすいビーチ。穏やかな日が多く初心者にも人気。",
  },
  {
    id: "omaezaki", name: "御前崎", reading: "おまえざき", prefecture: "静岡県",
    region: "tokai", area: "enshu", type: "point", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 34.59, lon: 138.22, bottom: "mixed",
    summary: "岬地形で多方向のうねりを拾う。複数のポイントがあり風・うねりで選べる。",
  },
  {
    id: "irako", name: "伊良湖", reading: "いらご", prefecture: "愛知県",
    region: "tokai", area: "atsumi", type: "point", level: "beginner",
    bestSeason: "通年", crowd: "mid", lat: 34.58, lon: 137.03, bottom: "mixed",
    summary: "ロングボードの聖地として知られる長い乗り味のポイント。伊良湖岬周辺。",
    details:
      "愛知県田原市の伊良湖は、長くゆったりと乗れる波質からロングボードの聖地として親しまれてきたポイント。渥美半島の先端・伊良湖岬周辺に位置し、コンディションが揃うと質の高い波が続きます。周辺には複数のポイントがあり、うねりと風で選び分けられます。",
  },
  {
    id: "akabane", name: "赤羽根", reading: "あかばね", prefecture: "愛知県",
    region: "tokai", area: "atsumi", type: "beach", level: "advanced",
    bestSeason: "通年", crowd: "mid", lat: 34.59, lon: 137.18,
    summary: "渥美半島・太平洋岸のビーチ。パワフルな波でコンテストも開催される実力派エリア。",
  },
  {
    id: "kounohama", name: "国府の浜", reading: "こうのはま", prefecture: "三重県",
    region: "tokai", area: "ise_shima", type: "beach", level: "intermediate",
    bestSeason: "夏〜秋", crowd: "mid", lat: 34.33, lon: 136.89,
    summary: "志摩の長い砂浜。南東うねりに反応し、東海・関西から多くのサーファーが訪れる。",
  },
  // ── 関西・四国 ───────────────────────────────
  {
    id: "isonoura", name: "磯ノ浦", reading: "いそのうら", prefecture: "和歌山県",
    region: "kansai_shikoku", area: "wakayama", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "high", lat: 34.25, lon: 135.09,
    summary: "関西を代表する人気ビーチ。電車でアクセスでき、初心者や学生に親しまれる。",
    details:
      "和歌山市の磯ノ浦は、関西でもっとも知られるビーチブレイクのひとつ。南海電車でアクセスできる利便性から、初心者や学生を含め多くのサーファーで賑わいます。穏やかな日が多い一方、うねりが強まるとサイズも出るため、コンディションに応じた判断が必要です。",
  },
  {
    id: "utsuma", name: "内妻", reading: "うつま", prefecture: "徳島県",
    region: "kansai_shikoku", area: "shikoku_se", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 33.56, lon: 134.37,
    summary: "徳島・海陽町の人気ビーチ。南うねりに反応し、波数が多い。",
  },
  {
    id: "shishikui", name: "宍喰", reading: "ししくい", prefecture: "徳島県",
    region: "kansai_shikoku", area: "shikoku_se", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 33.55, lon: 134.31,
    summary: "徳島・海陽町のビーチ。生見と並ぶ四国南部の人気エリアで、温暖で波数も多い。",
  },
  {
    id: "ikumi", name: "生見", reading: "いくみ", prefecture: "高知県",
    region: "kansai_shikoku", area: "shikoku_se", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 33.51, lon: 134.28,
    summary: "四国・東洋町の有名ポイント。温暖な気候で年間を通じて波を楽しめる。",
  },
  {
    id: "irino", name: "入野", reading: "いりの", prefecture: "高知県",
    region: "kansai_shikoku", area: "hata", type: "beach", level: "beginner",
    bestSeason: "通年", crowd: "low", lat: 33.02, lon: 133.04,
    summary: "黒潮町の約4kmの砂浜。広くて空いており、のんびり楽しめる。",
  },
  // ── 九州・沖縄 ───────────────────────────────
  {
    id: "kanegahama", name: "金ヶ浜", reading: "かねがはま", prefecture: "宮崎県",
    region: "kyushu", area: "hyuga", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 32.43, lon: 131.67,
    summary: "日向の美しいビーチ。東うねりに反応し、良質な波が立つ。",
  },
  {
    id: "okuragahama", name: "お倉ヶ浜", reading: "おくらがはま", prefecture: "宮崎県",
    region: "kyushu", area: "hyuga", type: "beach", level: "intermediate",
    bestSeason: "通年", crowd: "mid", lat: 32.41, lon: 131.66,
    summary: "宮崎・日向の代表的なビーチブレイク。良質な波質で全国から人が集まる。",
    details:
      "宮崎県日向市のお倉ヶ浜は、南国・宮崎を代表するビーチブレイクのひとつ。安定して質の高い波が立つことで知られ、全国からサーファーが訪れます。温暖な気候で年間を通して楽しめるのも魅力です。",
  },
  {
    id: "kizakihama", name: "木崎浜", reading: "きさきはま", prefecture: "宮崎県",
    region: "kyushu", area: "miyazaki_city", type: "beach", level: "advanced",
    bestSeason: "通年", crowd: "high", lat: 31.88, lon: 131.48,
    summary: "国内トップクラスのコンテストが行われるビーチ。パワフルで質の高い波で知られる。",
    details:
      "宮崎市の木崎浜は、国内トップクラスのコンテストが数多く開催されるハイクオリティなビーチブレイク。パワーのある良質な波で知られ、コンディションが揃うと国内屈指の波が立ちます。実力を試す上級者に人気のエリアです。",
  },
  {
    id: "aoshima", name: "青島", reading: "あおしま", prefecture: "宮崎県",
    region: "kyushu", area: "miyazaki_city", type: "reef", level: "intermediate",
    bestSeason: "通年", crowd: "high", lat: 31.8, lon: 131.49,
    summary: "宮崎・青島周辺の有名ポイント。温暖なサーフタウンとして全国から人が集まる。",
  },
  {
    id: "tanegashima", name: "種子島", reading: "たねがしま", prefecture: "鹿児島県",
    region: "kyushu", area: "tanegashima", type: "beach", level: "advanced",
    bestSeason: "通年", crowd: "low", lat: 30.55, lon: 131.05,
    summary: "多彩なポイントを持つ南の島。ワールドクラスの波が立つことで知られる。",
    details:
      "鹿児島県の種子島は、島内に多彩なポイントを持ち、コンディションが揃うと世界レベルの波が立つことで国内外に知られるサーフアイランド。うねりと風の条件でポイントを選べる一方、パワーのある波が入るため中〜上級者向けのエリアが多くなります。",
  },
  {
    id: "sunabe", name: "砂辺", reading: "すなべ", prefecture: "沖縄県",
    region: "kyushu", area: "okinawa", type: "reef", level: "advanced",
    bestSeason: "冬", crowd: "mid", lat: 26.33, lon: 127.73,
    summary: "北谷の護岸沿いに広がるリーフブレイク。冬の北西うねりで波が立つ。",
  },
  // ── 日本海 ───────────────────────────────────
  {
    id: "yunohama", name: "湯野浜", reading: "ゆのはま", prefecture: "山形県",
    region: "nihonkai", area: "shonai", type: "beach", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "low", lat: 38.78, lon: 139.71,
    summary: "山形・庄内の温泉地に隣接するビーチ。冬の北西うねりでサイズアップする。",
  },
  {
    id: "kujiranami", name: "鯨波", reading: "くじらなみ", prefecture: "新潟県",
    region: "nihonkai", area: "niigata", type: "beach", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "mid", lat: 37.35, lon: 138.51,
    summary: "日本海側の老舗ポイント。冬の北西うねりでサイズアップする。",
  },
  {
    id: "hatchohama", name: "八丁浜", reading: "はっちょうはま", prefecture: "京都府",
    region: "nihonkai", area: "tango", type: "beach", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "mid", lat: 35.69, lon: 135.03,
    summary: "京都・京丹後の日本海ビーチ。関西勢に親しまれ、冬のうねりで賑わう。",
  },
  {
    id: "futamigaura", name: "二見ヶ浦（糸島）", reading: "ふたみがうら", prefecture: "福岡県",
    region: "nihonkai", area: "itoshima", type: "beach", level: "intermediate",
    bestSeason: "秋〜冬", crowd: "mid", lat: 33.63, lon: 130.18,
    summary: "糸島の夫婦岩で知られる海岸。冬の季節風のうねりで波が立つ。",
  },
];

// ---- 取得ヘルパー ------------------------------------------------

export function getSpotById(id: string): SurfSpot | undefined {
  return SURF_SPOTS.find((s) => s.id === id);
}

export function getAreaById(id: string): SurfArea | undefined {
  return SURF_AREAS.find((a) => a.id === id);
}

export function areasInRegion(region: SpotRegion): SurfArea[] {
  return SURF_AREAS.filter((a) => a.region === region);
}

export function spotsInArea(areaId: string): SurfSpot[] {
  return SURF_SPOTS.filter((s) => s.area === areaId);
}

// ---- 向き・風の判定 ----------------------------------------------

const COMPASS8 = ["北", "北東", "東", "南東", "南", "南西", "西", "北西"];

/** 度 → 8方位 */
export function compass8(deg: number): string {
  return COMPASS8[Math.round((((deg % 360) + 360) % 360) / 45) % 8];
}

/** ポイントの向き（エリアと違えばポイント側を優先） */
export function facingOf(spot: SurfSpot, area?: SurfArea): number {
  return spot.facingDeg ?? area?.facingDeg ?? 90;
}

/** オフショアになる風（陸から海へ吹く風）の向き */
export function offshoreWindLabel(facingDeg: number): string {
  return `${compass8(facingDeg + 180)}の風`;
}

export type WindRelation = "offshore" | "cross" | "onshore";

/** 今吹いている風（吹いてくる方角）が、海岸に対してどう当たるか */
export function windRelation(windFromDeg: number, facingDeg: number): WindRelation {
  const offshoreFrom = (facingDeg + 180) % 360;
  const diff = Math.abs(((((windFromDeg - offshoreFrom) % 360) + 540) % 360) - 180);
  if (diff <= 45) return "offshore";
  if (diff >= 135) return "onshore";
  return "cross";
}

export const WIND_RELATION_META: Record<WindRelation, { label: string; note: string; style: string }> = {
  offshore: { label: "オフショア", note: "陸からの風。面が整いやすい", style: "bg-teal/15 text-teal" },
  cross: { label: "サイドショア", note: "横からの風。場所によって面が乱れる", style: "bg-sand text-navy/70" },
  onshore: { label: "オンショア", note: "海からの風。面が乱れやすい", style: "bg-navy/10 text-navy/70" },
};

// ---- 底質・注意点 ------------------------------------------------

export function bottomOf(spot: SurfSpot): SpotBottom {
  if (spot.bottom) return spot.bottom;
  if (spot.type === "reef") return "reef";
  if (spot.type === "point") return "rock";
  return "sand";
}

/** 種別・レベル・混雑・地域から、入る前に知っておきたい注意点をまとめる */
export function cautionsFor(spot: SurfSpot): string[] {
  const list: string[] = [];
  const bottom = bottomOf(spot);
  if (spot.type === "beach" || spot.type === "rivermouth") {
    list.push("離岸流（沖へ流れる強い流れ）に注意。流されたら岸と平行に泳いで抜ける。");
  }
  if (spot.type === "rivermouth") {
    list.push("雨の後は水が濁り、流れも強くなる。数日は様子を見る。");
  }
  if (bottom === "reef") {
    list.push("海底が浅い岩礁。干潮時は特に注意し、ブーツの着用がおすすめ。");
  } else if (bottom === "rock" || bottom === "mixed") {
    list.push("岩・玉石が混じる。波に巻かれた時は頭を守り、岩場に近づかない。");
  }
  if (spot.level === "advanced") {
    list.push("上級者が多いパワフルな波。サイズのある日は無理をしない。");
  }
  if (spot.crowd === "high") {
    list.push("混雑しやすい。優先権（ピークに近い人が優先）とローカルルールを守る。");
  }
  if (spot.region === "nihonkai" || spot.area === "tomakomai") {
    list.push("冬は水温が低く風も強い。厚手のフルスーツ・ブーツ・グローブを用意する。");
  }
  if (spot.area === "okinawa") {
    list.push("サンゴを踏まない・傷つけない。日差しが強いので日焼け対策も。");
  }
  return list;
}
