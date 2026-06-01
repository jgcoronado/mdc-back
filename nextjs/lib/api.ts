export interface AutorRef {
  autorId: string;
  nombre: string;
}

export interface MarchaDetail {
  ID_MARCHA: number;
  TITULO: string;
  FECHA: string | number;
  DEDICATORIA: string;
  LOCALIDAD: string;
  AUDIO: string;
  AUTOR: AutorRef[];
  BANDA_ESTRENO: number;
  BANDA: string;
  DETALLES_MARCHA: string;
  discosLength: number;
  discos: DiscoRef[];
}

export interface DiscoRef {
  ID_DISCO: number;
  NOMBRE_CD: string;
  FECHA_CD: number;
  ID_BANDA: number;
  BANDA: string;
}

export interface AutorDetail {
  ID_AUTOR: number;
  NOMBRE: string;
  APELLIDOS: string;
  F_NAC: string;
  LUGAR_NAC: string;
  BIO: string;
  marchasLength: number;
  marchas: MarchaRef[];
}

export interface MarchaRef {
  ID_MARCHA: number;
  TITULO: string;
  FECHA: string | number;
  DEDICATORIA: string;
}

export interface BandaDetail {
  ID_BANDA: number;
  NOMBRE_BREVE: string;
  NOMBRE_COMPLETO: string;
  LOCALIDAD: string;
  FECHA_FUND: number;
  FECHA_EXT: number | null;
  timeline: BandaTimelineItem[];
  discosLength: number;
  discos: DiscoListItem[];
  marchasLength: number;
  marchas: BandaMarchaItem[];
}

export interface BandaTimelineItem {
  ID_BANDA: number;
  NOMBRE_BREVE: string;
  FECHA_FUND: number;
  FECHA_EXT: number | null;
}

export interface DiscoListItem {
  ID_DISCO: number;
  NOMBRE_CD: string;
  FECHA_CD: number;
  PISTAS: number;
  DISCOS: number;
}

export interface BandaMarchaItem {
  ID_MARCHA: number;
  TITULO: string;
  FECHA: string | number;
  DEDICATORIA: string;
  AUTOR: AutorRef[];
}

export interface DiscoDetail {
  ID_DISCO: number;
  NOMBRE_CD: string;
  FECHA_CD: number;
  ID_BANDA: number;
  BANDA: string;
  DISCOS: number;
  marchasLength: number;
  marchas: DiscoMarchaItem[];
}

export interface DiscoMarchaItem {
  N_DISCO: number;
  NUMEROMARCHA: number;
  ID_MARCHA: number;
  TITULO: string;
  FECHA: string | number;
  AUTOR: AutorRef[];
}

export interface SearchResult<T> {
  rowsReturned: number;
  data: T[];
}

export interface MarchaRow {
  ID_MARCHA: number;
  TITULO: string;
  DEDICATORIA: string;
  LOCALIDAD: string;
  FECHA: string | number;
  AUTOR: AutorRef[];
  GRABADA: number;
}

export interface AutorRow {
  ID_AUTOR: number;
  NOMBRE: string;
  APELLIDOS: string;
  NOMBRE_COMPLETO: string;
  MARCHAS: number;
}

export interface BandaRow {
  ID_BANDA: number;
  NOMBRE_BREVE: string;
  NOMBRE_COMPLETO: string;
  PROVINCIA: string;
  LOCALIDAD: string;
  FECHA_FUND: number;
  FECHA_EXT: number | null;
}

export interface DiscoRow {
  ID_DISCO: number;
  NOMBRE_CD: string;
  FECHA_CD: number;
  ID_BANDA: number;
  BANDA: string;
}

export interface StatsAutorRow { ID_AUTOR: number; AUTOR: string; MARCHAS: number; }
export interface StatsDedicaRow { LUGAR: string; CUENTA: number; }
export interface StatsEstrenoRow { ID_BANDA: number; BANDA: string; MARCHAS: number; }
export interface StatsGrabadaRow {
  ID_MARCHA: number;
  TITULO: string;
  AUTOR: AutorRef[];
  GRABACIONES: number;
}
export interface EstadoRow {
  MARCHAS: number;
  AUTORES: number;
  BANDAS: number;
  DISCOS: number;
}

const INTERNAL_API_URL = (process.env.INTERNAL_API_URL || 'http://localhost:3001').replace(/\/$/, '');

async function apiFetch<T>(path: string, revalidate: number): Promise<T> {
  const url = `${INTERNAL_API_URL}${path}`;
  const res = await fetch(url, {
    next: { revalidate },
  });
  if (!res.ok) throw new Error(`API ${res.status}: ${path}`);
  return res.json() as Promise<T>;
}

// Detail pages — ISR 1 hour
export const fetchMarcha = (id: string) =>
  apiFetch<MarchaDetail>(`/api/marcha/${id}`, 3600);

export const fetchAutor = (id: string) =>
  apiFetch<AutorDetail>(`/api/autor/${id}`, 3600);

export const fetchBanda = (id: string) =>
  apiFetch<BandaDetail>(`/api/banda/${id}`, 3600);

export const fetchDisco = (id: string) =>
  apiFetch<DiscoDetail>(`/api/disco/${id}`, 3600);

// Search — always fresh
export const searchMarchas = (query: string) =>
  apiFetch<SearchResult<MarchaRow>>(`/api/marcha/search?${query}`, 0);

export const searchAutores = (query: string) =>
  apiFetch<SearchResult<AutorRow>>(`/api/autor/search?${query}`, 0);

export const searchBandas = (query: string) =>
  apiFetch<SearchResult<BandaRow>>(`/api/banda/search?${query}`, 0);

export const searchDiscos = (query: string) =>
  apiFetch<SearchResult<DiscoRow>>(`/api/disco/search?${query}`, 0);

// Stats — ISR 30 min
export const fetchMasAutor = () =>
  apiFetch<StatsAutorRow[]>(`/api/stats/masAutor`, 1800);

export const fetchMasDedica = () =>
  apiFetch<StatsDedicaRow[]>(`/api/stats/masDedica`, 1800);

export const fetchMasEstreno = () =>
  apiFetch<StatsEstrenoRow[]>(`/api/stats/masEstreno`, 1800);

export const fetchMasGrabada = () =>
  apiFetch<StatsGrabadaRow[]>(`/api/stats/masGrabada`, 1800);

export const fetchEstado = () =>
  apiFetch<EstadoRow>(`/api/stats/estado`, 1800);
