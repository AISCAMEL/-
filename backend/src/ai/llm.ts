import OpenAI from 'openai';
import Anthropic from '@anthropic-ai/sdk';
import { config } from '../config.js';

// 会話の頭脳。優先順位: Anthropic(Claude) > OpenAI/OpenRouter。
// ANTHROPIC_API_KEY があれば Claude を使い、無ければ従来の OpenAI互換(OpenRouter等)にフォールバック。
const anthropic = config.anthropic.apiKey
  ? new Anthropic({ apiKey: config.anthropic.apiKey })
  : null;

const openai = config.openai.apiKey
  ? new OpenAI({
      apiKey: config.openai.apiKey,
      baseURL: config.openai.baseUrl || undefined,
      // OpenRouter 推奨ヘッダ（任意）。HTTPヘッダはASCIIのみ可のため英字で指定する。
      defaultHeaders: config.openai.baseUrl.includes('openrouter')
        ? { 'HTTP-Referer': config.publicApiBaseUrl, 'X-Title': 'AI Operator 24' }
        : undefined,
    })
  : null;

export const llmEnabled = Boolean(anthropic || openai);
// いまどのLLMを会話の頭脳に使っているか（/healthで確認できるように公開）。
export const llmProvider = anthropic ? 'anthropic' : openai ? 'openai' : 'none';

interface ChatMessage {
  role: 'system' | 'user' | 'assistant';
  content: string;
}

// ---- Anthropic(Claude) 呼び出し ----
// Claude は system をトップレベルで渡すため、system役を分離して会話(user/assistant)だけを messages にする。
// 電話受付はレイテンシ重視のため thinking を無効化（Haiku 5.5は disabled 可）。
// モデルによっては disabled が不可(400)なので、その場合は thinking無しで再試行する。
async function claudeComplete(messages: ChatMessage[], maxTokens: number): Promise<string | null> {
  if (!anthropic) return null;
  const system = messages.filter((m) => m.role === 'system').map((m) => m.content).join('\n\n');
  const convo = messages
    .filter((m) => m.role !== 'system')
    .map((m) => ({ role: m.role as 'user' | 'assistant', content: m.content }));
  // Claude 5系は temperature 等の非既定値で400になるため指定しない。
  const base = { model: config.anthropic.model, max_tokens: maxTokens, system, messages: convo } as const;
  const extractText = (res: Anthropic.Message): string =>
    res.content.filter((b): b is Anthropic.TextBlock => b.type === 'text').map((b) => b.text).join('');
  try {
    const res = await anthropic.messages.create({ ...base, thinking: { type: 'disabled' } });
    return extractText(res);
  } catch {
    try {
      const res = await anthropic.messages.create({ ...base });
      return extractText(res);
    } catch (err) {
      console.error('[llm] claudeComplete failed:', err);
      return null;
    }
  }
}

/**
 * JSON応答を強制してチャット補完を呼ぶ。
 * 失敗や未設定時は null を返し、呼び出し側でフォールバックさせる。
 */
export async function chatJson<T>(messages: ChatMessage[], model?: string): Promise<T | null> {
  // --- Claude 優先 ---
  if (anthropic) {
    const msgs: ChatMessage[] = [
      ...messages,
      { role: 'system', content: '出力は必ず有効なJSONオブジェクトのみ。前後に説明文やコードブロック(```)を付けない。' },
    ];
    const text = await claudeComplete(msgs, 1024);
    return text ? parseJsonLoose<T>(text) : null;
  }
  // --- OpenAI/OpenRouter フォールバック ---
  if (!openai) return null;
  const useModel = model ?? config.openai.model;
  try {
    const res = await openai.chat.completions.create({
      model: useModel,
      messages,
      temperature: 0.3,
      response_format: { type: 'json_object' },
    });
    const text = res.choices[0]?.message?.content;
    if (!text) return null;
    return parseJsonLoose<T>(text);
  } catch (err) {
    // 一部のOpenRouter無料モデル等は JSON モード(response_format)に非対応。
    // その場合は response_format なしで再試行し、本文からJSONを抽出する。
    try {
      const res = await openai.chat.completions.create({
        model: useModel,
        messages: [...messages, { role: 'system', content: '出力は必ず有効なJSONオブジェクトのみ。前後に説明文やコードブロックを付けない。' }],
        temperature: 0.3,
      });
      const text = res.choices[0]?.message?.content;
      if (text) { const v = parseJsonLoose<T>(text); if (v) return v; }
    } catch { /* fallthrough */ }
    console.error('[llm] chatJson failed:', err);
    return null;
  }
}

/** モデルが ```json ... ``` で包んだり前後にテキストを付けても、JSON部分を取り出して解析する。 */
function parseJsonLoose<T>(text: string): T | null {
  try { return JSON.parse(text) as T; } catch { /* try extract */ }
  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/i);
  const candidate = fenced ? fenced[1] : text.slice(text.indexOf('{'), text.lastIndexOf('}') + 1);
  try { return JSON.parse(candidate) as T; } catch { return null; }
}

/** プレーンテキスト応答を返すチャット補完（チャットボット用）。未設定/失敗時は null。 */
export async function chatText(messages: ChatMessage[], model?: string): Promise<string | null> {
  if (anthropic) {
    return claudeComplete(messages, 512);
  }
  if (!openai) return null;
  try {
    const res = await openai.chat.completions.create({
      model: model ?? config.openai.model,
      messages,
      temperature: 0.5,
      max_tokens: 400,
    });
    return res.choices[0]?.message?.content ?? null;
  } catch (err) {
    console.error('[llm] chatText failed:', err);
    return null;
  }
}
