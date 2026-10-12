// 店舗オンボーディング用の招待トークン。ログイン鍵(HS256)で署名し、
// パスワード設定リンクに載せる。SETUP_TOKENと違い、宛先メール単位で有効。
import jwt from 'jsonwebtoken';
import { config } from '../config.js';

export interface InviteClaims { purpose: 'invite'; email: string; tenant_id: string | null; }

export function signInviteToken(email: string, tenantId: string | null): string {
  return jwt.sign(
    { purpose: 'invite', email, tenant_id: tenantId },
    config.auth.jwtSecret,
    { expiresIn: config.auth.inviteTtl as any },
  );
}

export function verifyInviteToken(token: string): InviteClaims | null {
  try {
    const c = jwt.verify(token, config.auth.jwtSecret) as any;
    if (c.purpose !== 'invite' || !c.email) return null;
    return { purpose: 'invite', email: String(c.email), tenant_id: c.tenant_id ?? null };
  } catch {
    return null;
  }
}
