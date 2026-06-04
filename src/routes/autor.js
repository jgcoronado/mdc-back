import express from 'express';
import { resolveQuery, poolExecute } from '../helpers/index.js';

export const router = express.Router();

const buildFtsQuery = (rawTerm) => {
  const cleaned = String(rawTerm || '').replace(/[^\p{L}\p{N}\s]/gu, ' ').trim();
  if (!cleaned) return null;
  const tokens = cleaned.split(/\s+/).map((token) => `"${token}"`);
  return tokens.join(' ');
};

router.get('/', (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name, /fastSearch?nombre=... .';
  res.send(response);
});

router.get('/fastSearch', async (req, res) => {
  try {
    const { nombre = '' } = req.query;
    const trimmedName = String(nombre).trim();

    if (trimmedName.length < 1) {
      return res.send({ rowsReturned: 0, data: [] });
    }

    const prefixPattern = `${trimmedName}%`;
    const containsPattern = `%${trimmedName}%`;

    const sql = `
      SELECT
        a.ID_AUTOR,
        a.NOMBRE,
        a.APELLIDOS,
        a.NOMBRE_ART,
        (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO
      FROM autor a
      WHERE
        a.APELLIDOS LIKE ? OR
        a.NOMBRE LIKE ? OR
        a.NOMBRE_ART LIKE ? OR
        (a.APELLIDOS || ' ' || a.NOMBRE) LIKE ? OR
        (a.NOMBRE || ' ' || a.APELLIDOS) LIKE ?
      ORDER BY
        (a.APELLIDOS LIKE ?) DESC,
        (a.NOMBRE LIKE ?) DESC,
        a.APELLIDOS ASC,
        a.NOMBRE ASC
      LIMIT 5
    `;
    const params = [
      prefixPattern, prefixPattern, prefixPattern,
      containsPattern, containsPattern,
      prefixPattern, prefixPattern,
    ];
    const [rows] = await poolExecute(sql, params);
    return res.send({ rowsReturned: rows.length, data: rows });
  } catch (err) {
    console.error('GET /api/autor/fastSearch failed:', err);
    return res.status(500).send({ rowsReturned: 0, data: [] });
  }
});

router.get('/search', async (req, res) => {
  try {
    const { nombre } = req.query;
    const conditions = [];
    const params = [];

    const nameFtsQuery = buildFtsQuery(nombre);
    if (nombre && nameFtsQuery) {
      conditions.push(`a.ID_AUTOR IN (SELECT rowid FROM autor_fts WHERE autor_fts MATCH ?)`);
      params.push(nameFtsQuery);
    }
    const sqlHead = `SELECT a.*, (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO,
      (SELECT COUNT(ma.ID_MARCHA) FROM marcha_autor ma WHERE ma.ID_AUTOR = a.ID_AUTOR) AS MARCHAS
      FROM autor a WHERE `;
    const sqlTail = ` ORDER BY a.APELLIDOS ASC`;
    const sql = sqlHead.concat(conditions.join(' AND ')).concat(sqlTail);
    const results = await resolveQuery(sql, params);
    res.send(results);
  } catch (err) {
    console.error('GET /api/autor/search failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sqlAutor = `SELECT * FROM autor WHERE ID_AUTOR = ?`;
    const [autorRows] = await poolExecute(sqlAutor, [id]);
    if (autorRows.length === 0) {
      return res.send([]);
    }
    const autor = autorRows[0];
    const sqlMarchas = `SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA
      FROM marcha m
      INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
      INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
      WHERE a.ID_AUTOR = ? ORDER BY m.FECHA ASC`;
    const [marchaRows] = await poolExecute(sqlMarchas, [id]);
    marchaRows.forEach((row) => {
      if (row.FECHA === 0 || row.FECHA === '') {
        row.FECHA = 's/f';
      }
    });
    const responsePayload = { ...autor, marchasLength: marchaRows.length, marchas: marchaRows };
    res.send(responsePayload);
  } catch (err) {
    console.error('GET /api/autor/:id failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

export default router;
