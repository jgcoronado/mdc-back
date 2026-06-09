-- Normaliza FECHA=0 a NULL en la tabla marcha.
-- Antes: 247 filas con FECHA = 0 (sentinel de "sin fecha").
-- Después: esas filas tendrán FECHA = NULL, lo que permite simplificar normalizeFecha en api.ts.

UPDATE marcha SET FECHA = NULL WHERE FECHA = 0;

-- Verificación:
SELECT COUNT(*) AS pendientes FROM marcha WHERE FECHA = 0;
