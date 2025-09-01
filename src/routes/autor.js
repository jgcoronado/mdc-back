import express from 'express';
import { resolveQuery, poolExecute } from '../helpers/index.js';

export const router = express.Router();

router.get('/', ( _, res) => {
    const response = 'Allow endpoints are: /all, /:id, /search/:name .';
    res.send(response);
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