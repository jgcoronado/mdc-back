<?php

declare(strict_types=1);

use App\Admin;
use App\Auth;
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

// ── Diagnóstico ──────────────────────────────────────────────────────────────
// Público: solo "ok" + versión. El detalle (rutas, conteos, FTS) requiere sesión
// admin para no filtrar la ruta del .db.
$router->get('/health', static function (): void {
    header('Content-Type: text/plain; charset=utf-8');
    Http::noStore();
    echo "status: ok\n";
    echo 'php: ' . PHP_VERSION . "\n";

    if (Auth::currentSession() === null) {
        return; // sin sesión no revelamos más
    }

    $config = $GLOBALS['config'];
    echo 'db_path: ' . $config['db_path'] . "\n";
    echo 'db_exists: ' . (is_file($config['db_path']) ? 'YES' : 'NO') . "\n";
    if (!is_file($config['db_path'])) {
        return;
    }
    try {
        $pdo = Db::pdo();
        echo 'sqlite: ' . $pdo->query('SELECT sqlite_version()')->fetchColumn() . "\n";
        echo 'journal_mode: ' . $pdo->query('PRAGMA journal_mode')->fetchColumn() . "\n";
        $c = Db::counts();
        echo "counts: marchas={$c['MARCHAS']} autores={$c['AUTORES']} bandas={$c['BANDAS']} discos={$c['DISCOS']}\n";
        $pdo->query('SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH \'"amargura"\' LIMIT 1')->fetchAll();
        echo "fts5: OK\n";
    } catch (\Throwable $e) {
        echo 'db_error: ' . $e->getMessage() . "\n";
    }
});

// ── Admin (auth + panel) ──────────────────────────────────────────────────────
$router->get('/login', [Admin::class, 'loginForm']);
$router->post('/login', [Admin::class, 'loginPost']);
$router->post('/logout', [Admin::class, 'logout']);
$router->get('/dashboard', [Admin::class, 'dashboard']);
// /add antes del catch-all /{id}
$router->get('/dashboard/marcha/add', [Admin::class, 'marchaAddForm']);
$router->post('/dashboard/marcha/add', [Admin::class, 'marchaAddPost']);
$router->get('/dashboard/marcha/{id}', [Admin::class, 'marchaEditForm']);
$router->post('/dashboard/marcha/{id}', [Admin::class, 'marchaEditPost']);
$router->get('/dashboard/autor/add', [Admin::class, 'autorAddForm']);
$router->post('/dashboard/autor/add', [Admin::class, 'autorAddPost']);
$router->get('/dashboard/autor/{id}', [Admin::class, 'autorEditForm']);
$router->post('/dashboard/autor/{id}', [Admin::class, 'autorEditPost']);
$router->get('/api/autor/fastSearch', [Admin::class, 'autorFastSearch']);

// ── Ingesta (revisión de candidatos de YouTube, ver tools/ingest/) ───────────
$router->get('/dashboard/ingesta', [Admin::class, 'ingestaList']);
$router->get('/dashboard/ingesta/{id}', [Admin::class, 'ingestaDetail']);
$router->post('/dashboard/ingesta/{id}/aceptar', [Admin::class, 'ingestaAceptar']);
$router->post('/dashboard/ingesta/{id}/descartar', [Admin::class, 'ingestaDescartar']);

// ── 404 ──────────────────────────────────────────────────────────────────────
$router->notFound([Http::class, 'notFound']);
