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
  let googleEventId: string | null = null;
  if (conn) {
    googleEventId = await googleCreateEvent(conn, {
      summary: input.title || `${input.type ?? '査定'}：${input.customer_name ?? ''}`.trim(),
      description: [input.note, input.phone_number].filter(Boolean).join('\n'),
      startIso: input.start_at, endIso: input.end_at,
    });
  }
  const appointment = await createAppointment(tenantId, { ...input, google_event_id: googleEventId });
  return { ok: true, appointment, google_synced: Boolean(googleEventId) };
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
