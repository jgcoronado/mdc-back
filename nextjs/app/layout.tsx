import type { Metadata } from 'next';
import Link from 'next/link';
import './globals.css';
import { fetchEstado } from '@/lib/api';

export const metadata: Metadata = {
  title: 'Marchas de Cristo',
  description: 'Base de datos de música procesional: marchas, compositores, bandas y discos.',
  metadataBase: new URL(process.env.SITE_URL || 'https://marchasdecristo.com'),
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const estado = await fetchEstado().catch(() => null);

  return (
    <html lang="es">
      <body>
        <nav className="grid justify-items-center py-5">
          <Link href="/">
            <img className="h-10" alt="Marchas de Cristo" src="/banner_mdc.png" />
          </Link>
          <ul className="menu menu-horizontal rounded-box">
            <li><Link href="/">Home</Link></li>
            <li><Link href="/marcha">Marcha</Link></li>
            <li><Link href="/autor">Autor</Link></li>
            <li><Link href="/banda">Banda</Link></li>
            <li><Link href="/disco">Disco</Link></li>
            <li><Link href="/estadisticas">Estadísticas</Link></li>
          </ul>
        </nav>
        <main className="grid justify-items-center">
          {children}
        </main>
        <footer className="grid justify-items-center py-5">
          {estado && (
            <div className="p-5 text-center">
              Dimensión de la base de datos: {estado.MARCHAS} marchas, {estado.AUTORES} autores, {estado.BANDAS} bandas y {estado.DISCOS} discos.
            </div>
          )}
        </footer>
      </body>
    </html>
  );
}
