import express from 'express';
import { poolExecuteAdmin } from '../helpers/admin.js';
import { getTokenFromRequest, verifySession } from '../helpers/authSession.js';

const router = express.Router();
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

router.post('/addAutor', async (req, res) => {
  try {
    const token = getTokenFromRequest(req);
    const session = verifySession(token);
    if (!session) {
      return res.status(401).json({ code: 'AUTH_REQUIRED', msg: 'Unauthorized' });
    }

    const { autor = {} } = req.body || {};
    const sanitizedAutor = {};
    INSERTABLE_AUTOR_FIELDS.forEach((field) => {
      sanitizedAutor[field] = normalizeValue(autor[field]);
    });

    const columns = INSERTABLE_AUTOR_FIELDS.join(', ');
    const placeholders = INSERTABLE_AUTOR_FIELDS.map(() => '?').join(', ');
    const insertSql = `INSERT INTO autor (${columns}) VALUES (${placeholders})`;
    const insertParams = INSERTABLE_AUTOR_FIELDS.map((field) => sanitizedAutor[field]);
    const [insertResult] = await poolExecuteAdmin(insertSql, insertParams);
    const insertId = insertResult.insertId;

    if (!insertId) {
      return res.status(500).json({ code: 'INTERNAL_ERROR', msg: 'Could not create autor' });
    }

    return res.status(201).json({
      code: 'CREATED',
      msg: 'Autor created successfully',
      autorId: insertId,
    });
  } catch (err) {
    console.error('POST /api/admin/addAutor failed:', err);
    return res.status(500).json({ code: 'INTERNAL_ERROR', msg: 'Internal server error' });
  }
});

export default router;
