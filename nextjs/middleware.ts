import { NextRequest, NextResponse } from 'next/server';
import { verifySession, getAuthCookieName } from '@/lib/auth-session';

// Node.js runtime required — verifySession uses node:crypto.
export const runtime = 'nodejs';

export const config = { matcher: ['/dashboard/:path*'] };

export function middleware(request: NextRequest) {
  const token = request.cookies.get(getAuthCookieName())?.value ?? '';
  const payload = verifySession(token);
  if (!payload) return NextResponse.redirect(new URL('/login', request.url));
  return NextResponse.next();
}
