// ============================================================
// 気象庁（JMA）の警報・注意報
// ------------------------------------------------------------
// 2026年の防災気象情報の見直し後のデータ（/bosai/warning/data/r8/{府県予報区}.json）。
// ファイルは「要素グループごとの最新電文」（VPWW55〜61など）の配列で、
// 1件に全市町村ぶんの状態が入る。グループが分かれているため「最新の1件」だけを
// 見ると取りこぼすので、全件を時刻順に重ね、警報種別ごとに新しい状態で上書きする。
// ============================================================

const JMA_BASE = "https://www.jma.go.jp/bosai/warning/data/r8";

/** 予報・警報を見る市町村（気象庁の二次細分区域 class20） */
export type JmaArea = { office: string; area: string; label: string };

/** サーフポイント → 市町村（気象庁 area.json の class20 コード） */
export const SPOT_JMA_AREA: Record<string, JmaArea> = {
  "hama-atsuma": { office: "015000", area: "0158100", label: "厚真町" },
  namiita: { office: "030000", area: "0346100", label: "大槌町" },
  "sendai-shinko": { office: "040000", area: "0410001", label: "仙台市東部" },
  shobutahama: { office: "040000", area: "0440400", label: "七ヶ浜町" },
  torinoumi: { office: "040000", area: "0436100", label: "亘理町" },
  iwasawa: { office: "070000", area: "0754100", label: "広野町" },
  harahama: { office: "070000", area: "0720900", label: "相馬市" },
  yotsukura: { office: "070000", area: "0720400", label: "いわき市" },
  toyoma: { office: "070000", area: "0720400", label: "いわき市" },
  kawarago: { office: "080000", area: "0820200", label: "日立市" },
  ajigaura: { office: "080000", area: "0822100", label: "ひたちなか市" },
  oarai: { office: "080000", area: "0830900", label: "大洗町" },
  otake: { office: "080000", area: "0823400", label: "鉾田市" },
  hasaki: { office: "080000", area: "0823200", label: "神栖市" },
  katakai: { office: "120000", area: "1240300", label: "九十九里町" },
  ichinomiya: { office: "120000", area: "1242100", label: "一宮町" },
  shidashita: { office: "120000", area: "1242100", label: "一宮町" },
  torami: { office: "120000", area: "1242100", label: "一宮町" },
  taito: { office: "120000", area: "1223800", label: "いすみ市" },
  onjuku: { office: "120000", area: "1244300", label: "御宿町" },
  kamogawa: { office: "120000", area: "1222300", label: "鴨川市" },
  chikura: { office: "120000", area: "1223400", label: "南房総市" },
  heisaura: { office: "120000", area: "1220500", label: "館山市" },
  kugenuma: { office: "140000", area: "1420500", label: "藤沢市" },
  tsujido: { office: "140000", area: "1420500", label: "藤沢市" },
  chigasaki: { office: "140000", area: "1420700", label: "茅ヶ崎市" },
  shichirigahama: { office: "140000", area: "1420400", label: "鎌倉市" },
  inamuragasaki: { office: "140000", area: "1420400", label: "鎌倉市" },
  "shirahama-shimoda": { office: "220000", area: "2221900", label: "下田市" },
  tatadohama: { office: "220000", area: "2221900", label: "下田市" },
  shizunami: { office: "220000", area: "2222600", label: "牧之原市" },
  omaezaki: { office: "220000", area: "2222300", label: "御前崎市" },
  irako: { office: "230000", area: "2323100", label: "田原市" },
  akabane: { office: "230000", area: "2323100", label: "田原市" },
  kounohama: { office: "240000", area: "2421500", label: "志摩市" },
  isonoura: { office: "300000", area: "3020100", label: "和歌山市" },
  utsuma: { office: "360000", area: "3638800", label: "海陽町" },
  shishikui: { office: "360000", area: "3638800", label: "海陽町" },
  ikumi: { office: "390000", area: "3930100", label: "東洋町" },
  irino: { office: "390000", area: "3942800", label: "黒潮町" },
  kanegahama: { office: "450000", area: "4520600", label: "日向市" },
  okuragahama: { office: "450000", area: "4520600", label: "日向市" },
  kizakihama: { office: "450000", area: "4520100", label: "宮崎市" },
  aoshima: { office: "450000", area: "4520100", label: "宮崎市" },
  tanegashima: { office: "460100", area: "4650100", label: "中種子町" },
  sunabe: { office: "471000", area: "4732600", label: "北谷町" },
  yunohama: { office: "060000", area: "0620300", label: "鶴岡市" },
  kujiranami: { office: "150000", area: "1520500", label: "柏崎市" },
  hatchohama: { office: "260000", area: "2621200", label: "京丹後市" },
  futamigaura: { office: "400000", area: "4023000", label: "糸島市" },
};

type Elem =
  | "rain" | "landslide" | "tide" | "wind" | "wind_snow" | "snow" | "wave"
  | "thunder" | "snow_melting" | "fog" | "dry" | "avalanche" | "cold"
  | "frost" | "ice_accretion" | "snow_accretion";

/** 気象庁の警報・注意報コード（2026年以降の体系）。level: 20=注意報 30=警報 40=危険警報 50=特別警報 */
const KINDS: Record<string, { name: string; elem: Elem; level: 20 | 30 | 40 | 50 }> = {
  "33": { name: "レベル5大雨特別警報", elem: "rain", level: 50 },
  "43": { name: "レベル4大雨危険警報", elem: "rain", level: 40 },
  "03": { name: "レベル3大雨警報", elem: "rain", level: 30 },
  "10": { name: "レベル2大雨注意報", elem: "rain", level: 20 },
  "39": { name: "レベル5土砂災害特別警報", elem: "landslide", level: 50 },
  "49": { name: "レベル4土砂災害危険警報", elem: "landslide", level: 40 },
  "09": { name: "レベル3土砂災害警報", elem: "landslide", level: 30 },
  "29": { name: "レベル2土砂災害注意報", elem: "landslide", level: 20 },
  "38": { name: "レベル5高潮特別警報", elem: "tide", level: 50 },
  "48": { name: "レベル4高潮危険警報", elem: "tide", level: 40 },
  "08": { name: "レベル3高潮警報", elem: "tide", level: 30 },
  "19": { name: "レベル2高潮注意報", elem: "tide", level: 20 },
  "35": { name: "暴風特別警報", elem: "wind", level: 50 },
  "05": { name: "暴風警報", elem: "wind", level: 30 },
  "15": { name: "強風注意報", elem: "wind", level: 20 },
  "32": { name: "暴風雪特別警報", elem: "wind_snow", level: 50 },
  "02": { name: "暴風雪警報", elem: "wind_snow", level: 30 },
  "13": { name: "風雪注意報", elem: "wind_snow", level: 20 },
  "36": { name: "大雪特別警報", elem: "snow", level: 50 },
  "06": { name: "大雪警報", elem: "snow", level: 30 },
  "12": { name: "大雪注意報", elem: "snow", level: 20 },
  "37": { name: "波浪特別警報", elem: "wave", level: 50 },
  "07": { name: "波浪警報", elem: "wave", level: 30 },
  "16": { name: "波浪注意報", elem: "wave", level: 20 },
  "14": { name: "雷注意報", elem: "thunder", level: 20 },
  "17": { name: "融雪注意報", elem: "snow_melting", level: 20 },
  "20": { name: "濃霧注意報", elem: "fog", level: 20 },
  "21": { name: "乾燥注意報", elem: "dry", level: 20 },
  "22": { name: "なだれ注意報", elem: "avalanche", level: 20 },
  "23": { name: "低温注意報", elem: "cold", level: 20 },
  "24": { name: "霜注意報", elem: "frost", level: 20 },
  "25": { name: "着氷注意報", elem: "ice_accretion", level: 20 },
  "26": { name: "着雪注意報", elem: "snow_accretion", level: 20 },
};

/** 海に入る判断に直結する種別 */
const MARINE: ReadonlySet<Elem> = new Set(["wave", "wind", "wind_snow", "tide", "thunder", "fog"]);

const INACTIVE = new Set(["解除", "発表警報・注意報はなし"]);

export type JmaWarning = {
  code: string;
  name: string;
  level: 20 | 30 | 40 | 50;
  marine: boolean;
  status: string; // 発表 / 継続 / 注意報から警報 など
  additions: string[]; // 例: ["うねり"]
};

export type JmaAreaWarnings = {
  area: JmaArea;
  warnings: JmaWarning[]; // 重い順
  headlines: string[];
  reportDatetime: string | null;
};

type RawKind = { code?: string; status: string; additions?: string[] };
type RawReport = {
  reportDatetime: string;
  headlineText?: string;
  warning?: { class20Items?: { areaCode: string; kinds: RawKind[] }[] };
};

async function fetchOffice(office: string): Promise<RawReport[] | null> {
  try {
    const res = await fetch(`${JMA_BASE}/${office}.json`, { next: { revalidate: 600 } });
    if (!res.ok) return null;
    const data = (await res.json()) as unknown;
    return Array.isArray(data) ? (data as RawReport[]) : null;
  } catch {
    return null;
  }
}

function warningsFor(reports: RawReport[], area: JmaArea): JmaAreaWarnings {
  // 古い電文から順に重ね、同じ種別は新しい状態で上書き
  const sorted = reports.slice().sort((a, b) => a.reportDatetime.localeCompare(b.reportDatetime));
  const latest = new Map<string, { kind: RawKind; report: RawReport }>();
  for (const r of sorted) {
    const item = r.warning?.class20Items?.find((a) => a.areaCode === area.area);
    for (const k of item?.kinds ?? []) {
      if (k.code) latest.set(k.code, { kind: k, report: r });
    }
  }
  const warnings: JmaWarning[] = [];
  const headlines = new Set<string>();
  let reportDatetime: string | null = null;
  for (const [code, { kind, report }] of latest) {
    if (INACTIVE.has(kind.status)) continue;
    const def = KINDS[code];
    if (!def) continue;
    warnings.push({
      code,
      name: def.name,
      level: def.level,
      marine: MARINE.has(def.elem),
      status: kind.status,
      additions: kind.additions ?? [],
    });
    if (report.headlineText) headlines.add(report.headlineText);
    if (!reportDatetime || report.reportDatetime > reportDatetime) reportDatetime = report.reportDatetime;
  }
  warnings.sort((a, b) => b.level - a.level || Number(b.marine) - Number(a.marine));
  if (!reportDatetime) {
    reportDatetime = sorted.length ? sorted[sorted.length - 1].reportDatetime : null;
  }
  return { area, warnings, headlines: [...headlines], reportDatetime };
}

/** 複数の市町村の警報・注意報を取得（府県ごとに1回だけ取得）。取得失敗した府県は除外 */
export async function getJmaWarnings(areas: JmaArea[]): Promise<JmaAreaWarnings[] | null> {
  const unique = [...new Map(areas.map((a) => [a.area, a])).values()];
  if (unique.length === 0) return [];
  const offices = [...new Set(unique.map((a) => a.office))];
  const data = new Map<string, RawReport[] | null>(
    await Promise.all(offices.map(async (o) => [o, await fetchOffice(o)] as const)),
  );
  const out = unique.flatMap((a) => {
    const reports = data.get(a.office);
    return reports ? [warningsFor(reports, a)] : [];
  });
  return out.length > 0 ? out : null;
}

export function jmaAreasForSpots(spotIds: string[]): JmaArea[] {
  return spotIds.flatMap((id) => (SPOT_JMA_AREA[id] ? [SPOT_JMA_AREA[id]] : []));
}

/** 気象庁の公式ページ（該当市町村） */
export function jmaWarningUrl(area: JmaArea): string {
  return `https://www.jma.go.jp/bosai/warning/#area_type=class20s&area_code=${area.area}`;
}

/** AI に渡す要約（発表中のみ） */
export function warningsDigest(list: JmaAreaWarnings[] | null) {
  if (!list) return "取得できませんでした";
  const active = list.filter((w) => w.warnings.length > 0);
  if (active.length === 0) return "発表なし";
  return active.map((w) => ({
    市町村: w.area.label,
    発表中: w.warnings.map((x) => x.name + (x.additions.length ? `（${x.additions.join("・")}）` : "")),
    見出し: w.headlines,
  }));
}
