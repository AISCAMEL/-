import { prisma } from "../index.js";

export async function writeSyncLog(entry: {
  kind: string;
  target?: string;
  action?: string;
  success: boolean;
  message?: string;
}) {
  return prisma.syncLog.create({ data: entry });
}

export async function recentSyncLogs(limit = 20) {
  return prisma.syncLog.findMany({
    orderBy: { createdAt: "desc" },
    take: limit,
  });
}
