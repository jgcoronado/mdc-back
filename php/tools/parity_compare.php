<?php

declare(strict_types=1);

/*
 * Compara la salida de App\Repo con parity_expected.json (espejo de api.ts).
 * Uso: php parity_compare.php <ruta a parity_expected.json>
 */

define('BASE_DIR', dirname(__DIR__));   // php/tools → php
define('APP_DIR', BASE_DIR . '/app');
define('DATA_DIR', BASE_DIR . '/data');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = APP_DIR . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

$GLOBALS['config'] = ['db_path' => DATA_DIR . '/mdc.db'];

use App\Repo;
use App\Seo;
use App\Slug;

const SITE = 'https://marchasdecristo.com';

$expectedPath = $argv[1] ?? (__DIR__ . '/parity_expected.json');
if (!is_file($expectedPath)) {
    fwrite(STDERR, "No existe expected: $expectedPath\n");
    exit(2);
}
$expected = json_decode((string) file_get_contents($expectedPath), true);

$cases = [
    'marcha_330' => static fn() => Repo::fetchMarcha('330'),
    'marcha_1' => static fn() => Repo::fetchMarcha('1'),
    'marcha_missing' => static fn() => Repo::fetchMarcha('99999'),
    'search_marchas_amargura' => static fn() => Repo::searchMarchas('titulo=Amargura', 1, 20),
    'search_marchas_localidad_sevilla' => static fn() => Repo::searchMarchas('localidad=Sevilla', 1, 20),
    'search_marchas_fecha' => static fn() => Repo::searchMarchas('fechaDesde=1990&fechaHasta=2000', 1, 5),
    'search_marchas_dedic' => static fn() => Repo::searchMarchas('dedicatoria=Hdad', 1, 10),
    'autor_1' => static fn() => Repo::fetchAutor('1'),
    'autor_44' => static fn() => Repo::fetchAutor('44'),
    'autor_missing' => static fn() => Repo::fetchAutor('99999'),
    'search_autores_gamez' => static fn() => Repo::searchAutores('nombre=Gamez', 1, 20),
    'banda_1' => static fn() => Repo::fetchBanda('1'),
    'banda_6' => static fn() => Repo::fetchBanda('6'),
    'search_bandas_tres' => static fn() => Repo::searchBandas('titulo=Tres', 1, 20),
    'disco_1' => static fn() => Repo::fetchDisco('1'),
    'disco_165' => static fn() => Repo::fetchDisco('165'),
    'search_discos_pasion' => static fn() => Repo::searchDiscos('nombre=Pasion', 1, 20),
    'ultimas' => static fn() => Repo::fetchUltimas(),
    'estado' => static fn() => Repo::fetchEstado(),
    'masAutor' => static fn() => Repo::fetchMasAutor(),
    'masDedica' => static fn() => Repo::fetchMasDedica(),
    'masEstreno' => static fn() => Repo::fetchMasEstreno(),
    'masGrabada' => static fn() => Repo::fetchMasGrabada(),

    'schema_marcha_330' => static function () {
        $m = Repo::fetchMarcha('330');
        return Seo::marcha($m, SITE . Slug::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO']));
    },
    'schema_autor_44' => static function () {
        $a = Repo::fetchAutor('44');
        $full = trim(($a['NOMBRE'] ?? '') . ' ' . ($a['APELLIDOS'] ?? ''));
        return Seo::autor($a, SITE . Slug::buildDetailPath('autor', $a['ID_AUTOR'], $full));
    },
    'schema_banda_6' => static function () {
        $b = Repo::fetchBanda('6');
        return Seo::banda($b, SITE . Slug::buildDetailPath('banda', $b['ID_BANDA'], (string) $b['NOMBRE_COMPLETO']));
    },
    'schema_disco_165' => static function () {
        $d = Repo::fetchDisco('165');
        return Seo::disco($d, SITE . Slug::buildDetailPath('disco', $d['ID_DISCO'], (string) $d['NOMBRE_CD']));
    },
    'breadcrumbs_marcha_330' => static function () {
        $m = Repo::fetchMarcha('330');
        $url = SITE . Slug::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO']);
        return Seo::breadcrumbs([
            ['name' => 'Inicio', 'url' => SITE],
            ['name' => 'Marchas', 'url' => SITE . '/marcha'],
            ['name' => $m['TITULO'], 'url' => $url],
        ]);
    },
];

/** @return string|null  path del primer desajuste, o null si iguales */
function deepDiff(mixed $exp, mixed $act, string $path = ''): ?string
{
    if ($exp === null && $act === null) return null;
    if ($exp === null || $act === null) {
        return "$path: uno es null — exp=" . json_encode($exp) . " act=" . json_encode($act);
    }
    if (is_array($exp) && is_array($act)) {
        if ($exp === [] || $act === []) {
            return $exp === $act ? null : "$path: array vacío vs no vacío";
        }
        $expList = array_is_list($exp);
        $actList = array_is_list($act);
        if ($expList && $actList) {
            if (count($exp) !== count($act)) {
                return "$path: longitud lista " . count($exp) . " vs " . count($act);
            }
            foreach ($exp as $i => $v) {
                $d = deepDiff($v, $act[$i], "{$path}[{$i}]");
                if ($d !== null) return $d;
            }
            return null;
        }
        // objeto: comparar por clave (orden irrelevante)
        $ek = array_keys($exp); sort($ek);
        $ak = array_keys($act); sort($ak);
        if ($ek !== $ak) {
            $missing = implode(',', array_diff($ek, $ak));
            $extra = implode(',', array_diff($ak, $ek));
            return "$path: claves distintas (faltan en PHP: [$missing]) (sobran en PHP: [$extra])";
        }
        foreach ($exp as $k => $v) {
            $d = deepDiff($v, $act[$k], $path === '' ? (string) $k : "$path.$k");
            if ($d !== null) return $d;
        }
        return null;
    }
    if ($exp === $act) return null;
    // Estricto por defecto (tipo + valor). Con LENIENT=1 se tolera igualdad numérica.
    if (getenv('LENIENT') === '1'
        && is_scalar($exp) && is_scalar($act) && is_numeric($exp) && is_numeric($act) && (float) $exp === (float) $act) {
        return null;
    }
    return "$path: " . json_encode($exp) . " (" . gettype($exp) . ") != " . json_encode($act) . " (" . gettype($act) . ")";
}

$pass = 0;
$fail = 0;
foreach ($cases as $name => $fn) {
    if (!array_key_exists($name, $expected)) {
        printf("  ?  %-34s (no está en expected)\n", $name);
        continue;
    }
    try {
        $actual = $fn();
    } catch (\Throwable $e) {
        printf("  ✗  %-34s EXCEPCIÓN: %s\n", $name, $e->getMessage());
        $fail++;
        continue;
    }
    $diff = deepDiff($expected[$name], $actual, '');
    if ($diff === null) {
        printf("  ✓  %-34s OK\n", $name);
        $pass++;
    } else {
        printf("  ✗  %-34s %s\n", $name, $diff);
        $fail++;
    }
}

echo "\n";
printf("Resultado: %d OK, %d FALLOS de %d casos.\n", $pass, $fail, count($cases));
exit($fail === 0 ? 0 : 1);
