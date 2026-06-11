import type { CallSummary } from '../types.js';
import { CATEGORY_LABEL } from './email.js';

export interface SlackNotificationInput {
  fromNumber: string;
  summary: CallSummary;
  statusLabel: string;
}

/** Slack Incoming Webhook に通話結果を通知する。 */
export async function sendSlackNotification(
  webhookUrl: string,
  input: SlackNotificationInput,
): Promise<{ ok: boolean; error?: string }> {
  if (!webhookUrl) return { ok: false, error: 'no webhook url' };

  const payload = { blocks: buildBlocks(input), text: '新しい電話受付がありました' };

  try {
    const res = await fetch(webhookUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (!res.ok) return { ok: false, error: `Slack ${res.status}: ${await res.text()}` };
    return { ok: true };
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}

function buildBlocks({ fromNumber, summary, statusLabel }: SlackNotificationInput) {
  const v = (x: string | null) => x ?? '—';
  const fields = [
    ['顧客名', v(summary.customer_name)],
    ['会社名', v(summary.company_name)],
    ['電話番号', fromNumber || '—'],
    ['要件', CATEGORY_LABEL[summary.category] ?? 'その他'],
    ['希望日時', v(summary.requested_datetime)],
    ['対応結果', statusLabel],
  ];
  return [
    { type: 'header', text: { type: 'plain_text', text: '📞 新しい電話受付がありました', emoji: true } },
    {
      type: 'section',
      fields: fields.map(([k, val]) => ({ type: 'mrkdwn', text: `*${k}*\n${val}` })),
    },
    { type: 'section', text: { type: 'mrkdwn', text: `*通話要約*\n${summary.summary || '—'}` } },
    ...(summary.next_action
      ? [{ type: 'context', elements: [{ type: 'mrkdwn', text: `🔸 次の対応: ${summary.next_action}` }] }]
      : []),
  ];
}
