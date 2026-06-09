import { describe, it, expect } from 'vitest';
import { POST as AddMarcha } from '@/app/api/admin/addMarcha/route';
import { POST as EditMarcha } from '@/app/api/admin/editMarcha/route';
import { POST as EditMarchaAutores } from '@/app/api/admin/editMarchaAutores/route';
import { POST as AddAutor } from '@/app/api/admin/addAutor/route';
import { POST as EditAutor } from '@/app/api/admin/editAutor/route';
import { req, authReq } from './helpers';

function jsonReq(path: string, body: unknown) {
  return req(path, { method: 'POST', body: JSON.stringify(body), headers: { 'Content-Type': 'application/json' } });
}
function jsonAuthReq(path: string, body: unknown) {
  return authReq(path, { method: 'POST', body: JSON.stringify(body), headers: { 'Content-Type': 'application/json' } });
}

// ── addMarcha ────────────────────────────────────────────────────────────────

describe('POST /api/admin/addMarcha', () => {
  it('returns 401 without auth', async () => {
    const res = await AddMarcha(jsonReq('/api/admin/addMarcha', {}));
    expect(res.status).toBe(401);
  });

  it('returns 400 when no valid fields are provided', async () => {
    const res = await AddMarcha(jsonAuthReq('/api/admin/addMarcha', {
      fieldsToInsert: ['CAMPO_INVALIDO'],
      valuesToInsert: ['value'],
      autoresIds: [1],
    }));
    expect(res.status).toBe(400);
    const body = await res.json();
    expect(body.code).toBe('INVALID_PAYLOAD');
  });

  it('returns 400 when autoresIds is empty', async () => {
    const res = await AddMarcha(jsonAuthReq('/api/admin/addMarcha', {
      fieldsToInsert: ['TITULO'],
      valuesToInsert: ['Nueva Marcha'],
      autoresIds: [],
    }));
    expect(res.status).toBe(400);
    const body = await res.json();
    expect(body.code).toBe('AUTHORS_REQUIRED');
  });

  it('creates a marcha and returns 201 with the new id', async () => {
    const res = await AddMarcha(jsonAuthReq('/api/admin/addMarcha', {
      fieldsToInsert: ['TITULO', 'FECHA', 'LOCALIDAD'],
      valuesToInsert: ['Nueva Marcha', '2021', 'Madrid'],
      autoresIds: [1],
    }));
    expect(res.status).toBe(201);
    const body = await res.json();
    expect(body.code).toBe('CREATED');
    expect(typeof body.marchaId).toBe('number');
    expect(body.marchaId).toBeGreaterThan(0);
  });
});

// ── editMarcha ────────────────────────────────────────────────────────────────

describe('POST /api/admin/editMarcha', () => {
  it('returns 401 without auth', async () => {
    const res = await EditMarcha(jsonReq('/api/admin/editMarcha', {}));
    expect(res.status).toBe(401);
  });

  it('returns 404 for a non-existent marchaId', async () => {
    const res = await EditMarcha(jsonAuthReq('/api/admin/editMarcha', {
      marchaId: 88888,
      keysToUpdate: ['TITULO'],
      valuesToUpdate: ['Titulo X'],
    }));
    expect(res.status).toBe(404);
    const body = await res.json();
    expect(body.code).toBe('NOT_FOUND');
  });

  it('updates a marcha and returns 200', async () => {
    const res = await EditMarcha(jsonAuthReq('/api/admin/editMarcha', {
      marchaId: 999,
      keysToUpdate: ['TITULO', 'LOCALIDAD'],
      valuesToUpdate: ['Marcha Actualizada', 'Granada'],
    }));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.code).toBe('UPDATED');
    expect(body.changedRows).toBe(1);
  });
});

// ── editMarchaAutores ─────────────────────────────────────────────────────────

describe('POST /api/admin/editMarchaAutores', () => {
  it('returns 401 without auth', async () => {
    const res = await EditMarchaAutores(jsonReq('/api/admin/editMarchaAutores', {}));
    expect(res.status).toBe(401);
  });

  it('updates marcha autores and returns 200', async () => {
    const res = await EditMarchaAutores(jsonAuthReq('/api/admin/editMarchaAutores', {
      marchaId: 999,
      autoresIds: [2],
    }));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.code).toBe('UPDATED');
  });
});

// ── addAutor ──────────────────────────────────────────────────────────────────

describe('POST /api/admin/addAutor', () => {
  it('returns 401 without auth', async () => {
    const res = await AddAutor(jsonReq('/api/admin/addAutor', {}));
    expect(res.status).toBe(401);
  });

  it('creates an autor and returns 201 with the new id', async () => {
    const res = await AddAutor(jsonAuthReq('/api/admin/addAutor', {
      autor: { NOMBRE: 'Pedro', APELLIDOS: 'Martínez', NOMBRE_ART: 'PM' },
    }));
    expect(res.status).toBe(201);
    const body = await res.json();
    expect(body.code).toBe('CREATED');
    expect(typeof body.autorId).toBe('number');
    expect(body.autorId).toBeGreaterThan(0);
  });
});

// ── editAutor ─────────────────────────────────────────────────────────────────

describe('POST /api/admin/editAutor', () => {
  it('returns 401 without auth', async () => {
    const res = await EditAutor(jsonReq('/api/admin/editAutor', {}));
    expect(res.status).toBe(401);
  });

  it('updates an autor and returns 200', async () => {
    const res = await EditAutor(jsonAuthReq('/api/admin/editAutor', {
      autorId: 1,
      keysToUpdate: ['NOMBRE_ART'],
      valuesToUpdate: ['JG-Updated'],
    }));
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.code).toBe('UPDATED');
  });
});
