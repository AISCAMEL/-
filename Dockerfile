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

# アカウント/ログ/保存データは /app/server 配下に作成される。
# 永続化する場合はこのディレクトリ（特に server/partners.json と server/data）を
# ボリュームにマウントすること（README 参照）。
CMD ["node", "server/server.js"]
