-- CreateEnum
CREATE TYPE "SupplierKind" AS ENUM ('alibaba', 'theckb');

-- CreateEnum
CREATE TYPE "ChannelKind" AS ENUM ('base');

-- CreateEnum
CREATE TYPE "ListingStatus" AS ENUM ('draft', 'published', 'unpublished', 'error');

-- CreateEnum
CREATE TYPE "OrderStatus" AS ENUM ('received', 'fulfilling', 'ordered_to_supplier', 'shipped', 'completed', 'cancelled');

-- CreateTable
CREATE TABLE "Supplier" (
    "id" TEXT NOT NULL,
    "kind" "SupplierKind" NOT NULL,
    "name" TEXT NOT NULL,
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Supplier_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SalesChannel" (
    "id" TEXT NOT NULL,
    "kind" "ChannelKind" NOT NULL,
    "name" TEXT NOT NULL,
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "SalesChannel_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SourceProduct" (
    "id" TEXT NOT NULL,
    "supplierId" TEXT NOT NULL,
    "externalId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "imageUrls" TEXT[],
    "cost" DECIMAL(12,2) NOT NULL,
    "costCurrency" TEXT NOT NULL DEFAULT 'CNY',
    "stock" INTEGER,
    "sourceUrl" TEXT,
    "raw" JSONB,
    "fetchedAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SourceProduct_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Product" (
    "id" TEXT NOT NULL,
    "sourceProductId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "sellPrice" DECIMAL(12,2) NOT NULL,
    "priceRuleId" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Product_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Listing" (
    "id" TEXT NOT NULL,
    "productId" TEXT NOT NULL,
    "channelId" TEXT NOT NULL,
    "externalListingId" TEXT,
    "status" "ListingStatus" NOT NULL DEFAULT 'draft',
    "publishedPrice" DECIMAL(12,2),
    "publishedStock" INTEGER,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Listing_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "PriceRule" (
    "id" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "fxRateToJpy" DECIMAL(12,4) NOT NULL,
    "dutyRate" DECIMAL(6,4) NOT NULL,
    "intlShippingPerUnit" DECIMAL(12,2) NOT NULL,
    "domesticShipping" DECIMAL(12,2) NOT NULL,
    "platformFeeRate" DECIMAL(6,4) NOT NULL,
    "marginType" TEXT NOT NULL,
    "marginValue" DECIMAL(12,4) NOT NULL,
    "roundTo" INTEGER NOT NULL DEFAULT 10,
    "minProfit" DECIMAL(12,2) NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "PriceRule_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Order" (
    "id" TEXT NOT NULL,
    "channelId" TEXT NOT NULL,
    "externalOrderId" TEXT NOT NULL,
    "status" "OrderStatus" NOT NULL DEFAULT 'received',
    "buyerName" TEXT NOT NULL,
    "buyerPostalCode" TEXT,
    "buyerAddress" TEXT,
    "buyerPhone" TEXT,
    "total" DECIMAL(12,2) NOT NULL,
    "orderedAt" TIMESTAMP(3) NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Order_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "OrderItem" (
    "id" TEXT NOT NULL,
    "orderId" TEXT NOT NULL,
    "externalItemId" TEXT NOT NULL,
    "sku" TEXT NOT NULL,
    "quantity" INTEGER NOT NULL,
    "unitPrice" DECIMAL(12,2) NOT NULL,

    CONSTRAINT "OrderItem_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SupplierOrder" (
    "id" TEXT NOT NULL,
    "orderId" TEXT NOT NULL,
    "supplierKind" TEXT NOT NULL,
    "externalProductId" TEXT NOT NULL,
    "externalSkuId" TEXT,
    "quantity" INTEGER NOT NULL,
    "cost" DECIMAL(12,2),
    "supplierOrderId" TEXT,
    "trackingNumber" TEXT,
    "carrier" TEXT,
    "accepted" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SupplierOrder_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SyncLog" (
    "id" TEXT NOT NULL,
    "kind" TEXT NOT NULL,
    "target" TEXT,
    "action" TEXT,
    "success" BOOLEAN NOT NULL DEFAULT true,
    "message" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SyncLog_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "Supplier_kind_key" ON "Supplier"("kind");

-- CreateIndex
CREATE UNIQUE INDEX "SalesChannel_kind_key" ON "SalesChannel"("kind");

-- CreateIndex
CREATE UNIQUE INDEX "SourceProduct_supplierId_externalId_key" ON "SourceProduct"("supplierId", "externalId");

-- CreateIndex
CREATE UNIQUE INDEX "Product_sourceProductId_key" ON "Product"("sourceProductId");

-- CreateIndex
CREATE UNIQUE INDEX "Listing_productId_channelId_key" ON "Listing"("productId", "channelId");

-- CreateIndex
CREATE UNIQUE INDEX "Order_channelId_externalOrderId_key" ON "Order"("channelId", "externalOrderId");

-- AddForeignKey
ALTER TABLE "SourceProduct" ADD CONSTRAINT "SourceProduct_supplierId_fkey" FOREIGN KEY ("supplierId") REFERENCES "Supplier"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Product" ADD CONSTRAINT "Product_sourceProductId_fkey" FOREIGN KEY ("sourceProductId") REFERENCES "SourceProduct"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Product" ADD CONSTRAINT "Product_priceRuleId_fkey" FOREIGN KEY ("priceRuleId") REFERENCES "PriceRule"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Listing" ADD CONSTRAINT "Listing_productId_fkey" FOREIGN KEY ("productId") REFERENCES "Product"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Listing" ADD CONSTRAINT "Listing_channelId_fkey" FOREIGN KEY ("channelId") REFERENCES "SalesChannel"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Order" ADD CONSTRAINT "Order_channelId_fkey" FOREIGN KEY ("channelId") REFERENCES "SalesChannel"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OrderItem" ADD CONSTRAINT "OrderItem_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES "Order"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "SupplierOrder" ADD CONSTRAINT "SupplierOrder_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES "Order"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
