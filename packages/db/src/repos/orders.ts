import { prisma } from "../index.js";

export interface CreateOrderInput {
  channelKind: string;
  externalOrderId: string;
  buyerName: string;
  buyerPostalCode?: string;
  buyerAddress?: string;
  buyerPhone?: string;
  total: number;
  orderedAt: Date;
  items: {
    externalItemId: string;
    sku: string;
    quantity: number;
    unitPrice: number;
  }[];
}

export async function createOrder(input: CreateOrderInput) {
  const channel = await prisma.salesChannel.findUnique({
    where: { kind: input.channelKind as "base" },
  });
  if (!channel) throw new Error(`channel "${input.channelKind}" not found`);

  return prisma.order.create({
    data: {
      channelId: channel.id,
      externalOrderId: input.externalOrderId,
      buyerName: input.buyerName,
      buyerPostalCode: input.buyerPostalCode,
      buyerAddress: input.buyerAddress,
      buyerPhone: input.buyerPhone,
      total: input.total,
      orderedAt: input.orderedAt,
      items: { create: input.items },
    },
    include: { items: true, channel: true },
  });
}

export async function listOrders(opts?: { skip?: number; take?: number }) {
  const [items, total] = await Promise.all([
    prisma.order.findMany({
      skip: opts?.skip,
      take: opts?.take ?? 50,
      orderBy: { orderedAt: "desc" },
      include: { items: true, channel: true, supplierOrders: true },
    }),
    prisma.order.count(),
  ]);
  return { items, total };
}

export async function updateOrderStatus(id: string, status: string) {
  return prisma.order.update({
    where: { id },
    data: { status: status as "received" | "fulfilling" | "ordered_to_supplier" | "shipped" | "completed" | "cancelled" },
  });
}
