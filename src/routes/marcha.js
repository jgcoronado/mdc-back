import express from 'express';
import {
  resolveQuery,
  poolExecute,
  formatAutor,
} from '../helpers/index.js';

const router = express.Router();

const buildFtsQuery = (rawTerm) => {
  const cleaned = String(rawTerm || '').replace(/[^\p{L}\p{N}\s]/gu, ' ').trim();
  if (!cleaned) return null;
  const tokens = cleaned.split(/\s+/).map((token) => `"${token}"`);
  return tokens.join(' ');
};

const buildSearchConditions = (queryParams) => {
  const { titulo, fechaDesde, fechaHasta, dedicatoria, localidad, provincia } = queryParams;
  const conditions = [];
  const params = [];

  const titleFtsQuery = buildFtsQuery(titulo);
  if (titulo && titleFtsQuery) {
    conditions.push(`m.ID_MARCHA IN (SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH ?)`);
    params.push(titleFtsQuery);
  }
  if (fechaDesde) {
    conditions.push(`m.FECHA >= ?`);
    params.push(`${fechaDesde}`);
  }
  if (fechaHasta) {
    conditions.push(`m.FECHA <= ?`);
    params.push(`${fechaHasta}`);
  }
  if (dedicatoria) {
    conditions.push(`m.DEDICATORIA LIKE ?`);
    params.push(`%${dedicatoria}%`);
  }
  if (localidad) {
    conditions.push(`m.LOCALIDAD LIKE ?`);
    params.push(`%${localidad}%`);
  }
  if (provincia) {
    conditions.push(`m.PROVINCIA LIKE ?`);
    params.push(`%${provincia}%`);
  }

  return { conditions, params };
};

const normalizeFechaForResponse = (row) => {
  if (row.FECHA === 0 || row.FECHA === '') {
    row.FECHA = 's/f';
  }
  return row;
};

router.get('/', (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name .';
  res.send(response);
});

router.get('/search', async (req, res) => {
  try {
    const { conditions, params } = buildSearchConditions(req.query);
    const sqlHead = `SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA,
        (SELECT GROUP_CONCAT(autor_entry, '|')
         FROM (SELECT DISTINCT (a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS) AS autor_entry
               FROM marcha_autor ma
               INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
               WHERE ma.ID_MARCHA = m.ID_MARCHA)
        ) AS AUTOR,
        CASE WHEN EXISTS (SELECT 1 FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA)
             THEN 1 ELSE 0 END AS GRABADA
        FROM marcha m
        WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA) AND `;
    const sqlTail = ` ORDER BY m.TITULO ASC`;
    const sql = sqlHead.concat(conditions.join(' AND ')).concat(sqlTail);
    const results = await resolveQuery(sql, params);
    results.data.forEach(normalizeFechaForResponse);
    results.data.forEach((row) => formatAutor(row));
    res.send(results);
  } catch (err) {
    console.error('GET /api/marcha/search failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sql = `SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA,
        (SELECT GROUP_CONCAT(autor_entry, '|')
         FROM (SELECT DISTINCT (a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS) AS autor_entry
               FROM marcha_autor ma
               INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
               WHERE ma.ID_MARCHA = m.ID_MARCHA)
        ) AS AUTOR,
        m.BANDA_ESTRENO, m.DETALLES_MARCHA,
        (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
        FROM marcha m
        LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
        WHERE m.ID_MARCHA = ?
          AND EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)`;
    const [results] = await poolExecute(sql, [id]);
    if (results.length === 0) {
      return res.send([]);
    }
    const formattedMarcha = formatAutor(results[0]);
    const sqlDiscos = `SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
        (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
        FROM disco d
        LEFT OUTER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
        LEFT OUTER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
        WHERE dm.IDMARCHA = ?
        ORDER BY d.FECHA_CD ASC`;
    const [discoRows] = await poolExecute(sqlDiscos, [id]);
    const responsePayload = {
      ...formattedMarcha,
      discosLength: discoRows.length,
      discos: discoRows,
    };
    res.send(responsePayload);
  } catch (err) {
    console.error('GET /api/marcha/:id failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/:id/disco', async (req, res) => {
  try {
    const { id } = req.params;
    const sql = `SELECT b.ID_BANDA, d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD,
        (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
        FROM disco_marcha dm
        INNER JOIN disco d ON d.ID_DISCO = dm.ID_DISCO
        INNER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
        WHERE dm.IDMARCHA = ? ORDER BY d.FECHA_CD ASC`;
    const results = await resolveQuery(sql, [id]);
    res.send(results);
  } catch (err) {
    console.error('GET /api/marcha/:id/disco failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

export default router;
