import express from 'express';
import crypto from 'node:crypto';
import { poolExecuteAdmin } from '../helpers/admin.js';

const router = express.Router();

const tokenSecret = process.env.SECRET_KEY || 'change-this-secret';
const EDITABLE_MARCHA_FIELDS = new Set([
  'TITULO',
  'FECHA',
  'DEDICATORIA',
  'LOCALIDAD',
  'AUDIO',
  'BANDA_ESTRENO',
  'DETALLES_MARCHA',
]);

const verifySession = (token) => {
  const [encodedPayload, signature] = (token || '').split('.');
  if (!encodedPayload || !signature) {
    return null;
  }

  const expectedSignature = crypto
    .createHmac('sha256', tokenSecret)
    .update(encodedPayload)
    .digest('base64url');
  if (signature !== expectedSignature) {
    return null;
  }

  const payload = JSON.parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
  if (!payload.exp || Date.now() > payload.exp) {
    return null;
  }

  return payload;
};

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
    const authHeader = req.headers.authorization || '';
    const token = authHeader.startsWith('Bearer ')
      ? authHeader.slice(7).trim()
      : '';
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

export default router;
