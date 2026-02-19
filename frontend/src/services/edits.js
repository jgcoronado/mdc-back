import axios from 'axios';

const BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');

const EDITABLE_MARCHA_FIELDS = [
  'TITULO',
  'FECHA',
  'DEDICATORIA',
  'LOCALIDAD',
  'AUDIO',
  'BANDA_ESTRENO',
  'DETALLES_MARCHA',
];
const INSERTABLE_MARCHA_FIELDS = [...EDITABLE_MARCHA_FIELDS];

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

const paramsToUpdate = (obj1, obj2, allowedKeys = null) => {
  const keys = Array.isArray(allowedKeys) && allowedKeys.length > 0
    ? allowedKeys
    : Object.keys(obj1 || {});
  const different = [];

  keys.forEach((key) => {
    if (normalizeValue(obj1?.[key]) !== normalizeValue(obj2?.[key])) {
      different.push(key);
    }
  });

  return different;
};

const buildMarchaUpdatePayload = (originalData, draftData) => {
  const marchaId = draftData?.ID_MARCHA;
  const keysToUpdate = paramsToUpdate(originalData, draftData, EDITABLE_MARCHA_FIELDS);
  const valuesToUpdate = keysToUpdate.map((key) => normalizeValue(draftData[key]));

  const setClauses = keysToUpdate.map((key) => `${key} = ?`);
  const sqlPreview = keysToUpdate.length > 0
    ? `UPDATE marcha SET ${setClauses.join(', ')} WHERE ID_MARCHA = ?`
    : '';

  const changedFields = keysToUpdate.map((key, index) => ({
    key,
    previousValue: normalizeValue(originalData?.[key]),
    newValue: valuesToUpdate[index],
  }));

  return {
    marchaId,
    keysToUpdate,
    valuesToUpdate,
    params: keysToUpdate.length > 0 ? [...valuesToUpdate, marchaId] : [],
    sqlPreview,
    changedFields,
  };
};

const executeMarchaUpdate = async (payload) => {
  const apiUrl = `${BASE_URL}/admin/editMarcha`;
  const res = await axios.post(apiUrl, payload, { withCredentials: true });
  return res.data;
};

const buildMarchaInsertPayload = (draftData) => {
  const valuesToInsert = INSERTABLE_MARCHA_FIELDS.map((key) => normalizeValue(draftData?.[key]));
  const autoresIds = normalizeValue(draftData?.AUTORES_IDS);
  const sqlPreview = `INSERT INTO marcha (${INSERTABLE_MARCHA_FIELDS.join(', ')}) VALUES (${INSERTABLE_MARCHA_FIELDS.map(() => '?').join(', ')})`;

  const previewFields = INSERTABLE_MARCHA_FIELDS.map((key, index) => ({
    key,
    newValue: valuesToInsert[index],
  }));

  return {
    fieldsToInsert: INSERTABLE_MARCHA_FIELDS,
    valuesToInsert,
    marcha: INSERTABLE_MARCHA_FIELDS.reduce((acc, key, index) => {
      acc[key] = valuesToInsert[index];
      return acc;
    }, {}),
    autoresIds,
    sqlPreview,
    previewFields,
  };
};

const executeMarchaInsert = async (payload) => {
  const apiUrl = `${BASE_URL}/admin/addMarcha`;
  const res = await axios.post(apiUrl, payload, { withCredentials: true });
  return res.data;
};

export {
  EDITABLE_MARCHA_FIELDS,
  INSERTABLE_MARCHA_FIELDS,
  paramsToUpdate,
  buildMarchaUpdatePayload,
  executeMarchaUpdate,
  buildMarchaInsertPayload,
  executeMarchaInsert,
};
