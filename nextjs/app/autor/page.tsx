import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Buscador de compositores — Marchas de Cristo',
  description: 'Busca compositores de música procesional por nombre y apellidos.',
};

export default function AutorSearchPage() {
  return (
    <form
      action="/autor/search"
      method="GET"
      className="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    >
      <legend className="fieldset-legend">Buscador de compositores</legend>

      <label className="label">Nombre</label>
      <input
        className="input w-full text-base"
        type="text"
        name="nombre"
        placeholder="Ejemplo: Manuel Rodríguez Ruiz"
      />

      <button className="btn btn-neutral mt-4" type="submit">Buscar</button>
    </form>
  );
}
