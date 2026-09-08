<?php

declare(strict_types=1);

/*
 * Asigna la localidad DEL ACOMPAÑAMIENTO a los contratos que no la tienen.
 *
 * Existe para una carga concreta: los 92 acompañamientos de Sevilla 2026 se
 * cargaron (2026-07-27) antes de que `contrato_localidad` se poblase, así que
 * quedaron sin localidad y /temporada/{año} tiene que adivinarla a partir de
 * `banda.LOCALIDAD` — que es justo el heurístico que la tabla vino a sustituir
 * (ver sql/009_contrato_localidad.sql y technical-debt.md §4.2). La alternativa
 * era abrir /dashboard/acompanamientos/localidad?loc= (la lista "Sin
 * localidad") y guardar 92 filas a mano.
 *
 * NO adivina nada: escribe la localidad que se le pase, y solo sobre las filas
 * que no tienen ninguna. Las que ya tienen localidad no se tocan nunca, ni
 * siquiera para corregirlas — eso es una decisión editorial y se hace desde el
 * panel.
 *
 * USO (desde la RAÍZ del proyecto, justo encima de php/):
 *   php php/app/tools/backfill_contrato_localidad.php Sevilla              # DRY-RUN
 *   php php/app/tools/backfill_contrato_localidad.php Sevilla --anio=2026  # solo esa temporada
 *   php php/app/tools/backfill_contrato_localidad.php Sevilla --commit     # escribe
 *
 * REVERSIBLE: solo inserta filas en `contrato_localidad`, no modifica ni borra
 * nada de `contrato`. Deshacer lo escrito por una pasada es
 *   DELETE FROM contrato_localidad WHERE LOCALIDAD = '<la que se pasó>';
 * (siempre que no hubiera ya filas con esa misma localidad de antes — el
 * dry-run dice cuántas hay para poder comprobarlo antes de lanzarlo).
 */

require __DIR__ . '/_cli.php';
[$config, $db] = cliBootstrap('Backfill de localidad abortado');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = APP_DIR . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
$config['env'] = 'local';
$GLOBALS['config'] = $config;
\App\Db::setAuditUser('backfill-contrato-localidad');

$args = array_slice($argv, 1);
$commit = in_array('--commit', $args, true);
$localidad = '';
$anio = null;
foreach ($args as $a) {
    if ($a === '--commit') continue;
    if (str_starts_with($a, '--anio=')) { $anio = (int) substr($a, 7); continue; }
    if ($localidad === '') $localidad = trim($a);
}
if ($localidad === '') {
    fwrite(STDERR, "Uso: php php/app/tools/backfill_contrato_localidad.php <localidad> [--anio=AAAA] [--commit]\n");
    exit(1);
}

fwrite(STDERR, "BD: $db\n");
fwrite(STDERR, "Localidad a escribir: $localidad" . ($anio !== null ? " (solo ANIO = $anio)" : '') . "\n");
fwrite(STDERR, 'Modo: ' . ($commit ? 'COMMIT' : 'DRY-RUN (añade --commit para escribir)') . "\n\n");

// Cuántas filas hay YA con esta localidad: sin este dato, el DELETE de
// reversión de la cabecera no se puede usar con seguridad.
$yaConEsta = \App\Db::one('SELECT COUNT(*) AS N FROM contrato_localidad WHERE LOCALIDAD = ?', [$localidad]);
fwrite(STDERR, 'Filas que ya tenían esta localidad antes de empezar: ' . (int) ($yaConEsta['N'] ?? 0) . "\n\n");

$where = 'cl.ID_CONTRATO IS NULL';
$params = [];
if ($anio !== null) { $where .= ' AND c.ANIO = ?'; $params[] = $anio; }

$sinLocalidad = \App\Db::all(
    "SELECT c.ID_CONTRATO, c.HERMANDAD, c.TITULAR, c.ANIO,
            (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
     FROM contrato c
     INNER JOIN banda b ON b.ID_BANDA = c.ID_BANDA
     LEFT JOIN contrato_localidad cl ON cl.ID_CONTRATO = c.ID_CONTRATO
     WHERE $where
     ORDER BY c.ID_CONTRATO ASC",
    $params
);

if ($sinLocalidad === []) {
    fwrite(STDERR, "No hay contratos sin localidad" . ($anio !== null ? " en $anio" : '') . ". Nada que hacer.\n");
    exit(0);
}

$escritas = 0;
foreach ($sinLocalidad as $c) {
    printf(
        "%s #%-5d %-34s %-20s %d  %s\n",
        $commit ? '+' : '·',
        (int) $c['ID_CONTRATO'],
        (string) $c['HERMANDAD'],
        (string) ($c['TITULAR'] ?? ''),
        (int) $c['ANIO'],
        (string) $c['BANDA']
    );
    if (!$commit) continue;
    \App\Db::run('INSERT INTO contrato_localidad (ID_CONTRATO, LOCALIDAD) VALUES (?, ?)', [(int) $c['ID_CONTRATO'], $localidad]);
    \App\Db::logAdmin('UPDATE', 'contrato', (int) $c['ID_CONTRATO'], ['localidad' => $localidad]);
    $escritas++;
}

fwrite(STDERR, sprintf(
    "\n%s: %d contratos.\n",
    $commit ? 'Escritos' : 'Se escribirían',
    $commit ? $escritas : count($sinLocalidad)
));
if (!$commit) fwrite(STDERR, "(Dry-run: no se ha escrito nada.)\n");
