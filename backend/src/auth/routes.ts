import type { FastifyInstance } from 'fastify';
import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { getUserForLogin, setUserPassword } from '../db/queries.js';
import { hashPassword, verifyPassword } from './password.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MIN_PASSWORD = 8;

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
    const token = jwt.sign(
      { sub: user.id, tenant_id: user.tenant_id, role: user.role, email: user.email },
      config.auth.jwtSecret,
      { expiresIn: config.auth.tokenTtl as any },
    );
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
}
