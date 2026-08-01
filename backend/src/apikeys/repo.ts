// 外部連携用APIキーのデータアクセス。平文キーは保存せず sha256 で保存する。
import { createHash, randomBytes } from 'node:crypto';
import { dbEnabled, query } from '../db/index.js';
import { demoApiKeys, demoTenant, newId, type DemoApiKey } from '../demo/fixtures.js';

function sha256(s: string): string {
  return createHash('sha256').update(s).digest('hex');
}

/** 新しいAPIキー文字列を生成（表示は発行時の一度きり）。 */
function newKey(): { key: string; prefix: string; hash: string } {
  const raw = randomBytes(24).toString('base64url'); // URLセーフ
  const key = `aiop_live_${raw}`;
  return { key, prefix: key.slice(0, 14), hash: sha256(key) };
}

/** キーを masked 形式で一覧（平文は返さない）。 */
export async function listKeys(tenantId: string) {
  if (!dbEnabled) {
    return demoApiKeys
      .filter((k) => k.tenant_id === tenantId || tenantId === demoTenant.id)
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
      .map(mask);
  }
  const rows = await query<any>(`select * from api_keys where tenant_id=$1 order by created_at desc`, [tenantId]);
  return rows.map(mask);
}

function mask(k: any) {
  return {
    id: k.id, name: k.name, key_prefix: k.key_prefix, scopes: k.scopes ?? [],
    last_used_at: k.last_used_at, created_at: k.created_at, revoked: Boolean(k.revoked_at),
  };
}

/** キーを発行。戻り値の key は発行時のみ（以後は取得不可）。 */
export async function createKey(tenantId: string, name: string, scopes: string[] = []) {
  const { key, prefix, hash } = newKey();
  if (!dbEnabled) {
    const row: DemoApiKey = {
      id: newId('ak'), tenant_id: tenantId, name, key_prefix: prefix, key_hash: hash,
      scopes, last_used_at: null, created_at: new Date().toISOString(), revoked_at: null,
    };
    demoApiKeys.push(row);
    return { key, record: mask(row) };
  }
  const [row] = await query<any>(
    `insert into api_keys (tenant_id, name, key_prefix, key_hash, scopes) values ($1,$2,$3,$4,$5) returning *`,
    [tenantId, name, prefix, hash, scopes]);
  return { key, record: mask(row) };
}

/** キーを失効。 */
export async function revokeKey(tenantId: string, id: string): Promise<boolean> {
  if (!dbEnabled) {
    const k = demoApiKeys.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!k || k.revoked_at) return false;
    k.revoked_at = new Date().toISOString();
    return true;
  }
  const rows = await query(`update api_keys set revoked_at=now() where id=$1 and tenant_id=$2 and revoked_at is null returning id`, [id, tenantId]);
  return rows.length > 0;
}

/** 平文キーから有効なテナントIDを解決（失効・不明は null）。最終利用時刻を更新。 */
export async function resolveTenantByKey(key: string): Promise<string | null> {
  if (!key || !key.startsWith('aiop_')) return null;
  const hash = sha256(key);
  if (!dbEnabled) {
    const k = demoApiKeys.find((x) => x.key_hash === hash && !x.revoked_at);
    if (!k) return null;
    k.last_used_at = new Date().toISOString();
    return k.tenant_id;
  }
  const [row] = await query<any>(`select tenant_id from api_keys where key_hash=$1 and revoked_at is null`, [hash]);
  if (!row) return null;
  await query(`update api_keys set last_used_at=now() where key_hash=$1`, [hash]).catch(() => {});
  return row.tenant_id;
}
