# 🌊 IWASAWA SURF BASE 初回設計ドキュメント

> 「福島の波を、もっと近くに。」
> 学べる / 借りられる / 移動できる / 現地を案内してもらえる をひとつにする、
> ワンストップ型の海体験サービス。

- 拠点：福島県双葉郡広野町・岩沢海岸エリア
- 本書の目的：既存構想を壊さずに「会員制コミュニティ化」と「管理画面試作」へ進むための起点整理
- 最終更新：2026-06-07

---

## 0. このドキュメントの位置づけ

セクション10「初回タスク」への回答。以下を一括で定義する。

1. 現在のサイト全体構成の再整理
2. 会員制コミュニティの画面一覧
3. 管理画面の画面一覧
4. 必要テーブル定義案
5. MVPスコープの明確化
6. 最初に作るべき画面の優先順位

開発の進め方（セクション8）と出力形式（セクション9）に従い、
各機能ブロックは「目的 / 既存構成との関係 / 画面構成 / 主要機能 / 必要データ定義 / UI・UXポイント / 次フェーズ拡張案」で記述する。

---

## 1. 現在のサイト全体構成（再整理）

IWASAWA SURF BASE は **3つの公開面 + 1つの非公開面** で構成する。

```
IWASAWA SURF BASE
├─ A. 公開サイト（Marketing / 入口）
│   └─ トップページ（Hero / Concept / 4サービス / Plan / Area / Flow / About / FAQ / Contact）
│       └─ 4サービス：オンラインスクール / ギアレンタル / 移動サポート / ローカルガイド
│
├─ B. オンラインショップ（無在庫・受注起点 / 法務必須）
│   └─ 商品一覧 → 商品詳細 → カート → 注文 → 注文ステータス
│       └─ 特商法・返品ポリシー・発送目安・PSP決済前提
│
├─ C. コミュニティ（★今回会員制化）
│   └─ 閲覧（ゲスト範囲制御）/ 投稿 / 波情報 / イベント / プロフィール
│       └─ カテゴリ：waves / experiences / questions / events / gear
│
└─ D. 管理画面（★今回試作 / 非公開・運用寄り）
    └─ ダッシュボード / 投稿 / イベント / 会員 / お知らせ / 通報 / 導線管理
```

### 横断する共通基盤（今回新設）

| 基盤 | 役割 | 影響範囲 |
|------|------|----------|
| 認証（Auth） | ログイン/登録/セッション | Community, Admin |
| 会員（Member） | 会員種別・プロフィール | Community, Admin |
| 権限（Role） | Visitor/Beginner/Local/Staff/Admin | 全体のゲート |
| 通知導線（LINE CTA） | 強い導線として全面で維持 | TOP, Community |

### 導線の原則（崩さない）

- TOP → 4サービス理解 → Shop / Community へ自然に分岐
- Community 内に常時 LINE CTA を残す
- 初心者が怖くならない順序（学ぶ→借りる→移動→案内→仲間）
- 県外ユーザーが「自分が参加する画」を想像できるコピー
- スマホ前提（モバイルファースト）

---

## 2. 会員制コミュニティ 画面一覧

### 今回追加する目的
閲覧専用ページを、継続利用される会員制コミュニティへ進化させる。
「見るだけ」から「参加し、また来る」へ。閉じたローカル感は出さず、初心者と県外客が自然に入れる温度感を保つ。

### 既存構成との関係
既存コミュニティ構成（Hero / コンセプト帯 / 投稿フィード / カテゴリフィルター / 投稿フォーム / 波情報ウィジェット / イベント一覧 / メンバータイプ紹介 / 利用者の声 / LINE CTA）を**土台として維持**。
その上に「認証ゲート」と「会員のマイページ」を重ねる。フィードや波情報は残し、投稿フォームを「会員のみ」に制御する。

### 画面構成（公開コミュニティ面）

| # | 画面 | ルート(案) | アクセス | 概要 |
|---|------|-----------|---------|------|
| C-01 | コミュニティTOP（フィード） | `/community` | ゲスト可（一部制限） | 既存構成を継承。投稿フィード＋波情報＋イベント |
| C-02 | 投稿詳細 | `/community/posts/:id` | ゲスト可（コメントは会員） | 本文・コメント・いいね |
| C-03 | カテゴリ別一覧 | `/community/c/:category` | ゲスト可 | waves/experiences/questions/events/gear |
| C-04 | ログイン | `/login` | 未認証 | メール/パスワード＋LINE連携想定 |
| C-05 | 新規登録 | `/signup` | 未認証 | 会員種別の初期値は Beginner |
| C-06 | パスワード再設定 | `/password/reset` | 未認証 | メールリンク方式 |
| C-07 | 投稿作成/編集 | `/community/new` | 会員のみ | カテゴリ選択・画像添付 |
| C-08 | マイプロフィール | `/me` | 会員のみ | 表示名・自己紹介・会員種別バッジ |
| C-09 | 自分の投稿一覧 | `/me/posts` | 会員のみ | 下書き/公開/非公開 |
| C-10 | いいね履歴 | `/me/likes` | 会員のみ | 履歴ベース設計（拡張前提） |
| C-11 | 参加イベント履歴 | `/me/events` | 会員のみ | 申込/参加済み |
| C-12 | 他会員プロフィール | `/u/:handle` | ゲスト可（範囲制限） | 種別バッジ・公開投稿のみ |
| C-13 | イベント詳細/申込 | `/community/events/:id` | 閲覧ゲスト可/申込会員 | 定員・受付状態 |

### ゲスト閲覧範囲の制御（重要ルール）

| 操作 | Visitor(ゲスト) | Beginner | Local | Staff/Admin |
|------|:---:|:---:|:---:|:---:|
| フィード閲覧 | ⭕（一部ぼかし） | ⭕ | ⭕ | ⭕ |
| 投稿詳細閲覧 | ⭕（最新数件） | ⭕ | ⭕ | ⭕ |
| いいね | ❌ | ⭕ | ⭕ | ⭕ |
| コメント | ❌ | ⭕ | ⭕ | ⭕ |
| 投稿作成 | ❌ | ⭕（質問/体験） | ⭕（＋波情報） | ⭕ |
| 波情報の投稿 | ❌ | ❌ | ⭕ | ⭕ |
| イベント申込 | ❌ | ⭕ | ⭕ | ⭕ |
| 管理画面 | ❌ | ❌ | ❌ | ⭕ |

> ゲストには「続きは登録して」と圧をかけすぎない。1〜2タップで登録できる導線にし、LINE連携を最短ルートにする。

### 会員種別（たたき台 → 確定方針）

| 種別 | 役割 | 既定権限 | 昇格条件(案) |
|------|------|---------|-------------|
| Visitor | 閲覧中心 | 閲覧のみ | 登録で Beginner へ |
| Beginner | 質問・体験共有 | 投稿(questions/experiences)、いいね、申込 | 既定値 |
| Local | 波情報・ローカル知見 | ＋ waves 投稿、信頼バッジ | 運営が手動付与 |
| Staff/Admin | 運営 | 全権 + 管理画面 | 運営が付与 |

### 主要機能
- 認証（登録/ログイン/再設定）、セッション維持
- 会員種別ベースの権限ゲート（UI制御＋API制御の二重化）
- 投稿CRUD（カテゴリ・画像・公開状態）
- いいね/コメント、履歴の蓄積
- イベント閲覧・申込、履歴
- 波情報ウィジェット（既存を維持、投稿はLocal以上）
- 通報導線（不適切投稿→管理画面へ）

### 必要データ定義
→ 「4. テーブル定義案」参照（members, posts, comments, likes, events, event_entries, reports など）

### UI/UXポイント
- 感性寄り：透明感・奥行き・朝の海。Deep Navy × Ocean Blue × Sand Beige。
- 会員種別はバッジで可視化（Localは信頼の証としてさりげなく強調）。
- ゲートは「壁」ではなく「招待」。ぼかし＋やわらかいCTAで。
- LINE CTAは画面下に常駐（モバイル）。

### 次フェーズ拡張案
- フォロー/通知、メンション、保存（ブックマーク）
- 波情報の外部API連携（潮汐・風・波高）
- バッジ/実績ゲーミフィケーション、関係人口スコア
- スクール受講履歴・レンタル履歴とプロフィール連携

---

## 3. 管理画面 画面一覧

### 今回追加する目的
コミュニティを「運営できる状態」にする。投稿・イベント・会員・通報を実務的に捌ける最小の運営基盤を試作する。

### 既存構成との関係
公開面の世界観は引き継ぐが、**管理画面は装飾過多を避け、実務的で見やすく**する（セクション5）。配色はベースを踏襲しつつ情報密度を上げる。

### 画面構成

| # | 画面 | ルート(案) | 概要 |
|---|------|-----------|------|
| AD-00 | 管理ログイン/ゲート | `/admin/login` | Staff/Admin のみ |
| AD-01 | ダッシュボード | `/admin` | 主要指標サマリ |
| AD-02 | 投稿管理 | `/admin/posts` | 一覧/操作 |
| AD-03 | イベント管理 | `/admin/events` | 作成/編集/受付/定員/表示順 |
| AD-04 | 会員管理 | `/admin/members` | 種別変更/状態/権限/履歴 |
| AD-05 | お知らせ管理 | `/admin/announcements` | 作成/公開/掲載先 |
| AD-06 | 通報/要確認 | `/admin/reports` | 通報一覧→投稿対応 |
| AD-07 | 導線管理 | `/admin/navigation` | TOP/Shop/Community バナー・CTA |
| AD-08 | （拡張）注文管理 | `/admin/orders` | 無在庫フロー運用（Phase2+） |

### ダッシュボード指標
- 会員数 / 新規登録数（当日・期間）
- 今日の投稿数
- イベント数（開催予定・受付中）
- 承認待ち件数（通報・要確認）
- よく見られているカテゴリ（閲覧数ランキング）

### 投稿管理 操作
一覧表示 / 公開・非公開 / 注目表示(featured) / カテゴリ変更 / 削除 / 通報確認

### イベント管理 操作
作成 / 編集 / 受付状態切替（受付前・受付中・締切・終了）/ 定員管理 / 表示順変更

### 会員管理 操作
会員種別変更 / 状態管理（有効・停止）/ 管理者権限付与 / 投稿履歴確認

### 主要機能
- ロールゲート（Staff/Admin）。操作ログ（誰が何を）を最小限記録。
- 一覧は検索・フィルタ・ページング前提。
- 破壊的操作（削除・停止）は確認ダイアログ。

### 必要データ定義
→ announcements, reports, navigation_items, admin_audit_logs（＋公開面テーブルを共有）

### UI/UXポイント
- 左サイドナビ＋上部ステータス。テーブル中心で1画面完結。
- 色は控えめ、状態はバッジ/タグで素早く判別。
- スマホでも最低限操作可（運営が現地で使う想定）。

### 次フェーズ拡張案
- 注文管理（無在庫フロー：注文→在庫確認→受注確定→発送→通知→欠品対応）
- 役割の細分化（モデレーター）、CSV出力、通知テンプレ管理

---

## 4. 必要テーブル定義案

> DBは将来移行しやすいよう中立に定義。MVPは PostgreSQL（Supabase等）想定。
> すべて `id`(uuid)、`created_at`、`updated_at` を基本に持つ。

### members（会員）
| カラム | 型 | 説明 |
|--------|----|------|
| id | uuid | PK |
| email | text | ログインID（unique） |
| password_hash | text | PSP同様、生パスワードは保持しない |
| handle | text | 表示用ハンドル（unique） |
| display_name | text | 表示名 |
| bio | text | 自己紹介 |
| avatar_url | text | アイコン |
| role | enum | visitor / beginner / local / staff / admin |
| status | enum | active / suspended |
| line_user_id | text | LINE連携（任意） |
| home_area | text | 地元/県外などの属性（任意） |
| last_login_at | timestamptz | 最終ログイン |

### posts（投稿）
| カラム | 型 | 説明 |
|--------|----|------|
| id | uuid | PK |
| author_id | uuid | FK→members |
| category | enum | waves / experiences / questions / events / gear |
| title | text | 任意 |
| body | text | 本文 |
| status | enum | draft / published / hidden |
| is_featured | bool | 注目表示 |
| view_count | int | 閲覧数（人気カテゴリ集計用） |
| like_count | int | 非正規化キャッシュ |

### post_images（投稿画像）
| id | uuid | PK |
| post_id | uuid | FK→posts |
| url | text | 画像URL |
| sort_order | int | 並び |

### comments（コメント）
| id | uuid | PK |
| post_id | uuid | FK→posts |
| author_id | uuid | FK→members |
| body | text | 本文 |
| status | enum | published / hidden |

### likes（いいね）
| id | uuid | PK |
| member_id | uuid | FK→members |
| post_id | uuid | FK→posts |
| (unique) | | (member_id, post_id) |

### events（イベント）
| id | uuid | PK |
| title | text | タイトル |
| description | text | 詳細 |
| location | text | 場所（岩沢海岸 等） |
| starts_at | timestamptz | 開始 |
| ends_at | timestamptz | 終了 |
| capacity | int | 定員 |
| entry_status | enum | upcoming / open / closed / finished |
| sort_order | int | 表示順 |
| created_by | uuid | FK→members(staff) |

### event_entries（イベント申込）
| id | uuid | PK |
| event_id | uuid | FK→events |
| member_id | uuid | FK→members |
| status | enum | applied / confirmed / cancelled / attended |
| (unique) | | (event_id, member_id) |

### reports（通報/要確認）
| id | uuid | PK |
| target_type | enum | post / comment |
| target_id | uuid | 対象ID |
| reporter_id | uuid | FK→members |
| reason | text | 理由 |
| status | enum | open / reviewing / resolved / dismissed |
| handled_by | uuid | FK→members(staff) |

### announcements（お知らせ）
| id | uuid | PK |
| title | text | 件名 |
| body | text | 本文 |
| placement | enum | top / shop / community / all |
| is_published | bool | 公開 |
| published_at | timestamptz | 公開日時 |

### navigation_items（導線管理）
| id | uuid | PK |
| surface | enum | top / shop / community |
| label | text | 表示文言 |
| href | text | リンク先 |
| kind | enum | banner / cta / link |
| is_active | bool | 表示 |
| sort_order | int | 並び |

### admin_audit_logs（操作ログ・最小）
| id | uuid | PK |
| actor_id | uuid | FK→members(staff) |
| action | text | 例：post.hide / member.suspend |
| target_type | text | 対象種別 |
| target_id | uuid | 対象ID |
| meta | jsonb | 補足 |

### waves_reports（波情報・任意/Local投稿）
| id | uuid | PK |
| author_id | uuid | FK→members(local+) |
| observed_at | timestamptz | 観測時刻 |
| wave_height | text | 波高（例：腰〜胸） |
| wind | text | 風 |
| condition | text | コンディション所見 |
| spot | text | スポット名 |

### Shop系（Phase2以降・既存法務思想を踏襲、定義のみ先出し）
- `products`（name, description, category, price, shipping_fee, lead_time_text, status…即納表現禁止・納期具体）
- `product_variants`（size, color, sku）
- `orders`（status: pending→stock_check→confirmed→shipped→delivered / cancelled・refunded、無在庫フロー準拠）
- `order_items`, `shipments`, `legal_pages`（特商法・返品・発送目安）
> カード情報は自社保持せず、決済はPSP（外部）前提。`orders` には PSPの参照IDのみ保持。

---

## 5. MVP スコープの明確化

### MVPで「作る」
コミュニティ会員制化と運営の最小ループに集中する。

公開コミュニティ：
- C-04 ログイン / C-05 新規登録 / C-06 再設定
- C-01 フィード（ゲスト範囲制御つき）/ C-02 投稿詳細
- C-07 投稿作成 / C-08 マイプロフィール / C-09 自分の投稿一覧
- いいね（C-10は履歴“ベース”のみ）

管理画面：
- AD-00 ゲート / AD-01 ダッシュボード（主要指標）
- AD-02 投稿管理（公開・非公開・注目・削除・カテゴリ変更）
- AD-04 会員管理（種別変更・状態・権限・履歴）
- AD-06 通報（最小：一覧→対象を非公開）

データ：
- members / posts / post_images / comments / likes / reports / admin_audit_logs
- events / event_entries は「定義＋一覧表示」まで（申込導線は次フェーズ）

### MVPで「作らない（次フェーズへ）」
- Shop の実装（テーブル定義のみ先出し、UIはPhase2）
- イベント申込フロー・定員リアルタイム制御
- 波情報の外部API連携
- フォロー/通知/メンション/ブックマーク
- 導線管理（AD-07）の動的化（MVPは静的設定でも可）
- 注文管理（AD-08）

### MVPの完了条件（Done）
1. ゲストはフィードを一部閲覧でき、登録するとBeginnerとして投稿・いいねできる
2. Staff/Adminは管理画面で投稿の公開状態と会員種別を操作できる
3. 通報された投稿を運営が非公開にできる
4. 世界観・LINE CTA・法務前提の文言が崩れていない

---

## 6. 最初に作るべき画面の優先順位

実装は「土台 → 入口 → 中身 → 運営」の順。各ステップは単独で動作確認してから次へ（セクション8/9-2）。

| 優先 | ステップ | 画面/対象 | 理由 |
|:---:|------|----------|------|
| 1 | 認証基盤 + members | C-05 登録 / C-04 ログイン | 全機能の前提。ここが無いと会員制が成立しない |
| 2 | 権限ゲート | role 判定の共通化 | ゲスト範囲制御・管理ゲートの土台 |
| 3 | コミュニティTOP | C-01 フィード（ゲスト制御） | 既存資産。会員制の効果が最初に見える |
| 4 | 投稿の中身 | C-02 詳細 / C-07 作成 / いいね | 「参加できる」体験の核 |
| 5 | マイページ | C-08 プロフィール / C-09 自分の投稿 | 継続利用の動機づけ |
| 6 | 管理ダッシュボード | AD-00 / AD-01 | 運営が状態を把握できる |
| 7 | 投稿管理 | AD-02 | 公開/非公開/注目/削除の実運用 |
| 8 | 会員管理 | AD-04 | 種別付与（Local昇格など）の運用 |
| 9 | 通報 | AD-06 | 健全性の最低ライン |

> 推奨：まず **ステップ1〜2（認証＋権限）** を1スプリントで固め、その後 **3〜5（公開コミュニティ）**、最後に **6〜9（管理）** の3ブロックで進める。

---

## 7. 技術スタック提案（要確認事項）

MVPを「次に拡張しやすい構造」で組むための提案。確定前にユーザー確認を推奨。

- フロント：Next.js（App Router）+ TypeScript + Tailwind CSS
  - 公開面の表現力（透明感・スクロール演出）と管理面の実務UIを両立しやすい
- 認証/DB：Supabase（Auth + Postgres + Storage）
  - RLSで「ゲスト範囲制御」「ロールゲート」をDB層でも担保 → UIとAPIの二重化が容易
- 決済（Phase2）：Stripe等のPSP（カード情報を自社保持しない要件に直結）
- LINE：ログイン連携（LINE Login）＋ CTA。MVPはCTAリンク優先、連携は段階導入

> ※ もし「静的サイト＋スプレッドシート/GAS」運用を希望する場合は、別案件(CarLoan_System)の構成に寄せることも可能。会員制・権限制御・拡張性を考えると上記スタックを推奨。

---

## 8. 次アクション（このドキュメント承認後）

1. 技術スタックの確定（セクション7）
2. リポジトリ初期化（Next.js + Supabase雛形）
3. ステップ1（認証＋members）の画面試作 → 動作確認
4. 以降、優先順位（セクション6）に沿って1ブロックずつ実装・確認

---

_本書は既存「IWASAWA SURF BASE」構想の延長線上の整理であり、世界観・導線・運用思想・法務前提を維持している。_
