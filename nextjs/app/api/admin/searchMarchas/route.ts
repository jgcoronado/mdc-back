import 'server-only';
import { type NextRequest } from 'next/server';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';
import { searchMarchas } from '@/lib/api';

export async function GET(req: NextRequest) {
  if (!verifySession(getTokenFromRequest(req))) {
    return Response.json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' }, { status: 401 });
  }
  const titulo = req.nextUrl.searchParams.get('titulo') ?? '';
  if (!titulo.trim()) return Response.json({ rowsReturned: 0, data: [] });
  const result = await searchMarchas(`titulo=${encodeURIComponent(titulo)}`);
  return Response.json({ rowsReturned: result.rowsReturned, data: result.data.slice(0, 15) });
}
