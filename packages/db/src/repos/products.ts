import { prisma } from "../index.js";
import type { Prisma } from "@prisma/client";

export interface ImportProductInput {
  supplierKind: string;
  externalId: string;
  title: string;
  description?: string;
  imageUrls: string[];
  costCNY: number;
  stock?: number;
  sourceUrl?: string;
  sellPrice: number;
  priceRuleId?: string;
}

export async function importProduct(input: ImportProductInput) {
  const supplier = await prisma.supplier.findUnique({
    where: { kind: input.supplierKind as any },
  });
  if (!supplier) throw new Error(`supplier "${input.supplierKind}" not found`);

  return prisma.$transaction(async (tx) => {
    const sourceProduct = await tx.sourceProduct.upsert({
      where: {
        supplierId_externalId: {
          supplierId: supplier.id,
          externalId: input.externalId,
        },
      },
      update: {
        title: input.title,
        description: input.description,
        imageUrls: input.imageUrls,
        cost: input.costCNY,
        stock: input.stock,
        sourceUrl: input.sourceUrl,
        fetchedAt: new Date(),
      },
      create: {
        supplierId: supplier.id,
        externalId: input.externalId,
        title: input.title,
        description: input.description,
        imageUrls: input.imageUrls,
        cost: input.costCNY,
        costCurrency: "CNY",
        stock: input.stock,
        sourceUrl: input.sourceUrl,
      },
    });

    const product = await tx.product.upsert({
      where: { sourceProductId: sourceProduct.id },
      update: {
        title: input.title,
        description: input.description,
        sellPrice: input.sellPrice,
        priceRuleId: input.priceRuleId ?? "default",
      },
      create: {
        sourceProductId: sourceProduct.id,
        title: input.title,
        description: input.description,
        sellPrice: input.sellPrice,
        priceRuleId: input.priceRuleId ?? "default",
      },
    });

    return { sourceProduct, product };
  });
}

export async function listProducts(opts?: { skip?: number; take?: number }) {
  const [items, total] = await Promise.all([
    prisma.product.findMany({
      skip: opts?.skip,
      take: opts?.take ?? 50,
      orderBy: { createdAt: "desc" },
      include: {
        sourceProduct: { include: { supplier: true } },
        listings: { include: { channel: true } },
        priceRule: true,
      },
    }),
    prisma.product.count(),
  ]);
  return { items, total };
}

export async function getProduct(id: string) {
  return prisma.product.findUnique({
    where: { id },
    include: {
      sourceProduct: { include: { supplier: true } },
      listings: { include: { channel: true } },
      priceRule: true,
    },
  });
}

export async function deleteProduct(id: string) {
  return prisma.$transaction(async (tx) => {
    await tx.listing.deleteMany({ where: { productId: id } });
    const product = await tx.product.delete({ where: { id } });
    await tx.sourceProduct.delete({ where: { id: product.sourceProductId } });
    return product;
  });
}
