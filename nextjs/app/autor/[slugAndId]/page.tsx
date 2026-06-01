import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import Link from 'next/link';
import { fetchAutor } from '@/lib/api';
import { extractId, buildDetailPath } from '@/lib/slugify';

export const revalidate = 3600;

type Params = Promise<{ slugAndId: string }>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) return {};
  const data = await fetchAutor(id).catch(() => null);
  if (!data) return {};
  const fullName = `${data.NOMBRE} ${data.APELLIDOS}`.trim();
  return {
    title: `${fullName} — Marchas de Cristo`,
    description: `Compositor de música procesional. Ha compuesto ${data.marchasLength} marchas.${data.LUGAR_NAC ? ` Natural de ${data.LUGAR_NAC}.` : ''}`,
  };
}

export default async function AutorDetailPage({ params }: { params: Params }) {
  const { slugAndId } = await params;
  const id = extractId(slugAndId);
  if (!id) notFound();

  const data = await fetchAutor(id!).catch(() => null);
  if (!data) notFound();

  const fullName = `${data.NOMBRE} ${data.APELLIDOS}`.trim();
  const canonical = buildDetailPath('autor', data.ID_AUTOR, fullName);
  if (`/autor/${slugAndId}` !== canonical) {
    redirect(canonical);
  }

  return (
    <div>
      <div className="headDetail">{data.NOMBRE} {data.APELLIDOS}</div>
      <div className="tableList">
        <table className="table table-zebra">
          <tbody>
            {data.F_NAC && (
              <tr>
                <th>Fecha de nacimiento</th>
                <td>{data.F_NAC}</td>
              </tr>
            )}
            {data.LUGAR_NAC && (
              <tr>
                <th>Lugar de nacimiento</th>
                <td>{data.LUGAR_NAC}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="divider py-10 my-0">Ha compuesto {data.marchasLength} marchas:</div>
      <div className="tableList">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral">
            <tr>
              <td>Marcha</td>
              <td>Fecha</td>
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
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
