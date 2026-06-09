# ✅ site.aiscompany.jp 切替チェックリスト（実値版）

> 既存 `site.aiscompany.jp`（Cloudflare Pages・静的）→ WPX の WordPress（テーマ `apprex`）へ、**同一ホスト名**で切り替えるための実作業チェックリスト。
>
> 【 】は環境固有のため、WPX パネル等の表示値に置き換えてください。

---

## 0. まだ未確定の値（最初に控える）

| 値 | 取得元 | メモ |
|----|--------|------|
| 【WPXサーバーのIP】 | WPXパネル「サーバー情報」 | DNSのAレコードに使用 |
| 【サーバーID】 | WPXパネル | cron のパスに使用 |
| 【公開ディレクトリのパス】 | ファイルマネージャ | 例：`/home/【サーバーID】/aiscompany.jp/public_html/site` |
| 【PHPのパス】 | パネルのcron例 | 例：`/usr/bin/php` または `/usr/bin/php8.2` |

`site.aiscompany.jp` の DNS は **Cloudflare（aiscompany.jp ゾーン）** 管理である前提。

---

## 1. 事前準備（切替の前日〜数時間前）

- [ ] Cloudflare DNS で `site` レコードの **TTL を 300秒**に変更（ロールバックを速くする）
- [ ] WPX に `aiscompany.jp` を「ドメイン設定追加」（未追加なら）
- [ ] WPX「サブドメイン設定追加」で **`site`** を追加 → `site.aiscompany.jp`
- [ ] そのサブドメインへ **WordPress インストール**＋テーマ `apprex` 有効化（自動構築）
- [ ] **動作確認URL** か **hosts**（`【WPXのIP】 site.aiscompany.jp`）で表示・フォーム・チャットを確認
- [ ] 作成が必要な追加ページを用意（任意）：`/terms/`（利用規約）、`/legal/`（特商法）、`/subsidy/`（補助金）、ブログ

## 2. wp-config.php 追記（ファイルマネージャ or FTP）

```php
// AIチャット・AI記事生成
define( 'APPREX_OPENROUTER_API_KEY', '【sk-or-...】' );
define( 'APPREX_OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku' );

// ステップメールをサーバーcronで確実配信（§5）
define( 'DISABLE_WP_CRON', true );
```

## 3. 301 リダイレクト（.htaccess）

公開ディレクトリ直下の `.htaccess` の **`# BEGIN WordPress` より上**に貼り付け。
（`googleb75f289fb560264c.html` は**リダイレクトしない**。Search Console確認用に必要なら新サイトにも同ファイルを設置）

```apache
# === 旧静的URL → 新WordPress URL (301) ===
Redirect 301 /index.html            /
Redirect 301 /about.html            /company/
Redirect 301 /company.html          /company/
Redirect 301 /features.html         /features/
Redirect 301 /services.html         /functions/
Redirect 301 /functions.html        /functions/
Redirect 301 /pricing.html          /pricing/
Redirect 301 /pricing-old.html      /pricing/
Redirect 301 /faq.html              /faq/
Redirect 301 /free-trial.html       /free-trial/
Redirect 301 /contact.html          /contact/
Redirect 301 /estimate.html         /estimate/
Redirect 301 /cases.html            /cases/
Redirect 301 /matching-cases.html   /cases/
Redirect 301 /hp-creation.html      /hp-creation/
Redirect 301 /website-creation.html /hp-creation/
Redirect 301 /privacy.html          /privacy-policy/
Redirect 301 /image-diagnostic.html /

# --- 下記は対応ページを作成してから有効化（暫定で残すと404回避） ---
Redirect 301 /terms.html            /terms/
Redirect 301 /legal.html            /legal/
Redirect 301 /subsidy.html          /subsidy/
Redirect 301 /blog.html             /blog/
Redirect 301 /blog-detail.html      /blog/
Redirect 301 /blog-1.html           /blog/
Redirect 301 /blog-2.html           /blog/
Redirect 301 /blog-3.html           /blog/
Redirect 301 /blog-4.html           /blog/
Redirect 301 /blog-5.html           /blog/
Redirect 301 /blog-6.html           /blog/
```

> `/terms/ /legal/ /subsidy/ /blog/` はまだWPに無いので、**先にページ（または投稿/ブログ）を作成**してからリダイレクトを有効化してください。未作成のまま運用するとリダイレクト先が404になります。作成しない場合は該当行を一旦コメントアウト（行頭に `#`）。

## 4. DNS 切替（Cloudflare）

- [ ] **Cloudflare Pages プロジェクト**のカスタムドメインから `site.aiscompany.jp` を**削除**（ホスト名を解放）
- [ ] Cloudflare **DNS** で `site` を編集／作成：
  - 種別：**A** ／ 名前：**`site`** ／ 内容：**【WPXのIP】**
  - プロキシ：**DNSのみ（グレー雲）**（SSL発行のため一旦オフ）
  - TTL：Auto（または300）
- [ ] 反映確認：`dig +short site.aiscompany.jp`（または nslookup）が **【WPXのIP】** を返す
- [ ] WPXで **無料独自SSL（Let's Encrypt）を `site.aiscompany.jp` に発行** → 最大1h
- [ ] `https://site.aiscompany.jp/` で鍵マーク確認（必要なら後でプロキシ＝オレンジ雲に）

## 5. サーバー cron（ステップメール／リマインダー）

WPXパネル「Cron設定」で**1時間ごと**に追加：

- スケジュール：`分 0 / 時 * / 日 * / 月 * / 曜 *`（= `0 * * * *`）
- コマンド：
  ```
  【PHPのパス】 【公開ディレクトリのパス】/wp-cron.php >/dev/null 2>&1
  例) /usr/bin/php /home/【サーバーID】/aiscompany.jp/public_html/site/wp-cron.php >/dev/null 2>&1
  ```

## 6. 切替後の仕上げ

- [ ] WP **設定 > 一般** の URL が `https://site.aiscompany.jp`
- [ ] **設定 > パーマリンク**を一度保存（リライト確実化）
- [ ] 主要ページ表示：`/ /features/ /functions/ /pricing/ /cases/ /estimate/ /faq/ /contact/ /free-trial/ /document/ /meeting/ /hp-creation/ /company/`
- [ ] 旧URLリダイレクト動作：例 `https://site.aiscompany.jp/pricing.html` → `/pricing/`
- [ ] フォーム送信 → 自動返信メール受信（迷惑メールも確認）
- [ ] AIチャット応答（APIキー設定後）
- [ ] `sitemap.xml` を再生成 → **Search Console（同一プロパティ）で再送信**
- [ ] スマホ表示・PageSpeed 確認

## 7. ロールバック（不具合時）

1. Cloudflare DNS の `site` を**元（Cloudflare Pages 宛）に戻す**（TTL短縮済みで数分）。
2. 必要なら Pages のカスタムドメインに `site.aiscompany.jp` を再登録。
3. **確認完了まで Cloudflare Pages プロジェクトは削除しない**。

## 8. 注意

- **MX（メール）レコードは変更しない**。`site` サブドメインの切替はメールに通常影響しません。
- Cloudflare プロキシ（オレンジ雲）を使う場合、SSL発行はグレー雲で完了させてから有効化が安全。
- 「中身を書き換える」のではなく**配信先サーバーをWordPressへ切替**。以後のコンテンツ編集は WordPress 管理画面で行います。
