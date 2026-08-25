# assets

ブランド素材を置くフォルダです。

## ロゴ

`logo.png`（または透過PNG）をこのフォルダに置くと、`[carmel_login]` のロゴとして
**自動で使われます**（属性 `logo=""` 未指定時のフォールバック）。

- 推奨：高さ 100px 程度、横長、背景透過
- CarMel 公式ロゴ（「ネットで安心してクルマ頼める！」入り）を配置してください
- 個別に差し替えたい場合は `[carmel_login logo="https://carmelonline.jp/path/logo.png"]`

優先順位：ショートコード属性 `logo` → `assets/logo.png` → 文字ロゴ（`wordmark`）
