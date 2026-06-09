import { describe, it, expect } from 'vitest';
import { GET } from '@/app/api/marcha/[id]/route';
import { GET as SearchMarchas } from '@/app/api/admin/searchMarchas/route';
import { req, authReq } from './helpers';

describe('GET /api/marcha/[id]', () => {
  it('returns 401 without auth token', async () => {
    const params = Promise.resolve({ id: '1' });
    const res = await GET(req('/api/marcha/1'), { params });
    expect(res.status).toBe(401);
  });

  it('returns 404 for non-existent marcha', async () => {
    const params = Promise.resolve({ id: '9999' });
    const res = await GET(authReq('/api/marcha/9999'), { params });
    expect(res.status).toBe(404);
  });

  it('returns 200 with marcha data for a valid id', async () => {
    const params = Promise.resolve({ id: '1' });
    const res = await GET(authReq('/api/marcha/1'), { params });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.ID_MARCHA).toBe(1);
    expect(body.TITULO).toBe('Marcha Test');
    expect(Array.isArray(body.AUTOR)).toBe(true);
  });
});

describe('GET /api/admin/searchMarchas', () => {
  it('returns 401 without auth token', async () => {
    const res = await SearchMarchas(req('/api/admin/searchMarchas?titulo=test'));
    expect(res.status).toBe(401);
  });

  it('returns results for a valid FTS query', async () => {
    const res = await SearchMarchas(authReq('/api/admin/searchMarchas?titulo=test'));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.rowsReturned).toBeGreaterThan(0);
    expect(body.data[0].TITULO).toContain('Test');
  });
});
