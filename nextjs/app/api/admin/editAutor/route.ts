import 'server-only';
import { type NextRequest } from 'next/server';
import { revalidatePath } from 'next/cache';
import { dbRun, logAdmin } from '@/lib/db';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';

const EDITABLE_FIELDS = ['NOMBRE', 'APELLIDOS', 'NOMBRE_ART', 'F_NAC', 'LUGAR_NAC', 'F_DEF', 'BIO'] as const;

export async function POST(req: NextRequest) {
  if (!verifySession(getTokenFromRequest(req))) {
    return Response.json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' }, { status: 401 });
  }

  const body = await req.json().catch(() => ({})) as Record<string, unknown>;
  const { autorId, keysToUpdate, valuesToUpdate } = body as {
    autorId: number;
    keysToUpdate: string[];
    valuesToUpdate: unknown[];
  };

  if (!autorId || !Array.isArray(keysToUpdate) || keysToUpdate.length === 0) {
    return Response.json({ code: 'BAD_REQUEST', msg: 'Missing autorId or no fields to update' }, { status: 400 });
  }

  const invalidKeys = keysToUpdate.filter((k) => !(EDITABLE_FIELDS as readonly string[]).includes(k));
  if (invalidKeys.length) {
    return Response.json({ code: 'BAD_REQUEST', msg: `Invalid fields: ${invalidKeys.join(', ')}` }, { status: 400 });
  }

  const setClauses = keysToUpdate.map((k) => `${k} = ?`).join(', ');
  dbRun(`UPDATE autor SET ${setClauses} WHERE ID_AUTOR = ?`, [...valuesToUpdate, autorId]);
  logAdmin('UPDATE', 'autor', autorId, { keysToUpdate, valuesToUpdate });
  revalidatePath('/autor', 'layout');
  return Response.json({ code: 'UPDATED', msg: 'Autor updated' });
}
