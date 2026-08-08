// 予約オーケストレーション：内部予約(買取スケジュール) ＋ Googleカレンダー(スタッフ予定) を
// 掛け合わせて空き枠を出し、重複しないように予約する。
import { getSettings } from '../db/queries.js';
import { availableSlots, hasConflict, type Interval, type Slot } from './slots.js';
import { googleBusy, googleConnFromSettings, googleCreateEvent, googleDeleteEvent } from './google.js';
import { createAppointment, getAppointment, listAppointments, updateAppointment, type AppointmentInput } from './repo.js';

const DEFAULT_DURATION = 45;

function durationOf(settings: any): number {
  const d = Number(settings?.appointment_duration_min);
  return Number.isFinite(d) && d > 0 ? d : DEFAULT_DURATION;
}

/** 内部予約(cancelled以外) を busy 区間に変換。 */
async function internalBusy(tenantId: string, fromIso: string, toIso: string): Promise<Interval[]> {
  const rows = await listAppointments(tenantId, fromIso, toIso, false);
  return rows.map((a: any) => ({ start: new Date(a.start_at), end: new Date(a.end_at) }));
}

/** 内部予約＋Googleカレンダーの両方を合わせた busy を返す。 */
export async function combinedBusy(tenantId: string, fromIso: string, toIso: string): Promise<Interval[]> {
  const settings = await getSettings(tenantId);
  const internal = await internalBusy(tenantId, fromIso, toIso);
  const conn = googleConnFromSettings(settings);
  const gbusy = conn ? await googleBusy(conn, fromIso, toIso) : [];
  return [...internal, ...gbusy];
}

export async function calendarStatus(tenantId: string) {
  const settings = await getSettings(tenantId);
  const conn = googleConnFromSettings(settings);
  return {
    google_connected: Boolean(conn),
    calendar_id: settings?.google_calendar_id ?? '',
    appointment_duration_min: durationOf(settings),
  };
}

/** 指定日(YYYY-MM-DD, JST)の空き枠を返す。内部予約＋Google予定を除外。 */
export async function findSlots(tenantId: string, dateYmd: string, durationMin?: number): Promise<Slot[]> {
  const settings = await getSettings(tenantId);
  const duration = durationMin ?? durationOf(settings);
  // その日の JST 00:00〜翌00:00 を UTC で囲って busy を取得
  const dayStart = new Date(`${dateYmd}T00:00:00+09:00`).toISOString();
  const dayEnd = new Date(new Date(`${dateYmd}T00:00:00+09:00`).getTime() + 24 * 3600_000).toISOString();
  const busy = await combinedBusy(tenantId, dayStart, dayEnd);
  return availableSlots(dateYmd, settings?.business_hours, settings?.holiday_settings, busy, duration);
}

export interface BookResult {
  ok: boolean;
  conflict?: boolean;
  appointment?: any;
  google_synced?: boolean;
  meet_url?: string | null;
  error?: string;
}

/**
 * 予約を取る。重複していたら ok:false, conflict:true を返す（ダブルブッキング防止）。
 * Google接続時はイベントも作成する（失敗しても内部予約は確定）。
 */
export async function book(tenantId: string, input: AppointmentInput): Promise<BookResult> {
  const start = new Date(input.start_at), end = new Date(input.end_at);
  if (!(start < end)) return { ok: false, error: '開始・終了時刻が不正です' };
  // 重複チェック（内部＋Google）
  const busy = await combinedBusy(tenantId, input.start_at, input.end_at);
  if (hasConflict(start, end, busy)) return { ok: false, conflict: true, error: 'その時間帯は既に予約・予定が入っています' };

  const settings = await getSettings(tenantId);
  const conn = googleConnFromSettings(settings);
  // 「オンライン」を含むタイプはビデオ査定 → Google Meet を自動発行
  const wantsMeet = /オンライン|online|ビデオ|web|ウェブ/i.test(input.type ?? '');
  let googleEventId: string | null = null;
  let meetUrl: string | null = null;
  if (conn) {
    const created = await googleCreateEvent(conn, {
      summary: input.title || `${input.type ?? '査定'}：${input.customer_name ?? ''}`.trim(),
      description: [input.note, input.phone_number].filter(Boolean).join('\n'),
      startIso: input.start_at, endIso: input.end_at, withMeet: wantsMeet,
    });
    if (created) { googleEventId = created.eventId; meetUrl = created.meetUrl; }
  }
  const appointment = await createAppointment(tenantId, { ...input, google_event_id: googleEventId, meet_url: meetUrl });
  return { ok: true, appointment, google_synced: Boolean(googleEventId), meet_url: meetUrl };
}

export interface AutoBookOpts {
  type?: string; customer_name?: string | null; phone_number?: string | null; note?: string | null;
  contact_id?: string | null; call_id?: string | null; source?: string;
  date?: string;          // YYYY-MM-DD 希望日（無ければ最短の空き）
  preferredHHMM?: string; // "14:00" 希望時刻（近い枠を優先）
  withinDays?: number;    // 希望日に空きが無い場合、何日先まで探すか（既定14）
  durationMin?: number;
  status?: string;        // 既定 tentative（仮予約）
}

/**
 * AIが通話中に使う自動予約。希望日（または最短）の空き枠を探して仮予約する。
 * 空きが無ければ候補を返す（予約はしない）。オンライン査定など出張不要でも同じ枠管理でOK。
 */
export async function autoBook(tenantId: string, opts: AutoBookOpts): Promise<BookResult & { slot?: Slot; alternatives?: Slot[] }> {
  const settings = await getSettings(tenantId);
  const duration = opts.durationMin ?? durationOf(settings);
  const within = opts.withinDays ?? 14;
  // 起点日（希望日 or 明日）から順に、空き枠が見つかる日まで探す
  const startDate = opts.date ? new Date(`${opts.date}T00:00:00+09:00`) : new Date(Date.now() + 24 * 3600_000);
  let chosen: Slot | undefined;
  let firstDaySlots: Slot[] = [];
  for (let i = 0; i <= within && !chosen; i++) {
    const d = new Date(startDate.getTime() + i * 24 * 3600_000);
    const ymd = d.toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); // YYYY-MM-DD(JST)
    const slots = await findSlots(tenantId, ymd, duration);
    if (i === 0) firstDaySlots = slots;
    if (slots.length === 0) continue;
    // 希望時刻に最も近い枠を選ぶ
    if (opts.preferredHHMM && i === 0) {
      const target = new Date(`${ymd}T${opts.preferredHHMM}:00+09:00`).getTime();
      chosen = slots.slice().sort((a, b) => Math.abs(new Date(a.start).getTime() - target) - Math.abs(new Date(b.start).getTime() - target))[0];
    } else {
      chosen = slots[0];
    }
  }
  if (!chosen) return { ok: false, error: '空き枠が見つかりませんでした', alternatives: firstDaySlots };
  const r = await book(tenantId, {
    type: opts.type ?? '査定', title: `${opts.type ?? '査定'}：${opts.customer_name ?? ''}`.trim(),
    customer_name: opts.customer_name, phone_number: opts.phone_number, note: opts.note,
    contact_id: opts.contact_id, call_id: opts.call_id, source: opts.source ?? 'ai_inbound',
    status: opts.status ?? 'tentative', start_at: chosen.start, end_at: chosen.end,
  });
  return { ...r, slot: chosen };
}

/** 予約のステータス変更（confirmed/cancelled/done）。キャンセル時はGoogleイベントも削除。 */
export async function changeStatus(tenantId: string, id: string, status: string): Promise<any | null> {
  const appt = await getAppointment(tenantId, id);
  if (!appt) return null;
  if (status === 'cancelled' && appt.google_event_id) {
    const settings = await getSettings(tenantId);
    const conn = googleConnFromSettings(settings);
    if (conn) await googleDeleteEvent(conn, appt.google_event_id);
  }
  return updateAppointment(tenantId, id, { status });
}

export { listAppointments, getAppointment };
