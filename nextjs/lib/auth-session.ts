import crypto from 'node:crypto';
import type { NextRequest } from 'next/server';

const COOKIE_NAME = process.env.AUTH_COOKIE_NAME ?? 'mdc_session';
const TTL_MS = Number(process.env.LOGIN_TTL_MS ?? 8 * 60 * 60 * 1000);
const MIN_SECRET_LENGTH = 32;

// Lazy so that asserting the secret doesn't run at next build time.
let _secret: string | null = null;
function getSecret(): string {
  if (_secret) return _secret;
  const s = process.env.SECRET_KEY ?? '';
  if (!s || s.length < MIN_SECRET_LENGTH) throw new Error('SECRET_KEY is missing or too short (min 32 chars)');
  _secret = s;
  return _secret;
}

export function getAuthCookieName() { return COOKIE_NAME; }

export function getSessionCookieOptions() {
  const secure = process.env.NODE_ENV === 'production' || process.env.COOKIE_SECURE === 'true';
  return { httpOnly: true, secure, sameSite: 'lax' as const, path: '/', maxAge: TTL_MS };
}

export function signSession(payload: Record<string, unknown>): string {
  const encoded = Buffer.from(JSON.stringify(payload)).toString('base64url');
  const sig = crypto.createHmac('sha256', getSecret()).update(encoded).digest('base64url');
  return `${encoded}.${sig}`;
}

export function verifySession(token: string | null | undefined): Record<string, unknown> | null {
  if (!token) return null;
  const dot = token.indexOf('.');
  if (dot < 0) return null;
  const encoded = token.slice(0, dot);
  const sig = token.slice(dot + 1);
  const expected = crypto.createHmac('sha256', getSecret()).update(encoded).digest('base64url');
  const sigBuf = Buffer.from(sig);
  const expBuf = Buffer.from(expected);
  if (sigBuf.length !== expBuf.length || !crypto.timingSafeEqual(sigBuf, expBuf)) return null;
  try {
    const payload = JSON.parse(Buffer.from(encoded, 'base64url').toString('utf8')) as Record<string, unknown>;
    if (!payload.exp || Date.now() > Number(payload.exp)) return null;
    return payload;
  } catch { return null; }
}

export function getTokenFromRequest(req: NextRequest): string {
  const auth = req.headers.get('authorization') ?? '';
  if (auth.startsWith('Bearer ')) return auth.slice(7).trim();
  return req.cookies.get(COOKIE_NAME)?.value ?? '';
}
