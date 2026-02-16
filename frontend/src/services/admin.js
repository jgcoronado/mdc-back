const EDITABLE_MARCHA_FIELDS = [
  'TITULO',
  'FECHA',
  'DEDICATORIA',
  'LOCALIDAD',
  'AUDIO',
  'BANDA_ESTRENO',
  'DETALLES_MARCHA',
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

export const paramsToUpdate = (obj1, obj2, allowedKeys = null) => {
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

export const buildMarchaUpdatePayload = (originalData, draftData) => {
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

export { EDITABLE_MARCHA_FIELDS };
