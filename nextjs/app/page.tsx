import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Marchas de Cristo — Música procesional',
  description: 'Descubre marchas procesionales, compositores, bandas y discos de música de Semana Santa.',
};

export default function HomePage() {
  return (
    <div className="mockup-window border border-base-300 w-full">
      <div className="grid place-content-center border-t border-base-300 h-80 sm:max-w-sm md:min-w-xl md:max-w-3xl m-5 p-5">
        <div>
          <p className="text-center">¡Bienvenido a Marchas de Cristo!</p>
          <br />
          <p className="text-justify indent-8">
            Tras unos años de pausa, retomo este proyecto para conocer,
            poner en valor y disfrutar de la música procesional de nuestras formaciones
            de viento-metal y percusión, como son las bandas de cornetas y tambores y las
            agrupaciones musicales.
          </p>
          <p className="text-justify indent-8">
            Por desgracia, la base de datos se quedó parada a principios de 2020, confío en que pronto
            pueda actualizarse y llevar a día de hoy toda la web. Espero que te sea de utilidad.
          </p>
        </div>
        <div className="text-right text-base">
          Javier Guerra —{' '}
          <a href="https://x.com/JaviWarSVQ" className="hover:underline">@JaviWarSVQ</a>
        </div>
      </div>
    </div>
  );
}
