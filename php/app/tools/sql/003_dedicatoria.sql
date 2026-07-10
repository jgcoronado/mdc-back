-- Normalización de dedicatorias para los hubs de advocación (pantallas N-01 / N-02).
--
-- El campo marcha.DEDICATORIA es texto libre: ~805 variantes que agrupan la misma
-- advocación bajo escrituras distintas ("Hdad Esperanza" / "Hermandad Esperanza",
-- "La Estrella" / "Estrella"). La granularidad elegida es «advocación + localidad»
-- (una "Esperanza" de Triana no es la de otra localidad), así que la unidad de
-- agrupación es el par (DEDICATORIA, LOCALIDAD) tal cual aparece en la marcha.
--
--   dedicatoria        = advocación canónica (lo que ve el usuario y da la URL).
--   dedicatoria_alias  = cada par (VARIANTE, LOCALIDAD) crudo -> su canónica.
--
-- El join a marchas es por texto exacto contra el par:
--   marcha m JOIN dedicatoria_alias da
--     ON da.VARIANTE = m.DEDICATORIA AND da.LOCALIDAD = COALESCE(m.LOCALIDAD, '')
--
-- LOCALIDAD se guarda como '' (no NULL) para que forme parte de la PRIMARY KEY sin
-- las sorpresas de NULL en índices UNIQUE de SQLite.
--
-- Idempotente (CREATE ... IF NOT EXISTS): lo aplica migrate_ingest.php y lo asegura
-- seed_dedicatorias.php antes de poblar. La curación manual (panel admin) reasigna
-- filas de dedicatoria_alias; re-ejecutar el seed NO la pisa (ver seed_dedicatorias.php).

-- PERSONAL: dedicatoria particular a una persona o grupo concreto ("A Manuel
-- Rodríguez Ruiz", "Al Padre Del Autor", "A La Banda De Las Cigarreras"), en vez
-- de institucional (hermandad, cofradía, agrupación, advocación mariana o
-- cristológica: "Hdad Esperanza", "Virgen Del Carmen"). Clasificada por
-- Repo::esDedicatoriaPersonal() al sembrar/crear la canónica; editable a mano
-- desde el panel. Las PERSONAL = 1 no aparecen en el índice público (N-02) ni
-- en el sitemap — igual que las «thin» (ver Repo::DEDIC_MIN_MARCHAS).
CREATE TABLE IF NOT EXISTS dedicatoria (
    ID_DEDIC   INTEGER PRIMARY KEY,
    NOMBRE     TEXT NOT NULL,              -- advocación canónica ("Jesús Nazareno")
    LOCALIDAD  TEXT NOT NULL DEFAULT '',   -- localidad canónica ('' = sin localidad)
    PROVINCIA  TEXT,
    SLUG_KEY   TEXT NOT NULL UNIQUE,       -- clave de agrupación: slug(nombre)|slug(localidad)
    PERSONAL   INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS dedicatoria_alias (
    VARIANTE   TEXT NOT NULL,              -- marcha.DEDICATORIA cruda
    LOCALIDAD  TEXT NOT NULL DEFAULT '',   -- marcha.LOCALIDAD cruda (COALESCE a '')
    ID_DEDIC   INTEGER NOT NULL REFERENCES dedicatoria(ID_DEDIC),
    PRIMARY KEY (VARIANTE, LOCALIDAD)
);

CREATE INDEX IF NOT EXISTS idx_dedic_alias_dedic ON dedicatoria_alias (ID_DEDIC);
