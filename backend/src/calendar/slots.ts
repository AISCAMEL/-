// 予約枠の計算（純粋ロジック・テスト容易）。
// 日本向けのため営業時間は JST(UTC+9) のウォールクロックとして解釈する。
const JST_OFFSET_MIN = 9 * 60;
const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

export interface Interval { start: Date; end: Date; }
export interface Slot { start: string; end: string; } // ISO

/** YYYY-MM-DD と "HH:MM"(JST) から UTC の Date を作る。 */
export function jstWallToDate(dateYmd: string, hhmm: string): Date {
  const [y, m, d] = dateYmd.split('-').map(Number);
  const [hh, mm] = hhmm.split(':').map(Number);
  // JST のウォールクロック → UTC instant（UTC = JST - 9h）
  return new Date(Date.UTC(y, m - 1, d, hh, mm) - JST_OFFSET_MIN * 60_000);
}

/** 日付(YYYY-MM-DD)の JST 曜日キー（sun..sat）。 */
export function jstWeekdayKey(dateYmd: string): string {
  const [y, m, d] = dateYmd.split('-').map(Number);
  // 正午JSTを基準に曜日を判定（DST無しの日本では安全）
  const dt = new Date(Date.UTC(y, m - 1, d, 12 - 9, 0));
  return WEEKDAYS[dt.getUTCDay()];
}

export interface BusinessHours { [day: string]: [string, string][]; }
export interface HolidaySettings { weekly?: string[]; dates?: string[]; }

/** その日が休業日かどうか。 */
export function isHoliday(dateYmd: string, holiday: HolidaySettings | null | undefined): boolean {
  if (!holiday) return false;
  if (holiday.dates?.includes(dateYmd)) return true;
  if (holiday.weekly?.includes(jstWeekdayKey(dateYmd))) return true;
  return false;
}

function overlaps(aStart: Date, aEnd: Date, bStart: Date, bEnd: Date): boolean {
  return aStart < bEnd && bStart < aEnd;
}

/** 区間 [start,end) が busy のいずれかと重なるか。 */
export function hasConflict(start: Date, end: Date, busy: Interval[]): boolean {
  return busy.some((b) => overlaps(start, end, b.start, b.end));
}

/**
 * 指定日の空き枠を返す。営業時間から busy（既存予約＋カレンダー予定）を除外する。
 * @param stepMin 枠の刻み（既定 = duration）
 */
export function availableSlots(
  dateYmd: string,
  hours: BusinessHours | null | undefined,
  holiday: HolidaySettings | null | undefined,
  busy: Interval[],
  durationMin: number,
  opts: { stepMin?: number; now?: Date } = {},
): Slot[] {
  if (isHoliday(dateYmd, holiday)) return [];
  const day = jstWeekdayKey(dateYmd);
  const ranges = hours?.[day] ?? [];
  const step = opts.stepMin ?? durationMin;
  const now = opts.now ?? new Date();
  const out: Slot[] = [];
  for (const [open, close] of ranges) {
    const openD = jstWallToDate(dateYmd, open);
    const closeD = jstWallToDate(dateYmd, close);
    for (let t = openD.getTime(); t + durationMin * 60_000 <= closeD.getTime() + 1; t += step * 60_000) {
      const s = new Date(t);
      const e = new Date(t + durationMin * 60_000);
      if (s <= now) continue;                 // 過去・直近は出さない
      if (hasConflict(s, e, busy)) continue;  // 重複は除外
      out.push({ start: s.toISOString(), end: e.toISOString() });
    }
  }
  return out;
}
