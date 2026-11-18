import { chatJson, chatTextStream, chatAgentStream, llmEnabled } from './llm.js';
import { buildSystemPrompt, buildReplyPrompt, buildOutboundSystemPrompt } from './prompts.js';
import { schedulingTools, makeSchedulingRunner } from './schedulingTools.js';
import type { AiTurnResult, ExtractedFields, TenantContext, TranscriptLine } from '../types.js';

const EMPTY_EXTRACTED: ExtractedFields = {
  customer_name: null,
  company_name: null,
  requested_datetime: null,
  request_detail: null,
  callback_requested: false,
  callback_number_confirmed: false,
};

/**
 * 1通話分の会話を保持し、ターンごとに AI 応答を生成する。
 * Twilio Conversation Relay の1セッション = 1 Orchestrator。
 */
export class ConversationOrchestrator {
  private readonly history: TranscriptLine[] = [];
  private extracted: ExtractedFields = { ...EMPTY_EXTRACTED };
  private lastResult: AiTurnResult | null = null;

  // outbound を渡すと「電話をかける側」のプロンプトに切り替わる。
  // runtime にDB上のtenantId/callIdを渡すと、着信時にスケジューリング用ツールを有効化する。
  constructor(
    private readonly ctx: TenantContext,
    private readonly outbound?: { purpose: string; goal: string },
    private readonly runtime?: { tenantId: string; callId: string | null },
  ) {}

  private systemPrompt(): string {
    return this.outbound
      ? buildOutboundSystemPrompt(this.ctx, this.outbound.purpose, this.outbound.goal)
      : buildSystemPrompt(this.ctx);
  }

  // 音声用：返答を平文でストリーミング生成し、合間を最小化する。
  // 分類・抽出はここでは行わず（通話後の要約でまとめて行う）、転送判定だけ軽量に行う。
  private replyPrompt(): string {
    return this.outbound
      ? buildOutboundSystemPrompt(this.ctx, this.outbound.purpose, this.outbound.goal)
      : buildReplyPrompt(this.ctx);
  }

  /**
   * ユーザ発話を受けて、返答をストリーミング生成する（音声の合間を最小化）。
   * onDelta にトークンが届くたびに即発話できる。返り値は確定した返答全文等。
   */
  async handleUserUtteranceStream(
    text: string,
    onDelta: (t: string) => void,
  ): Promise<{ reply: string; should_transfer: boolean; should_end_call: boolean }> {
    this.history.push({ speaker: 'customer', message: text });

    let reply: string | null = null;
    if (llmEnabled && !this.outbound) {
      const messages = [
        { role: 'system' as const, content: this.replyPrompt() },
        ...this.history.map((l) => ({
          role: (l.speaker === 'customer' ? 'user' : 'assistant') as 'user' | 'assistant',
          content: l.message,
        })),
      ];
      // DB上のtenantIdがあれば、空き確認・仮予約ツールを使えるようにする。
      const tid = this.runtime?.tenantId;
      if (tid) {
        const runner = makeSchedulingRunner(tid, this.runtime?.callId ?? null);
        reply = await chatAgentStream(messages, schedulingTools, runner, onDelta);
      } else {
        reply = await chatTextStream(messages, onDelta);
      }
    } else if (llmEnabled && this.outbound) {
      // アウトバウンドは従来のJSON経路（能動発話・判定が必要なため）。
      const r = await this.callLlm();
      if (r) { reply = r.reply; onDelta(r.reply); this.lastResult = r; }
    }

    if (!reply || !reply.trim()) {
      const fb = this.fallback(text);
      reply = fb.reply;
      onDelta(reply);
      this.lastResult = fb;
    }

    this.history.push({ speaker: 'ai', message: reply });
    // 軽量な転送判定（実発話の転送はMVP未実装のため、基本は折り返し運用＝自動切断しない）。
    const wantsHuman = /担当者と|担当の方|人と話|責任者|オペレーター|クレーム/.test(text);
    const canTransfer = Boolean(this.ctx.humanTransferEnabled && this.ctx.transferPhoneNumber);
    const should_transfer = wantsHuman && canTransfer;
    if (!this.lastResult) {
      this.lastResult = {
        reply, intent: 'other', state: 'initial', extracted: this.extracted,
        should_transfer, should_end_call: false, next_action: null, confidence: 0.5,
      };
    } else {
      this.lastResult.should_transfer = this.lastResult.should_transfer || should_transfer;
    }
    return { reply, should_transfer, should_end_call: false };
  }

  get transcript(): TranscriptLine[] {
    return this.history;
  }

  get latest(): AiTurnResult | null {
    return this.lastResult;
  }

  /** ユーザ発話を受けて AI のターン結果を返す。履歴も内部に蓄積する。 */
  async handleUserUtterance(text: string): Promise<AiTurnResult> {
    this.history.push({ speaker: 'customer', message: text });

    const result = (await this.callLlm()) ?? this.fallback(text);

    // 判明済みフィールドは保持しつつ更新（null で上書きしない）。
    this.extracted = mergeExtracted(this.extracted, result.extracted);
    result.extracted = this.extracted;

    this.history.push({ speaker: 'ai', message: result.reply });
    this.lastResult = result;
    return result;
  }

  private async callLlm(): Promise<AiTurnResult | null> {
    if (!llmEnabled) return null;
    const messages = [
      { role: 'system' as const, content: this.systemPrompt() },
      ...this.history.map((l) => ({
        role: (l.speaker === 'customer' ? 'user' : 'assistant') as 'user' | 'assistant',
        content: l.message,
      })),
    ];
    const raw = await chatJson<Partial<AiTurnResult>>(messages);
    return raw ? normalize(raw) : null;
  }

  /** LLM が使えない/失敗時の最低限の応答（デモが止まらないように）。 */
  private fallback(text: string): AiTurnResult {
    const t = text.toLowerCase();
    const wantsHuman = /担当|人|責任者|オペレーター/.test(text);
    if (wantsHuman && this.ctx.humanTransferEnabled && this.ctx.transferPhoneNumber) {
      return {
        reply: '承知しました。担当者におつなぎします。少々お待ちください。',
        intent: 'transfer', state: 'transfer_ready', extracted: this.extracted,
        should_transfer: true, should_end_call: false, next_action: '担当者へ転送', confidence: 0.5,
      };
    }
    return {
      reply: 'ご用件をお伺いします。ご予約、お問い合わせ、折り返しのご希望など、ご用件をお話しください。',
      intent: 'other', state: 'initial', extracted: this.extracted,
      should_transfer: false, should_end_call: false, next_action: null, confidence: 0.2,
    };
  }
}

function mergeExtracted(prev: ExtractedFields, next: ExtractedFields): ExtractedFields {
  return {
    customer_name: next.customer_name ?? prev.customer_name,
    company_name: next.company_name ?? prev.company_name,
    requested_datetime: next.requested_datetime ?? prev.requested_datetime,
    request_detail: next.request_detail ?? prev.request_detail,
    callback_requested: next.callback_requested || prev.callback_requested,
    callback_number_confirmed: next.callback_number_confirmed || prev.callback_number_confirmed,
  };
}

// LLM 出力の欠損を補い、型を安全化する。
function normalize(raw: Partial<AiTurnResult>): AiTurnResult {
  return {
    reply: typeof raw.reply === 'string' && raw.reply.trim()
      ? raw.reply
      : '申し訳ありません。もう一度お願いできますか？',
    intent: raw.intent ?? 'other',
    state: raw.state ?? 'initial',
    extracted: { ...EMPTY_EXTRACTED, ...(raw.extracted ?? {}) },
    should_transfer: Boolean(raw.should_transfer),
    should_end_call: Boolean(raw.should_end_call),
    next_action: raw.next_action ?? null,
    confidence: typeof raw.confidence === 'number' ? raw.confidence : 0,
  };
}
