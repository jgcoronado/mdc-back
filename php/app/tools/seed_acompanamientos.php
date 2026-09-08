<?php

declare(strict_types=1);

/*
 * Carga de acompañamientos (banda ↔ hermandad ↔ paso) desde un CSV por
 * localidad. Sustituye al seed_contratos_2026.php de la carga de Sevilla
 * (borrado), que exigía el ID_BANDA ya resuelto en el propio CSV: aquí el CSV
 * se escribe con el NOMBRE de la banda tal y como lo publica la fuente y es el
 * script quien lo resuelve contra la base, porque el nombre es lo único que
 * trae un listado de prensa.
 *
 * USO (desde la RAÍZ del proyecto, justo encima de php/):
 *   php php/app/tools/seed_acompanamientos.php docs/data/acompanamientos_granada_2026.csv
 *   php php/app/tools/seed_acompanamientos.php docs/data/acompanamientos_granada_2026.csv --commit
 *   ... --pendientes=/ruta/pendientes.csv      (por defecto: <csv sin .csv>.pendientes.csv)
 *
 * COLUMNAS del CSV (cabecera obligatoria; el orden da igual):
 *   LOCALIDAD  Localidad DEL ACOMPAÑAMIENTO (de qué Semana Santa sale), no la
 *              sede de la banda — ver sql/009_contrato_localidad.sql.
 *   HERMANDAD  Texto libre, tal cual debe leerse en la web.
 *   TITULAR    Paso concreto (Cruz de Guía, Misterio, Palio…). Opcional.
 *   BANDA      Nombre de la banda tal y como lo publica la fuente.
 *   ID_BANDA   Opcional: si viene, manda sobre BANDA y no se resuelve nada.
 *   ANIO       Año de INICIO del acompañamiento.
 *   ANIO_FIN   Año de fin. Vacío = sigue vigente. Opcional.
 *   FUENTE     URL del anuncio. Opcional (se muestra público).
 *   NOTA       Nota interna. Opcional (NO se muestra público).
 *
 * RESOLUCIÓN DE LA BANDA, en tres pasadas de menos a más permisiva:
 *   1. Slug exacto contra NOMBRE_BREVE o NOMBRE_COMPLETO.
 *   2. Slug exacto ignorando el "de <localidad>" final del nombre completo.
 *   3. Todas las palabras del CSV contenidas en el nombre de una ÚNICA banda.
 * Si ninguna resuelve, o la tercera deja más de una candidata, la fila NO se
 * carga: se apunta en el CSV de pendientes con las candidatas encontradas,
 * para darla de alta a mano (o crear la banda) y relanzar. El script es
 * idempotente — comprueba (banda, hermandad, paso, año, localidad) antes de
 * insertar — así que relanzarlo tras resolver pendientes no duplica nada.
 */

require __DIR__ . '/_cli.php';
[$config, $db] = cliBootstrap('Carga de acompañamientos abortada');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = APP_DIR . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

// Las escrituras van por App\AdminRepo (misma validación y mismo log que el
// panel), y ese camino pasa por Db::assertWritable(): en 'local' escribe.
$config['env'] = 'local';
$GLOBALS['config'] = $config;
\App\Db::setAuditUser('seed-acompanamientos');

$args = array_slice($argv, 1);
$commit = in_array('--commit', $args, true);
$csvPath = '';
$pendientesPath = '';
foreach ($args as $a) {
    if ($a === '--commit') continue;
    if (str_starts_with($a, '--pendientes=')) { $pendientesPath = substr($a, 13); continue; }
    if ($csvPath === '') $csvPath = $a;
}
if ($csvPath === '') {
    fwrite(STDERR, "Uso: php php/app/tools/seed_acompanamientos.php <csv> [--commit] [--pendientes=ruta.csv]\n");
    exit(1);
}
if (!is_file($csvPath)) {
    fwrite(STDERR, "ERROR: no encuentro el CSV: $csvPath\n");
    exit(1);
}
if ($pendientesPath === '') {
    $pendientesPath = preg_replace('/\.csv$/i', '', $csvPath) . '.pendientes.csv';
}

fwrite(STDERR, "BD: $db\n");
fwrite(STDERR, 'Modo: ' . ($commit ? 'COMMIT' : 'DRY-RUN (añade --commit para escribir)') . "\n\n");

// ── Índice de bandas en memoria ─────────────────────────────────────────────
// Son unos centenares de filas: cargarlas de una vez sale más barato (y
// resuelve mejor) que una consulta LIKE por cada línea del CSV.
/** @var list<array{id:int,breve:string,completo:string,localidad:string,slugs:list<string>,palabras:list<string>}> $bandas */
$bandas = [];
/** @var array<string,list<int>> $porSlug  slug → posiciones en $bandas */
$porSlug = [];

/** Nombre sin el "de <localidad>" del final, que la prensa casi nunca escribe. */
$sinLocalidad = static function (string $nombre, string $localidad): string {
    if ($localidad === '') return $nombre;
    $sufijo = '-de-' . \App\Slug::slugify($localidad);
    $slug = \App\Slug::slugify($nombre);
    return str_ends_with($slug, $sufijo) ? substr($slug, 0, -strlen($sufijo)) : $slug;
};

foreach (\App\Db::all('SELECT ID_BANDA, NOMBRE_BREVE, NOMBRE_COMPLETO, LOCALIDAD FROM banda') as $b) {
    $breve = (string) ($b['NOMBRE_BREVE'] ?? '');
    $completo = (string) ($b['NOMBRE_COMPLETO'] ?? '');
    $loc = (string) ($b['LOCALIDAD'] ?? '');
    $slugs = array_values(array_unique(array_filter([
        \App\Slug::slugify($breve),
        \App\Slug::slugify($completo),
        $sinLocalidad($completo, $loc),
        $sinLocalidad($breve, $loc),
    ])));
    $pos = count($bandas);
    $bandas[] = [
        'id' => (int) $b['ID_BANDA'],
        'breve' => $breve,
        'completo' => $completo,
        'localidad' => $loc,
        'slugs' => $slugs,
        'palabras' => array_values(array_filter(explode('-', \App\Slug::slugify($completo . ' ' . $breve . ' ' . $loc)))),
    ];
    foreach ($slugs as $s) $porSlug[$s][] = $pos;
}
fwrite(STDERR, 'Bandas en la base: ' . count($bandas) . "\n");

/**
 * Resuelve un nombre de banda contra el índice.
 * @return array{0:?int,1:string,2:list<string>} [id o null, cómo se resolvió, candidatas]
 */
$resolver = static function (string $nombre, string $localidad) use ($bandas, $porSlug, $sinLocalidad): array {
    $slug = \App\Slug::slugify($nombre);
    if ($slug === '') return [null, 'vacio', []];

    foreach ([$slug, $sinLocalidad($nombre, $localidad)] as $i => $clave) {
        if ($clave === '' || !isset($porSlug[$clave])) continue;
        $pos = $porSlug[$clave];
        if (count($pos) === 1) return [$bandas[$pos[0]]['id'], $i === 0 ? 'exacto' : 'exacto-sin-localidad', []];
        // Mismo nombre en varias localidades: desempata la del acompañamiento.
        $mismaLoc = array_values(array_filter($pos, static fn(int $p): bool => \App\Slug::slugify($bandas[$p]['localidad']) === \App\Slug::slugify($localidad)));
        if (count($mismaLoc) === 1) return [$bandas[$mismaLoc[0]]['id'], 'exacto-desempatado-por-localidad', []];
        return [null, 'ambiguo', array_map(static fn(int $p): string => $bandas[$p]['breve'] . ' (#' . $bandas[$p]['id'] . ', ' . $bandas[$p]['localidad'] . ')', $pos)];
    }

    // Contención de palabras: "AM Redención" contra "Agrupación Musical
    // Redención de Sevilla". Solo vale si deja UNA candidata.
    $palabras = array_values(array_filter(explode('-', $slug), static fn(string $w): bool => strlen($w) > 2));
    if ($palabras === []) return [null, 'sin-palabras', []];
    $cands = [];
    foreach ($bandas as $b) {
        $todas = true;
        foreach ($palabras as $w) {
            if (!in_array($w, $b['palabras'], true)) { $todas = false; break; }
        }
        if ($todas) $cands[] = $b;
    }
    if (count($cands) === 1) return [$cands[0]['id'], 'por-palabras', []];
    return [null, $cands === [] ? 'no-encontrada' : 'ambiguo', array_map(
        static fn(array $b): string => $b['breve'] . ' (#' . $b['id'] . ', ' . $b['localidad'] . ')',
        array_slice($cands, 0, 6)
    )];
};

// ── Recorrido del CSV ───────────────────────────────────────────────────────
$fh = fopen($csvPath, 'r');
if ($fh === false) { fwrite(STDERR, "ERROR: no se puede leer $csvPath\n"); exit(1); }
$head = fgetcsv($fh);
if ($head === false) { fwrite(STDERR, "ERROR: CSV vacío\n"); exit(1); }
$idx = array_flip(array_map(static fn($h): string => strtoupper(trim((string) $h)), $head));
foreach (['HERMANDAD', 'BANDA', 'ANIO'] as $req) {
    if (!isset($idx[$req])) { fwrite(STDERR, "ERROR: falta la columna $req en el CSV\n"); exit(1); }
}
$col = static function (array $row, array $idx, string $name): string {
    return isset($idx[$name]) ? trim((string) ($row[$idx[$name]] ?? '')) : '';
};

$creados = $dup = $err = 0;
/** @var list<array<string,string>> $pendientes */
$pendientes = [];

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) === 1 && trim((string) $row[0]) === '') continue;

    $localidad = $col($row, $idx, 'LOCALIDAD');
    $hermandad = $col($row, $idx, 'HERMANDAD');
    $titular   = $col($row, $idx, 'TITULAR');
    $nombreB   = $col($row, $idx, 'BANDA');
    $anio      = $col($row, $idx, 'ANIO');
    $anioFin   = $col($row, $idx, 'ANIO_FIN');
    $fuente    = $col($row, $idx, 'FUENTE');
    $nota      = $col($row, $idx, 'NOTA');
    $idBanda   = (int) $col($row, $idx, 'ID_BANDA');

    $como = 'csv';
    $candidatas = [];
    if ($idBanda === 0) {
        [$idBanda, $como, $candidatas] = $resolver($nombreB, $localidad);
    }
    if (!$idBanda) {
        $pendientes[] = [
            'LOCALIDAD' => $localidad, 'HERMANDAD' => $hermandad, 'TITULAR' => $titular,
            'BANDA' => $nombreB, 'ANIO' => $anio, 'ANIO_FIN' => $anioFin,
            'MOTIVO' => $como, 'CANDIDATAS' => implode(' | ', $candidatas),
        ];
        printf("? PENDIENTE  %-34s %-18s banda=«%s» (%s)\n", $hermandad, $titular, $nombreB, $como);
        continue;
    }

    // Idempotencia: la misma banda, hermandad, paso, año de inicio y localidad
    // es la misma fila. Sin UNIQUE en la tabla (los datos históricos no la
    // aguantarían), así que se comprueba aquí, igual que hacía el seed viejo.
    $existe = \App\Db::one(
        "SELECT c.ID_CONTRATO FROM contrato c
         LEFT JOIN contrato_localidad cl ON cl.ID_CONTRATO = c.ID_CONTRATO
         WHERE c.ID_BANDA = ? AND c.HERMANDAD_SLUG = ? AND c.ANIO = ?
           AND IFNULL(c.TITULAR, '') = ? AND IFNULL(cl.LOCALIDAD, '') = ?",
        [$idBanda, \App\Slug::slugify($hermandad), (int) $anio, $titular, $localidad]
    );
    if ($existe !== null) {
        printf("· YA EXISTE  %-34s %-18s banda=#%d\n", $hermandad, $titular, $idBanda);
        $dup++;
        continue;
    }

    if (!$commit) {
        printf("+ [dry-run]  %-34s %-18s banda=#%d (%s)%s\n", $hermandad, $titular, $idBanda, $como,
               $localidad !== '' ? " · $localidad" : '');
        $creados++;
        continue;
    }

    $res = \App\AdminRepo::addContrato(
        $idBanda,
        $hermandad,
        $anio,
        $titular !== '' ? $titular : null,
        $fuente !== '' ? $fuente : null,
        $nota !== '' ? $nota : null,
        $localidad !== '' ? $localidad : null,
        $anioFin !== '' ? $anioFin : null
    );
    if ($res['code'] === 'CREATED') {
        printf("+ CREADO     %-34s %-18s banda=#%d (%s)\n", $hermandad, $titular, $idBanda, $como);
        $creados++;
    } else {
        printf("! ERROR (%s) %s / %s\n", $res['code'], $hermandad, $titular);
        $err++;
    }
}
fclose($fh);

// ── Pendientes ──────────────────────────────────────────────────────────────
if ($pendientes !== []) {
    $out = fopen($pendientesPath, 'w');
    if ($out !== false) {
        fputcsv($out, array_keys($pendientes[0]));
        foreach ($pendientes as $p) fputcsv($out, array_values($p));
        fclose($out);
        fwrite(STDERR, "\nPendientes escritos en: $pendientesPath\n");
    } else {
        fwrite(STDERR, "\nAVISO: no se pudo escribir $pendientesPath\n");
    }
}

fwrite(STDERR, sprintf(
    "\nResumen: %s=%d · ya_existían=%d · pendientes=%d · errores=%d\n",
    $commit ? 'creados' : 'se_crearían', $creados, $dup, count($pendientes), $err
));
if (!$commit) fwrite(STDERR, "(Dry-run: no se ha escrito nada en la base.)\n");
exit($err > 0 ? 1 : 0);
