import { signSession } from '@/lib/auth-session';
import { NextRequest } from 'next/server';

export function makeToken(): string {
  return signSession({
    user: 'admin',
    iat: Date.now(),
    exp: Date.now() + 8 * 60 * 60 * 1000,
    jti: 'test-jti',
  });
}

export function req(path: string, init?: RequestInit): NextRequest {
  return new NextRequest(`http://localhost${path}`, init);
}

export function authReq(path: string, init?: RequestInit): NextRequest {
  return new NextRequest(`http://localhost${path}`, {
    ...init,
    headers: {
      ...((init?.headers as Record<string, string>) ?? {}),
      Authorization: `Bearer ${makeToken()}`,
    },
  });
}
