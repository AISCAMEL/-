# BUYMO 全体マップ ＆ 運用ガイド（棚卸し）

BUYMO 車買取サイト＋業務システムの全体像。公開・運用の起点として使ってください。
会社：合同会社アイズ（いわき市）／古物商 第25121A010859号／info@aisjaltd.com

---

## 1. ページ一覧

### 公開（集客）
| URL | 役割 | 認証 |
|---|---|---|
| `buymo.html` | 買取トップLP（査定シミュ・比較表・追従CTA・査定フォーム・会員誘導） | 公開 |
| `buymo-contact.html` | お問い合わせ／無料査定フォーム | 公開 |
| `buymo-partner.html` | パートナー（加盟店）募集＋応募フォーム | 公開 |
| `genre/` | 買取ジャンルハブ（9ジャンル） | 公開 |
| `area/` ＋ `area/<県>/` | 都道府県別SEO LP（ハブ＋47県） | 公開 |
| `tokushoho.html` | 会社概要・古物商・特商法表記 | 公開 |
| `privacy.html` | プライバシーポリシー（既存） | 公開 |

### 会員（お客様）
| URL | 役割 | 認証 |
|---|---|---|
| `member.html` | 査定/買取の進捗をステッパー表示 | メール |

### 業務（本部／加盟店）※ `noindex`・ログイン必須
| URL | 役割 | 権限 |
|---|---|---|
| `portal-login.html` | ログイン振り分け | 公開入口 |
| `hq-dashboard.html` | 本部ダッシュボード | hq |
| `hq.html?role=hq\|partner` | 案件ボード（看板＋詳細パネル/対応履歴） | staff |
| `hq-leads.html` | リード一覧・担当割当 | hq |
| `hq-stores.html` | 加盟店管理 | hq |
| `report.html` | 営業レポート | hq |
| `hq-notices.html` | お知らせ管理（本部→加盟店） | hq |
| `partner-academy.html` | アカデミー（コース一覧・進捗） | staff |
| `partner-course.html?id=` | 受講（動画＋テキスト・完了管理） | staff |
| `partner-quiz.html?id=` | 修了テスト | staff |
| `partner-cert.html?id=` | 修了証（印刷可） | staff |
| `partner-scripts.html` | トークスクリプト集 | staff |
| `partner-community.html` | 加盟店コミュニティ | staff |

---

## 2. JS モジュール（`site/assets/js/`）
| ファイル | 役割 | 設定 |
|---|---|---|
| `buymo.js` | フォーム検証・送信・スムーズスクロール・FAQ・カルーセル・追従CTA 等 | `ENDPOINT` |
| `simulator-buy.js` | 査定シミュレーター | 係数 `CLASS_BASE` 等 |
| `genres.js` | ジャンル一元管理（フッター/カード） | `GENRES` |
| `analytics.js` | GA4／イベント計測 | `MEASUREMENT_ID` |
| `auth.js` | ログイン・セッション・ページガード | `ENDPOINT` |
| `hq-common.js` | 業務データ共有（案件/加盟店/お知らせ）・ナビ | `ENDPOINT` |
| `board.js` | 看板ボード＋詳細パネル/対応履歴 | （hq-common経由） |
| `hq-leads.js` / `hq-stores.js` / `report.js` | リード／加盟店／レポート | 〃 |
| `member.js` | 会員マイページ | `ENDPOINT` |
| `academy.js` | コース/進捗/クイズ/修了証 | `COURSES`・`QUIZ` |
| `chatbot.js` | ユーザー/加盟店 切替ボット | `KB`・`BUYMO_BOT_MODE` |
| `notices.js` | 加盟店お知らせバナー | （hq-common経由） |

> ⚠️ `ENDPOINT` 設定が必要なファイルは **`auth.js`・`buymo.js`・`hq-common.js`・`member.js`・`report.js`**（＋AUC既存の `app.js`）。`board.js`/`hq-leads.js`/`hq-stores.js`/`notices.js` は `hq-common.js` 経由、`academy.js`/`simulator-buy.js`/`chatbot.js`/`genres.js` はクライアント完結で個別設定不要。公開時は上記すべてに **同じ GAS `/exec` URL** を入れます。

---

## 3. GAS（`gas/`）エンドポイント
ウェブアプリ（`doPost`/`doGet`）。詳細は各 .gs と `docs/`。

**doPost `type:`**
- `buymo` … 査定/問合せリード（記録＋通知＋ステップメール＋案件生成）
- `stepmail` … 外部（WP等）からステップメール発火（要 `TRIGGER_TOKEN`）
- `case` / `note` / `notice` … 看板更新／対応履歴／お知らせ（要セッショントークン）
- `register` / `contact` / `quote` / `order` / `sell` / `loan` … 既存（AUC含む）

**doGet `action=`**
- `login` / `logout` … 認証（JSONP）
- `cases` / `mycase` … 看板用／会員マイページ用（JSONP）
- `notices` … お知らせ一覧（JSONP）
- `quotes` … 既存（相場回答）

主要シート：`問い合わせ`／`案件ボード`／`対応履歴`／`お知らせ`／`BUYMOステップメール`／`認証ユーザー`／`セッション`／`会員マスタ`。

---

## 4. 設定箇所（公開前に入れる値）
| 何を | どこに |
|---|---|
| GAS ウェブアプリURL `/exec` | `auth.js`・`buymo.js`・`hq-common.js`・`member.js`・`report.js` の `ENDPOINT`（＋既存 `app.js`） |
| GA4 測定ID `G-XXXX` | `analytics.js` の `MEASUREMENT_ID` |
| 本番ドメイン | `tools/gen-area.js`・`tools/gen-genre.js` の `SITE_URL` → 再生成、`robots.txt` |
| ステップメール送信元/会員URL/合言葉 | `gas/StepMail.gs` の `stepCfg_()` |
| 認証アカウント | GASで `createUser(email,pw,role,name)` を実行 |
| ステップメール定期実行 | GASトリガーで `runStepMails`（日次） |

---

## 5. localStorage キー（デモ/クライアント保存）
`buymo_session`（ログイン）／`buymo_cases`（案件）／`buymo_stores`（加盟店）／`buymo_notices`・`buymo_notice_seen`（お知らせ）／`buymo_academy_progress`・`buymo_academy_passed`（学習）／`buymo_community`（投稿）／`buymo_member_email`（会員）。
※ GAS接続時はサーバー（スプレッドシート）が正。localStorage はデモ/未接続時のフォールバック。

---

## 6. 公開チェックリスト
- [ ] 本番ドメイン確定 → `SITE_URL` 設定＆ `node tools/gen-area.js && node tools/gen-genre.js`／`robots.txt` 絶対URL
- [ ] GAS デプロイ → 各 `ENDPOINT` 設定（`docs/デプロイ手順.md`）
- [ ] 認証アカウント作成（本部・加盟店）／本番ハードニング検討（`docs/BUYMO_認証設計.md`）
- [ ] `runStepMails` トリガー登録（`gas/ステップメール_セットアップ.md`）
- [ ] GA4 ID 設定＋`generate_lead` をコンバージョン設定
- [ ] 📞 電話番号統一（表示↔リンク）
- [ ] 実画像差し替え（`docs/BUYMO_画像差し替えガイド.md`）／OGP・faviconのBUYMO化
- [ ] 架空の声・実績を実データへ（現状「※イメージ」明記）
- [ ] 各ジャンルLPをLPtoolsで作成 →`genres.js`を`live`化／`sitemap.xml`追加
- [ ] 法務レビュー（特商法・古物商・プライバシー・契約文言）

---

## 7. 関連ドキュメント
`docs/プロジェクト状況.md`（サマリ）／`docs/BUYMO_業務システム設計.md`／`docs/BUYMO_認証設計.md`／`docs/BUYMO_画像差し替えガイド.md`／`docs/buymo_seo_mindmap.*`／`gas/ステップメール_セットアップ.md`／`gas/セットアップ手順.md`／`site/embed/`（LPtools原稿・スニペット）。
