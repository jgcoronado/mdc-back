import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import Link from 'next/link';
import CdList from '@/components/CdList';
import { fetchMarcha } from '@/lib/api';
import { extractId, buildDetailPath } from '@/lib/slugify';

export const revalidate = 3600;

type Params = Promise<{ slugAndId: string }>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) return {};
  const data = await fetchMarcha(id).catch(() => null);
  if (!data) return {};
  const autores = data.AUTOR.map((a) => a.nombre).join(', ');
  return {
    title: `${data.TITULO} — Marchas de Cristo`,
    description: `Marcha procesional "${data.TITULO}" compuesta por ${autores}.${data.DEDICATORIA ? ` Dedicada a ${data.DEDICATORIA}.` : ''}`,
  };
}

export default async function MarchaDetailPage({ params }: { params: Params }) {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) notFound();

  const data = await fetchMarcha(id!).catch(() => null);
  if (!data) notFound();

  // Redirect legacy /marcha/123 to canonical /marcha/slug-123
  const canonical = buildDetailPath('marcha', data.ID_MARCHA, data.TITULO);
  if (`/marcha/${slugAndId}` !== canonical) {
    redirect(canonical);
  }

  function getDedicatoria(ded: string, loc: string) {
    const isDed = ded && ded !== '0';
    const isLoc = loc && loc !== '0';
    if (isDed && isLoc) return `${ded} (${loc})`;
    if (isDed) return ded;
    return '';
  }

  return (
    <div>
      <div className="headDetail">{data.TITULO}</div>
      <div className="tableList">
        <table className="table table-zebra">
          <tbody>
            {data.FECHA && (
              <tr>
                <th>Fecha</th>
                <td>{data.FECHA}</td>
              </tr>
            )}
            <tr>
              <th>Autor</th>
              <td>
                {data.AUTOR.map((a) => (
                  <div key={a.autorId}>
                    <Link href={buildDetailPath('autor', a.autorId, a.nombre)} className="hover:underline">
                      {a.nombre}
                    </Link>
                  </div>
                ))}
              </td>
            </tr>
            {data.DEDICATORIA && (
              <tr>
                <th>Dedicatoria</th>
                <td>{getDedicatoria(data.DEDICATORIA, data.LOCALIDAD)}</td>
              </tr>
            )}
            {data.BANDA_ESTRENO && (
              <tr>
                <th>Estrenada por</th>
                <td>
                  <Link href={buildDetailPath('banda', data.BANDA_ESTRENO, data.BANDA)} className="hover:underline">
                    {data.BANDA}
                  </Link>
                </td>
              </tr>
            )}
            {data.DETALLES_MARCHA && (
              <tr>
                <th>Información adicional</th>
                <td>{data.DETALLES_MARCHA}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {data.discosLength !== 0 ? (
        <div className="divider py-10 my-0">Esta marcha se ha grabado en {data.discosLength} discos:</div>
      ) : (
        <div className="divider py-10 my-0">Esta marcha aún no ha sido grabada en disco.</div>
      )}

      {data.discos.map((d) => (
        <CdList key={d.ID_DISCO} disco={d} />
      ))}
    </div>
  );
}
