import connection from '../db.js';
import express from 'express';
import { resolveQuery } from '../helpers/index.js';

const router = express.Router();

router.get('/', ( _, res) => {
    const response = 'Allow endpoints are: /all, /:id, /search/:name .';
    res.send(response);
});

router.get('/all', async (_, res) => {
  try {
    const [results, fields] = await connection.query(
      'SELECT * FROM autor LIMIT 100'
    );
    console.log(fields); // fields contains extra meta data about results, if available
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
    const [results_autor] = await connection.execute(sql_autor, params);
    const autor = results_autor[0];
    console.log("🚀 ~ autor:", autor)
    if (results_autor.length === 0) res.send([]);
    const sql_marcha = `SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA from marcha m
      INNER JOIN marcha_autor ma 
      ON ma.ID_MARCHA = m.ID_MARCHA
      INNER JOIN autor a
      ON a.ID_AUTOR = ma.ID_AUTOR
      WHERE a.ID_AUTOR
      LIKE ? ORDER BY m.FECHA ASC`
    const [results_marchas] = await connection.execute(sql_marcha, params);
    const marchasLength = results_marchas.length;
    const resToSend = { ...autor, marchasLength, marchas: results_marchas};
    res.send(resToSend);
  } catch (err) {
    console.log(err);
  }
});

router.get('/search/:name', async (req, res) => {
  try {
    const { name } = req.params;
    const sql = `SELECT a.ID_AUTOR, a.NOMBRE_ART, 
					CONCAT(a.NOMBRE,' ', a.APELLIDOS) AS AUTOR FROM autor a
					WHERE MATCH(a.APELLIDOS, a.NOMBRE, a.NOMBRE_ART) AGAINST(?) 
					ORDER BY a.APELLIDOS ASC, a.NOMBRE ASC`;
    const params = [`%${name}%`];
    const results = await resolveQuery(sql,params);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

export default router;