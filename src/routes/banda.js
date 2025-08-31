import db from '../db.js';
import express from 'express';
import { poolExecute, formatAutor, resolveQuery } from '../helpers/index.js';

const router = express.Router();

const getTimeline = async banda => {
  const timeline = [];
  const { ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE } = banda;
  timeline.push({ ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE });
  let idAnt = banda.FORMACION_ANT;
  let idSig = banda.FORMACION_SIG;
  [[idAnt, 'ANT'], [idSig,'SIG']].forEach(async e => {
    let [id, _] = e;
    while(id) {
      const sql = `SELECT b.ID_BANDA, b.FORMACION_${e[1]}, b.FORMACION_${e[1]}2,
        b.NOMBRE_BREVE, b.FECHA_FUND, b.FECHA_EXT
        FROM banda b WHERE b.ID_BANDA = ${id}`;
      const [results, fields] = await db.pool.query(sql);
      if(fields.length > 0) {
        const { ID_BANDA, FECHA_FUND, FECHA_EXT, NOMBRE_BREVE } = results[0];
        timeline.push({
          ID_BANDA,
          FECHA_FUND,
          FECHA_EXT,
          NOMBRE_BREVE,
      });
      }
      const name = `FORMACION_${e[1]}`;
      const {[name]: form} = results[0];
      id = form;
    }
  });
  return timeline;
};

router.get('/', ( _, res) => {
    const response = 'Allow endpoints are: /all, /:id, /search/:name .';
    res.send(response);
});

router.get('/all', async (_, res) => {
  try {
    const [results, fields] = await db.connection.query(
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
    const { titulo, localidad, provincia } = req.query;
    const sql_search = [];
    const params = [];

    if(titulo) {
      sql_search.push(`b.NOMBRE_COMPLETO LIKE ?`);
      params.push(`%${titulo}%`);
    }
    if(localidad) {
      sql_search.push(`b.LOCALIDAD LIKE ?`);
      params.push(`%${localidad}%`);
    }
    if(provincia) {
      sql_search.push(`b.PROVINCIA LIKE ?`);
      params.push(`%${provincia}%`);
    }
    const sql_head = `SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.PROVINCIA,
      b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT, CONCAT(b.NOMBRE_BREVE,' (', b.LOCALIDAD,')') as BANDA
      FROM banda b WHERE `;
    const sql_tail = ` GROUP BY b.ID_BANDA ORDER BY b.NOMBRE_BREVE ASC`;
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
    const sql_autor = `SELECT * from banda
      WHERE banda.ID_BANDA LIKE ?`;
    const params = [id];
    const [results_banda] = await poolExecute(sql_autor, params);
    const autor = results_banda[0];
    const timeline = await getTimeline(autor);
    if (results_banda.length === 0) res.send([]);
    const sql_discos = `SELECT DISTINCT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD,
      (SELECT COUNT(m.ID_DM) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) as PISTAS,
      (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) as DISCOS from disco d
      WHERE d.BANDADISCO LIKE ? ORDER BY d.FECHA_CD ASC`;
    const [results_discos] = await poolExecute(sql_discos, params);
    const discosLength = results_discos.length;
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
    const [results_marchas] = await poolExecute(sql_estrenos, params);
    results_marchas.map(r => formatAutor(r));
    const marchasLength = results_marchas.length;
    timeline.sort((a, b) => a.FECHA_FUND - b.FECHA_FUND);
    const resToSend = {
      ...autor,
      timeline,
      discosLength,
      discos: results_discos,
      marchasLength,
      marchas: results_marchas,
    };
    res.send(resToSend);
  } catch (err) {
    console.log(err);
  }
});

export default router;