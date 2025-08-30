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
      'SELECT * FROM disco LIMIT 100'
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
    const sql_autor = `SELECT d.ID_DISCO, d.NOMBRE_CD,
      d.FECHA_CD, d.d_DETALLES, b.ID_BANDA,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) as DISCOS, 
      CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA from disco d
      LEFT JOIN BANDA b ON b.ID_BANDA = d.BANDADISCO
      WHERE d.ID_DISCO LIKE ?`;
    const params = [id];
    const [results_disco] = await connection.execute(sql_autor, params);
    if (results_disco.length === 0) res.send([]);
    const sql_marchas = `SELECT dm.N_DISCO, dm.NUMEROMARCHA, m.ID_MARCHA, m.TITULO, m.FECHA,					
      GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,'#',a.NOMBRE,' ',a.APELLIDOS) SEPARATOR '|') as AUTOR,
      CASE WHEN dm.DM_ENLAZADA is null then 0 else 1 end as ENLAZADA FROM disco d
      INNER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
      INNER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
      INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
      INNER JOIN marcha_autor am ON am.ID_MARCHA = m.ID_MARCHA
      INNER JOIN autor a ON a.ID_AUTOR = am.ID_AUTOR
      WHERE d.ID_DISCO LIKE ?
      GROUP BY dm.ID_DM ORDER BY dm.N_DISCO ASC, dm.NUMEROMARCHA ASC, dm.DM_ENLAZADA ASC;`;
    const [results_marchas] = await connection.execute(sql_marchas, params);
    results_marchas.map(r => formatAutor(r));
    const marchasLength = results_marchas.length;
    const resToSend = { ...results_disco[0], marchasLength, marchas: results_marchas};
    res.send(resToSend);
  } catch (err) {
    console.log(err);
  }
});

router.get('/search/:name', async (req, res) => {
  try {
    const { name } = req.params;
    const sql = `SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA, 
      CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA from disco d
      LEFT JOIN BANDA b ON b.ID_BANDA = d.BANDADISCO
      WHERE d.NOMBRE_CD LIKE ?
      ORDER BY d.NOMBRE_cd ASC`;
    const params = [`%${name}%`];
    const results = await resolveQuery(sql,params);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

export default router;