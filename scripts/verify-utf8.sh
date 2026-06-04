#!/bin/sh
# Run inside a one-shot node:22-alpine container with sqlite installed
set -e
apk add --no-cache sqlite > /dev/null

DB=/work/mdc.db

echo "=== marcha 311 + 330 titles ==="
sqlite3 "$DB" "SELECT ID_MARCHA, TITULO FROM marcha WHERE ID_MARCHA IN (311, 330);"
echo ""
echo "=== Discos with accents (looking for Señor / Madrugá / Pasión) ==="
sqlite3 "$DB" "SELECT ID_DISCO, NOMBRE_CD FROM disco WHERE ID_DISCO IN (220, 365, 199) ORDER BY ID_DISCO;"
echo ""
echo "=== Bandas with accents ==="
sqlite3 "$DB" "SELECT ID_BANDA, NOMBRE_BREVE FROM banda WHERE NOMBRE_BREVE LIKE '%Coronaci%' OR NOMBRE_BREVE LIKE '%Pasi%' LIMIT 5;"
echo ""
echo "=== FTS5 search for 'esperanza' ==="
sqlite3 "$DB" "SELECT m.ID_MARCHA, m.TITULO FROM marcha m WHERE m.ID_MARCHA IN (SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH '\"esperanza\"') LIMIT 5;"
echo ""
echo "=== Row counts ==="
sqlite3 "$DB" "SELECT 'marcha' as t, COUNT(*) FROM marcha UNION ALL SELECT 'autor', COUNT(*) FROM autor UNION ALL SELECT 'banda', COUNT(*) FROM banda UNION ALL SELECT 'disco', COUNT(*) FROM disco;"
