import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

async function main() {
  const theckb = await prisma.supplier.upsert({
    where: { kind: "theckb" },
    update: {},
    create: { kind: "theckb", name: "THE CKB" },
  });

  const alibaba = await prisma.supplier.upsert({
    where: { kind: "alibaba" },
    update: {},
    create: { kind: "alibaba", name: "Alibaba.com" },
  });

  const base = await prisma.salesChannel.upsert({
    where: { kind: "base" },
    update: {},
    create: { kind: "base", name: "BASE" },
  });

  await prisma.priceRule.upsert({
    where: { id: "default" },
    update: {},
    create: {
      id: "default",
      name: "猫グッズ標準",
      fxRateToJpy: 21,
      dutyRate: 0.1,
      intlShippingPerUnit: 300,
      domesticShipping: 500,
      platformFeeRate: 0.036,
      marginType: "rate",
      marginValue: 0.3,
      roundTo: 10,
      minProfit: 300,
    },
  });

  console.log("Seeded:", { theckb: theckb.id, alibaba: alibaba.id, base: base.id });
}

main()
  .then(() => prisma.$disconnect())
  .catch((e) => {
    console.error(e);
    prisma.$disconnect();
    process.exit(1);
  });
