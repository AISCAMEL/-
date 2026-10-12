import { config } from '../config.js';
import { getInvoice, getTenantSquareCustomerId, setTenantSquareCustomerId } from '../db/queries.js';

// Square 連携。アクセストークン未設定時は「デモ（シミュレート）」で動作する。
export const squareEnabled = Boolean(config.square.accessToken);

function apiBase(): string {
  return config.square.env === 'production'
    ? 'https://connect.squareup.com'
    : 'https://connect.squareupsandbox.com';
}

async function squareFetch(path: string, init: RequestInit = {}): Promise<any> {
  const res = await fetch(`${apiBase()}${path}`, {
    ...init,
    headers: {
      'Square-Version': '2024-12-18',
      Authorization: `Bearer ${config.square.accessToken}`,
      'Content-Type': 'application/json',
      ...(init.headers as Record<string, string>),
    },
  });
  const data = await res.json().catch(() => ({})) as any;
  if (!res.ok) throw new Error(`Square ${res.status}: ${JSON.stringify(data.errors ?? data)}`);
  return data;
}

export interface BillingStatus {
  enabled: boolean;            // Square接続済みか
  plan: { key: string; label: string; base_jpy: number; allowance_min: number; overage_jpy_per_min: number };
  usage: { billable_minutes: number; overage_minutes: number };
  this_month_total_jpy: number; // 当月の見込み請求（税込）
  square_customer_id: string | null;
  square_subscription_id: string | null;
}

/** テナントの請求状況（当月）。請求書データ(getInvoice)を流用。 */
export async function getBillingStatus(tenantId: string, square: { customer_id?: string | null; subscription_id?: string | null } = {}): Promise<BillingStatus> {
  const inv = await getInvoice(tenantId);
  return {
    enabled: squareEnabled,
    plan: inv.plan,
    usage: { billable_minutes: inv.usage.billable_minutes, overage_minutes: inv.usage.overage_minutes },
    this_month_total_jpy: inv.total,
    square_customer_id: square.customer_id ?? null,
    square_subscription_id: square.subscription_id ?? null,
  };
}

/**
 * 当月の超過分について Square 請求書(Invoice)を作成する。
 * Square未接続時はシミュレート結果を返す（デモ）。
 */
export async function createOverageInvoice(tenantId: string, month?: string): Promise<{ ok: boolean; simulated: boolean; amount_jpy: number; invoice_id?: string; error?: string; message: string }> {
  const inv = await getInvoice(tenantId, month);
  const overageMin = inv.usage.overage_minutes;
  const amount = overageMin * inv.plan.overage_jpy_per_min;

  if (overageMin <= 0) {
    return { ok: true, simulated: !squareEnabled, amount_jpy: 0, message: '当月の超過はありません。請求書は作成しませんでした。' };
  }

  if (!squareEnabled) {
    return { ok: true, simulated: true, amount_jpy: amount, message: `（デモ）超過 ${overageMin}分 × ¥${inv.plan.overage_jpy_per_min} = ¥${amount} の請求書を作成する想定です。Square接続で実発行されます。` };
  }

  try {
    // Square Orders → Invoices の最小フロー（JPY・整数額）。
    const idem = `ov-${tenantId}-${inv.month}`;
    const order = await squareFetch('/v2/orders', {
      method: 'POST',
      body: JSON.stringify({
        idempotency_key: `${idem}-order`,
        order: {
          location_id: config.square.locationId,
          line_items: [{
            name: `超過通話料 ${inv.month}（${overageMin}分）`,
            quantity: '1',
            base_price_money: { amount, currency: 'JPY' },
          }],
        },
      }),
    });
    const invoice = await squareFetch('/v2/invoices', {
      method: 'POST',
      body: JSON.stringify({
        idempotency_key: `${idem}-invoice`,
        invoice: {
          location_id: config.square.locationId,
          order_id: order.order.id,
          delivery_method: 'EMAIL',
          accepted_payment_methods: { card: true },
          primary_recipient: { customer_id: undefined },
          title: 'AIオペレーター24 超過通話料',
        },
      }),
    });
    return { ok: true, simulated: false, amount_jpy: amount, invoice_id: invoice.invoice?.id, message: `Square請求書を作成しました（¥${amount}）。` };
  } catch (err) {
    return { ok: false, simulated: false, amount_jpy: amount, error: String(err), message: 'Square請求書の作成に失敗しました。' };
  }
}

/** テナントのSquare顧客を取得。無ければ billing_email で作成して保存する。失敗時 null。 */
async function ensureSquareCustomer(
  tenantId: string,
  billing: { company_name?: string; billing_email?: string | null },
): Promise<string | null> {
  const existing = await getTenantSquareCustomerId(tenantId);
  if (existing) return existing;
  if (!billing.billing_email) return null;
  const data = await squareFetch('/v2/customers', {
    method: 'POST',
    body: JSON.stringify({
      idempotency_key: `cust-${tenantId}`,
      given_name: billing.company_name ?? 'ご担当者',
      company_name: billing.company_name,
      email_address: billing.billing_email,
    }),
  });
  const id = data.customer?.id ?? null;
  if (id) await setTenantSquareCustomerId(tenantId, id);
  return id;
}

// 請求書の支払期限（発行から14日後、YYYY-MM-DD）。
function dueDateIso(): string {
  return new Date(Date.now() + 14 * 86400_000).toISOString().slice(0, 10);
}

/**
 * 当月の「フル請求書」（基本料＋着信＋従量＋消費税）をSquareで発行し、
 * お客様にカード決済リンク付きメールを送る。未接続時はシミュレート。
 */
export async function createMonthlyInvoice(
  tenantId: string,
  month?: string,
): Promise<{ ok: boolean; simulated: boolean; amount_jpy: number; invoice_id?: string; public_url?: string | null; error?: string; message: string }> {
  const inv = await getInvoice(tenantId, month);
  if (inv.total <= 0) {
    return { ok: true, simulated: !squareEnabled, amount_jpy: 0, message: '当月の請求額は0円のため、請求書は発行しませんでした。' };
  }
  if (!squareEnabled) {
    return { ok: true, simulated: true, amount_jpy: inv.total, message: `（デモ）${inv.month} 分 ¥${inv.total}（税込）の請求書を発行する想定です。Square接続で実発行されます。` };
  }
  try {
    const customerId = await ensureSquareCustomer(tenantId, inv.tenant as any);
    if (!customerId) {
      return { ok: false, simulated: false, amount_jpy: inv.total, error: 'no billing email', message: '請求先メール（billing_email）が未設定のため発行できません。店舗情報に請求先メールを設定してください。' };
    }
    const idem = `inv-${tenantId}-${inv.month}`;
    const lineItems = inv.lines.map((l) => ({
      name: l.desc, quantity: '1', base_price_money: { amount: l.amount, currency: 'JPY' },
    }));
    if (inv.tax > 0) {
      lineItems.push({ name: `消費税(${Math.round(inv.tax_rate * 100)}%)`, quantity: '1', base_price_money: { amount: inv.tax, currency: 'JPY' } });
    }
    const order = await squareFetch('/v2/orders', {
      method: 'POST',
      body: JSON.stringify({
        idempotency_key: `${idem}-order`,
        order: { location_id: config.square.locationId, customer_id: customerId, line_items: lineItems },
      }),
    });
    const created = await squareFetch('/v2/invoices', {
      method: 'POST',
      body: JSON.stringify({
        idempotency_key: `${idem}-invoice`,
        invoice: {
          location_id: config.square.locationId,
          order_id: order.order.id,
          primary_recipient: { customer_id: customerId },
          delivery_method: 'EMAIL',
          accepted_payment_methods: { card: true },
          title: `AIオペレーター24 ${inv.month} ご利用料`,
          payment_requests: [{ request_type: 'BALANCE', due_date: dueDateIso() }],
        },
      }),
    });
    // 発行（publish）するとお客様へメールが送られ、決済リンクが有効になる。
    const published = await squareFetch(`/v2/invoices/${created.invoice.id}/publish`, {
      method: 'POST',
      body: JSON.stringify({ idempotency_key: `${idem}-publish`, version: created.invoice.version }),
    });
    return {
      ok: true, simulated: false, amount_jpy: inv.total,
      invoice_id: created.invoice.id,
      public_url: published.invoice?.public_url ?? null,
      message: `Square請求書を発行しました（¥${inv.total} 税込）。お客様にカード決済リンク付きメールが届きます。`,
    };
  } catch (err) {
    return { ok: false, simulated: false, amount_jpy: inv.total, error: String(err), message: 'Square請求書の発行に失敗しました。' };
  }
}
