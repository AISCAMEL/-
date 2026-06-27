// Googleカレンダー連携。テナントごとの refresh_token と組み合わせて使う。
// 未接続(クレデンシャル/トークン無し)の場合は connected=false を返し、内部予約のみで重複判定する。
import { config } from '../config.js';
import type { Interval } from './slots.js';

export interface GoogleConn { calendarId: string; refreshToken: string; }

/** テナント設定から Google 接続情報を取り出す（無ければ null）。 */
export function googleConnFromSettings(settings: any): GoogleConn | null {
  const calendarId = settings?.google_calendar_id;
  const refreshToken = settings?.google_refresh_token;
  if (!config.google.clientId || !config.google.clientSecret) return null;
  if (!calendarId || !refreshToken) return null;
  return { calendarId, refreshToken };
}

/** refresh_token をアクセストークンに交換する。失敗時 null。 */
async function getAccessToken(refreshToken: string): Promise<string | null> {
  try {
    const res = await fetch('https://oauth2.googleapis.com/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        client_id: config.google.clientId,
        client_secret: config.google.clientSecret,
        refresh_token: refreshToken,
        grant_type: 'refresh_token',
      }),
    });
    if (!res.ok) { console.error('[google] token refresh failed', res.status, await res.text()); return null; }
    const data = (await res.json()) as any;
    return data.access_token ?? null;
  } catch (err) {
    console.error('[google] token error', err);
    return null;
  }
}

/** 指定期間の busy 区間を Google FreeBusy から取得。失敗時は空配列（＝連携分は無視）。 */
export async function googleBusy(conn: GoogleConn, fromIso: string, toIso: string): Promise<Interval[]> {
  const token = await getAccessToken(conn.refreshToken);
  if (!token) return [];
  try {
    const res = await fetch('https://www.googleapis.com/calendar/v3/freeBusy', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ timeMin: fromIso, timeMax: toIso, items: [{ id: conn.calendarId }] }),
    });
    if (!res.ok) { console.error('[google] freebusy failed', res.status); return []; }
    const data = (await res.json()) as any;
    const busy = data.calendars?.[conn.calendarId]?.busy ?? [];
    return busy.map((b: any) => ({ start: new Date(b.start), end: new Date(b.end) }));
  } catch (err) {
    console.error('[google] freebusy error', err);
    return [];
  }
}

/** Google カレンダーへイベントを作成。成功時 eventId、失敗時 null。 */
export async function googleCreateEvent(
  conn: GoogleConn,
  ev: { summary: string; description?: string; startIso: string; endIso: string },
): Promise<string | null> {
  const token = await getAccessToken(conn.refreshToken);
  if (!token) return null;
  try {
    const res = await fetch(`https://www.googleapis.com/calendar/v3/calendars/${encodeURIComponent(conn.calendarId)}/events`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        summary: ev.summary,
        description: ev.description ?? '',
        start: { dateTime: ev.startIso, timeZone: 'Asia/Tokyo' },
        end: { dateTime: ev.endIso, timeZone: 'Asia/Tokyo' },
      }),
    });
    if (!res.ok) { console.error('[google] insert failed', res.status, await res.text()); return null; }
    const data = (await res.json()) as any;
    return data.id ?? null;
  } catch (err) {
    console.error('[google] insert error', err);
    return null;
  }
}

/** Google カレンダーのイベントを削除（キャンセル時）。 */
export async function googleDeleteEvent(conn: GoogleConn, eventId: string): Promise<boolean> {
  const token = await getAccessToken(conn.refreshToken);
  if (!token) return false;
  try {
    const res = await fetch(`https://www.googleapis.com/calendar/v3/calendars/${encodeURIComponent(conn.calendarId)}/events/${encodeURIComponent(eventId)}`,
      { method: 'DELETE', headers: { Authorization: `Bearer ${token}` } });
    return res.ok || res.status === 410; // 410 = already gone
  } catch { return false; }
}
