# まず触れる！デモ公開クイックスタート（無料・キー不要）

ブラウザで管理画面・LP・問い合わせフォームを触れる状態にします。
**電話機能以外**が動くデモです（OpenAI/Twilioキーは後でOK）。所要 約15分・無料。

構成：**バックエンド = Render（無料）** / **フロント = Vercel（無料）**
前提：このリポジトリが GitHub にある／Render・Vercel・GitHub のアカウント（無料）。

---

## STEP 1. バックエンドを Render に公開（約7分）

1. https://render.com にGitHubでログイン。
2. 右上 **New +** → **Blueprint** をクリック。
3. このリポジトリ（`aiscamel/-`）を選択 → ブランチ **`claude/clever-franklin-1rc2cs`** を選ぶ。
4. リポジトリ直下の `render.yaml` が自動で読み込まれる → **Apply / Create** をクリック。
5. デプロイ完了まで数分待つ（Logsに `listening` と出れば成功）。
6. 払い出されたURL（例 `https://ai-operator24-backend-xxxx.onrender.com`）を**コピー**。
7. 動作確認：ブラウザでそのURLの末尾に `/health` を付けて開く。
   `{"ok":true,...}` が表示されればOK。

> このままでデモモード（DB・キー不要、サンプルデータ入り）で動きます。
> ※ 無料プランは無アクセスが続くと休止し、次アクセス時の初回だけ起動に数十秒かかります。

---

## STEP 2. フロントを Vercel に公開（約7分）

1. https://vercel.com にGitHubでログイン。
2. **Add New… → Project** → このリポジトリを **Import**。
3. 設定画面で：
   - **Root Directory** を **`frontend`** に変更（重要）。
   - **Branch** は `claude/clever-franklin-1rc2cs`。
   - **Environment Variables** に1つ追加：
     - Name: `NEXT_PUBLIC_API_BASE_URL`
     - Value: STEP1でコピーした Render のURL（末尾 `/` は付けない）
4. **Deploy** をクリック。完了するとURL（例 `https://ai-operator24-xxxx.vercel.app`）が出る。

---

## STEP 3. 触ってみる

開いたVercelのURLで以下を体験できます。

- **トップ（LP）**：サービス紹介・料金・フッターの法務ページ
- **`/contact`**：問い合わせ・資料請求フォーム（送信できる）
- **`/login`**：管理画面ログイン（デモなので任意の内容でOK）
  - 「店舗オーナー」で入る → 通話履歴・利用状況・FAQ・設定・ユーザー管理
  - 「スーパー管理者」で入る → 運営ダッシュボード・問い合わせ管理・テナント管理

> 問い合わせフォームから送信 → スーパー管理者でログイン →「問い合わせ管理」に表示されます。

---

## STEP 4.（任意）電話とAIを動かす

電話まで繋ぐには、Render のサービスに環境変数を足します（`Environment` タブ）。
- `OPENAI_API_KEY`（OpenAI）
- `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN`（Twilio）
- `PUBLIC_API_BASE_URL` / `PUBLIC_WS_BASE_URL` を Render のURL（`https://…` / `wss://…`）に設定

詳しい電話設定は `docs/twilio-setup.md`、本番化の全体像は `docs/deploy.md` を参照。

---

## うまくいかない時

| 症状 | 対処 |
|------|------|
| 管理画面が真っ白・データが出ない | Vercelの `NEXT_PUBLIC_API_BASE_URL` が Render のURLと一致しているか。設定後に再デプロイ |
| `/health` が開けない | Renderのデプロイがまだ／Logsでエラー確認。`rootDir: backend` になっているか |
| フォーム送信でエラー | Renderが休止からの起動中（数十秒待って再送）。または上記URL設定を確認 |
| 初回アクセスが遅い | 無料プランの休止からの復帰。2回目以降は速い |
