import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import Link from 'next/link';
import CdList from '@/components/CdList';
import Timeline from '@/components/Timeline';
import { fetchBanda } from '@/lib/api';
import { extractId, buildDetailPath } from '@/lib/slugify';
import { generateBandaSchema, generateBreadcrumbs } from '@/lib/schema';

export const revalidate = 3600;

type Params = Promise<{ slugAndId: string }>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) return {};
  const data = await fetchBanda(id).catch(() => null);
  if (!data) return {};
  const url = `${process.env.SITE_URL || 'https://marchasdecristo.com'}${buildDetailPath('banda', data.ID_BANDA, data.NOMBRE_COMPLETO)}`;
  return {
    title: `${data.NOMBRE_BREVE} — Marchas de Cristo`,
    description: `${data.NOMBRE_COMPLETO}, banda de ${data.LOCALIDAD}. Ha grabado ${data.discosLength} discos y estrenado ${data.marchasLength} marchas.`,
    openGraph: {
      type: 'music.playlist',
      title: data.NOMBRE_BREVE,
      description: `Banda de música procesional de ${data.LOCALIDAD}`,
      url,
    },
  };
}

export default async function BandaDetailPage({ params }: { params: Params }) {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) notFound();

  const data = await fetchBanda(id!).catch(() => null);
  if (!data) notFound();

  const canonical = buildDetailPath('banda', data.ID_BANDA, data.NOMBRE_COMPLETO);
  if (`/banda/${slugAndId}` !== canonical) {
    redirect(canonical);
  }

  const url = `${process.env.SITE_URL || 'https://marchasdecristo.com'}${canonical}`;
  const bandaSchema = generateBandaSchema(data, url);
  const breadcrumbsSchema = generateBreadcrumbs([
    { name: 'Inicio', url: process.env.SITE_URL || 'https://marchasdecristo.com' },
    { name: 'Bandas', url: `${process.env.SITE_URL || 'https://marchasdecristo.com'}/banda` },
    { name: data.NOMBRE_BREVE, url },
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(bandaSchema) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(breadcrumbsSchema) }} />
    <div>
      <div className="headDetail">{data.NOMBRE_BREVE}</div>
      <div className="tableList">
        <table className="table table-zebra">
          <tbody>
            <tr>
              <th>Nombre completo</th>
              <td>{data.NOMBRE_COMPLETO}</td>
            </tr>
            <tr>
              <th>Localidad</th>
              <td>{data.LOCALIDAD}</td>
            </tr>
            {data.FECHA_FUND > 1800 && (
              <tr>
                <th>Fecha de fundación</th>
                <td>{data.FECHA_FUND}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="grid place-items-center">
        <Timeline apiData={data} />
      </div>

      {data.discosLength > 0 && (
        <div className="divider py-10 my-0">Esta banda ha grabado {data.discosLength} discos:</div>
      )}
      {data.discos.map((d) => (
        <CdList key={d.ID_DISCO} disco={d} />
      ))}

      {data.marchasLength > 0 && (
        <div className="divider py-10 my-0">Esta banda ha estrenado {data.marchasLength} marchas:</div>
      )}
      <div className="tableList">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral">
            <tr>
              <td>Marcha</td>
              <td>Fecha</td>
              <td>Compositor/es</td>
            </tr>
          </thead>
          <tbody>
            {data.marchas.map((m) => (
              <tr key={m.ID_MARCHA}>
                <td>
                  <Link href={buildDetailPath('marcha', m.ID_MARCHA, m.TITULO)} className="hover:underline">
                    {m.TITULO}
                  </Link>
                </td>
                <td>{m.FECHA}</td>
                <td>
                  {m.AUTOR.map((a) => (
                    <div key={a.autorId}>
                      <Link href={buildDetailPath('autor', a.autorId, a.nombre)} className="hover:underline">
                        {a.nombre}
                      </Link>
                    </div>
                  ))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <br />
      </div>
    </>
  );
}
