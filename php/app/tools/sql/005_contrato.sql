-- Contratos banda↔hermandad por temporada (N-04/N-05): "¿quién toca este año
-- detrás de cada paso?". Alta manual desde el panel por ahora (N-06, la
-- ingesta semi-automática de anuncios, queda para más adelante).
--
-- HERMANDAD es texto libre (no hay entidad `hermandad` todavía — N-03 está
-- condicionado a que los hubs de dedicatoria (N-01) demuestren tráfico real
-- antes de construirla). HERMANDAD_SLUG normaliza igual que Slug::slugify
-- para agrupar variantes de escritura de la misma hermandad en /temporada/{año}
-- sin depender de una FK que aún no existe; cuando llegue N-03, migrar esta
-- columna a una referencia real es un ALTER sencillo sobre datos ya limpios.
--
-- Idempotente (CREATE ... IF NOT EXISTS): lo aplica migrate_ingest.php.
CREATE TABLE IF NOT EXISTS contrato (
    ID_CONTRATO    INTEGER PRIMARY KEY,
    ID_BANDA       INTEGER NOT NULL REFERENCES banda(ID_BANDA),
    HERMANDAD      TEXT    NOT NULL,             -- tal cual lo escribe el admin
    HERMANDAD_SLUG TEXT    NOT NULL,             -- slugify(HERMANDAD), para agrupar
    TITULAR        TEXT,                         -- paso/imagen concreto (opcional)
    ANIO           INTEGER NOT NULL,
    FUENTE         TEXT,                         -- URL de la fuente (opcional, se muestra público)
    NOTA           TEXT,                         -- nota interna del admin (NO se muestra público)
    CREATED_AT     TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_contrato_anio ON contrato (ANIO);
CREATE INDEX IF NOT EXISTS idx_contrato_hermandad ON contrato (ANIO, HERMANDAD_SLUG);
CREATE INDEX IF NOT EXISTS idx_contrato_banda ON contrato (ID_BANDA);
