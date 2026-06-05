import { database } from '../db.js';

const WRITE_STATEMENT_REGEX = /^\s*(INSERT|UPDATE|DELETE|REPLACE)/i;

const isWriteStatement = (sql) => WRITE_STATEMENT_REGEX.test(sql);

const runAdminWrite = (sql, params) => {
  const statement = database.prepare(sql);
  const info = statement.run(...params);
  return {
    insertId: Number(info.lastInsertRowid),
    affectedRows: info.changes,
    changedRows: info.changes,
  };
};

const runAdminRead = (sql, params) => {
  const statement = database.prepare(sql);
  return statement.all(...params);
};

const resolveQueryAdmin = async (sql, params = []) => {
  const safeParams = params || [];
  const rows = runAdminRead(sql, safeParams);
  return { rowsReturned: rows.length, data: rows };
};

const poolExecuteAdmin = async (sql, params = []) => {
  const safeParams = params || [];
  if (isWriteStatement(sql)) {
    const writeResult = runAdminWrite(sql, safeParams);
    return [writeResult];
  }
  const rows = runAdminRead(sql, safeParams);
  return [rows];
};

export { resolveQueryAdmin, poolExecuteAdmin };
