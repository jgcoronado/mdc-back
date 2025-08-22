import connection from '../db.js';

const resolveQuery = async (sql, params) => {
    const [queryResults] = await connection.execute(sql, params);
    const queryRows = queryResults.length;
    return { rowsReturned: queryRows, data: queryResults };
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

export { resolveQuery, formatAutor };