import { describe, it, expect } from 'vitest';
import { GET as AutorSearch } from '@/app/api/autor/fastSearch/route';
import { GET as BandaSearch } from '@/app/api/banda/fastSearch/route';
import { req } from './helpers';

describe('GET /api/autor/fastSearch', () => {
  it('returns empty result for blank query', async () => {
    const res = await AutorSearch(req('/api/autor/fastSearch?nombre='));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toEqual({ rowsReturned: 0, data: [] });
  });

  it('returns matching autores for a prefix query', async () => {
    const res = await AutorSearch(req('/api/autor/fastSearch?nombre=Gar'));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.rowsReturned).toBeGreaterThan(0);
    expect(body.data[0].APELLIDOS).toBe('García');
  });
});

describe('GET /api/banda/fastSearch', () => {
  it('returns empty result for blank query', async () => {
    const res = await BandaSearch(req('/api/banda/fastSearch?nombre='));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toEqual({ rowsReturned: 0, data: [] });
  });

  it('returns matching bandas for a prefix query', async () => {
    const res = await BandaSearch(req('/api/banda/fastSearch?nombre=Ban'));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.rowsReturned).toBeGreaterThan(0);
    expect(body.data[0].NOMBRE_BREVE).toBe('Banda Test');
  });
});
