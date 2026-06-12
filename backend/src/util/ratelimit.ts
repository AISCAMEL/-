// 依存追加なしの軽量レート制限（IP単位・固定ウィンドウ）。
// 公開エンドポイント（問い合わせフォーム等）のスパム/連投対策。
// 単一プロセス前提のインメモリ。将来スケール時は Redis 等へ。

interface Bucket { count: number; resetAt: number; }
const buckets = new Map<string, Bucket>();

// 定期的に期限切れエントリを掃除（メモリリーク防止）。
setInterval(() => {
  const now = Date.now();
  for (const [k, b] of buckets) if (b.resetAt <= now) buckets.delete(k);
}, 60_000).unref?.();

/**
 * key（通常はIP）に対し windowMs 内 max 回まで許可。
 * 超過時 allowed=false と retryAfter(秒) を返す。
 */
export function rateLimit(key: string, max: number, windowMs: number): { allowed: boolean; retryAfter: number } {
  const now = Date.now();
  const b = buckets.get(key);
  if (!b || b.resetAt <= now) {
    buckets.set(key, { count: 1, resetAt: now + windowMs });
    return { allowed: true, retryAfter: 0 };
  }
  if (b.count >= max) {
    return { allowed: false, retryAfter: Math.ceil((b.resetAt - now) / 1000) };
  }
  b.count += 1;
  return { allowed: true, retryAfter: 0 };
}
