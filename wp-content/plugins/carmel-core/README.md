# Carmel Core プラグイン

カーメル統合管理システムのコア機能を提供する WordPress プラグイン（Phase 1）。
要件定義書 v1.4 に対応。

## 提供機能（Phase 1）

| 機能 | 実装 |
|------|------|
| カスタム投稿タイプ 10種 | `includes/class-carmel-post-types.php` |
| 権限ロール 4階層＋キャパビリティ | `includes/class-carmel-roles.php` |
| ポータルのアクセス制御（/mypage・/store・/hq） | `includes/class-carmel-access-control.php` |
| 通知オーケストレーター＋4チャネルアダプタ | `includes/notifications/` |

### カスタム投稿タイプ（10）

`carmel_deal`（案件）/ `carmel_store`（加盟店）/ `carmel_vehicle`（在庫車両）/
`carmel_document`（書類）/ `carmel_repayment`（返済）/ `carmel_support`（サポート）/
`carmel_inspection`（車検）/ `carmel_insurance`（保険）/ `carmel_notify_log`（通知ログ）/
`carmel_content`（加盟店コンテンツ）

### 権限ロール（4）

`hq_admin`（本部管理者）/ `store_owner`（加盟店オーナー）/
`store_staff`（加盟店スタッフ）/ `customer`（ユーザー）

> 「自店のみ」のような行レベル制限はキャパビリティでは表現できないため、
> クエリフィルター（今後のポータル実装）で担保する。キャパは「操作の種類」を制御する。

### 通知（オーケストレーター方式）

`Carmel_Notifier::notify( $event_type, $context )` を単一の入口とし、
ルーティング表に従って対象者ごとに 4 チャネルへ振り分ける。

| チャネル | 主対象 | アダプタ |
|---------|-------|---------|
| プロライン（LINE） | 顧客（既定） | `Carmel_ProLine_Adapter` |
| **LINE公式（Messaging API）** | 顧客（移行先） | `Carmel_LINE_Adapter` |
| LINE WORKS | 社内 | `Carmel_LineWorks_Adapter` |
| Slack | 運用/開発 | `Carmel_Slack_Adapter` |
| メール | 正式・フォールバック | `Carmel_Mail_Adapter` |

**プロライン → LINE公式 への段階移行**：`carmel_line_channel_token`（チャネルアクセストークン）を設定し、`carmel_line_mode` を `line` にすると、顧客向けの `proline` 配信が **LINE Messaging API push** に自動で置き換わる（フォールバックはメール）。既定 `proline` の間はアダプタは登録されるだけで使われないので、いつでも切替できる。宛先は顧客 user_meta `line_user_id`（LIFFで取得）。

- 顧客向け LINE 送信が未連携／失敗時は **メールへ自動フォールバック**
- `event_id × recipient × channel` で**重複排除**
- 全送信を `carmel_notify_log` に記録

#### 発火例

```php
// 審査結果（OK）を通知
Carmel_Notifier::notify( 'screening_result', array(
    'deal_id' => 123,
    'vars'    => array( 'name' => '山田太郎', 'result' => 'OK' ),
) );

// あるいはアクション経由
do_action( 'carmel_event', 'inspection_notice', array(
    'deal_id' => 123,
    'vars'    => array( 'expiry_date' => '2026-09-01' ),
) );
```

#### チャネル接続情報

各アダプタは定数（`wp-config.php`）または `wp_options` から設定を読む。
未設定のチャネルは送信せず、ログに `failed`（未設定）として残るだけで全体は動作する。

| チャネル | 定数 / オプション |
|---------|------------------|
| プロライン | `CARMEL_PROLINE_ENDPOINT` / `CARMEL_PROLINE_TOKEN`（または `carmel_proline_endpoint` / `carmel_proline_token`） |
| LINE WORKS | `CARMEL_LINEWORKS_WEBHOOK`（または `carmel_lineworks_webhook`） |
| Slack | `CARMEL_SLACK_WEBHOOK`（または `carmel_slack_webhook`） |
| メール | 不要（`wp_mail`） |

ユーザーの LINE ID は user_meta `line_user_id`、所属加盟店は user_meta `store_id` を参照する。

## 有効化

1. `wp-content/plugins/carmel-core/` を配置
2. 管理画面でプラグインを有効化（CPT・ロール登録＋リライトルール flush が自動実行）

## 申込受付（Phase 2 実装済み）

`Carmel_Application_Intake` がフォーム送信を受けて **顧客アカウント自動発行 → 案件作成 → 申込受付通知** を一括処理する。フォーム非依存で、以下3経路から同じ `process()` を呼ぶ。

| 経路 | 配線 | フィールドマッピング |
|------|------|---------------------|
| Contact Form 7 | `wpcf7_mail_sent` | `carmel_cf7_field_map` フィルタ |
| Gravity Forms | `gform_after_submission` | `carmel_gform_field_map` フィルタ（フォームID別） |
| REST | `POST /wp-json/carmel/v1/application` | `X-Carmel-Token` ヘッダ必須 |

- **正規化キー**：`name`(必須) / `email`(必須) / `phone` / `deal_type`(loan/buyback/lease) / `message` / `extra[]`
- **アカウント発行**：同一メールの既存ユーザーは再利用、新規は `customer` ロールで作成。**平文パスワードは送らず**、パスワード設定（マジック）リンクを通知に含める（要件§13-#5の安全な既定）
- **初期ステータス**：loan→`provisional` / buyback→`appraisal_request` / lease→`lease_request`
- 完了後 `carmel_application_created` アクションを発火（後続連携用）

```php
$result = Carmel_Application_Intake::process( array(
    'name'      => '山田太郎',
    'email'     => 'taro@example.com',
    'phone'     => '090-0000-0000',
    'deal_type' => 'loan',
    'message'   => 'ローン審査希望',
) );
// => [ 'deal_id' => 45, 'customer_id' => 12, 'created_account' => true ]
```

REST エンドポイントは `CARMEL_INTAKE_TOKEN`（定数）または `carmel_intake_token`（オプション）未設定時は既定で拒否（`carmel_intake_allow_unauthenticated` フィルタで開放可）。

### 組み込み申込フォーム（`[carmel_application_form]`）

フォームプラグイン不要の**自己完結フォーム**。HPの任意ページに設置でき、送信は `Carmel_Application_Intake::process()` に直結（アカウント自動発行＋案件作成＋通知）。

- 属性：`type="loan|buyback|lease"`（種別を固定し選択欄を隠す）、`thanks="/thanks"`（完了後リダイレクト先）、`title`
- 項目：種別・氏名(必須)・メール(必須)・電話・**住所（納車先＝陸送費計算に使用）**・ご要望
- スパム対策：nonce ＋ ハニーポット。未ログイン送信に対応（admin-post nopriv）
- 例：`[carmel_application_form type="loan" thanks="/thanks"]`

> 既存のフォーム（CF7 / Gravity Forms / 独自・REST）からも接続可。`address` を含む正規化キーで受け取り、住所は陸送費自動計算に利用される。

## ステータス遷移（Phase 2 実装済み）

`Carmel_Deal_Status` が案件ステータスのステートマシン。**ステータスが変わると通知・在庫連動・監査ログが自動発火**する。

```php
// 本部が審査OKに（cap: carmel_screening を要求 → screening_result 通知）
Carmel_Deal_Status::change( $deal_id, 'approved' );

// 却下理由を添えて（NG通知の vars に渡る）
Carmel_Deal_Status::change( $deal_id, 'rejected', array(
    'vars' => array( 'result' => 'NG（収入条件未充足）' ),
) );

// Cron 等の自動処理は権限チェックをスキップ
Carmel_Deal_Status::change( $deal_id, 'delivered', array( 'system' => true ) );
```

- **権限（§5.5）**：遷移先ごとに必要 cap を判定（`approved`/`rejected`→`carmel_screening`、`contracted`→`carmel_send_contract`、他→`carmel_change_deal_status`）。`system=true` で自動処理はスキップ
- **通知連動**：`approved`/`rejected`→`screening_result`、`matched`→`store_assigned`、`contracted`→`contract_sign_request`、`delivery_prep`/`delivered`→`delivery_date_fixed`
- **在庫連動（§6.3）**：`matched`→商談中 / `contracted`→売約済 / `delivered`→納車済（`vehicle_id` 紐付け車両）
- **監査ログ（§10）**：from/to・実行者・日時を `_carmel_status_history` に追記
- 処理完了で `carmel_deal_status_changed` アクションを発火
- 管理画面でメタを直接変更した場合も**メタ変更リスナー**経由で同じ処理が走る（経路を問わず一貫）

各マップは `carmel_transition_caps` / `carmel_status_events` / `carmel_vehicle_status_map` フィルタで調整可能。

## 本部 審査管理画面（Phase 2 実装済み）

ショートコード **`[carmel_hq_screening]`** を /hq ページに設置すると、信販審査キューが表示される（`carmel_screening` cap を持つ本部のみ）。

- 審査待ち案件（`provisional` / `scored` / `screening`）を一覧表示。申込者・種別・AIスコア・現在ステータスを表示
- 各案件に **審査開始 / 審査OK / 審査NG（理由入力）** ボタン
- ボタン押下 → `admin-post.php`（nonce 検証 + cap チェック）→ `Carmel_Deal_Status::change()` を実行
  - これにより**顧客への審査結果通知・在庫連動・監査ログが自動発火**
- NG時は `screening_result` / `screening_reason` メタも保存し、NG理由を通知 vars に反映
- 処理後はバナーで結果表示（送信成功／権限エラー等）

> /hq ページ自体のアクセス制御は `Carmel_Access_Control` が担当（未ログイン→/login、権限不足→403）。本ショートコードは二重に cap を確認する。

## 顧客マイページ（Phase 2 実装済み）

ショートコード **`[carmel_mypage]`** を /mypage ページに設置すると、ログイン顧客**自身の案件のみ**を表示する（`customer_id` でフィルター）。

- **ローン案件**：7段階の **PHASEステッパー**（仮申込→審査中→審査結果→契約→納車準備→納車済→アフター）で現在地を可視化。本部の審査操作（OK/NG）がそのまま反映される
  - 審査NG時は理由を表示し再申込を案内、OK時は通過メッセージ
- **買取/リース案件**：現在ステータスを簡易表示
- **アフター（納車後）**：紐付け車両の車検満了日・保険満了日の**カウントダウン**（30日以内は強調表示）
- CSS同梱・テーマ非依存

> /hq の審査画面でステータスを変えると、この /mypage の表示と顧客への通知が連動して更新される（本部↔顧客の対の画面）。

## 加盟店ポータル（Phase 2 実装済み）

ショートコード **`[carmel_store]`** を /store ページに設置すると、加盟店ダッシュボードを表示する（`store_owner` / `store_staff`）。

- **行レベルのアクセス制御**：ユーザーの user_meta `store_id` と案件の `store_id` が一致する案件のみ表示・操作可。他店舗の案件は閲覧・操作とも 403
- **ダッシュボード**：担当案件数・ステータス別カウントのサマリーカード
- **案件一覧**：申込者・種別・ステータス・操作ボタン
- **工程を進めるボタン**：加盟店が担当する前進遷移のみ提示（loan: matched→書類準備→（本部の契約後）→納車準備→納車済→アフター→クローズ、buyback/lease は各フロー）
  - **本部専用の遷移（審査・契約）は提示せず**、`doc_prep` 等では「本部の手続き待ち」と表示。万一POSTされても `Carmel_Deal_Status` の cap で二重ブロック
  - 納車準備へ進める際は**納車予定日**を入力でき、`delivery_date_fixed` 通知に反映
- 押下 → `admin-post.php`（nonce＋cap＋**自店所属チェック**＋遷移妥当性チェック）→ `Carmel_Deal_Status::change()` → 通知・在庫連動・監査ログ発火

> HQユーザー（`carmel_manage_stores`）は全店の案件を横断表示・操作できる。

## 定期処理 WP-Cron（Phase 3 実装済み）

`Carmel_Cron` が毎日／毎週のジョブを実行し、すべて通知オーケストレーター経由で送る（dedup・フォールバック・ログが効く）。有効化時に自動スケジュール、`init` で自己修復（未登録なら再スケジュール）。

| ジョブ | 内容 |
|--------|------|
| 毎朝8:00 | **返済リマインダー**（期日3日前/前日/当日・未払いのみ）／**延滞検知＋利息計算**（`元金×14.6%÷365×延滞日数`、1/5/14日目に督促）／**車検満了アラート**（90/60/30日前）／**保険更新アラート**（90/30日前） |
| 毎週9:00 | **週次レポート**（案件総数・種別内訳）を Slack＋本部メールへ |

- 同一日の重複送信は通知ログの冪等性キーで抑止（例 `delinquency:{repayment_id}:{延滞日数}`）
- 延滞日数・延滞利息は `carmel_repayment` のメタに書き戻し
- 車検は `carmel_vehicle.inspection_expiry`＋`linked_deal_id`、保険は `carmel_insurance.end_date`＋`deal_id` を参照
- 手動実行：`Carmel_Cron::instance()->run_daily()` / `run_weekly()`

> §9.4 の方針どおり、返済・延滞・アフターの定期通知を GAS トリガーから WP-Cron に一本化。

## GAS連携（Phase 3 実装済み）

`Carmel_GAS_Client` が WP→GAS の呼び出しと結果の書き戻しを担う（WPが正、GASはサービス）。

| 機能 | 内容 |
|------|------|
| AIスコアリング | ローン申込作成時に**自動でスコア依頼**（単発Cronで非同期化しフォーム応答をブロックしない）。結果を `ai_score`/`score_rank` に保存し、`provisional`→`scored` に前進 |
| 書類生成 | 案件が `doc_prep` に入ると申込書PDFを依頼。結果を `carmel_document`（`pdf_url`/`generated_at`）として保存 |
| 認証 | 送信は `X-Carmel-Token` ヘッダ＋body `token`（Secret Token） |
| 非同期コールバック | `POST /wp-json/carmel/v1/gas-callback`（トークン検証）。`type=score|document` で書き戻し。GAS側が時間のかかる処理を後から返せる |
| 失敗時 | GAS呼び出し失敗は `system_error` 通知で Slack へ自動アラート |

設定（未設定なら安全に no-op）：

| 定数 / オプション | 用途 |
|------------------|------|
| `CARMEL_GAS_ENDPOINT` / `carmel_gas_endpoint` | GAS Web アプリ URL（doPost） |
| `CARMEL_GAS_TOKEN` / `carmel_gas_token` | Secret Token（送信・コールバック共通） |

GASへ送る案件ペイロードは `carmel_gas_deal_payload` フィルタで拡張可能。
スコアは /hq 審査画面の「AIスコア」列に表示される。

## 陸送費計算（Phase 3 実装済み）

`Carmel_Transport` が Google Maps Distance Matrix API で距離を取得し、単価テーブルで費用を自動算出する。

- **トリガー**：案件が `delivery_prep`（納車準備）に入ると自動計算
- **出発地**：加盟店の `store_address`（`carmel_transport_origin` で上書き可）
- **納車先**：案件 `applicant_address` → 顧客 user_meta `address` の順（`carmel_transport_destination`）
- **料金式**：`fee = base + per_km × km`（最低料金あり）。`carmel_transport_rates` オプション／フィルタで本部設定可（既定 base=10,000 / per_km=80 / min=10,000）
- **保存**：`transport_from` / `transport_to` / `transport_distance_km` / `transport_fee` を案件メタに保存。/mypage の納車準備フェーズに**陸送費・距離を表示**
- **失敗時**：Maps API 失敗は `system_error`→Slack へ通知
- API キー：`CARMEL_MAPS_API_KEY` / `carmel_maps_api_key`（未設定なら no-op）
- 手動計算：`Carmel_Transport::instance()->calculate( $deal_id )`

## Square / WooCommerce 決済（Phase 3 実装済み）

`Carmel_Payments` が決済結果を案件に紐付ける（**車両本体代金は対象外**・§8.2）。

| 決済対象 | type |
|---------|------|
| 申込金・手付金 | `deposit` |
| 保証プラン | `warranty` |
| オプション | `option` |
| 加盟店販促購入費 | `promo` |
| 会費 | `membership` |

- **WooCommerce 連携**：注文に `carmel_deal_id` / `carmel_payment_type` メタを持たせる。`woocommerce_order_status_completed`/`payment_complete` で入金記録＋`payment_completed` 通知、`failed` で `payment_failed` 通知（WC未導入でもフック追加は無害）
- **Square webhook**：`POST /wp-json/carmel/v1/square-webhook`。**HMAC-SHA256 署名検証**（`base64(HMAC(notification_url + body, signature_key))`、JPYは最小単位=円）。`payment.created`/`payment.updated` で `reference_id`＝案件ID にマッピングし入金記録
- **記録**：`payment_{type}_status` / `payment_{type}_amount` を案件メタに保存し、`_carmel_payments` に履歴追記。`carmel_payment_recorded` アクション発火
- 設定：`CARMEL_SQUARE_SIGNATURE_KEY` / `carmel_square_signature_key`、`CARMEL_SQUARE_WEBHOOK_URL` / `carmel_square_webhook_url`（署名キー未設定なら webhook は拒否）

## レポート / 売上ダッシュボード（Phase 4 実装済み）

ショートコード **`[carmel_hq_reports]`**。既存データを集計して可視化（`carmel_view_reports` cap）。

- **スコープ**：本部（`carmel_manage_stores`）は全店、加盟店オーナーは自店のみ
- **KPIカード**：案件総数・成約数・**転換率**（成約以降のステータス÷総数）・売上合計
- **業務種別内訳**：ローン/買取/リース
- **売上内訳**：決済種別ごと（`_carmel_payments` の paid を集計）
- **ステータス分布**／**加盟店別案件数**（本部のみ）
- **在庫・問い合わせKPI**：在庫総数・公開数・在庫ステータス内訳・在庫問い合わせ件数（直近30日含む）
- **CSV出力**：スコープ内案件を CSV ダウンロード（Excel 対応の UTF-8 BOM 付き、nonce 検証）

## 本部統合ダッシュボード（実装済み）

`Carmel_HQ_Dashboard`。ショートコード **`[carmel_hq_dashboard]`**（`carmel_view_reports`）。/hq トップに主要KPIを集約。

- **案件**（総数・成約・転換率・進行中・売上合計）／**在庫・問い合わせ**（在庫総数・掲載中・問い合わせ累計/30日）／**在庫共有・コミュニティ**（手数料未精算 件数/金額・トピック数・未回答数）
- スコープ：本部=全店 / 加盟店オーナー=自店。各管理画面への導線つき

## 在庫のSEO（構造化データ・実装済み）

在庫詳細（`?vehicle=ID`・公開車両）で `wp_head` に **schema.org/Car の JSON-LD** と **OGPメタ**（og:title/og:image/product:price）を出力。検索エンジン・SNSシェアでの見え方を最適化。

## マネーフォワード契約（Phase 4 実装済み・本部専用）

`Carmel_MF_Contract`。ショートコード **`[carmel_hq_contracts]`**（`carmel_send_contract` cap = 本部のみ。加盟店は送付不可）。

- 契約待ち案件（`doc_prep` / `contracted`）を一覧。**マネーフォワード契約 送付**ボタンで署名依頼
- 送付 → `mf_contract_id`/`mf_contract_status=sent` を保存、`contract`（売買契約書）`carmel_document` を作成、顧客へ `contract_sign_request` 通知
- **署名完了 webhook**：`POST /wp-json/carmel/v1/mf-contract-callback`（トークン検証）。`status=completed` で署名済PDFを保存し、案件を **`contracted` へ自動前進**（system でHQ capをバイパス）。`rejected` も記録
- 送付失敗は `system_error`→Slack
- 設定：`CARMEL_MF_ENDPOINT` / `CARMEL_MF_TOKEN`（または同名オプション）。リクエスト形は `carmel_mf_contract_payload` フィルタで調整

> 加盟店の電子署名は加盟店独自の外部サービス（システム外・§14-3）。本機能は本部の契約送付のみ。

## 書類アップロード（Phase 4 実装済み）

`Carmel_Documents`。ショートコード **`[carmel_upload]`** を /mypage に設置すると、顧客が自分の案件に本人確認書類・収入証明等を提出できる。

- **保護保存**：`uploads/carmel-secure/{deal_id}/` に保存し、`deny all` の .htaccess を自動設置（直URLアクセス不可）
- **権限付きダウンロード**：公開URLを使わず `admin-post.php?action=carmel_doc_download`（nonce＋権限チェック＋パス封じ込め）で配信。閲覧可は**案件の顧客／担当店／本部のみ**
- **検証**：JPG/PNG/PDF・最大10MB、`wp_check_filetype` で MIME 検証
- アップロードは `carmel_document`（`doc_type=customer_upload`・`category`・`file_path`・`uploaded_by`）として記録、`carmel_document_uploaded` 発火
- 提出済み一覧を案件ごとに表示

## 返済状況の顧客表示（Phase 4 実装済み）

`[carmel_mypage]` の案件カードに**返済スケジュール**を表示（`carmel_repayment` を `deal_id` で取得）。

- 入金済 N/M 回・**次回お支払い日**のサマリー
- 期日順テーブルで各回を **入金済／予定／延滞（延滞日数）** に色分け表示
- ローン・リース双方に対応（返済レコードがある案件のみ表示）

## ACF フィールド定義（実装済み）

`Carmel_ACF_Fields` が全CPTのフィールド群を**コードで登録**（`acf_add_local_field_group`・`acf/init`）。ACF Pro 有効時のみ動作し、未導入なら no-op。

- **carmel_deal**：共通フィールド＋**deal_type 別フィールドを条件表示**（loan: AIスコア/審査結果/陸送費/契約状況、buyback: 査定額/入金、lease: 月額/期間/GPS）
- **carmel_store / carmel_vehicle / carmel_repayment / carmel_inspection / carmel_insurance** も各項目を定義
- 在庫・車検・保険・案件の関連は `post_object`（ID返却）で相互参照。日付は date_picker（`Y-m-d`）
- フィールドキーはグループ接頭辞で一意化。DB非依存でバージョン管理可能

> CPT登録（`Carmel_Post_Types`）＋本フィールド定義により、ACF Pro 導入後は管理画面で案件・車両・返済等を直接編集できる（メタ変更は `Carmel_Deal_Status` のリスナーにも連動）。

## 全加盟店横断カンバン（実装済み）

`Carmel_HQ_Board`。ショートコード **`[carmel_hq_board]`**（HQ専用 `carmel_manage_stores`）。

- 業務種別タブ（ローン/買取/リース）＋加盟店フィルターで、案件を**ステータス列**にカード表示
- **ドラッグ＆ドロップ**で列間移動 → admin-ajax（`check_ajax_referer`＋cap）→ `Carmel_Deal_Status::change()`
  - 移動と同時に通知・在庫連動・監査ログが発火（既存基盤を再利用）
- カードは案件番号・申込者・加盟店名を表示。結果はバナーでフィードバック

## 会費（メンバーシップ・実装済み）

`Carmel_Membership`。加盟店（`carmel_store`）の会費を管理。§13-#1 の決定が未確定でも動くよう**3モード対応**：

- (a) **WooCommerce Subscriptions**：`woocommerce_subscription_status_active/expired/cancelled` で会費ステータスを同期（サブスクメタ `carmel_store_id` か購読者の `store_id` で店舗解決）
- (b) **都度／銀行振込（手動）**：本部が店舗のACF会費フィールド（`membership_status`/`membership_next_billing`/`membership_plan`/`membership_fee`）を編集
- (c) **共通**：WP-Cron が請求日の **7日前/前日に更新案内**、期日超過で**期限切れ**に変更し本部・店舗へ通知（`membership_renewal`/`membership_expired`）

## コミュニティ・学習コンテンツ（実装済み）

`Carmel_Community`。ショートコード **`[carmel_learning]`**。

- **学習コンテンツ**（Notion 外部リンク）：店舗の `notion_url`（無ければ `carmel_notion_url` オプション）。店舗/本部スタッフのみ表示
- **コミュニティ**（bbPress）：`carmel_community_url` オプション、または bbPress 有効時はフォーラムURLを自動取得。ログインユーザー全員に表示

---

## 統合ログイン画面（実装済み）

`Carmel_Login`／`[carmel_login]` を `/login` に設置。

- WordPress コア認証（`wp_login_form` → wp-login.php）をブランド付きUIでラップ。セキュリティはコア準拠
- ログイン後は**ロール別に自動振り分け**（顧客→/mypage、加盟店→/store、本部→/hq）。`?redirect_to=` の安全なディープリンクは尊重
- ログイン失敗は `/login` に戻りエラー表示（標準の wp-login 画面に飛ばさない）
- ログイン済みなら「自分のポータルを開く」＋ログアウトを表示
- パスワード再設定リンク付き。申込/加盟店発行で送るのは「パスワード設定リンク」で、初回はそこから設定

> 認証は1つ。顧客・加盟店・本部が同じ画面からログインし、入った先（体験）だけがロールで分かれる。

**ブランド既定値**：wordmark `CarMel`／tagline「ネットで安心してクルマ頼める！」／カラー紫（brand `#5b2a86`・accent `#7c3aed`）。

ロゴ画像は `assets/logo.png` を置けば自動採用（`assets/README.md` 参照）。属性でも上書き可：
```
[carmel_login
  logo="https://carmelonline.jp/logo.png"   ← ロゴ画像URL（無ければ assets/logo.png → 文字ロゴ）
  wordmark="CarMel"                          ← 文字ロゴ
  tagline="ネットで安心してクルマ頼める！"      ← キャッチコピー
  brand="#5b2a86" accent="#7c3aed"]          ← メイン／アクセントカラー（紫）
```
中央寄せのカード型UI（ロゴ＋キャッチ＋フォーム）。配色はブランド変数で全体に反映。プレビュー：`docs/login-preview.html`（ブラウザで確認可）。

## 加盟店ライフサイクル（本部管理・実装済み）

会員（顧客）と加盟店は**同一WPで運用**し、ロール＋行レベル制御で仕切る（別システムにしない）。加盟店の応募〜発行〜運用は**本部が一元管理**する。

| 機能 | 実装 |
|------|------|
| 加盟店募集フォーム（公開・応募） | `Carmel_Franchise`／`[carmel_franchise_form]`。応募で `carmel_store` を下書き（`application_status=pending`）作成し本部へ通知 |
| 本部 加盟店管理 | `Carmel_HQ_Stores`／`[carmel_hq_stores]`（`carmel_manage_stores`）。応募一覧の**承認/却下**、加盟店一覧（オーナー・会費・担当案件数） |
| 承認＝オーナー発行 | 承認で店舗を公開化＋`store_owner` アカウント自動発行（`owner_user_id`／オーナーの `store_id` を相互リンク）。ログイン設定リンクを通知 |
| オーナー→スタッフ発行 | `/store` にスタッフ追加フォーム（`carmel_manage_staff`）。`store_staff` を発行し自店 `store_id` に紐付け、設定リンクをメール送付 |
| ログイン後の自動振り分け | `login_redirect` でロール別に誘導（顧客→/mypage、加盟店→/store、本部→/hq）。`carmel_role_home` フィルタで変更可 |

> 認証基盤は1つ（wp-login）。「入口・体験」をロールで分離し、顧客向け導線を経由せず各ポータルへ入れる。顧客申込（`carmel_deal`）と加盟店応募（`carmel_store`）は別フロー。

## 共通デザイン層（実装済み）

`Carmel_Assets` が全ポータル共通のスタイルをフッターで出力（各ショートコードの個別スタイルを上書き統一）。

- **書体**：日本語優先のシステムフォント（Hiragino Sans／Noto Sans JP／Yu Gothic／Meiryo…）＋アンチエイリアス。`CARMEL_WEBFONT`（or `carmel_webfont`）に URL を設定すれば Noto Sans JP 等の Web フォントも読み込み可
- **可読性**：本文 line-height 1.75、見出し字間調整、テーブルの落ち着いた配色
- **スマホ最適化**：入力は 16px（iOS自動ズーム防止）、ボタンは 44px のタップ領域、横長テーブルは横スクロール、カード/カラム/KPIは縦積み、主要ボタンは全幅、ステッパー折り返し
- **ブランド統一**：バッジ・主要ボタン・タブ・KPI数値などを紫（`--carmel-brand`/`--carmel-accent`）に統一。緑/赤は「承認/却下」等の意味色として保持

> シンプル・モバイルファースト・読みやすい書体を全ポータルに一括適用。配色は CSS 変数で一元管理。

## 加盟店コンテンツの作成（本部・フロントエンド・実装済み）

`Carmel_HQ_Content`。ショートコード **`[carmel_hq_content]`**（`carmel_manage_stores`＝本部のみ）。wp-admin を開かずに加盟店向けコンテンツを作成・編集・公開・削除できる。

- **種別**：スタートガイド(guide)／お知らせ(notice)／マニュアル(manual)／FAQ(faq)／販促ツール(promo)
- 入力：タイトル・概要・本文(HTML可・`wp_kses_post`)・添付URL・重要(固定)・表示順(ガイド)・**加盟店へ通知**
- 公開すると加盟店の **`[carmel_store_content]`（/store-content）** に表示。「加盟店へ通知」ONで全加盟店へ一斉通知（`store_notice`・記事単位で冪等）
- 公開中コンテンツの一覧から編集（`?edit=ID`）・削除。nonce＋本部capで保護

> wp-admin の `carmel_content` 直接編集も従来どおり可。本ショートコードはフロントからの簡易作成手段。

## 加盟店向けコンテンツ（実装済み）

`Carmel_Store_Content`／ショートコード **`[carmel_store_content]`**。本部が wp-admin で「加盟店コンテンツ（`carmel_content`）」を作成し、加盟店が `/store` で閲覧。

- **種別**：スタートガイド（guide）／お知らせ（notice）／マニュアル・資料（manual・DLリンク）／FAQ（アコーディオン）／販促ツール（promo）
- **スタートガイド（始め方マニュアル）**：`step_order` の番号順に表示する**オンボーディング手順**。`[carmel_store_content]` の最上部にステップ式（アコーディオン）で表示し、`/store` ダッシュボードには**「スタートガイドを開く」導線**を表示
- **初期コンテンツ自動投入**：`Carmel_Content_Seeder` が有効化時に**始め方マニュアル（9ステップ）＋各種マニュアル＋FAQ**を投入（冪等・`_carmel_seeded` で識別、本部は自由に編集可）。`carmel_seed_content` フィルタで追加・上書き可
- **重要フラグ**で上部固定、**概要**で一覧表示、**添付URL**で資料配布
- `/store` ダッシュボード上部に**最新お知らせ**を自動表示（`carmel_store_dashboard_top` フック）
- 「公開時に加盟店へ通知」をONにすると、公開時に**全加盟店へ一斉通知**（通知オーケストレーターの新オーディエンス `all_stores`）
- 閲覧は加盟店・本部のみ（顧客には非表示）

## 帳票・契約書テンプレート発行（加盟店→ユーザー・実装済み）

`Carmel_Billing`。加盟店が自店の案件に対し、ユーザー向けの帳票・契約書を**発行して引き出せる**。発行物は `carmel_document`（`doc_group=billing|contract`・`doc_type` に種別）として保存され、**ユーザーのマイページにも表示**される。

- **加盟店画面**：ショートコード **`[carmel_store_billing]`**（`carmel_change_deal_status`。自店案件のみ／本部は全店）
  - **見積書・請求書**：明細（品目・数量・単価）を入力すると消費税・合計を**自動計算**（JS）。発行日・期限・備考つき
  - **契約書テンプレート**：**売買契約書／自社リース契約書／保証書／委任状／譲渡証明書**。車両・金額・お客様情報を案件データから**自動差し込み**（`{{placeholder}}`）
  - **発行済み一覧**：表示・印刷（A4印刷用HTML／ブラウザでPDF保存）・削除
- **ユーザー画面**：`[carmel_mypage]` の案件カードに発行書類を自動表示。専用一覧 **`[carmel_my_documents]`** も提供
- **アクセス制御**：発行は自店＋本部、閲覧は**発行側＋宛先のユーザー本人**のみ。表示は nonce 付き `admin-post.php?action=carmel_billing_view`
- **通知**：発行時にユーザーへ `document_issued`（プロライン→メール）。ルーティング/文面はフィルタで注入（notifier 本体は不変更）
- 設定：消費税率 `carmel_tax_rate`（既定10）、発行元会社情報 `carmel_company_info`（社名・住所・TEL・登録番号＝インボイス）。テンプレート本文は `carmel_contract_templates`、差し込み値は `carmel_contract_vars` で上書き可
- プログラムAPI：`Carmel_Billing::instance()->create_billing( $deal_id, 'quote'|'invoice'|…, $items, $opts )` / `create_contract( $deal_id, 'sales_contract'|…, $extra )`

> 本部の**電子契約（マネーフォワード契約・署名）**は `Carmel_MF_Contract` が担当。本機能は「加盟店が手元で作成・印刷するテンプレート帳票」を担い、役割を分離している。

## 販売支援（加盟店・実装済み）

`Carmel_Sales_Support`。ショートコード **`[carmel_sales_support]`**（`carmel_change_deal_status`）。販売・成約を支援するツールを1画面に集約。試算結果は**そのまま見積書として発行**できる（`Carmel_Billing` 連携）。

| ツール | 内容 |
|--------|------|
| 🛡 **保証** | 保証プラン（`carmel_warranty_plans`・既定3プラン）を案件に適用 → 保証情報を記録（`warranty_*` メタ）し**保証書を発行** |
| 🚚 **陸送** | ボタンで `Carmel_Transport::calculate()` を実行し陸送費を即時表示（店舗住所〜納車先） |
| 💳 **オートローン** | 車両価格・頭金・回数・実質年率から**元利均等の月々支払い**を試算（JS即時）→ 支払シミュレーションを見積書として発行 |
| 🔑 **自社リース** | 車両価格・残価率・期間・年率から**月額リース料**を試算 → 案件の `monthly_payment`/`lease_term` を更新し見積書を発行 |
| 📣 **販促ツール** | 本部が `carmel_content`（種別=販促ツール `promo`）で配布したPOP・チラシ等を**ダウンロード** |

- 計算ロジックは再利用可能な公開メソッド：`Carmel_Sales_Support::monthly_payment()` / `lease_monthly()`
- 金利・リースの既定値は `carmel_finance_defaults`（年率・回数・残価率）で本部設定可
- 加盟店ダッシュボード（`[carmel_store]`）上部に**「帳票・契約書を発行」「販売支援」への導線**を自動表示（リンク先スラッグは `carmel_billing_page_slug`/`carmel_sales_support_page_slug` フィルタで変更可）

## 在庫掲載・在庫共有（ログイン分け・実装済み）

`Carmel_Inventory`。1つの在庫データ（`carmel_vehicle`）を**見る人によって表示を変える（ログイン分け）**。

- **カーメル在庫ページ `[carmel_inventory]`**（公開ページ／`/inventory` 想定）
  - **公開フラグON＋販売可能**（在庫ステータス 販売中/商談中）の車両のみ掲載。画像・メーカー/車種・年式・走行・色・**小売価格**を表示
  - **ログイン分け**：未ログイン→「ログインして相談」、お客様→「このお車を相談する」（申込ページに `?vehicle=ID` 連携）、加盟店/本部→加盟店在庫ページへ誘導
  - **仕入原価・車台番号などの内部情報は公開しない**。メーカー絞り込み・価格上限・キーワード検索つき
- **加盟店在庫 `[carmel_store_inventory]`**（`/store-inventory`・加盟店/本部）
  - **自店在庫**：全ステータス（未公開含む）を表示し、**原価表示・公開/非公開の切替・編集**が可能
  - **在庫共有（ネットワーク）**：他店の公開在庫を横断表示。**小売価格のみ**（原価は出さない）で、**「取り寄せ・商談を依頼」**ボタンから保有店＋本部へ通知（`inventory_inquiry`）し、**依頼元店舗の商談（案件）を自動起票**（`source_store_id`＝保有店を自動セット→手数料配分が自動連動。重複は再利用）
- 公開切替・依頼は nonce＋自店スコープ検証つき admin-post。サムネイルは `post-thumbnails` を有効化してテーマ非依存で表示

> 原価の可視範囲：**本部＝全件 / 加盟店＝自店のみ**。他店在庫には小売価格のみ共有され、共有から商談につなげられる。

### 在庫詳細ページ・CSV一括取込

- **在庫詳細**：`[carmel_inventory]` で車両名をクリックすると詳細表示（`?vehicle=ID`）。スペック表・説明・価格・取扱店・CTA。**未公開車両は加盟店/本部のみ閲覧**、車台番号/ナンバー/所在地は加盟店・本部のみ、原価は本部/自店のみ。**お客様はこの詳細から問い合わせフォーム**で送信でき、保有店＋本部へ通知＋`carmel_support` に記録
- **CSV一括取込（VIN upsert・画像URL）**：`[carmel_store_inventory]` の「在庫をCSVで一括取込」から、`maker,model,grade,year,mileage,color,vin,plate_no,price,cost,vehicle_status,published,image_urls`（本部は `store_id` 列で店舗指定可）をまとめて登録。UTF-8/Shift_JIS対応・最大500行・「取込時に全公開」オプション・**記入例つきテンプレCSVのDL**。**車台番号(VIN)が一致する既存在庫は更新（upsert）**。**`image_urls`（`|`区切り）で画像URLをサイドロード**し添付＋先頭をアイキャッチに（1台最大6枚・取込ごとの総数制限あり）。結果は「新規N件・更新M件」で表示
- **SNSシェア・地図**：在庫詳細に **LINE / X / Facebook / URLコピー**のシェアボタン。**取扱店の所在地マップ**を埋め込み表示（`CARMEL_MAPS_API_KEY` 設定時、Embed API。未設定でも「Googleマップで開く」リンク）。一覧カードにも「🗺地図」リンク
- **在庫マップ（複数マーカー）**：一覧上部に取扱店を**ジオコーディングしてマーカー表示**（Maps JS API）。緯度経度は店舗メタ（`store_lat`/`store_lng`）にキャッシュし、1表示あたりの新規ジオコーディング数を制限。マーカーから詳細へ遷移
- **お気に入り**：ログインユーザーは各車両を♥でお気に入り登録（`carmel_favorites` user_meta）。`?fav=1` でお気に入りのみ表示、タブで切替。**お気に入りをまとめて比較**（サーバー側のお気に入りから比較URLを生成）
- **比較**：カードの「比較に追加」で最大4台を選択（localStorage）。比較バーから side-by-side の**比較表**（画像・価格・スペック・取扱店）を表示
- **保存検索・新着アラート（頻度設定）**：現在の絞り込み条件を保存（`carmel_saved_searches` user_meta・最大10件）。**頻度＝即時/日次/週次**を選択。即時は在庫公開を検知して即通知、日次/週次はcronで前回以降の新着を検出し本人へ通知（`inventory_new_arrival`・LINE→メール）。再検索・削除可
- **複数画像ギャラリー**：在庫詳細で**アイキャッチ＋車両に添付した画像**をサムネイル切替式ギャラリー表示
- **類似在庫レコメンド**：在庫詳細に「類似の在庫」（同メーカー優先→価格帯±30%で補完・最大4台）を表示
- **CSVエクスポート**：`[carmel_store_inventory]` から現在の在庫をCSV書き出し（加盟店=自店 / 本部=全店・Excel対応UTF-8）。VIN列により再取込でupsert可能
- **問い合わせ→商談自動起票**：在庫詳細のお客様問い合わせで、顧客×車両の商談（案件）を自動起票（`matched`・取扱店に割当・重複は再利用）。`carmel_support` に問い合わせ記録、案件はマイページ/加盟店ポータルに反映
- **顧客ひも付け**：在庫共有から自動起票された「顧客未確定」案件に、加盟店が `[carmel_store]` から**顧客（氏名・メール・電話）をひも付け**。既存ユーザーは再利用、新規は `customer` で発行＋設定リンク送付

### 在庫共有 売上配分（手数料）

`Carmel_Commission`。ショートコード **`[carmel_hq_commissions]`**。他店在庫（`source_store_id`）を別店が販売して成約した場合の手数料配分を管理。

- 適用：案件に `source_store_id` が設定され、販売店（`store_id`）と異なるとき。**手数料 = 販売価格 × 料率**（既定5%・`carmel_commission_rate`）
- 成約系ステータス（契約完了・納車準備・納車済 等）で**自動再計算**し案件メタに保存
- 画面：本部は全件＋**精算済/未精算トグル**、加盟店オーナーは自店が関与する配分を閲覧（料率・対象成約数・手数料合計・未精算額のサマリーつき）
- `source_store_id` は案件のACF（在庫保有店）で設定、または在庫共有の取り寄せ依頼からの案件化運用で記録

## コミュニティ（実装済み）

`Carmel_Community`。2系統を提供。

- **外部リンク `[carmel_learning]`**：学習コンテンツ（Notion）＋外部コミュニティ（bbPress等）への導線（従来）
- **組み込み掲示板 `[carmel_community]`**（`/community`・bbPress非依存・CARMEL内で完結）
  - CPT `carmel_community`＋WPコメントで構成。**トピック投稿**と**返信**を同一ページ内（`?topic=ID`）で表示
  - 既定はログインユーザー全員が利用可（`carmel_community_can_use` フィルタで制限可）。本部は wp-admin でモデレート可能
  - 一覧（投稿者・日付・返信数）→トピック詳細（本文＋返信＋返信フォーム）
  - **カテゴリ**（お知らせ／質問／事例共有／販売・在庫／雑談・`carmel_community_categories` で変更可）でタブ絞り込み
  - **本部ピン留め**：本部はトピックを上部に固定でき（📌）、一覧でピン優先表示
  - **通知連携**：新着トピックは本部へ（`community_new_topic`）、返信はトピック投稿者本人へ（`community_reply`・LINE→メール）自動通知
  - **画像添付**：トピック・返信に画像を添付可（トピックはアイキャッチ、返信はコメントメタに保存）
  - **メンション**：本文の `@ユーザー名`（ログイン名）を強調表示し、該当ユーザーへ通知（`community_mention`）。入力時は **@候補をAJAXでサジェスト**（↑↓/Enterで選択）
  - 画像添付のため `customer`/`store_owner`/`store_staff`/`hq_admin` に `upload_files` 権限を付与（ロール未更新環境でもアップロード処理中のみ実行時許可）
  - **いいね**：トピック・返信に👍（`_carmel_likes`）。いいねされた本人へ通知（`community_like`）。**人気のトピック（いいね数ランキング）**を一覧上部に表示
  - **ベストアンサー**：トピック投稿者/本部が返信を1件ベスト指定（先頭固定＋一覧に「✔解決済」）
  - **カテゴリ別未読**：カテゴリタブに未読件数バッジ（閲覧で既読化・`carmel_comm_read`）。**通知設定**：カテゴリを購読すると新着トピックを通知（`carmel_comm_subs`→`community_category_new`）

## フォーム連携（問い合わせ／審査申込）と LINE / LIFF（実装済み）

任意のフォーム（CF7 / Gravity / MW WP Form / Snow Monkey Forms / WPForms 等）を `Carmel_Application_Intake::process()` に繋ぐと、顧客アカウント自動発行＋案件作成＋通知が走る。

### intent で「反響」と「審査申込」を出し分け

`process()` の正規化キーに **`intent`** を追加：
- `intent=application`（既定）：正式申込。`carmel_application_created` を発火し**AIスコア（信販プレ審査）**へ。
- `intent=inquiry`：反響・問い合わせ。**スコアは走らせず**、案件に `is_lead=1` を付け `carmel_inquiry_created` を発火（集計・通知のみ）。

→ 「審査申込フォーム」は `intent=application`、「お問い合わせ共通情報フォーム」は `intent=inquiry` で繋ぐ。

### LINE ユーザーID の取り込み

正規化キー **`line_user_id`** を渡すと顧客の user_meta `line_user_id` に保存され、以後の通知（審査結果・契約・納車等）が**その人の LINE へ自動配信**される（プロライン／LINE）。

### LIFF ヘルパー `[carmel_liff]`

LINE公式アカウントのリッチメニュー「審査フォーム」→ **LIFF URL（＝審査フォームを置いた WP ページ）** という動線で使う。そのページに `[carmel_liff]` を設置すると、LIFF SDK を読み込み、ログイン中の LINE userId を取得して**ページ内フォームの hidden `line_user_id` に自動セット**（氏名が空なら LINE 表示名で補完）。

```
[carmel_liff id="1657xxxxxx-XXXXXXXX" field="line_user_id"]
```
LIFF ID は属性 / `CARMEL_LIFF_ID` 定数 / `carmel_liff_id` オプションのいずれか。フォームには `line_user_id` の hidden 項目を1つ用意（無ければJSが自動挿入）。

### LIFF ワンタップ会員ログイン `[carmel_liff_login]`

LINEから**会員ページ（マイページ）へワンタップ**で入れる。会員ページ用LIFFのエンドポイントに `[carmel_liff_login]` を置くと、LINEの **IDトークンをサーバーで LINE 検証**（`https://api.line.me/oauth2/v2.1/verify`・aud=チャネルID）し、`line_user_id` が一致する**会員を自動ログイン**して `/mypage` へ。未会員（line_user_id 一致なし）は申込/審査ページへ自動誘導。

- 安全策：IDトークンは LINE 側で署名・iss・aud・exp を検証（なりすまし不可）。自動ログインは**顧客ロールのみ**（管理者/本部/加盟店は対象外＝特権昇格防止・`carmel_liff_login_allowed` で調整可）。検証済みメール一致時のみ既存顧客に `line_user_id` を後付け紐付け。
- 設定：`carmel_liff_id`（LIFF ID）、`carmel_liff_channel_id`（LIFFが紐づく**LINEログインチャネルID**＝検証の client_id）。LIFFスコープは **openid 必須**（IDトークン取得）、email紐付けを使うなら `email` も付与。
- 動線：LINEボット/リッチメニューの「会員ページ」リンクを **この会員ページLIFFのURL**（`carmel_member_page_url`）に向ける → タップで検証ログイン → `/mypage`。
- REST：`POST /wp-json/carmel/v1/liff-login`（body: `id_token`）→ `{ ok, redirect }`。`carmel_liff_logged_in` アクション発火。

### 各フォームプラグインの繋ぎ方

- **CF7**：`carmel_cf7_field_map` でフィールド名を対応づけ（`line-user-id`/`intent` 既定対応）。フォーム別 intent は `carmel_cf7_intent` フィルタ。
- **Gravity**：`carmel_gform_field_map`（フォームID別にフィールドIDを対応）。
- **REST**：`POST /wp-json/carmel/v1/application`（`X-Carmel-Token`）に `name/email/phone/deal_type/message/line_user_id/intent` を送る。
- **WPForms（ネイティブ対応・実装済み）**：`wpforms_process_complete` を取り込み済み。**安全のため許可制**で、対象フォームIDを指定したものだけ処理する（無関係なフォームを誤って案件化しない）。フィールドは型・ラベルから**自動マッピング**（氏名/メール/電話/住所/内容/`line_user_id`）。設定例：

  ```php
  // 取り込む WPForms フォームID（= 投稿ID）
  add_filter( 'carmel_wpforms_forms', fn() => array( 7348, 7361 ) );
  // 問い合わせ扱い（反響＝スコア無し）にするフォーム
  add_filter( 'carmel_wpforms_inquiry_forms', fn() => array( 7361 ) );
  // （任意）自動マップで拾えない場合の明示マップ：form_id => [ 正規化キー => フィールドID ]
  add_filter( 'carmel_wpforms_field_map', function ( $m ) {
      $m[7348] = array( 'name' => 1, 'email' => 2, 'phone' => 3, 'message' => 4, 'line_user_id' => 5 );
      return $m;
  } );
  ```
  → これで **7348＝審査申込（application・AIスコア）/ 7361＝お問い合わせ（inquiry・反響）** が繋がる。`line_user_id` は LIFF が挿入する hidden を WPForms の AJAX 送信が含むため自動で取り込まれる（フォームに Hidden Field を1つ置くとより確実）。`carmel_wpforms_deal_type` / `carmel_wpforms_intent` でフォーム別に上書き可。設定UIは `wp_options` に `carmel_wpforms_forms` / `carmel_wpforms_inquiry_forms`（配列）でも可。

- **MW WP Form / Snow Monkey Forms**：各プラグインの送信完了アクションで `Carmel_Application_Intake::process()` を呼ぶ薄いブリッジを追加。例（Snow Monkey Forms）：

```php
add_action( 'snow_monkey_forms/complete', function ( $data ) {
    $v = $data->to_array()['data'] ?? array();
    Carmel_Application_Intake::process( array(
        'name'         => $v['お名前']    ?? '',
        'email'        => $v['メール']    ?? '',
        'phone'        => $v['電話番号']  ?? '',
        'deal_type'    => 'loan',
        'message'      => $v['お問い合わせ内容'] ?? '',
        'line_user_id' => $v['line_user_id'] ?? '',
        'intent'       => 'application', // 問い合わせフォームは 'inquiry'
        'source'       => 'snow-monkey',
    ) );
}, 10, 1 );
```

> どのプラグインかをお知らせいただければ、フィールド名に合わせた**ネイティブブリッジ**を carmel-core 同梱で追加します。

### 動線（LINE → システム）

```
リッチメニュー「審査フォーム」タップ → LIFF URL（WPの審査フォームページ・[carmel_liff]）
  → LIFFが line_user_id を hidden にセット → フォーム送信 → intake
  → 顧客作成（line_user_id保存）＋案件作成（application→AIスコア / inquiry→反響）
  → 審査結果・契約・納車などの通知が その人のLINEへ自動配信
AI自動応答：LINE Webhook → ボット(GAS/LLM)でFAQ応答・必要時にLIFF審査フォームへ誘導
            （ボットがREST intakeを叩いて反響起票も可）
```

### LINE 自動応答ボット（Webhook・実装済み）

`Carmel_LINE_Bot`。公式アカウントの **Webhook 受信口**：`POST /wp-json/carmel/v1/line-webhook`。

- **署名検証**：`X-Line-Signature` を チャネルシークレット（`CARMEL_LINE_CHANNEL_SECRET` / `carmel_line_channel_secret`）で HMAC-SHA256 検証。未設定なら拒否（オープン回避）
- **応答ロジック**（reply API・チャネルアクセストークン使用）：
  1. 「審査/申込/ローン」等 → **審査フォーム(LIFF)へ誘導**（クイックリプライ）
  2. 「在庫/車」等 → 在庫ページへ誘導
  3. `carmel_line_ai_endpoint`（GAS/LLM）設定時 → メッセージを委譲し `{"reply": "..."}` を返信（**AI自動応答**）
  4. 未設定なら**組み込みFAQ**（営業時間/場所/見積/保証/納車・`carmel_line_faqs` で編集可）
  5. いずれも無ければ既定の案内＋メニュー
- **会話型ヒアリング**：クイックリプライ「チャットで相談」→ 氏名→電話→**エリア選択**→内容 を対話で聞き取り、**`carmel_support`（`support_type=line_inquiry`・`line_user_id`・`area`・`store_id`）として反響起票**。状態は transient 保持・「キャンセル」で中止
- **在庫カード（Flex Message）**：「在庫見たい」やヒアリング完了時に、**画像＋車名＋価格＋「詳細を見る」ボタン**のカルーセルで在庫を提示（担当店の在庫を優先）
- **エリアで担当加盟店へ自動ルーティング**：選択エリア → `carmel_area_store_map`（option/filter・`エリア=>store_id`）で**担当店を割当**し、反響を**本部＋担当店へ通知**（`line_lead`）。例：
  ```php
  add_filter( 'carmel_area_store_map', fn() => array(
      '関東' => 12, '近畿' => 18, '九州・沖縄' => 25, // エリア => 加盟店(carmel_store)のID
  ) );
  ```
  エリア候補は `carmel_line_regions` フィルタで編集可
- **follow（友だち追加）**：ウェルカム＋導線。`carmel_line_message` / `carmel_line_follow` / `carmel_line_postback` / `carmel_line_lead_created` アクションで拡張可
- 設定：`carmel_line_form_url`（審査フォームのLIFF URL）、`carmel_line_ai_endpoint`（任意のAI応答先・GAS雛形は `docs/line-ai-endpoint.gs`）
- **実機テスト手順**：`docs/LINE_LIFF_セットアップとテスト.md`

> LINE Developers 側で Webhook URL に上記エンドポイントを登録し、応答メッセージ（自動応答）をオフ、Webhookをオンにする。チャネルアクセストークン＝返信、チャネルシークレット＝署名検証に使用。

## 公開 加盟店（店舗）ページ（実装済み）

`Carmel_Store_Profile`。ショートコード **`[carmel_store_profile]`**（公開ページ `/stores` 想定）。

- **一覧（`?store` なし）**：公開加盟店のディレクトリ（店舗名・住所・公開在庫数・リンク）。**エリア／取扱種別で絞り込み**（ACF `store_area`/`store_services`・エリアは `carmel_line_regions` と共通）
- **店舗詳細（`?store=ID`）**：店舗名・住所・電話・営業時間・紹介文・**地図**＋**実績**（公開在庫/成約/取扱案件）＋**スタッフ紹介**（自店の owner/staff・`carmel_store_show_staff` で制御）＋**その店の公開在庫カード**＋「この店舗の在庫をもっと見る」（`/inventory?store_id=ID`）＋**レビュー**（★評価・承認制／ハニーポット＋nonce）＋問い合わせ導線
- 在庫一覧は `?store_id=ID` で**店舗絞り込み**（チップ表示＋他フィルタと併用保持）。在庫詳細の「取扱店」は店舗ページへリンク、**この店舗の他の在庫**セクションを表示
- **本部レビュー承認**：`[carmel_hq_reviews]`（`/hq`）で承認待ちレビューを**フロントで承認/却下**（wp-admin不要・`carmel_manage_stores`）
- **お気に入り店舗**（`Carmel_Store_Follow`）：店舗ページでフォロー（`carmel_followed_stores`）。フォロー店舗が在庫を公開すると**新着通知**（`store_new_stock`・プロライン→メール）
- 一般公開（未ログイン可）。**原価・オーナーID・会費等の内部情報は出さない**
- **SEO**：店舗詳細に `schema.org/AutoDealer` の JSON-LD ＋ OGP
- 在庫詳細の「取扱店：◯◯」は店舗ページへリンク。店舗の電話/営業時間は ACF（`store_tel`/`store_hours`）で設定

## 会員ページ誘導・反響管理（実装済み）

- **会員ページCTAの常設**：在庫詳細（ログイン顧客）と在庫問い合わせ完了に「会員ページへ」導線。リンク先は `carmel_member_page_url`（LIFF会員ログイン）優先、無ければ `/mypage`
- **再訪ログイン導線**（`Carmel_Member_Nudge`）：節目（納車完了 `delivered`/`lease_delivered`）にお客様へ **`mypage_invite`** 通知（プロライン→メール）。文面に会員ページURL（ワンタップLIFF or /mypage）。節目は `carmel_member_nudge_statuses` で調整可
- **反響の担当店表示**（`Carmel_Store_Leads`）：`/store` ダッシュボード上部に **LINE反響＋在庫問い合わせ**の一覧（自店割当のみ／本部は全件）。氏名・連絡先・エリア・内容・対応状況（新規→対応中→完了トグル）。`carmel_support`（`line_inquiry`/`inventory_inquiry`）を集約
  - **商談化（顧客確定）**：リード → `carmel_deal`（`matched`・店舗割当・`is_lead`・スコア無し）を起票。**氏名・メール・電話を入力するとその場で顧客アカウントを発行**し、**LINE ID（`line_user_id`）を紐付け**て通知・会員ページを有効化（新規は設定リンクをメール）。メール空欄なら「顧客未確定」で起票。リードに `deal_id` 紐付け（既存商談はリンク表示）
  - **未対応SLAエスカレーション**：日次cronで **SLA時間**（`carmel_lead_sla_hours`・既定24h）超の未対応反響を本部＋担当店へ通知（`lead_sla_breach`・1回のみ）
- **車検・保険・点検通知に会員ページURL付加**：`inspection_notice`/`insurance_notice`/`maintenance_notice` の文面に会員ページリンクを自動付加（`Carmel_Member_Nudge`）

## セットアップ手順

1. **前提プラグイン**：ACF Pro（フィールド表示）、WooCommerce＋Square for WooCommerce（決済）、任意で Contact Form 7 / Gravity Forms、bbPress、WooCommerce Subscriptions（会費サブスク時）
2. `wp-content/plugins/carmel-core/` を配置し、管理画面で有効化（CPT・ロール登録、Cron スケジュール、リライトflush が自動実行）
3. **固定ページを作成**し各ショートコードを設置：
   - `/login` → `[carmel_login]`（統合ログイン画面・ログイン後はロール別に自動振り分け）
   - `/mypage` → `[carmel_mypage]`＋`[carmel_upload]`（任意で `[carmel_my_documents]`／`[carmel_customer_guide]`）
   - `/store` → `[carmel_store]`（＋任意で `[carmel_store_content]` を別ページや同ページに）
   - `/store-billing` → `[carmel_store_billing]`（帳票・契約書の発行）
   - `/sales-support` → `[carmel_sales_support]`（販売支援）
   - `/store-content` → `[carmel_store_content]`（スタートガイド・お知らせ・マニュアル・FAQ）
   - `/store-inventory` → `[carmel_store_inventory]`（自店在庫管理＋在庫共有）
   - `/inventory` → `[carmel_inventory]`（カーメル在庫ページ・公開／ログイン分け）
   - `/stores` → `[carmel_store_profile]`（公開・加盟店ページ：一覧／`?store=ID` で店舗詳細）
   - `/community` → `[carmel_community]`（組み込みコミュニティ掲示板）
   - `/hq` → `[carmel_hq_dashboard]` `[carmel_hq_screening]` `[carmel_hq_board]` `[carmel_hq_contracts]` `[carmel_hq_reports]` `[carmel_hq_stores]` `[carmel_hq_commissions]` `[carmel_hq_content]` `[carmel_hq_reviews]`
   - 申込ページ → `[carmel_application_form]`／加盟店募集ページ → `[carmel_franchise_form]`
   - 任意ページ → `[carmel_learning]`
4. **連携キーを設定**（`wp-config.php` 定数 or `wp_options`）：

   | サービス | 定数 |
   |---------|------|
   | プロライン | `CARMEL_PROLINE_ENDPOINT` / `CARMEL_PROLINE_TOKEN` |
   | LINE WORKS | `CARMEL_LINEWORKS_WEBHOOK` |
   | Slack | `CARMEL_SLACK_WEBHOOK` |
   | GAS | `CARMEL_GAS_ENDPOINT` / `CARMEL_GAS_TOKEN` |
   | Google Maps | `CARMEL_MAPS_API_KEY` |
   | Square | `CARMEL_SQUARE_SIGNATURE_KEY` / `CARMEL_SQUARE_WEBHOOK_URL` |
   | マネーフォワード契約 | `CARMEL_MF_ENDPOINT` / `CARMEL_MF_TOKEN` |
   | 申込REST | `CARMEL_INTAKE_TOKEN` |
   | Notion / bbPress | `CARMEL_NOTION_URL` / `CARMEL_COMMUNITY_URL` |

5. **外部側の webhook 登録**：
   - Square → `/wp-json/carmel/v1/square-webhook`
   - GAS → `/wp-json/carmel/v1/gas-callback`
   - マネーフォワード契約 → `/wp-json/carmel/v1/mf-contract-callback`
6. ユーザーに `store_id`（所属加盟店）、`line_user_id`（LINE）を user_meta で設定。

### 動作確認
- このリポジトリ環境では WordPress 本体が無いため、検証は **PHP 構文チェック（`php -l`）のみ**実施済み。実挙動（CPT/ロール登録、ショートコード描画、通知・連携・Cron）は WordPress 環境での確認が必要。
- Cron の手動実行：`Carmel_Cron::instance()->run_daily()` / `run_weekly()`。

## 残課題

- 会費の最終課金方式の確定（§13-#1）。現状はサブスク／手動の両対応で運用可能
- 本番リリース前のセキュリティ最終確認（§12 Phase 4 最終項目）
