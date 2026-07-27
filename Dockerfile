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

# @hub/db は dist/ にビルドが必要（Prisma generate + tsc）
RUN pnpm --filter @hub/db run build

ENV NODE_ENV=production
ENV API_PORT=3001
EXPOSE 3001

# tsx でそのまま実行（ビルド不要）
CMD ["pnpm", "--filter", "@hub/api", "exec", "tsx", "src/server.ts"]
