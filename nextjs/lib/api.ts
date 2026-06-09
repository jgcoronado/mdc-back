import 'server-only';
import { dbAll, dbRun, formatAutor } from './db';

// ── Interfaces ────────────────────────────────────────────────────────────────

export interface AutorRef { autorId: string; nombre: string; }

export interface MarchaDetail {
  ID_MARCHA: number; TITULO: string; FECHA: string | number;
  DEDICATORIA: string; LOCALIDAD: string; PROVINCIA: string; AUDIO: string;
  AUTOR: AutorRef[]; BANDA_ESTRENO: number; BANDA: string;
  DETALLES_MARCHA: string; discosLength: number; discos: DiscoRef[];
}

export interface DiscoRef {
  ID_DISCO: number; NOMBRE_CD: string; FECHA_CD: number; ID_BANDA: number; BANDA: string;
}

export interface AutorDetail {
  ID_AUTOR: number; NOMBRE: string; APELLIDOS: string; NOMBRE_ART: string;
  F_NAC: string; LUGAR_NAC: string; F_DEF: string; BIO: string;
  marchasLength: number; marchas: MarchaRef[];
}

export interface MarchaRef {
  ID_MARCHA: number; TITULO: string; FECHA: string | number; DEDICATORIA: string;
}

export interface BandaDetail {
  ID_BANDA: number; NOMBRE_BREVE: string; NOMBRE_COMPLETO: string;
  LOCALIDAD: string; FECHA_FUND: number; FECHA_EXT: number | null;
  timeline: BandaTimelineItem[]; discosLength: number; discos: DiscoListItem[];
  marchasLength: number; marchas: BandaMarchaItem[];
}

export interface BandaTimelineItem {
  ID_BANDA: number; NOMBRE_BREVE: string; FECHA_FUND: number; FECHA_EXT: number | null;
}

export interface DiscoListItem {
  ID_DISCO: number; NOMBRE_CD: string; FECHA_CD: number; PISTAS: number; DISCOS: number;
}

export interface BandaMarchaItem {
  ID_MARCHA: number; TITULO: string; FECHA: string | number; DEDICATORIA: string; AUTOR: AutorRef[];
}

export interface DiscoDetail {
  ID_DISCO: number; NOMBRE_CD: string; FECHA_CD: number; ID_BANDA: number;
  BANDA: string; DISCOS: number; marchasLength: number; marchas: DiscoMarchaItem[];
}

export interface DiscoMarchaItem {
  N_DISCO: number; NUMEROMARCHA: number; ID_MARCHA: number;
  TITULO: string; FECHA: string | number; AUTOR: AutorRef[]; ENLAZADA: number;
}

export interface SearchResult<T> { rowsReturned: number; data: T[]; }

export interface MarchaRow {
  ID_MARCHA: number; TITULO: string; DEDICATORIA: string;
  LOCALIDAD: string; FECHA: string | number; AUTOR: AutorRef[]; GRABADA: number;
}

export interface AutorRow {
  ID_AUTOR: number; NOMBRE: string; APELLIDOS: string; NOMBRE_COMPLETO: string; MARCHAS: number;
}

export interface BandaRow {
  ID_BANDA: number; NOMBRE_BREVE: string; NOMBRE_COMPLETO: string;
  PROVINCIA: string; LOCALIDAD: string; FECHA_FUND: number; FECHA_EXT: number | null;
}

export interface DiscoRow {
  ID_DISCO: number; NOMBRE_CD: string; FECHA_CD: number; ID_BANDA: number; BANDA: string;
}

export interface StatsAutorRow { ID_AUTOR: number; AUTOR: string; MARCHAS: number; }
export interface StatsDedicaRow { LUGAR: string; CUENTA: number; }
export interface StatsEstrenoRow { ID_BANDA: number; BANDA: string; MARCHAS: number; }
export interface StatsGrabadaRow { ID_MARCHA: number; TITULO: string; AUTOR: AutorRef[]; GRABACIONES: number; }
export interface EstadoRow { MARCHAS: number; AUTORES: number; BANDAS: number; DISCOS: number; }

// ── Helpers ───────────────────────────────────────────────────────────────────

const buildFtsQuery = (raw: string): string | null => {
  const cleaned = raw.replace(/[^\p{L}\p{N}\s]/gu, ' ').trim();
  if (!cleaned) return null;
  return cleaned.split(/\s+/).map((t) => `"${t}"`).join(' ');
};

const normalizeFecha = <T extends { FECHA?: unknown }>(row: T): T => {
  if (row.FECHA === null || row.FECHA === '') row.FECHA = 's/f';
  return row;
};

// ── Marcha ────────────────────────────────────────────────────────────────────

export async function fetchMarcha(id: string): Promise<MarchaDetail | null> {
  const rows = dbAll<MarchaDetail>(`
    SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.PROVINCIA, m.AUDIO, m.FECHA,
      (SELECT json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))
       FROM (SELECT DISTINCT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = m.ID_MARCHA ORDER BY a.APELLIDOS)
      ) AS AUTOR,
      m.BANDA_ESTRENO, m.DETALLES_MARCHA,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM marcha m
    LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
    WHERE m.ID_MARCHA = ?
      AND EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)`, [id]);
  if (!rows.length) return null;
  const marcha = formatAutor(normalizeFecha(rows[0]));
  const discos = dbAll<DiscoRef>(`
    SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM disco d
    LEFT OUTER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
    LEFT OUTER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
    WHERE dm.IDMARCHA = ? ORDER BY d.FECHA_CD ASC`, [id]);
  return { ...marcha, discosLength: discos.length, discos };
}

export async function searchMarchas(query: string): Promise<SearchResult<MarchaRow>> {
  const params = new URLSearchParams(query);
  const conditions: string[] = [];
  const values: unknown[] = [];
  const titulo = params.get('titulo') ?? '';
  const fts = titulo ? buildFtsQuery(titulo) : null;
  if (fts) { conditions.push(`m.ID_MARCHA IN (SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH ?)`); values.push(fts); }
  const { fechaDesde, fechaHasta, dedicatoria, localidad, provincia } = Object.fromEntries(params);
  if (fechaDesde) { conditions.push(`m.FECHA >= ?`); values.push(fechaDesde); }
  if (fechaHasta) { conditions.push(`m.FECHA <= ?`); values.push(fechaHasta); }
  if (dedicatoria) { conditions.push(`m.DEDICATORIA LIKE ?`); values.push(`%${dedicatoria}%`); }
  if (localidad) { conditions.push(`m.LOCALIDAD LIKE ?`); values.push(`%${localidad}%`); }
  if (provincia) { conditions.push(`m.PROVINCIA LIKE ?`); values.push(`%${provincia}%`); }
  const where = conditions.length ? conditions.join(' AND ') : '1=1';
  const rows = dbAll<MarchaRow>(`
    SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA,
      (SELECT json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))
       FROM (SELECT DISTINCT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = m.ID_MARCHA ORDER BY a.APELLIDOS)
      ) AS AUTOR,
      CASE WHEN EXISTS (SELECT 1 FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA) THEN 1 ELSE 0 END AS GRABADA
    FROM marcha m
    WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA) AND ${where}
    ORDER BY m.TITULO ASC`, values);
  rows.forEach((r) => { normalizeFecha(r); formatAutor(r); });
  return { rowsReturned: rows.length, data: rows };
}

// ── Autor ─────────────────────────────────────────────────────────────────────

export async function fetchAutor(id: string): Promise<AutorDetail | null> {
  const autores = dbAll<AutorDetail>(`SELECT * FROM autor WHERE ID_AUTOR = ?`, [id]);
  if (!autores.length) return null;
  const marchas = dbAll<MarchaRef>(`
    SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA
    FROM marcha m
    INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
    WHERE ma.ID_AUTOR = ? ORDER BY m.FECHA ASC`, [id]);
  marchas.forEach(normalizeFecha);
  return { ...autores[0], marchasLength: marchas.length, marchas };
}

export async function searchAutores(query: string): Promise<SearchResult<AutorRow>> {
  const params = new URLSearchParams(query);
  const nombre = params.get('nombre') ?? '';
  const fts = nombre ? buildFtsQuery(nombre) : null;
  const where = fts ? `a.ID_AUTOR IN (SELECT rowid FROM autor_fts WHERE autor_fts MATCH ?)` : '1=1';
  const values = fts ? [fts] : [];
  const rows = dbAll<AutorRow>(`
    SELECT a.*, (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO,
      (SELECT COUNT(ma.ID_MARCHA) FROM marcha_autor ma WHERE ma.ID_AUTOR = a.ID_AUTOR) AS MARCHAS
    FROM autor a WHERE ${where} ORDER BY a.APELLIDOS ASC`, values);
  return { rowsReturned: rows.length, data: rows };
}

// ── Banda ─────────────────────────────────────────────────────────────────────

export async function fetchBanda(id: string): Promise<BandaDetail | null> {
  const bandas = dbAll<BandaDetail>(`SELECT * FROM banda WHERE ID_BANDA = ?`, [id]);
  if (!bandas.length) return null;
  const banda = bandas[0];
  const { ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE } = banda;
  const timeline: BandaTimelineItem[] = [{ ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE }];
  const discos = dbAll<DiscoListItem>(`
    SELECT DISTINCT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD,
      (SELECT COUNT(m.ID_DM) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS PISTAS,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS
    FROM disco d WHERE d.BANDADISCO = ? ORDER BY d.FECHA_CD ASC`, [id]);
  const marchas = dbAll<BandaMarchaItem>(`
    SELECT m.TITULO, m.ID_MARCHA, m.DEDICATORIA, m.LOCALIDAD, m.FECHA,
      (SELECT json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))
       FROM (SELECT DISTINCT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             FROM marcha_autor am INNER JOIN autor a ON a.ID_AUTOR = am.ID_AUTOR
             WHERE am.ID_MARCHA = m.ID_MARCHA ORDER BY a.APELLIDOS)
      ) AS AUTOR
    FROM marcha m
    WHERE m.BANDA_ESTRENO = ?
      AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
    ORDER BY m.FECHA DESC, m.TITULO ASC`, [id]);
  marchas.forEach((r) => formatAutor(r));
  timeline.sort((a, b) => a.FECHA_FUND - b.FECHA_FUND);
  return { ...banda, timeline, discosLength: discos.length, discos, marchasLength: marchas.length, marchas };
}

export async function searchBandas(query: string): Promise<SearchResult<BandaRow>> {
  const params = new URLSearchParams(query);
  const conditions: string[] = [];
  const values: unknown[] = [];
  const { titulo, localidad, provincia } = Object.fromEntries(params);
  if (titulo) { conditions.push(`b.NOMBRE_COMPLETO LIKE ?`); values.push(`%${titulo}%`); }
  if (localidad) { conditions.push(`b.LOCALIDAD LIKE ?`); values.push(`%${localidad}%`); }
  if (provincia) { conditions.push(`b.PROVINCIA LIKE ?`); values.push(`%${provincia}%`); }
  const where = conditions.length ? conditions.join(' AND ') : '1=1';
  const rows = dbAll<BandaRow>(`
    SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.PROVINCIA,
      b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM banda b WHERE ${where}
    GROUP BY b.ID_BANDA ORDER BY b.NOMBRE_BREVE ASC`, values);
  return { rowsReturned: rows.length, data: rows };
}

// ── Disco ─────────────────────────────────────────────────────────────────────

export async function fetchDisco(id: string): Promise<DiscoDetail | null> {
  const discos = dbAll<DiscoDetail>(`
    SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, d.d_DETALLES, b.ID_BANDA,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM disco d LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
    WHERE d.ID_DISCO = ?`, [id]);
  if (!discos.length) return null;
  const marchas = dbAll<DiscoMarchaItem>(`
    SELECT dm.N_DISCO, dm.NUMEROMARCHA, m.ID_MARCHA, m.TITULO, m.FECHA,
      (SELECT json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))
       FROM (SELECT DISTINCT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             FROM marcha_autor am INNER JOIN autor a ON a.ID_AUTOR = am.ID_AUTOR
             WHERE am.ID_MARCHA = m.ID_MARCHA ORDER BY a.APELLIDOS)
      ) AS AUTOR,
      CASE WHEN dm.DM_ENLAZADA IS NULL THEN 0 ELSE 1 END AS ENLAZADA
    FROM disco d
    INNER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
    INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
    WHERE d.ID_DISCO = ?
      AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
    ORDER BY dm.N_DISCO ASC, dm.NUMEROMARCHA ASC, dm.DM_ENLAZADA ASC`, [id]);
  marchas.forEach((r) => { normalizeFecha(r); formatAutor(r); });
  return { ...discos[0], marchasLength: marchas.length, marchas };
}

export async function searchDiscos(query: string): Promise<SearchResult<DiscoRow>> {
  const params = new URLSearchParams(query);
  const nombre = params.get('nombre') ?? '';
  const where = nombre ? `d.NOMBRE_CD LIKE ?` : '1=1';
  const values = nombre ? [`%${nombre}%`] : [];
  const rows = dbAll<DiscoRow>(`
    SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM disco d LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
    WHERE ${where} ORDER BY d.FECHA_CD ASC`, values);
  return { rowsReturned: rows.length, data: rows };
}

// ── Stats ─────────────────────────────────────────────────────────────────────

export async function fetchEstado(): Promise<EstadoRow> {
  const rows = dbAll<EstadoRow>(`SELECT
    (SELECT COUNT(*) FROM marcha) AS MARCHAS,
    (SELECT COUNT(*) FROM autor)  AS AUTORES,
    (SELECT COUNT(*) FROM banda)  AS BANDAS,
    (SELECT COUNT(*) FROM disco)  AS DISCOS`);
  return rows[0];
}

export async function fetchMasAutor(): Promise<StatsAutorRow[]> {
  return dbAll<StatsAutorRow>(`
    SELECT a.ID_AUTOR, COUNT(m.ID_MARCHA) AS MARCHAS,
      (a.NOMBRE || ' ' || a.APELLIDOS) AS AUTOR
    FROM autor a
    INNER JOIN marcha_autor am ON am.ID_AUTOR = a.ID_AUTOR
    INNER JOIN marcha m ON m.ID_MARCHA = am.ID_MARCHA
    GROUP BY a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
    ORDER BY MARCHAS DESC LIMIT 10`);
}

export async function fetchMasDedica(): Promise<StatsDedicaRow[]> {
  return dbAll<StatsDedicaRow>(`
    SELECT COUNT(DEDICATORIA) AS CUENTA,
      (DEDICATORIA || ' (' || LOCALIDAD || ')') AS LUGAR
    FROM marcha WHERE DEDICATORIA LIKE '%Hdad%' GROUP BY LUGAR
    HAVING CUENTA >= 15 ORDER BY CUENTA DESC`);
}

export async function fetchMasEstreno(): Promise<StatsEstrenoRow[]> {
  return dbAll<StatsEstrenoRow>(`
    SELECT b.ID_BANDA, COUNT(m.ID_MARCHA) AS MARCHAS,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
    FROM marcha m INNER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
    WHERE b.ID_BANDA != 0
    GROUP BY b.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
    ORDER BY MARCHAS DESC LIMIT 20`);
}

export async function fetchMasGrabada(): Promise<StatsGrabadaRow[]> {
  const rows = dbAll<StatsGrabadaRow>(`
    SELECT COUNT(dm.IDMARCHA) AS GRABACIONES, m.ID_MARCHA, m.TITULO,
      (SELECT json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))
       FROM (SELECT DISTINCT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = m.ID_MARCHA ORDER BY a.APELLIDOS)
      ) AS AUTOR
    FROM disco_marcha dm INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
    WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)
    GROUP BY dm.IDMARCHA, m.ID_MARCHA, m.TITULO
    ORDER BY GRABACIONES DESC LIMIT 20`);
  rows.forEach((r) => formatAutor(r));
  return rows;
}

// ── DB write helpers (used by admin Route Handlers) ───────────────────────────
export { dbAll, dbRun };
