import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import Link from 'next/link';
import { fetchDisco } from '@/lib/api';
import { extractId, buildDetailPath } from '@/lib/slugify';

export const revalidate = 3600;

type Params = Promise<{ slugAndId: string }>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) return {};
  const data = await fetchDisco(id).catch(() => null);
  if (!data) return {};
  return {
    title: `${data.NOMBRE_CD} — Marchas de Cristo`,
    description: `Disco de música procesional "${data.NOMBRE_CD}" de ${data.BANDA}. Contiene ${data.marchasLength} marchas.`,
  };
}

export default async function DiscoDetailPage({ params }: { params: Params }) {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) notFound();

  const data = await fetchDisco(id!).catch(() => null);
  if (!data) notFound();

  const canonical = buildDetailPath('disco', data.ID_DISCO, data.NOMBRE_CD);
  if (`/disco/${slugAndId}` !== canonical) {
    redirect(canonical);
  }

  const coverSrc = `/cover/${data.ID_DISCO}.png`;
  const bandaPath = buildDetailPath('banda', data.ID_BANDA, data.BANDA);

  return (
    <div className="grid place-items-center">
      <div className="grid place-items-center xl:join md:join join-vertically">
        <figure className="m-1">
          <img
            className="shadow-sm"
            src={coverSrc}
            alt={`Portada del disco '${data.NOMBRE_CD}'`}
            onContextMenu={(e) => e.preventDefault()}
          />
        </figure>
        <div className="justify-items-center">
          <div className="headDetail">{data.NOMBRE_CD}</div>
          <div className="tableList">
            <table className="table table-zebra">
              <tbody>
                <tr>
                  <th>Fecha</th>
                  <td>{data.FECHA_CD}</td>
                </tr>
                <tr>
                  <th>Banda</th>
                  <td>
                    <Link href={bandaPath} className="hover:underline">
                      {data.BANDA}
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="divider py-10 my-0">Este disco contiene {data.marchasLength} marchas:</div>
      <div className="tableList">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral">
            <tr>
              {data.DISCOS > 1 && <td>Disco</td>}
              <td>#</td>
              <td>Marcha</td>
              <td>Autor</td>
              <td>Fecha</td>
            </tr>
          </thead>
          <tbody>
            {data.marchas.map((m, i) => (
              <tr key={`${m.ID_MARCHA}-${i}`}>
                {data.DISCOS > 1 && <td>{m.N_DISCO}</td>}
                <td>{m.NUMEROMARCHA}</td>
                <td>
                  <Link href={buildDetailPath('marcha', m.ID_MARCHA, m.TITULO)} className="hover:underline">
                    {m.TITULO}
                  </Link>
                </td>
                <td>
                  {m.AUTOR.map((a) => (
                    <div key={a.autorId}>
                      <Link href={buildDetailPath('autor', a.autorId, a.nombre)} className="hover:underline">
                        {a.nombre}
                      </Link>
                    </div>
                  ))}
                </td>
                <td>{m.FECHA}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
