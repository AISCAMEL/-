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
