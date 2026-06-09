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

## 次フェーズ（未実装）

- /mypage の PHASE 表示制御、/store・/hq の画面
- ステータス変更フックから `carmel_event` の自動発火
- ACF フィールド群（deal_type 別）、行レベルのクエリフィルター
- GAS / Square / Google Maps 連携
