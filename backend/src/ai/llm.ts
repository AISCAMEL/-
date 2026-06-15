import OpenAI from 'openai';
import { config } from '../config.js';

// MVPは OpenAI 優先。将来 Claude / Gemini を足す際はこの薄いラッパを差し替える。
const client = config.openai.apiKey ? new OpenAI({ apiKey: config.openai.apiKey }) : null;

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
  try {
    const res = await client.chat.completions.create({
      model: model ?? config.openai.model,
      messages,
      temperature: 0.3,
      response_format: { type: 'json_object' },
    });
    const text = res.choices[0]?.message?.content;
    if (!text) return null;
    return JSON.parse(text) as T;
  } catch (err) {
    console.error('[llm] chatJson failed:', err);
    return null;
  }
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
