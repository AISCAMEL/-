import type { Metadata } from "next";
import { SitePage } from "@/components/site-page";

export const metadata: Metadata = {
  title: "特定商取引法に基づく表記",
};

const ROWS: { k: string; v: string; todo?: boolean }[] = [
  { k: "事業者の名称", v: "【運営者にて記入】", todo: true },
  { k: "運営統括責任者", v: "【運営者にて記入】", todo: true },
  { k: "所在地", v: "【運営者にて記入（請求があれば遅滞なく開示）】", todo: true },
  { k: "電話番号", v: "【運営者にて記入（請求があれば遅滞なく開示）】", todo: true },
  { k: "メールアドレス", v: "【運営者にて記入】", todo: true },
  { k: "販売価格", v: "各講師・各スキルのページに税込で表示します。受講は月額制です。" },
  { k: "商品代金以外の必要料金", v: "サービス利用に通信料が発生します。決済手数料が生じる場合は購入手続き時に表示します。" },
  { k: "運営手数料", v: "マッチング成立後の月額から、所定の運営手数料（例：20%）を差し引いた額を講師にお支払いします。手数料率は申込時に表示します。" },
  { k: "支払方法", v: "クレジットカード等（決済代行会社を通じて処理します。カード情報は当社で保持しません）。" },
  { k: "支払時期", v: "月額サブスクリプションは、初回はお申込み時、以降は毎月同日に自動決済されます。" },
  { k: "役務の提供時期", v: "決済確認後、講師と日程を調整のうえ提供します。" },
  { k: "キャンセル・解約", v: "マイページからいつでも解約できます。日割り返金の有無は各プランの表示に従います。役務提供後の返金は原則承っておりません。" },
  { k: "返品・不良対応", v: "物販（ギア等）の初期不良は、商品到着後7日以内にご連絡ください。良品と交換または返金します。" },
  { k: "動作環境", v: "最新のモバイル/PCブラウザ。" },
];

export default function TokushohoPage() {
  return (
    <SitePage
      title="特定商取引法に基づく表記"
      lead="このページは雛形です。事業開始（決済導入）前に、実際の事業者情報を必ずご記入ください。"
    >
      <div className="overflow-hidden rounded-2xl border border-navy/10 bg-white">
        <table className="w-full text-sm">
          <tbody>
            {ROWS.map((r) => (
              <tr key={r.k} className="border-b border-navy/10 last:border-0 align-top">
                <th className="w-40 bg-foam px-4 py-3 text-left font-medium text-navy/70">
                  {r.k}
                </th>
                <td className={`px-4 py-3 ${r.todo ? "text-red-500" : "text-navy/80"}`}>
                  {r.v}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="mt-6 text-xs text-navy/40">
        ※ 赤字の項目は事業者情報です。開業届・登記等の正式名称・所在地に合わせてご記入ください。
        表現は事業内容に合わせ、必要に応じて専門家（行政書士・弁護士）にご確認いただくことをおすすめします。
      </p>
    </SitePage>
  );
}
