/**
 * サイト全体の基本情報。
 * placeholder（仮）の項目は実情報が確定したら差し替えてください。
 */
export const site = {
  name: "合同会社アイズ",
  nameEn: "AIS LLC",
  brandTagline: "Always Innovation Solutions",
  // SEO の基準URL（本番ドメインに合わせて変更）
  url: "https://aiscompany.jp",
  description:
    "合同会社アイズは、自動車の販売・買取・リース・カーレスキューを主軸に、ノーコードアプリ開発「APPREX」・Web/システム開発「WEBCREWS」、GPS事業、FC事業を展開する会社です。クルマのことからデジタルまで、ワンストップでお応えします。",
  // 連絡先（placeholder: 確定情報に差し替え）
  contact: {
    email: "info@aisjaltd.com", // placeholder
    tel: "000-0000-0000", // placeholder
    telHours: "平日 10:00 - 18:00",
    address: "〒000-0000  XXX県XXX市XXX 0-0-0", // placeholder
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
