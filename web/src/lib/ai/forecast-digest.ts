import {
  getSpotById,
  spotsInArea,
  getAreaById,
  facingOf,
  bottomOf,
  compass8,
  offshoreWindLabel,
  windRelation,
  WIND_RELATION_META,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
  BOTTOM_LABEL,
  CROWD_LABEL,
  SPOT_REGION_LABEL,
} from "@/lib/spots";
import { compass, waveSizeLabel, weatherLabel, type WaveReport } from "@/lib/waves";
import { warningsDigest, type JmaAreaWarnings } from "@/lib/jma";
import { tideDigest, type TideReport } from "@/lib/tide";

/** 波予報・AI解説の対象（ポイント or エリア） */
export type ForecastPlace = {
  id: string;
  kind: "spot" | "area";
  name: string;
  prefecture: string;
  region: string;
  areaName: string | null;
  facingDeg: number;
  goodSwell: string | null;
  season: string | null;
  tips: string | null;
  lat: number;
  lon: number;
  /** 気象庁の警報を見る対象のポイント（エリアなら所属ポイント全部） */
  spotIds: string[];
  profile: Record<string, string>;
};

/** ポイントID または エリアID から対象を引く（ポイント優先） */
export function resolvePlace(id: string): ForecastPlace | null {
  const spot = getSpotById(id);
  if (spot) {
    const area = getAreaById(spot.area);
    return {
      id: spot.id,
      kind: "spot",
      name: spot.name,
      prefecture: spot.prefecture,
      region: SPOT_REGION_LABEL[spot.region],
      areaName: area?.name ?? null,
      facingDeg: facingOf(spot, area),
      goodSwell: area?.goodSwell ?? null,
      season: spot.bestSeason,
      tips: area?.tips ?? null,
      lat: spot.lat,
      lon: spot.lon,
      spotIds: [spot.id],
      profile: {
        タイプ: SPOT_TYPE_LABEL[spot.type],
        レベル: SPOT_LEVEL_LABEL[spot.level],
        海底: BOTTOM_LABEL[bottomOf(spot)],
        混雑: CROWD_LABEL[spot.crowd],
        概要: spot.summary,
      },
    };
  }
  const area = getAreaById(id);
  if (area) {
    return {
      id: area.id,
      kind: "area",
      name: area.name,
      prefecture: area.prefecture,
      region: SPOT_REGION_LABEL[area.region],
      areaName: area.name,
      facingDeg: area.facingDeg,
      goodSwell: area.goodSwell,
      season: area.season,
      tips: area.tips,
      lat: area.lat,
      lon: area.lon,
      spotIds: spotsInArea(area.id).map((s) => s.id),
      profile: { 概要: area.summary },
    };
  }
  return null;
}

const r1 = (n: number | null | undefined) => (n == null ? null : Math.round(n * 10) / 10);
const ms = (kmh: number | null | undefined) => (kmh == null ? null : Math.round((kmh / 3.6) * 10) / 10);

/**
 * AI に渡すための、場所＋予報のコンパクトな要約（JSON化して渡す）。
 * 予報モデルの数値をそのまま、サーファーが読む単位（m・秒・m/s）に揃える。
 */
export function forecastDigest(
  place: ForecastPlace,
  report: WaveReport,
  warnings?: JmaAreaWarnings[] | null,
  tide?: TideReport | null,
) {
  const rel = (deg: number | null) =>
    deg == null ? null : WIND_RELATION_META[windRelation(deg, place.facingDeg)].label;

  const hours = (report.hours ?? [])
    .filter((_, i) => i % 3 === 0)
    .slice(0, 9)
    .map((h) => ({
      時刻: h.time.replace("T", " "),
      波高m: r1(h.waveHeight),
      周期秒: r1(h.wavePeriod),
      うねり向き: h.waveDirection == null ? null : compass(h.waveDirection),
      うねり成分m: r1(h.swellHeight),
      風波成分m: r1(h.windWaveHeight),
      風: h.windDirection == null ? null : compass(h.windDirection),
      風速ms: ms(h.windSpeed),
      突風ms: ms(h.windGust),
      風の当たり方: rel(h.windDirection),
      天気: weatherLabel(h.weatherCode).text,
      降水確率: h.precipProb,
    }));

  return {
    場所: {
      名前: place.name,
      種別: place.kind === "spot" ? "ポイント" : "エリア",
      都道府県: place.prefecture,
      地方: place.region,
      エリア: place.areaName,
      海の向き: `${compass8(place.facingDeg)}向き`,
      良いうねり: place.goodSwell,
      オフショアの風: offshoreWindLabel(place.facingDeg),
      シーズン: place.season,
      エリアのコツ: place.tips,
      ...place.profile,
    },
    現在: {
      観測時刻: report.now.time.replace("T", " "),
      波高m: r1(report.now.waveHeight),
      サイズ: waveSizeLabel(report.now.waveHeight),
      周期秒: r1(report.now.wavePeriod),
      うねり向き: compass(report.now.waveDirection),
      水温: r1(report.now.waterTemp),
      気温: r1(report.now.temperature),
      風: compass(report.now.windDirection),
      風速ms: ms(report.now.windSpeed),
      風の当たり方: rel(report.now.windDirection),
      天気: weatherLabel(report.now.weatherCode).text,
    },
    気象庁の警報注意報: warnings === undefined ? "未確認" : warningsDigest(warnings),
    潮回り: tide === undefined ? "未確認" : tideDigest(tide),
    時間帯別予報_3時間ごと: hours,
    日別予報: report.days.slice(0, 5).map((d) => ({
      日付: d.date,
      最大波高m: r1(d.waveHeightMax),
      うねり向き: compass(d.waveDirection),
      天気: weatherLabel(d.weatherCode).text,
      降水確率: d.precipProb,
      最大風速ms: ms(d.windMax),
      気温: `${r1(d.tempMin) ?? "—"}〜${r1(d.tempMax) ?? "—"}℃`,
      日の出: d.sunrise?.slice(11) ?? null,
      日の入り: d.sunset?.slice(11) ?? null,
    })),
  };
}
