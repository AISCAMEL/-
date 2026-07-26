import { prisma } from "../index.js";

export async function getSupplierByKind(kind: string) {
  return prisma.supplier.findUnique({ where: { kind: kind as "alibaba" | "theckb" } });
}

export async function listSuppliers() {
  return prisma.supplier.findMany({ where: { enabled: true } });
}

export async function getSalesChannelByKind(kind: string) {
  return prisma.salesChannel.findUnique({ where: { kind: kind as "base" } });
}

export async function listSalesChannels() {
  return prisma.salesChannel.findMany({ where: { enabled: true } });
}
