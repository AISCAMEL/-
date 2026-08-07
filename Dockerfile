# Hub API 用 Dockerfile（Railway / Render / VPS などで利用）
# リポジトリルートをビルドコンテキストにしてください。
FROM node:20-slim

RUN apt-get update -y && apt-get install -y openssl && rm -rf /var/lib/apt/lists/*
RUN corepack enable
WORKDIR /app

# 依存解決（モノレポ全体）
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml tsconfig.base.json ./
COPY apps ./apps
COPY packages ./packages
RUN pnpm install --frozen-lockfile

# Prisma Client 生成（@prisma/client を node_modules に生成）
RUN pnpm --filter @hub/db run generate

ENV NODE_ENV=production
ENV API_PORT=3001
EXPOSE 3001

# 起動時にマイグレーション実行 → API サーバー起動
CMD pnpm --filter @hub/db exec prisma migrate resolve --rolled-back 20260801010000_add_manual_supplier 2>/dev/null || true; \
    pnpm --filter @hub/db exec prisma migrate deploy && \
    pnpm --filter @hub/api exec tsx src/server.ts
