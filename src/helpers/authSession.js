import crypto from 'node:crypto';

const DEFAULT_COOKIE_NAME = 'mdc_session';
const DEFAULT_TTL_MS = 8 * 60 * 60 * 1000;
const MIN_SECRET_LENGTH = 32;

const assertTokenSecret = () => {
  const secret = process.env.SECRET_KEY || '';
  if (!secret || secret === 'change-this-secret' || secret.length < MIN_SECRET_LENGTH) {
    throw new Error(
      `SECRET_KEY is missing or weak. Set a random secret with at least ${MIN_SECRET_LENGTH} chars.`
    );
  }
  return secret;
};
const TOKEN_SECRET = assertTokenSecret();

const getAuthCookieName = () => process.env.AUTH_COOKIE_NAME || DEFAULT_COOKIE_NAME;

const getTokenTtlMs = () => Number(process.env.LOGIN_TTL_MS || DEFAULT_TTL_MS);

const getSessionCookieOptions = () => {
  const secureByEnv = (process.env.NODE_ENV || '').toLowerCase() === 'production';
  const forceSecure = (process.env.COOKIE_SECURE || '').toLowerCase() === 'true';
  return {
    httpOnly: true,
    secure: forceSecure || secureByEnv,
    sameSite: 'lax',
    path: '/',
    maxAge: getTokenTtlMs(),
  };
};

const signSession = (payload) => {
  const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64url');
  const signature = crypto
    .createHmac('sha256', TOKEN_SECRET)
    .update(encodedPayload)
    .digest('base64url');
  return `${encodedPayload}.${signature}`;
};

const verifySession = (token) => {
  const [encodedPayload, signature] = (token || '').split('.');
  if (!encodedPayload || !signature) {
    return null;
  }

  const expectedSignature = crypto
    .createHmac('sha256', TOKEN_SECRET)
    .update(encodedPayload)
    .digest('base64url');

  const providedBuffer = Buffer.from(signature);
  const expectedBuffer = Buffer.from(expectedSignature);
  if (providedBuffer.length !== expectedBuffer.length) {
    return null;
  }
  if (!crypto.timingSafeEqual(providedBuffer, expectedBuffer)) {
    return null;
  }

  try {
    const payload = JSON.parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
    if (!payload.exp || Date.now() > payload.exp) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
};

const parseCookies = (cookieHeader = '') => {
  return cookieHeader
    .split(';')
    .map((part) => part.trim())
    .filter(Boolean)
    .reduce((acc, item) => {
      const separatorIndex = item.indexOf('=');
      if (separatorIndex < 0) return acc;
      const key = item.slice(0, separatorIndex).trim();
      const value = item.slice(separatorIndex + 1).trim();
      acc[key] = decodeURIComponent(value);
      return acc;
    }, {});
};

const getTokenFromRequest = (req) => {
  const authHeader = req.headers.authorization || '';
  const bearer = authHeader.startsWith('Bearer ')
    ? authHeader.slice(7).trim()
    : '';
  if (bearer) return bearer;

  const cookieName = getAuthCookieName();
  const cookies = parseCookies(req.headers.cookie || '');
  return cookies[cookieName] || '';
};

export {
  getAuthCookieName,
  getTokenFromRequest,
  getTokenTtlMs,
  getSessionCookieOptions,
  parseCookies,
  signSession,
  verifySession,
};
