-- Relaciones tipadas entre bandas. Reemplaza el modelo de lista enlazada
-- FORMACION_ANT / FORMACION_SIG (+ los slots -2, que estaban sin usar).
--
-- Una fila = un vínculo dirigido ORIGEN -> DESTINO. El significado depende de TIPO:
--   renombrado : formación anterior -> formación nueva            (1->1)
--   fusion     : cada banda que se une -> formación resultante    (N->1)
--   division   : banda que se rompe   -> cada formación nueva      (1->N)
--   juvenil    : banda madre          -> banda juvenil            (usa FECHA_INICIO/FIN)
--
-- Consultas típicas:
--   linaje hacia atrás  : WHERE ID_DESTINO = ? AND TIPO IN ('renombrado','fusion','division')
--   linaje hacia delante: WHERE ID_ORIGEN  = ? AND TIPO IN ('renombrado','fusion','division')
--   juveniles de una banda: WHERE ID_ORIGEN  = ? AND TIPO = 'juvenil'
--   madre de una juvenil  : WHERE ID_DESTINO = ? AND TIPO = 'juvenil'
--
-- Idempotente (CREATE ... IF NOT EXISTS): seguro re-ejecutar desde migrate_ingest.php.
CREATE TABLE IF NOT EXISTS banda_relacion (
    ID_RELACION  INTEGER PRIMARY KEY,
    ID_ORIGEN    INTEGER NOT NULL REFERENCES banda(ID_BANDA),
    ID_DESTINO   INTEGER NOT NULL REFERENCES banda(ID_BANDA),
    TIPO         TEXT    NOT NULL CHECK (TIPO IN ('renombrado','fusion','division','juvenil')),
    FECHA_INICIO INTEGER,          -- año del evento (sucesión) o inicio del vínculo (juvenil)
    FECHA_FIN    INTEGER,          -- año; sólo juvenil. NULL = vigente
    NOTA         TEXT,
    CHECK (ID_ORIGEN <> ID_DESTINO),
    UNIQUE (ID_ORIGEN, ID_DESTINO, TIPO, FECHA_INICIO)
);

CREATE INDEX IF NOT EXISTS idx_rel_origen  ON banda_relacion (ID_ORIGEN);
CREATE INDEX IF NOT EXISTS idx_rel_destino ON banda_relacion (ID_DESTINO);
CREATE INDEX IF NOT EXISTS idx_rel_tipo    ON banda_relacion (TIPO);
