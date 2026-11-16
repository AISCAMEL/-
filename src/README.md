# CarLoan_System — Google Apps Script ソース

`docs/開発仕様書.md` に基づく CarLoan_System（カーローン・買取相談システム）の
Google Apps Script プロジェクトのソースコードです。

Google Drive 上のスクリプトプロジェクト `CarLoan_System` から取り込みました。

## ファイル構成

| ファイル | 役割 |
|---|---|
| `appsscript.json` | プロジェクト設定（Asia/Tokyo、V8、Webアプリ公開） |
| `Config.gs` | 各種設定値（LINE / OpenRouter / スプレッドシート / スコアリング閾値など） |
| `Main.gs` | Webhook受信（`doPost` / `doGet`）、ProLine転送、LINE返信 |
| `Setup.gs` | 初期セットアップ |
| `OpenRouter.gs` | AI（OpenRouter）連携・査定応答プロンプト生成 |
| `InsertCreditors.gs` | 信販会社マスタの初期投入 |
| `Scoring.gs` | 顧客スコアリング |
| `FormHandler.gs` | フォーム受信処理 |
| `CreditMatch.gs` | 信販会社マッチング |
| `DelayCalc.gs` | 返済遅延・延滞利息計算 |
| `AfterSupport.gs` | アフターサポート（車検・保険・乗り換え等） |
| `Reminder.gs` | リマインダー通知 |
| `LineNotify.gs` | LINE通知 |
| `LineWorks.gs` | LINE WORKS連携・週次レポート |
| `Trigger.gs` | トリガー設定 |

## セキュリティ（重要）

リポジトリへの取り込みにあたり、以下の機密情報は**プレースホルダにマスキング**しています。
本番運用時は実値に置き換えるか、`PropertiesService`（スクリプトプロパティ）経由で
読み込む方式へ変更することを推奨します。

| 項目 | 場所 | プレースホルダ |
|---|---|---|
| LINE チャネルトークン | `Config.gs` | `YOUR_LINE_CHANNEL_TOKEN` |
| LINE チャネルシークレット | `Config.gs` | `YOUR_LINE_CHANNEL_SECRET` |
| OpenRouter APIキー | `Config.gs` | `YOUR_OPENROUTER_API_KEY` |
| ProLine Webhook URL | `Main.gs` | `.../webhook/YOUR_PROLINE_WEBHOOK_TOKEN` |

> スプレッドシートID・フォームIDは Google 認証が必要なため残していますが、
> 公開リポジトリで運用する場合はこれらも環境管理へ移すことを検討してください。
