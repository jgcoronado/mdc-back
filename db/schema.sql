-- SQLite schema extras: FTS5 virtual tables, sync triggers and indexes.
-- This file is run AFTER the migration script has created and populated
-- the base tables (marcha, autor, banda, disco, marcha_autor, disco_marcha,
-- usuarios) from MySQL. See scripts/migrate-mysql-to-sqlite.mjs.

-- ============================================================
-- FTS5: marchas (full-text on TITULO)
-- ============================================================
DROP TABLE IF EXISTS marcha_fts;
CREATE VIRTUAL TABLE marcha_fts USING fts5(
  titulo,
  content='marcha',
  content_rowid='ID_MARCHA',
  tokenize="unicode61 remove_diacritics 2"
);

INSERT INTO marcha_fts(rowid, titulo)
  SELECT ID_MARCHA, TITULO FROM marcha;

DROP TRIGGER IF EXISTS marcha_ai;
CREATE TRIGGER marcha_ai AFTER INSERT ON marcha BEGIN
  INSERT INTO marcha_fts(rowid, titulo) VALUES (new.ID_MARCHA, new.TITULO);
END;

DROP TRIGGER IF EXISTS marcha_ad;
CREATE TRIGGER marcha_ad AFTER DELETE ON marcha BEGIN
  INSERT INTO marcha_fts(marcha_fts, rowid, titulo)
    VALUES('delete', old.ID_MARCHA, old.TITULO);
END;

DROP TRIGGER IF EXISTS marcha_au;
CREATE TRIGGER marcha_au AFTER UPDATE ON marcha BEGIN
  INSERT INTO marcha_fts(marcha_fts, rowid, titulo)
    VALUES('delete', old.ID_MARCHA, old.TITULO);
  INSERT INTO marcha_fts(rowid, titulo)
    VALUES (new.ID_MARCHA, new.TITULO);
END;

-- ============================================================
-- FTS5: autores (full-text on NOMBRE, APELLIDOS, NOMBRE_ART)
-- ============================================================
DROP TABLE IF EXISTS autor_fts;
CREATE VIRTUAL TABLE autor_fts USING fts5(
  nombre,
  apellidos,
  nombre_art,
  content='autor',
  content_rowid='ID_AUTOR',
  tokenize="unicode61 remove_diacritics 2"
);

INSERT INTO autor_fts(rowid, nombre, apellidos, nombre_art)
  SELECT ID_AUTOR, NOMBRE, APELLIDOS, NOMBRE_ART FROM autor;

DROP TRIGGER IF EXISTS autor_ai;
CREATE TRIGGER autor_ai AFTER INSERT ON autor BEGIN
  INSERT INTO autor_fts(rowid, nombre, apellidos, nombre_art)
    VALUES (new.ID_AUTOR, new.NOMBRE, new.APELLIDOS, new.NOMBRE_ART);
END;

DROP TRIGGER IF EXISTS autor_ad;
CREATE TRIGGER autor_ad AFTER DELETE ON autor BEGIN
  INSERT INTO autor_fts(autor_fts, rowid, nombre, apellidos, nombre_art)
    VALUES('delete', old.ID_AUTOR, old.NOMBRE, old.APELLIDOS, old.NOMBRE_ART);
END;

DROP TRIGGER IF EXISTS autor_au;
CREATE TRIGGER autor_au AFTER UPDATE ON autor BEGIN
  INSERT INTO autor_fts(autor_fts, rowid, nombre, apellidos, nombre_art)
    VALUES('delete', old.ID_AUTOR, old.NOMBRE, old.APELLIDOS, old.NOMBRE_ART);
  INSERT INTO autor_fts(rowid, nombre, apellidos, nombre_art)
    VALUES (new.ID_AUTOR, new.NOMBRE, new.APELLIDOS, new.NOMBRE_ART);
END;

-- ============================================================
-- Indexes that were missing in the original MySQL schema
-- (see docs/roadmap.md §4 and docs/db-analysis.md)
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_dm_disco ON disco_marcha (ID_DISCO);
CREATE INDEX IF NOT EXISTS idx_dm_marcha ON disco_marcha (IDMARCHA);
CREATE INDEX IF NOT EXISTS idx_disco_banda ON disco (BANDADISCO);
CREATE INDEX IF NOT EXISTS idx_marcha_banda_estreno ON marcha (BANDA_ESTRENO);
CREATE INDEX IF NOT EXISTS idx_ma_marcha ON marcha_autor (ID_MARCHA);
CREATE INDEX IF NOT EXISTS idx_ma_autor ON marcha_autor (ID_AUTOR);
CREATE INDEX IF NOT EXISTS idx_banda_formacion_ant ON banda (FORMACION_ANT);
CREATE INDEX IF NOT EXISTS idx_banda_formacion_sig ON banda (FORMACION_SIG);
