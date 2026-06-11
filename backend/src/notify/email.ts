import { config } from '../config.js';
import type { CallSummary } from '../types.js';

export interface CallNotificationInput {
  fromNumber: string;
  summary: CallSummary;
  statusLabel: string;   // 対応結果（例: AI対応完了 / 折り返し希望 / 担当者転送）
}

const SUBJECT = '【AIオペレーター24】新しい電話受付がありました';

/** 通話終了メールを送る。Resend 未設定時はコンソール出力（デモ用）。 */
export async function sendCallNotification(
  to: string,
  input: CallNotificationInput,
): Promise<{ ok: boolean; error?: string }> {
  const body = renderBody(input);

  if (!config.mail.resendApiKey) {
    console.log(`[notify:email] (dry-run) to=${to}\n件名: ${SUBJECT}\n${body}`);
    return { ok: true };
  }

  try {
    const res = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${config.mail.resendApiKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        from: config.mail.from,
        to: [to],
        subject: SUBJECT,
        text: body,
      }),
    });
    if (!res.ok) {
      const error = `Resend ${res.status}: ${await res.text()}`;
      return { ok: false, error };
    }
    return { ok: true };
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}

// docs/api.md の本文項目に準拠。
function renderBody({ fromNumber, summary, statusLabel }: CallNotificationInput): string {
  const v = (x: string | null) => x ?? '—';
  return [
    `顧客名: ${v(summary.customer_name)}`,
    `会社名: ${v(summary.company_name)}`,
    `電話番号: ${fromNumber || '—'}`,
    `要件: ${categoryLabel(summary.category)}`,
    `内容: ${v(summary.request_detail)}`,
    `希望日時: ${v(summary.requested_datetime)}`,
    `対応結果: ${statusLabel}`,
    `次の対応: ${v(summary.next_action)}`,
    '',
    '― 通話要約 ―',
    summary.summary,
  ].join('\n');
}

export const CATEGORY_LABEL: Record<CallSummary['category'], string> = {
  reservation: '予約', inquiry: '問い合わせ', pricing: '料金問い合わせ',
  callback: '折り返し希望', transfer: '担当者希望', complaint: 'クレーム', other: 'その他',
};

function categoryLabel(c: CallSummary['category']): string {
  return CATEGORY_LABEL[c] ?? 'その他';
}
