import { LegalShell, Section } from '@/components/legal';

export const metadata = { title: 'プライバシーポリシー | AIオペレーター24' };

export default function PrivacyPage() {
  return (
    <LegalShell title="プライバシーポリシー">
      <p className="text-sm text-gray-600">
        AIオペレーター24（以下「当サービス」）は、利用者の個人情報を適切に取り扱うため、本ポリシーを定めます。
      </p>

      <Section heading="1. 取得する情報">
        <p>氏名、会社名、メールアドレス、電話番号、業種、お問い合わせ内容、ならびにサービス提供に伴い発生する通話記録・文字起こし・要約・利用状況等の情報を取得します。</p>
      </Section>
      <Section heading="2. 利用目的">
        <ul className="list-inside list-disc space-y-1">
          <li>当サービスの提供・本人確認・お問い合わせ対応のため</li>
          <li>料金請求、利用状況の分析、品質改善のため</li>
          <li>新機能・キャンペーン等のご案内のため</li>
        </ul>
      </Section>
      <Section heading="3. 第三者提供">
        <p>法令に基づく場合を除き、本人の同意なく個人情報を第三者に提供しません。</p>
      </Section>
      <Section heading="4. 業務委託（外部サービスの利用）">
        <p>当サービスは、電話基盤（Twilio）、AI処理（OpenAI 等）、メール配信、ホスティング、データベース等の外部サービスを利用します。これらに対し、サービス提供に必要な範囲で情報の取扱いを委託します。</p>
      </Section>
      <Section heading="5. 通話の録音・文字起こし">
        <p>当サービスはAIによる電話応対の性質上、通話内容を文字起こし・要約し、必要に応じて録音します。録音の有無は契約者の設定に従います。</p>
      </Section>
      <Section heading="6. Cookie等の利用">
        <p>ログイン状態の維持やアクセス解析のため、Cookie等を利用する場合があります。</p>
      </Section>
      <Section heading="7. 開示・訂正・削除の請求">
        <p>本人からの保有個人データの開示・訂正・利用停止・削除のご請求には、法令に従い適切に対応します。下記窓口までご連絡ください。</p>
      </Section>
      <Section heading="8. お問い合わせ窓口">
        <p>【要記入】株式会社〇〇　個人情報お問い合わせ窓口　support@ai-operator24.com</p>
      </Section>
      <Section heading="9. 改定">
        <p>本ポリシーは必要に応じて改定します。重要な変更は当サイト上で通知します。</p>
      </Section>
      <p className="mt-8 text-xs text-gray-400">制定日：【要記入】2026年〇月〇日</p>
    </LegalShell>
  );
}
