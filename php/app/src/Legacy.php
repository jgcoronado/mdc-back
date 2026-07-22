<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Puente de indexación tras la migración desde el sitio MySQL original.
 *
 * Google todavía tiene indexadas las URLs de aquel sitio con el formato
 * «/{slug}-{entidad}-{id}.html» (con .html, y llegando por www/http). Tras el
 * cutover esas URLs devuelven 404, así que Google va soltando TODO el histórico
 * y el dominio desaparece de las búsquedas. Aquí se traducen a la URL canónica
 * nueva («/{page}/{slug}-{id}») para emitir un 301 permanente que preserva la
 * autoridad acumulada.
 *
 * Los IDs numéricos se conservaron en la migración (id 730 = misma marcha), así
 * que basta el id para reconstruir la ficha; el slug de la URL vieja se ignora
 * y se regenera desde la BD para que el destino sea la canónica EXACTA (un solo
 * salto, sin 301→308 encadenado).
 *
 * Se invoca SOLO desde el fallback de notFound (ver routes.php): las rutas
 * válidas no pasan por aquí, coste cero. Para cubrir otros patrones heredados
 * (listados, paginación, etc.) que revele el informe de Search Console, añadir
 * más reglas en resolve().
 */
final class Legacy
{
    /**
     * entidad heredada → [page canónica, tabla, columna id, expresión etiqueta].
     * La etiqueta se elige igual que en el sitemap y en la ficha de detalle para
     * que el slug regenerado coincida con la canónica y el 301 caiga en un 200.
     */
    private const ENTIDADES = [
        'marcha' => ['marcha', 'marcha', 'ID_MARCHA', 'TITULO'],
        'autor'  => ['autor',  'autor',  'ID_AUTOR',  "NOMBRE || ' ' || APELLIDOS"],
        'banda'  => ['banda',  'banda',  'ID_BANDA',  'NOMBRE_COMPLETO'],
        'disco'  => ['disco',  'disco',  'ID_DISCO',  'NOMBRE_CD'],
    ];

    /**
     * Ruta canónica nueva (sin host) a la que redirigir, o null si $path no es
     * una URL heredada reconocible o si el registro ya no existe (→ 404).
     */
    public static function resolve(string $path): ?string
    {
        // Ficha de detalle: «/{slug}-{entidad}-{id}.html» (un solo segmento).
        // El .+ inicial se queda con el slug; entidad+id se anclan al final, así
        // que un slug que contenga «-banda-» no confunde el match (gana la última
        // ocurrencia). El .html + id numérico hacen imposible chocar con una ruta
        // nueva (ninguna lleva .html) — y de todos modos esto solo corre en 404.
        if (preg_match('#^/.+-(marcha|autor|banda|disco)-(\d+)\.html$#', $path, $m) !== 1) {
            return null;
        }

        [$page, $tabla, $colId, $exprLabel] = self::ENTIDADES[$m[1]];
        $id = $m[2];

        try {
            // $tabla/$colId/$exprLabel salen de la allowlist constante (no del
            // usuario); el id va como parámetro ligado.
            $row = Db::one("SELECT {$exprLabel} AS label FROM {$tabla} WHERE {$colId} = ?", [$id]);
        } catch (Throwable) {
            return null; // sin BD o error → mejor 404 que 500
        }
        if ($row === null) {
            return null; // id que ya no existe → cae a 404
        }

        return Slug::buildDetailPath($page, $id, (string) $row['label']);
    }
}
