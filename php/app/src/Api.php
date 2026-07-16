<?php

declare(strict_types=1);

namespace App;

/**
 * API JSON pública de solo lectura (M1). Reutiliza EXACTAMENTE las mismas
 * lecturas que las páginas HTML (Repo::fetch*), pero proyecta cada entidad a
 * una forma JSON estable y documentada — no vuelca la estructura interna del
 * Repo (que lleva campos de presentación: REG_POS, timeline, ESTRENOS_MAP…).
 *
 * Objetivo (foco SEO/IA del consejo de sabios): ser una fuente de datos
 * citable y fácil de consumir por robots/LLMs. Por eso cada respuesta:
 *   - incluye la URL canónica HTML de la entidad (para citar/enlazar),
 *   - incluye el bloque de licencia (CC BY 4.0 + atribución),
 *   - permite CORS (Access-Control-Allow-Origin: *): son datos públicos.
 *
 * Contrato de URLs: /api/{entidad}/{id}.json  (id numérico o slug-id).
 */
final class Api
{
    private static function base(): string
    {
        return rtrim((string) ($GLOBALS['config']['site_url'] ?? 'https://marchasdecristo.com'), '/');
    }

    /**
     * Bloque de licencia, idéntico en la API, el feed, la página «Datos» y el
     * JSON-LD Dataset. Fuente única de verdad de la política de citación.
     *
     * @return array{nombre:string,nombre_completo:string,url:string,atribucion:string}
     */
    public static function licencia(): array
    {
        return [
            'nombre'          => 'CC BY 4.0',
            'nombre_completo' => 'Creative Commons Atribución 4.0 Internacional',
            'url'             => 'https://creativecommons.org/licenses/by/4.0/',
            'atribucion'      => 'marchasdecristo.com',
        ];
    }

    /** Año como entero o null (FECHA viene normalizada a 's/f' cuando falta). */
    private static function anio(mixed $fecha): ?int
    {
        if ($fecha === null || $fecha === '' || $fecha === 's/f') {
            return null;
        }
        $y = (int) $fecha;
        return $y > 0 ? $y : null;
    }

    /** Cadena no vacía o null (evita "" y espacios en el JSON). */
    private static function str(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s !== '' ? $s : null;
    }

    private static function entero(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }

    // ── Emisión ──────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $payload
     */
    private static function emit(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        // Datos públicos: consumibles desde cualquier origen (fetch de otra web,
        // agente, cuaderno de análisis). Solo GET, solo lectura.
        header('Access-Control-Allow-Origin: *');
        if ($status === 200) {
            Http::cachePublic(3600);
        } else {
            Http::noStore();
        }
        echo (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }

    private static function notFound(string $entidad): never
    {
        self::emit([
            'error'   => 'not_found',
            'entidad' => $entidad,
            'mensaje' => 'No existe un recurso de este tipo con ese identificador.',
        ], 404);
    }

    /** Extrae el id numérico de "5" o "consuelo-gitano-5"; null si no hay. */
    private static function id(mixed $raw): ?string
    {
        return Slug::extractId((string) $raw);
    }

    // ── Enlaces internos (misma canónica que el HTML) ────────────────────────

    /** @return array{id:int,nombre:string,url:string} */
    private static function refAutor(int $id, ?string $nombre): array
    {
        $nombre = (string) ($nombre ?? '');
        return [
            'id'     => $id,
            'nombre' => $nombre,
            'url'    => self::base() . Slug::buildDetailPath('autor', $id, $nombre),
        ];
    }

    /** @return array{id:int,nombre:string,url:string} */
    private static function refBanda(int $id, string $nombre): array
    {
        return [
            'id'     => $id,
            'nombre' => $nombre,
            'url'    => self::base() . Slug::buildDetailPath('banda', $id, $nombre),
        ];
    }

    /** @return array{id:int,titulo:string,url:string} */
    private static function refMarcha(int $id, string $titulo): array
    {
        return [
            'id'     => $id,
            'titulo' => $titulo,
            'url'    => self::base() . Slug::buildDetailPath('marcha', $id, $titulo),
        ];
    }

    /** @return array{id:int,titulo:string,url:string} */
    private static function refDisco(int $id, string $titulo): array
    {
        return [
            'id'     => $id,
            'titulo' => $titulo,
            'url'    => self::base() . Slug::buildDetailPath('disco', $id, $titulo),
        ];
    }

    // ── Controladores ────────────────────────────────────────────────────────

    public static function marcha(array $p): void
    {
        $id = self::id($p['id'] ?? null);
        if ($id === null) {
            self::notFound('marcha');
        }
        $m = Repo::fetchMarcha($id);
        if ($m === null) {
            self::notFound('marcha');
        }

        $mid = (int) $m['ID_MARCHA'];
        $titulo = (string) $m['TITULO'];

        $compositores = array_map(
            static fn(array $a): array => self::refAutor((int) $a['autorId'], $a['nombre'] ?? ''),
            $m['AUTOR']
        );

        $banda = null;
        if (!empty($m['BANDA_ESTRENO'])) {
            $nombre = self::str($m['BANDA_NOMBRE_COMPLETO'] ?? null) ?? (string) $m['BANDA'];
            $banda = self::refBanda((int) $m['BANDA_ESTRENO'], $nombre);
        }

        $grabaciones = array_map(static function (array $d): array {
            return [
                'disco' => self::refDisco((int) $d['ID_DISCO'], (string) $d['NOMBRE_CD']),
                'anio'  => self::anio($d['FECHA_CD'] ?? null),
                'banda' => self::str($d['BANDA'] ?? null),
                'pista' => self::entero($d['NUMEROMARCHA'] ?? null),
            ];
        }, $m['discos'] ?? []);

        self::emit([
            'recurso'      => 'marcha',
            'id'           => $mid,
            'titulo'       => $titulo,
            'anio'         => self::anio($m['FECHA'] ?? null),
            'tipo'         => self::str($m['TIPO'] ?? null),
            'estilo'       => self::str($m['ESTILO'] ?? null),
            'dedicatoria'  => self::str($m['DEDICATORIA'] ?? null),
            'localidad'    => self::str($m['LOCALIDAD'] ?? null),
            'provincia'    => self::str($m['PROVINCIA'] ?? null),
            'duracion_seg' => self::entero($m['DURACION_SEG'] ?? null),
            'audio_url'    => self::str($m['AUDIO'] ?? null),
            'notas'        => self::str($m['DETALLES_MARCHA'] ?? null),
            'compositores' => $compositores,
            'banda_estreno' => $banda,
            'grabaciones'  => $grabaciones,
            'url'          => self::base() . Slug::buildDetailPath('marcha', $mid, $titulo),
            'licencia'     => self::licencia(),
        ]);
    }

    public static function autor(array $p): void
    {
        $id = self::id($p['id'] ?? null);
        if ($id === null) {
            self::notFound('autor');
        }
        $a = Repo::fetchAutor($id);
        if ($a === null) {
            self::notFound('autor');
        }

        $aid = (int) $a['ID_AUTOR'];
        $nombre = trim(((string) ($a['NOMBRE'] ?? '')) . ' ' . ((string) ($a['APELLIDOS'] ?? '')));

        $nacimiento = null;
        $anioNac = self::anio($a['F_NAC'] ?? null);
        $lugarNac = self::str($a['LUGAR_NAC'] ?? null);
        if ($anioNac !== null || $lugarNac !== null) {
            $nacimiento = ['anio' => $anioNac, 'lugar' => $lugarNac];
        }

        $marchas = array_map(static function (array $m): array {
            $ref = self::refMarcha((int) $m['ID_MARCHA'], (string) $m['TITULO']);
            $ref['anio'] = self::anio($m['FECHA'] ?? null);
            return $ref;
        }, $a['marchas'] ?? []);

        self::emit([
            'recurso'            => 'autor',
            'id'                 => $aid,
            'nombre'             => $nombre,
            'nombre_artistico'   => self::str($a['NOMBRE_ART'] ?? null),
            'nacimiento'         => $nacimiento,
            'defuncion_anio'     => self::anio($a['F_DEF'] ?? null),
            'biografia'          => self::str($a['BIO'] ?? null),
            'n_marchas'          => (int) ($a['marchasLength'] ?? count($marchas)),
            'marchas'            => $marchas,
            'url'                => self::base() . Slug::buildDetailPath('autor', $aid, $nombre),
            'licencia'           => self::licencia(),
        ]);
    }

    public static function banda(array $p): void
    {
        $id = self::id($p['id'] ?? null);
        if ($id === null) {
            self::notFound('banda');
        }
        $b = Repo::fetchBanda($id);
        if ($b === null) {
            self::notFound('banda');
        }

        $bid = (int) $b['ID_BANDA'];
        $nombre = self::str($b['NOMBRE_COMPLETO'] ?? null) ?? (string) ($b['NOMBRE_BREVE'] ?? '');

        $marchas = array_map(static function (array $m): array {
            $ref = self::refMarcha((int) $m['ID_MARCHA'], (string) $m['TITULO']);
            $ref['anio'] = self::anio($m['FECHA'] ?? null);
            return $ref;
        }, $b['marchas'] ?? []);

        $discos = array_map(static function (array $d): array {
            $ref = self::refDisco((int) $d['ID_DISCO'], (string) $d['NOMBRE_CD']);
            $ref['anio'] = self::anio($d['FECHA_CD'] ?? null);
            return $ref;
        }, $b['discos'] ?? []);

        self::emit([
            'recurso'            => 'banda',
            'id'                 => $bid,
            'nombre'             => $nombre,
            'nombre_breve'       => self::str($b['NOMBRE_BREVE'] ?? null),
            'localidad'          => self::str($b['LOCALIDAD'] ?? null),
            'provincia'          => self::str($b['PROVINCIA'] ?? null),
            'fundacion_anio'     => self::anio($b['FECHA_FUND'] ?? null),
            'extincion_anio'     => self::anio($b['FECHA_EXT'] ?? null),
            'n_marchas_estreno'  => (int) ($b['marchasLength'] ?? count($marchas)),
            'n_discos'           => (int) ($b['discosLength'] ?? count($discos)),
            'marchas_estrenadas' => $marchas,
            'discos'             => $discos,
            'url'                => self::base() . Slug::buildDetailPath('banda', $bid, $nombre),
            'licencia'           => self::licencia(),
        ]);
    }

    public static function disco(array $p): void
    {
        $id = self::id($p['id'] ?? null);
        if ($id === null) {
            self::notFound('disco');
        }
        $d = Repo::fetchDisco($id);
        if ($d === null) {
            self::notFound('disco');
        }

        $did = (int) $d['ID_DISCO'];
        $titulo = (string) $d['NOMBRE_CD'];

        $banda = null;
        if (!empty($d['ID_BANDA'])) {
            // NOMBRE_COMPLETO para que la URL sea la canónica de la banda (igual
            // que en la ficha HTML); el nombre visible sigue siendo el corto.
            $nombreUrl = self::str($d['BANDA_COMPLETO'] ?? null) ?? (string) ($d['BANDA_BREVE'] ?? '');
            $banda = self::refBanda((int) $d['ID_BANDA'], $nombreUrl);
            $banda['nombre'] = self::str($d['BANDA'] ?? null) ?? $banda['nombre'];
        }

        $marchas = array_map(static function (array $m): array {
            $ref = self::refMarcha((int) $m['ID_MARCHA'], (string) $m['TITULO']);
            $ref['anio']  = self::anio($m['FECHA'] ?? null);
            $ref['pista'] = self::entero($m['NUMEROMARCHA'] ?? null);
            return $ref;
        }, $d['marchas'] ?? []);

        self::emit([
            'recurso'    => 'disco',
            'id'         => $did,
            'titulo'     => $titulo,
            'anio'       => self::anio($d['FECHA_CD'] ?? null),
            'banda'      => $banda,
            'n_marchas'  => (int) ($d['marchasLength'] ?? count($marchas)),
            'marchas'    => $marchas,
            'url'        => self::base() . Slug::buildDetailPath('disco', $did, $titulo),
            'licencia'   => self::licencia(),
        ]);
    }
}
