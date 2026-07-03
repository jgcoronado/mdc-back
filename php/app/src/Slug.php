<?php

declare(strict_types=1);

namespace App;

use Normalizer;

/**
 * Puerto exacto de nextjs/lib/slugify.ts — las URLs deben salir idénticas a las
 * actuales para no perder indexación tras el cutover.
 */
final class Slug
{
    public static function slugify(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // NFD + eliminar marcas diacríticas combinantes (equivale a ̀-ͯ en JS).
        $s = Normalizer::normalize($value, Normalizer::FORM_D);
        if ($s === false) {
            $s = $value;
        }
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
        $s = trim($s, '-');
        $s = preg_replace('/-{2,}/', '-', $s) ?? $s;

        return $s;
    }

    public static function buildDetailPath(string $page, string|int $id, string $label): string
    {
        $safeId = trim((string) $id);
        if ($safeId === '') {
            return "/{$page}";
        }
        $slug = self::slugify($label);
        return $slug !== '' ? "/{$page}/{$slug}-{$safeId}" : "/{$page}/{$safeId}";
    }

    public static function extractId(string $slugAndId): ?string
    {
        return preg_match('/(\d+)$/', $slugAndId, $m) ? $m[1] : null;
    }
}
