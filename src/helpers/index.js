import db from '../db.js';

const resolveQuery = async (sql, params) => {
    const conn = await db.pool.getConnection();
    try {
      const [queryResults] = await conn.execute(sql, params);
      const queryRows = queryResults.length;
      return { rowsReturned: queryRows, data: queryResults };
    } finally {
      db.pool.releaseConnection(conn);
    }
};

const poolExecute = async (sql, params = []) => {
  const conn = await db.pool.getConnection();
  try {
    const result = await conn.execute(sql, params);
    return result;
  } finally {
    db.pool.releaseConnection(conn);
  }
};

const parsePositiveInt = (value) => {
  const parsed = Number.parseInt(value, 10);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    return null;
  }
  return parsed;
};

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

export { resolveQuery, poolExecute, formatAutor, parsePositiveInt };
