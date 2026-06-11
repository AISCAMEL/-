import twilio from 'twilio';
import type { FastifyRequest } from 'fastify';
import { config } from '../config.js';

/**
 * X-Twilio-Signature を検証する。
 * 署名は「フルURL + POSTパラメータ」で計算されるため、公開URLを再構成して照合する。
 * TWILIO_VALIDATE_SIGNATURE=false（開発時）は常に許可。
 */
export function verifyTwilioSignature(req: FastifyRequest, params: Record<string, unknown>): boolean {
  if (!config.twilio.validateSignature) return true;
  const signature = req.headers['x-twilio-signature'];
  if (typeof signature !== 'string') return false;

  const url = `${config.publicApiBaseUrl}${req.url}`;
  return twilio.validateRequest(
    config.twilio.authToken,
    signature,
    url,
    params as Record<string, string>,
  );
}
