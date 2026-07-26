import { prisma } from "../index.js";

export interface CreateListingInput {
  productId: string;
  channelKind: string;
  externalListingId?: string;
  publishedPrice: number;
  publishedStock: number;
}

export async function upsertListing(input: CreateListingInput) {
  const channel = await prisma.salesChannel.findUnique({
    where: { kind: input.channelKind as "base" },
  });
  if (!channel) throw new Error(`channel "${input.channelKind}" not found`);

  return prisma.listing.upsert({
    where: {
      productId_channelId: {
        productId: input.productId,
        channelId: channel.id,
      },
    },
    update: {
      externalListingId: input.externalListingId,
      status: "published",
      publishedPrice: input.publishedPrice,
      publishedStock: input.publishedStock,
    },
    create: {
      productId: input.productId,
      channelId: channel.id,
      externalListingId: input.externalListingId,
      status: "published",
      publishedPrice: input.publishedPrice,
      publishedStock: input.publishedStock,
    },
    include: { product: true, channel: true },
  });
}

export async function updateListingStatus(
  id: string,
  status: "draft" | "published" | "unpublished" | "error",
) {
  return prisma.listing.update({ where: { id }, data: { status } });
}

export async function updateListingStockPrice(
  id: string,
  data: { publishedStock?: number; publishedPrice?: number; status?: string },
) {
  return prisma.listing.update({
    where: { id },
    data: {
      publishedStock: data.publishedStock,
      publishedPrice: data.publishedPrice,
      status: data.status as "published" | "unpublished" | undefined,
    },
  });
}

export async function listListingsForSync() {
  return prisma.listing.findMany({
    where: { status: { in: ["published", "unpublished"] } },
    include: {
      product: {
        include: {
          sourceProduct: { include: { supplier: true } },
          priceRule: true,
        },
      },
      channel: true,
    },
  });
}
