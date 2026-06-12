import Fastify from "fastify";
import { z } from "zod";
import {
  createChannelConnectors,
  createMarketConnectors,
  createSupplierConnectors,
  type MarketResearchConnector,
  type SalesChannelConnector,
  type SupplierConnector,
} from "@hub/connectors";
import type { ChannelId, MarketId, SupplierId } from "@hub/core";
import { loadConfig } from "./config.js";
import { importProduct, publishToChannel } from "./services/listing-service.js";
import { researchMarket } from "./services/research-service.js";

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
    markets: z.array(z.enum(["amazon", "rakuten"])).default(["amazon", "rakuten"]),
    limit: z.number().int().positive().max(50).optional(),
    // 任意: 仕入れ商品を指定すると利益・利益率・ROI まで計算
    supplierId: z.enum(["alibaba", "theckb"]).optional(),
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
  const port = loadConfig().port;
  app.listen({ port, host: "0.0.0.0" }).catch((err) => {
    app.log.error(err);
    process.exit(1);
  });
}
