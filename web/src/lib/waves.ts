// ============================================================
// 天気予報 × 波 の連携（Open-Meteo / 無料・APIキー不要）
// 客観データ（API）＋ 主観（ローカル投稿）の二段構成のうち「客観」側。
// 岩沢海岸（福島県双葉郡広野町）の座標を固定で使用。
//   - 波: marine-api.open-meteo.com（波高/周期/向き/水温）
//   - 天気: api.open-meteo.com（気温/風/天気/降水/日の出入り）
// ============================================================

const IWASAWA = { lat: 37.215, lon: 141.01 };
const TZ = "Asia/Tokyo";

export type WaveNow = {
  time: string;
  waveHeight: number | null; // m
  wavePeriod: number | null; // s
  waveDirection: number | null; // deg
  waterTemp: number | null; // ℃（海面水温）
  windSpeed: number | null; // km/h
  windDirection: number | null; // deg
  temperature: number | null; // ℃（気温）
  weatherCode: number | null; // WMO
  precipitation: number | null; // mm
};

export type WaveDay = {
  date: string;
  waveHeightMax: number | null;
  waveDirection: number | null;
  tempMax: number | null;
  tempMin: number | null;
  weatherCode: number | null;
  precipProb: number | null; // %
  windMax: number | null; // km/h
  sunrise: string | null;
  sunset: string | null;
};

export type WaveHour = {
  time: string;
  waveHeight: number | null;
};

export type WaveReport = {
  now: WaveNow;
  today: WaveHour[]; // 今日の時間帯別（波高）
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

/** WMO天気コード → 絵文字＋日本語 */
export function weatherLabel(code: number | null): { icon: string; text: string } {
  if (code == null) return { icon: "🌊", text: "—" };
  const map: Record<number, { icon: string; text: string }> = {
    0: { icon: "☀️", text: "快晴" },
    1: { icon: "🌤️", text: "晴れ" },
    2: { icon: "⛅", text: "晴れ時々くもり" },
    3: { icon: "☁️", text: "くもり" },
    45: { icon: "🌫️", text: "霧" },
    48: { icon: "🌫️", text: "霧" },
    51: { icon: "🌦️", text: "霧雨" },
    53: { icon: "🌦️", text: "霧雨" },
    55: { icon: "🌦️", text: "霧雨" },
    61: { icon: "🌧️", text: "小雨" },
    63: { icon: "🌧️", text: "雨" },
    65: { icon: "🌧️", text: "強い雨" },
    71: { icon: "🌨️", text: "小雪" },
    73: { icon: "🌨️", text: "雪" },
    75: { icon: "🌨️", text: "強い雪" },
    80: { icon: "🌦️", text: "にわか雨" },
    81: { icon: "🌦️", text: "にわか雨" },
    82: { icon: "⛈️", text: "激しいにわか雨" },
    85: { icon: "🌨️", text: "にわか雪" },
    86: { icon: "🌨️", text: "にわか雪" },
    95: { icon: "⛈️", text: "雷雨" },
    96: { icon: "⛈️", text: "雷雨（ひょう）" },
    99: { icon: "⛈️", text: "雷雨（ひょう）" },
  };
  return map[code] ?? { icon: "🌊", text: "—" };
}

/** 水温 → ウェットの目安 */
export function wetsuitHint(waterTemp: number | null): string {
  if (waterTemp == null) return "—";
  if (waterTemp >= 24) return "ボードショーツ／タッパー";
  if (waterTemp >= 20) return "シーガル／3mm";
  if (waterTemp >= 16) return "フルスーツ 3/2mm";
  if (waterTemp >= 12) return "フルスーツ 5/3mm＋ブーツ";
  return "セミドライ＋ブーツ・グローブ";
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

/** ISO文字列（Open-Meteoはローカル時刻）から HH:MM を取り出す */
export function hhmm(iso: string | null): string {
  if (!iso) return "—";
  const m = iso.match(/T(\d{2}:\d{2})/);
  return m ? m[1] : "—";
}

/**
 * 天気予報＋波を取得。失敗時（ネットワーク制限・APIダウン等）は null を返し、
 * UI 側はローカルの声などにフォールバックする。30分キャッシュ。
 */
export async function getWaveReport(): Promise<WaveReport | null> {
  const marineUrl =
    `https://marine-api.open-meteo.com/v1/marine?latitude=${IWASAWA.lat}` +
    `&longitude=${IWASAWA.lon}` +
    `&current=wave_height,wave_period,wave_direction,sea_surface_temperature` +
    `&hourly=wave_height` +
    `&daily=wave_height_max,wave_direction_dominant` +
    `&timezone=${encodeURIComponent(TZ)}&forecast_days=4`;

  const weatherUrl =
    `https://api.open-meteo.com/v1/forecast?latitude=${IWASAWA.lat}` +
    `&longitude=${IWASAWA.lon}` +
    `&current=temperature_2m,weather_code,wind_speed_10m,wind_direction_10m,precipitation` +
    `&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,wind_speed_10m_max,sunrise,sunset` +
    `&timezone=${encodeURIComponent(TZ)}&forecast_days=4`;

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
      waterTemp: marine?.current?.sea_surface_temperature ?? null,
      windSpeed: weather?.current?.wind_speed_10m ?? null,
      windDirection: weather?.current?.wind_direction_10m ?? null,
      temperature: weather?.current?.temperature_2m ?? null,
      weatherCode: weather?.current?.weather_code ?? null,
      precipitation: weather?.current?.precipitation ?? null,
    };

    const hTimes: string[] = marine?.hourly?.time ?? [];
    const hWaves: number[] = marine?.hourly?.wave_height ?? [];
    const todayStr = (now.time || hTimes[0] || "").slice(0, 10);
    const today: WaveHour[] = hTimes
      .map((t, i) => ({ time: t, waveHeight: hWaves[i] ?? null }))
      .filter((h) => h.time.startsWith(todayStr));

    const dates: string[] = weather?.daily?.time ?? marine?.daily?.time ?? [];
    const days: WaveDay[] = dates.map((date, i) => ({
      date,
      waveHeightMax: pick(marine?.daily?.wave_height_max, i),
      waveDirection: pick(marine?.daily?.wave_direction_dominant, i),
      tempMax: pick(weather?.daily?.temperature_2m_max, i),
      tempMin: pick(weather?.daily?.temperature_2m_min, i),
      weatherCode: pick(weather?.daily?.weather_code, i),
      precipProb: pick(weather?.daily?.precipitation_probability_max, i),
      windMax: pick(weather?.daily?.wind_speed_10m_max, i),
      sunrise: pick(weather?.daily?.sunrise, i),
      sunset: pick(weather?.daily?.sunset, i),
    }));

    return { now, today, days, fetchedAt: new Date().toISOString() };
  } catch {
    return null;
  }
}
