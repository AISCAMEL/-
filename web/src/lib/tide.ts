// ============================================================
// 潮回り（潮位・満潮/干潮・潮名）
// ------------------------------------------------------------
// 潮位: 気象庁「潮位表」（天文潮位の推算値）テキスト
//   https://www.data.jma.go.jp/kaiyou/data/db/tide/suisan/txt/{年}/{地点記号}.txt
//   1行=1日（136文字）: 毎時潮位 24×3桁 / 年月日 6桁 / 地点記号 2桁 /
//   満潮 4回×(時刻4桁+潮位3桁) / 干潮 4回×(同)。欠測は 9999・999。
// 潮名（大潮・中潮…）: 月の満ち欠け（旧暦の日付）から計算する一般的な目安。
// ※ 天文潮位は気象・海況による変動（高潮など）を含まない。
// ============================================================

const TIDE_BASE = "https://www.data.jma.go.jp/kaiyou/data/db/tide/suisan/txt";

export type TideStation = { code: string; name: string };

/** サーフポイント → 潮位表の地点（外洋に面した近くの地点を選定） */
export const SPOT_TIDE_STATION: Record<string, TideStation> = {
  "hama-atsuma": { code: "C8", name: "苫小牧東" },
  namiita: { code: "Q6", name: "釜石" },
  "sendai-shinko": { code: "SD", name: "仙台新港" },
  shobutahama: { code: "SG", name: "塩釜" },
  torinoumi: { code: "ZM", name: "相馬" },
  iwasawa: { code: "ON", name: "小名浜" },
  harahama: { code: "ZM", name: "相馬" },
  yotsukura: { code: "ON", name: "小名浜" },
  toyoma: { code: "ON", name: "小名浜" },
  kawarago: { code: "D1", name: "日立" },
  ajigaura: { code: "D3", name: "大洗" },
  oarai: { code: "D3", name: "大洗" },
  otake: { code: "D2", name: "鹿島" },
  hasaki: { code: "CS", name: "銚子漁港" },
  katakai: { code: "CS", name: "銚子漁港" },
  ichinomiya: { code: "ZF", name: "勝浦" },
  shidashita: { code: "ZF", name: "勝浦" },
  torami: { code: "ZF", name: "勝浦" },
  taito: { code: "ZF", name: "勝浦" },
  onjuku: { code: "ZF", name: "勝浦" },
  kamogawa: { code: "ZF", name: "勝浦" },
  chikura: { code: "MR", name: "布良" },
  heisaura: { code: "MR", name: "布良" },
  kugenuma: { code: "D8", name: "湘南港" },
  tsujido: { code: "D8", name: "湘南港" },
  chigasaki: { code: "D8", name: "湘南港" },
  shichirigahama: { code: "D8", name: "湘南港" },
  inamuragasaki: { code: "D8", name: "湘南港" },
  "shirahama-shimoda": { code: "D6", name: "下田" },
  tatadohama: { code: "D6", name: "下田" },
  shizunami: { code: "OM", name: "御前崎" },
  omaezaki: { code: "OM", name: "御前崎" },
  irako: { code: "I4", name: "赤羽根" },
  akabane: { code: "I4", name: "赤羽根" },
  kounohama: { code: "TB", name: "鳥羽" },
  isonoura: { code: "WY", name: "和歌山" },
  utsuma: { code: "L7", name: "甲浦" },
  shishikui: { code: "L7", name: "甲浦" },
  ikumi: { code: "L7", name: "甲浦" },
  irino: { code: "L6", name: "高知下田" },
  kanegahama: { code: "Z6", name: "細島" },
  okuragahama: { code: "Z6", name: "細島" },
  kizakihama: { code: "MG", name: "宮崎" },
  aoshima: { code: "MG", name: "宮崎" },
  tanegashima: { code: "TJ", name: "種子島" },
  sunabe: { code: "NH", name: "那覇" },
  yunohama: { code: "S9", name: "酒田" },
  kujiranami: { code: "ZC", name: "柏崎" },
  hatchohama: { code: "T6", name: "津居山" },
  futamigaura: { code: "QF", name: "博多" },
};

export type TideEvent = { time: string; height: number }; // time "HH:MM"

export type TideDay = {
  date: string; // YYYY-MM-DD
  hourly: number[]; // 0〜23時の潮位（cm, 潮位表基準面）
  highs: TideEvent[];
  lows: TideEvent[];
};

// ---- 日付（日本時間） --------------------------------------------

/** いまの日本時間を { date: "YYYY-MM-DD", minutes: 0〜1439 } で */
export function nowJst(now = new Date()): { date: string; minutes: number } {
  const j = new Date(now.getTime() + 9 * 3600 * 1000);
  const date = j.toISOString().slice(0, 10);
  return { date, minutes: j.getUTCHours() * 60 + j.getUTCMinutes() };
}

function addDays(date: string, n: number): string {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

// ---- 潮位表テキストの解析 ------------------------------------------

function num(s: string): number | null {
  const t = s.trim();
  if (t === "" || /^9+$/.test(t)) return null;
  const n = Number(t);
  return Number.isFinite(n) ? n : null;
}

function events(chunk: string): TideEvent[] {
  const out: TideEvent[] = [];
  for (let i = 0; i < 4; i++) {
    const e = chunk.slice(i * 7, i * 7 + 7);
    const hhmm = e.slice(0, 4);
    if (/^\s*9999$/.test(hhmm) || hhmm.trim() === "") continue;
    const h = num(e.slice(4, 7));
    const t = hhmm.replace(/ /g, "0");
    if (h == null || !/^\d{4}$/.test(t)) continue;
    out.push({ time: `${t.slice(0, 2)}:${t.slice(2)}`, height: h });
  }
  return out;
}

export function parseTideLine(line: string): TideDay | null {
  if (line.length < 136) return null;
  const hourly: number[] = [];
  for (let h = 0; h < 24; h++) {
    const v = num(line.slice(h * 3, h * 3 + 3));
    if (v == null) return null;
    hourly.push(v);
  }
  const yy = line.slice(72, 74).trim();
  const mm = line.slice(74, 76).trim().padStart(2, "0");
  const dd = line.slice(76, 78).trim().padStart(2, "0");
  return {
    date: `20${yy.padStart(2, "0")}-${mm}-${dd}`,
    hourly,
    highs: events(line.slice(80, 108)),
    lows: events(line.slice(108, 136)),
  };
}

async function fetchYear(code: string, year: number): Promise<Map<string, TideDay> | null> {
  try {
    // 年間の推算値なので長めにキャッシュ（1日）
    const res = await fetch(`${TIDE_BASE}/${year}/${code}.txt`, { next: { revalidate: 86400 } });
    if (!res.ok) return null;
    const text = await res.text();
    const map = new Map<string, TideDay>();
    for (const line of text.split(/\r?\n/)) {
      const d = parseTideLine(line);
      if (d) map.set(d.date, d);
    }
    return map.size > 0 ? map : null;
  } catch {
    return null;
  }
}

// ---- 潮名（月齢・旧暦日） ------------------------------------------

/** 新月の時刻（Meeus『Astronomical Algorithms』第49章の近似, 誤差は数分程度） */
function newMoonJde(k: number): number {
  const T = k / 1236.85;
  const rad = Math.PI / 180;
  let jde =
    2451550.09766 + 29.530588861 * k + 0.00015437 * T * T - 0.00000015 * T ** 3 + 0.00000000073 * T ** 4;
  const E = 1 - 0.002516 * T - 0.0000074 * T * T;
  const M = (2.5534 + 29.1053567 * k - 0.0000014 * T * T) * rad;
  const Mp = (201.5643 + 385.81693528 * k + 0.0107582 * T * T) * rad;
  const F = (160.7108 + 390.67050284 * k - 0.0016118 * T * T) * rad;
  const Om = (124.7746 - 1.56375588 * k + 0.0020672 * T * T) * rad;
  jde +=
    -0.4072 * Math.sin(Mp) +
    0.17241 * E * Math.sin(M) +
    0.01608 * Math.sin(2 * Mp) +
    0.01039 * Math.sin(2 * F) +
    0.00739 * E * Math.sin(Mp - M) -
    0.00514 * E * Math.sin(Mp + M) +
    0.00208 * E * E * Math.sin(2 * M) -
    0.00111 * Math.sin(Mp - 2 * F) -
    0.00057 * Math.sin(Mp + 2 * F) +
    0.00056 * E * Math.sin(2 * Mp + M) -
    0.00042 * Math.sin(3 * Mp) +
    0.00042 * E * Math.sin(M + 2 * F) +
    0.00038 * E * Math.sin(M - 2 * F) -
    0.00024 * E * Math.sin(2 * Mp - M) -
    0.00017 * Math.sin(Om);
  return jde;
}

const jdeToMs = (jde: number) => (jde - 2440587.5) * 86400000;

/** その日（日本時間）の旧暦の日付（1〜30）と、正午時点の月齢 */
export function lunarDay(date: string): { day: number; moonAge: number } {
  const noonJst = Date.parse(`${date}T12:00:00+09:00`);
  const endJst = Date.parse(`${date}T00:00:00+09:00`) + 86400000;
  // 直近（その日の終わりまで）の新月を探す
  let k = Math.floor((noonJst / 86400000 + 2440587.5 - 2451550.09766) / 29.530588861) + 1;
  let nm = jdeToMs(newMoonJde(k));
  while (nm >= endJst) {
    k -= 1;
    nm = jdeToMs(newMoonJde(k));
  }
  const nmDateJst = new Date(nm + 9 * 3600 * 1000).toISOString().slice(0, 10);
  const day = Math.round((Date.parse(`${date}T00:00:00Z`) - Date.parse(`${nmDateJst}T00:00:00Z`)) / 86400000) + 1;
  const moonAge = Math.max(0, (noonJst - nm) / 86400000);
  return { day, moonAge: Math.round(moonAge * 10) / 10 };
}

export type TideName = "大潮" | "中潮" | "小潮" | "長潮" | "若潮";

/** 旧暦の日付 → 潮名（一般的な目安の対応表） */
export function tideName(lunar: number): TideName {
  const d = ((lunar - 1) % 30) + 1;
  if (d <= 3 || d === 30) return "大潮";
  if (d <= 6) return "中潮";
  if (d <= 9) return "小潮";
  if (d === 10) return "長潮";
  if (d === 11) return "若潮";
  if (d <= 14) return "中潮";
  if (d <= 17) return "大潮";
  if (d <= 20) return "中潮";
  if (d <= 23) return "小潮";
  if (d === 24) return "長潮";
  if (d === 25) return "若潮";
  return "中潮";
}

export const TIDE_NAME_NOTE: Record<TideName, string> = {
  大潮: "干満の差が大きい。潮の動きが速く、干潮時は浅くなりやすい",
  中潮: "干満の差はふつう。潮がよく動く",
  小潮: "干満の差が小さい。潮位の変化がゆるやか",
  長潮: "干満の差がいちばん小さく、潮があまり動かない",
  若潮: "小潮から大潮へ向かい、潮が動き始める",
};

export function moonEmoji(age: number): string {
  const p = age / 29.53;
  const icons = ["🌑", "🌒", "🌓", "🌔", "🌕", "🌖", "🌗", "🌘"];
  return icons[Math.round(p * 8) % 8];
}

// ---- まとめ ------------------------------------------------------

export type TideReport = {
  station: TideStation;
  date: string;
  name: TideName;
  moonAge: number;
  today: TideDay;
  tomorrow: TideDay | null;
  /** いまの潮位（cm, 毎時値の線形補間）と傾向 */
  now: { minutes: number; height: number; trend: "上げ" | "下げ" | "止まり" };
  next: { kind: "満潮" | "干潮"; time: string; height: number; inMinutes: number } | null;
};

function interp(hourly: number[], minutes: number, nextDay: TideDay | null): number {
  const h = Math.floor(minutes / 60);
  const f = (minutes % 60) / 60;
  const a = hourly[h];
  const b = h < 23 ? hourly[h + 1] : (nextDay?.hourly[0] ?? hourly[23]);
  return Math.round(a + (b - a) * f);
}

function toMin(t: string): number {
  return Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
}

export async function getTideReport(spotId: string, now = new Date()): Promise<TideReport | null> {
  const station = SPOT_TIDE_STATION[spotId];
  if (!station) return null;
  const { date, minutes } = nowJst(now);
  const tomorrowDate = addDays(date, 1);
  const year = Number(date.slice(0, 4));
  const thisYear = await fetchYear(station.code, year);
  const nextYear = tomorrowDate.slice(0, 4) !== date.slice(0, 4) ? await fetchYear(station.code, year + 1) : null;
  const today = thisYear?.get(date);
  if (!today) return null;
  const tomorrow = (nextYear ?? thisYear)?.get(tomorrowDate) ?? null;

  const height = interp(today.hourly, minutes, tomorrow);
  const later = interp(today.hourly, Math.min(minutes + 30, 1439), tomorrow);
  const trend = Math.abs(later - height) <= 1 ? "止まり" : later > height ? "上げ" : "下げ";

  const upcoming = [
    ...today.highs.map((e) => ({ kind: "満潮" as const, ...e, at: toMin(e.time) })),
    ...today.lows.map((e) => ({ kind: "干潮" as const, ...e, at: toMin(e.time) })),
    ...(tomorrow?.highs ?? []).map((e) => ({ kind: "満潮" as const, ...e, at: toMin(e.time) + 1440 })),
    ...(tomorrow?.lows ?? []).map((e) => ({ kind: "干潮" as const, ...e, at: toMin(e.time) + 1440 })),
  ]
    .filter((e) => e.at > minutes)
    .sort((a, b) => a.at - b.at);
  const n = upcoming[0];

  const { day, moonAge } = lunarDay(date);
  return {
    station,
    date,
    name: tideName(day),
    moonAge,
    today,
    tomorrow,
    now: { minutes, height, trend },
    next: n ? { kind: n.kind, time: n.time, height: n.height, inMinutes: n.at - minutes } : null,
  };
}

/** AI に渡す要約 */
export function tideDigest(t: TideReport | null) {
  if (!t) return "取得できませんでした";
  return {
    潮名: t.name,
    月齢: t.moonAge,
    観測地点: `${t.station.name}（気象庁 潮位表・天文潮位）`,
    いまの潮位cm: t.now.height,
    いまの傾向: t.now.trend,
    次の満干: t.next ? `${t.next.kind} ${t.next.time}（${t.next.height}cm）` : null,
    今日の満潮: t.today.highs.map((e) => `${e.time} ${e.height}cm`),
    今日の干潮: t.today.lows.map((e) => `${e.time} ${e.height}cm`),
    明日の満潮: t.tomorrow?.highs.map((e) => `${e.time} ${e.height}cm`) ?? [],
    明日の干潮: t.tomorrow?.lows.map((e) => `${e.time} ${e.height}cm`) ?? [],
  };
}
