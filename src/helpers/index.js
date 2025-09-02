import db from '../db.js';

const resolveQuery = async (sql, params) => {
    const conn = await db.pool.getConnection();
    const [queryResults] = await conn.execute(sql, params);
    db.pool.releaseConnection(conn);
    const queryRows = queryResults.length;
    return { rowsReturned: queryRows, data: queryResults };
};

const poolExecute = async (sql, params) => {
  const conn = await db.pool.getConnection().catch(err => console.log("getConnection error", err));
  const result = await conn.execute(sql, params);
  db.pool.releaseConnection(conn);
  return result;  
}

const mapIdAutor = autor => {
  const result = [];
  autor.map(a => {
    const aut = a.split('#')
    result.push({
      autorId: aut[0],
      nombre: aut[1]
    });
  })
  return result;
};

const formatAutor = query => {
  let { AUTOR: autor} = query;
  const aut = autor.includes('|') 
    ? autor = autor.split("|")
    : [autor];
  const autorMap = mapIdAutor(aut);
  query.AUTOR = autorMap;
  return query;
}

export { resolveQuery, poolExecute, formatAutor };