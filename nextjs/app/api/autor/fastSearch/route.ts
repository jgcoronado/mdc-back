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
    SELECT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS, a.NOMBRE_ART,
      (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO
    FROM autor a
    WHERE a.APELLIDOS LIKE ? OR a.NOMBRE LIKE ? OR a.NOMBRE_ART LIKE ?
      OR (a.APELLIDOS || ' ' || a.NOMBRE) LIKE ?
      OR (a.NOMBRE || ' ' || a.APELLIDOS) LIKE ?
    ORDER BY (a.APELLIDOS LIKE ?) DESC, (a.NOMBRE LIKE ?) DESC,
      a.APELLIDOS ASC, a.NOMBRE ASC
    LIMIT 5`,
    [prefix, prefix, prefix, contains, contains, prefix, prefix]
  );
  return Response.json({ rowsReturned: rows.length, data: rows });
}
