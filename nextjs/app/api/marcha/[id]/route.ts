import 'server-only';
import { type NextRequest } from 'next/server';
import { verifySession, getTokenFromRequest } from '@/lib/auth-session';
import { fetchMarcha } from '@/lib/api';

export async function GET(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  if (!verifySession(getTokenFromRequest(req))) {
    return Response.json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' }, { status: 401 });
  }
  const { id } = await params;
  const marcha = await fetchMarcha(id);
  if (!marcha) return Response.json({ code: 'NOT_FOUND', msg: 'Marcha not found' }, { status: 404 });
  return Response.json(marcha);
}
