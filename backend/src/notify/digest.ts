import { buildWeeklyDigest, getSettings, listDigestTargets } from '../db/queries.js';
import { sendEmail, CATEGORY_LABEL } from './email.js';

/** 指定テナントの週次サマリーをメール送信する（手動・自動の共通処理）。 */
export async function sendWeeklyDigest(tenantId: string): Promise<{ ok: boolean; destination: string; summary: any; error?: string }> {
  const d = await buildWeeklyDigest(tenantId);
  const settings = await getSettings(tenantId);
  const dest = settings?.notification_email ?? 'owner@example.com';
  const breakdown = Object.entries(d.byCategory)
    .map(([k, n]) => `  ・${CATEGORY_LABEL[k as keyof typeof CATEGORY_LABEL] ?? k}: ${n}件`).join('\n') || '  （なし）';
  const body = [
    '直近7日間の電話受付サマリーです。',
    '',
    `■ 総着信数: ${d.total}件`,
    `■ 折り返し希望: ${d.callbacks}件`,
    `■ 担当者転送: ${d.transfers}件`,
    `■ 未対応: ${d.unhandled}件`,
    '',
    '■ 要件の内訳',
    breakdown,
    '',
    '管理画面で詳細を確認できます。',
  ].join('\n');
  const result = await sendEmail(dest, '【AIオペレーター24】今週の電話サマリー', body);
  return { ok: result.ok, destination: dest, summary: d, error: result.error };
}

// ISO週キー（年-週番号）。同じ週に二重送信しないための識別子。
function isoWeekKey(d = new Date()): string {
  const date = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
  const dayNum = (date.getUTCDay() + 6) % 7;
  date.setUTCDate(date.getUTCDate() - dayNum + 3);
  const firstThursday = new Date(Date.UTC(date.getUTCFullYear(), 0, 4));
  const week = 1 + Math.round(((date.getTime() - firstThursday.getTime()) / 86400000 - 3 + ((firstThursday.getUTCDay() + 6) % 7)) / 7);
  return `${date.getUTCFullYear()}-W${week}`;
}

const sentWeeks = new Set<string>(); // インメモリ重複防止（プロセス内）

/**
 * 全対象テナントへ週次サマリーを送る。worker から定期呼び出し。
 * すでに今週送ったテナントはスキップ。
 */
export async function runWeeklyDigests(log?: (m: string) => void): Promise<number> {
  const week = isoWeekKey();
  const targets = await listDigestTargets();
  let sent = 0;
  for (const t of targets) {
    const key = `${t.tenantId}:${week}`;
    if (sentWeeks.has(key)) continue;
    const r = await sendWeeklyDigest(t.tenantId).catch((e) => ({ ok: false, error: String(e), destination: '', summary: null }));
    if (r.ok) { sentWeeks.add(key); sent++; log?.(`weekly digest sent to ${r.destination}`); }
  }
  return sent;
}
