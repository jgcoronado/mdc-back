import express from 'express';
import crypto from 'node:crypto';
import { poolExecute } from '../helpers/index.js';
import { poolExecuteAdmin } from '../helpers/admin.js';
import {
  getAuthCookieName,
  getSessionCookieOptions,
  getTokenFromRequest,
  getTokenTtlMs,
  signSession,
  verifySession,
} from '../helpers/authSession.js';

const router = express.Router();
const tokenTtlMs = getTokenTtlMs();
const authCookieName = getAuthCookieName();
const sessionCookieOptions = getSessionCookieOptions();

const MAX_ATTEMPTS = Number(process.env.LOGIN_MAX_ATTEMPTS || 6);
const WINDOW_MS = Number(process.env.LOGIN_WINDOW_MS || 15 * 60 * 1000);
const LOCK_MS = Number(process.env.LOGIN_LOCK_MS || 15 * 60 * 1000);
const attemptsByKey = new Map();

const now = () => Date.now();

const getRateKey = (req, username) => {
  const ip = req.ip || req.socket?.remoteAddress || 'unknown-ip';
  return `${ip}:${String(username || '').trim().toLowerCase()}`;
};

const getAttemptState = (key) => {
  const state = attemptsByKey.get(key);
  if (!state) return { count: 0, firstAt: now(), lockUntil: 0 };
  if ((now() - state.firstAt) > WINDOW_MS && state.lockUntil < now()) {
    attemptsByKey.delete(key);
    return { count: 0, firstAt: now(), lockUntil: 0 };
  }
  return state;
};

const registerFailure = (key) => {
  const state = getAttemptState(key);
  const updated = {
    count: state.count + 1,
    firstAt: state.firstAt || now(),
    lockUntil: state.lockUntil || 0,
  };
  if (updated.count >= MAX_ATTEMPTS) {
    updated.lockUntil = now() + LOCK_MS;
  }
  attemptsByKey.set(key, updated);
};

const clearFailures = (key) => {
  attemptsByKey.delete(key);
};

const hashPassword = (plainPassword) => {
  const iterations = Number(process.env.PASSWORD_PBKDF2_ITERATIONS || 210000);
  const digest = 'sha512';
  const salt = crypto.randomBytes(16).toString('base64url');
  const derived = crypto.pbkdf2Sync(plainPassword, salt, iterations, 64, digest).toString('base64url');
  return `pbkdf2$${digest}$${iterations}$${salt}$${derived}`;
};

const timingSafeStringEqual = (left, right) => {
  const leftBuffer = Buffer.from(String(left || ''));
  const rightBuffer = Buffer.from(String(right || ''));
  if (leftBuffer.length !== rightBuffer.length) return false;
  return crypto.timingSafeEqual(leftBuffer, rightBuffer);
};

const verifyPassword = (plainPassword, storedHash) => {
  if (!storedHash) return false;

  if (storedHash.startsWith('pbkdf2$')) {
    const parts = storedHash.split('$');
    if (parts.length !== 5) return false;
    const [, digest, iterationsRaw, salt, expected] = parts;
    const iterations = Number(iterationsRaw);
    if (!digest || !salt || !expected || !Number.isFinite(iterations)) return false;
    const derived = crypto.pbkdf2Sync(plainPassword, salt, iterations, 64, digest).toString('base64url');
    return timingSafeStringEqual(derived, expected);
  }

  // Legacy format: MD5 hash
  const legacyMd5 = crypto.createHash('md5').update(plainPassword).digest('hex');
  return timingSafeStringEqual(legacyMd5, storedHash);
};

const isLegacyPasswordHash = (storedHash) => /^[a-f0-9]{32}$/i.test(String(storedHash || ''));

router.post('/', async (req, res) => {
  try {
    res.set('Cache-Control', 'no-store');
    const { username, password } = req.body || {};
    if (!username || !password) {
      return res.status(400).json({ msg: 'Missing credentials' });
    }
    if (String(username).length > 120 || String(password).length > 512) {
      return res.status(400).json({ msg: 'Invalid credentials payload' });
    }

    const rateKey = getRateKey(req, username);
    const currentState = getAttemptState(rateKey);
    if (currentState.lockUntil && currentState.lockUntil > now()) {
      const retryAfterSec = Math.ceil((currentState.lockUntil - now()) / 1000);
      return res.status(429).json({ msg: `Too many attempts. Retry in ${retryAfterSec}s.` });
    }

    const sql = `SELECT u.USUARIO, u.CLAVE FROM usuarios u WHERE u.USUARIO = ? LIMIT 1`;
    const [rows] = await poolExecute(sql, [String(username).trim()]);
    const userRecord = rows?.[0];

    if (!userRecord || !verifyPassword(password, userRecord.CLAVE)) {
      registerFailure(rateKey);
      return res.status(401).json({ msg: 'Invalid credentials' });
    }
    clearFailures(rateKey);

    if (isLegacyPasswordHash(userRecord.CLAVE)) {
      const upgradedHash = hashPassword(password);
      await poolExecuteAdmin('UPDATE usuarios SET CLAVE = ? WHERE USUARIO = ? LIMIT 1', [upgradedHash, userRecord.USUARIO]);
    }

    const expiresAt = Date.now() + tokenTtlMs;
    const token = signSession({
      user: userRecord.USUARIO,
      iat: Date.now(),
      exp: expiresAt,
      jti: crypto.randomUUID(),
    });
    res.cookie(authCookieName, token, sessionCookieOptions);

    return res.status(200).json({
      login: true,
      user: userRecord.USUARIO,
      expiresAt,
    });
  } catch (err) {
    console.error('POST /api/login failed:', err);
    return res.status(500).json({ msg: 'Internal server error' });
  }
});

router.get('/verify', (req, res) => {
  res.set('Cache-Control', 'no-store');
  const token = getTokenFromRequest(req);
  const payload = verifySession(token);
  if (!payload) {
    return res.status(401).json({ authenticated: false });
  }

  return res.status(200).json({
    authenticated: true,
    user: payload.user,
    expiresAt: payload.exp,
  });
});

router.post('/logout', (req, res) => {
  res.set('Cache-Control', 'no-store');
  res.clearCookie(authCookieName, {
    httpOnly: sessionCookieOptions.httpOnly,
    secure: sessionCookieOptions.secure,
    sameSite: sessionCookieOptions.sameSite,
    path: sessionCookieOptions.path,
  });
  return res.status(200).json({ logout: true });
});

export default router;
