<?php

declare(strict_types=1);

namespace App;

/**
 * Coropleta SVG de provincias (N-10). El SVG base (assets/mapa-provincias.svg,
 * ver su README de origen/licencia) trae un <g id="ES-XX"> vacío por provincia
 * (código ISO 3166-2:ES) sin relleno; aquí se colorea por nivel de recuento y
 * se enlaza al hub de esa provincia.
 */
final class Mapa
{
    /**
     * Código ISO 3166-2:ES => nombre de provincia, tal cual aparece en el SVG
     * base y coincide con marcha.PROVINCIA/banda.PROVINCIA en la BD (ambos
     * heredan la forma castellana histórica: "La Coruña", "Gerona"…).
     */
    public const PROVINCIAS = [
        'ES-A' => 'Alicante', 'ES-AB' => 'Albacete', 'ES-AL' => 'Almería',
        'ES-AV' => 'Ávila', 'ES-B' => 'Barcelona', 'ES-BA' => 'Badajoz',
        'ES-BI' => 'Vizcaya', 'ES-BU' => 'Burgos', 'ES-C' => 'La Coruña',
        'ES-CA' => 'Cádiz', 'ES-CC' => 'Cáceres', 'ES-CE' => 'Ceuta',
        'ES-CO' => 'Córdoba', 'ES-CR' => 'Ciudad Real', 'ES-CS' => 'Castellón',
        'ES-CU' => 'Cuenca', 'ES-GC' => 'Las Palmas', 'ES-GI' => 'Gerona',
        'ES-GR' => 'Granada', 'ES-GU' => 'Guadalajara', 'ES-H' => 'Huelva',
        'ES-HU' => 'Huesca', 'ES-J' => 'Jaén', 'ES-L' => 'Lérida',
        'ES-LE' => 'León', 'ES-LO' => 'La Rioja', 'ES-LU' => 'Lugo',
        'ES-M' => 'Madrid', 'ES-MA' => 'Málaga', 'ES-ML' => 'Melilla',
        'ES-MU' => 'Murcia', 'ES-NA' => 'Navarra', 'ES-O' => 'Asturias',
        'ES-OR' => 'Orense', 'ES-P' => 'Palencia', 'ES-PM' => 'Baleares',
        'ES-PO' => 'Pontevedra', 'ES-S' => 'Cantabria', 'ES-SA' => 'Salamanca',
        'ES-SE' => 'Sevilla', 'ES-SG' => 'Segovia', 'ES-SO' => 'Soria',
        'ES-SS' => 'Guipúzcoa', 'ES-T' => 'Tarragona', 'ES-TE' => 'Teruel',
        'ES-TF' => 'Santa Cruz de Tenerife', 'ES-TO' => 'Toledo', 'ES-V' => 'Valencia',
        'ES-VA' => 'Valladolid', 'ES-VI' => 'Álava', 'ES-Z' => 'Zaragoza',
        'ES-ZA' => 'Zamora',
    ];

    /**
     * Cortes de recuento => nivel de intensidad (1-4; 0 = sin datos, 5 = por
     * encima del último corte). Ajustados a la distribución real del catálogo
     * (muy concentrado en Andalucía: Sevilla sola pasa de 1.300) en vez de una
     * escala lineal, que dejaría todo salvo Sevilla casi en blanco.
     */
    private const CORTES = [1 => 9, 2 => 49, 3 => 149, 4 => 399];

    private static function nivel(int $n): int
    {
        if ($n === 0) {
            return 0;
        }
        foreach (self::CORTES as $nivel => $max) {
            if ($n <= $max) {
                return $nivel;
            }
        }
        return 5;
    }

    /**
     * Transformación afín lat/lng → coordenadas del viewBox de mapa-provincias.svg
     * (0 0 569 392), ajustada por mínimos cuadrados entre el centro geográfico
     * real de cada provincia (calculado a partir de app/geo/municipios_es.php)
     * y el centro (bbox) de su <g id="ES-XX"> en el SVG. Error medio ~5.5
     * unidades de las 569×392 en las provincias peninsulares/Baleares/Ceuta/
     * Melilla usadas para calibrar — aproximado, no una proyección cartográfica
     * exacta (el usuario aceptó esta imprecisión a cambio de no depender de
     * mapas/tiles externos). Canarias no se calibra: en este SVG se dibuja
     * como recuadro aparte, fuera de su posición geográfica real.
     * x = AFFINE_X[0]*lng + AFFINE_X[1]*lat + AFFINE_X[2]  (e igual para y)
     */
    private const AFFINE_X = [30.344918062123682, 0.5291605450524717, 401.85804883522326];
    private const AFFINE_Y = [-0.006727446051473969, -40.566595540504814, 1786.7949572953016];

    /** @var array<string,array{0:float,1:float}>|null localidad|provincia normalizados → [lat,lng] */
    private static ?array $municipiosIndex = null;

    /** @return array<string,array{0:float,1:float}> */
    private static function municipiosIndex(): array
    {
        if (self::$municipiosIndex !== null) {
            return self::$municipiosIndex;
        }
        $rows = require APP_DIR . '/geo/municipios_es.php';
        $idx = [];
        foreach ($rows as [$provincia, $nombre, $lat, $lng]) {
            $idx[Db::noAcc($provincia) . '|' . Db::noAcc($nombre)] = [(float) $lat, (float) $lng];
        }
        return self::$municipiosIndex = $idx;
    }

    /**
     * Localidades con coordenadas conocidas, ya proyectadas a x/y del SVG.
     * Las localidades sin match en municipios_es.php (variantes de nombre no
     * cubiertas, pedanías, etc.) se omiten sin más — no hay dónde pintarlas.
     *
     * @param  list<array{LOCALIDAD:string,PROVINCIA:string,N:int}> $porLocalidad  Repo::hubLocalidades()
     * @return list<array{x:float,y:float,localidad:string,provincia:string,n:int}>
     */
    public static function puntos(array $porLocalidad): array
    {
        $idx = self::municipiosIndex();
        $out = [];
        foreach ($porLocalidad as $r) {
            $localidad = (string) $r['LOCALIDAD'];
            $provincia = (string) $r['PROVINCIA'];
            $key = Db::noAcc($provincia) . '|' . Db::noAcc($localidad);
            if (!isset($idx[$key])) {
                continue;
            }
            [$lat, $lng] = $idx[$key];
            $out[] = [
                'x' => self::AFFINE_X[0] * $lng + self::AFFINE_X[1] * $lat + self::AFFINE_X[2],
                'y' => self::AFFINE_Y[0] * $lng + self::AFFINE_Y[1] * $lat + self::AFFINE_Y[2],
                'localidad' => $localidad,
                'provincia' => $provincia,
                'n' => (int) $r['N'],
            ];
        }
        return $out;
    }

    /** Radio del punto (px) por recuento: escala logarítmica para que las
     *  grandes ciudades no eclipsen a los pueblos con pocas marchas. */
    private static function radio(int $n): float
    {
        return min(6.0, 1.3 + log($n + 1, 2) * 0.85);
    }

    /**
     * @param list<array{K:string,N:int}> $porProvincia  Repo::hubProvincias()
     * @param list<array{x:float,y:float,localidad:string,provincia:string,n:int}> $puntos  self::puntos()
     * @return string  Markup <svg>…</svg> listo para imprimir sin escapar
     *                 (se construye aquí, no viene de entrada de usuario).
     */
    public static function render(array $porProvincia, array $puntos = []): string
    {
        $conteo = [];
        foreach ($porProvincia as $r) {
            $conteo[(string) $r['K']] = (int) $r['N'];
        }

        $dom = new \DOMDocument();
        $dom->loadXML((string) file_get_contents(PUBLIC_DIR . '/assets/mapa-provincias.svg'));
        $svgEl = $dom->documentElement;

        foreach (iterator_to_array($svgEl->childNodes) as $g) {
            if (!($g instanceof \DOMElement) || $g->nodeName !== 'g') {
                continue;
            }
            $iso = $g->getAttribute('id');
            $nombre = self::PROVINCIAS[$iso] ?? null;
            if ($nombre === null) {
                continue;
            }
            $n = $conteo[$nombre] ?? 0;
            $g->setAttribute('class', 'prov prov-' . self::nivel($n));

            $title = $dom->createElement('title');
            $title->appendChild($dom->createTextNode(
                $nombre . ': ' . number_format($n, 0, ',', '.') . ' marcha' . ($n === 1 ? '' : 's')
            ));
            $g->insertBefore($title, $g->firstChild);

            if ($n > 0) {
                $a = $dom->createElement('a');
                $a->setAttribute('href', Pages::provinciaHubPath($nombre));
                $svgEl->replaceChild($a, $g);
                $a->appendChild($g);
            }
        }

        if ($puntos !== []) {
            $capa = $dom->createElement('g');
            $capa->setAttribute('class', 'mapa-puntos');
            foreach ($puntos as $p) {
                $a = $dom->createElement('a');
                $a->setAttribute('href', '/marcha?' . http_build_query(['localidad' => $p['localidad']]));
                $c = $dom->createElement('circle');
                $c->setAttribute('class', 'mapa-punto');
                $c->setAttribute('cx', (string) round($p['x'], 2));
                $c->setAttribute('cy', (string) round($p['y'], 2));
                $c->setAttribute('r', (string) round(self::radio($p['n']), 2));
                $title = $dom->createElement('title');
                $title->appendChild($dom->createTextNode(
                    $p['localidad'] . ' (' . $p['provincia'] . '): ' . number_format($p['n'], 0, ',', '.') . ' marcha' . ($p['n'] === 1 ? '' : 's')
                ));
                $c->appendChild($title);
                $a->appendChild($c);
                $capa->appendChild($a);
            }
            $svgEl->appendChild($capa);
        }

        return (string) $dom->saveXML($svgEl);
    }
}
