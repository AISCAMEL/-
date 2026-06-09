# 🚀 site.aiscompany.jp → WordPress 切替 実践ランブック（詳細版）

> 既存 `site.aiscompany.jp`（Cloudflare Pages・静的）を、WPX 上の WordPress（テーマ `apprex`）へ**同一ホスト名のまま**切り替えるための、クリック単位の手順書。
>
> 関連：`APPREX_切替チェックリスト.md`（要点版）／`APPREX_WPX設置手順.md`（基本）。

---

## 0. 全体像・所要時間・鉄則

- 流れ：**WPXで完成 → 自分だけプレビュー → DNS切替 → SSL発行 → 仕上げ**。
- 公開が切り替わるのは **DNS変更の瞬間だけ**。それまで一般訪問者は現行サイトを見続けます。
- 所要：構築1〜数時間／DNS反映 数分〜（TTL短縮済みなら速い）／SSL発行 最大1時間。
- 鉄則：**確認が済むまで Cloudflare Pages は消さない**（即ロールバック用）。

### 事前に控える値（WPXパネル「サーバー情報」等）
| 記号 | 値 | 取得元 |
|------|----|--------|
| `IP` | 例 `183.xxx.xxx.xxx` | パネル「サーバー情報」のIPアドレス |
| `SID` | 例 `xs123456` | サーバーID（パネル/契約情報） |
| `DOCROOT` | 例 `/home/SID/aiscompany.jp/public_html/site` | ファイルマネージャで確認 |
| `PHP` | 例 `/usr/bin/php`（or `…/php8.2`） | cron設定の例に表示 |

---

## A. バックアップ（保険）

1. 現行の静的ソース（zip）は手元に保管済みであることを確認。
2. Cloudflare Pages のプロジェクトは**そのまま温存**（切替失敗時の戻り先）。
3. （WPに既存データがある場合のみ）WPXの自動バックアップ or エクスポートを確認。

---

## B. WPX で WordPress を構築（DNSはまだ触らない）

### B-1. 親ドメインを追加
1. WPX サーバーパネル → **「ドメイン設定」**。
2. **「ドメイン設定追加」**タブ → `aiscompany.jp` を入力 →「確認画面へ進む」→「追加する」。
   - すでに追加済みなら次へ。

### B-2. サブドメイン `site` を追加
1. サーバーパネル → **「サブドメイン設定」**。
2. ドメイン `aiscompany.jp` を選択 → **「サブドメイン設定追加」**。
3. サブドメイン欄に **`site`** → 追加。→ `site.aiscompany.jp` が作成されます。
   - 反映直後はWPX内部で有効。公開はDNS次第（後述）。

### B-3. WordPress を簡単インストール
1. サーバーパネル → **「WordPress簡単インストール」**。
2. インストール先ドメインで **`site.aiscompany.jp`** を選択 → 「WordPressインストール」タブ。
3. 各項目：
   - サイトURL：`site.aiscompany.jp`（ディレクトリは空＝ルート）
   - ブログ名：APPREX（後で変更可）
   - ユーザー名／パスワード：管理用（強固に）
   - メールアドレス：受信できるアドレス
   - キャッシュ自動削除：ON、データベース：自動生成でOK
4. 「確認画面へ進む」→「インストールする」。完了後、管理URL `…/wp-admin/` を控える。

### B-4. テーマ `apprex` を導入
1. `…/wp-admin/` にログイン。
2. **外観 > テーマ > 新規追加 > テーマのアップロード** → `apprex-theme.zip` → 今すぐインストール → **有効化**。
3. 有効化で固定ページ・フロントページ・メニュー・導入事例が**自動生成**。
4. **設定 > パーマリンク** を開いて「変更を保存」。

### B-5. wp-config.php を編集（ファイルマネージャ）
1. サーバーパネル → **「ファイルマネージャ」**（WebFTP）。
2. `DOCROOT`（例 `…/public_html/site`）の **`wp-config.php`** を開く。
3. `/* 編集が必要なのはここまでです */` の**上**に追記：
   ```php
   define( 'APPREX_OPENROUTER_API_KEY', '【sk-or-...】' );
   define( 'APPREX_OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku' );
   define( 'DISABLE_WP_CRON', true ); // cronはサーバー側で（§H）
   ```
4. 保存。

---

## C. 切替前プレビュー（自分だけ新サイトを見る）

DNS切替前は `site.aiscompany.jp` がまだ Cloudflare を指すため、次のどちらかで確認します。

### 方法1：WPXの「動作確認URL」
- パベル上の確認用URL（例 `https://svXXX.xserver.jp/...`）があればそれで表示確認（SSL付き）。
- ただし WordPress は本来URLへ転送するため、リンク遷移で `site.aiscompany.jp` に飛ぶことあり。ざっと見る用途。

### 方法2：hosts ファイル（推奨・確実）
自分のPCだけ `site.aiscompany.jp` を WPX に向けます。

- **Windows**：メモ帳を「管理者として実行」→ `C:\Windows\System32\drivers\etc\hosts` を開く。
- **Mac / Linux**：ターミナルで `sudo nano /etc/hosts`。
- 末尾に1行追加（`IP` は控えた値）：
  ```
  183.xxx.xxx.xxx   site.aiscompany.jp
  ```
- 保存後、ブラウザのキャッシュをクリアして `http://site.aiscompany.jp/` を表示（この時点はhttp。SSLは切替後）。
- **確認できたら hosts の行は必ず削除**（戻す）。

### この段階でやる確認
- 全主要ページの表示、フォーム送信、チャット（APIキー設定後）、スマホ表示。

---

## D. 仕上げ：不足ページの作成（任意・推奨）

旧サイトにあって WP に未作成のページ：`利用規約 /terms`、`特定商取引法 /legal`、`補助金 /subsidy`、`ブログ /blog`。

- WP管理画面 **固定ページ > 新規追加** で作成し、スラッグを `terms` / `legal` / `subsidy` に。
- ブログは **投稿** で記事を作成（旧 blog-detail の「ノーコード開発ガイド」を移植推奨）。
- 作らない場合は §G の該当リダイレクト行を `#` でコメントアウト（404防止）。

---

## E. DNS 切替（Cloudflare）

> `site.aiscompany.jp` は Cloudflare（aiscompany.jp ゾーン）管理の前提。

### E-1. Pages からホスト名を解放
1. Cloudflare ダッシュボード → **Workers & Pages** → 該当 Pages プロジェクト。
2. **Custom domains** → `site.aiscompany.jp` → **Remove**（削除）。
   - これで `site` の自動CNAME（pages.dev宛・プロキシ）が外れます。

### E-2. A レコードを作成
1. Cloudflare → ドメイン **aiscompany.jp** → **DNS > Records**。
2. 既存の `site`（CNAME等）が残っていれば削除。
3. **Add record**：
   - Type：**A**
   - Name：**`site`**
   - IPv4 address：**`IP`**（WPXのIP）
   - Proxy status：**DNS only（グレー雲）** ← SSL発行のため必須
   - TTL：Auto
4. 保存。

### E-3. 反映確認（数分）
- 端末で：
  ```
  dig +short site.aiscompany.jp        # → IP が返ればOK
  nslookup site.aiscompany.jp          # 代替
  ```

---

## F. SSL 発行（Let's Encrypt 無料）

1. WPX サーバーパネル → **「SSL設定」** → ドメイン `aiscompany.jp` → 対象 `site.aiscompany.jp`。
2. **「独自SSL設定追加（無料Let's Encrypt）」** を実行。発行・反映に最大1時間。
3. `https://site.aiscompany.jp/` で鍵マーク確認。
4. （Cloudflareプロキシを使う場合のみ）SSL確認後にE-2の `site` を**オレンジ雲**へ。さらに Cloudflare **SSL/TLS > 概要** を **Full (strict)** に設定（Flexibleはリダイレクトループの原因。使わない）。

---

## G. 301 リダイレクト設置（.htaccess）

1. ファイルマネージャで `DOCROOT/.htaccess` を開く。
2. **`# BEGIN WordPress` の行より上**に以下を貼り付け（`googleb…html` はリダイレクトしない）：
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
   # --- ページ作成後に有効化（未作成なら # でコメントアウト） ---
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
3. 保存。

---

## H. サーバー cron（ステップメール／リマインダー）

WordPressの `wp-cron` はアクセス依存で遅延するため、サーバーcronで確実化します（§B-5で `DISABLE_WP_CRON` 済み）。

1. サーバーパネル → **「Cron設定」** → 追加。
2. スケジュール（1時間ごと）：分 `0` ／ 時 `*` ／ 日 `*` ／ 月 `*` ／ 曜 `*`。
3. コマンド：
   ```
   PHP DOCROOT/wp-cron.php >/dev/null 2>&1
   例) /usr/bin/php /home/xs123456/aiscompany.jp/public_html/site/wp-cron.php >/dev/null 2>&1
   ```
   - `DOCROOT`/`PHP` は実値に置換。パスはファイルマネージャのパンくず表示で確認可能。

---

## I. 切替後の動作確認（コマンド付き）

```bash
# 1) 名前解決がWPXを指す
dig +short site.aiscompany.jp

# 2) トップが200
curl -I https://site.aiscompany.jp/

# 3) 旧URLが301で新URLへ
curl -I https://site.aiscompany.jp/pricing.html      # → 301 / Location: /pricing/
curl -I https://site.aiscompany.jp/contact.html      # → 301 / Location: /contact/

# 4) 新ページが200
curl -I https://site.aiscompany.jp/estimate/
```

ブラウザでも：主要ページ表示／フォーム送信→自動返信メール受信／AIチャット応答／スマホ表示。

---

## J. 公開後 SEO

1. **Search Console**：同一プロパティ（`site.aiscompany.jp`）。`sitemap.xml` を再生成し再送信。
2. 主要旧URLの**301が効いているか**を実機確認（I-3）。
3. 必要なら Google確認ファイル（`googleb…html`）を新サイト直下に設置。
4. 内部リンク・外部告知のURLを順次新URLへ。

---

## K. ロールバック（不具合時・数分で復旧）

1. Cloudflare DNS の `site` を**元に戻す**：A→WPX を削除し、Pagesのカスタムドメインに `site.aiscompany.jp` を**再登録**（自動でCNAME復活）。
2. TTLを短縮してあるため数分で旧サイトに復帰。
3. 原因を WPX 側で直してから再挑戦。

---

## L. よくあるトラブル

| 症状 | 原因／対処 |
|------|-----------|
| SSLが発行できない | DNSがまだWPXを向いていない／Cloudflareがオレンジ雲。**グレー雲**にして発行 |
| リダイレクトループ | Cloudflare SSL/TLS が **Flexible**。**Full (strict)** に変更（オリジンに正規SSLがある状態で） |
| 管理画面や表示が旧URLに飛ぶ | WP一般設定のURLと実アクセスURLの不一致。プレビューは hosts 推奨 |
| 旧 .html が404 | `.htaccess` が `# BEGIN WordPress` より下にある／対象ページ未作成。順序とページ作成を確認 |
| メールが届かない | SMTPプラグイン未設定／迷惑メール。WP Mail SMTP 等を設定 |
| ステップメールが来ない | サーバーcron未設定／`DISABLE_WP_CRON` の整合。§H確認 |

---

### この手順で「実値」を埋めるために必要な3点
1. `IP`（WPXのIPアドレス）
2. `SID` と `DOCROOT`（cron用パス）
3. `site.aiscompany.jp` の DNS が Cloudflare 管理か

いただければ、`.htaccess`・DNS・cron を**完成形**にしてお渡しします。
