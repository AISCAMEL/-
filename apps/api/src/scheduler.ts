import { runSync } from "./services/sync-service.js";
import { updatePriceRuleFxRate } from "./services/fx-service.js";

let timer: NodeJS.Timeout | null = null;
let intervalMinutes = 0;

interface Logger {
  info: (msg: string) => void;
  error: (msg: string) => void;
}

/**
 * 在庫・価格同期の定期実行を開始する。
 * minutes <= 0 で無効。起動時に1回実行し、その後 interval ごとに実行する。
 */
export function startSyncScheduler(minutes: number, log?: Logger): void {
  intervalMinutes = minutes;
  if (timer) clearInterval(timer);
  if (minutes <= 0) {
    log?.info("auto-sync: 無効（SYNC_INTERVAL_MINUTES 未設定）");
    return;
  }
  const tick = async () => {
    try {
      const fx = await updatePriceRuleFxRate();
      if (fx.updated) log?.info(`為替レート更新: CNY→JPY ${fx.rate}`);
      const r = await runSync();
      log?.info(`auto-sync 実行: ${JSON.stringify(r.summary)}`);
    } catch (e) {
      log?.error(`auto-sync 失敗: ${String(e)}`);
    }
  };
  void tick();
  timer = setInterval(() => void tick(), minutes * 60_000);
  timer.unref?.(); // プロセス終了を妨げない
  log?.info(`auto-sync: 有効（${minutes}分ごと）`);
}

export function getSchedulerInterval(): number {
  return intervalMinutes;
}

export function stopSyncScheduler(): void {
  if (timer) clearInterval(timer);
  timer = null;
  intervalMinutes = 0;
}
