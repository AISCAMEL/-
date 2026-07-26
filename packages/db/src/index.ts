import { PrismaClient } from "@prisma/client";

const globalForPrisma = globalThis as unknown as { prisma?: PrismaClient };

export const prisma = globalForPrisma.prisma ?? new PrismaClient();

if (process.env.NODE_ENV !== "production") {
  globalForPrisma.prisma = prisma;
}

export type { PrismaClient } from "@prisma/client";
export { Prisma } from "@prisma/client";

export * as productRepo from "./repos/products.js";
export * as listingRepo from "./repos/listings.js";
export * as orderRepo from "./repos/orders.js";
export * as priceRuleRepo from "./repos/price-rules.js";
export * as supplierRepo from "./repos/suppliers.js";
export * as syncLogRepo from "./repos/sync-logs.js";
