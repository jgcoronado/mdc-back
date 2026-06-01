import type { Metadata } from 'next';
import Link from 'next/link';
import { searchMarchas } from '@/lib/api';
import { buildDetailPath } from '@/lib/slugify';

export const metadata: Metadata = {
  title: 'Resultados de marchas — Marchas de Cristo',
  robots: { index: false },
};

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export default async function MarchaListPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const query = new URLSearchParams(sp as Record<string, string>).toString();

  if (!query) {
    return (
      <div className="divider py-10 my-0">Introduce criterios de búsqueda.</div>
    );
  }

  const result = await searchMarchas(query);

  return (
    <>
      {result.rowsReturned < 1 ? (
        <div className="divider py-10 my-0">Lo sentimos, no se ha encontrado ninguna marcha.</div>
      ) : result.rowsReturned === 1 ? (
        <div className="divider py-10 my-0">Se ha encontrado una marcha:</div>
      ) : (
        <div className="divider py-10 my-0">Se han encontrado {result.rowsReturned} marchas:</div>
      )}
      {result.rowsReturned > 0 && (
        <div className="tableList">
          <table className="table table-zebra">
            <thead className="bg-neutral-content text-neutral">
              <tr>
                <td>Título</td>
                <td>Compositor/es</td>
                <td>Fecha</td>
              </tr>
            </thead>
            <tbody>
              {result.data.map((marcha) => (
                <tr key={marcha.ID_MARCHA}>
                  <td>
                    <Link
                      href={buildDetailPath('marcha', marcha.ID_MARCHA, marcha.TITULO)}
                      className="hover:underline"
                    >
                      {marcha.TITULO}
                    </Link>
                  </td>
                  <td>
                    {marcha.AUTOR.map((a) => (
                      <div key={a.autorId}>
                        <Link
                          href={buildDetailPath('autor', a.autorId, a.nombre)}
                          className="hover:underline"
                        >
                          {a.nombre}
                        </Link>
                      </div>
                    ))}
                  </td>
                  <td>{marcha.FECHA}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
