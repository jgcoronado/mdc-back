import 'dotenv/config';
import path from 'node:path';
import fs from 'node:fs';
import Database from 'better-sqlite3';

const DEFAULT_DB_PATH = path.resolve(process.cwd(), 'data', 'mdc.db');

const resolveDbPath = () => {
  const fromEnv = process.env.DB_PATH;
  return fromEnv && fromEnv.trim() ? fromEnv.trim() : DEFAULT_DB_PATH;
};

const ensureDbDirExists = (dbFilePath) => {
  const dirName = path.dirname(dbFilePath);
  fs.mkdirSync(dirName, { recursive: true });
};

const dbFilePath = resolveDbPath();
ensureDbDirExists(dbFilePath);

const database = new Database(dbFilePath, {
  readonly: false,
  fileMustExist: false,
});

database.pragma('journal_mode = WAL');
database.pragma('foreign_keys = ON');
database.pragma('busy_timeout = 5000');

export default { database, dbFilePath };
export { database, dbFilePath };
