import type { Metadata } from "next";
import { SitePage, Section } from "@/components/site-page";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "利用規約",
};

export default function TermsPage() {
  return (
    <SitePage
      title="利用規約"
      lead={`${SITE.name} のご利用にあたり、以下に同意いただくものとします。（雛形）`}
    >
      <Section heading="第1条（適用）">
        <p>本規約は、当サービスの提供条件および利用者と運営者の関係を定めるものです。</p>
      </Section>
      <Section heading="第2条（会員登録）">
        <p>
          登録は無料です。虚偽の情報での登録、他人へのなりすまし、複数アカウントの不正利用を禁止します。
        </p>
      </Section>
      <Section heading="第3条（スクール・マッチング）">
        <p>
          講師と生徒のマッチングの場を提供します。受講は月額制で、運営はマッチング成立後の月額から
          所定の手数料を受け取ります。レッスンの内容・品質・当日の実施は当事者間の責任で行われ、
          運営は場の提供者として合理的な範囲でサポートします。
        </p>
      </Section>
      <Section heading="第4条（海の安全）">
        <p>
          サーフィンには相応の危険が伴います。参加者は自己の体調・技量・当日の海況を判断し、
          自己責任で参加するものとします。講師の安全指示・ローカルルールに従ってください。
          未成年の参加には保護者の同意が必要です。
        </p>
      </Section>
      <Section heading="第5条（禁止事項）">
        <p>
          法令・公序良俗に反する行為、他者への誹謗中傷・ハラスメント、無断の商用勧誘、
          プラットフォームを介さない直接取引による手数料回避、他者の権利侵害を禁止します。
        </p>
      </Section>
      <Section heading="第6条（投稿・口コミ）">
        <p>
          投稿・口コミは事実に基づき、他者の名誉やプライバシーを侵害しない範囲で行ってください。
          運営は不適切な内容を非公開・削除できます。
        </p>
      </Section>
      <Section heading="第7条（退会・利用停止）">
        <p>
          利用者はいつでも退会できます。規約違反があった場合、運営は事前通知なく利用停止・
          アカウント削除を行うことがあります。
        </p>
      </Section>
      <Section heading="第8条（免責）">
        <p>
          運営は、利用者間のトラブルや、サービス中断・データ消失等について、法令で許容される範囲で
          責任を負いません。
        </p>
      </Section>
      <Section heading="第9条（規約の変更）">
        <p>本規約は必要に応じて変更します。変更後の利用をもって同意とみなします。</p>
      </Section>
      <p className="mt-8 text-xs text-navy/40">
        制定日：【要記入】／ ※ 実運用前に、事業内容に合わせて内容をご確認ください。
      </p>
    </SitePage>
  );
}
