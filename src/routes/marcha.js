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

router.get('/search', async (req, res) => {
  try {
    const { titulo, fecha, dedicatoria, localidad, provincia } = req.query;
    const sql_search = [];
    const params = [];

    if(titulo) {
      sql_search.push(`MATCH(m.TITULO) AGAINST(? IN NATURAL LANGUAGE MODE)`);
      params.push(`%${titulo}%`);
    }
    if(fecha) {
      sql_search.push(`m.FECHA LIKE ?`);
      params.push(`%${fecha}%`);
    }
    if(dedicatoria) {
      sql_search.push(`m.DEDICATORIA LIKE ?`);
      params.push(`%${dedicatoria}%`);
    }
    if(localidad) {
      sql_search.push(`m.LOCALIDAD LIKE ?`);
      params.push(`%${localidad}%`);
    }
    if(provincia) {
      sql_search.push(`m.PROVINCIA LIKE ?`);
      params.push(`%${provincia}%`);
    }
    const sql_head = `SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA, 
        GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,"#", a.NOMBRE,' ',a.APELLIDOS) SEPARATOR '|') as AUTOR,
        CASE WHEN dm.IDMARCHA is not null then 1 else 0 end as GRABADA
        FROM marcha m
        INNER JOIN marcha_autor ma 
        ON ma.ID_MARCHA = m.ID_MARCHA
        INNER JOIN autor a
        ON a.ID_AUTOR = ma.ID_AUTOR
        LEFT OUTER JOIN disco_marcha dm 
        ON dm.IDMARCHA = m.ID_MARCHA WHERE `;
    const sql_tail = ` GROUP BY m.ID_MARCHA ORDER BY m.TITULO ASC`;
    const sql = sql_head.concat(sql_search.join(' AND ')).concat(sql_tail);
    console.log("🚀 ~ sql:", sql)
    const results = await resolveQuery(sql,params);
    results.data.map(r => formatAutor(r));
    res.send(results);
  } catch (err) {
    console.log(err);
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const sql = `SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA, 
        GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,"#", a.NOMBRE,' ', a.APELLIDOS) SEPARATOR '|') as AUTOR,
        m.BANDA_ESTRENO, CONCAT (b.NOMBRE_BREVE,' (',b.LOCALIDAD,')') as BANDA FROM marcha m
        INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
        INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
        LEFT OUTER JOIN disco_marcha dm ON dm.IDMARCHA = m.ID_MARCHA
        LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
        WHERE m.ID_MARCHA LIKE ?
        GROUP BY m.ID_MARCHA`;
    const params = [id];
    console.log("🚀 ~ params:", params)
    const [results] = await connection.execute(sql, params);
    console.log("🚀 ~ results:", results)
    if (results.length === 0) res.send([]);
    const res_marcha = formatAutor(results[0]);
    const sql_discos = `SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
      CONCAT (b.NOMBRE_BREVE,' (',b.LOCALIDAD,')') as BANDA FROM disco d
      LEFT OUTER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
      LEFT OUTER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
      WHERE dm.IDMARCHA LIKE ?
      ORDER BY d.FECHA_CD ASC`;
    const [results_disco] = await connection.execute(sql_discos, params);
    const discosLength = results_disco.length;
    const resToSend = { ...res_marcha, discosLength, discos: results_disco};
    res.send(resToSend);
  } catch (err) {
    console.log(err);
  }
});

// router.get('/search/:name', async (req, res) => {
//   try {
//     const { name } = req.params;
//     const sql = `SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA, 
//         GROUP_CONCAT(DISTINCT CONCAT(a.ID_AUTOR,"#", a.NOMBRE,' ',a.APELLIDOS) SEPARATOR '|') as AUTOR,
//         CASE WHEN dm.IDMARCHA is not null then 1 else 0 end as GRABADA
//         FROM marcha m
//         INNER JOIN marcha_autor ma 
//         ON ma.ID_MARCHA = m.ID_MARCHA
//         INNER JOIN autor a
//         ON a.ID_AUTOR = ma.ID_AUTOR
//         LEFT OUTER JOIN disco_marcha dm 
//         ON dm.IDMARCHA = m.ID_MARCHA
//         WHERE MATCH(m.TITULO)
//         AGAINST(? IN NATURAL LANGUAGE MODE)
//         GROUP BY m.ID_MARCHA
//         ORDER BY m.TITULO ASC`;
//     const params = [`%${name}%`];
//     const results = await resolveQuery(sql,params);
//     results.data.map(r => formatAutor(r));
//     res.send(results);
//   } catch (err) {
//     console.log(err);
//   }
// });

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