-- Seed manual supplier
INSERT INTO "Supplier" ("id", "kind", "name", "enabled", "createdAt", "updatedAt")
VALUES ('supplier_manual', 'manual', '手動登録', true, NOW(), NOW())
ON CONFLICT ("kind") DO NOTHING;

-- Seed aliexpress supplier
INSERT INTO "Supplier" ("id", "kind", "name", "enabled", "createdAt", "updatedAt")
VALUES ('supplier_aliexpress', 'aliexpress', 'AliExpress', true, NOW(), NOW())
ON CONFLICT ("kind") DO NOTHING;
