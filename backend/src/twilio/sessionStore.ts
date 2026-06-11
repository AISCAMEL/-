import type { ConversationOrchestrator } from '../ai/orchestrator.js';

// 進行中通話の状態を保持する。WebSocket ハンドラと connect-ended Webhook で共有する。
// MVPは単一プロセス前提のインメモリ。将来スケール時は Redis 等へ。
export interface CallSession {
  callSid: string;
  sessionId: string | null;
  tenantId: string;
  callId: string | null;        // DB calls.id（DBなしモードでは null）
  from: string;
  to: string;
  startedAt: number;            // epoch ms
  orchestrator: ConversationOrchestrator;
}

const byCallSid = new Map<string, CallSession>();

export function putSession(s: CallSession): void {
  byCallSid.set(s.callSid, s);
}

export function getSession(callSid: string): CallSession | undefined {
  return byCallSid.get(callSid);
}

export function deleteSession(callSid: string): void {
  byCallSid.delete(callSid);
}
