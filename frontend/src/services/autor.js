import axios from 'axios';

const BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
const INSERTABLE_AUTOR_FIELDS = [
  'NOMBRE',
  'APELLIDOS',
  'F_NAC',
  'LUGAR_NAC',
  'F_DEF',
  'BIO',
];

const normalizeValue = (value) => {
  if (value === undefined) {
    return null;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
  }
  return value;
};

const buildAutorInsertPayload = (draftData) => {
  const valuesToInsert = INSERTABLE_AUTOR_FIELDS.map((field) => normalizeValue(draftData?.[field]));
  const sqlPreview = `INSERT INTO autor (${INSERTABLE_AUTOR_FIELDS.join(', ')}) VALUES (${INSERTABLE_AUTOR_FIELDS.map(() => '?').join(', ')})`;
  const previewFields = INSERTABLE_AUTOR_FIELDS.map((field, index) => ({
    key: field,
    newValue: valuesToInsert[index],
  }));
  const autor = INSERTABLE_AUTOR_FIELDS.reduce((acc, field, index) => {
    acc[field] = valuesToInsert[index];
    return acc;
  }, {});

  return {
    autor,
    fieldsToInsert: INSERTABLE_AUTOR_FIELDS,
    valuesToInsert,
    sqlPreview,
    previewFields,
  };
};

const executeAutorInsert = async (payload) => {
  const apiUrl = `${BASE_URL}/admin/addAutor`;
  const response = await axios.post(apiUrl, payload, { withCredentials: true });
  return response.data;
};

export { buildAutorInsertPayload, executeAutorInsert };
