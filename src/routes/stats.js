import express from 'express';
import { resolveQuery, poolExecute, formatAutor } from '../helpers/index.js';

const router = express.Router();

router.get('/', (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name .';
  res.send(response);
});

router.get('/test', async (_, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name.';
  const sql = `SELECT * FROM autor LIMIT 3`;
  const [results] = await poolExecute(sql);
  res.send({ response, results });
});

router.get('/masAutor', async (_, res) => {
  try {
    const sql = `SELECT a.ID_AUTOR, COUNT(m.ID_MARCHA) AS MARCHAS,
      (a.NOMBRE || ' ' || a.APELLIDOS) AS AUTOR FROM autor a
      INNER JOIN marcha_autor am ON am.ID_AUTOR = a.ID_AUTOR
      INNER JOIN marcha m ON m.ID_MARCHA = am.ID_MARCHA
      GROUP BY a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
      ORDER BY MARCHAS DESC LIMIT 10`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.error('GET /api/stats/masAutor failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/masDedica', async (_, res) => {
  try {
    const sql = `SELECT COUNT(DEDICATORIA) AS CUENTA,
      (DEDICATORIA || ' (' || LOCALIDAD || ')') AS LUGAR
      FROM marcha WHERE DEDICATORIA LIKE '%Hdad%' GROUP BY LUGAR
      HAVING CUENTA >= 15 ORDER BY CUENTA DESC`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.error('GET /api/stats/masDedica failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/masEstreno', async (_, res) => {
  try {
    const sql = `SELECT b.ID_BANDA, COUNT(m.ID_MARCHA) AS MARCHAS,
      (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA FROM marcha m
      INNER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
      WHERE b.ID_BANDA != 0
      GROUP BY b.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
      ORDER BY MARCHAS DESC LIMIT 20`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.error('GET /api/stats/masEstreno failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/masGrabada', async (_, res) => {
  try {
    const sql = `SELECT COUNT(dm.IDMARCHA) AS GRABACIONES, m.ID_MARCHA, m.TITULO,
      (SELECT GROUP_CONCAT(autor_entry, '|')
       FROM (SELECT DISTINCT (a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS) AS autor_entry
             FROM marcha_autor ma
             INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = m.ID_MARCHA)
      ) AS AUTOR
      FROM disco_marcha dm
      INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
      WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)
      GROUP BY dm.IDMARCHA, m.ID_MARCHA, m.TITULO
      ORDER BY GRABACIONES DESC LIMIT 20`;
    const results = await resolveQuery(sql);
    results.data.forEach((row) => formatAutor(row));
    res.send(results.data);
  } catch (err) {
    console.error('GET /api/stats/masGrabada failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

router.get('/estado', async (_, res) => {
  try {
    const sql = `SELECT
      (SELECT COUNT(m.ID_MARCHA) FROM marcha m) AS MARCHAS,
      (SELECT COUNT(a.ID_AUTOR) FROM autor a) AS AUTORES,
      (SELECT COUNT(b.ID_BANDA) FROM banda b) AS BANDAS,
      (SELECT COUNT(d.ID_DISCO) FROM disco d) AS DISCOS`;
    const [results] = await poolExecute(sql);
    res.send(results[0]);
  } catch (err) {
    console.error('GET /api/stats/estado failed:', err);
    res.status(500).send({ error: 'Internal server error' });
  }
});

export default router;
