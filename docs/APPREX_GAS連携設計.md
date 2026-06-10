# 🔗 お問い合わせ後の自動化フロー設計（GAS連携）— 確認用

> 「スプレッド → Asana → Slack → 自動返信 → ステップメール」の内容と繋ぎ方をまとめたものです。**この内容でよいかご確認ください。**

## 1. 全体の流れ

```
来訪者がフォーム送信 / 発注
        │
        ▼
WordPress（テーマ apprex）
 ├─ ① 自動返信メール（種別別）……顧客へ即時
 ├─ ② 管理者通知メール ……運営へ即時
 ├─ ③ ステップメール登録（種別別・最大1年/予約リマインダー）
 └─ ④ GAS Webhook へ送信（JSON）
                 │
                 ▼
        Google Apps Script（Webアプリ）
         ├─ A. スプレッドシートに1行追記（受付台帳）
         ├─ B. Asana にタスク作成（対応漏れ防止）
         └─ C. Slack に通知（リアルタイム共有）
```

- ①②③ は **WordPress 側で完結**（設定だけで稼働）。
- ④以降の **スプレッド→Asana→Slack** は **GAS** が担当。WPは「GASに丸投げ」するだけなので柔軟・最短。

## 2. WordPress → GAS に送るデータ（JSON）

```json
{
  "event": "inquiry",            // または "order"
  "token": "（共有トークン）",
  "site": "https://site.aiscompany.jp/",
  "time": "2026-06-10 12:34:56",
  "data": {
    "id": 123,
    "type": "document",          // contact/document/trial/meeting/estimate
    "type_label": "資料請求",
    "name": "山田太郎",
    "company": "サンプル商事",
    "email": "taro@example.com",
    "phone": "090-xxxx-xxxx",
    "message": "資料希望",
    "meeting_at": "",            // ミーティング予約時のみ日時
    "admin_url": "https://site.aiscompany.jp/wp-admin/post.php?post=123&action=edit"
  }
}
```
発注（order）の場合は `data` に `service / plan / billing / monthly / oneoff / annual` が入ります。

## 3. 各連携先の内容（確認ポイント）

### A. スプレッドシート（受付台帳）
1行＝1件。列の例：
`日時 / 種別 / 氏名 / 会社 / メール / 電話 / 内容 / 予約日時 / 金額 / WP管理リンク`

### B. Asana タスク
- タイトル：`【種別】氏名（会社）`（例：`【資料請求】山田太郎（サンプル商事）`）
- 説明：メール・電話・内容・WP管理リンク・（発注なら見積金額）
- 用途：対応漏れ防止。担当割当・期日はAsana側の運用ルールで。

### C. Slack 通知（例）
```
:bell: 新規【資料請求】
氏名: 山田太郎（サンプル商事）
メール: taro@example.com / 電話: 090-xxxx-xxxx
内容: 資料希望
▶ 管理画面: https://site.aiscompany.jp/wp-admin/...
```

## 4. WordPress 側の自動返信・ステップメール（確認用・現状）

| 種別 | 即時の自動返信 | フォロー配信 |
|------|----------------|--------------|
| 資料請求 | 資料DLリンク付き | 1/3/7/14/30 日 |
| 見積もり・発注 | 見積明細付き | 1/3/7/14/30 日 |
| 30日お試し | 開始案内 | 1/3/7/14/25/30 日（終了前リマインド含む） |
| ミーティング予約 | 日時確認 | 予約日時の前日・直前・翌日フォロー |
| お問い合わせ | 受付 | 1/3/7/14/30/90/180/365 日 |

> 文面はテーマの `apprex_step_mails` / `apprex_meeting_reminders` / `apprex_autoreply_body` フィルタで自由に編集できます。**文面の修正希望があればこの表に赤入れでお戻しください。**

---

## 5. 設定手順

### WordPress 側
1. **設定 > APPREX 連携** を開く
2. **GAS Webhook URL**：後述のGASをデプロイして得た `…/exec` を貼る
3. **GAS 共有トークン**：任意の合言葉（GAS側と同じ値）

### GAS 側
1. スプレッドシートを作成（タブ名 `受付`）
2. 拡張機能 > Apps Script に下記コードを貼る
3. `CONFIG` を設定（SHEET_ID / SHARED_TOKEN / SLACK_WEBHOOK / ASANA_TOKEN / ASANA_PROJECT）
4. デプロイ > 新しいデプロイ > 種類「ウェブアプリ」/ アクセス「全員」→ URL を WP に登録

```javascript
// ===== APPREX 受付 → スプレッド/Asana/Slack =====
const CONFIG = {
  SHARED_TOKEN: 'ここにWPと同じ合言葉',
  SHEET_ID:     'スプレッドシートのID',
  SHEET_NAME:   '受付',
  SLACK_WEBHOOK: 'https://hooks.slack.com/services/XXX/YYY/ZZZ', // Incoming Webhook
  ASANA_TOKEN:   'Asanaの個人アクセストークン（任意）',
  ASANA_PROJECT: 'AsanaプロジェクトGID（任意）'
};

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== CONFIG.SHARED_TOKEN) {
      return ContentService.createTextOutput('forbidden');
    }
    const d = body.data || {};
    const amount = d.billing === 'monthly' ? ('月額' + (d.monthly||0)) :
                   d.oneoff ? (d.oneoff + '円') : '';

    // A. スプレッドシート
    const sh = SpreadsheetApp.openById(CONFIG.SHEET_ID).getSheetByName(CONFIG.SHEET_NAME);
    sh.appendRow([body.time, d.type_label||d.type, d.name, d.company, d.email,
                  d.phone, d.message, d.meeting_at||'', amount, d.admin_url]);

    // B. Asana（任意）
    if (CONFIG.ASANA_TOKEN && CONFIG.ASANA_PROJECT) {
      const notes = `メール: ${d.email}\n電話: ${d.phone||''}\n内容: ${d.message||''}\n` +
                    (amount?`金額: ${amount}\n`:'') + `管理: ${d.admin_url||''}`;
      UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
        method: 'post', muteHttpExceptions: true,
        headers: { Authorization: 'Bearer ' + CONFIG.ASANA_TOKEN },
        contentType: 'application/json',
        payload: JSON.stringify({ data: {
          name: `【${d.type_label||d.type}】${d.name}（${d.company||''}）`,
          notes: notes, projects: [CONFIG.ASANA_PROJECT]
        }})
      });
    }

    // C. Slack
    if (CONFIG.SLACK_WEBHOOK) {
      const text = `:bell: 新規【${d.type_label||d.type}】\n` +
        `氏名: ${d.name}（${d.company||''}）\n` +
        `メール: ${d.email} / 電話: ${d.phone||''}\n` +
        (amount?`金額: ${amount}\n`:'') +
        `内容: ${d.message||''}\n▶ ${d.admin_url||''}`;
      UrlFetchApp.fetch(CONFIG.SLACK_WEBHOOK, {
        method: 'post', contentType: 'application/json',
        payload: JSON.stringify({ text })
      });
    }
    return ContentService.createTextOutput('ok');
  } catch (err) {
    return ContentService.createTextOutput('error: ' + err);
  }
}
```

---

## 6. ご確認いただきたいこと
1. スプレッドの**列構成**はこれでOKか
2. **Slackの文面**・チャンネルはこれでよいか
3. **Asana**は使うか（使う場合はプロジェクトとトークン）
4. **メール文面**（§4の表）に修正はあるか

赤入れ・ご要望をいただければ、文面と列を調整して最終化します。

---

## 7. 記事公開 → SNS連動（追加イベント）

ブログ記事を公開すると、同じ GAS Webhook に `event: "post_published"` が届きます。GAS側で Instagram / X / Facebook / LINE 等へ投稿できます（各SNSのAPI/トークンは GAS 側で設定）。

```json
{
  "event": "post_published",
  "token": "（共有トークン）",
  "data": {
    "id": 123,
    "title": "ノーコードアプリ開発のはじめ方",
    "url": "https://site.aiscompany.jp/...",
    "excerpt": "記事の冒頭抜粋…",
    "image": "https://site.aiscompany.jp/wp-content/uploads/...jpg",
    "ai": true
  }
}
```

GAS の `doPost` 冒頭で `body.event` を分岐し、`post_published` の場合は SNS投稿、`inquiry`/`order` の場合は スプレッド→Asana→Slack、と振り分けてください。
