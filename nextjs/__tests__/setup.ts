import { vi, beforeAll } from 'vitest';
import crypto from 'node:crypto';

// Must be hoisted — prevents server-only from throwing outside Next.js runtime
vi.mock('server-only', () => ({}));
// revalidatePath only works in Next.js runtime context
vi.mock('next/cache', () => ({ revalidatePath: vi.fn() }));

import { dbRun } from '@/lib/db';

beforeAll(() => {
  // ── Schema ────────────────────────────────────────────────────────────────
  dbRun(`CREATE TABLE IF NOT EXISTS usuarios (usuario TEXT PRIMARY KEY, clave TEXT NOT NULL)`);
  dbRun(`CREATE TABLE IF NOT EXISTS marcha (
    ID_MARCHA INTEGER PRIMARY KEY AUTOINCREMENT, TITULO TEXT, FECHA INTEGER,
    DEDICATORIA TEXT, LOCALIDAD TEXT, PROVINCIA TEXT,
    BANDA_ESTRENO INTEGER DEFAULT 0, DETALLES_MARCHA TEXT, AUDIO TEXT)`);
  dbRun(`CREATE TABLE IF NOT EXISTS autor (
    ID_AUTOR INTEGER PRIMARY KEY AUTOINCREMENT, NOMBRE TEXT, APELLIDOS TEXT,
    NOMBRE_ART TEXT, F_NAC TEXT, LUGAR_NAC TEXT, F_DEF TEXT, BIO TEXT)`);
  dbRun(`CREATE TABLE IF NOT EXISTS banda (
    ID_BANDA INTEGER PRIMARY KEY AUTOINCREMENT, NOMBRE_BREVE TEXT, NOMBRE_COMPLETO TEXT,
    LOCALIDAD TEXT, PROVINCIA TEXT, FECHA_FUND INTEGER, FECHA_EXT INTEGER,
    FORMACION_ANT INTEGER, FORMACION_SIG INTEGER)`);
  dbRun(`CREATE TABLE IF NOT EXISTS disco (
    ID_DISCO INTEGER PRIMARY KEY AUTOINCREMENT, NOMBRE_CD TEXT,
    FECHA_CD INTEGER, BANDADISCO INTEGER, d_DETALLES TEXT)`);
  dbRun(`CREATE TABLE IF NOT EXISTS marcha_autor (ID_MARCHA INTEGER, ID_AUTOR INTEGER)`);
  dbRun(`CREATE TABLE IF NOT EXISTS disco_marcha (
    ID_DISCO INTEGER, IDMARCHA INTEGER, N_DISCO INTEGER,
    NUMEROMARCHA INTEGER, DM_ENLAZADA INTEGER)`);
  dbRun(`CREATE TABLE IF NOT EXISTS admin_log (
    id INTEGER PRIMARY KEY, accion TEXT NOT NULL, tabla TEXT NOT NULL,
    id_registro INTEGER, usuario TEXT, ts INTEGER NOT NULL, payload TEXT)`);
  dbRun(`CREATE VIRTUAL TABLE IF NOT EXISTS marcha_fts USING fts5(
    titulo, content='marcha', content_rowid='ID_MARCHA',
    tokenize="unicode61 remove_diacritics 2")`);
  dbRun(`CREATE VIRTUAL TABLE IF NOT EXISTS autor_fts USING fts5(
    nombre, apellidos, nombre_art, content='autor', content_rowid='ID_AUTOR',
    tokenize="unicode61 remove_diacritics 2")`);

  // ── Seed ──────────────────────────────────────────────────────────────────
  // MD5 hash of 'password' — login handler supports MD5 for legacy accounts
  dbRun(`INSERT OR REPLACE INTO usuarios(usuario, clave) VALUES('admin', ?)`,
    [crypto.createHash('md5').update('password').digest('hex')]);

  dbRun(`INSERT INTO autor(ID_AUTOR, NOMBRE, APELLIDOS, NOMBRE_ART) VALUES(1,'Juan','García','JG')`);
  dbRun(`INSERT INTO autor(ID_AUTOR, NOMBRE, APELLIDOS, NOMBRE_ART) VALUES(2,'María','López',NULL)`);

  dbRun(`INSERT INTO banda(ID_BANDA, NOMBRE_BREVE, NOMBRE_COMPLETO, LOCALIDAD)
    VALUES(1,'Banda Test','Banda Test Completo','Sevilla')`);
  dbRun(`INSERT INTO disco(ID_DISCO, NOMBRE_CD, FECHA_CD, BANDADISCO) VALUES(1,'Disco Test',2020,1)`);

  dbRun(`INSERT INTO marcha(ID_MARCHA, TITULO, FECHA, LOCALIDAD, PROVINCIA, BANDA_ESTRENO)
    VALUES(1,'Marcha Test',2020,'Sevilla','Sevilla',1)`);
  dbRun(`INSERT INTO marcha(ID_MARCHA, TITULO, FECHA, LOCALIDAD)
    VALUES(999,'Marcha Editable',2019,'Córdoba')`);

  dbRun(`INSERT INTO marcha_autor(ID_MARCHA, ID_AUTOR) VALUES(1,1)`);
  dbRun(`INSERT INTO marcha_autor(ID_MARCHA, ID_AUTOR) VALUES(999,1)`);

  // FTS seed (bypasses triggers since we insert directly)
  dbRun(`INSERT INTO marcha_fts(rowid, titulo) VALUES(1,'Marcha Test')`);
  dbRun(`INSERT INTO marcha_fts(rowid, titulo) VALUES(999,'Marcha Editable')`);
  dbRun(`INSERT INTO autor_fts(rowid, nombre, apellidos, nombre_art) VALUES(1,'Juan','García','JG')`);
  dbRun(`INSERT INTO autor_fts(rowid, nombre, apellidos, nombre_art) VALUES(2,'María','López','')`);
});
