<?php

declare(strict_types=1);

namespace App;

use Normalizer;

/**
 * Port de nextjs/lib/schema.ts — genera los objetos JSON-LD (schema.org).
 * OJO: schema.ts usa un slugify PROPIO (distinto de lib/slugify.ts) para las
 * URLs internas del JSON-LD; se replica aquí en self::slugify() para paridad.
 * Las claves se construyen en el mismo orden que el objeto JS y se omiten las
 * que en JS quedarían `undefined`.
 */
final class Seo
{
    private static function base(): string
    {
        return rtrim((string) ($GLOBALS['config']['site_url'] ?? 'https://marchasdecristo.com'), '/');
    }

    /** slugify de schema.ts (no el de Slug::slugify). */
    private static function slugify(string $text): string
    {
        $s = mb_strtolower($text, 'UTF-8');
        $n = Normalizer::normalize($s, Normalizer::FORM_D);
        if ($n !== false) $s = $n;
        $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', $s) ?? $s;
        $s = preg_replace('/[^\w\s-]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', '-', $s) ?? $s;
        $s = preg_replace('/-+/', '-', $s) ?? $s;
        return $s;
    }

    private static function formatDate(mixed $dateStr): ?string
    {
        if (empty($dateStr)) return null;
        $str = (string) $dateStr;
        if (str_contains($str, '-')) return $str;
        if (strlen($str) === 4) return $str;
        return preg_match('/^\d{4}/', $str) ? $str : null;
    }

    public static function marcha(array $data, string $url): array
    {
        $base = self::base();
        $autores = array_map(static fn(array $a): array => [
            '@type' => 'Person',
            'name' => $a['nombre'],
            'url' => "$base/autor/" . self::slugify((string) $a['nombre']) . '-' . $a['autorId'],
        ], $data['AUTOR']);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicComposition',
            'name' => $data['TITULO'],
            'url' => $url,
        ];
        if (count($autores) > 0) $schema['creator'] = $autores;
        if (!empty($data['FECHA'])) $schema['dateCreated'] = (string) $data['FECHA'];
        $schema['description'] = 'Marcha procesional'
            . (!empty($data['DEDICATORIA']) ? ' dedicada a ' . $data['DEDICATORIA'] : '') . '.';
        if (!empty($data['BANDA_ESTRENO'])) {
            $schema['performanceLocation'] = [
                '@type' => 'MusicGroup',
                'name' => $data['BANDA'],
                'url' => "$base/banda/" . self::slugify((string) $data['BANDA']) . '-' . $data['BANDA_ESTRENO'],
            ];
        }
        if (($data['discosLength'] ?? 0) > 0) {
            $schema['recordedAs'] = array_map(static fn(array $d): array => [
                '@type' => 'MusicRecording',
                'name' => $d['NOMBRE_CD'],
                'byArtist' => ['@type' => 'MusicGroup', 'name' => $d['BANDA']],
            ], $data['discos']);
        }

        // Vídeo asociado (VideoObject). Solo se emite cuando conocemos la fecha
        // de publicación (uploadDate): Google la exige como campo obligatorio,
        // así que sin ella preferimos no generar un item inválido en el informe
        // de vídeos de Search Console.
        $ytid = Media::youtubeId($data['AUDIO'] ?? null);
        if ($ytid !== null && !empty($data['VIDEO_UPLOAD'])) {
            $schema['associatedMedia'] = [
                '@type' => 'VideoObject',
                'name' => 'Interpretación de «' . $data['TITULO'] . '»',
                'description' => 'Interpretación en vídeo de la marcha procesional «' . $data['TITULO'] . '»'
                    . (!empty($data['BANDA_ESTRENO']) ? ' por ' . $data['BANDA'] : '') . '.',
                'thumbnailUrl' => Media::youtubeThumb($ytid),
                'uploadDate' => (string) $data['VIDEO_UPLOAD'],
                'contentUrl' => $data['AUDIO'],
                'embedUrl' => Media::youtubeEmbed($ytid),
            ];
        }
        return $schema;
    }

    public static function autor(array $data, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => trim(($data['NOMBRE'] ?? '') . ' ' . ($data['APELLIDOS'] ?? '')),
            'url' => $url,
        ];
        if (!empty($data['F_NAC'])) {
            $bd = self::formatDate($data['F_NAC']);
            if ($bd !== null) $schema['birthDate'] = $bd;
        }
        if (!empty($data['LUGAR_NAC'])) {
            $schema['birthPlace'] = ['@type' => 'Place', 'name' => $data['LUGAR_NAC']];
        }
        $schema['description'] = 'Compositor de música procesional. Ha compuesto ' . $data['marchasLength'] . ' marchas.';
        if (!empty($data['BIO'])) $schema['knowsAbout'] = $data['BIO'];
        return $schema;
    }

    public static function banda(array $data, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicGroup',
            'name' => !empty($data['NOMBRE_COMPLETO']) ? $data['NOMBRE_COMPLETO'] : $data['NOMBRE_BREVE'],
            'url' => $url,
        ];
        if (!empty($data['FECHA_FUND'])) $schema['foundingDate'] = (string) $data['FECHA_FUND'];
        if (!empty($data['FECHA_EXT'])) $schema['dissolutionDate'] = (string) $data['FECHA_EXT'];
        $schema['location'] = ['@type' => 'Place', 'name' => $data['LOCALIDAD']];
        $schema['description'] = 'Banda de música procesional. Ha estrenado ' . $data['marchasLength']
            . ' marchas y grabado ' . $data['discosLength'] . ' discos.';
        return $schema;
    }

    public static function disco(array $data, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicAlbum',
            'name' => $data['NOMBRE_CD'],
            'url' => $url,
            'byArtist' => ['@type' => 'MusicGroup', 'name' => $data['BANDA']],
        ];
        if (!empty($data['FECHA_CD'])) $schema['datePublished'] = (string) $data['FECHA_CD'];
        $schema['description'] = 'Disco que contiene ' . $data['marchasLength'] . ' marchas de música procesional.';
        if (($data['marchasLength'] ?? 0) > 0) {
            $marchas = $data['marchas'];
            $schema['tracks'] = [
                '@type' => 'ItemList',
                'itemListElement' => array_map(
                    static fn(array $m, int $idx): array => [
                        '@type' => 'MusicRecording',
                        'position' => $idx + 1,
                        'name' => $m['TITULO'],
                        'byArtist' => array_map(
                            static fn(array $a): array => ['@type' => 'Person', 'name' => $a['nombre']],
                            $m['AUTOR']
                        ),
                    ],
                    $marchas,
                    array_keys($marchas)
                ),
            ];
        }
        return $schema;
    }

    /**
     * Hub de dedicatoria (pantalla N-01): CollectionPage cuyo mainEntity es un
     * ItemList de las marchas dedicadas a la advocación.
     *
     * @param array{NOMBRE:string,LOCALIDAD:string,marchas:list<array<string,mixed>>} $data
     */
    public static function dedicatoria(array $data, string $url): array
    {
        $loc = trim((string) ($data['LOCALIDAD'] ?? ''));
        $titular = $data['NOMBRE'] . ($loc !== '' ? ' (' . $loc . ')' : '');
        $items = array_map(
            static fn(array $m, int $idx): array => [
                '@type' => 'ListItem',
                'position' => $idx + 1,
                'item' => [
                    '@type' => 'MusicComposition',
                    'name' => $m['TITULO'],
                    'url' => self::base() . '/marcha/' . self::slugify((string) $m['TITULO']) . '-' . $m['ID_MARCHA'],
                ],
            ],
            $data['marchas'],
            array_keys($data['marchas'])
        );
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'Marchas dedicadas a ' . $titular,
            'url' => $url,
            'description' => 'Marchas procesionales dedicadas a ' . $titular . '.',
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($items),
                'itemListElement' => $items,
            ],
        ];
    }

    /** @param list<array{name:string,url:string}> $items */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn(array $it, int $idx): array => [
                    '@type' => 'ListItem',
                    'position' => $idx + 1,
                    'name' => $it['name'],
                    'item' => $it['url'],
                ],
                $items,
                array_keys($items)
            ),
        ];
    }

    /** Serializa un objeto schema para incrustar en <script type="application/ld+json">. */
    public static function json(array $schema): string
    {
        return (string) json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );
    }
}
