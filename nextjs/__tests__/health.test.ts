import { describe, it, expect } from 'vitest';
import { GET } from '@/app/api/health/route';

describe('GET /api/health', () => {
  it('returns 200 with {status:ok, db:true} when DB is reachable', async () => {
    const res = GET();
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toEqual({ status: 'ok', db: true });
  });

  it('response Content-Type is application/json', async () => {
    const res = GET();
    expect(res.headers.get('content-type')).toContain('application/json');
  });
});
