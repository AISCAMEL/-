import { config } from '../config.js';

/** SMS送信（Twilio）。未設定時はコンソール出力（デモ用ドライラン）。 */
export async function sendSms(to: string, body: string): Promise<{ ok: boolean; error?: string; dryRun?: boolean }> {
  const { accountSid, authToken, smsFrom } = config.twilio;
  if (!accountSid || !authToken || !smsFrom) {
    console.log(`[sms] (dry-run) to=${to}\n${body}\n---`);
    return { ok: true, dryRun: true };
  }
  try {
    const params = new URLSearchParams({ To: to, Body: body });
    // Messaging Service SID(MG...) なら MessagingServiceSid、電話番号なら From。
    if (smsFrom.startsWith('MG')) params.set('MessagingServiceSid', smsFrom);
    else params.set('From', smsFrom);
    const res = await fetch(`https://api.twilio.com/2010-04-01/Accounts/${accountSid}/Messages.json`, {
      method: 'POST',
      headers: {
        Authorization: 'Basic ' + Buffer.from(`${accountSid}:${authToken}`).toString('base64'),
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: params,
    });
    if (!res.ok) return { ok: false, error: `Twilio ${res.status}: ${await res.text()}` };
    return { ok: true };
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}
