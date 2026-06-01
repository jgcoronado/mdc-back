import type { Metadata } from 'next';
import Link from 'next/link';
import { searchAutores } from '@/lib/api';
import { buildDetailPath } from '@/lib/slugify';

export const metadata: Metadata = {
  title: 'Resultados de compositores — Marchas de Cristo',
  robots: { index: false },
};

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export default async function AutorListPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const query = new URLSearchParams(sp as Record<string, string>).toString();

  if (!query) {
    return <div className="divider py-10 my-0">Introduce criterios de búsqueda.</div>;
  }

  const result = await searchAutores(query);

  return (
    <>
      {result.rowsReturned < 1 ? (
        <div className="divider py-10 my-0">Lo sentimos, no se ha encontrado ningún compositor.</div>
      ) : result.rowsReturned === 1 ? (
        <div className="divider py-10 my-0">Se ha encontrado un autor:</div>
      ) : (
        <div className="divider py-10 my-0">Se han encontrado {result.rowsReturned} autores:</div>
      )}
      {result.rowsReturned > 0 && (
        <div className="tableList">
          <table className="table table-zebra">
            <thead className="bg-neutral-content text-neutral">
              <tr>
                <td>Nombre</td>
                <td>Marchas compuestas</td>
              </tr>
            </thead>
            <tbody>
              {result.data.map((autor) => (
                <tr key={autor.ID_AUTOR}>
                  <td>
                    <Link
                      href={buildDetailPath('autor', autor.ID_AUTOR, autor.NOMBRE_COMPLETO)}
                      className="hover:underline"
                    >
                      {autor.NOMBRE_COMPLETO}
                    </Link>
                  </td>
                  <td>{autor.MARCHAS}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
