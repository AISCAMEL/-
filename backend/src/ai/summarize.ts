import { chatJson, llmEnabled } from './llm.js';
import { buildSummaryPrompt } from './prompts.js';
import { config } from '../config.js';
import type { CallSummary, TranscriptLine } from '../types.js';

/** 通話ログから要約JSONを生成する。LLM未設定時は安全なデフォルトを返す。 */
export async function summarizeCall(lines: TranscriptLine[]): Promise<CallSummary> {
  const transcriptText = lines
    .map((l) => `${speakerLabel(l.speaker)}: ${l.message}`)
    .join('\n');

  if (llmEnabled) {
    const raw = await chatJson<Partial<CallSummary>>(
      [
        { role: 'system', content: buildSummaryPrompt() },
        { role: 'user', content: `通話ログ:\n${transcriptText}` },
      ],
      config.openai.summaryModel,
    );
    if (raw) return normalizeSummary(raw);
  }
  return fallbackSummary(transcriptText);
}

function speakerLabel(s: TranscriptLine['speaker']): string {
  return s === 'customer' ? 'お客様' : s === 'ai' ? 'AI' : s === 'agent' ? '担当者' : 'システム';
}

function normalizeSummary(raw: Partial<CallSummary>): CallSummary {
  return {
    summary: raw.summary ?? '要約を生成できませんでした。',
    category: raw.category ?? 'other',
    customer_name: raw.customer_name ?? null,
    company_name: raw.company_name ?? null,
    requested_datetime: raw.requested_datetime ?? null,
    request_detail: raw.request_detail ?? null,
    next_action: raw.next_action ?? null,
    urgency: raw.urgency ?? 'normal',
    sentiment: raw.sentiment ?? 'neutral',
    callback_requested: Boolean(raw.callback_requested),
    should_follow_up: Boolean(raw.should_follow_up),
  };
}

function fallbackSummary(transcriptText: string): CallSummary {
  return {
    summary: transcriptText.slice(0, 200) || '通話内容なし',
    category: 'other',
    customer_name: null, company_name: null,
    requested_datetime: null, request_detail: null, next_action: null,
    urgency: 'normal', sentiment: 'neutral',
    callback_requested: false, should_follow_up: true,
  };
}
