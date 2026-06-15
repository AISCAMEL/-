import { chatJson, llmEnabled } from './llm.js';
import { buildSystemPrompt } from './prompts.js';
import type { AiTurnResult, ExtractedFields, TenantContext } from '../types.js';

const EMPTY: ExtractedFields = {
  customer_name: null, company_name: null, requested_datetime: null,
  request_detail: null, callback_requested: false, callback_number_confirmed: false,
};

export interface TestTurn { role: 'customer' | 'ai'; content: string; }

/**
 * 管理画面の「AI応対テスト」用。テナント設定＋FAQに基づき、1ターンのAI応答を返す。
 * 通話と違い状態を保存せず、毎回フロントから履歴を受け取る（ステートレス）。
 */
export async function tenantTestReply(
  ctx: TenantContext, history: TestTurn[], message: string,
): Promise<{ reply: string; intent: string; should_transfer: boolean; should_end_call: boolean }> {
  if (llmEnabled) {
    const messages = [
      { role: 'system' as const, content: buildSystemPrompt(ctx) },
      ...history.map((t) => ({
        role: (t.role === 'customer' ? 'user' : 'assistant') as 'user' | 'assistant',
        content: t.content,
      })),
      { role: 'user' as const, content: message },
    ];
    const raw = await chatJson<Partial<AiTurnResult>>(messages);
    if (raw) {
      return {
        reply: raw.reply?.trim() || 'もう一度お願いできますか？',
        intent: raw.intent ?? 'other',
        should_transfer: Boolean(raw.should_transfer),
        should_end_call: Boolean(raw.should_end_call),
      };
    }
  }
  return fallback(ctx, message);
}

// LLM未設定(デモ)時の簡易応答。FAQに一致すればFAQ回答、転送語なら転送。
function fallback(ctx: TenantContext, message: string): { reply: string; intent: string; should_transfer: boolean; should_end_call: boolean } {
  const wantsHuman = /担当|責任者|オペレーター|人に|人と/.test(message);
  if (wantsHuman) {
    return ctx.humanTransferEnabled && ctx.transferPhoneNumber
      ? { reply: '承知しました。担当者におつなぎします。少々お待ちください。', intent: 'transfer', should_transfer: true, should_end_call: false }
      : { reply: ctx.fallbackMessage ?? '申し訳ありません。担当者より折り返しご連絡いたします。', intent: 'callback', should_transfer: false, should_end_call: false };
  }
  // FAQ簡易マッチ（質問文・キーワードの部分一致）
  const hit = ctx.faqs.find((f) => {
    const q = f.question ?? '';
    return q && (message.includes(q.slice(0, 4)) || q.split(/\s|　/).some((w) => w.length >= 2 && message.includes(w)));
  });
  if (hit) return { reply: hit.answer, intent: 'inquiry', should_transfer: false, should_end_call: false };

  if (/予約|取りたい|空いて/.test(message)) {
    return { reply: 'ご予約希望ですね。ご希望の日にちとお時間を教えてください。', intent: 'reservation', should_transfer: false, should_end_call: false };
  }
  if (/折り返し|かけ直し|あとで/.test(message)) {
    return { reply: '折り返しのご希望ですね。お名前とご用件を伺えますか？', intent: 'callback', should_transfer: false, should_end_call: false };
  }
  return {
    reply: '申し訳ありません。こちらで確認できる情報には詳細がありません。担当者より確認してご案内する形でもよろしいでしょうか？（デモ応答：OpenAIキー接続で自然な会話になります）',
    intent: 'other', should_transfer: false, should_end_call: false,
  };
}

// 未使用だが将来の拡張用に空のextractedを公開
export const emptyExtracted = EMPTY;
