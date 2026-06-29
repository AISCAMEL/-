# LINE 公式アカウント × LIFF × 審査フォーム セットアップ＆実機テスト手順

carmel-core の LINE 連携（LIFF審査フォーム / 自動応答ボット / 通知）を本番に乗せ、
スマホ実機で確認するための手順。

関連実装：
- `[carmel_liff]`（`Carmel_LIFF`）… フォームページで LINE userId を hidden に自動セット
- WPForms ブリッジ（`Carmel_Application_Intake`）… フォーム送信→案件/反響＋`line_user_id`保存
- `Carmel_LINE_Bot`（`/wp-json/carmel/v1/line-webhook`）… 自動応答・会話ヒアリング
- `Carmel_LINE_Adapter`（`carmel_line_mode`）… 通知をプロライン→LINE公式へ段階切替

---

## 0. 用意するもの（LINE Developers）

1. **LINE公式アカウント**（Messaging API を有効化）→ プロバイダー＋Messaging APIチャネル作成
2. チャネルから取得：
   - **チャネルアクセストークン（長期）** … 返信・push に使用
   - **チャネルシークレット** … Webhook 署名検証に使用
3. **LIFF アプリ**を追加（チャネル → LIFF → 追加）
   - エンドポイントURL：審査フォームを置く WP ページURL（例 `https://carmelonline.jp/apply-line/`）
   - サイズ：Full、`profile` スコープ（userId取得に必要。emailは任意）
   - 発行される **LIFF ID**（例 `1657xxxxxx-XXXXXXXX`）と **LIFF URL**（`https://liff.line.me/{LIFF_ID}`）を控える

---

## 1. WordPress 側の設定

### 1-1. 定数 or オプション

`wp-config.php`（定数）か `wp_options` に設定：

| 用途 | 定数 / オプション |
|------|------------------|
| LIFF ID | `CARMEL_LIFF_ID` / `carmel_liff_id` |
| LIFFのLINEログインチャネルID（会員ログイン検証用） | `CARMEL_LIFF_CHANNEL_ID` / `carmel_liff_channel_id` |
| 会員ページURL（LINEメニューの「会員ページ」先＝会員ログインLIFFのURL） | `carmel_member_page_url` |
| チャネルアクセストークン | `CARMEL_LINE_CHANNEL_TOKEN` / `carmel_line_channel_token` |
| チャネルシークレット | `CARMEL_LINE_CHANNEL_SECRET` / `carmel_line_channel_secret` |
| 審査フォームのLIFF URL | `carmel_line_form_url`（= `https://liff.line.me/{LIFF_ID}`） |
| AI応答エンドポイント（任意） | `carmel_line_ai_endpoint`（GASの /exec URL） |
| 通知モード | `CARMEL_LINE_MODE` / `carmel_line_mode`（当面 `proline`、移行時 `line`） |

### 1-2. WPForms 取り込み対象（functions.php / mu-plugin）

```php
add_filter( 'carmel_wpforms_forms',         fn() => array( 7348, 7361 ) ); // 審査申込, 問い合わせ
add_filter( 'carmel_wpforms_inquiry_forms', fn() => array( 7361 ) );        // 7361は反響(スコア無し)

// LINE反響のエリア→担当加盟店ルーティング（エリア => carmel_store のID）
add_filter( 'carmel_area_store_map', fn() => array(
    '関東' => 12, '近畿' => 18, '中部' => 20, '九州・沖縄' => 25,
) );
```

### 1-3. 固定ページ

- **審査フォームページ**（LIFFエンドポイント）：WPForms `[wpforms id="7348"]` ＋ `[carmel_liff]` を設置
- フォームに **Hidden Field** を1つ追加し、CSSクラス or 名前を `line_user_id` に（無くても `[carmel_liff]` のJSが自動挿入＋WPFormsのAJAX送信に含まれる）

---

## 2. LINE 側の設定

1. **Webhook URL**：`https://carmelonline.jp/wp-json/carmel/v1/line-webhook` を登録 → 「検証」で 200 を確認
2. **Webhookの利用＝オン**、**応答メッセージ＝オフ**、あいさつメッセージは任意
3. **リッチメニュー**を作成し、ボタンに以下を割当：
   - 「審査申込」→ アクション「リンク」→ **LIFF URL**（`https://liff.line.me/{LIFF_ID}`）
   - 「在庫を見る」→ リンク → `https://carmelonline.jp/inventory/`
   - 「チャットで相談」→ アクション「テキスト」→ `相談` を送信（or 自動応答のクイックリプライから）

---

## 3. 実機テスト（スマホ）

### 3-1. 友だち追加 → 自動応答
- [ ] 公式アカウントを友だち追加 → **ウェルカム＋クイックリプライ**（在庫/審査申込/チャットで相談）が出る
- [ ] 「営業時間は？」等 → FAQが返る（`carmel_line_ai_endpoint` 設定時はAI応答）
- [ ] 「在庫見たい」→ 在庫リンク、「審査したい」→ 審査フォーム誘導

### 3-2. 会話型ヒアリング（反響起票＋エリア割当＋在庫提示）
- [ ] クイックリプライ「チャットで相談」→ 氏名→電話→**エリア選択**→内容 の順に質問される
- [ ] 完了で「受け付けました」＋**在庫カード（Flex）**が表示される（画像・価格・詳細ボタン）
- [ ] WP管理画面 `carmel_support` に **LINE反響：氏名**（`support_type=line_inquiry`・`line_user_id`・`area`・`store_id`）が作成される
- [ ] `carmel_area_store_map` に該当エリアがあれば **担当店が割当**され、その店舗にも `line_lead` 通知が届く（本部にも届く）
- [ ] 途中で「キャンセル」→ 中止できる

### 3-2b. 在庫カード（Flex）
- [ ] 「在庫見たい」→ **画像＋車名＋価格＋詳細ボタン**のカルーセルが返る
- [ ] 「詳細を見る」→ 在庫詳細（`?vehicle=ID`）が開く／末尾「在庫一覧へ」も動作

### 3-3. LIFF審査フォーム → 案件＋スコア＋LINE紐付け
- [ ] リッチメニュー「審査申込」タップ → LIFFで審査フォームページが開く（必要なら同意/ログイン）
- [ ] 氏名が空なら **LINE表示名が自動補完**される（`fill_name`）
- [ ] 送信 → WP に `carmel_deal`（`deal_type=loan`・`deal_status=provisional`）が作成される
- [ ] 申込者の WordPress ユーザーの user_meta に **`line_user_id` が保存**されている（重要）
- [ ] loan のため数分後に **AIスコア**（GAS設定時）が入り `scored` へ
- [ ] 問い合わせフォーム(7361)からの送信は **`is_lead=1` でスコアが走らない**こと

### 3-3b. LIFF ワンタップ会員ログイン
- [ ] 会員ページLIFFのエンドポイントページに `[carmel_liff_login]` を設置（LIFFスコープ＝openid必須、emailは任意）
- [ ] リッチメニュー/ボットの「会員ページ」→ そのLIFF URL（`carmel_member_page_url`）
- [ ] 申込済み（`line_user_id` 保存済み）の会員がタップ → **自動ログインして /mypage** が開く
- [ ] 未会員（line_user_id 未登録）→ 申込/審査ページへ誘導される
- [ ] 本部/加盟店/管理者アカウントは LINE 自動ログインの対象外（/login へ）
- [ ] `carmel_liff_channel_id` 未設定だと検証不可（申込へフォールバック）

### 3-4. 通知が LINE へ届く
- [ ] `/hq` 審査画面で「審査OK」→ 申込者に審査結果通知が届く
  - `carmel_line_mode=proline`（現状）：プロライン経由
  - `carmel_line_mode=line` ＋ トークン設定：**LINE公式pushで届く**（失敗時メール）
- [ ] 通知ログ `carmel_notify_log` に送信記録が残る

---

## 4. つまずきポイント

- **userId が保存されない**：LIFFは「LINEアプリ内ブラウザ」で開く必要あり（外部ブラウザでは `liff.getProfile` 不可）。リッチメニューのLIFF URLから開くこと。`profile` スコープ必須。
- **Webhook検証が失敗**：チャネルシークレット未設定だと署名検証で 401。`carmel_line_channel_secret` を設定。
- **返信が来ない**：チャネルアクセストークン未設定／応答メッセージがオンのまま（オフに）。
- **問い合わせがスコアされてしまう**：`carmel_wpforms_inquiry_forms` に該当フォームIDを入れる。
- **AI応答が返らない**：`carmel_line_ai_endpoint` 未設定時はFAQ/既定にフォールバック（正常）。GAS側は `{"reply":"..."}` を返すこと。

---

## 5. 段階移行（プロライン → LINE公式）

1. 当面は `carmel_line_mode=proline` のまま（現行どおり）
2. LINE公式の運用が固まったら `carmel_line_channel_token` を設定し、`carmel_line_mode=line` に変更
3. これで顧客向け通知が LINE公式 push に切替（宛先＝`line_user_id`、フォールバック＝メール）
4. 問題なければプロラインを停止
