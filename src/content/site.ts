/**
 * サイト全体の基本情報。
 * placeholder（仮）の項目は実情報が確定したら差し替えてください。
 */
export const site = {
  name: "合同会社アイズ",
  nameEn: "AIS LLC",
  brandTagline: "Always Innovation Solutions",
  // SEO の基準URL（本番ドメイン）
  url: "https://aisjaltd.com",
  description:
    "合同会社アイズは、自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカーを主軸に、ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、FC事業を展開する会社です。クルマのことからデジタルまで、ワンストップでお応えします。",
  contact: {
    email: "info@aisjaltd.com",
    tel: "050-1722-3365",
    telHours: "平日 10:00 - 18:00",
    address: "〒979-0204 福島県いわき市四倉町細谷字大町1番",
    replyTarget: "原則1〜2営業日以内",
  },
  // SNS（placeholder: 運用中のアカウントに差し替え。未運用なら空文字）
  social: {
    x: "",
    facebook: "",
    instagram: "",
    youtube: "",
  },
} as const;

export type Site = typeof site;
