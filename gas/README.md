# ⚙️ GAS 実装（請求書 → マネーフォワード 本線 MVP）

Gmailのラベル付き請求書を、抽出 → 機械検証 → Drive保存 → 台帳記録 → MF用CSV出力まで自動化するGoogle Apps Script一式です。
設計の根拠は `docs/請求書自動化_設計書.md`、データ雛形は `templates/` を参照。

## ファイル構成（役割分割）

| ファイル | 役割 |
|----------|------|
| `Main.gs` | 時間トリガーのエントリ・全体制御 |
| `Config.gs` | 設定・スクリプトプロパティ・`setupCheck()` |
| `Utils.gs` | 金額/日付正規化・リトライ・ログ・JSONパース |
| `GmailService.gs` | ラベル検索・添付/本文取得・ラベル張替 |
| `AiExtractor.gs` | AI抽出（OpenRouter）・2段モデル・スキーマ正規化 |
| `Validator.gs` | 機械検証 V1〜V8・二重請求検知 |
| `DriveService.gs` | 証憑を業者別/年月別に保存 |
| `VendorMaster.gs` | 業者マスタ読込・送信元/名称での名寄せ |
| `JournalBuilder.gs` | 業者×仕訳パターン×金額 → 仕訳明細を生成 |
| `LedgerService.gs` | 台帳の読み書き・採番・重複チェック |
| `MfExporter.gs` | 承認済み行 → MF仕訳インポートCSV出力 |
| `appsscript.json` | マニフェスト（タイムゾーン等） |

## セットアップ手順

### 1. スプレッドシートを用意
`templates/` の5つのCSVを、1つのスプレッドシートの各タブ（`業者マスタ` `仕訳パターン明細` `勘定科目マスタ` `請求台帳` `処理ログ`）に取り込む（手順は `templates/README.md`）。

### 2. Driveルートフォルダを用意
証憑保存先の親フォルダ「請求書」を作成し、そのフォルダIDを控える。

### 3. GASプロジェクト作成
- スプレッドシートの[拡張機能]→[Apps Script]、または スタンドアロンのGASプロジェクトを作成。
- このフォルダの `.gs` と `appsscript.json` を貼り付け（または `clasp push`）。

### 4. スクリプトプロパティを登録
[プロジェクトの設定]→[スクリプトプロパティ]に以下を登録：

| キー | 値の例 | 必須 |
|------|--------|------|
| `SPREADSHEET_ID` | 台帳スプレッドシートのID | ✅ |
| `DRIVE_ROOT_FOLDER_ID` | 「請求書」フォルダのID | ✅ |
| `OPENROUTER_API_KEY` | OpenRouterのAPIキー | ✅ |
| `AI_MODEL_PRIMARY` | `google/gemini-2.0-flash-001` | ✅ |
| `AI_MODEL_FALLBACK` | `anthropic/claude-3.5-haiku` | 任意 |
| `APPROVAL_THRESHOLD` | `50000`（5万円以上は要承認） | 任意 |
| `TAX_INPUT_MODE` | `NUKI`（税抜）/ `KOMI`（税込） | 任意 |

### 5. Gmailラベルを用意
`請求書/未処理`（監視対象）、`請求書/処理済`、`請求書/要確認`、`請求書/エラー`。
※ 既存ラベル名を使う場合は `Config.gs` の `CONFIG.LABEL` を書き換える。

### 6. 動作確認
1. `setupCheck()` を手動実行 → ログで不足が無いことを確認。
2. テスト用の請求書メールに `請求書/未処理` を付与。
3. `processInvoices()` を手動実行 → 台帳に行が追加され、Driveに証憑が保存されることを確認。

### 7. トリガー設定
[トリガー]→ `processInvoices` を「時間主導 / 1時間ごと」で追加。

### 8. 月次のMF出力
`generateMfExportFile()` を実行（メニュー化も可）→ 承認済み行のMF用CSVがDriveに出力される。
そのCSVをMFクラウド会計の「仕訳インポート」で取り込む。

## ⚠️ 確定前の調整ポイント

- **MF仕訳CSVの列**（`MfExporter.gs` の `MF_HEADER` / `buildMfRow_`）は、
  あなたのMF管理画面からDLした**仕訳インポートテンプレCSVの実物**に合わせて調整が必要（確認事項#13-A）。
- **PDF添付のAI読取**はモデル/プラグイン依存。`AiExtractor.gs` の `buildContent_()` で、
  使うモデルがPDFを直接読めない場合は OCR を挟む方式に切り替える。
- 金額の**税抜/税込入力**運用（確認事項#13-C）に合わせて `TAX_INPUT_MODE` を設定。

## 設計上の安全策

- **冪等性**：メールIDで重複チェックし、二重記録を防止。
- **失敗の隔離**：1通の失敗で全体を止めず、`エラー`/`要確認`に隔離。
- **証憑優先**：抽出に失敗しても原本はDriveに残す。
- **慣らし運転**：業者マスタの `自動承認可否=否` の間は必ず人の確認に回る。
