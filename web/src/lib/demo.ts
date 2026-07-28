// ============================================================
// デモモード
// Supabase 未接続のとき、サンプルデータで画面を体験できるようにする。
// （npm run dev だけで中身のあるプレビューが見られる。実接続すると自動でOFF）
// ============================================================
import type { PostSummary } from "@/components/community/post-card";
import type { WaveReport } from "@/lib/waves";
import type { SkillCategory } from "@/lib/skills";

/** Supabase 未設定ならデモモード */
export const DEMO =
  !process.env.NEXT_PUBLIC_SUPABASE_URL ||
  !process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY;

const now = "2026-06-16T00:00:00+09:00";

export const demoPosts: PostSummary[] = [
  {
    id: "demo-1",
    category: "experiences",
    title: "初めての海デビュー、最高でした！",
    body: "広野まで来てよかった。インストラクターの方が丁寧で、こわさより楽しさが勝ちました。テイクオフの瞬間、忘れられません🌊",
    like_count: 12,
    created_at: now,
    author: { display_name: "ゆうき", role: "beginner" },
  },
  {
    id: "demo-2",
    category: "questions",
    title: "初心者におすすめのボードサイズは？",
    body: "身長170cm・体重65kgです。最初の一本で迷っています。ロングとファンボード、どちらがいいでしょう？広野の波にも合うものが知りたいです。",
    like_count: 5,
    created_at: now,
    author: { display_name: "みなと", role: "beginner" },
  },
  {
    id: "demo-3",
    category: "waves",
    title: "今朝の岩沢、腰〜胸でメロー",
    body: "風も弱くて気持ちよかった。朝イチが狙い目です。昼から南風で崩れるかも。",
    like_count: 21,
    created_at: now,
    author: { display_name: "タカ", role: "local" },
  },
  {
    id: "demo-4",
    category: "gear",
    title: "夏用ウェット、みんな何着てる？",
    body: "そろそろ3mmフルからシーガルに替えようか迷い中。水温まだ低め？",
    like_count: 3,
    created_at: now,
    author: { display_name: "ケン", role: "local" },
  },
  {
    id: "demo-5",
    category: "events",
    title: "今週末、ビーチクリーン＆朝サーフやります",
    body: "6/21(土) 6:00 集合。初めての方も歓迎、道具貸し出しあり。終わったらコーヒー☕",
    like_count: 9,
    created_at: now,
    author: { display_name: "IWASAWA運営", role: "staff" },
  },
];

export const demoPostById: Record<
  string,
  { post: PostSummary; comments: { id: string; body: string; author: string }[] }
> = {
  "demo-2": {
    post: demoPosts[1],
    comments: [
      { id: "c1", body: "最初はファンボード7'6\"くらいが安定して楽しいですよ！", author: "タカ" },
      { id: "c2", body: "広野はゆるい日が多いので浮力多めが◎", author: "ケン" },
    ],
  },
};

export type DemoSkill = {
  id: string;
  category: SkillCategory;
  title: string;
  description: string;
  price: number | null;
  area: string | null;
  owner: { display_name: string | null; role: string } | null;
};

export const demoSkills: DemoSkill[] = [
  {
    id: "skill-1",
    category: "school",
    title: "初心者向け 海デビュー体験レッスン",
    description: "基礎の陸トレから、パドル・テイクオフまで丁寧にサポート。ボードレンタル込み。広野の穏やかな日を選んで開催します。",
    price: 5000,
    area: "岩沢海岸",
    owner: { display_name: "タカ", role: "local" },
  },
  {
    id: "skill-2",
    category: "repair",
    title: "ディング（小傷）リペアします",
    description: "ノーズ・レール周りの補修。乾燥含め2〜3日。まずは写真を送っていただければ見積もります。",
    price: null,
    area: "広野町",
    owner: { display_name: "ケン", role: "beginner" },
  },
  {
    id: "skill-3",
    category: "photo",
    title: "サーフィン中の写真、撮ります",
    description: "陸から望遠で。データ納品、30枚〜。SNS用の加工もご相談ください。",
    price: 3000,
    area: "岩沢海岸",
    owner: { display_name: "まゆ", role: "beginner" },
  },
];

export const demoWaveReport: WaveReport = {
  now: {
    time: now,
    waveHeight: 0.8,
    wavePeriod: 8,
    waveDirection: 90,
    waterTemp: 19,
    windSpeed: 8,
    windDirection: 225,
    temperature: 21,
    weatherCode: 2,
    precipitation: 0,
  },
  today: [
    { time: "2026-06-16T06:00", waveHeight: 0.9 },
    { time: "2026-06-16T09:00", waveHeight: 0.8 },
    { time: "2026-06-16T12:00", waveHeight: 0.7 },
    { time: "2026-06-16T15:00", waveHeight: 0.6 },
    { time: "2026-06-16T18:00", waveHeight: 0.7 },
    { time: "2026-06-16T21:00", waveHeight: 0.9 },
  ],
  days: [
    { date: "2026-06-16", waveHeightMax: 0.9, waveDirection: 90, tempMax: 23, tempMin: 17, weatherCode: 2, precipProb: 10, windMax: 14, sunrise: "2026-06-16T04:20", sunset: "2026-06-16T18:52" },
    { date: "2026-06-17", waveHeightMax: 1.3, waveDirection: 100, tempMax: 24, tempMin: 18, weatherCode: 3, precipProb: 30, windMax: 22, sunrise: "2026-06-17T04:20", sunset: "2026-06-17T18:52" },
    { date: "2026-06-18", waveHeightMax: 0.5, waveDirection: 80, tempMax: 22, tempMin: 17, weatherCode: 61, precipProb: 70, windMax: 18, sunrise: "2026-06-18T04:20", sunset: "2026-06-18T18:53" },
    { date: "2026-06-19", waveHeightMax: 0.7, waveDirection: 95, tempMax: 25, tempMin: 19, weatherCode: 1, precipProb: 10, windMax: 12, sunrise: "2026-06-19T04:20", sunset: "2026-06-19T18:53" },
  ],
  fetchedAt: now,
};

export const demoLocalWaves = [
  { id: "demo-3", body: "朝イチがメローでおすすめ。昼から風上がりそう。", author: "タカ（Local）" },
  { id: "demo-4", body: "今日は腰くらい。初心者の練習に良い日。", author: "ケン（Local）" },
];

// --- サーフィンスクール（プロ講師）------------------------------
import type { InstructorRank } from "@/lib/instructors";

export type DemoInstructor = {
  id: string;
  name: string;
  avatar_url: string | null;
  rank: InstructorRank;
  headline: string;
  bio: string;
  achievements: string;
  home_break: string;
  years: number;
  monthly_price: number | null;
  accepting: boolean;
  rating_avg: number;
  rating_count: number;
};

export const demoInstructors: DemoInstructor[] = [
  {
    id: "ins-1",
    name: "遠藤 海斗",
    avatar_url: null,
    rank: "pro",
    headline: "JPSA参戦のプロが、初めての一本を全力サポート",
    bio: "福島出身のプロサーファー。国内ツアーを転戦しながら、地元・岩沢で初心者スクールを開いています。「怖い」を「楽しい」に変えるのが得意です。",
    achievements: "JPSAロングボード 年間ランキング上位／東北アマチュア選手権 優勝",
    home_break: "岩沢海岸",
    years: 18,
    monthly_price: 12000,
    accepting: true,
    rating_avg: 4.9,
    rating_count: 38,
  },
  {
    id: "ins-2",
    name: "佐藤 みなみ",
    avatar_url: null,
    rank: "top_amateur",
    headline: "やさしく丁寧に。女性・初心者に人気の講師",
    bio: "トップアマチュアとして大会に出場しつつ、週末は初心者・女性向けレッスンを担当。基礎の姿勢づくりから、無理なくステップアップできます。",
    achievements: "全日本サーフィン選手権 東北ブロック 準優勝",
    home_break: "岩沢海岸",
    years: 9,
    monthly_price: 9000,
    accepting: true,
    rating_avg: 4.8,
    rating_count: 27,
  },
  {
    id: "ins-3",
    name: "工藤 亮",
    avatar_url: null,
    rank: "instructor",
    headline: "安全第一。ファミリー・キッズも安心の認定インストラクター",
    bio: "日本サーフィン連盟 公認インストラクター。海の安全とルールを大切に、家族連れやお子さまにも丁寧に指導します。",
    achievements: "JSF公認インストラクター／ライフセービング資格保有",
    home_break: "岩沢海岸",
    years: 12,
    monthly_price: 8000,
    accepting: false,
    rating_avg: 4.7,
    rating_count: 15,
  },
];

export const demoInstructorReviews: Record<
  string,
  { id: string; author: string; rating: number; body: string }[]
> = {
  "ins-1": [
    { id: "r1", author: "みなと", rating: 5, body: "まったくの初心者でしたが、初回でテイクオフできました！褒め上手で楽しかったです。" },
    { id: "r2", author: "あや", rating: 5, body: "海の怖さが無くなりました。安全面の説明も丁寧で安心。" },
    { id: "r3", author: "だいき", rating: 4, body: "人気で予約が取りにくいのが玉に瑕。内容は文句なし。" },
  ],
  "ins-2": [
    { id: "r4", author: "ゆい", rating: 5, body: "女性一人でも参加しやすい雰囲気。基礎から丁寧でした。" },
    { id: "r5", author: "さき", rating: 5, body: "姿勢を直してもらってから一気に上達しました！" },
  ],
  "ins-3": [
    { id: "r6", author: "たけし", rating: 5, body: "子どもと参加。安全第一で親も安心して見ていられました。" },
  ],
};

// --- 広告枠（スポンサーバナー）---------------------------------
export type DemoAd = {
  id: string;
  advertiser: string;
  image_url: string;
  href: string;
  placement: "feed" | "waves" | "blog" | "all";
};

export const demoAds: DemoAd[] = [
  { id: "ad-1", advertiser: "WAVE RIDER 広野店", image_url: "/ads/shop.png", href: "#", placement: "feed" },
  { id: "ad-2", advertiser: "AQUA wetsuits", image_url: "/ads/wetsuit.png", href: "#", placement: "waves" },
  { id: "ad-3", advertiser: "岩沢の宿 うみやど", image_url: "/ads/cafe.png", href: "#", placement: "blog" },
];

export type DemoArticle = {
  id: string;
  title: string;
  author: string;
  rank: InstructorRank;
  excerpt: string;
  body: string;
  published_at: string;
};

export const demoArticles: DemoArticle[] = [
  {
    id: "art-1",
    title: "初心者がまず揃えるべき3つの道具",
    author: "遠藤 海斗",
    rank: "pro",
    excerpt: "最初の一本、ウェット、そしてリーシュ。プロが本当に必要なものだけ解説します。",
    body: "サーフィンを始めるとき、道具選びで迷う人はとても多いです。結論から言うと、最初に必要なのは『ボード・ウェット・リーシュコード』の3つ。\n\nボードは浮力のあるファンボード〜ロングがおすすめ。岩沢のようなメローな波なら、無理に短い板を選ばず、まずは安定して立てる一本を。ウェットは水温に合わせて、迷ったらショップかスクールで相談を。\n\n道具は『続けられる』ことが一番大事。最初から高価なものを揃える必要はありません。",
    published_at: "2026-06-14",
  },
  {
    id: "art-2",
    title: "海が怖い人へ。安全に楽しむ5つの心得",
    author: "佐藤 みなみ",
    rank: "top_amateur",
    excerpt: "『怖い』は正しい感覚。だからこそ知っておきたい、海と仲良くなる基本。",
    body: "海が怖いと感じるのは、とても自然なことです。むしろその感覚は大切にしてください。\n\n①自分の膝〜腰くらいの深さから始める ②離岸流（リップカレント）を知る ③体調が悪い日は入らない ④一人では入らない ⑤無理をしない。\n\nこの5つを守れば、海はぐっと身近になります。焦らず、自分のペースで。",
    published_at: "2026-06-12",
  },
  {
    id: "art-3",
    title: "岩沢海岸のシーズン別コンディション",
    author: "遠藤 海斗",
    rank: "pro",
    excerpt: "地元プロが教える、季節ごとの波と混雑、ベストな時間帯。",
    body: "岩沢海岸は年間を通して楽しめるビーチブレイク。春〜初夏はメローで初心者に最適、秋は台風うねりでサイズアップ。\n\n朝イチは風が弱く、面がきれいなことが多いのでおすすめです。地元の波情報とローカルの声もチェックして、その日のベストな時間に入りましょう。",
    published_at: "2026-06-10",
  },
];
