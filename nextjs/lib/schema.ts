import type { MarchaDetail, AutorDetail, BandaDetail, DiscoDetail } from './api';

const BASE_URL = process.env.SITE_URL || 'https://marchasdecristo.com';

// ── Marcha (MusicComposition) ────────────────────────────────────────────────
export function generateMarchaSchema(data: MarchaDetail, url: string) {
  const autores = data.AUTOR.map((a) => ({
    '@type': 'Person' as const,
    name: a.nombre,
    url: `${BASE_URL}/autor/${slugify(a.nombre)}-${a.autorId}`,
  }));

  const bandaEstreno = data.BANDA_ESTRENO
    ? {
        '@type': 'MusicGroup' as const,
        name: data.BANDA,
        url: `${BASE_URL}/banda/${slugify(data.BANDA)}-${data.BANDA_ESTRENO}`,
      }
    : undefined;

  return {
    '@context': 'https://schema.org',
    '@type': 'MusicComposition',
    name: data.TITULO,
    url,
    creator: autores.length > 0 ? autores : undefined,
    dateCreated: data.FECHA ? String(data.FECHA) : undefined,
    description: `Marcha procesional${data.DEDICATORIA ? ` dedicada a ${data.DEDICATORIA}` : ''}.`,
    performanceLocation: bandaEstreno,
    ...(data.discosLength > 0 && {
      recordedAs: data.discos.map((d) => ({
        '@type': 'MusicRecording',
        name: d.NOMBRE_CD,
        byArtist: {
          '@type': 'MusicGroup',
          name: d.BANDA,
        },
      })),
    }),
  };
}

// ── Autor (Person) ──────────────────────────────────────────────────────────
export function generateAutorSchema(data: AutorDetail, url: string) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Person',
    name: `${data.NOMBRE} ${data.APELLIDOS}`.trim(),
    url,
    birthDate: data.F_NAC ? formatDate(data.F_NAC) : undefined,
    birthPlace: data.LUGAR_NAC
      ? {
          '@type': 'Place',
          name: data.LUGAR_NAC,
        }
      : undefined,
    description: `Compositor de música procesional. Ha compuesto ${data.marchasLength} marchas.`,
    ...(data.BIO && { knowsAbout: data.BIO }),
  };
}

// ── Banda (MusicGroup) ──────────────────────────────────────────────────────
export function generateBandaSchema(data: BandaDetail, url: string) {
  return {
    '@context': 'https://schema.org',
    '@type': 'MusicGroup',
    name: data.NOMBRE_COMPLETO || data.NOMBRE_BREVE,
    url,
    foundingDate: data.FECHA_FUND ? String(data.FECHA_FUND) : undefined,
    dissolutionDate: data.FECHA_EXT ? String(data.FECHA_EXT) : undefined,
    location: {
      '@type': 'Place',
      name: data.LOCALIDAD,
    },
    description: `Banda de música procesional. Ha estrenado ${data.marchasLength} marchas y grabado ${data.discosLength} discos.`,
  };
}

// ── Disco (MusicAlbum) ──────────────────────────────────────────────────────
export function generateDiscoSchema(data: DiscoDetail, url: string) {
  return {
    '@context': 'https://schema.org',
    '@type': 'MusicAlbum',
    name: data.NOMBRE_CD,
    url,
    byArtist: {
      '@type': 'MusicGroup',
      name: data.BANDA,
    },
    datePublished: data.FECHA_CD ? String(data.FECHA_CD) : undefined,
    description: `Disco que contiene ${data.marchasLength} marchas de música procesional.`,
    ...(data.marchasLength > 0 && {
      tracks: {
        '@type': 'ItemList',
        itemListElement: data.marchas.map((m, idx) => ({
          '@type': 'MusicRecording',
          position: idx + 1,
          name: m.TITULO,
          byArtist: m.AUTOR.map((a) => ({
            '@type': 'Person',
            name: a.nombre,
          })),
        })),
      },
    }),
  };
}

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
export function generateBreadcrumbs(items: Array<{ name: string; url: string }>) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, idx) => ({
      '@type': 'ListItem',
      position: idx + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

function formatDate(dateStr: string | number): string | undefined {
  if (!dateStr) return undefined;
  const str = String(dateStr);
  // Try ISO format first
  if (str.includes('-')) return str;
  // Try YYYY format
  if (str.length === 4) return str;
  // Fallback: return as-is if it looks like a date
  return /^\d{4}/.test(str) ? str : undefined;
}
