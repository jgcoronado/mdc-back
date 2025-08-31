import express from 'express';
import { resolveQuery, poolExecute, formatAutor } from '../helpers/index.js';

const router = express.Router();

router.get('/', ( _, res) => {
  const response = 'Allow endpoints are: /all, /:id, /search/:name .';
  res.send(response);
});

router.get('/masAutor', async (req, res) => {
  try {
    const sql = `SELECT a.ID_AUTOR, COUNT(m.ID_MARCHA) AS MARCHAS,
      CONCAT(a.NOMBRE,' ', a.APELLIDOS) AS AUTOR FROM autor a
      INNER JOIN marcha_autor am ON am.ID_AUTOR = a.ID_AUTOR 
      INNER JOIN marcha m ON m.ID_MARCHA = am.ID_MARCHA 	
      GROUP BY AUTOR ORDER BY MARCHAS DESC LIMIT 0,10`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

router.get('/masDedica', async (req, res) => {
  try {
    const sql = `SELECT COUNT(DEDICATORIA) AS CUENTA,
      CONCAT(DEDICATORIA,' (', LOCALIDAD,')') as LUGAR 
      FROM marcha WHERE DEDICATORIA LIKE '%Hdad%' GROUP BY LUGAR 
      HAVING CUENTA >= 15 ORDER BY CUENTA DESC`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

router.get('/masEstreno', async (req, res) => {
  try {
    const sql = `SELECT b.ID_BANDA, COUNT(m.BANDA_ESTRENO) AS MARCHAS,
      CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA FROM marcha m
      INNER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO 
      GROUP BY BANDA HAVING b.ID_BANDA != 0 ORDER BY MARCHAS DESC LIMIT 20`;
    const [results] = await poolExecute(sql);
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

router.get('/masGrabada', async (req, res) => {
  try {
    const sql = `SELECT COUNT(dm.IDMARCHA) AS GRABACIONES, m.ID_MARCHA, m.TITULO, 
      GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,"#", a.NOMBRE,' ',a.APELLIDOS) SEPARATOR '|') as AUTOR
      FROM disco_marcha dm INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
      INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
      INNER JOIN autor a ON a.ID_autor = ma.ID_AUTOR
		  GROUP BY dm.IDMARCHA ORDER BY GRABACIONES DESC LIMIT 20;`;
    const results = await resolveQuery(sql);
    results.data.map(r => formatAutor(r));
    res.send(results.data);
  } catch (err) {
    console.log(err);
  }
});

export default router;