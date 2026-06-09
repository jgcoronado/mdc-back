import type { MarchaDetail, AutorDetail } from './api';

const EDITABLE_MARCHA_FIELDS = [
  'TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'AUDIO', 'BANDA_ESTRENO', 'DETALLES_MARCHA',
] as const;

const INSERTABLE_MARCHA_FIELDS = [
  'TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'BANDA_ESTRENO', 'DETALLES_MARCHA',
] as const;

const EDITABLE_AUTOR_FIELDS = [
  'NOMBRE', 'APELLIDOS', 'NOMBRE_ART', 'F_NAC', 'LUGAR_NAC', 'F_DEF', 'BIO',
] as const;

const INSERTABLE_AUTOR_FIELDS = [
  'NOMBRE', 'APELLIDOS', 'NOMBRE_ART', 'F_NAC', 'LUGAR_NAC', 'F_DEF', 'BIO',
] as const;

type EditableMarchaField = typeof EDITABLE_MARCHA_FIELDS[number];
type EditableAutorField = typeof EDITABLE_AUTOR_FIELDS[number];
type InsertableAutorField = typeof INSERTABLE_AUTOR_FIELDS[number];

const normalize = (v: unknown): unknown => {
  if (v === undefined) return null;
  if (typeof v === 'string') { const t = v.trim(); return t === '' ? null : t; }
  return v;
};

export interface MarchaUpdatePayload {
  marchaId: number;
  keysToUpdate: string[];
  valuesToUpdate: unknown[];
  params: unknown[];
  sqlPreview: string;
  changedFields: { key: string; previousValue: unknown; newValue: unknown }[];
}

export function buildMarchaUpdatePayload(original: Partial<MarchaDetail>, draft: Partial<MarchaDetail>): MarchaUpdatePayload {
  const marchaId = draft.ID_MARCHA as number;
  const keysToUpdate = EDITABLE_MARCHA_FIELDS.filter(
    (k) => normalize(original[k as keyof MarchaDetail]) !== normalize(draft[k as keyof MarchaDetail])
  );
  const valuesToUpdate = keysToUpdate.map((k) => normalize(draft[k as keyof MarchaDetail]));
  const sqlPreview = keysToUpdate.length > 0
    ? `UPDATE marcha SET ${keysToUpdate.map((k) => `${k} = ?`).join(', ')} WHERE ID_MARCHA = ?`
    : '';
  const changedFields = keysToUpdate.map((k, i) => ({
    key: k,
    previousValue: normalize(original[k as keyof MarchaDetail]),
    newValue: valuesToUpdate[i],
  }));
  return { marchaId, keysToUpdate, valuesToUpdate, params: keysToUpdate.length > 0 ? [...valuesToUpdate, marchaId] : [], sqlPreview, changedFields };
}

export interface MarchaInsertDraft {
  TITULO: string; FECHA: string; DEDICATORIA: string; LOCALIDAD: string;
  PROVINCIA: string; BANDA_ESTRENO: number | null; DETALLES_MARCHA: string; AUTORES_IDS: string;
}

export function buildMarchaInsertPayload(draft: MarchaInsertDraft) {
  const fields = [...INSERTABLE_MARCHA_FIELDS];
  const valuesToInsert = fields.map((k) => normalize(draft[k as keyof MarchaInsertDraft]));
  const autoresIds = normalize(draft.AUTORES_IDS);
  const sqlPreview = `INSERT INTO marcha (${fields.join(', ')}) VALUES (${fields.map(() => '?').join(', ')})`;
  const previewFields = fields.map((k, i) => ({ key: k, newValue: valuesToInsert[i] }));
  return { fieldsToInsert: fields, valuesToInsert, autoresIds, sqlPreview, previewFields };
}

export type AutorInsertDraft = Record<InsertableAutorField, string>;

export function buildAutorInsertPayload(draft: AutorInsertDraft) {
  const fields = [...INSERTABLE_AUTOR_FIELDS];
  const valuesToInsert = fields.map((k) => normalize(draft[k]));
  const sqlPreview = `INSERT INTO autor (${fields.join(', ')}) VALUES (${fields.map(() => '?').join(', ')})`;
  const previewFields = fields.map((k, i) => ({ key: k, newValue: valuesToInsert[i] }));
  return { autor: Object.fromEntries(fields.map((k, i) => [k, valuesToInsert[i]])), fieldsToInsert: fields, valuesToInsert, sqlPreview, previewFields };
}

export interface AutorUpdatePayload {
  autorId: number;
  keysToUpdate: string[];
  valuesToUpdate: unknown[];
  params: unknown[];
  sqlPreview: string;
  changedFields: { key: string; previousValue: unknown; newValue: unknown }[];
}

export function buildAutorUpdatePayload(original: Partial<AutorDetail>, draft: Partial<AutorDetail>): AutorUpdatePayload {
  const autorId = draft.ID_AUTOR as number;
  const keysToUpdate = EDITABLE_AUTOR_FIELDS.filter(
    (k) => normalize(original[k as EditableAutorField]) !== normalize(draft[k as EditableAutorField])
  );
  const valuesToUpdate = keysToUpdate.map((k) => normalize(draft[k as EditableAutorField]));
  const sqlPreview = keysToUpdate.length > 0
    ? `UPDATE autor SET ${keysToUpdate.map((k) => `${k} = ?`).join(', ')} WHERE ID_AUTOR = ?`
    : '';
  const changedFields = keysToUpdate.map((k, i) => ({
    key: k,
    previousValue: normalize(original[k as EditableAutorField]),
    newValue: valuesToUpdate[i],
  }));
  return { autorId, keysToUpdate, valuesToUpdate, params: keysToUpdate.length > 0 ? [...valuesToUpdate, autorId] : [], sqlPreview, changedFields };
}

const apiPost = async (path: string, body: unknown) => {
  const res = await fetch(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'include',
  });
  return res.json();
};

export const executeMarchaUpdate = (payload: MarchaUpdatePayload) => apiPost('/api/admin/editMarcha', payload);
export const executeMarchaInsert = (payload: ReturnType<typeof buildMarchaInsertPayload>) => apiPost('/api/admin/addMarcha', payload);
export const executeAutorInsert = (payload: ReturnType<typeof buildAutorInsertPayload>) => apiPost('/api/admin/addAutor', payload);
export const executeAutorUpdate = (payload: AutorUpdatePayload) => apiPost('/api/admin/editAutor', payload);
export const executeEditMarchaAutores = (payload: { marchaId: number; autoresIds: number[] }) => apiPost('/api/admin/editMarchaAutores', payload);

export const searchAutores = async (nombre: string) => {
  const res = await fetch(`/api/autor/fastSearch?nombre=${encodeURIComponent(nombre)}`);
  const data = await res.json();
  return Array.isArray(data?.data) ? data.data : [];
};

export const searchBandas = async (nombre: string) => {
  const res = await fetch(`/api/banda/fastSearch?nombre=${encodeURIComponent(nombre)}`);
  const data = await res.json();
  return Array.isArray(data?.data) ? data.data : [];
};
