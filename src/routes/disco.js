import express from 'express';
import { resolveQuery, poolExecute, formatAutor } from '../helpers/index.js';

const router = express.Router();

router.get('/', (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name .';
  res.send(response);
});

router.get('/search', async (req, res) => {
  try {
    const { nombre } = req.query;
    const conditions = [];
    const params = [];

    if (nombre) {
      conditions.push(`d.NOMBRE_CD LIKE ?`);
      params.push(`%${nombre}%`);
    }
    const sqlHead = `SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
      FROM disco d
      LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO WHERE `;
    const sqlTail = ` ORDER BY d.FECHA_CD ASC`;
    const sql = sqlHead.concat(conditions.join(' AND ')).concat(sqlTail);
    const results = await resolveQuery(sql, params);
    res.send(results);
  } catch (err) {
    console.error('GET /api/disco/search failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sqlDisco = `SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, d.d_DETALLES, b.ID_BANDA,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
      FROM disco d
      LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
      WHERE d.ID_DISCO = ?`;
    const [discoRows] = await poolExecute(sqlDisco, [id]);
    if (discoRows.length === 0) {
      return res.send([]);
    }
    const sqlMarchas = `SELECT dm.N_DISCO, dm.NUMEROMARCHA, m.ID_MARCHA, m.TITULO, m.FECHA,
      (SELECT GROUP_CONCAT(autor_entry, '|')
       FROM (SELECT DISTINCT (a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS) AS autor_entry
             FROM marcha_autor am
             INNER JOIN autor a ON a.ID_AUTOR = am.ID_AUTOR
             WHERE am.ID_MARCHA = m.ID_MARCHA)
      ) AS AUTOR,
      CASE WHEN dm.DM_ENLAZADA IS NULL THEN 0 ELSE 1 END AS ENLAZADA
      FROM disco d
      INNER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
      INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
      WHERE d.ID_DISCO = ?
        AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
      ORDER BY dm.N_DISCO ASC, dm.NUMEROMARCHA ASC, dm.DM_ENLAZADA ASC`;
    const [marchaRows] = await poolExecute(sqlMarchas, [id]);
    marchaRows.forEach((row) => {
      if (row.FECHA === 0 || row.FECHA === '') {
        row.FECHA = 's/f';
      }
      formatAutor(row);
    });
    const responsePayload = {
      ...discoRows[0],
      marchasLength: marchaRows.length,
      marchas: marchaRows,
    };
    res.send(responsePayload);
  } catch (err) {
    console.error('GET /api/disco/:id failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

export default router;
