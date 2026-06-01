import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Buscador de discos — Marchas de Cristo',
  description: 'Busca discos de música procesional de Semana Santa por nombre.',
};

export default function DiscoSearchPage() {
  return (
    <form
      action="/disco/search"
      method="GET"
      className="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    >
      <legend className="fieldset-legend">Buscador de discos de música procesional</legend>

      <label className="label">Nombre</label>
      <input
        className="input w-full text-base"
        type="text"
        name="nombre"
        placeholder="Ejemplo: Fons Vitae"
      />

      <button className="btn btn-neutral mt-4" type="submit">Buscar</button>
    </form>
  );
}
