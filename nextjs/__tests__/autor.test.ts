import { describe, it, expect } from 'vitest';
import { GET } from '@/app/api/autor/[id]/route';
import { GET as SearchAutores } from '@/app/api/admin/searchAutores/route';
import { req, authReq } from './helpers';

describe('GET /api/autor/[id]', () => {
  it('returns 401 without auth token', async () => {
    const params = Promise.resolve({ id: '1' });
    const res = await GET(req('/api/autor/1'), { params });
    expect(res.status).toBe(401);
  });

  it('returns 404 for non-existent autor', async () => {
    const params = Promise.resolve({ id: '9999' });
    const res = await GET(authReq('/api/autor/9999'), { params });
    expect(res.status).toBe(404);
  });

  it('returns 200 with autor data for a valid id', async () => {
    const params = Promise.resolve({ id: '1' });
    const res = await GET(authReq('/api/autor/1'), { params });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.ID_AUTOR).toBe(1);
    expect(body.NOMBRE).toBe('Juan');
    expect(body.APELLIDOS).toBe('García');
    expect(Array.isArray(body.marchas)).toBe(true);
  });
});

describe('GET /api/admin/searchAutores', () => {
  it('returns 401 without auth token', async () => {
    const res = await SearchAutores(req('/api/admin/searchAutores?nombre=garcia'));
    expect(res.status).toBe(401);
  });

  it('returns results for a valid FTS query', async () => {
    const res = await SearchAutores(authReq('/api/admin/searchAutores?nombre=garcia'));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.rowsReturned).toBeGreaterThan(0);
    expect(body.data[0].APELLIDOS).toBe('García');
  });
});
