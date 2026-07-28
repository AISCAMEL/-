import type { Metadata } from "next";
import { SitePage, Section } from "@/components/site-page";

export const metadata: Metadata = {
  title: "プライバシーポリシー｜IWASAWA SURF BASE",
};

export default function PrivacyPage() {
  return (
    <SitePage
      title="プライバシーポリシー"
      lead="IWASAWA SURF BASE（以下「当サービス」）における個人情報の取り扱い方針です。（雛形）"
    >
      <Section heading="1. 取得する情報">
        <p>
          会員登録時のお名前（表示名）・メールアドレス、プロフィール情報、投稿・口コミ・お問い合わせの内容、
          プロフィール写真、アクセスログ等を取得します。決済に関する情報は決済代行会社が取り扱い、
          当サービスはカード番号等を保持しません。
        </p>
      </Section>
      <Section heading="2. 利用目的">
        <p>
          本人確認、サービスの提供・改善、講師と生徒のマッチング、お問い合わせ対応、
          重要なお知らせの連絡、不正利用の防止のために利用します。
        </p>
      </Section>
      <Section heading="3. 第三者提供">
        <p>
          法令に基づく場合を除き、ご本人の同意なく第三者に個人情報を提供しません。
          サービス運営に必要な範囲で、決済・メール配信等の委託先に取り扱いを委託することがあります。
        </p>
      </Section>
      <Section heading="4. 公開範囲">
        <p>
          表示名・プロフィール・投稿・口コミ・講師プロフィールは、サービス内で他の利用者に公開されます。
          メールアドレス等の連絡先は公開されません。
        </p>
      </Section>
      <Section heading="5. Cookie等">
        <p>ログイン状態の維持等のために Cookie を使用します。</p>
      </Section>
      <Section heading="6. 開示・訂正・削除">
        <p>
          ご自身の情報の開示・訂正・削除・退会をご希望の場合は、お問い合わせ窓口までご連絡ください。
          マイページから編集・削除できる項目もあります。
        </p>
      </Section>
      <Section heading="7. 改定">
        <p>本ポリシーは必要に応じて改定します。重要な変更は当サービス上で告知します。</p>
      </Section>
      <Section heading="8. お問い合わせ窓口">
        <p>【運営者にて記入：事業者名・連絡先】</p>
      </Section>
      <p className="mt-8 text-xs text-navy/40">
        制定日：【要記入】／ ※ 実運用前に内容と事業者情報をご確認・ご記入ください。
      </p>
    </SitePage>
  );
}
