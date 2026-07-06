-- ─────────────────────────────────────────────────────────────────────────────
-- Ingesta de marchas desde YouTube — tablas de staging (Fase 0)
--
-- Fuente de verdad del esquema de la herramienta de ingesta. Se aplica con:
--   php app/tools/migrate_ingest.php
-- Es idempotente (CREATE ... IF NOT EXISTS), así que se puede re-ejecutar sin
-- riesgo tanto en local como en el host (HelioHost, SQLite 3.34.1 → sin STRICT
-- ni AUTOINCREMENT; se usa INTEGER PRIMARY KEY como alias de rowid, igual que el
-- resto del esquema en marcha/autor/banda).
--
-- Flujo: el extractor Node (offline) emite candidatos → se importan aquí →
-- el panel PHP los revisa → al aceptar se insertan en `marcha`/`marcha_autor`.
-- ─────────────────────────────────────────────────────────────────────────────

-- Mapeo banda ↔ canal de YouTube. `banda` no tiene campo de canal, así que el
-- usuario carga aquí a mano los canales de las bandas que le interesan.
CREATE TABLE IF NOT EXISTS ingest_canal (
  ID_CANAL    INTEGER PRIMARY KEY,
  ID_BANDA    INTEGER NOT NULL REFERENCES banda(ID_BANDA) ON DELETE CASCADE,
  CANAL_URL   TEXT NOT NULL,                 -- URL tal cual la pega el usuario (/@handle, /channel/UC..., /c/...)
  CANAL_ID    TEXT,                          -- UC... resuelto por yt-dlp (NULL hasta la 1ª pasada)
  HANDLE      TEXT,                          -- @handle si aplica
  ACTIVO      INTEGER NOT NULL DEFAULT 1,    -- 0 para pausar un canal sin borrarlo
  DESDE_ANIO  INTEGER NOT NULL DEFAULT 2019, -- año mínimo a ingerir para este canal
  NOTAS       TEXT,
  CREATED_AT  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_ingest_canal_banda_url ON ingest_canal (ID_BANDA, CANAL_URL);
CREATE INDEX IF NOT EXISTS ix_ingest_canal_activo ON ingest_canal (ACTIVO);

-- Traza de cada ejecución del extractor (para métricas y auditoría de lotes).
CREATE TABLE IF NOT EXISTS ingest_run (
  ID_RUN       INTEGER PRIMARY KEY,
  STARTED_AT   TEXT NOT NULL DEFAULT (datetime('now')),
  FINISHED_AT  TEXT,
  FUENTE       TEXT NOT NULL DEFAULT 'yt-dlp',
  N_CANALES    INTEGER,
  N_VIDEOS     INTEGER,
  N_CANDIDATOS INTEGER,
  NOTAS        TEXT
);

-- Staging de marchas propuestas. Un candidato = un vídeo que el clasificador
-- considera estreno/novedad/recuperación. Los campos P_* son la propuesta que
-- el revisor puede editar antes de aceptar.
CREATE TABLE IF NOT EXISTS ingest_candidato (
  ID_CAND         INTEGER PRIMARY KEY,
  ID_RUN          INTEGER REFERENCES ingest_run(ID_RUN) ON DELETE SET NULL,
  ID_BANDA        INTEGER REFERENCES banda(ID_BANDA)    ON DELETE SET NULL,

  -- Datos del vídeo (crudos de YouTube)
  VIDEO_ID        TEXT NOT NULL,             -- id de YouTube (clave natural, único)
  VIDEO_URL       TEXT NOT NULL,
  VIDEO_TITULO    TEXT,
  VIDEO_DESC      TEXT,
  PUBLICADO_AT    TEXT,                      -- fecha ISO 8601 de publicación
  DURACION_SEG    INTEGER,

  -- Clasificación heurística
  CLASIFICACION   TEXT,                      -- estreno | novedad | recuperacion | otro
  CONFIANZA       REAL,                      -- 0..1
  FLAGS           TEXT,                      -- JSON: motivos de "revisar" / avisos

  -- Campos propuestos de marcha (editables en el panel)
  P_TITULO        TEXT,
  P_FECHA         INTEGER,                   -- año (4 dígitos)
  P_DEDICATORIA   TEXT,
  P_LOCALIDAD     TEXT,
  P_PROVINCIA     TEXT,
  P_AUTORES       TEXT,                      -- nombres de compositor(es); se resuelven a ID_AUTOR al aceptar
  P_BANDA_ESTRENO INTEGER,                   -- normalmente = ID_BANDA

  -- Dedup contra la BD existente
  MATCH_MARCHA_ID INTEGER REFERENCES marcha(ID_MARCHA) ON DELETE SET NULL,
  MATCH_SCORE     REAL,                      -- similitud 0..1 con la marcha encontrada

  -- Estado del ciclo de revisión
  ESTADO          TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | aceptado | descartado | duplicado
  MOTIVO          TEXT,                      -- motivo de descarte / nota del revisor
  MARCHA_CREADA   INTEGER REFERENCES marcha(ID_MARCHA) ON DELETE SET NULL, -- id resultante al aceptar

  RAW_JSON        TEXT,                      -- metadatos completos del vídeo (por si hay que reparsear)
  CREATED_AT      TEXT NOT NULL DEFAULT (datetime('now')),
  REVIEWED_AT     TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_ingest_cand_video  ON ingest_candidato (VIDEO_ID);
CREATE INDEX        IF NOT EXISTS ix_ingest_cand_estado ON ingest_candidato (ESTADO);
CREATE INDEX        IF NOT EXISTS ix_ingest_cand_banda  ON ingest_candidato (ID_BANDA);
CREATE INDEX        IF NOT EXISTS ix_ingest_cand_run    ON ingest_candidato (ID_RUN);
