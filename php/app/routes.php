<?php

declare(strict_types=1);

use App\Db;
use App\Http;
use App\Pages;

/** @var App\Router $router */

// ── Home ─────────────────────────────────────────────────────────────────────
$router->get('/', [Pages::class, 'home']);

// ── Redirects legacy /x/search → /x?... (antes del catch-all) ─────────────────
$legacySearch = static function (string $target): callable {
    return static function () use ($target): void {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        Http::redirect($target . ($qs !== '' ? '?' . $qs : ''), 308);
    };
};
$router->get('/marcha/search', $legacySearch('/marcha'));
$router->get('/autor/search', $legacySearch('/autor'));
$router->get('/banda/search', $legacySearch('/banda'));
$router->get('/disco/search', $legacySearch('/disco'));

// ── Listados / buscadores ─────────────────────────────────────────────────────
$router->get('/marcha', [Pages::class, 'marchaList']);
$router->get('/autor', [Pages::class, 'autorList']);
$router->get('/banda', [Pages::class, 'bandaList']);
$router->get('/disco', [Pages::class, 'discoList']);

// ── Detalles (catch-all por entidad) ──────────────────────────────────────────
$router->get('/marcha/{slugAndId}', [Pages::class, 'marchaDetail']);
$router->get('/autor/{slugAndId}', [Pages::class, 'autorDetail']);
$router->get('/banda/{slugAndId}', [Pages::class, 'bandaDetail']);
$router->get('/disco/{slugAndId}', [Pages::class, 'discoDetail']);

// ── Estadísticas ──────────────────────────────────────────────────────────────
$router->get('/estadisticas', [Pages::class, 'estadisticas']);

// ── SEO ────────────────────────────────────────────────────────────────────────
$router->get('/sitemap.xml', [Pages::class, 'sitemap']);
$router->get('/robots.txt', [Pages::class, 'robots']);

// ── Diagnóstico (Fase 0) ─────────────────────────────────────────────────────
// TODO Fase 4: restringir o eliminar antes del cutover (revela la ruta del .db).
$router->get('/health', static function (): void {
    header('Content-Type: text/plain; charset=utf-8');
    $config = $GLOBALS['config'];
    $lines = [
        'status: ok',
        'php: ' . PHP_VERSION,
        'db_path: ' . $config['db_path'],
        'db_exists: ' . (is_file($config['db_path']) ? 'YES' : 'NO'),
    ];
    if (is_file($config['db_path'])) {
        try {
            $pdo = Db::pdo();
            $lines[] = 'sqlite: ' . $pdo->query('SELECT sqlite_version()')->fetchColumn();
            $lines[] = 'journal_mode: ' . $pdo->query('PRAGMA journal_mode')->fetchColumn();
            $c = Db::counts();
            $lines[] = "counts: marchas={$c['MARCHAS']} autores={$c['AUTORES']} bandas={$c['BANDAS']} discos={$c['DISCOS']}";
            try {
                $pdo->query('SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH \'"amargura"\' LIMIT 1')->fetchAll();
                $lines[] = 'fts5: OK';
            } catch (\Throwable $e) {
                $lines[] = 'fts5: ERROR ' . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $lines[] = 'db_error: ' . $e->getMessage();
        }
    }
    echo implode("\n", $lines) . "\n";
});

// ── 404 ──────────────────────────────────────────────────────────────────────
$router->notFound([Http::class, 'notFound']);
