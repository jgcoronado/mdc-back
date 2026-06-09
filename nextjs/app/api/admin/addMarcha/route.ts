import 'server-only';
import { type NextRequest } from 'next/server';
import { dbAll, dbRun, dbTransaction, logAdmin } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

const INSERTABLE_FIELDS = new Set([
  'TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'BANDA_ESTRENO', 'DETALLES_MARCHA',
]);

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
  const fieldsRaw = Array.isArray(body.fieldsToInsert) ? body.fieldsToInsert as string[] : [];
  const valuesRaw = Array.isArray(body.valuesToInsert) ? body.valuesToInsert : [];

  const safeEntries = fieldsRaw
    .map((f, i) => [f, valuesRaw[i]] as [string, unknown])
    .filter(([f]) => INSERTABLE_FIELDS.has(f));

  if (safeEntries.length === 0) {
    return Response.json({ code: 'INVALID_PAYLOAD', msg: 'No valid fields to insert' }, { status: 400 });
  }

  const safeFields = safeEntries.map(([f]) => f);
  const safeValues = safeEntries.map(([, v]) => normalize(v));

  const fechaIdx = safeFields.indexOf('FECHA');
  if (fechaIdx !== -1 && safeValues[fechaIdx] !== null && !/^\d{4}$/.test(String(safeValues[fechaIdx]))) {
    return Response.json({ code: 'INVALID_FECHA', msg: 'FECHA debe ser un año de 4 dígitos o vacío' }, { status: 400 });
  }

  const autoresRaw = body.autoresIds;
  const rawStr = Array.isArray(autoresRaw) ? autoresRaw.join(',') : String(autoresRaw ?? '');
  const ids = [...new Set(
    rawStr.split(',').map((v) => parseInt(v.trim(), 10)).filter((n) => Number.isInteger(n) && n > 0)
  )];

  if (ids.length === 0) {
    return Response.json({ code: 'AUTHORS_REQUIRED', msg: 'Al menos un autor es obligatorio' }, { status: 400 });
  }

  const found = dbAll<{ c: number }>(
    `SELECT COUNT(*) AS c FROM autor WHERE ID_AUTOR IN (${ids.map(() => '?').join(',')})`,
    ids
  );
  if (found[0].c !== ids.length) {
    return Response.json({ code: 'INVALID_AUTHORS', msg: 'Uno o más IDs de autor no existen' }, { status: 400 });
  }

  const marchaId = dbTransaction<number>(() => {
    const info = dbRun(
      `INSERT INTO marcha (${safeFields.join(', ')}) VALUES (${safeFields.map(() => '?').join(', ')})`,
      safeValues
    );
    const newId = Number(info.lastInsertRowid);
    if (!newId) throw new Error('Could not create marcha');
    dbRun(
      `INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES ${ids.map(() => '(?, ?)').join(', ')}`,
      ids.flatMap((id) => [newId, id])
    );
    return newId;
  });

  logAdmin('INSERT', 'marcha', marchaId, { campos: safeFields, autores: ids });
  return Response.json({ code: 'CREATED', msg: 'Marcha created', marchaId }, { status: 201 });
}
