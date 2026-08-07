-- AlterEnum: add 'aliexpress' and 'manual' to SupplierKind
ALTER TYPE "SupplierKind" ADD VALUE IF NOT EXISTS 'aliexpress';
ALTER TYPE "SupplierKind" ADD VALUE IF NOT EXISTS 'manual';
