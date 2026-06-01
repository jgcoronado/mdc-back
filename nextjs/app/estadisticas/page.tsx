import type { Metadata } from 'next';
import Link from 'next/link';
import { fetchMasAutor, fetchMasDedica, fetchMasEstreno, fetchMasGrabada } from '@/lib/api';
import { buildDetailPath } from '@/lib/slugify';

export const revalidate = 1800;

export const metadata: Metadata = {
  title: 'Estadísticas de música procesional — Marchas de Cristo',
  description: 'Los compositores con más marchas, bandas con más estrenos y marchas más grabadas.',
};

export default async function EstadisticasPage() {
  const [masAutor, masDedica, masEstreno, masGrabada] = await Promise.all([
    fetchMasAutor().catch(() => []),
    fetchMasDedica().catch(() => []),
    fetchMasEstreno().catch(() => []),
    fetchMasGrabada().catch(() => []),
  ]);

  return (
    <>
      <details className="collapse collapse-arrow bg-base-100 border border-base-300">
        <summary className="collapse-title font-semibold">Autores que más marchas han compuesto</summary>
        <div className="collapse-content text-sm">
          <div className="tableList">
            <table className="table table-zebra">
              <thead className="bg-neutral-content text-neutral">
                <tr><td>Nombre</td><td>Marchas compuestas</td></tr>
              </thead>
              <tbody>
                {masAutor.map((a) => (
                  <tr key={a.ID_AUTOR}>
                    <td>
                      <Link href={buildDetailPath('autor', a.ID_AUTOR, a.AUTOR)} className="hover:underline">
                        {a.AUTOR}
                      </Link>
                    </td>
                    <td>{a.MARCHAS}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </details>

      <details className="collapse collapse-arrow bg-base-100 border border-base-300">
        <summary className="collapse-title font-semibold">Hermandades con más marchas dedicadas</summary>
        <div className="collapse-content text-sm">
          <div className="tableList">
            <table className="table table-zebra">
              <thead className="bg-neutral-content text-neutral">
                <tr><td>Nombre</td><td>Marchas dedicadas</td></tr>
              </thead>
              <tbody>
                {masDedica.map((d, i) => (
                  <tr key={i}>
                    <td>{d.LUGAR}</td>
                    <td>{d.CUENTA}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </details>

      <details className="collapse collapse-arrow bg-base-100 border border-base-300">
        <summary className="collapse-title font-semibold">Bandas que más marchas han estrenado</summary>
        <div className="collapse-content text-sm">
          <div className="tableList">
            <table className="table table-zebra">
              <thead className="bg-neutral-content text-neutral">
                <tr><td>Banda</td><td>Marchas estrenadas</td></tr>
              </thead>
              <tbody>
                {masEstreno.map((e) => (
                  <tr key={e.ID_BANDA}>
                    <td>
                      <Link href={buildDetailPath('banda', e.ID_BANDA, e.BANDA)} className="hover:underline">
                        {e.BANDA}
                      </Link>
                    </td>
                    <td>{e.MARCHAS}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </details>

      <details className="collapse collapse-arrow bg-base-100 border border-base-300">
        <summary className="collapse-title font-semibold">Marchas que más veces han sido grabadas</summary>
        <div className="collapse-content text-sm">
          <div className="tableList">
            <table className="table table-zebra">
              <thead className="bg-neutral-content text-neutral">
                <tr><td>Marcha</td><td>Autor/es</td><td>Grabaciones</td></tr>
              </thead>
              <tbody>
                {masGrabada.map((g) => (
                  <tr key={g.ID_MARCHA}>
                    <td>
                      <Link href={buildDetailPath('marcha', g.ID_MARCHA, g.TITULO)} className="hover:underline">
                        {g.TITULO}
                      </Link>
                    </td>
                    <td>
                      {g.AUTOR.map((a) => (
                        <div key={a.autorId}>
                          <Link href={buildDetailPath('autor', a.autorId, a.nombre)} className="hover:underline">
                            {a.nombre}
                          </Link>
                        </div>
                      ))}
                    </td>
                    <td>{g.GRABACIONES}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </details>
    </>
  );
}
