import { dbEnabled, syncLogRepo } from "@hub/db";

export interface Alert {
  id: string;
  type: "hot_product" | "out_of_stock" | "fx_update";
  title: string;
  message: string;
  severity: "info" | "warning" | "critical";
  createdAt: string;
  read: boolean;
}

let alerts: Alert[] = [];
let idCounter = 0;

export function pushAlert(alert: Omit<Alert, "id" | "createdAt" | "read">) {
  const entry: Alert = {
    ...alert,
    id: `alert_${++idCounter}`,
    createdAt: new Date().toISOString(),
    read: false,
  };
  alerts.unshift(entry);
  if (alerts.length > 100) alerts = alerts.slice(0, 100);

  if (dbEnabled) {
    syncLogRepo.writeSyncLog({
      kind: `alert:${alert.type}`,
      action: alert.title,
      success: true,
      message: alert.message,
    }).catch(() => {});
  }

  return entry;
}

export function getAlerts(opts?: { unreadOnly?: boolean; limit?: number }): Alert[] {
  let result = alerts;
  if (opts?.unreadOnly) result = result.filter((a) => !a.read);
  return result.slice(0, opts?.limit ?? 50);
}

export function markAlertRead(id: string): boolean {
  const alert = alerts.find((a) => a.id === id);
  if (alert) {
    alert.read = true;
    return true;
  }
  return false;
}

export function getUnreadCount(): number {
  return alerts.filter((a) => !a.read).length;
}
