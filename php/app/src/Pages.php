<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Controladores de las páginas públicas. Ports de los Server Components (app/.../page.tsx).
 */
final class Pages
{
    private static function base(): string
    {
        return rtrim((string) ($GLOBALS['config']['site_url'] ?? 'https://marchasdecristo.com'), '/');
    }

    /** @return array{0:array<string,string>,1:bool,2:int,3:int} [criteria, hasQuery, page, limit] */
    private static function searchParams(): array
    {
        $flat = $_GET;
        unset($flat['page'], $flat['limit']);
        $criteria = [];
        $hasQuery = false;
        foreach ($flat as $k => $v) {
            if (!is_string($v)) continue;
            $criteria[$k] = $v;
            if (trim($v) !== '') $hasQuery = true;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limitIn = (int) ($_GET['limit'] ?? 20);
        $limit = in_array($limitIn, [10, 20, 50], true) ? $limitIn : 20;
        return [$criteria, $hasQuery, $page, $limit];
    }

    // ── Home ──────────────────────────────────────────────────────────────────
    public static function home(): void
    {
        Http::cachePublic(1800);
        View::render('home', [
            'ultimas' => Repo::fetchUltimas(),
            'estado' => Repo::fetchEstado(),
        ], [
            'title' => 'Marchas de Cristo — Música procesional',
            'description' => 'Descubre marchas procesionales, compositores, bandas y discos de música de Semana Santa.',
        ]);
    }

    // ── Listados / buscadores ─────────────────────────────────────────────────
    public static function marchaList(): void
    {
        [$criteria, $hasQuery, $page, $limit] = self::searchParams();
        $result = $hasQuery ? Repo::searchMarchas(http_build_query($criteria), $page, $limit) : null;
        $result !== null ? Http::noStore() : Http::cachePublic(3600);
        View::render('marcha_list', compact('criteria', 'result', 'page', 'limit'), [
            'title' => 'Buscador de marchas procesionales — Marchas de Cristo',
            'description' => 'Busca marchas procesionales por título, fecha, dedicatoria, localidad y provincia.',
            'noindex' => true,
        ]);
    }

    public static function autorList(): void
    {
        [$criteria, $hasQuery, $page, $limit] = self::searchParams();
        $result = $hasQuery ? Repo::searchAutores(http_build_query($criteria), $page, $limit) : null;
        $result !== null ? Http::noStore() : Http::cachePublic(3600);
        View::render('autor_list', compact('criteria', 'result', 'page', 'limit'), [
            'title' => 'Buscador de compositores — Marchas de Cristo',
            'description' => 'Busca compositores de música procesional por nombre.',
            'noindex' => true,
        ]);
    }

    public static function bandaList(): void
    {
        [$criteria, $hasQuery, $page, $limit] = self::searchParams();
        $result = $hasQuery ? Repo::searchBandas(http_build_query($criteria), $page, $limit) : null;
        $result !== null ? Http::noStore() : Http::cachePublic(3600);
        View::render('banda_list', compact('criteria', 'result', 'page', 'limit'), [
            'title' => 'Buscador de bandas — Marchas de Cristo',
            'description' => 'Busca bandas de cornetas y tambores y agrupaciones musicales por nombre, localidad y provincia.',
            'noindex' => true,
        ]);
    }

    public static function discoList(): void
    {
        [$criteria, $hasQuery, $page, $limit] = self::searchParams();
        $result = $hasQuery ? Repo::searchDiscos(http_build_query($criteria), $page, $limit) : null;
        $result !== null ? Http::noStore() : Http::cachePublic(3600);
        View::render('disco_list', compact('criteria', 'result', 'page', 'limit'), [
            'title' => 'Buscador de discos — Marchas de Cristo',
            'description' => 'Busca discos de música procesional de Semana Santa por nombre.',
            'noindex' => true,
        ]);
    }

    // ── Detalles ──────────────────────────────────────────────────────────────
    public static function marchaDetail(array $p): void
    {
        $id = Slug::extractId($p['slugAndId']);
        if ($id === null) Http::notFound();
        $m = Repo::fetchMarcha($id);
        if ($m === null) Http::notFound();

        $canonical = Slug::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO']);
        if ('/marcha/' . $p['slugAndId'] !== $canonical) Http::redirect($canonical);

        $base = self::base();
        $url = $base . $canonical;
        $autores = implode(', ', array_map(static fn(array $a): string => (string) $a['nombre'], $m['AUTOR']));

        Http::cachePublic(3600);
        View::render('marcha_detail', ['m' => $m, 'url' => $url], [
            'title' => $m['TITULO'] . ' — Marchas de Cristo',
            'description' => 'Marcha procesional "' . $m['TITULO'] . '" compuesta por ' . $autores . '.'
                . (!empty($m['DEDICATORIA']) ? ' Dedicada a ' . $m['DEDICATORIA'] . '.' : ''),
            'og' => ['type' => 'music.song', 'title' => $m['TITULO'], 'description' => 'Marcha procesional compuesta por ' . $autores, 'url' => $url],
            'jsonld' => [
                Seo::marcha($m, $url),
                Seo::breadcrumbs([
                    ['name' => 'Inicio', 'url' => $base],
                    ['name' => 'Marchas', 'url' => $base . '/marcha'],
                    ['name' => $m['TITULO'], 'url' => $url],
                ]),
            ],
        ]);
    }

    public static function autorDetail(array $p): void
    {
        $id = Slug::extractId($p['slugAndId']);
        if ($id === null) Http::notFound();
        $a = Repo::fetchAutor($id);
        if ($a === null) Http::notFound();

        $fullName = trim(($a['NOMBRE'] ?? '') . ' ' . ($a['APELLIDOS'] ?? ''));
        $canonical = Slug::buildDetailPath('autor', $a['ID_AUTOR'], $fullName);
        if ('/autor/' . $p['slugAndId'] !== $canonical) Http::redirect($canonical);

        $base = self::base();
        $url = $base . $canonical;

        Http::cachePublic(3600);
        View::render('autor_detail', ['a' => $a, 'fullName' => $fullName, 'url' => $url], [
            'title' => $fullName . ' — Marchas de Cristo',
            'description' => 'Compositor de música procesional. Ha compuesto ' . $a['marchasLength'] . ' marchas.'
                . (!empty($a['LUGAR_NAC']) ? ' Natural de ' . $a['LUGAR_NAC'] . '.' : ''),
            'og' => ['type' => 'profile', 'title' => $fullName, 'description' => 'Compositor de ' . $a['marchasLength'] . ' marchas de música procesional', 'url' => $url],
            'jsonld' => [
                Seo::autor($a, $url),
                Seo::breadcrumbs([
                    ['name' => 'Inicio', 'url' => $base],
                    ['name' => 'Autores', 'url' => $base . '/autor'],
                    ['name' => $fullName, 'url' => $url],
                ]),
            ],
        ]);
    }

    public static function bandaDetail(array $p): void
    {
        $id = Slug::extractId($p['slugAndId']);
        if ($id === null) Http::notFound();
        $b = Repo::fetchBanda($id);
        if ($b === null) Http::notFound();

        $canonical = Slug::buildDetailPath('banda', $b['ID_BANDA'], (string) $b['NOMBRE_COMPLETO']);
        if ('/banda/' . $p['slugAndId'] !== $canonical) Http::redirect($canonical);

        $base = self::base();
        $url = $base . $canonical;

        Http::cachePublic(3600);
        View::render('banda_detail', ['b' => $b, 'url' => $url], [
            'title' => $b['NOMBRE_BREVE'] . ' — Marchas de Cristo',
            'description' => $b['NOMBRE_COMPLETO'] . ', banda de ' . $b['LOCALIDAD'] . '. Ha grabado ' . $b['discosLength'] . ' discos y estrenado ' . $b['marchasLength'] . ' marchas.',
            'og' => ['type' => 'music.playlist', 'title' => $b['NOMBRE_BREVE'], 'description' => 'Banda de música procesional de ' . $b['LOCALIDAD'], 'url' => $url],
            'jsonld' => [
                Seo::banda($b, $url),
                Seo::breadcrumbs([
                    ['name' => 'Inicio', 'url' => $base],
                    ['name' => 'Bandas', 'url' => $base . '/banda'],
                    ['name' => $b['NOMBRE_BREVE'], 'url' => $url],
                ]),
            ],
        ]);
    }

    public static function discoDetail(array $p): void
    {
        $id = Slug::extractId($p['slugAndId']);
        if ($id === null) Http::notFound();
        $d = Repo::fetchDisco($id);
        if ($d === null) Http::notFound();

        $canonical = Slug::buildDetailPath('disco', $d['ID_DISCO'], (string) $d['NOMBRE_CD']);
        if ('/disco/' . $p['slugAndId'] !== $canonical) Http::redirect($canonical);

        $base = self::base();
        $url = $base . $canonical;

        Http::cachePublic(3600);
        View::render('disco_detail', ['d' => $d, 'url' => $url], [
            'title' => $d['NOMBRE_CD'] . ' — Marchas de Cristo',
            'description' => 'Disco de música procesional "' . $d['NOMBRE_CD'] . '" de ' . $d['BANDA'] . '. Contiene ' . $d['marchasLength'] . ' marchas.',
            'og' => ['type' => 'music.album', 'title' => $d['NOMBRE_CD'], 'description' => 'Álbum de música procesional de ' . $d['BANDA'], 'url' => $url],
            'jsonld' => [
                Seo::disco($d, $url),
                Seo::breadcrumbs([
                    ['name' => 'Inicio', 'url' => $base],
                    ['name' => 'Discos', 'url' => $base . '/disco'],
                    ['name' => $d['NOMBRE_CD'], 'url' => $url],
                ]),
            ],
        ]);
    }

    // ── Estadísticas ──────────────────────────────────────────────────────────
    public static function estadisticas(): void
    {
        Http::cachePublic(1800);
        View::render('estadisticas', [
            'masAutor' => Repo::fetchMasAutor(),
            'masDedica' => Repo::fetchMasDedica(),
            'masEstreno' => Repo::fetchMasEstreno(),
            'masGrabada' => Repo::fetchMasGrabada(),
        ], [
            'title' => 'Estadísticas de música procesional — Marchas de Cristo',
            'description' => 'Los compositores con más marchas, bandas con más estrenos y marchas más grabadas.',
        ]);
    }

    // ── sitemap.xml ───────────────────────────────────────────────────────────
    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        Http::cachePublic(3600);
        $base = self::base();

        $urls = [
            [$base . '/', 'daily', '1.0'],
            [$base . '/marcha', 'weekly', '0.9'],
            [$base . '/autor', 'weekly', '0.8'],
            [$base . '/banda', 'weekly', '0.8'],
            [$base . '/disco', 'weekly', '0.8'],
            [$base . '/estadisticas', 'weekly', '0.7'],
        ];

        try {
            foreach (Db::all('SELECT ID_MARCHA AS id, TITULO AS label FROM marcha') as $r) {
                $urls[] = [$base . Slug::buildDetailPath('marcha', $r['id'], (string) $r['label']), 'monthly', '0.7'];
            }
            foreach (Db::all("SELECT ID_AUTOR AS id, (NOMBRE || ' ' || APELLIDOS) AS label FROM autor") as $r) {
                $urls[] = [$base . Slug::buildDetailPath('autor', $r['id'], (string) $r['label']), 'monthly', '0.6'];
            }
            // NOMBRE_COMPLETO para que el slug coincida con la canónica del detalle
            // (el detalle usa NOMBRE_COMPLETO; con NOMBRE_BREVE el sitemap daba 308).
            foreach (Db::all('SELECT ID_BANDA AS id, NOMBRE_COMPLETO AS label FROM banda') as $r) {
                $urls[] = [$base . Slug::buildDetailPath('banda', $r['id'], (string) $r['label']), 'monthly', '0.6'];
            }
            foreach (Db::all('SELECT ID_DISCO AS id, NOMBRE_CD AS label FROM disco') as $r) {
                $urls[] = [$base . Slug::buildDetailPath('disco', $r['id'], (string) $r['label']), 'monthly', '0.6'];
            }
        } catch (Throwable $e) {
            error_log('[sitemap] ' . $e->getMessage());
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $freq, $prio]) {
            echo '<url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>'
                . '<changefreq>' . $freq . '</changefreq>'
                . '<priority>' . $prio . '</priority></url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }

    // ── robots.txt ────────────────────────────────────────────────────────────
    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        Http::cachePublic(86400);
        $base = self::base();
        echo "User-Agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /login\n";
        echo "Disallow: /dashboard\n";
        echo "\n";
        echo "Sitemap: $base/sitemap.xml\n";
    }
}
