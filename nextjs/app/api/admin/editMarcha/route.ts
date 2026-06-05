import 'server-only';
import { type NextRequest } from 'next/server';
import { dbRun, logAdmin } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

const EDITABLE_FIELDS = new Set([
  'TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'AUDIO', 'BANDA_ESTRENO', 'DETALLES_MARCHA',
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
  const { marchaId, keysToUpdate = [], valuesToUpdate = [] } = body;

  if (!marchaId) return Response.json({ code: 'INVALID_PAYLOAD', msg: 'Missing marchaId' }, { status: 400 });
  if (!Array.isArray(keysToUpdate) || !Array.isArray(valuesToUpdate) || keysToUpdate.length !== valuesToUpdate.length) {
    return Response.json({ code: 'INVALID_PAYLOAD', msg: 'Invalid update arrays' }, { status: 400 });
  }
  if (keysToUpdate.length === 0) return Response.json({ code: 'NO_CHANGES', msg: 'No changes', affectedRows: 0 });

  const safeKeys = (keysToUpdate as string[]).filter((k) => EDITABLE_FIELDS.has(k));
  const safeVals = safeKeys.map((k) => normalize(valuesToUpdate[(keysToUpdate as string[]).indexOf(k)]));

  if (safeKeys.length === 0) return Response.json({ code: 'INVALID_FIELDS', msg: 'No editable fields' }, { status: 400 });

  const sql = `UPDATE marcha SET ${safeKeys.map((k) => `${k} = ?`).join(', ')} WHERE ID_MARCHA = ?`;
  const info = dbRun(sql, [...safeVals, marchaId]);

  if (info.changes === 0) return Response.json({ code: 'NOT_FOUND', msg: 'Marcha not found', affectedRows: 0 }, { status: 404 });

  logAdmin('UPDATE', 'marcha', Number(marchaId), { campos: safeKeys });
  return Response.json({ code: 'UPDATED', msg: 'Marcha updated', changedRows: info.changes, affectedRows: info.changes });
}
