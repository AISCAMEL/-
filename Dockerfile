# バイモダイレクト 加盟店ポータル（依存ライブラリ不要のNodeサーバー）
FROM node:20-alpine
WORKDIR /app

# アプリ本体（サーバー＋サイト）をコピー
COPY server ./server
COPY site ./site

# 実行時設定（本番では BMD_SECRET を必ず上書きすること）
ENV PORT=8080
ENV SESSION_HOURS=12
EXPOSE 8080

# アカウント/ログ/保存データの保存先。
#  - 既定（未設定）では /app/server 配下に作成される。
#  - 本番では BMD_DATA_DIR に永続ディスクのマウント先を指定し、そこへ集約する
#    （partners.json・access.log・data/records.json が1箇所にまとまる）。
#    Render は render.yaml で /var/bmd-data を自動マウント。
CMD ["node", "server/server.js"]
