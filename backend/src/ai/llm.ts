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

// system は配列(text block)にして prompt caching を効かせる。
// 電話は多ターンで同じシステムプロンプトを毎回送るため、2ターン目以降の入力処理が速く・安くなる。
function splitForClaude(messages: ChatMessage[]): {
  system: Anthropic.TextBlockParam[] | undefined;
  convo: { role: 'user' | 'assistant'; content: string }[];
} {
  const systemText = messages.filter((m) => m.role === 'system').map((m) => m.content).join('\n\n');
  const system: Anthropic.TextBlockParam[] | undefined = systemText
    ? [{ type: 'text', text: systemText, cache_control: { type: 'ephemeral' } }]
    : undefined;
  const convo = messages
    .filter((m) => m.role !== 'system')
    .map((m) => ({ role: m.role as 'user' | 'assistant', content: m.content }));
  return { system, convo };
}

async function claudeComplete(messages: ChatMessage[], maxTokens: number): Promise<string | null> {
  if (!anthropic) return null;
  const { system, convo } = splitForClaude(messages);
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

// ストリーミング生成。トークンが届くたび onDelta を呼ぶ（音声は最初の数文字で喋り始められる＝合間が消える）。
// 返り値は生成し終えた全文。失敗時は null。
async function claudeStream(
  messages: ChatMessage[],
  maxTokens: number,
  onDelta: (text: string) => void,
): Promise<string | null> {
  if (!anthropic) return null;
  const { system, convo } = splitForClaude(messages);
  const base = { model: config.anthropic.model, max_tokens: maxTokens, system, messages: convo } as const;
  let emitted = false;
  const run = async (extra: Record<string, unknown>): Promise<string> => {
    const stream = anthropic!.messages.stream({ ...base, ...extra });
    let full = '';
    stream.on('text', (t: string) => { full += t; emitted = true; onDelta(t); });
    await stream.finalMessage();
    return full;
  };
  try {
    return await run({ thinking: { type: 'disabled' } });
  } catch (err) {
    // 途中まで喋ってしまっていたら、二重発話を避けるため再試行しない。
    if (emitted) { console.error('[llm] claudeStream partial then failed:', err); return null; }
    try {
      return await run({});
    } catch (err2) {
      console.error('[llm] claudeStream failed:', err2);
      return null;
    }
  }
}

/**
 * 返答をストリーミング生成し、トークンごとに onDelta を呼ぶ（音声の合間を最小化）。
 * 返り値は全文。未設定/失敗時は null（呼び出し側でフォールバック）。
 */
export async function chatTextStream(
  messages: ChatMessage[],
  onDelta: (text: string) => void,
  maxTokens = 220,
): Promise<string | null> {
  if (anthropic) return claudeStream(messages, maxTokens, onDelta);
  // OpenAI/OpenRouter フォールバック（ストリーミング）。
  if (!openai) return null;
  try {
    const stream = await openai.chat.completions.create({
      model: config.openai.model,
      messages,
      temperature: 0.5,
      max_tokens: maxTokens,
      stream: true,
    });
    let full = '';
    for await (const chunk of stream) {
      const t = chunk.choices[0]?.delta?.content ?? '';
      if (t) { full += t; onDelta(t); }
    }
    return full || null;
  } catch (err) {
    console.error('[llm] chatTextStream failed:', err);
    return null;
  }
}

// ---- ツール利用（function calling）付きの会話 ----
export interface ToolSpec {
  name: string;
  description: string;
  input_schema: Record<string, unknown>;
}
export type ToolRunner = (name: string, input: any) => Promise<unknown>;

/**
 * ツール（空き確認・予約など）を使いながら返答をストリーミング生成する。
 * 1) モデルがツールを要求したら runTool を実行し結果を返して継続、
 * 2) ツール要求が無くなったら最終の返答（音声）を全文返す。
 * onDelta には音声で読み上げるテキストだけが届く（tool_use は読み上げない）。
 * Anthropic未設定時はツール無しのテキストストリームにフォールバック。
 */
export async function chatAgentStream(
  messages: ChatMessage[],
  tools: ToolSpec[],
  runTool: ToolRunner,
  onDelta: (t: string) => void,
  maxTokens = 400,
): Promise<string | null> {
  if (!anthropic) return chatTextStream(messages, onDelta, maxTokens);
  const { system, convo } = splitForClaude(messages);
  const msgs: Anthropic.MessageParam[] = convo.map((m) => ({ role: m.role, content: m.content }));
  let finalText = '';
  let emitted = false;
  try {
    // 最大4ステップ（ツール→応答）までで打ち切り、無限ループを防ぐ。
    for (let step = 0; step < 4; step++) {
      const stream = anthropic.messages.stream({
        model: config.anthropic.model,
        max_tokens: maxTokens,
        system,
        messages: msgs,
        tools: tools as unknown as Anthropic.Tool[],
        thinking: { type: 'disabled' },
      });
      stream.on('text', (t: string) => { finalText += t; emitted = true; onDelta(t); });
      const final = await stream.finalMessage();
      const toolUses = final.content.filter(
        (b): b is Anthropic.ToolUseBlock => b.type === 'tool_use',
      );
      if (toolUses.length === 0) break;
      // アシスタントのツール要求を会話に積む。
      msgs.push({ role: 'assistant', content: final.content });
      // 各ツールを実行し、結果をまとめて返す。
      const results: Anthropic.ToolResultBlockParam[] = [];
      for (const tu of toolUses) {
        let out: unknown;
        try { out = await runTool(tu.name, tu.input); }
        catch (e) { out = { error: String(e) }; }
        results.push({ type: 'tool_result', tool_use_id: tu.id, content: JSON.stringify(out) });
      }
      msgs.push({ role: 'user', content: results });
    }
    return finalText || null;
  } catch (err) {
    console.error('[llm] chatAgentStream failed:', err);
    // まだ何も喋っていなければ、ツール無しで素直に返答を試みる。
    if (!emitted) return chatTextStream(messages, onDelta, maxTokens);
    return finalText || null;
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
