#!/usr/bin/env node
/**
 * Migrate data from the existing MySQL database to a fresh SQLite file.
 *
 * Flow:
 *   1. Connect to MySQL using the same env vars the app uses.
 *   2. For each table in TABLES_TO_MIGRATE: introspect columns and
 *      create the SQLite table preserving column names.
 *   3. Stream rows in batches and INSERT them into SQLite.
 *   4. Run db/schema.sql to add FTS5 tables, triggers and indexes.
 *
 * Usage:
 *   node scripts/migrate-mysql-to-sqlite.mjs [--out path/to/mdc.db]
 *
 * Re-running drops and recreates everything in the target file.
 */

import 'dotenv/config';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createConnection } from 'mysql2/promise';
import Database from 'better-sqlite3';

const TABLES_TO_MIGRATE = [
  'autor',
  'banda',
  'disco',
  'marcha',
  'marcha_autor',
  'disco_marcha',
  'usuarios',
];

const BATCH_SIZE = 500;

const projectRoot = path.resolve(fileURLToPath(import.meta.url), '..', '..');
const schemaExtrasPath = path.join(projectRoot, 'db', 'schema.sql');

const parseCliArgs = (argv) => {
  const args = { out: path.join(projectRoot, 'data', 'mdc.db') };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--out' && argv[index + 1]) {
      args.out = path.resolve(argv[index + 1]);
    }
  }
  return args;
};

const mapMysqlTypeToSqlite = (mysqlType) => {
  const normalized = String(mysqlType || '').toLowerCase();
  if (normalized.startsWith('int') || normalized.startsWith('tinyint')
      || normalized.startsWith('smallint') || normalized.startsWith('mediumint')
      || normalized.startsWith('bigint')) {
    return 'INTEGER';
  }
  if (normalized.startsWith('decimal') || normalized.startsWith('float')
      || normalized.startsWith('double') || normalized.startsWith('numeric')) {
    return 'REAL';
  }
  if (normalized.startsWith('blob') || normalized.startsWith('binary')
      || normalized.startsWith('varbinary')) {
    return 'BLOB';
  }
  return 'TEXT';
};

const buildCreateTableSql = (tableName, columns) => {
  const columnDefs = columns.map((col) => {
    const sqliteType = mapMysqlTypeToSqlite(col.Type);
    const isPrimaryKey = col.Key === 'PRI';
    if (isPrimaryKey && sqliteType === 'INTEGER') {
      return `${col.Field} INTEGER PRIMARY KEY`;
    }
    const nullable = col.Null === 'YES' ? '' : ' NOT NULL';
    return `${col.Field} ${sqliteType}${nullable}`;
  });
  return `CREATE TABLE ${tableName} (\n  ${columnDefs.join(',\n  ')}\n);`;
};

const introspectTable = async (mysqlConn, tableName) => {
  const [columns] = await mysqlConn.execute(`SHOW COLUMNS FROM \`${tableName}\``);
  return columns;
};

const fetchAllRows = async (mysqlConn, tableName) => {
  const [rows] = await mysqlConn.query(`SELECT * FROM \`${tableName}\``);
  return rows;
};

const normalizeRowValue = (value) => {
  if (value === undefined) return null;
  if (value instanceof Date) return value.toISOString();
  if (Buffer.isBuffer(value)) return value.toString('utf8');
  return value;
};

const insertRowsInBatches = (sqliteDb, tableName, columnNames, rows) => {
  if (rows.length === 0) return 0;
  const placeholders = columnNames.map(() => '?').join(', ');
  const insertSql = `INSERT INTO ${tableName} (${columnNames.join(', ')}) VALUES (${placeholders})`;
  const insertStmt = sqliteDb.prepare(insertSql);

  const runBatch = sqliteDb.transaction((batch) => {
    for (const row of batch) {
      const params = columnNames.map((name) => normalizeRowValue(row[name]));
      insertStmt.run(params);
    }
  });

  let inserted = 0;
  for (let start = 0; start < rows.length; start += BATCH_SIZE) {
    const batch = rows.slice(start, start + BATCH_SIZE);
    runBatch(batch);
    inserted += batch.length;
  }
  return inserted;
};

const migrateSingleTable = async (mysqlConn, sqliteDb, tableName) => {
  const columns = await introspectTable(mysqlConn, tableName);
  const createTableSql = buildCreateTableSql(tableName, columns);
  sqliteDb.exec(`DROP TABLE IF EXISTS ${tableName};`);
  sqliteDb.exec(createTableSql);

  const rows = await fetchAllRows(mysqlConn, tableName);
  const columnNames = columns.map((col) => col.Field);
  const inserted = insertRowsInBatches(sqliteDb, tableName, columnNames, rows);
  return { tableName, columnCount: columns.length, rowCount: inserted };
};

const ensureOutputDirExists = (outFilePath) => {
  const outDir = path.dirname(outFilePath);
  fs.mkdirSync(outDir, { recursive: true });
};

const openFreshSqliteDb = (outFilePath) => {
  if (fs.existsSync(outFilePath)) {
    fs.unlinkSync(outFilePath);
  }
  const sqliteDb = new Database(outFilePath);
  sqliteDb.pragma('journal_mode = WAL');
  sqliteDb.pragma('foreign_keys = OFF');
  return sqliteDb;
};

const applySchemaExtras = (sqliteDb) => {
  const schemaSql = fs.readFileSync(schemaExtrasPath, 'utf8');
  sqliteDb.exec(schemaSql);
};

const buildMysqlConnection = async () => {
  return createConnection({
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    charset: 'utf8mb4',
    multipleStatements: false,
  });
};

const logResult = (results, outFilePath) => {
  console.log('\nMigration complete.');
  console.log(`Target file: ${outFilePath}`);
  console.log('Tables migrated:');
  for (const result of results) {
    console.log(
      `  - ${result.tableName.padEnd(15)} ${String(result.rowCount).padStart(7)} rows / ${result.columnCount} cols`
    );
  }
};

const main = async () => {
  const { out: outFilePath } = parseCliArgs(process.argv.slice(2));
  ensureOutputDirExists(outFilePath);

  console.log(`Connecting to MySQL at ${process.env.DB_HOST}:${process.env.DB_PORT || 3306}...`);
  const mysqlConn = await buildMysqlConnection();
  const sqliteDb = openFreshSqliteDb(outFilePath);

  try {
    const results = [];
    for (const tableName of TABLES_TO_MIGRATE) {
      console.log(`Migrating ${tableName}...`);
      const result = await migrateSingleTable(mysqlConn, sqliteDb, tableName);
      results.push(result);
    }

    console.log('Applying FTS5 schema extras...');
    applySchemaExtras(sqliteDb);

    logResult(results, outFilePath);
  } finally {
    await mysqlConn.end();
    sqliteDb.close();
  }
};

main().catch((err) => {
  console.error('Migration failed:', err);
  process.exit(1);
});
