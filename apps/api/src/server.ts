import Fastify from "fastify";
import { z } from "zod";
import {
  connectorModes,
  createChannelConnectors,
  createMarketConnectors,
  createSupplierConnectors,
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
import { importProduct, publishToChannel } from "./services/listing-service.js";
import { getOrders, getPnl } from "./services/orders-service.js";
import { researchMarket } from "./services/research-service.js";
import { screenCandidates } from "./services/screening-service.js";
import { getLastRun, runSync } from "./services/sync-service.js";
import { getSchedulerInterval, startSyncScheduler } from "./scheduler.js";

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
    endpoints: ["/health", "/connectors", "/niche/cat-goods", "/research", "/research/screen", "/orders", "/dashboard/pnl", "/sync/run", "/sync/status"],
  }));

  // 各コネクタの実効モード（mock | live）。どのデータ源が本番接続かを確認する。
  app.get("/connectors", async () => ({
    defaultMode: config.connector.mode,
    modes: connectorModes(config.connector),
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

    const ranked = await screenCandidates({
      candidates,
      resolveSupplier: getSupplier,
      markets: selectedMarkets,
      options: { minMarginRate, minGrade, limit },
    });
    return { count: ranked.length, items: ranked };
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
  app.get("/orders", async () => ({ orders: getOrders() }));

  // 損益サマリ
  app.get("/dashboard/pnl", async () => getPnl());

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

  return app;
}

// エントリポイント
const isMain = process.argv[1] && import.meta.url.endsWith(process.argv[1].split("/").pop() ?? "");
if (isMain) {
  const app = buildServer();
  const cfg = loadConfig();
  startSyncScheduler(cfg.syncIntervalMinutes, app.log);
  app.listen({ port: cfg.port, host: "0.0.0.0" }).catch((err) => {
    app.log.error(err);
    process.exit(1);
  });
}
