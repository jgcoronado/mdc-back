<?php

declare(strict_types=1);

namespace App;

/**
 * Componentes de presentación reutilizables (ports de los componentes React:
 * Pagination, CdList, Timeline, CoverImage). Devuelven HTML como string.
 */
final class Html
{
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    // ── CoverImage ───────────────────────────────────────────────────────────
    public static function cover(string $src, string $alt, string $class = ''): string
    {
        // JS mínimo: oculta la imagen si no existe y desactiva el menú contextual.
        return '<img class="' . self::e($class) . '" src="' . self::e($src) . '" alt="' . self::e($alt)
            . '" oncontextmenu="return false" onerror="this.style.display=\'none\'">';
    }

    // ── Pagination ───────────────────────────────────────────────────────────
    public static function pagination(int $currentPage, int $totalRows, int $limit, string $basePath, array $criteria): string
    {
        $totalPages = (int) ceil($totalRows / $limit);
        if ($totalPages <= 1) return '';

        $url = static function (int $page) use ($basePath, $criteria, $limit): string {
            $params = array_merge($criteria, ['page' => (string) $page, 'limit' => (string) $limit]);
            return $basePath . '?' . http_build_query($params);
        };

        $out = '<nav class="pagination">';
        if ($currentPage > 1) {
            $out .= '<a class="btn btn-sm btn-ghost" href="' . self::e($url($currentPage - 1)) . '">‹ Anterior</a>';
        }
        foreach (self::pageList($currentPage, $totalPages) as $p) {
            if ($p === '…') {
                $out .= '<span class="ellipsis">…</span>';
            } else {
                $cls = $p === $currentPage ? 'btn btn-sm btn-neutral' : 'btn btn-sm btn-ghost';
                $out .= '<a class="' . $cls . '" href="' . self::e($url((int) $p)) . '">' . $p . '</a>';
            }
        }
        if ($currentPage < $totalPages) {
            $out .= '<a class="btn btn-sm btn-ghost" href="' . self::e($url($currentPage + 1)) . '">Siguiente ›</a>';
        }
        return $out . '</nav>';
    }

    /** @return list<int|string> */
    private static function pageList(int $current, int $total): array
    {
        if ($total <= 7) return range(1, $total);
        $pages = [1];
        if ($current > 3) $pages[] = '…';
        for ($p = max(2, $current - 1); $p <= min($total - 1, $current + 1); $p++) $pages[] = $p;
        if ($current < $total - 2) $pages[] = '…';
        $pages[] = $total;
        return $pages;
    }

    // ── CdList ───────────────────────────────────────────────────────────────
    public static function cdList(array $disco): string
    {
        $coverSrc = '/cover/' . $disco['ID_DISCO'] . '.png';
        $discoPath = Slug::buildDetailPath('disco', $disco['ID_DISCO'], (string) $disco['NOMBRE_CD']);
        $hasBanda = !empty($disco['ID_BANDA']) && !empty($disco['BANDA']);
        $bandaPath = $hasBanda ? Slug::buildDetailPath('banda', $disco['ID_BANDA'], (string) $disco['BANDA']) : null;

        if ($hasBanda) {
            $sub = '<a class="link" href="' . self::e($bandaPath) . '">' . self::e($disco['BANDA']) . '</a>';
        } elseif (!empty($disco['DISCOS']) && (int) $disco['DISCOS'] > 1) {
            $sub = self::e($disco['DISCOS']) . ' CDs, ' . self::e($disco['PISTAS'] ?? '') . ' marchas';
        } else {
            $sub = self::e($disco['PISTAS'] ?? '') . ' marchas';
        }

        return '<ul class="cdlist">'
            . '<li class="cdlist-row">'
            . '<div class="cdlist-cover"><a href="' . self::e($discoPath) . '">'
            . self::cover($coverSrc, "Portada del disco '" . $disco['NOMBRE_CD'] . "'", 'cover-thumb') . '</a></div>'
            . '<div class="cdlist-main"><a class="cdlist-title link" href="' . self::e($discoPath) . '">'
            . self::e($disco['NOMBRE_CD']) . '</a>'
            . '<div class="cdlist-sub">' . $sub . '</div></div>'
            . '<div class="cdlist-date">' . self::e($disco['FECHA_CD']) . '</div>'
            . '</li></ul>';
    }

    // ── Timeline (banda) ─────────────────────────────────────────────────────
    public static function timeline(array $banda): string
    {
        $fund = (int) ($banda['FECHA_FUND'] ?? 0);
        $ext = $banda['FECHA_EXT'] ?? null;
        $path = Slug::buildDetailPath('banda', $banda['ID_BANDA'], (string) $banda['NOMBRE_BREVE']);
        $endLabel = ($ext !== null && (int) $ext !== 0) ? self::e($ext) : 'Hoy';

        $out = '<ul class="timeline">';
        $out .= '<li><span class="tl-date">' . ($fund > 1800 ? $fund : 's/f') . '</span>'
            . '<span class="tl-dot"></span>'
            . '<span class="tl-box"><a class="link" href="' . self::e($path) . '">' . self::e($banda['NOMBRE_BREVE']) . '</a></span></li>';
        $out .= '<li><span class="tl-date">' . $endLabel . '</span>'
            . '<span class="tl-dot"></span>'
            . (($ext !== null && (int) $ext !== 0) ? '<span class="tl-box">Desaparece la banda</span>' : '') . '</li>';
        return $out . '</ul>';
    }
}
