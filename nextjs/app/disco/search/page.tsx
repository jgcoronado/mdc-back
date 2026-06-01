import type { Metadata } from 'next';
import { searchDiscos } from '@/lib/api';
import CdList from '@/components/CdList';

export const metadata: Metadata = {
  title: 'Resultados de discos — Marchas de Cristo',
  robots: { index: false },
};

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export default async function DiscoListPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const query = new URLSearchParams(sp as Record<string, string>).toString();

  if (!query) {
    return <div className="divider py-10 my-0">Introduce criterios de búsqueda.</div>;
  }

  const result = await searchDiscos(query);

  return (
    <>
      {result.rowsReturned < 1 ? (
        <div className="divider py-10 my-0">Lo sentimos, no se ha encontrado ningún disco.</div>
      ) : result.rowsReturned === 1 ? (
        <div className="divider py-10 my-0">Se ha encontrado un disco:</div>
      ) : (
        <div className="divider py-10 my-0">Se han encontrado {result.rowsReturned} discos:</div>
      )}
      {result.data.map((d) => (
        <CdList key={d.ID_DISCO} disco={d} />
      ))}
    </>
  );
}
