<?php

declare(strict_types=1);

namespace App;

/**
 * Similitud de texto 0..1 (1 = idéntico). Mismo criterio que
 * tools/ingest/dedup.mjs (normaliza minúsculas/sin acentos/sin signos de
 * apertura/espacios colapsados y compara por distancia de Levenshtein), para
 * que el umbral "80%"/"90%" signifique lo mismo en el pipeline offline y en
 * el panel PHP.
 */
final class Similarity
{
    private static function normalize(string $s): string
    {
        $s = Db::noAcc($s);
        $s = preg_replace('/[¡!¿?"\'«»“”‘’]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    public static function ratio(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') return 0.0;
        if ($a === $b) return 1.0;
        $maxLen = max(strlen($a), strlen($b));
        return 1 - levenshtein($a, $b) / $maxLen;
    }
}
