import 'server-only';
import { type NextRequest } from 'next/server';
import { dbRun } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

const INSERTABLE_FIELDS = ['TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'BANDA_ESTRENO', 'DETALLES_MARCHA'] as const;

const normalize = (v: unknown): unknown => {
  if (v === undefined) return null;
  if (typeof v === 'string') { const t = v.trim(); return t === '' ? null : t; }
  return v;
};

export async function POST(req: NextRequest) {
  if (!verifySession(getTokenFromRequest(req))) {
    return Response.json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' }, { status: 401 });
  }

  const body = await req.json().catch(() => ({})) as Record<string, unknown>;
  const marcha = (body.marcha ?? {}) as Record<string, unknown>;
  const autoresIds = body.autoresIds;

  const values = INSERTABLE_FIELDS.map((f) => normalize(marcha[f]));
  const placeholders = INSERTABLE_FIELDS.map(() => '?').join(', ');
  const insertInfo = dbRun(
    `INSERT INTO marcha (${INSERTABLE_FIELDS.join(', ')}) VALUES (${placeholders})`,
    values
  );
  const marchaId = Number(insertInfo.lastInsertRowid);
  if (!marchaId) return Response.json({ code: 'INTERNAL_ERROR', msg: 'Could not create marcha' }, { status: 500 });

  const raw = Array.isArray(autoresIds) ? autoresIds.join(',') : String(autoresIds ?? '');
  const ids = [...new Set(
    raw.split(',').map((v) => parseInt(v.trim(), 10)).filter((n) => Number.isInteger(n) && n > 0)
  )];
  if (ids.length > 0) {
    dbRun(
      `INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES ${ids.map(() => '(?, ?)').join(', ')}`,
      ids.flatMap((id) => [marchaId, id])
    );
  }

  return Response.json({ code: 'CREATED', msg: 'Marcha created', marchaId }, { status: 201 });
}
