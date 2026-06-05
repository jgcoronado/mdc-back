import { database } from '../db.js';

const WRITE_STATEMENT_REGEX = /^\s*(INSERT|UPDATE|DELETE|REPLACE)/i;

const isWriteStatement = (sql) => WRITE_STATEMENT_REGEX.test(sql);

const runWriteStatement = (sql, params) => {
  const statement = database.prepare(sql);
  const info = statement.run(...params);
  return {
    insertId: Number(info.lastInsertRowid),
    affectedRows: info.changes,
    changedRows: info.changes,
  };
};

const runReadStatement = (sql, params) => {
  const statement = database.prepare(sql);
  return statement.all(...params);
};

const resolveQuery = async (sql, params = []) => {
  const safeParams = params || [];
  const rows = runReadStatement(sql, safeParams);
  return { rowsReturned: rows.length, data: rows };
};

const poolExecute = async (sql, params = []) => {
  const safeParams = params || [];
  if (isWriteStatement(sql)) {
    const writeResult = runWriteStatement(sql, safeParams);
    return [writeResult];
  }
  const rows = runReadStatement(sql, safeParams);
  return [rows];
};

const parseAutorEntry = (entry) => {
  const [autorId, nombre] = String(entry).split('#');
  return { autorId, nombre };
};

const splitAutorString = (autorString) => {
  if (autorString === null || autorString === undefined) return [];
  const raw = String(autorString);
  return raw.includes('|') ? raw.split('|') : [raw];
};

const formatAutor = (row) => {
  const entries = splitAutorString(row.AUTOR);
  row.AUTOR = entries.map(parseAutorEntry);
  return row;
};

export { resolveQuery, poolExecute, formatAutor };
