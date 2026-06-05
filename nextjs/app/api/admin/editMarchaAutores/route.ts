import 'server-only';
import { type NextRequest } from 'next/server';
import { dbAll, dbRun, dbTransaction, logAdmin } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

export async function POST(req: NextRequest) {
  if (!verifySession(getTokenFromRequest(req))) {
    return Response.json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' }, { status: 401 });
  }

  const body = await req.json().catch(() => ({})) as Record<string, unknown>;
  const { marchaId, autoresIds } = body as { marchaId: number; autoresIds: number[] };

  if (!marchaId || !Array.isArray(autoresIds) || autoresIds.length === 0) {
    return Response.json({ code: 'BAD_REQUEST', msg: 'marchaId y autoresIds (no vacío) son obligatorios' }, { status: 400 });
  }

  const existingAutores = dbAll<{ ID_AUTOR: number }>(
    `SELECT ID_AUTOR FROM autor WHERE ID_AUTOR IN (${autoresIds.map(() => '?').join(',')})`,
    autoresIds
  );
  if (existingAutores.length !== autoresIds.length) {
    return Response.json({ code: 'INVALID_AUTORES', msg: 'Uno o más IDs de autor no existen' }, { status: 400 });
  }

  dbTransaction(() => {
    dbRun('DELETE FROM marcha_autor WHERE ID_MARCHA = ?', [marchaId]);
    for (const autorId of autoresIds) {
      dbRun('INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES (?, ?)', [marchaId, autorId]);
    }
  });

  logAdmin('UPDATE', 'marcha_autor', marchaId, { autoresIds });
  return Response.json({ code: 'UPDATED', msg: 'Autores de marcha actualizados' });
}
