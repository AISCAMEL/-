# Hub API 用 Dockerfile（Railway / Render / VPS などで利用）
# リポジトリルートをビルドコンテキストにしてください。
FROM node:20-slim

RUN corepack enable
WORKDIR /app

# 依存解決（モノレポ全体）
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml tsconfig.base.json ./
COPY apps ./apps
COPY packages ./packages
RUN pnpm install --frozen-lockfile

ENV NODE_ENV=production
ENV API_PORT=3001
EXPOSE 3001

# tsx でそのまま実行（ビルド不要）
CMD ["pnpm", "--filter", "@hub/api", "exec", "tsx", "src/server.ts"]
