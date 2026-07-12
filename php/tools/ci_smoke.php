<?php

declare(strict_types=1);

/*
 * Smoke tests de CI sobre un servidor ya arrancado con la fixture de
 * ci_fixture.php (ver .github/workflows/ci.yml). Cubre las rutas doradas de
 * las 5 entidades públicas: 200, redirección 308 de slug/alias incorrecto a
 * la canónica, 404, JSON-LD parseable y sitemap bien formado con una muestra
 * de URLs comprobadas.
 *
 * No usa ningún framework de test (cero dependencias de Composer): un fallo
 * de aserción es una excepción capturada por el runner.
 *
 * Uso: php ci_smoke.php <base_url, p.ej. http://127.0.0.1:8000>
 */

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8000'), '/');

/** @return array{status:int,headers:array<string,string>,body:string} */
function httpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException("curl error en $url: " . curl_error($ch));
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $headers = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => (string) $body];
}

function assertStatus(string $path, int $expected, string $base): array
{
    $r = httpGet($base . $path);
    if ($r['status'] !== $expected) {
        throw new RuntimeException("$path → esperado $expected, obtenido {$r['status']}");
    }
    return $r;
}

function assertRedirect(string $path, string $expectedLocationSuffix, string $base): void
{
    $r = httpGet($base . $path);
    if ($r['status'] !== 308 && $r['status'] !== 301) {
        throw new RuntimeException("$path → esperado 308/301, obtenido {$r['status']}");
    }
    $loc = $r['headers']['location'] ?? '';
    if (!str_ends_with($loc, $expectedLocationSuffix)) {
        throw new RuntimeException("$path → Location '$loc' no termina en '$expectedLocationSuffix'");
    }
}

/** Extrae y valida cada bloque <script type="application/ld+json">, exige @type. */
function assertJsonLd(string $path, string $base, ?string $expectType = null): void
{
    $r = assertStatus($path, 200, $base);
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $m);
    if ($m[1] === []) {
        throw new RuntimeException("$path → sin bloques JSON-LD");
    }
    $foundType = false;
    foreach ($m[1] as $json) {
        $decoded = json_decode($json, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("$path → JSON-LD inválido: " . json_last_error_msg());
        }
        if ($expectType !== null && ($decoded['@type'] ?? null) === $expectType) {
            $foundType = true;
        }
    }
    if ($expectType !== null && !$foundType) {
        throw new RuntimeException("$path → ningún bloque JSON-LD con @type=$expectType");
    }
}

function assertContains(string $path, string $needle, string $base): void
{
    $r = assertStatus($path, 200, $base);
    if (!str_contains($r['body'], $needle)) {
        throw new RuntimeException("$path → no contiene '$needle'");
    }
}

function assertHeader(string $path, string $headerName, string $needle, string $base): void
{
    $r = assertStatus($path, 200, $base);
    $val = $r['headers'][strtolower($headerName)] ?? '';
    if (!str_contains($val, $needle)) {
        throw new RuntimeException("$path → cabecera $headerName='$val' no contiene '$needle'");
    }
}

function assertNoIndex(string $path, string $base): void
{
    $r = assertStatus($path, 200, $base);
    if (!str_contains($r['body'], 'name="robots" content="noindex"')) {
        throw new RuntimeException("$path → esperaba <meta name=\"robots\" content=\"noindex\">");
    }
}

function assertNotNoIndex(string $path, string $base): void
{
    $r = assertStatus($path, 200, $base);
    if (str_contains($r['body'], 'name="robots" content="noindex"')) {
        throw new RuntimeException("$path → no debería llevar noindex");
    }
}

function assertSitemap(string $base): void
{
    $r = assertStatus('/sitemap.xml', 200, $base);
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadXML($r['body']);
    if (!$ok) {
        $errs = array_map(static fn($e) => trim($e->message), libxml_get_errors());
        throw new RuntimeException('/sitemap.xml → XML mal formado: ' . implode('; ', $errs));
    }
    $locs = [];
    foreach ($dom->getElementsByTagName('loc') as $node) {
        $locs[] = $node->textContent;
    }
    if (count($locs) < 5) {
        throw new RuntimeException('/sitemap.xml → se esperaban al menos 5 <loc>, hay ' . count($locs));
    }
    $lastmods = $dom->getElementsByTagName('lastmod');
    if ($lastmods->length !== count($locs)) {
        throw new RuntimeException('/sitemap.xml → cada <url> debería llevar su <lastmod> (C2)');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmods->item(0)?->textContent ?? '')) {
        throw new RuntimeException('/sitemap.xml → <lastmod> no tiene forma YYYY-MM-DD');
    }
    // Muestra: home + primeras 4 URLs de detalle/hub, comprobadas con GET real.
    $sample = array_slice($locs, 0, 5);
    foreach ($sample as $loc) {
        $path = (string) parse_url($loc, PHP_URL_PATH);
        $qs = parse_url($loc, PHP_URL_QUERY);
        $rel = $path . ($qs ? '?' . $qs : '');
        $s = httpGet($base . $rel)['status'];
        if ($s !== 200) {
            throw new RuntimeException("/sitemap.xml → muestra '$rel' devolvió $s (se esperaba 200)");
        }
    }
}

// ── Suite ────────────────────────────────────────────────────────────────
$tests = [
    'home 200' => static fn() => assertStatus('/', 200, $base),
    'robots.txt 200 + Sitemap:' => static fn() => assertContains('/robots.txt', 'Sitemap:', $base),
    'health 200' => static fn() => assertStatus('/health', 200, $base),

    // ── Explorador (noindex, sin caché con query) ──────────────────────────
    'marcha explorador 200' => static fn() => assertStatus('/marcha', 200, $base),
    'marcha explorador noindex' => static fn() => assertNoIndex('/marcha', $base),
    'marcha búsqueda no-store' => static fn() => assertHeader('/marcha?titulo=consuelo', 'Cache-Control', 'no-store', $base),

    // ── Ficha de marcha: canónica, redirecciones, JSON-LD ──────────────────
    'marcha canónica 200' => static fn() => assertStatus('/marcha/consuelo-gitano-1', 200, $base),
    'marcha canónica JSON-LD MusicComposition' => static fn() => assertJsonLd('/marcha/consuelo-gitano-1', $base, 'MusicComposition'),
    'marcha solo-ID → 308 canónica' => static fn() => assertRedirect('/marcha/1', '/marcha/consuelo-gitano-1', $base),
    'marcha slug incorrecto → 308 canónica' => static fn() => assertRedirect('/marcha/titulo-erroneo-1', '/marcha/consuelo-gitano-1', $base),
    'marcha inexistente 404' => static fn() => assertStatus('/marcha/nada-999999', 404, $base),

    // ── Autor / Banda / Disco / Dedicatoria ─────────────────────────────────
    'autor listado 200' => static fn() => assertStatus('/autor', 200, $base),
    'autor ficha 200 + JSON-LD Person' => static fn() => assertJsonLd('/autor/jose-garcia-perez-1', $base, 'Person'),
    'banda listado 200' => static fn() => assertStatus('/banda', 200, $base),
    'banda ficha 200 + JSON-LD MusicGroup' => static fn() => assertJsonLd('/banda/banda-de-cctt-ntra-sra-de-la-victoria-las-cigarreras-1', $base, 'MusicGroup'),
    'disco listado 200' => static fn() => assertStatus('/disco', 200, $base),
    'disco ficha 200 + JSON-LD MusicAlbum' => static fn() => assertJsonLd('/disco/sevilla-cofrade-vol-1-1', $base, 'MusicAlbum'),
    'dedicatorias índice 200' => static fn() => assertStatus('/dedicatorias', 200, $base),
    'dedicatoria ficha 200 + JSON-LD CollectionPage' => static fn() => assertJsonLd('/dedicatoria/hdad-de-los-gitanos-sevilla-1', $base, 'CollectionPage'),
    'dedicatoria inexistente 404' => static fn() => assertStatus('/dedicatoria/nada-999999', 404, $base),

    'estadisticas 200' => static fn() => assertStatus('/estadisticas', 200, $base),

    // ── Hubs de catálogo indexables (C1) ────────────────────────────────────
    'hub año con sustancia 200 + indexable' => static fn() => assertNotNoIndex('/marcha/ano/1995', $base),
    'hub año thin → noindex' => static fn() => assertNoIndex('/marcha/ano/1990', $base),
    'hub año inexistente 404' => static fn() => assertStatus('/marcha/ano/1800', 404, $base),
    'hub estilo alias cctt → 308 canónica' => static fn() => assertRedirect('/marcha/estilo/cctt', '/marcha/estilo/cornetas-y-tambores', $base),
    'hub estilo canónico 200 + JSON-LD CollectionPage' => static fn() => assertJsonLd('/marcha/estilo/cornetas-y-tambores', $base, 'CollectionPage'),
    'hub estilo desconocido 404' => static fn() => assertStatus('/marcha/estilo/inexistente', 404, $base),
    'hub provincia con mayúsculas → 308 canónica' => static fn() => assertRedirect('/marcha/provincia/Sevilla', '/marcha/provincia/sevilla', $base),
    'hub provincia canónica 200' => static fn() => assertStatus('/marcha/provincia/sevilla', 200, $base),
    'hub provincia desconocida 404' => static fn() => assertStatus('/marcha/provincia/nada', 404, $base),

    'sitemap.xml bien formado + muestra 200' => static fn() => assertSitemap($base),
];

$failed = [];
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "  OK   $name\n";
    } catch (Throwable $e) {
        $failed[] = "$name: {$e->getMessage()}";
        echo "  FAIL $name — {$e->getMessage()}\n";
    }
}

echo "\n" . count($tests) - count($failed) . '/' . count($tests) . " pruebas superadas.\n";
if ($failed !== []) {
    fwrite(STDERR, "\nFallos:\n" . implode("\n", $failed) . "\n");
    exit(1);
}
