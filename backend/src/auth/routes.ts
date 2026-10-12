import type { FastifyInstance } from 'fastify';
import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { getUserForLogin, setUserPassword } from '../db/queries.js';
import { hashPassword, verifyPassword } from './password.js';
import { verifyInviteToken } from './invite.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MIN_PASSWORD = 8;

// ログイントークン（HS256）を発行する。
function issueLoginToken(user: { id: string; tenant_id: string | null; role: string; email: string }): string {
  return jwt.sign(
    { sub: user.id, tenant_id: user.tenant_id, role: user.role, email: user.email },
    config.auth.jwtSecret,
    { expiresIn: config.auth.tokenTtl as any },
  );
}

// 自前のメール＋パスワード認証。ログインでHS256のJWTを発行し、
// 以降のAPIは auth/jwt.ts の authenticate が同じ秘密鍵で検証する。
export async function registerAuthRoutes(app: FastifyInstance): Promise<void> {
  // ログイン: email/password → JWT を発行。
  app.post('/api/auth/login', async (req, reply) => {
    const { email, password } = (req.body ?? {}) as { email?: string; password?: string };
    if (!email || !password) return reply.code(400).send({ error: 'メールとパスワードを入力してください。' });
    if (!config.auth.jwtSecret) {
      return reply.code(503).send({ error: 'サーバーに認証鍵(AUTH_JWT_SECRET)が未設定です。運営側の設定が必要です。' });
    }
    const user = await getUserForLogin(email);
    // ユーザー有無・パスワード誤りで応答を変えない（列挙攻撃対策）。
    if (!user || !user.is_active || !verifyPassword(password, user.password_hash)) {
      return reply.code(401).send({ error: 'メールアドレスまたはパスワードが違います。' });
    }
    const token = issueLoginToken(user);
    return { token, role: user.role, tenant_id: user.tenant_id, email: user.email, name: user.name ?? null };
  });

  // 初回パスワード設定/リセット: SETUP_TOKEN 設定時のみ有効。
  app.post('/api/auth/bootstrap', async (req, reply) => {
    const { email, password, setup_token } = (req.body ?? {}) as
      { email?: string; password?: string; setup_token?: string };
    if (!config.auth.setupToken) {
      return reply.code(403).send({ error: '初回設定は無効です（サーバーに SETUP_TOKEN が未設定）。' });
    }
    if (!setup_token || setup_token !== config.auth.setupToken) {
      return reply.code(403).send({ error: 'セットアップトークンが違います。' });
    }
    if (!email || !EMAIL_RE.test(email)) return reply.code(400).send({ error: 'メールアドレスの形式が正しくありません。' });
    if (!password || password.length < MIN_PASSWORD) {
      return reply.code(400).send({ error: `パスワードは${MIN_PASSWORD}文字以上にしてください。` });
    }
    const ok = await setUserPassword(email, hashPassword(password));
    if (!ok) return reply.code(404).send({ error: 'そのメールのユーザーが見つかりません（seedのオーナーと一致させてください）。' });
    return { ok: true };
  });

  // 招待リンクからのパスワード設定（店舗オンボーディング）。トークンが宛先メールを保証する。
  app.post('/api/auth/accept-invite', async (req, reply) => {
    const { token, password } = (req.body ?? {}) as { token?: string; password?: string };
    if (!config.auth.jwtSecret) {
      return reply.code(503).send({ error: 'サーバーに認証鍵(AUTH_JWT_SECRET)が未設定です。' });
    }
    const claims = token ? verifyInviteToken(token) : null;
    if (!claims) return reply.code(400).send({ error: '招待リンクが無効か、有効期限が切れています。運営者に再発行を依頼してください。' });
    if (!password || password.length < MIN_PASSWORD) {
      return reply.code(400).send({ error: `パスワードは${MIN_PASSWORD}文字以上にしてください。` });
    }
    const ok = await setUserPassword(claims.email, hashPassword(password));
    if (!ok) return reply.code(404).send({ error: 'アカウントが見つかりません。運営者にお問い合わせください。' });
    // そのままログインできるようトークンも返す。
    const user = await getUserForLogin(claims.email);
    const loginToken = user ? issueLoginToken(user) : null;
    return { ok: true, token: loginToken, role: user?.role ?? null, tenant_id: user?.tenant_id ?? null, email: claims.email };
  });
}
