import 'server-only';
import crypto from 'node:crypto';
import { type NextRequest } from 'next/server';
import { dbAll, dbRun } from '@/lib/db';
import { signSession, getAuthCookieName, getSessionCookieOptions } from '@/lib/auth-session';

const MAX_ATTEMPTS = Number(process.env.LOGIN_MAX_ATTEMPTS ?? 6);
const WINDOW_MS    = Number(process.env.LOGIN_WINDOW_MS    ?? 15 * 60 * 1000);
const LOCK_MS      = Number(process.env.LOGIN_LOCK_MS      ?? 15 * 60 * 1000);
const TTL_MS       = Number(process.env.LOGIN_TTL_MS       ?? 8 * 60 * 60 * 1000);

const attempts = new Map<string, { count: number; firstAt: number; lockUntil: number }>();

function rateKey(req: NextRequest, username: string) {
  const ip = req.headers.get('x-forwarded-for')?.split(',')[0].trim()
    ?? req.headers.get('x-real-ip')
    ?? 'unknown';
  return `${ip}:${username.trim().toLowerCase()}`;
}

function getState(key: string) {
  const s = attempts.get(key);
  const now = Date.now();
  if (!s || (now - s.firstAt > WINDOW_MS && s.lockUntil < now)) return { count: 0, firstAt: now, lockUntil: 0 };
  return s;
}

function fail(key: string) {
  const s = getState(key);
  const next = { count: s.count + 1, firstAt: s.firstAt, lockUntil: s.lockUntil };
  if (next.count >= MAX_ATTEMPTS) next.lockUntil = Date.now() + LOCK_MS;
  attempts.set(key, next);
}

function verifyPassword(plain: string, stored: string): boolean {
  if (!stored) return false;
  if (stored.startsWith('pbkdf2$')) {
    const [, digest, iterStr, salt, expected] = stored.split('$');
    const iters = Number(iterStr);
    if (!digest || !salt || !expected || !Number.isFinite(iters)) return false;
    const derived = crypto.pbkdf2Sync(plain, salt, iters, 64, digest).toString('base64url');
    const a = Buffer.from(derived), b = Buffer.from(expected);
    return a.length === b.length && crypto.timingSafeEqual(a, b);
  }
  const md5 = crypto.createHash('md5').update(plain).digest('hex');
  const a = Buffer.from(md5), b = Buffer.from(stored);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function hashPassword(plain: string): string {
  const iters = Number(process.env.PASSWORD_PBKDF2_ITERATIONS ?? 210000);
  const salt = crypto.randomBytes(16).toString('base64url');
  const derived = crypto.pbkdf2Sync(plain, salt, iters, 64, 'sha512').toString('base64url');
  return `pbkdf2$sha512$${iters}$${salt}$${derived}`;
}

export async function POST(req: NextRequest) {
  const headers = { 'Cache-Control': 'no-store' };
  const body = await req.json().catch(() => ({})) as Record<string, unknown>;
  const username = String(body.username ?? '');
  const password = String(body.password ?? '');

  if (!username || !password) return Response.json({ msg: 'Missing credentials' }, { status: 400, headers });
  if (username.length > 120 || password.length > 512) return Response.json({ msg: 'Invalid credentials payload' }, { status: 400, headers });

  const key = rateKey(req, username);
  const state = getState(key);
  if (state.lockUntil > Date.now()) {
    const retry = Math.ceil((state.lockUntil - Date.now()) / 1000);
    return Response.json({ msg: `Too many attempts. Retry in ${retry}s.` }, { status: 429, headers });
  }

  const rows = dbAll<{ USUARIO: string; CLAVE: string }>(`SELECT USUARIO, CLAVE FROM usuarios WHERE USUARIO = ? LIMIT 1`, [username.trim()]);
  const user = rows[0];

  if (!user || !verifyPassword(password, user.CLAVE)) {
    fail(key);
    return Response.json({ msg: 'Invalid credentials' }, { status: 401, headers });
  }
  attempts.delete(key);

  if (/^[a-f0-9]{32}$/i.test(user.CLAVE)) {
    dbRun(`UPDATE usuarios SET CLAVE = ? WHERE USUARIO = ?`, [hashPassword(password), user.USUARIO]);
  }

  const expiresAt = Date.now() + TTL_MS;
  const token = signSession({ user: user.USUARIO, iat: Date.now(), exp: expiresAt, jti: crypto.randomUUID() });
  const opts = getSessionCookieOptions();
  const cookieValue = [
    `${getAuthCookieName()}=${token}`,
    `Path=${opts.path}`,
    `Max-Age=${Math.floor(opts.maxAge / 1000)}`,
    opts.httpOnly ? 'HttpOnly' : '',
    opts.secure ? 'Secure' : '',
    `SameSite=${opts.sameSite}`,
  ].filter(Boolean).join('; ');

  return Response.json({ login: true, user: user.USUARIO, expiresAt }, {
    status: 200,
    headers: { ...headers, 'Set-Cookie': cookieValue },
  });
}
