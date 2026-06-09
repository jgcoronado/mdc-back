import 'server-only';
import { type NextRequest } from 'next/server';
import { dbRun, logAdmin } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

const INSERTABLE_FIELDS = ['NOMBRE', 'APELLIDOS', 'NOMBRE_ART', 'F_NAC', 'LUGAR_NAC', 'F_DEF', 'BIO'] as const;

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
  const autor = (body.autor ?? {}) as Record<string, unknown>;

  const values = INSERTABLE_FIELDS.map((f) => normalize(autor[f]));
  const placeholders = INSERTABLE_FIELDS.map(() => '?').join(', ');
  const info = dbRun(
    `INSERT INTO autor (${INSERTABLE_FIELDS.join(', ')}) VALUES (${placeholders})`,
    values
  );
  const autorId = Number(info.lastInsertRowid);
  if (!autorId) return Response.json({ code: 'INTERNAL_ERROR', msg: 'Could not create autor' }, { status: 500 });

  logAdmin('INSERT', 'autor', autorId);
  return Response.json({ code: 'CREATED', msg: 'Autor created', autorId }, { status: 201 });
}
