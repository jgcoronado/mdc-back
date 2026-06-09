-- Añade FK constraints a marcha_autor y disco_marcha, y crea tabla admin_log.
-- Ejecutar CON BACKUP PREVIO, DESPUÉS de clean-orphans.sql.
-- SQLite no soporta ALTER TABLE ADD FOREIGN KEY → se recrean las tablas.

PRAGMA foreign_keys = OFF;
BEGIN;

-- ── marcha_autor ──────────────────────────────────────────────────────────────
CREATE TABLE marcha_autor_new (
  ID_MA     INTEGER PRIMARY KEY,
  ID_MARCHA INTEGER NOT NULL REFERENCES marcha(ID_MARCHA) ON DELETE CASCADE,
  ID_AUTOR  INTEGER NOT NULL REFERENCES autor(ID_AUTOR)   ON DELETE CASCADE
);
INSERT INTO marcha_autor_new SELECT * FROM marcha_autor;
DROP TABLE marcha_autor;
ALTER TABLE marcha_autor_new RENAME TO marcha_autor;

-- ── disco_marcha ──────────────────────────────────────────────────────────────
CREATE TABLE disco_marcha_new (
  ID_DM       INTEGER PRIMARY KEY,
  ID_DISCO    INTEGER NOT NULL REFERENCES disco(ID_DISCO)   ON DELETE CASCADE,
  N_DISCO     INTEGER,
  NUMEROMARCHA INTEGER NOT NULL,
  IDMARCHA    INTEGER NOT NULL REFERENCES marcha(ID_MARCHA) ON DELETE CASCADE,
  DM_DETALLES TEXT,
  DM_BANDA    INTEGER,
  DM_ENLAZADA INTEGER
);
INSERT INTO disco_marcha_new SELECT * FROM disco_marcha;
DROP TABLE disco_marcha;
ALTER TABLE disco_marcha_new RENAME TO disco_marcha;

-- ── admin_log ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_log (
  id         INTEGER PRIMARY KEY,
  accion     TEXT    NOT NULL,
  tabla      TEXT    NOT NULL,
  id_registro INTEGER,
  usuario    TEXT,
  ts         INTEGER NOT NULL,
  payload    TEXT
);

COMMIT;
PRAGMA foreign_keys = ON;

-- Recrear índices perdidos al drop de tablas
CREATE INDEX IF NOT EXISTS idx_dm_disco   ON disco_marcha (ID_DISCO);
CREATE INDEX IF NOT EXISTS idx_dm_marcha  ON disco_marcha (IDMARCHA);
CREATE INDEX IF NOT EXISTS idx_ma_marcha  ON marcha_autor  (ID_MARCHA);
CREATE INDEX IF NOT EXISTS idx_ma_autor   ON marcha_autor  (ID_AUTOR);

-- Verificación de integridad FK (deben devolver 0 filas)
PRAGMA foreign_key_check(marcha_autor);
PRAGMA foreign_key_check(disco_marcha);
