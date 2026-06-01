import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Buscador de bandas — Marchas de Cristo',
  description: 'Busca bandas de cornetas y tambores y agrupaciones musicales por nombre, localidad y provincia.',
};

export default function BandaSearchPage() {
  return (
    <form
      action="/banda/search"
      method="GET"
      className="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    >
      <legend className="fieldset-legend">Buscador de bandas</legend>

      <label className="label">Nombre</label>
      <input className="input w-full text-base" type="text" name="titulo" placeholder="Ejemplo: Sagrada Columna y Azotes" />

      <label className="label">Localidad</label>
      <input className="input w-full text-base" type="text" name="localidad" placeholder="Ejemplo: Palma del Río" />

      <label className="label">Provincia</label>
      <input className="input w-full text-base" type="text" name="provincia" placeholder="Ejemplo: Huelva" />

      <button className="btn btn-neutral mt-4" type="submit">Buscar</button>
    </form>
  );
}
