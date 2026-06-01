import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Buscador de marchas procesionales — Marchas de Cristo',
  description: 'Busca marchas procesionales por título, fecha, dedicatoria, localidad y provincia.',
};

export default function MarchaSearchPage() {
  return (
    <form
      action="/marcha/search"
      method="GET"
      className="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    >
      <legend className="fieldset-legend">Buscador de marchas procesionales</legend>

      <label className="label">Título</label>
      <input className="input w-full text-base" type="text" name="titulo" placeholder="Ejemplo: Consuelo Gitano" />

      <label className="label">Fecha</label>
      <div className="join">
        <input className="input text-base" type="text" name="fechaDesde" maxLength={4} size={4} placeholder="Desde: 1993" />
        <input className="input text-base" type="text" name="fechaHasta" maxLength={4} size={4} placeholder="Hasta: 2021" />
      </div>

      <label className="label">Dedicatoria</label>
      <input className="input w-full text-base" type="text" name="dedicatoria" placeholder="Ejemplo: Hdad Cristo de la Corona" />

      <label className="label">Localidad</label>
      <input className="input w-full text-base" type="text" name="localidad" placeholder="Ejemplo: Osuna" />

      <label className="label">Provincia</label>
      <input className="input w-full text-base" type="text" name="provincia" placeholder="Ejemplo: Almería" />

      <button className="btn btn-neutral mt-4" type="submit">Buscar</button>
    </form>
  );
}
