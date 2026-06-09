import { describe, it, expect } from 'vitest';
import { POST } from '@/app/api/login/route';
import { req } from './helpers';

function loginReq(body: Record<string, unknown>) {
  return req('/api/login', {
    method: 'POST',
    body: JSON.stringify(body),
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('POST /api/login', () => {
  it('returns 200 and sets cookie on valid credentials', async () => {
    const res = await POST(loginReq({ username: 'admin', password: 'password' }));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.login).toBe(true);
    expect(body.user).toBe('admin');
    expect(res.headers.get('set-cookie')).toContain('mdc_session');
  });

  it('returns 401 on wrong password', async () => {
    const res = await POST(loginReq({ username: 'admin', password: 'wrong' }));
    expect(res.status).toBe(401);
    const body = await res.json();
    expect(body.msg).toBe('Invalid credentials');
  });

  it('returns 400 when credentials are missing', async () => {
    const res = await POST(loginReq({}));
    expect(res.status).toBe(400);
    const body = await res.json();
    expect(body.msg).toContain('Missing');
  });
});
