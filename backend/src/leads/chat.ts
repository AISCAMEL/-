import { chatText, llmEnabled } from '../ai/llm.js';

export interface ChatTurn { role: 'user' | 'assistant'; content: string; }

const SYSTEM = `あなたは「AIオペレーター24」の販売アシスタントです。
AIオペレーター24は、電話に出られない時間にAIが24時間自動で電話受付し、予約・問い合わせ・折り返し・担当者転送・通話要約・通知までを行うAI電話受付SaaSです。
丁寧で簡潔な日本語（2〜4文）で答えてください。分からないことは推測せず「担当者へおつなぎします」と案内し、無料デモ/資料請求のフォーム（/contact）を勧めてください。
料金: Starter 月9,800円〜(月100分)、Business 月29,800円〜(月500分)、Pro 月59,800円〜(月1,500分)。`;

/** チャットボットの応答を返す。LLM未設定時はFAQベースのルール応答。 */
export async function salesChatReply(message: string, history: ChatTurn[]): Promise<string> {
  if (llmEnabled) {
    const reply = await chatText([
      { role: 'system', content: SYSTEM },
      ...history.slice(-8).map((t) => ({ role: t.role, content: t.content })),
      { role: 'user', content: message },
    ]);
    if (reply) return reply.trim();
  }
  return ruleReply(message);
}

// LLMが無い環境（デモ）でも自然に答えるためのキーワードFAQ。
function ruleReply(message: string): string {
  const m = message.toLowerCase();
  if (/料金|価格|いくら|費用|プラン|値段/.test(message)) {
    return '料金はStarter 月額9,800円〜（月100分）、Business 月額29,800円〜（月500分）、Pro 月額59,800円〜（月1,500分）です。超過分は1分50〜80円。まずは無料デモもお試しいただけます。';
  }
  if (/機能|何ができ|できること|どんな/.test(message)) {
    return 'AIが24時間電話に応答し、予約受付・問い合わせ対応・折り返し受付・担当者転送・通話の文字起こし/要約・メールやSlackへの通知を自動で行います。管理画面で履歴も確認できます。';
  }
  if (/デモ|試|無料|体験/.test(message)) {
    return '無料デモをご用意しています。お問い合わせフォーム（資料請求/無料デモ）からお申し込みいただくと、担当者よりご案内します。';
  }
  if (/予約/.test(message)) {
    return 'AIが希望日時・お名前・ご用件を聞き取り「予約希望」として受け付けます。予約システム連携も将来対応予定です。';
  }
  if (/電話|番号|twilio|着信/.test(message)) {
    return '御社専用のAI電話番号を発行し、その番号にかかってきた電話にAIが応答します。まずは1番号で1週間のテスト導入が可能です。';
  }
  if (/導入|始め|申し込|契約|どうやって/.test(message)) {
    return '導入は、お問い合わせ→ヒアリング→AI設定（挨拶文/FAQ/転送先）→テスト番号で開始、という流れです。お問い合わせフォームからお気軽にご連絡ください。';
  }
  if (/人|担当|オペレーター|相談/.test(message)) {
    return '担当者がご相談を承ります。お問い合わせフォームにご連絡先をご記入いただければ、折り返しご案内いたします。';
  }
  return 'ご質問ありがとうございます。AIオペレーター24は電話受付を自動化するサービスです。料金・機能・無料デモなど、何でもお聞きください。詳しいご相談はお問い合わせフォームからも承っています。';
}
