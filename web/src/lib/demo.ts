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

// --- オンライン講座（マニュアル＋動画・サブスク）----------------
import type { CourseLevel } from "@/lib/courses";

export type DemoLesson = {
  id: string;
  title: string;
  body: string;
  is_free: boolean;
  duration_min: number;
};

export type DemoCourse = {
  id: string;
  title: string;
  description: string;
  level: CourseLevel;
  cover_url: string;
  lessons: DemoLesson[];
};

export const demoCourses: DemoCourse[] = [
  {
    id: "c-beginner",
    title: "海デビュー 初心者コース",
    description: "まったくの初めてから、自分で海に入れるようになるまで。ルール・パドリング・テイクオフを、動画とマニュアルで。",
    level: "beginner",
    cover_url: "/courses/beginner.png",
    lessons: [
      {
        id: "l-rules",
        title: "第1回：海のルールと安全（無料）",
        is_free: true,
        duration_min: 8,
        body: "サーフィンは自然が相手のスポーツです。まず身につけたいのは『安全』と『ルール』。\n\n■ 一本の波に一人\nピーク（波の割れ始め）に近い人が優先。すでに乗っている人の前に入る『ドロップイン（前乗り）』は絶対に避けます。\n\n■ 離岸流を知る\n沖へ流れる速い流れ。捕まったら岸に平行に泳いで抜けます。逆らって岸へ戻ろうとしないこと。\n\n■ 自分のレベルで\nサイズ・風・混雑を確認し、無理をしない。初めは膝〜腰の深さ、空いた場所で練習しましょう。",
      },
      {
        id: "l-paddle",
        title: "第2回：パドリングの基礎",
        is_free: false,
        duration_min: 10,
        body: "パドリングはサーフィンの土台。ここが安定すると、上達が一気に早くなります。\n\n■ ボードの上の位置\nノーズ（先端）が水面から握りこぶし1つ分出るくらい。前すぎると刺さり、後ろすぎると進みません。\n\n■ 手のかき方\n体の中心線に沿って、水を後ろへ長く押す。左右交互に、あわてず大きく。\n\n■ 目線\n進みたい方向を見る。下を向くと失速します。",
      },
      {
        id: "l-takeoff",
        title: "第3回：テイクオフ",
        is_free: false,
        duration_min: 12,
        body: "波に乗って立ち上がる一連の動き。陸トレで体に覚えさせてから海へ。\n\n■ タイミング\n波が近づいたら強くパドル。ボードが波に押される感覚を掴む。\n\n■ 立ち上がり\n胸を起こし、後ろ足→前足の順にすばやく。目線は前。",
      },
      {
        id: "l-getout",
        title: "第4回：ゲッティングアウト（沖に出る）",
        is_free: false,
        duration_min: 9,
        body: "沖に出るときは、乗っている人の進路を避けて回り込みます。白波の下をくぐる、押し返される力の逃し方など、安全に出るコツを解説します。",
      },
    ],
  },
  {
    id: "c-prep",
    title: "来訪前の準備・事前学習",
    description: "県外から岩沢へ来る前に。持ち物・装備・海の入り方を、来る前に家で予習。",
    level: "prep",
    cover_url: "/courses/prep.png",
    lessons: [
      {
        id: "l-gear",
        title: "第1回：持ち物と装備（無料）",
        is_free: true,
        duration_min: 6,
        body: "初回に必要なのは、ボード・ウェット・リーシュの3つ。あとはタオル・水・日焼け対策。\n\nレンタルも使えるので手ぶらでもOK。水温に合わせたウェット選びは、スクールやショップに相談を。",
      },
      {
        id: "l-access",
        title: "第2回：岩沢海岸の入り方・注意点",
        is_free: false,
        duration_min: 8,
        body: "駐車場所、エントリーとカレント、混雑する時間帯、地元のマナー。初めての岩沢を安心して楽しむための実践情報。",
      },
    ],
  },
  {
    id: "c-stepup",
    title: "ステップアップコース",
    description: "テイクオフができた次へ。横に走る・ターンの基礎を身につける。",
    level: "intermediate",
    cover_url: "/courses/stepup.png",
    lessons: [
      { id: "l-trim", title: "第1回：横に走る（トリム）", is_free: false, duration_min: 11, body: "波の斜面（フェイス）を横に走るための重心と目線。板を走らせる感覚を掴みます。" },
      { id: "l-bottom", title: "第2回：ボトムターン", is_free: false, duration_min: 12, body: "すべての基本となるターン。踏み込みのタイミングと体の使い方を解説します。" },
    ],
  },
];

// --- オンラインショップ（無在庫・受注起点）----------------------
import type { ProductCategory } from "@/lib/shop";

export type DemoProduct = {
  id: string;
  name: string;
  description: string;
  category: ProductCategory;
  price: number;
  shipping_fee: number;
  lead_time_text: string;
  image_url: string;
};

export const demoProducts: DemoProduct[] = [
  {
    id: "p-funboard",
    name: 'ファンボード 7\'6"',
    description: "初心者に最適な浮力とサイズ。岩沢のメローな波で、安定して立てる一本。",
    category: "board",
    price: 68000,
    shipping_fee: 3000,
    lead_time_text: "受注確定後 7〜14営業日で発送",
    image_url: "/shop/funboard.png",
  },
  {
    id: "p-longboard",
    name: 'ロングボード 9\'0"',
    description: "ゆったりしたテイクオフとクルージング。長く付き合える定番モデル。",
    category: "board",
    price: 98000,
    shipping_fee: 3000,
    lead_time_text: "受注確定後 7〜14営業日で発送",
    image_url: "/shop/longboard.png",
  },
  {
    id: "p-wetsuit",
    name: "フルスーツ 3/2mm",
    description: "春〜初夏の岩沢に。動きやすく、はじめての一着におすすめ。",
    category: "wetsuit",
    price: 24000,
    shipping_fee: 800,
    lead_time_text: "受注確定後 5〜10営業日で発送",
    image_url: "/shop/wetsuit.png",
  },
  {
    id: "p-wax",
    name: "サーフワックス",
    description: "水温に合わせて選べるベーシックワックス。",
    category: "accessory",
    price: 700,
    shipping_fee: 300,
    lead_time_text: "受注確定後 3〜7営業日で発送",
    image_url: "/shop/wax.png",
  },
  {
    id: "p-leash",
    name: "リーシュコード",
    description: "安全に欠かせない基本アイテム。ボードサイズに合わせて。",
    category: "accessory",
    price: 4500,
    shipping_fee: 500,
    lead_time_text: "受注確定後 3〜7営業日で発送",
    image_url: "/shop/leash.png",
  },
  {
    id: "p-deckpad",
    name: "デッキパッド",
    description: "後ろ足のグリップ力を高める。ショート・ファン向け。",
    category: "accessory",
    price: 5200,
    shipping_fee: 500,
    lead_time_text: "受注確定後 3〜7営業日で発送",
    image_url: "/shop/deckpad.png",
  },
];

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
