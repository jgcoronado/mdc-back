import 'server-only';
import { type NextRequest } from 'next/server';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

export async function GET(req: NextRequest) {
  const headers = { 'Cache-Control': 'no-store' };
  const payload = verifySession(getTokenFromRequest(req));
  if (!payload) return Response.json({ authenticated: false }, { status: 401, headers });
  return Response.json({ authenticated: true, user: payload.user, expiresAt: payload.exp }, { headers });
}
