import 'server-only';
import { getAuthCookieName, getSessionCookieOptions } from '@/lib/auth-session';

export async function POST() {
  const opts = getSessionCookieOptions();
  const cookieValue = [
    `${getAuthCookieName()}=`,
    `Path=${opts.path}`,
    'Max-Age=0',
    opts.httpOnly ? 'HttpOnly' : '',
    opts.secure ? 'Secure' : '',
    `SameSite=${opts.sameSite}`,
  ].filter(Boolean).join('; ');
  return Response.json({ logout: true }, {
    headers: { 'Cache-Control': 'no-store', 'Set-Cookie': cookieValue },
  });
}
