import OpenAI from 'openai';
import { config } from '../config.js';

// MVPは OpenAI 優先。OpenRouter等のOpenAI互換APIは OPENAI_BASE_URL で切り替え可。
const client = config.openai.apiKey
  ? new OpenAI({
      apiKey: config.openai.apiKey,
      baseURL: config.openai.baseUrl || undefined,
      // OpenRouter 推奨ヘッダ（任意）。
      defaultHeaders: config.openai.baseUrl.includes('openrouter')
        ? { 'HTTP-Referer': config.publicApiBaseUrl, 'X-Title': 'AIオペレーター24' }
        : undefined,
    })
  : null;

export const llmEnabled = Boolean(client);

interface ChatMessage {
  role: 'system' | 'user' | 'assistant';
  content: string;
}

/**
 * JSON応答を強制してチャット補完を呼ぶ。
 * 失敗や未設定時は null を返し、呼び出し側でフォールバックさせる。
 */
export async function chatJson<T>(messages: ChatMessage[], model?: string): Promise<T | null> {
  if (!client) return null;
  const useModel = model ?? config.openai.model;
  try {
    const res = await client.chat.completions.create({
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
      const res = await client.chat.completions.create({
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
  if (!client) return null;
  try {
    const res = await client.chat.completions.create({
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
