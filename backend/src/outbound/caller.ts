import { config } from '../config.js';
import { listPhoneNumbers } from '../db/queries.js';
import { getPendingTargets, updateTarget, getCampaign } from './repo.js';

export const twilioCallEnabled = Boolean(config.twilio.accountSid && config.twilio.authToken);

// Twilio REST で1件発信する。発信時のTwiMLは outbound-twiml が返す。
async function placeCall(toNumber: string, fromNumber: string, campaignId: string): Promise<{ ok: boolean; sid?: string; error?: string }> {
  const url = `https://api.twilio.com/2010-04-01/Accounts/${config.twilio.accountSid}/Calls.json`;
  const body = new URLSearchParams({
    To: toNumber,
    From: fromNumber,
    Url: `${config.publicApiBaseUrl}/api/twilio/outbound-twiml?campaign=${encodeURIComponent(campaignId)}`,
    Method: 'POST',
  });
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Authorization: 'Basic ' + Buffer.from(`${config.twilio.accountSid}:${config.twilio.authToken}`).toString('base64'),
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
    });
    const data = await res.json().catch(() => ({})) as any;
    if (!res.ok) return { ok: false, error: `Twilio ${res.status}: ${data.message ?? ''}` };
    return { ok: true, sid: data.sid };
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}

const DEMO_OUTCOMES = ['打合せ希望', '資料送付希望', '興味あり（再連絡）', '不要', '不在'];

/**
 * キャンペーンの pending 対象を発信する。
 * Twilio未接続(デモ)時は発信せず、結果をシミュレートする。
 */
export async function runCampaign(tenantId: string, campaignId: string): Promise<{ placed: number; simulated: boolean; results: any[] }> {
  const targets = await getPendingTargets(tenantId, campaignId);
  const phones = await listPhoneNumbers(tenantId);
  const fromNumber = phones.find((p: any) => p.status === 'active')?.phone_number ?? phones[0]?.phone_number ?? '';

  const results: any[] = [];
  let placed = 0;

  for (let i = 0; i < targets.length; i++) {
    const t = targets[i];
    if (twilioCallEnabled && fromNumber) {
      await updateTarget(tenantId, t.id, { status: 'calling' });
      const r = await placeCall(t.phone_number, fromNumber, campaignId);
      if (r.ok) { placed++; results.push({ id: t.id, phone: t.phone_number, ok: true, sid: r.sid }); }
      else { await updateTarget(tenantId, t.id, { status: 'failed', note: r.error }); results.push({ id: t.id, phone: t.phone_number, ok: false, error: r.error }); }
    } else {
      // デモ：発信せず結果をシミュレート
      const outcome = DEMO_OUTCOMES[i % DEMO_OUTCOMES.length];
      const status = outcome === '不在' ? 'no_answer' : 'done';
      await updateTarget(tenantId, t.id, { status, outcome });
      placed++;
      results.push({ id: t.id, phone: t.phone_number, ok: true, simulated: true, outcome });
    }
  }
  return { placed, simulated: !twilioCallEnabled, results };
}

export { getCampaign };
