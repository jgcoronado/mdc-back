import type { Metadata } from 'next';
import Link from 'next/link';
import { searchBandas } from '@/lib/api';
import { buildDetailPath } from '@/lib/slugify';

export const metadata: Metadata = {
  title: 'Resultados de bandas — Marchas de Cristo',
  robots: { index: false },
};

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

function showDate(fund: number, ext: number | null): string {
  const funRes = fund > 1800 ? String(fund) : 's/f';
  const extRes = ext == null || ext === 0 ? '' : ` - ${ext}`;
  return `${funRes}${extRes}`;
}

function showLocalidad(loc: string, prov: string): string {
  const isLoc = loc && loc !== '0';
  const isProv = prov && prov !== '0' && prov != null;
  if (isLoc && isProv) return `${loc} (${prov})`;
  if (isLoc) return loc;
  return '';
}

export default async function BandaListPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const query = new URLSearchParams(sp as Record<string, string>).toString();

  if (!query) {
    return <div className="divider py-10 my-0">Introduce criterios de búsqueda.</div>;
  }

  const result = await searchBandas(query);

  return (
    <>
      {result.rowsReturned < 1 ? (
        <div className="divider py-10 my-0">Lo sentimos, no se ha encontrado ninguna banda.</div>
      ) : result.rowsReturned === 1 ? (
        <div className="divider py-10 my-0">Se ha encontrado una banda:</div>
      ) : (
        <div className="divider py-10 my-0">Se han encontrado {result.rowsReturned} bandas:</div>
      )}
      <div className="tableList">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral">
            <tr>
              <td>Nombre</td>
              <td>Localidad</td>
              <td>Fundación</td>
            </tr>
          </thead>
          <tbody>
            {result.data.map((b) => (
              <tr key={b.ID_BANDA}>
                <td>
                  <Link
                    href={buildDetailPath('banda', b.ID_BANDA, b.NOMBRE_COMPLETO)}
                    className="hover:underline"
                  >
                    {b.NOMBRE_COMPLETO}
                  </Link>
                </td>
                <td>{showLocalidad(b.LOCALIDAD, b.PROVINCIA)}</td>
                <td>{showDate(b.FECHA_FUND, b.FECHA_EXT)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
