import { resolve, dirname } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import { config as loadDotenv } from "dotenv";
import Fastify from "fastify";

const __dirname = dirname(fileURLToPath(import.meta.url));
loadDotenv({ path: resolve(__dirname, "../../../.env") });
import { z } from "zod";
import {
  BaseConnector,
  connectorModes,
  createChannelConnectors,
  createMarketConnectors,
  createSupplierConnectors,
  configFor,
  type MarketResearchConnector,
  type SalesChannelConnector,
  type SupplierConnector,
} from "@hub/connectors";
import {
  CAT_GOODS,
  allKeywords,
  buildSocialLink,
  type ChannelId,
  type MarketId,
  type SocialPlatform,
  type SupplierId,
} from "@hub/core";
import { loadConfig } from "./config.js";
import { importProduct, importManualProduct, publishToChannel } from "./services/listing-service.js";
import { getOrders, getPnl } from "./services/orders-service.js";
import { researchMarket } from "./services/research-service.js";
import { screenCandidates } from "./services/screening-service.js";
import { getLastRun, runSync } from "./services/sync-service.js";
import { getSchedulerInterval, startSyncScheduler } from "./scheduler.js";
import { fetchCnyToJpy, updatePriceRuleFxRate, getCachedRate } from "./services/fx-service.js";
import { getAlerts, getUnreadCount, markAlertRead } from "./services/alert-service.js";
import { runAutoScreen, getAutoScreenStatus } from "./services/auto-screen-service.js";
import { dbEnabled, productRepo } from "@hub/db";
import { fulfillOrder, autoFulfillAll } from "./services/fulfillment-service.js";
import { isWebhookConfigured, sendTestNotification } from "./services/webhook-notify-service.js";
import { repriceAll } from "./services/reprice-service.js";

export function buildServer() {
  const config = loadConfig();
  const app = Fastify({ logger: true });

  const suppliers = createSupplierConnectors(config.connector);
  const channels = createChannelConnectors(config.connector);
  const markets = createMarketConnectors(config.connector);

  const getSupplier = (id: string): SupplierConnector | undefined =>
    suppliers[id as SupplierId];
  const getChannel = (id: string): SalesChannelConnector | undefined =>
    channels[id as ChannelId];
  const getMarket = (id: string): MarketResearchConnector | undefined =>
    markets[id as MarketId];

  app.get("/health", async () => ({ ok: true, mode: config.connector.mode }));

  // ルート: サービス稼働の確認用（ブラウザで開いたとき向け）
  app.get("/", async () => ({
    service: "dropshipping-hub-api",
    status: "ok",
    endpoints: ["/health", "/connectors", "/niche/cat-goods", "/products", "/products/:id", "/research", "/research/screen", "/research/test", "/orders", "/dashboard/pnl", "/sync/run", "/sync/status", "/fx/rate", "/fx/update", "/alerts", "/auth/base/authorize", "/auth/base/exchange"],
  }));

  // 為替レート取得（CNY→JPY）
  app.get("/fx/rate", async () => {
    const rate = await fetchCnyToJpy();
    return { cnyToJpy: rate, cached: getCachedRate() !== null };
  });

  // 為替レートをDBに反映
  app.post("/fx/update", async () => updatePriceRuleFxRate());

  // 為替レートに基づき全商品の売値を再計算
  app.post("/fx/reprice", async () => repriceAll());

  // アラート一覧
  app.get("/alerts", async (req) => {
    const { unread } = req.query as { unread?: string };
    return {
      alerts: getAlerts({ unreadOnly: unread === "1", limit: 50 }),
      unreadCount: getUnreadCount(),
    };
  });

  // アラート既読
  app.post("/alerts/:id/read", async (req, reply) => {
    const { id } = req.params as { id: string };
    return markAlertRead(id) ? { ok: true } : reply.code(404).send({ error: "not found" });
  });

  // 定期スクリーニング手動実行
  app.post("/research/auto-screen", async () => {
    const selectedMarkets = (["amazon", "rakuten", "yahoo"] as const)
      .map(getMarket).filter((m): m is MarketResearchConnector => !!m);
    return runAutoScreen({ resolveSupplier: getSupplier, markets: selectedMarkets });
  });

  // 定期スクリーニング状態
  app.get("/research/auto-screen/status", async () => getAutoScreenStatus());

  // 各コネクタの実効モード（mock | live）。どのデータ源が本番接続かを確認する。
  app.get("/connectors", async () => ({
    defaultMode: config.connector.mode,
    modes: connectorModes(config.connector),
    baseOAuthConfigured: !!config.baseOAuth,
  }));

  // 猫グッズ特化プリセット（リサーチキーワード・推奨スクリーニング・規約注意）
  app.get("/niche/cat-goods", async () => ({
    ...CAT_GOODS,
    allKeywords: allKeywords(CAT_GOODS),
  }));

  // SNS集客: UTM付きの計測リンクを生成（どの投稿から売れたか把握）
  const linkSchema = z.object({
    url: z.string().url(),
    platform: z.enum(["instagram", "tiktok", "x", "youtube"]),
    campaign: z.string().min(1),
    content: z.string().optional(),
  });
  app.get("/marketing/link", async (req, reply) => {
    const parsed = linkSchema.safeParse(req.query);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const { url, platform, campaign, content } = parsed.data;
    return { link: buildSocialLink(url, platform as SocialPlatform, campaign, content) };
  });

  app.get("/suppliers", async () => ({
    suppliers: Object.keys(suppliers),
  }));

  // 仕入れ商品検索
  app.get("/suppliers/:id/products", async (req, reply) => {
    const { id } = req.params as { id: string };
    const { keyword, externalId } = req.query as { keyword?: string; externalId?: string };
    const supplier = getSupplier(id);
    if (!supplier) return reply.code(404).send({ error: "unknown supplier" });
    const products = await supplier.searchProducts({ keyword, externalId });
    return { products };
  });

  // 仕入れ商品の取り込み（価格計算＋規約チェック）
  const importSchema = z.object({ supplierId: z.string(), externalId: z.string() });
  app.post("/products/import", async (req, reply) => {
    const parsed = importSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const supplier = getSupplier(parsed.data.supplierId);
    if (!supplier) return reply.code(404).send({ error: "unknown supplier" });
    const result = await importProduct(supplier, parsed.data.externalId);
    return result;
  });

  // 手動商品登録（仕入先API不要）
  const manualImportSchema = z.object({
    title: z.string().min(1),
    cost: z.number().positive(),
    costCurrency: z.enum(["CNY", "USD", "JPY"]).default("CNY"),
    stock: z.number().int().nonnegative().optional(),
    imageUrls: z.array(z.string()).default([]),
    sourceUrl: z.string().optional(),
    supplierName: z.string().optional(),
    description: z.string().optional(),
  });
  app.post("/products/manual", async (req, reply) => {
    if (!dbEnabled) return reply.code(503).send({ error: "DATABASE_URL 未設定" });
    const parsed = manualImportSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const result = await importManualProduct(parsed.data);
    return result;
  });

  // 管理商品一覧（DB）
  app.get("/products", async (req, reply) => {
    if (!dbEnabled) return reply.code(503).send({ error: "DATABASE_URL 未設定", items: [], total: 0 });
    const { skip, take } = req.query as { skip?: string; take?: string };
    return productRepo.listProducts({
      skip: skip ? Number(skip) : undefined,
      take: take ? Number(take) : undefined,
    });
  });

  // 管理商品の詳細
  app.get("/products/:id", async (req, reply) => {
    if (!dbEnabled) return reply.code(503).send({ error: "DATABASE_URL 未設定" });
    const { id } = req.params as { id: string };
    const product = await productRepo.getProduct(id);
    if (!product) return reply.code(404).send({ error: "product not found" });
    return product;
  });

  // 管理商品の売値更新
  app.patch("/products/:id", async (req, reply) => {
    if (!dbEnabled) return reply.code(503).send({ error: "DATABASE_URL 未設定" });
    const { id } = req.params as { id: string };
    const { sellPrice } = req.body as { sellPrice?: string };
    if (!sellPrice) return reply.code(400).send({ error: "sellPrice is required" });
    try {
      const { prisma } = await import("@hub/db");
      const updated = await prisma.product.update({
        where: { id },
        data: { sellPrice: parseFloat(sellPrice) },
        include: {
          sourceProduct: { include: { supplier: true } },
          listings: { include: { channel: true } },
        },
      });
      return updated;
    } catch {
      return reply.code(404).send({ error: "product not found" });
    }
  });

  // 管理商品の削除
  app.delete("/products/:id", async (req, reply) => {
    if (!dbEnabled) return reply.code(503).send({ error: "DATABASE_URL 未設定" });
    const { id } = req.params as { id: string };
    try {
      await productRepo.deleteProduct(id);
      return { ok: true };
    } catch {
      return reply.code(404).send({ error: "product not found" });
    }
  });

  // Amazon・楽天で市場調査 → 仕入れ値と突き合わせて利益率を算出
  const researchSchema = z.object({
    keyword: z.string().min(1),
    markets: z.array(z.enum(["amazon", "rakuten", "yahoo", "ebay"])).default(["amazon", "rakuten", "yahoo"]),
    limit: z.number().int().positive().max(50).optional(),
    // 任意: 仕入れ商品を指定すると利益・利益率・ROI まで計算
    supplierId: z.enum(["alibaba", "theckb", "aliexpress"]).optional(),
    externalId: z.string().optional(),
  });
  app.post("/research", async (req, reply) => {
    const parsed = researchSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const { keyword, markets: marketIds, limit, supplierId, externalId } = parsed.data;

    const selectedMarkets = marketIds.map(getMarket).filter((m): m is MarketResearchConnector => !!m);
    if (selectedMarkets.length === 0) return reply.code(404).send({ error: "no valid market" });

    let supplier: { connector: SupplierConnector; externalId: string } | undefined;
    if (supplierId && externalId) {
      const connector = getSupplier(supplierId);
      if (!connector) return reply.code(404).send({ error: "unknown supplier" });
      supplier = { connector, externalId };
    }

    const result = await researchMarket({ keyword, markets: selectedMarkets, limit, supplier });
    return result;
  });

  // 一括スクリーニング: 複数候補を調査・採点し、利益率/グレードで足切りしてランキング
  const screenSchema = z.object({
    candidates: z
      .array(
        z.object({
          supplierId: z.enum(["alibaba", "theckb", "aliexpress"]),
          externalId: z.string(),
          keyword: z.string().optional(),
        }),
      )
      .min(1),
    markets: z.array(z.enum(["amazon", "rakuten", "yahoo", "ebay"])).default(["amazon", "rakuten", "yahoo"]),
    minMarginRate: z.number().min(0).max(1).optional(),
    minGrade: z.enum(["A", "B", "C"]).optional(),
    limit: z.number().int().positive().max(50).optional(),
  });
  app.post("/research/screen", async (req, reply) => {
    const parsed = screenSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const { candidates, markets: marketIds, minMarginRate, minGrade, limit } = parsed.data;

    const selectedMarkets = marketIds.map(getMarket).filter((m): m is MarketResearchConnector => !!m);
    if (selectedMarkets.length === 0) return reply.code(404).send({ error: "no valid market" });

    const result = await screenCandidates({
      candidates,
      resolveSupplier: getSupplier,
      markets: selectedMarkets,
      options: { minMarginRate, minGrade, limit },
    });
    return {
      count: result.items.length,
      candidateCount: candidates.length,
      scoredCount: result.scoredCount,
      errors: result.errors,
      markets: marketIds,
      items: result.items,
    };
  });

  // 市場調査テスト（単一キーワードで楽天APIの疎通確認用）
  const testResearchSchema = z.object({
    keyword: z.string().default("猫 おもちゃ"),
    market: z.enum(["amazon", "rakuten", "yahoo", "ebay"]).default("rakuten"),
  });
  app.get("/research/test", async (req, reply) => {
    const parsed = testResearchSchema.safeParse(req.query);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const { keyword, market: marketId } = parsed.data;
    const market = getMarket(marketId);
    if (!market) return reply.code(404).send({ error: `unknown market: ${marketId}` });
    try {
      const listings = await market.searchListings({ keyword, limit: 5 });
      return { market: marketId, keyword, count: listings.length, listings };
    } catch (err) {
      return reply.code(500).send({
        error: "search failed",
        detail: err instanceof Error ? err.message : String(err),
      });
    }
  });

  // 在庫・価格同期を実行（欠品の自動非公開・価格更新・在庫更新・再公開）
  app.post("/sync/run", async () => runSync());

  // 定期同期の状態と前回結果
  app.get("/sync/status", async () => ({
    enabled: config.syncIntervalMinutes > 0,
    intervalMinutes: config.syncIntervalMinutes,
    schedulerRunning: getSchedulerInterval() > 0,
    lastRun: getLastRun(),
  }));

  // 受注一覧（損益付き）
  app.get("/orders", async () => ({ orders: await getOrders() }));

  // 損益サマリ
  app.get("/dashboard/pnl", async () => await getPnl());

  // 受注 → 仕入れ先への自動発注
  app.post("/orders/:id/fulfill", async (req) => {
    const { id } = req.params as { id: string };
    return fulfillOrder(id, getSupplier);
  });

  app.post("/orders/fulfill-all", async () => autoFulfillAll(getSupplier));

  // Webhook通知状態
  app.get("/notifications/status", async () => isWebhookConfigured());

  // 通知テスト送信
  app.post("/notifications/test", async () => sendTestNotification());

  // BASE へ出品
  const publishSchema = z.object({
    supplierId: z.string(),
    externalId: z.string(),
    channelId: z.string().default("base"),
  });
  app.post("/products/publish", async (req, reply) => {
    const parsed = publishSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });
    const supplier = getSupplier(parsed.data.supplierId);
    const channel = getChannel(parsed.data.channelId);
    if (!supplier) return reply.code(404).send({ error: "unknown supplier" });
    if (!channel) return reply.code(404).send({ error: "unknown channel" });
    const result = await importProduct(supplier, parsed.data.externalId);
    if (!result.publishable) {
      return reply.code(422).send({ error: "not publishable", issues: result.issues });
    }
    const listing = await publishToChannel(channel, result);
    return { listing, sellPrice: result.sellPrice, profit: result.profit };
  });

  // ===== BASE OAuth2 連携フロー =====

  app.get("/auth/base/authorize", async (req, reply) => {
    if (!config.baseOAuth) {
      return reply
        .code(500)
        .send({ error: "BASE_CLIENT_ID / BASE_CLIENT_SECRET が未設定です" });
    }
    const { redirectUri } = req.query as { redirectUri?: string };
    const uri = redirectUri || config.baseOAuth.redirectUri;
    const scope = "read_items write_items read_orders write_orders";
    const params = new URLSearchParams({
      response_type: "code",
      client_id: config.baseOAuth.clientId,
      redirect_uri: uri,
      scope,
    });
    return { url: `https://api.thebase.in/1/oauth/authorize?${params}` };
  });

  const exchangeSchema = z.object({
    code: z.string().min(1),
    redirectUri: z.string().url(),
  });
  app.post("/auth/base/exchange", async (req, reply) => {
    if (!config.baseOAuth) {
      return reply
        .code(500)
        .send({ error: "BASE_CLIENT_ID / BASE_CLIENT_SECRET が未設定です" });
    }
    const parsed = exchangeSchema.safeParse(req.body);
    if (!parsed.success) return reply.code(400).send({ error: parsed.error.flatten() });

    const body = new URLSearchParams({
      grant_type: "authorization_code",
      client_id: config.baseOAuth.clientId,
      client_secret: config.baseOAuth.clientSecret,
      code: parsed.data.code,
      redirect_uri: parsed.data.redirectUri,
    });

    const res = await fetch("https://api.thebase.in/1/oauth/token", {
      method: "POST",
      body,
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
    });
    const tokenData = (await res.json()) as Record<string, unknown>;
    if (!res.ok) {
      return reply.code(res.status).send({
        error: "token exchange failed",
        detail: String(tokenData.error_description || tokenData.error),
      });
    }

    const accessToken = String(tokenData.access_token);
    config.connector.credentials!.BASE_ACCESS_TOKEN = accessToken;
    if (tokenData.refresh_token) {
      config.connector.credentials!.BASE_REFRESH_TOKEN = String(tokenData.refresh_token);
    }
    config.connector.modes!.base = "live";
    channels.base = new BaseConnector(configFor(config.connector, "base"));

    app.log.info("BASE OAuth token obtained — connector switched to live");
    return {
      ok: true,
      mode: "live",
      expiresIn: tokenData.expires_in,
      scope: tokenData.scope,
    };
  });

  app.post("/auth/base/refresh", async (_req, reply) => {
    if (!config.baseOAuth) {
      return reply
        .code(500)
        .send({ error: "BASE_CLIENT_ID / BASE_CLIENT_SECRET が未設定です" });
    }
    const refreshToken = config.connector.credentials?.BASE_REFRESH_TOKEN;
    if (!refreshToken) {
      return reply.code(400).send({ error: "refresh token がありません" });
    }

    const body = new URLSearchParams({
      grant_type: "refresh_token",
      client_id: config.baseOAuth.clientId,
      client_secret: config.baseOAuth.clientSecret,
      refresh_token: refreshToken,
    });

    const res = await fetch("https://api.thebase.in/1/oauth/token", {
      method: "POST",
      body,
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
    });
    const tokenData = (await res.json()) as Record<string, unknown>;
    if (!res.ok) {
      return reply.code(res.status).send({
        error: "token refresh failed",
        detail: String(tokenData.error_description || tokenData.error),
      });
    }

    config.connector.credentials!.BASE_ACCESS_TOKEN = String(tokenData.access_token);
    if (tokenData.refresh_token) {
      config.connector.credentials!.BASE_REFRESH_TOKEN = String(tokenData.refresh_token);
    }
    config.connector.modes!.base = "live";
    channels.base = new BaseConnector(configFor(config.connector, "base"));

    return { ok: true, expiresIn: tokenData.expires_in };
  });

  return app;
}

// エントリポイント（Windows/Mac/Linux どこでも動く判定）
const isMain = process.argv[1]
  ? import.meta.url === pathToFileURL(process.argv[1]).href
  : false;
if (isMain) {
  const app = buildServer();
  const cfg = loadConfig();
  startSyncScheduler(cfg.syncIntervalMinutes, app.log);
  app.listen({ port: cfg.port, host: "0.0.0.0" }).catch((err) => {
    app.log.error(err);
    process.exit(1);
  });
}
