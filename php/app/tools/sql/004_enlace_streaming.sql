-- Enlaces a servicios de streaming (Spotify, Apple, Deezer, YouTube, ...).
-- Modelo genérico: una fila enlaza CUALQUIER entidad (banda, disco o marcha)
-- con UN servicio. Así las 3 fases del proyecto (discos, bandas, singles/marchas)
-- y cualquier servicio nuevo caben sin volver a tocar el esquema.
--
--   TIPO_ENT = 'banda'  -> ID_ENT referencia banda(ID_BANDA)    (página de artista)
--   TIPO_ENT = 'disco'  -> ID_ENT referencia disco(ID_DISCO)    (álbum)
--   TIPO_ENT = 'marcha' -> ID_ENT referencia marcha(ID_MARCHA)  (single / pista)
--
-- Nota: marcha.AUDIO ya guarda 1 URL de YouTube por marcha. Esta tabla es
-- aditiva; la migración de esos valores a (marcha, youtube) es opcional (ver plan).
--
-- Idempotente (CREATE ... IF NOT EXISTS): seguro re-ejecutar desde migrate_ingest.php.

-- 1) Enlaces aprobados / publicados (lo que consume la ficha pública).
CREATE TABLE IF NOT EXISTS enlace_streaming (
    ID_ENLACE   INTEGER PRIMARY KEY,
    TIPO_ENT    TEXT    NOT NULL CHECK (TIPO_ENT IN ('banda','disco','marcha')),
    ID_ENT      INTEGER NOT NULL,
    SERVICIO    TEXT    NOT NULL CHECK (SERVICIO IN ('spotify','apple','deezer','youtube','tidal','amazon')),
    URL         TEXT    NOT NULL,
    ID_EXT      TEXT,                       -- id nativo del servicio (album/artist/track)
    VERIFICADO  INTEGER NOT NULL DEFAULT 1, -- 1 = revisado por admin; 0 = auto sin revisar
    FECHA_ALTA  TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (TIPO_ENT, ID_ENT, SERVICIO)     -- un enlace por entidad+servicio
);
CREATE INDEX IF NOT EXISTS idx_enl_ent ON enlace_streaming (TIPO_ENT, ID_ENT);

-- 2) Cola de candidatos pendientes de curación (igual patrón que ingest_candidato).
--    El pipeline escribe aquí; el panel admin aprueba -> pasa a enlace_streaming.
CREATE TABLE IF NOT EXISTS enlace_candidato (
    ID_CAND     INTEGER PRIMARY KEY,
    TIPO_ENT    TEXT    NOT NULL CHECK (TIPO_ENT IN ('banda','disco','marcha')),
    ID_ENT      INTEGER NOT NULL,
    SERVICIO    TEXT    NOT NULL CHECK (SERVICIO IN ('spotify','apple','deezer','youtube','tidal','amazon')),
    URL         TEXT    NOT NULL,
    ID_EXT      TEXT,
    TITULO_ENC  TEXT,                        -- título devuelto por el servicio
    ARTISTA_ENC TEXT,                        -- artista devuelto por el servicio
    ANIO_ENC    TEXT,
    SCORE       REAL    NOT NULL DEFAULT 0,
    CONFIANZA   TEXT    NOT NULL CHECK (CONFIANZA IN ('ALTA','MEDIA','BAJA','SIN_MATCH')),
    ESTADO      TEXT    NOT NULL DEFAULT 'pendiente' CHECK (ESTADO IN ('pendiente','aprobado','rechazado')),
    RUN_ID      TEXT,                         -- lote de ejecución
    FECHA       TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (TIPO_ENT, ID_ENT, SERVICIO, URL)
);
CREATE INDEX IF NOT EXISTS idx_cand_estado ON enlace_candidato (ESTADO);
CREATE INDEX IF NOT EXISTS idx_cand_ent    ON enlace_candidato (TIPO_ENT, ID_ENT);
