// ============================================================
// サイト（拠点）の一元設定
// ------------------------------------------------------------
// ブランド名・タグライン・地域トークンをここに集約する。
// 将来、別拠点（例：茨城・千葉・湘南…）へ展開する際は、原則このファイルの
// 値を変えるだけで済むようにするための「単一の出所」。
// ※ about / faq / demo などの長文コピーは各ページの読み物として残す
//   （新拠点では書き直す前提なので無理に変数化しない）。
// ============================================================

export const SITE = {
  /** 正式名称（メタタイトルのサフィックス等に使用） */
  name: "IWASAWA SURF BASE",
  /** ワードマーク（2段表示） */
  wordmark: { line1: "IWASAWA", line2: "SURF BASE" },
  /** アプリ短縮名（ホーム画面追加時など） */
  shortName: "IWASAWA",
  /** キャッチコピー */
  tagline: "福島の波を、もっと近くに。",
  /** メタ説明 */
  description:
    "福島の波を、もっと近くに。学べる・借りられる・移動できる・案内される、岩沢海岸の海体験プラットフォーム。",
  /** 地域トークン */
  region: {
    prefecture: "福島県",
    city: "双葉郡広野町",
    /** 海岸名（見出し・プレースホルダ等のラベルに使用） */
    beach: "岩沢海岸",
    /** 海岸名の短縮（「岩沢の、今日。」等の口語見出しに使用） */
    beachShort: "岩沢",
    /** エリア表記（フッター・問い合わせ等） */
    areaLabel: "福島県双葉郡広野町・岩沢海岸エリア",
  },
  /**
   * 運営者情報（特定商取引法表記・プライバシーポリシー・利用規約に表示）。
   * 公開前にここを埋めれば、すべての法務ページに反映される。空欄のままだと「要記入」と表示。
   */
  operator: {
    name: "", // 事業者の名称（例：株式会社〇〇）
    representative: "", // 運営統括責任者
    address: "", // 所在地
    phone: "", // 電話番号
    email: "", // お問い合わせ用メールアドレス
    legalEffectiveDate: "", // 法務ページの制定日（例：2026年12月1日）
  },
  /** LINE公式アカウントの友だち追加URL（未設定ならLINEの案内を出さない） */
  lineUrl: process.env.NEXT_PUBLIC_LINE_URL ?? "",
} as const;

/**
 * サイトの公開URL（OGP・サイトマップ・検索エンジン向け）。
 * NEXT_PUBLIC_SITE_URL を優先し、なければ Vercel の本番URL、ローカルでは localhost。
 */
export const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ||
  (process.env.VERCEL_PROJECT_PRODUCTION_URL
    ? `https://${process.env.VERCEL_PROJECT_PRODUCTION_URL}`
    : "http://localhost:3200")
).replace(/\/$/, "");
