import Fastify from "fastify";
import { z } from "zod";
import {
  createChannelConnectors,
  createSupplierConnectors,
  type SalesChannelConnector,
  type SupplierConnector,
} from "@hub/connectors";
import type { ChannelId, SupplierId } from "@hub/core";
import { loadConfig } from "./config.js";
import { importProduct, publishToChannel } from "./services/listing-service.js";

export function buildServer() {
  const config = loadConfig();
  const app = Fastify({ logger: true });

  const suppliers = createSupplierConnectors(config.connector);
  const channels = createChannelConnectors(config.connector);

  const getSupplier = (id: string): SupplierConnector | undefined =>
    suppliers[id as SupplierId];
  const getChannel = (id: string): SalesChannelConnector | undefined =>
    channels[id as ChannelId];

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
