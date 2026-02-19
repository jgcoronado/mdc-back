import express from 'express';
import { resolveQuery, poolExecute } from '../helpers/index.js';

export const router = express.Router();

router.get('/', ( _, res) => {
    const response = 'Allow endpoints are: /all, /:id, /search/:name, /fastSearch?nombre=... .';
    res.send(response);
});

router.get('/fastSearch', async (req, res) => {
  try {
    const { nombre = '' } = req.query;
    const trimmed = String(nombre).trim();

    if (trimmed.length < 1) {
      return res.send({ rowsReturned: 0, data: [] });
    }

    const prefix = `${trimmed}%`;
    const contains = `%${trimmed}%`;

    const sql = `
      SELECT
        a.ID_AUTOR,
        a.NOMBRE,
        a.APELLIDOS,
        a.NOMBRE_ART,
        CONCAT(a.NOMBRE, ' ', a.APELLIDOS) AS NOMBRE_COMPLETO
      FROM autor a
      WHERE
        a.APELLIDOS LIKE ? OR
        a.NOMBRE LIKE ? OR
        a.NOMBRE_ART LIKE ? OR
        CONCAT(a.APELLIDOS, ' ', a.NOMBRE) LIKE ? OR
        CONCAT(a.NOMBRE, ' ', a.APELLIDOS) LIKE ?
      ORDER BY
        (a.APELLIDOS LIKE ?) DESC,
        (a.NOMBRE LIKE ?) DESC,
        a.APELLIDOS ASC,
        a.NOMBRE ASC
      LIMIT 5
    `;
    const params = [prefix, prefix, prefix, contains, contains, prefix, prefix];
    const [rows] = await poolExecute(sql, params);
    return res.send({ rowsReturned: rows.length, data: rows });
  } catch (err) {
    console.log(err);
    return res.status(500).send({ rowsReturned: 0, data: [] });
  }
});

router.get('/search', async (req, res) => {
  try {
    const { nombre } = req.query;
    const sql_search = [];
    const params = [];

    if(nombre) {
      sql_search.push(`MATCH(a.APELLIDOS, a.NOMBRE, a.NOMBRE_ART) AGAINST(?)`);
      params.push(`%${nombre}%`);
    }
    const sql_head = `SELECT *, CONCAT(a.NOMBRE,' ', a.APELLIDOS) as NOMBRE_COMPLETO,
      (SELECT COUNT(ma.ID_MARCHA) from marcha_autor ma WHERE ma.ID_AUTOR = a.ID_AUTOR)
      AS MARCHAS from autor a WHERE `;
    const sql_tail = ` ORDER BY a.APELLIDOS ASC`;
    const sql = sql_head.concat(sql_search.join(' AND ')).concat(sql_tail);
    const results = await resolveQuery(sql,params);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sql_autor = `SELECT * from autor
      WHERE autor.ID_AUTOR LIKE ?`;
    const params = [id];
    const [results_autor] = await poolExecute(sql_autor, params);
    const autor = results_autor[0];
    if (results_autor.length === 0) res.send([]);
    const sql_marcha = `SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA from marcha m
      INNER JOIN marcha_autor ma 
      ON ma.ID_MARCHA = m.ID_MARCHA
      INNER JOIN autor a
      ON a.ID_AUTOR = ma.ID_AUTOR
      WHERE a.ID_AUTOR
      LIKE ? ORDER BY m.FECHA ASC`
    const [results_marchas] = await poolExecute(sql_marcha, params);
    results_marchas.map(r => r.FECHA === 0 || r.FECHA === '' ? r.FECHA = 's/f' : r.FECHA);
    const marchasLength = results_marchas.length;
    const resToSend = { ...autor, marchasLength, marchas: results_marchas};
    res.send(resToSend);
  } catch (err) {
    console.log(err);
  }
});

export default router;
