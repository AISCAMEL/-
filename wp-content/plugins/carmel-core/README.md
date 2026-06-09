# Carmel Core プラグイン

カーメル統合管理システムのコア機能を提供する WordPress プラグイン（Phase 1）。
要件定義書 v1.4 に対応。

## 提供機能（Phase 1）

| 機能 | 実装 |
|------|------|
| カスタム投稿タイプ 9種 | `includes/class-carmel-post-types.php` |
| 権限ロール 4階層＋キャパビリティ | `includes/class-carmel-roles.php` |
| ポータルのアクセス制御（/mypage・/store・/hq） | `includes/class-carmel-access-control.php` |
| 通知オーケストレーター＋4チャネルアダプタ | `includes/notifications/` |

### カスタム投稿タイプ（9）

`carmel_deal`（案件）/ `carmel_store`（加盟店）/ `carmel_vehicle`（在庫車両）/
`carmel_document`（書類）/ `carmel_repayment`（返済）/ `carmel_support`（サポート）/
`carmel_inspection`（車検）/ `carmel_insurance`（保険）/ `carmel_notify_log`（通知ログ）

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
| プロライン（LINE） | 顧客 | `Carmel_ProLine_Adapter` |
| LINE WORKS | 社内 | `Carmel_LineWorks_Adapter` |
| Slack | 運用/開発 | `Carmel_Slack_Adapter` |
| メール | 正式・フォールバック | `Carmel_Mail_Adapter` |

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

- **権限（§5.5）**：遷移先ごとに必要 cap を判定（`approved`/`rejected`→`carmel_screening`、`contracted`→`carmel_send_cloudsign`、他→`carmel_change_deal_status`）。`system=true` で自動処理はスキップ
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
- **CSV出力**：スコープ内案件を CSV ダウンロード（Excel 対応の UTF-8 BOM 付き、nonce 検証）

## クラウドサイン契約（Phase 4 実装済み・本部専用）

`Carmel_CloudSign`。ショートコード **`[carmel_hq_contracts]`**（`carmel_send_cloudsign` cap = 本部のみ。加盟店は送付不可）。

- 契約待ち案件（`doc_prep` / `contracted`）を一覧。**クラウドサイン送付**ボタンで署名依頼
- 送付 → `cloudsign_id`/`cloudsign_status=sent` を保存、`contract`（売買契約書）`carmel_document` を作成、顧客へ `contract_sign_request` 通知
- **署名完了 webhook**：`POST /wp-json/carmel/v1/cloudsign-callback`（トークン検証）。`status=completed` で署名済PDFを保存し、案件を **`contracted` へ自動前進**（system でHQ capをバイパス）。`rejected` も記録
- 送付失敗は `system_error`→Slack
- 設定：`CARMEL_CLOUDSIGN_ENDPOINT` / `CARMEL_CLOUDSIGN_TOKEN`（または同名オプション）。リクエスト形は `carmel_cloudsign_payload` フィルタで調整

> 加盟店の電子署名は加盟店独自の外部サービス（システム外・§14-3）。本機能は本部の契約送付のみ。

## 次フェーズ（未実装）

- /hq 全加盟店横断カンバン（D&D）
- 書類アップロード、返済状況の顧客表示
- bbPress / Notion 連携
- ACF フィールド群（deal_type 別）、会費の定期課金（WooCommerce Subscriptions 等）
- ステータス変更フックから `carmel_event` の自動発火
- ACF フィールド群（deal_type 別）、行レベルのクエリフィルター
- GAS / Square / Google Maps 連携
