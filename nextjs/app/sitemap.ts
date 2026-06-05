import type { MetadataRoute } from 'next';
import { dbAll } from '@/lib/db';
import { buildDetailPath } from '@/lib/slugify';

export const revalidate = 3600;

export default function sitemap(): MetadataRoute.Sitemap {
  const base = (process.env.SITE_URL ?? 'https://marchasdecristo.com').replace(/\/$/, '');

  const statics: MetadataRoute.Sitemap = [
    { url: `${base}/`,            changeFrequency: 'daily',  priority: 1.0 },
    { url: `${base}/marcha`,      changeFrequency: 'weekly', priority: 0.9 },
    { url: `${base}/autor`,       changeFrequency: 'weekly', priority: 0.8 },
    { url: `${base}/banda`,       changeFrequency: 'weekly', priority: 0.8 },
    { url: `${base}/disco`,       changeFrequency: 'weekly', priority: 0.8 },
    { url: `${base}/estadisticas`,changeFrequency: 'weekly', priority: 0.7 },
  ];

  try {
    const marchas = dbAll<{ id: number; label: string }>(`SELECT ID_MARCHA AS id, TITULO AS label FROM marcha`);
    const autores = dbAll<{ id: number; label: string }>(`SELECT ID_AUTOR AS id, (NOMBRE || ' ' || APELLIDOS) AS label FROM autor`);
    const bandas  = dbAll<{ id: number; label: string }>(`SELECT ID_BANDA AS id, NOMBRE_BREVE AS label FROM banda`);
    const discos  = dbAll<{ id: number; label: string }>(`SELECT ID_DISCO AS id, NOMBRE_CD AS label FROM disco`);

    const toEntries = (entity: string, rows: typeof marchas, freq: MetadataRoute.Sitemap[0]['changeFrequency'], prio: number): MetadataRoute.Sitemap =>
      rows.map((r) => ({ url: `${base}${buildDetailPath(entity, r.id, r.label)}`, changeFrequency: freq, priority: prio }));

    return [
      ...statics,
      ...toEntries('marcha', marchas, 'monthly', 0.7),
      ...toEntries('autor',  autores, 'monthly', 0.6),
      ...toEntries('banda',  bandas,  'monthly', 0.6),
      ...toEntries('disco',  discos,  'monthly', 0.6),
    ];
  } catch {
    return statics;
  }
}
