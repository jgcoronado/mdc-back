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
     * @param list<array{K:string,N:int}> $porProvincia  Repo::hubProvincias()
     * @return string  Markup <svg>…</svg> listo para imprimir sin escapar
     *                 (se construye aquí, no viene de entrada de usuario).
     */
    public static function render(array $porProvincia): string
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

        return (string) $dom->saveXML($svgEl);
    }
}
