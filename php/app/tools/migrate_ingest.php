<?php

declare(strict_types=1);

/*
 * Aplica las migraciones de la herramienta de ingesta (tablas de staging).
 * Idempotente: se puede ejecutar tantas veces como haga falta.
 *
 *   /usr/local/bin/php /home/USUARIO/app/tools/migrate_ingest.php
 *
 * En local, apuntando a una BD concreta:
 *   DB_PATH=data/mdc.db php php/app/tools/migrate_ingest.php
 *
 * Lee todos los .sql de app/tools/sql/ en orden alfabético y los ejecuta contra
 * la BD de `config.php` (respeta DB_PATH). Los .sql son CREATE ... IF NOT EXISTS,
 * así que re-ejecutar no rompe nada ni pierde datos.
 */

define('APP_DIR', dirname(__DIR__));       // .../app
define('BASE_DIR', dirname(APP_DIR));      // .../ (home en el host)
define('DATA_DIR', BASE_DIR . '/data');

/** @var array<string,mixed> $config */
$config = require APP_DIR . '/config.php';
$db = (string) $config['db_path'];

if (!is_file($db)) {
    fwrite(STDERR, "Migración abortada: no existe la BD en $db\n");
    exit(1);
}

$sqlDir = __DIR__ . '/sql';
$files = glob($sqlDir . '/*.sql') ?: [];
sort($files, SORT_STRING);
if ($files === []) {
    fwrite(STDERR, "Migración abortada: no hay .sql en $sqlDir\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            fwrite(STDERR, 'No se pudo leer ' . basename($file) . "\n");
            exit(1);
        }
        // Cada fichero es un lote de sentencias; PDO::exec ejecuta múltiples con ';'.
        $pdo->exec($sql);
        echo 'aplicado: ' . basename($file) . "\n";
    }

    // Verificación: listar las tablas ingest_* resultantes.
    $tables = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'ingest_%' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migración falló: ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'OK. Tablas de ingesta: ' . (empty($tables) ? '(ninguna)' : implode(', ', $tables)) . "\n";
