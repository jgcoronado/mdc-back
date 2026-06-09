import 'server-only';
import path from 'node:path';
import type { Database as DB } from 'better-sqlite3';

// Lazy singleton — not opened until first query so next build succeeds without a real DB file.
let _db: DB | null = null;

function getDb(): DB {
  if (_db) return _db;
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const Database = require('better-sqlite3') as typeof import('better-sqlite3');
  const dbPath = process.env.DB_PATH ?? path.resolve(process.cwd(), '../data/mdc.db');
  _db = new Database(dbPath) as DB;
  _db.pragma('journal_mode = WAL');
  _db.pragma('foreign_keys = ON');
  _db.pragma('busy_timeout = 5000');
  return _db;
}

export function dbAll<T = Record<string, unknown>>(sql: string, params: unknown[] = []): T[] {
  return getDb().prepare(sql).all(...params) as T[];
}

export function dbRun(sql: string, params: unknown[] = []) {
  return getDb().prepare(sql).run(...params);
}

export function dbTransaction<T>(fn: () => T): T {
  return getDb().transaction(fn)();
}

export function logAdmin(accion: string, tabla: string, id_registro: number | null, payload?: unknown) {
  dbRun(
    'INSERT INTO admin_log (accion, tabla, id_registro, usuario, ts, payload) VALUES (?, ?, ?, ?, ?, ?)',
    [accion, tabla, id_registro, 'admin', Math.floor(Date.now() / 1000), payload != null ? JSON.stringify(payload) : null]
  );
}

export function formatAutor<T extends { AUTOR?: unknown }>(row: T): T {
  if (row.AUTOR == null) { row.AUTOR = []; return row; }
  row.AUTOR = JSON.parse(row.AUTOR as string);
  return row;
}
