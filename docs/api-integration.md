# 外部連携API（v1）ガイド

御社のサイト・LINE・他システムから、APIキーで査定依頼や連絡先を登録できます。

## 1. APIキーを発行する

1. 管理画面 →「🔌 API連携」
2. 用途名（例：HP査定フォーム）を入れて「発行」
3. 表示された `aiop_live_...` を**その場でコピー**（以後は再表示できません。紛失時は再発行）

キーはテナント（御社）に紐づき、そのキーで送られたデータは御社のアカウントに入ります。

## 2. 認証

すべての `/api/v1/*` はАPIキーが必要です。次のどちらかのヘッダで渡します：

```
X-API-Key: aiop_live_xxxxxxxx
```
または
```
Authorization: Bearer aiop_live_xxxxxxxx
```

Base URL は御社バックエンドの `https://<...>.onrender.com` です。

## 3. エンドポイント

### 査定依頼を登録：`POST /api/v1/inquiries`

御社サイトのフォーム送信時に呼ぶと、連絡先（カテゴリ「査定見込み」）に登録し、担当へメール通知します。

| フィールド | 必須 | 説明 |
|---|---|---|
| `name` | ※1 | お名前 |
| `phone` | ※1 | 電話番号 |
| `email` | ※1 | メール |
| `car` / `make` / `model` / `year` / `mileage` | 任意 | 車両情報（メモにまとめて保存） |
| `message` / `note` | 任意 | 自由記入 |
| `category` | 任意 | 既定は「査定見込み」 |

※1 name / phone / email のいずれか1つ以上は必須。

```bash
curl -X POST https://<...>.onrender.com/api/v1/inquiries \
  -H "X-API-Key: aiop_live_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"name":"山田太郎","phone":"09012345678","email":"taro@example.com","car":"プリウス 2019 4万km","message":"出張査定希望"}'
```

レスポンス：
```json
{ "ok": true, "contact_id": "ct-xxxx" }
```

### 連絡先を作成：`POST /api/v1/contacts`

```json
{ "contacts": [ {"name":"…","phone_number":"…","email":"…","category":"…","note":"…"} ] }
```
（単体オブジェクトでも可）

### 連絡先を取得：`GET /api/v1/contacts?category=&q=&status=`

登録済みの連絡先を返します（他システムへの取り込み用）。

### 空き枠に自動予約：`POST /api/v1/appointments/auto-book`

希望日・査定タイプを渡すと、Googleカレンダーと既存予約を避けて**最短の空き枠に仮予約**します（AIが通話中に使うのと同じ仕組み）。

| フィールド | 説明 |
|---|---|
| `type` | 出張査定 / 持込査定 / オンライン査定 など |
| `customer_name` / `phone_number` | 顧客情報 |
| `date` | 希望日 YYYY-MM-DD（省略時は最短の空き） |
| `preferredHHMM` | 希望時刻 "14:00"（近い枠を優先） |
| `withinDays` | 希望日に空きが無い場合、何日先まで探すか（既定14） |

```json
{ "ok": true, "slot": {"start":"...","end":"..."}, "appointment": {"id":"...","status":"tentative"} }
```
空きが無い場合は 409 と `alternatives`（その日の空き枠候補）を返します。

## 4. HTMLフォームからの最小例

```html
<form id="satei">
  <input name="name" placeholder="お名前">
  <input name="phone" placeholder="電話番号">
  <input name="car" placeholder="車種・年式・走行">
  <button>査定を申し込む</button>
</form>
<script>
document.getElementById('satei').addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.target;
  await fetch('https://<...>.onrender.com/api/v1/inquiries', {
    method: 'POST',
    headers: { 'X-API-Key': 'aiop_live_xxxxxxxx', 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: f.name.value, phone: f.phone.value, car: f.car.value }),
  });
  alert('査定依頼を受け付けました');
});
</script>
```

> セキュリティ：APIキーは秘密情報です。公開ページのJavaScriptに直接埋め込むとキーが露出します。
> 本番では、フォーム送信は自社サーバー（またはGAS等）を経由し、キーはサーバー側に保管して呼び出すことを推奨します。

## 5. 失効・再発行

- 「API連携」画面でいつでも**失効**できます（漏洩時・退役時）。失効すると即座にそのキーは使えなくなります。
- 失効しても他のキーには影響しません。用途ごとに分けて発行するのがおすすめです。
