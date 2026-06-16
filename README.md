# 合同会社アイズ コーポレートサイト

自動車販売「カーメル」・買取「BUYMO」・リース「CARSHICO」・車両セキュリティ「天護 TENGO」・レッカーを主軸に、
IT事業「APPREX」（ノーコードアプリ開発）・WEB開発「WEB crews」・FC事業を展開する
合同会社アイズ（AIS LLC）のコーポレートサイト（リニューアル版）です。

## 技術スタック

- [Next.js 14](https://nextjs.org/)（App Router）/ React 18 / TypeScript
- [Tailwind CSS v3](https://tailwindcss.com/)
- 全ページ静的生成（SSG）、SEO対応（metadata / sitemap / robots / JSON-LD）

## セットアップ

```bash
npm install
npm run dev        # http://localhost:3000
npm run build      # 本番ビルド
npm run start      # 本番サーバー起動
```

## ディレクトリ構成

```
src/
├─ app/          各ページ（ルーティング）+ sitemap / robots / not-found
├─ components/   layout / ui / home / contact のコンポーネント
└─ content/      文言・サービス・FAQ・実績・記事などのデータ（CMS移行を想定）
docs/            リニューアル戦略・設計書
```

## コンテンツの編集

文言や掲載情報は `src/content/*.ts` を編集します（UIコードの変更は不要）。

| ファイル | 内容 |
| --- | --- |
| `site.ts` | 会社名・連絡先・ドメインなど基本情報 |
| `navigation.ts` | ヘッダー/フッターのメニュー |
| `services.ts` | 8事業（3グループ）とブランドの詳細 |
| `home.ts` | トップの課題・強み・流れ・メッセージ |
| `faq.ts` | よくある質問 |
| `works.ts` | 実績・事例 |
| `news.ts` | お知らせ・コラム |
| `company.ts` | 会社概要・理念 |

## 公開前に差し替えが必要な項目（placeholder）

- 各ブランドのロゴ・サービスサイトURL（カーメル／BUYMO／CARSHICO／天護／APPREX／WEB crews）
- 実績（`works.ts`）・お知らせ（`news.ts`）の実データ（`isPlaceholder: false` に）
- プライバシーポリシー本文 `app/privacy/page.tsx`
- お問い合わせフォームの送信処理（`components/contact/ContactForm.tsx`）

> 会社概要・連絡先・本番ドメイン（aisjaltd.com）は実情報を反映済みです。

詳細は [`docs/サイトリニューアル戦略.md`](./docs/サイトリニューアル戦略.md) を参照してください。
