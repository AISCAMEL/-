// 予約（appointments）のデータアクセス。DB or デモ(インメモリ)。
import { dbEnabled, query } from '../db/index.js';
import { demoAppointments, demoTenant, newId, type DemoAppointment } from '../demo/fixtures.js';

export interface AppointmentInput {
  contact_id?: string | null; call_id?: string | null;
  type?: string; title?: string | null; customer_name?: string | null; phone_number?: string | null;
  start_at: string; end_at: string; status?: string; source?: string;
  google_event_id?: string | null; note?: string | null;
}

/** 期間内の予約（cancelledを除く）を開始時刻順に返す。 */
export async function listAppointments(tenantId: string, fromIso?: string, toIso?: string, includeCancelled = false) {
  if (!dbEnabled) {
    let rows = demoAppointments.filter((a) => a.tenant_id === tenantId || tenantId === demoTenant.id);
    if (fromIso) rows = rows.filter((a) => a.end_at > fromIso);
    if (toIso) rows = rows.filter((a) => a.start_at < toIso);
    if (!includeCancelled) rows = rows.filter((a) => a.status !== 'cancelled');
    return [...rows].sort((a, b) => a.start_at.localeCompare(b.start_at));
  }
  const where: string[] = ['tenant_id = $1']; const params: unknown[] = [tenantId];
  if (fromIso) { params.push(fromIso); where.push(`end_at > $${params.length}`); }
  if (toIso) { params.push(toIso); where.push(`start_at < $${params.length}`); }
  if (!includeCancelled) where.push(`status <> 'cancelled'`);
  return query<any>(`select * from appointments where ${where.join(' and ')} order by start_at asc limit 500`, params);
}

export async function createAppointment(tenantId: string, a: AppointmentInput) {
  if (!dbEnabled) {
    const row: DemoAppointment = {
      id: newId('ap'), tenant_id: tenantId, contact_id: a.contact_id ?? null, call_id: a.call_id ?? null,
      type: a.type ?? '査定', title: a.title ?? null, customer_name: a.customer_name ?? null, phone_number: a.phone_number ?? null,
      start_at: a.start_at, end_at: a.end_at, status: a.status ?? 'confirmed', source: a.source ?? 'manual',
      google_event_id: a.google_event_id ?? null, note: a.note ?? null, created_at: new Date().toISOString(),
    };
    demoAppointments.push(row);
    return row;
  }
  const [row] = await query<any>(
    `insert into appointments (tenant_id, contact_id, call_id, type, title, customer_name, phone_number, start_at, end_at, status, source, google_event_id, note)
     values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13) returning *`,
    [tenantId, a.contact_id ?? null, a.call_id ?? null, a.type ?? '査定', a.title ?? null, a.customer_name ?? null, a.phone_number ?? null,
     a.start_at, a.end_at, a.status ?? 'confirmed', a.source ?? 'manual', a.google_event_id ?? null, a.note ?? null]);
  return row;
}

export async function updateAppointment(tenantId: string, id: string, patch: any) {
  if (!dbEnabled) {
    const a = demoAppointments.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!a) return null;
    for (const k of ['type', 'title', 'customer_name', 'phone_number', 'start_at', 'end_at', 'status', 'note', 'google_event_id']) if (k in patch) (a as any)[k] = patch[k];
    return a;
  }
  const [row] = await query<any>(
    `update appointments set type=coalesce($3,type), title=coalesce($4,title), start_at=coalesce($5,start_at),
        end_at=coalesce($6,end_at), status=coalesce($7,status), note=coalesce($8,note), google_event_id=coalesce($9,google_event_id)
      where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.type ?? null, patch.title ?? null, patch.start_at ?? null, patch.end_at ?? null, patch.status ?? null, patch.note ?? null, patch.google_event_id ?? null]);
  return row ?? null;
}

export async function getAppointment(tenantId: string, id: string) {
  if (!dbEnabled) return demoAppointments.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id)) ?? null;
  const [row] = await query<any>(`select * from appointments where id=$1 and tenant_id=$2`, [id, tenantId]);
  return row ?? null;
}
