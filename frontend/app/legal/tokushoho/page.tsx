import Link from 'next/link';
import { LegalShell, Row } from '@/components/legal';

export const metadata = { title: '特定商取引法に基づく表記 | AIオペレーター24' };

// ※ 有料サービスをオンライン販売する場合、特定商取引法に基づく表記は必須です。
//    【要記入】の箇所は実際の事業者情報に置き換えてください。
export default function TokushohoPage() {
  return (
    <LegalShell title="特定商取引法に基づく表記">
      <table className="w-full border-collapse text-sm">
        <tbody>
          <Row label="販売事業者">【要記入】株式会社〇〇</Row>
          <Row label="運営統括責任者">【要記入】山田 太郎</Row>
          <Row label="所在地">【要記入】〒000-0000 東京都〇〇区〇〇 0-0-0</Row>
          <Row label="電話番号">【要記入】03-0000-0000（受付：平日10:00〜18:00）</Row>
          <Row label="メールアドレス">support@ai-operator24.com</Row>
          <Row label="販売価格">
            各料金プランに表示の金額（Starter 月額9,800円／Business 月額29,800円／Pro 月額59,800円、いずれも税込）。
            上限分数を超過した場合は超過料金（1分あたり50〜80円）が加算されます。
          </Row>
          <Row label="商品代金以外の必要料金">消費税、通信回線の費用、超過通話料、オプション利用料。</Row>
          <Row label="支払方法">【要記入】クレジットカード／銀行振込</Row>
          <Row label="支払時期">【要記入】毎月末締め、翌月◯日にご請求（クレジットカードは即時決済）。</Row>
          <Row label="サービス提供時期">お申込み・初期設定の完了後、順次提供を開始します。</Row>
          <Row label="返品・キャンセル">
            サービスの性質上、提供開始後の返金は原則承っておりません。解約は管理画面またはお問い合わせよりお申し付けください。
            解約はお申し出のあった当月末をもって終了します。
          </Row>
          <Row label="動作環境">最新版の Google Chrome / Safari / Microsoft Edge を推奨します。</Row>
        </tbody>
      </table>
      <p className="mt-6 text-sm text-gray-500">
        <Link href="/" className="text-brand hover:underline">トップへ戻る</Link>
      </p>
    </LegalShell>
  );
}
