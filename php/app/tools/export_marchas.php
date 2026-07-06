<?php

declare(strict_types=1);

/*
 * Exporta a JSON las marchas existentes de las bandas que tienen canal de
 * YouTube en `ingest_canal` (Fase 3: dedup). Es de solo lectura, no toca nada.
 *
 *   php app/tools/export_marchas.php > tools/ingest/out/marchas.json
 *   DB_PATH=data/mdc.db php php/app/tools/export_marchas.php > tools/ingest/out/marchas.json
 *
 * Solo se traen las bandas con canal registrado (no las 4.212 marchas de toda
 * la BD) porque dedup.mjs cruza cada candidato contra las marchas de SU banda.
 */

define('APP_DIR', dirname(__DIR__));
define('BASE_DIR', dirname(APP_DIR));
define('DATA_DIR', BASE_DIR . '/data');

/** @var array<string,mixed> $config */
$config = require APP_DIR . '/config.php';
$db = (string) $config['db_path'];
if (!is_file($db)) {
    fwrite(STDERR, "Abortado: no existe la BD en $db\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $marchas = $pdo->query(
        "SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.BANDA_ESTRENO
         FROM marcha m
         WHERE m.BANDA_ESTRENO IN (SELECT DISTINCT ID_BANDA FROM ingest_canal)
         ORDER BY m.ID_MARCHA"
    )->fetchAll();

    $autoresStmt = $pdo->prepare(
        "SELECT (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO
         FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
         WHERE ma.ID_MARCHA = ? ORDER BY a.APELLIDOS"
    );

    $out = [];
    foreach ($marchas as $m) {
        $autoresStmt->execute([$m['ID_MARCHA']]);
        $autores = array_column($autoresStmt->fetchAll(), 'NOMBRE_COMPLETO');
        $out[] = [
            'id_marcha' => (int) $m['ID_MARCHA'],
            'titulo' => $m['TITULO'],
            'fecha' => $m['FECHA'] !== null ? (int) $m['FECHA'] : null,
            'banda_estreno' => $m['BANDA_ESTRENO'] !== null ? (int) $m['BANDA_ESTRENO'] : null,
            'autores' => $autores,
        ];
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Exportación falló: ' . $e->getMessage() . "\n");
    exit(1);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
fwrite(STDERR, 'OK: ' . count($out) . " marchas exportadas (bandas con canal en ingest_canal)\n");
