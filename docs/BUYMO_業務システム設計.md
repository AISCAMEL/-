# BUYMO 業務システム設計（会員／加盟店／本部・段階導入）

「後から項目を足していける」ことを前提に、いまのスタック（**静的サイト ＋ GAS ＋ スプレッドシート ＋ Slack/LINE**）の上で組む設計です。WordPress（LPtools）からも後付けで連携できます。

## 1. 全体像

```
[WP / LPtools / 各LP]──POST──▶ GAS WebApp(doPost)
                                   ├ type:"buymo"    査定/問合せ → 記録・通知・ステップメール登録・案件自動生成
                                   ├ type:"stepmail" ★WPから後発でステップメール発動（token認証）
                                   ├ type:"register" 会員/加盟店/本部の登録（role付き）
                                   └ type:"case"     看板ボードの作成/更新（Slack通知）
[hq.html 看板ボード]◀─JSONP(doGet action=cases)── GAS  （ドラッグでステージ移動→type:"case"）
[ステップメール runStepMails (時間トリガー)] → 会員ページへ誘導メール
```

データはすべて1つのスプレッドシートのシートに集約：
`会員マスタ` / `問い合わせ` / `BUYMOステップメール` / `案件ボード` ほか。

## 2. ロール（3区分）

| ロール | 入口 | できること | 実装状況 |
|---|---|---|---|
| **会員（お客様）** | `login.html`→`mypage.html` | 査定状況・買取の進捗確認、追加相談 | 既存（デモ）＋ステップメール誘導済 |
| **加盟店** | `portal-login.html`→`hq.html?role=partner` | 自店の担当案件の看板管理 | ✅ 看板ボード（担当フィルタ）実装 |
| **本部** | `portal-login.html`→`hq.html?role=hq` | 全案件の看板・営業サマリー | ✅ 実装 |

`type:"register"` に `role`（member/partner/hq）を持たせて `会員マスタ` に記録。

## 3. 実装済み（このコミット）

- **WPから後発でステップメール発動**：`POST {type:"stepmail", token, email, name, genre}`
  - `token` は `gas/StepMail.gs` の `stepCfg_().TRIGGER_TOKEN` と一致が必要（本番必須）。
  - WP側は「Webhook送信」プラグインや、フォーム送信時アクションでこのURLにPOSTすればOK。
- **看板ボード（`hq.html`＋`board.js`）**：6ステージ（新規受付→査定中→商談中→契約→入金待ち→完了）、ドラッグ移動、営業サマリー（件数・想定売上）。
  - 査定フォーム送信が自動で「新規受付」に積まれる（`createCaseFromLead_`）。
  - GAS接続時はステージ変更が `案件ボード` に保存＋**Slack/LINE通知**。
- **Slack/LINE通知**：既存 `notifyStaff_` を全イベントで利用（査定・案件・登録）。
- **ロールログイン（`portal-login.html`）**：会員/加盟店/本部の振り分け（デモ）。

## 4. 後から入れる（フェーズ計画）

| 項目 | 内容 | 必要作業 |
|---|---|---|
| 認証（本番） | 各ロールにID/パスワード | GASでトークン発行＋検証、または Google ログイン/外部Auth。`hq.html` にガード追加 |
| 加盟店ごとの権限 | 自店データのみ表示・編集 | `?who=店名` を認証連動に。doGet/doPost にassignee検証 |
| WPフィールド連動 | WP側の任意項目でステップメール/案件を発火 | WPフォーム→Webhook→`type:"stepmail"`/`type:"case"`。項目名マッピングを追加するだけ |
| Slackチャンネル振り分け | 本部/加盟店で通知先を分ける | `Config.gs` に複数Webhook、イベント種別でルーティング |
| 会員マイページ（BUYMO版） | 査定状況の表示をBUYMOデザインに | `mypage.html` を複製しブランド差し替え＋ doGet で案件取得 |
| 営業レポート | 期間別の成約率・売上集計 | `案件ボード` を集計するダッシュボード追加 |
| 看板の項目追加 | 金額・確度・次回アクション等 | `案件ボード` 列と `board.js` のカード表示に追記 |

## 5. セキュリティ注意（本番前に必須）

- GASウェブアプリは「全員アクセス可」なので、**書き込み系（type:"case"/"stepmail"/"register"）はトークン必須**にする（stepmailは実装済、case/registerにも同方式を推奨）。
- `hq.html` 等の業務画面は `noindex` 済み。本番は**認証必須**にし、URLだけで開けないようにする。
- 個人情報を扱うため、HTTPS・アクセス権限・スプレッドシート共有範囲を最小化。

## 6. 関連ファイル

- フロント：`site/portal-login.html` `site/hq.html` `site/assets/js/board.js` `site/assets/css/board.css`
- 会員導線：`site/buymo.html`（会員CTA）・`login.html`/`mypage.html`（既存）
- GAS：`gas/WebApp.gs`（doPost/doGet）`gas/Board.gs`（看板）`gas/StepMail.gs`（ステップメール）
- 手順：`gas/ステップメール_セットアップ.md`
