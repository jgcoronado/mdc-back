import connection from '../db.js';
import express from 'express';
import { resolveQuery, formatAutor } from '../helpers/index.js';

const router = express.Router();

router.get('/', ( _, res) => {
    const response = 'Allow endpoints are: /all, /:id, /search/:name .';
    res.send(response);
});

router.get('/all', async (_, res) => {
  try {
    const [results, fields] = await connection.query(
      'SELECT * FROM marcha LIMIT 100'
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
    const sql_autor = `SELECT * from BANDA
      WHERE BANDA.ID_BANDA LIKE ?`;
    const params = [id];
    const [results_banda] = await connection.execute(sql_autor, params);
    const autor = results_banda[0];
    if (results_banda.length === 0) res.send([]);
    const sql_estrenos = `SELECT m.TITULO, m.ID_MARCHA, m.DEDICATORIA, m.LOCALIDAD, m.FECHA, 
      GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,'#',a.NOMBRE,' ',a.APELLIDOS) SEPARATOR '|') as AUTOR
      FROM marcha m
      INNER JOIN
      marcha_autor am
      ON am.ID_MARCHA = m.ID_MARCHA
      INNER JOIN
      autor a
      ON a.ID_AUTOR = am.ID_AUTOR WHERE m.BANDA_ESTRENO LIKE ? 
      GROUP BY m.ID_MARCHA ORDER BY m.FECHA DESC, m.TITULO ASC`
    const [results_marchas] = await connection.execute(sql_estrenos, params);
    results_marchas.map(r => formatAutor(r));
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
    const sql = `SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.PROVINCIA,
      b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT, CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA
      FROM banda b
      WHERE b.NOMBRE_COMPLETO LIKE ?
      GROUP BY b.ID_BANDA ORDER BY b.NOMBRE_BREVE ASC`;
    const params = [`%${name}%`];
    const results = await resolveQuery(sql,params);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});


 //TODO
router.get('/:id/disco', async (req, res) => {
  try {
    const { id } = req.params;
    const sql = `SELECT b.ID_BANDA, d.ID_DISCO, d.NOMBRE_CD,
      d.FECHA_CD, CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA
      FROM disco_marcha dm
      INNER JOIN disco d
      ON d.ID_DISCO = dm.ID_DISCO
      INNER JOIN banda b
      ON b.ID_BANDA = d.BANDADISCO
      WHERE dm.IDMARCHA LIKE ? ORDER BY d.FECHA_CD ASC`;
    const params = [id];
    const results = await resolveQuery(sql,params);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

export default router;