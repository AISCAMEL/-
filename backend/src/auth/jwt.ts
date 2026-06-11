import jwt from 'jsonwebtoken';
import type { FastifyReply, FastifyRequest } from 'fastify';
import { config } from '../config.js';

// 認証済みリクエストに付与する主体情報。
export interface AuthPrincipal {
  authUserId: string | null;
  tenantId: string | null;     // super_admin は null（全テナント対象）
  role: 'owner' | 'admin' | 'staff' | 'super_admin';
  email: string | null;
}

declare module 'fastify' {
  interface FastifyRequest {
    principal?: AuthPrincipal;
  }
}

/**
 * Authorization: Bearer <token> を検証して principal を解決する。
 * - SUPABASE_JWT_SECRET 設定時: HS256 で署名検証し、クレームから tenant_id/role を取り出す。
 * - devMode: 署名検証せず、ヘッダ x-tenant-id / x-role でテナントを指定（無指定はデモテナント）。
 */
export function authenticate(req: FastifyRequest, reply: FastifyReply, done: (err?: Error) => void): void {
  const principal = resolvePrincipal(req);
  if (!principal) {
    reply.code(401).send({ error: 'unauthorized' });
    return;
  }
  req.principal = principal;
  done();
}

/** super_admin 限定エンドポイント用。 */
export function requireSuperAdmin(req: FastifyRequest, reply: FastifyReply, done: (err?: Error) => void): void {
  authenticate(req, reply, (err) => {
    if (err) return done(err);
    if (req.principal?.role !== 'super_admin') {
      reply.code(403).send({ error: 'forbidden' });
      return;
    }
    done();
  });
}

function resolvePrincipal(req: FastifyRequest): AuthPrincipal | null {
  const header = req.headers.authorization;
  const token = header?.startsWith('Bearer ') ? header.slice(7) : null;

  if (config.auth.jwtSecret && token) {
    try {
      const claims = jwt.verify(token, config.auth.jwtSecret) as Record<string, any>;
      const meta = claims.app_metadata ?? {};
      return {
        authUserId: claims.sub ?? null,
        tenantId: claims.tenant_id ?? meta.tenant_id ?? null,
        role: claims.role ?? meta.role ?? 'staff',
        email: claims.email ?? null,
      };
    } catch {
      return null;
    }
  }

  // devMode: 署名なしで通す（本番では SUPABASE_JWT_SECRET を必須にすること）。
  if (config.auth.devMode) {
    const role = (req.headers['x-role'] as AuthPrincipal['role']) ?? 'owner';
    const tenantId = role === 'super_admin'
      ? null
      : (req.headers['x-tenant-id'] as string) ?? config.demoTenantId;
    return { authUserId: 'dev', tenantId, role, email: 'dev@example.com' };
  }

  return null;
}
