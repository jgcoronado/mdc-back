import express from 'express';
import { poolExecuteAdmin } from '../helpers/admin.js';
import { getTokenFromRequest, verifySession } from '../helpers/authSession.js';

const router = express.Router();
const EDITABLE_MARCHA_FIELDS = new Set([
  'TITULO',
  'FECHA',
  'DEDICATORIA',
  'LOCALIDAD',
  'PROVINCIA',
  'BANDA_ESTRENO',
  'DETALLES_MARCHA',
]);
const INSERTABLE_MARCHA_FIELDS = [
  'TITULO',
  'FECHA',
  'DEDICATORIA',
  'LOCALIDAD',
  'PROVINCIA',
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

router.post('/editMarcha', async (req, res) => {
  try {
    const token = getTokenFromRequest(req);
    const session = verifySession(token);
    if (!session) {
      return res.status(401).json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' });
    }

    const { marchaId, keysToUpdate = [], valuesToUpdate = [] } = req.body || {};

    if (!marchaId) {
      return res.status(400).json({ code: 'INVALID_PAYLOAD', msg: 'Missing marchaId' });
    }
    if (!Array.isArray(keysToUpdate) || !Array.isArray(valuesToUpdate)) {
      return res.status(400).json({ code: 'INVALID_PAYLOAD', msg: 'Invalid update arrays' });
    }
    if (keysToUpdate.length !== valuesToUpdate.length) {
      return res.status(400).json({ code: 'INVALID_PAYLOAD', msg: 'Mismatched keys/values' });
    }
    if (keysToUpdate.length === 0) {
      return res.status(200).json({ code: 'NO_CHANGES', msg: 'No changes to apply', affectedRows: 0 });
    }

    const sanitizedKeys = [];
    const sanitizedValues = [];
    keysToUpdate.forEach((key, index) => {
      if (!EDITABLE_MARCHA_FIELDS.has(key)) {
        return;
      }
      sanitizedKeys.push(key);
      sanitizedValues.push(normalizeValue(valuesToUpdate[index]));
    });

    if (sanitizedKeys.length === 0) {
      return res.status(400).json({ code: 'INVALID_FIELDS', msg: 'No editable fields in request' });
    }

    const sql = `UPDATE marcha SET ${sanitizedKeys.map((key) => `${key} = ?`).join(', ')} WHERE ID_MARCHA = ?`;
    const params = [...sanitizedValues, marchaId];
    const [result] = await poolExecuteAdmin(sql, params);

    if (result.affectedRows === 0) {
      return res.status(404).json({ code: 'NOT_FOUND', msg: 'Marcha not found', affectedRows: 0 });
    }

    return res.status(200).json({
      code: 'UPDATED',
      msg: 'Marcha updated successfully',
      changedRows: result.changedRows,
      affectedRows: result.affectedRows,
    });
  } catch (err) {
    console.error('POST /api/admin/editMarcha failed:', err);
    return res.status(500).json({ code: 'INTERNAL_ERROR', msg: 'Internal server error' });
  }
});

router.post('/addMarcha', async (req, res) => {
  try {
    const token = getTokenFromRequest(req);
    const session = verifySession(token);
    if (!session) {
      return res.status(401).json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' });
    }

    const { marcha = {}, autoresIds = null } = req.body || {};
    const sanitizedMarcha = {};
    INSERTABLE_MARCHA_FIELDS.forEach((field) => {
      sanitizedMarcha[field] = normalizeValue(marcha[field]);
    });
    const columns = INSERTABLE_MARCHA_FIELDS.join(', ');
    const placeholders = INSERTABLE_MARCHA_FIELDS.map(() => '?').join(', ');
    const insertSql = `INSERT INTO marcha (${columns}) VALUES (${placeholders})`;
    const insertParams = INSERTABLE_MARCHA_FIELDS.map((field) => sanitizedMarcha[field]);
    const [insertResult] = await poolExecuteAdmin(insertSql, insertParams);
    const insertId = insertResult.insertId;

    if (!insertId) {
      return res.status(500).json({ code: 'INTERNAL_ERROR', msg: 'Could not create marcha' });
    }

    const normalizedAutoresInput = Array.isArray(autoresIds)
      ? autoresIds.join(',')
      : String(autoresIds ?? '');
    const sanitizedAutores = [...new Set(
      normalizedAutoresInput
        .split(',')
        .map((value) => Number.parseInt(String(value).trim(), 10))
        .filter((id) => Number.isInteger(id) && id > 0)
    )];

    if (sanitizedAutores.length > 0) {
      const relationPlaceholders = sanitizedAutores.map(() => '(?, ?)').join(', ');
      const relationParams = sanitizedAutores.flatMap((autorId) => [insertId, autorId]);
      const relationSql = `INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES ${relationPlaceholders}`;
      await poolExecuteAdmin(relationSql, relationParams);
    }

    return res.status(201).json({
      code: 'CREATED',
      msg: 'Marcha created successfully',
      marchaId: insertId,
    });
  } catch (err) {
    console.error('POST /api/admin/addMarcha failed:', err);
    return res.status(500).json({ code: 'INTERNAL_ERROR', msg: 'Internal server error' });
  }
});

export default router;
