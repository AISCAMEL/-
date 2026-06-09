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

## 次フェーズ（未実装）

- 申込フォーム → 案件作成＋顧客アカウント自動発行
- /mypage の PHASE 表示制御、/store・/hq の画面
- ステータス変更フックから `carmel_event` の自動発火
- ACF フィールド群（deal_type 別）、行レベルのクエリフィルター
- GAS / Square / Google Maps 連携
