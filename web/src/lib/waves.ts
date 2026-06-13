// ============================================================
// 波情報（Open-Meteo / 無料・APIキー不要）
// 客観データ（API）＋ 主観（ローカル投稿）の二段構成のうち「客観」側。
// 岩沢海岸（福島県双葉郡広野町）の座標を固定で使用。
// ============================================================

const IWASAWA = { lat: 37.215, lon: 141.01 };
const TZ = "Asia/Tokyo";

export type WaveNow = {
  time: string;
  waveHeight: number | null; // m
  wavePeriod: number | null; // s
  waveDirection: number | null; // deg
  windSpeed: number | null; // km/h
  windDirection: number | null; // deg
  temperature: number | null; // ℃
};

export type WaveDay = {
  date: string;
  waveHeightMax: number | null;
  tempMax: number | null;
  tempMin: number | null;
};

export type WaveReport = {
  now: WaveNow;
  days: WaveDay[];
  fetchedAt: string;
};

const COMPASS = [
  "北", "北北東", "北東", "東北東", "東", "東南東", "南東", "南南東",
  "南", "南南西", "南西", "西南西", "西", "西北西", "北西", "北北西",
];

/** 方角（度）を16方位の日本語に */
export function compass(deg: number | null): string {
  if (deg == null) return "—";
  return COMPASS[Math.round(deg / 22.5) % 16];
}

/** 波の高さ（m）をサーファー表現に */
export function waveSizeLabel(h: number | null): string {
  if (h == null) return "—";
  if (h < 0.3) return "ほぼフラット";
  if (h < 0.6) return "スネ〜ヒザ";
  if (h < 1.0) return "コシ〜ハラ";
  if (h < 1.5) return "ハラ〜ムネ";
  if (h < 2.0) return "ムネ〜カタ";
  return "アタマ以上";
}

/** 風の強さ（km/h）を言葉に */
export function windLabel(speed: number | null): string {
  if (speed == null) return "—";
  if (speed < 12) return "弱い";
  if (speed < 25) return "ややあり";
  return "強い";
}

/** 今日入れる？のゆるい総合コメント */
export function vibe(now: WaveNow): { tone: "good" | "ok" | "flat"; text: string } {
  const h = now.waveHeight ?? 0;
  const wind = now.windSpeed ?? 0;
  if (h < 0.3) return { tone: "flat", text: "今日はほぼフラット。のんびり海を眺める日。" };
  if (wind >= 25) return { tone: "ok", text: "サイズはあるけど風強め。上級者向きかも。" };
  if (h >= 0.6 && h <= 1.5 && wind < 12) return { tone: "good", text: "ちょうどいいサイズ＆風弱め。狙い目です🌊" };
  if (h >= 0.4) return { tone: "ok", text: "入れるサイズ。コンディションは現地で確認を。" };
  return { tone: "flat", text: "小さめ。初心者の練習にはよいかも。" };
}

function pick<T>(arr: T[] | undefined, i = 0): T | null {
  return arr && arr.length > i ? arr[i] : null;
}

/**
 * 波情報を取得。失敗時（ネットワーク制限・APIダウン等）は null を返し、
 * UI 側はローカルの声などにフォールバックする。
 * 30分キャッシュ。
 */
export async function getWaveReport(): Promise<WaveReport | null> {
  const marineUrl =
    `https://marine-api.open-meteo.com/v1/marine?latitude=${IWASAWA.lat}` +
    `&longitude=${IWASAWA.lon}` +
    `&current=wave_height,wave_period,wave_direction` +
    `&daily=wave_height_max&timezone=${encodeURIComponent(TZ)}&forecast_days=3`;

  const weatherUrl =
    `https://api.open-meteo.com/v1/forecast?latitude=${IWASAWA.lat}` +
    `&longitude=${IWASAWA.lon}` +
    `&current=temperature_2m,wind_speed_10m,wind_direction_10m` +
    `&daily=temperature_2m_max,temperature_2m_min&timezone=${encodeURIComponent(TZ)}&forecast_days=3`;

  try {
    const [marineRes, weatherRes] = await Promise.all([
      fetch(marineUrl, { next: { revalidate: 1800 } }),
      fetch(weatherUrl, { next: { revalidate: 1800 } }),
    ]);
    if (!marineRes.ok || !weatherRes.ok) return null;

    const marine = await marineRes.json();
    const weather = await weatherRes.json();

    const now: WaveNow = {
      time: marine?.current?.time ?? weather?.current?.time ?? "",
      waveHeight: marine?.current?.wave_height ?? null,
      wavePeriod: marine?.current?.wave_period ?? null,
      waveDirection: marine?.current?.wave_direction ?? null,
      windSpeed: weather?.current?.wind_speed_10m ?? null,
      windDirection: weather?.current?.wind_direction_10m ?? null,
      temperature: weather?.current?.temperature_2m ?? null,
    };

    const dates: string[] = marine?.daily?.time ?? weather?.daily?.time ?? [];
    const days: WaveDay[] = dates.map((date, i) => ({
      date,
      waveHeightMax: pick(marine?.daily?.wave_height_max, i),
      tempMax: pick(weather?.daily?.temperature_2m_max, i),
      tempMin: pick(weather?.daily?.temperature_2m_min, i),
    }));

    return { now, days, fetchedAt: new Date().toISOString() };
  } catch {
    return null;
  }
}
