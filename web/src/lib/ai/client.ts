import Anthropic from "@anthropic-ai/sdk";

// ============================================================
// Claude API（Anthropic）共通設定
// ------------------------------------------------------------
// ANTHROPIC_API_KEY が未設定のときは AI 機能を無効にし、
// 各画面は AI なしの表示（ルールベースのコメント等）にフォールバックする。
// ============================================================

export const AI_MODEL = "claude-opus-5-5";

/** 安全上の理由で断られた場合、サーバー側で推奨モデルに自動で引き継ぐ */
export const FALLBACK_BETA = "server-side-fallback-2026-07-01";

export const AI_ENABLED = Boolean(
  process.env.ANTHROPIC_API_KEY || process.env.ANTHROPIC_AUTH_TOKEN,
);

let client: Anthropic | null = null;

export function anthropic(): Anthropic {
  if (!client) client = new Anthropic();
  return client;
}
