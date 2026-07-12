<?php

declare(strict_types=1);

use App\Db;
use App\Seo;
use App\View;

/** @var string $content              cuerpo ya renderizado de la vista */
/** @var array<string,mixed> $meta    title, description, noindex, og, jsonld */

$title = (string) ($meta['title'] ?? 'Marchas de Cristo');
$description = $meta['description'] ?? null;
$noindex = !empty($meta['noindex']);
$canonical = $meta['canonical'] ?? null;
$og = $meta['og'] ?? null;
$jsonld = $meta['jsonld'] ?? [];

$siteName = 'Marchas de Cristo';
$nav = [
    '/marcha' => 'Marchas',
    '/autor' => 'Compositores',
    '/banda' => 'Bandas',
    '/disco' => 'Discos',
    '/dedicatorias' => 'Dedicatorias',
    '/estadisticas' => 'Estadísticas',
];

try {
    $counts = Db::counts();
} catch (\Throwable) {
    $counts = null;
}
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Sección activa para aria-current (primer segmento de la ruta, sin query string).
$reqPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$current = '/' . (explode('/', trim($reqPath, '/'))[0] ?? '');

// Buscador de cabecera: solo en las secciones con búsqueda de texto propia
// (Dedicatorias filtra por localidad/provincia y Estadísticas no tiene
// buscador, así que ahí el cuadro no se muestra).
$searchBySection = [
    '/marcha' => ['action' => '/marcha', 'param' => 'titulo', 'label' => 'marchas'],
    '/autor' => ['action' => '/autor', 'param' => 'nombre', 'label' => 'compositores'],
    '/banda' => ['action' => '/banda', 'param' => 'titulo', 'label' => 'bandas'],
    '/disco' => ['action' => '/disco', 'param' => 'nombre', 'label' => 'discos'],
];
$search = $searchBySection[$current] ?? null;
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
<?php if ($description !== null): ?>
    <meta name="description" content="<?= $e($description) ?>">
<?php endif; ?>
<?php if ($noindex): ?>
    <meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($canonical !== null): ?>
    <link rel="canonical" href="<?= $e($canonical) ?>">
<?php endif; ?>
<?php if ($og !== null): ?>
    <meta property="og:type" content="<?= $e($og['type'] ?? 'website') ?>">
    <meta property="og:title" content="<?= $e($og['title'] ?? $title) ?>">
<?php if (!empty($og['description'])): ?>
    <meta property="og:description" content="<?= $e($og['description']) ?>">
<?php endif; ?>
<?php if (!empty($og['url'])): ?>
    <meta property="og:url" content="<?= $e($og['url']) ?>">
<?php endif; ?>
<?php endif; ?>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css">
<?php foreach ($jsonld as $schema): ?>
    <script type="application/ld+json"><?= Seo::json($schema) ?></script>
<?php endforeach; ?>
</head>
<body>
    <header>
        <nav>
            <a class="brand" href="/"><?= $siteName ?><span class="brand-sub">Base de datos de música procesional</span></a>
            <ul class="nav-links">
<?php foreach ($nav as $href => $label): ?>
                <li><a href="<?= $href ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
<?php endforeach; ?>
            </ul>
            <details class="nav-mobile">
                <summary aria-label="Menú">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </summary>
                <ul>
                    <li><a href="/">Inicio</a></li>
<?php foreach ($nav as $href => $label): ?>
                    <li><a href="<?= $href ?>"><?= $label ?></a></li>
<?php endforeach; ?>
                </ul>
            </details>
        </nav>
<?php if ($search !== null): ?>
        <div class="site-search-row">
            <form class="site-search" action="<?= $e($search['action']) ?>" method="get" role="search">
                <span aria-hidden="true">⌕</span>
                <input id="site-q" type="search" name="<?= $e($search['param']) ?>" placeholder="<?= $e('Buscar en ' . $search['label'] . '…') ?>" aria-label="<?= $e('Buscar en ' . $search['label']) ?>">
                <span class="kbd">/</span>
            </form>
        </div>
<?php endif; ?>
    </header>

    <main><?= $content ?></main>

    <footer>
        <div class="inner">
<?php if ($counts !== null): ?>
            <?= $counts['MARCHAS'] ?> marchas · <?= $counts['AUTORES'] ?> compositores · <?= $counts['BANDAS'] ?> bandas · <?= $counts['DISCOS'] ?> discos
<?php else: ?>
            <?= $siteName ?>
<?php endif; ?>
        </div>
    </footer>
    <script src="/assets/catalog.js" defer></script>
<?php if (!empty($GLOBALS['config']['goatcounter_code'])): ?>
    <script data-goatcounter="https://<?= $e($GLOBALS['config']['goatcounter_code']) ?>.goatcounter.com/count"
            async src="//gc.zgo.at/count.js"></script>
<?php endif; ?>
</body>
</html>
