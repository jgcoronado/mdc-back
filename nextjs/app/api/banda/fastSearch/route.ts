import 'server-only';
import { type NextRequest } from 'next/server';
import { dbAll } from '@/lib/db';

export async function GET(req: NextRequest) {
  const nombre = req.nextUrl.searchParams.get('nombre') ?? '';
  const trimmed = nombre.trim();
  if (trimmed.length < 1) return Response.json({ rowsReturned: 0, data: [] });

  const prefix = `${trimmed}%`;
  const contains = `%${trimmed}%`;
  const rows = dbAll(`
    SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.LOCALIDAD
    FROM banda b
    WHERE b.NOMBRE_BREVE LIKE ? OR b.NOMBRE_COMPLETO LIKE ?
      OR (b.NOMBRE_BREVE || ' ' || b.LOCALIDAD) LIKE ?
      OR (b.NOMBRE_COMPLETO || ' ' || b.LOCALIDAD) LIKE ?
    ORDER BY (b.NOMBRE_BREVE LIKE ?) DESC, (b.NOMBRE_COMPLETO LIKE ?) DESC,
      b.NOMBRE_BREVE ASC
    LIMIT 5`,
    [prefix, prefix, contains, contains, prefix, prefix]
  );
  return Response.json({ rowsReturned: rows.length, data: rows });
}
