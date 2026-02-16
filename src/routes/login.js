import express from 'express';
import crypto from 'node:crypto';
import { resolveQuery } from '../helpers/index.js';

const router = express.Router();
const tokenSecret = process.env.SECRET_KEY || 'change-this-secret';
const tokenTtlMs = Number(process.env.LOGIN_TTL_MS || 8 * 60 * 60 * 1000);

const signSession = (payload) => {
  const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64url');
  const signature = crypto
    .createHmac('sha256', tokenSecret)
    .update(encodedPayload)
    .digest('base64url');
  return `${encodedPayload}.${signature}`;
};

const verifySession = (token) => {
  const [encodedPayload, signature] = token.split('.');
  if (!encodedPayload || !signature) {
    return null;
  }

  const expectedSignature = crypto
    .createHmac('sha256', tokenSecret)
    .update(encodedPayload)
    .digest('base64url');
  if (signature !== expectedSignature) {
    return null;
  }

  const payload = JSON.parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
  if (!payload.exp || Date.now() > payload.exp) {
    return null;
  }

  return payload;
};

router.post('/', async (req, res) => {
  try {
    const { username, password } = req.body;
    if (!username || !password) {
      return res.status(400).json({ msg: 'Missing credentials' });
    }

    const sql = `SELECT u.USUARIO FROM usuarios u
      WHERE u.USUARIO = ? AND u.CLAVE = MD5(?) LIMIT 1`;
    const params = [username, password];
    const results = await resolveQuery(sql, params);
    const credenciales = results?.data?.[0];

    if (!credenciales) {
      return res.status(401).json({ msg: 'Invalid credentials' });
    }

    const expiresAt = Date.now() + tokenTtlMs;
    const token = signSession({ user: credenciales.USUARIO, exp: expiresAt });

    return res.status(200).json({
      login: true,
      token,
      user: credenciales.USUARIO,
      expiresAt,
    });
  } catch (err) {
    console.error('POST /api/login failed:', err);
    return res.status(500).json({ msg: 'Internal server error' });
  }
});

router.get('/verify', (req, res) => {
  const authHeader = req.headers.authorization || '';
  const token = authHeader.startsWith('Bearer ')
    ? authHeader.slice(7).trim()
    : '';

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

export default router;
