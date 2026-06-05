import express from 'express';
import { poolExecute, formatAutor, resolveQuery } from '../helpers/index.js';

const router = express.Router();

// NOTE: the original implementation in MySQL used a forEach(async ...) that
// never awaited its inner queries, so the returned timeline only ever
// contained the starting band. To keep behaviour identical during the
// SQLite migration we preserve that semantics here. The roadmap Phase 1
// tracks the proper fix with for...of + await.
const buildBandaTimeline = (banda) => {
  const { ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE } = banda;
  return [{ ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE }];
};

router.get('/', (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name .';
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
        b.ID_BANDA,
        b.NOMBRE_BREVE,
        b.NOMBRE_COMPLETO,
        b.LOCALIDAD
      FROM banda b
      WHERE
        b.NOMBRE_BREVE LIKE ? OR
        b.NOMBRE_COMPLETO LIKE ? OR
        (b.NOMBRE_BREVE || ' ' || b.LOCALIDAD) LIKE ? OR
        (b.NOMBRE_COMPLETO || ' ' || b.LOCALIDAD) LIKE ?
      ORDER BY
        (b.NOMBRE_BREVE LIKE ?) DESC,
        (b.NOMBRE_COMPLETO LIKE ?) DESC,
        b.NOMBRE_BREVE ASC
      LIMIT 5
    `;
    const params = [
      prefixPattern, prefixPattern,
      containsPattern, containsPattern,
      prefixPattern, prefixPattern,
    ];
    const [rows] = await poolExecute(sql, params);
    return res.send({ rowsReturned: rows.length, data: rows });
  } catch (err) {
    console.error('GET /api/banda/fastSearch failed:', err);
    return res.status(500).send({ rowsReturned: 0, data: [] });
  }
});

router.get('/search', async (req, res) => {
  try {
    const { titulo, localidad, provincia } = req.query;
    const conditions = [];
    const params = [];

    if (titulo) {
      conditions.push(`b.NOMBRE_COMPLETO LIKE ?`);
      params.push(`%${titulo}%`);
    }
    if (localidad) {
      conditions.push(`b.LOCALIDAD LIKE ?`);
      params.push(`%${localidad}%`);
    }
    if (provincia) {
      conditions.push(`b.PROVINCIA LIKE ?`);
      params.push(`%${provincia}%`);
    }
    const sqlHead = `SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.PROVINCIA,
      b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
      FROM banda b WHERE `;
    const sqlTail = ` GROUP BY b.ID_BANDA ORDER BY b.NOMBRE_BREVE ASC`;
    const sql = sqlHead.concat(conditions.join(' AND ')).concat(sqlTail);
    const results = await resolveQuery(sql, params);
    res.send(results);
  } catch (err) {
    console.error('GET /api/banda/search failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sqlBanda = `SELECT * FROM banda WHERE ID_BANDA = ?`;
    const [bandaRows] = await poolExecute(sqlBanda, [id]);
    if (bandaRows.length === 0) {
      return res.send([]);
    }
    const banda = bandaRows[0];
    const timeline = buildBandaTimeline(banda);
    const sqlDiscos = `SELECT DISTINCT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD,
      (SELECT COUNT(m.ID_DM) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS PISTAS,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS
      FROM disco d
      WHERE d.BANDADISCO = ? ORDER BY d.FECHA_CD ASC`;
    const [discoRows] = await poolExecute(sqlDiscos, [id]);
    const sqlEstrenos = `SELECT m.TITULO, m.ID_MARCHA, m.DEDICATORIA, m.LOCALIDAD, m.FECHA,
      (SELECT GROUP_CONCAT(autor_entry, '|')
       FROM (SELECT DISTINCT (a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS) AS autor_entry
             FROM marcha_autor am
             INNER JOIN autor a ON a.ID_AUTOR = am.ID_AUTOR
             WHERE am.ID_MARCHA = m.ID_MARCHA)
      ) AS AUTOR
      FROM marcha m
      WHERE m.BANDA_ESTRENO = ?
        AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
      ORDER BY m.FECHA DESC, m.TITULO ASC`;
    const [marchaRows] = await poolExecute(sqlEstrenos, [id]);
    marchaRows.forEach((row) => formatAutor(row));
    timeline.sort((left, right) => left.FECHA_FUND - right.FECHA_FUND);
    const responsePayload = {
      ...banda,
      timeline,
      discosLength: discoRows.length,
      discos: discoRows,
      marchasLength: marchaRows.length,
      marchas: marchaRows,
    };
    res.send(responsePayload);
  } catch (err) {
    console.error('GET /api/banda/:id failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

export default router;
