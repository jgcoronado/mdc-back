-- Limpieza de 43 filas huérfanas heredadas de la migración MySQL→SQLite.
-- Ejecutar CON BACKUP PREVIO, ANTES de add-fk-constraints.sql.
-- Conteos verificados el 2026-06-05:
--   disco_marcha → marcha : 27
--   disco_marcha → disco  :  2
--   marcha_autor → marcha :  4
--   marcha_autor → autor  : 10

BEGIN;

DELETE FROM disco_marcha WHERE IDMARCHA NOT IN (SELECT ID_MARCHA FROM marcha);
DELETE FROM disco_marcha WHERE ID_DISCO  NOT IN (SELECT ID_DISCO  FROM disco);
DELETE FROM marcha_autor  WHERE ID_MARCHA NOT IN (SELECT ID_MARCHA FROM marcha);
DELETE FROM marcha_autor  WHERE ID_AUTOR  NOT IN (SELECT ID_AUTOR  FROM autor);

COMMIT;

-- Verificación post-limpieza (deben devolver 0):
SELECT 'dm→m' AS check, COUNT(*) AS n FROM disco_marcha WHERE IDMARCHA  NOT IN (SELECT ID_MARCHA FROM marcha)
UNION ALL
SELECT 'dm→d',          COUNT(*)        FROM disco_marcha WHERE ID_DISCO  NOT IN (SELECT ID_DISCO  FROM disco)
UNION ALL
SELECT 'ma→m',          COUNT(*)        FROM marcha_autor  WHERE ID_MARCHA NOT IN (SELECT ID_MARCHA FROM marcha)
UNION ALL
SELECT 'ma→a',          COUNT(*)        FROM marcha_autor  WHERE ID_AUTOR  NOT IN (SELECT ID_AUTOR  FROM autor);
