<?php

declare(strict_types=1);

namespace App;

/**
 * Capa de lectura — port de nextjs/lib/api.ts.
 *
 * Diferencia intencionada: la serialización de autores NO usa json_group_array
 * (SQLite 3.34 de HelioHost puede no traer JSON1). En su lugar se agrupan en PHP
 * con autoresFor(). El resto del SQL es fiel al original para garantizar paridad.
 */
final class Repo
{
    private const AUTOR_LIMIT = 20;

    // ── Helpers ────────────────────────────────────────────────────────────

    private static function buildFtsQuery(string $raw): ?string
    {
        $cleaned = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $raw));
        if ($cleaned === '') {
            return null;
        }
        $tokens = preg_split('/\s+/', $cleaned) ?: [];
        return implode(' ', array_map(static fn(string $t): string => '"' . $t . '"', $tokens));
    }

    /** Muta la fila: FECHA null o '' → 's/f'. Espejo de normalizeFecha(). */
    private static function normalizeFecha(array &$row): void
    {
        if (!array_key_exists('FECHA', $row)) {
            return;
        }
        if ($row['FECHA'] === null || $row['FECHA'] === '') {
            $row['FECHA'] = 's/f';
        }
    }

    /**
     * Autores por marcha, agrupados en PHP (equivale al json_group_array del SQL).
     * La concatenación del nombre se hace EN SQL para replicar la propagación de
     * NULL de SQLite (NOMBRE || ' ' || APELLIDOS con APELLIDOS NULL → NULL).
     *
     * @param  list<int> $marchaIds
     * @return array<int, list<array{autorId:int, nombre:?string}>>
     */
    private static function autoresFor(array $marchaIds): array
    {
        if ($marchaIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($marchaIds), '?'));
        $rows = Db::all(
            "SELECT ma.ID_MARCHA AS mid, a.ID_AUTOR,
                    (a.NOMBRE || ' ' || a.APELLIDOS) AS nombre
             FROM marcha_autor ma
             INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA IN ($placeholders)
             ORDER BY ma.ID_MARCHA, a.APELLIDOS",
            array_values($marchaIds)
        );

        $map = [];
        $seen = [];
        foreach ($rows as $r) {
            $mid = (int) $r['mid'];
            $aid = (int) $r['ID_AUTOR'];
            $key = $mid . '#' . $aid;
            if (isset($seen[$key])) {
                continue; // DISTINCT
            }
            $seen[$key] = true;
            $map[$mid][] = ['autorId' => $aid, 'nombre' => $r['nombre']];
        }
        return $map;
    }

    /** Asigna la lista de autores a cada fila (por ID_MARCHA). */
    private static function attachAutores(array &$rows): void
    {
        $ids = [];
        foreach ($rows as $r) {
            if (isset($r['ID_MARCHA'])) {
                $ids[] = (int) $r['ID_MARCHA'];
            }
        }
        $map = self::autoresFor($ids);
        foreach ($rows as &$r) {
            $mid = isset($r['ID_MARCHA']) ? (int) $r['ID_MARCHA'] : 0;
            $r['AUTOR'] = $map[$mid] ?? [];
        }
        unset($r);
    }

    // ── Marcha ───────────────────────────────────────────────────────────────

    public static function fetchMarcha(string $id): ?array
    {
        $marcha = Db::one(
            "SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.PROVINCIA, m.AUDIO, m.FECHA,
                    m.BANDA_ESTRENO, m.DETALLES_MARCHA,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM marcha m
             LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
             WHERE m.ID_MARCHA = ?
               AND EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)",
            [$id]
        );
        if ($marcha === null) {
            return null;
        }
        self::normalizeFecha($marcha);
        $mid = (int) $marcha['ID_MARCHA'];
        $marcha['AUTOR'] = self::autoresFor([$mid])[$mid] ?? [];

        $discos = Db::all(
            "SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM disco d
             LEFT OUTER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
             LEFT OUTER JOIN banda b ON b.ID_BANDA = d.BANDADISCO
             WHERE dm.IDMARCHA = ? ORDER BY d.FECHA_CD ASC",
            [$id]
        );
        $marcha['discosLength'] = count($discos);
        $marcha['discos'] = $discos;
        return $marcha;
    }

    public static function searchMarchas(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        $conditions = [];
        $values = [];

        $titulo = (string) ($params['titulo'] ?? '');
        $fts = $titulo !== '' ? self::buildFtsQuery($titulo) : null;
        if ($fts !== null) {
            $conditions[] = 'm.ID_MARCHA IN (SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH ?)';
            $values[] = $fts;
        }
        if (!empty($params['fechaDesde'])) { $conditions[] = 'm.FECHA >= ?'; $values[] = $params['fechaDesde']; }
        if (!empty($params['fechaHasta'])) { $conditions[] = 'm.FECHA <= ?'; $values[] = $params['fechaHasta']; }
        if (!empty($params['dedicatoria'])) { $conditions[] = 'm.DEDICATORIA LIKE ?'; $values[] = '%' . $params['dedicatoria'] . '%'; }
        if (!empty($params['localidad'])) { $conditions[] = 'm.LOCALIDAD LIKE ?'; $values[] = '%' . $params['localidad'] . '%'; }
        if (!empty($params['provincia'])) { $conditions[] = 'm.PROVINCIA LIKE ?'; $values[] = '%' . $params['provincia'] . '%'; }

        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';
        $baseWhere = "EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA) AND $where";

        $countRow = Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE $baseWhere", $values);
        $totalRows = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.AUDIO, m.FECHA,
                    CASE WHEN EXISTS (SELECT 1 FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA) THEN 1 ELSE 0 END AS GRABADA
             FROM marcha m
             WHERE $baseWhere
             ORDER BY m.TITULO ASC LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );
        foreach ($rows as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        self::attachAutores($rows);

        return ['rowsReturned' => count($rows), 'totalRows' => $totalRows, 'data' => $rows];
    }

    // ── Autor ────────────────────────────────────────────────────────────────

    public static function fetchAutor(string $id): ?array
    {
        $autor = Db::one('SELECT * FROM autor WHERE ID_AUTOR = ?', [$id]);
        if ($autor === null) {
            return null;
        }
        $marchas = Db::all(
            "SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA
             FROM marcha m
             INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
             WHERE ma.ID_AUTOR = ? ORDER BY m.FECHA ASC",
            [$id]
        );
        foreach ($marchas as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        $autor['marchasLength'] = count($marchas);
        $autor['marchas'] = $marchas;
        return $autor;
    }

    public static function searchAutores(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        $nombre = (string) ($params['nombre'] ?? '');
        $fts = $nombre !== '' ? self::buildFtsQuery($nombre) : null;
        $where = $fts !== null ? 'a.ID_AUTOR IN (SELECT rowid FROM autor_fts WHERE autor_fts MATCH ?)' : '1=1';
        $values = $fts !== null ? [$fts] : [];

        $countRow = Db::one("SELECT COUNT(*) AS n FROM autor a WHERE $where", $values);
        $totalRows = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT a.*, (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO,
                    (SELECT COUNT(ma.ID_MARCHA) FROM marcha_autor ma WHERE ma.ID_AUTOR = a.ID_AUTOR) AS MARCHAS
             FROM autor a WHERE $where ORDER BY a.APELLIDOS ASC LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );
        return ['rowsReturned' => count($rows), 'totalRows' => $totalRows, 'data' => $rows];
    }

    // ── Banda ──────────────────────────────────────────────────────────────

    public static function fetchBanda(string $id): ?array
    {
        $banda = Db::one('SELECT * FROM banda WHERE ID_BANDA = ?', [$id]);
        if ($banda === null) {
            return null;
        }

        $timeline = [[
            'ID_BANDA'    => $banda['ID_BANDA'],
            'FECHA_FUND'  => $banda['FECHA_FUND'],
            'FECHA_EXT'   => $banda['FECHA_EXT'],
            'NOMBRE_BREVE' => $banda['NOMBRE_BREVE'],
        ]];

        $discos = Db::all(
            "SELECT DISTINCT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD,
                    (SELECT COUNT(m.ID_DM) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS PISTAS,
                    (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS
             FROM disco d WHERE d.BANDADISCO = ? ORDER BY d.FECHA_CD ASC",
            [$id]
        );

        $marchas = Db::all(
            "SELECT m.TITULO, m.ID_MARCHA, m.DEDICATORIA, m.LOCALIDAD, m.FECHA
             FROM marcha m
             WHERE m.BANDA_ESTRENO = ?
               AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
             ORDER BY m.FECHA DESC, m.TITULO ASC",
            [$id]
        );
        self::attachAutores($marchas); // fetchBanda solo aplica formatAutor, NO normalizeFecha

        usort($timeline, static fn(array $a, array $b): int => (int) $a['FECHA_FUND'] <=> (int) $b['FECHA_FUND']);

        $banda['timeline'] = $timeline;
        $banda['discosLength'] = count($discos);
        $banda['discos'] = $discos;
        $banda['marchasLength'] = count($marchas);
        $banda['marchas'] = $marchas;
        return $banda;
    }

    public static function searchBandas(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        $conditions = [];
        $values = [];
        if (!empty($params['titulo'])) { $conditions[] = 'b.NOMBRE_COMPLETO LIKE ?'; $values[] = '%' . $params['titulo'] . '%'; }
        if (!empty($params['localidad'])) { $conditions[] = 'b.LOCALIDAD LIKE ?'; $values[] = '%' . $params['localidad'] . '%'; }
        if (!empty($params['provincia'])) { $conditions[] = 'b.PROVINCIA LIKE ?'; $values[] = '%' . $params['provincia'] . '%'; }
        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';

        $countRow = Db::one("SELECT COUNT(*) AS n FROM banda b WHERE $where", $values);
        $totalRows = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT b.ID_BANDA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO, b.PROVINCIA,
                    b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM banda b WHERE $where
             GROUP BY b.ID_BANDA ORDER BY b.NOMBRE_BREVE ASC LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );
        return ['rowsReturned' => count($rows), 'totalRows' => $totalRows, 'data' => $rows];
    }

    // ── Disco ──────────────────────────────────────────────────────────────

    public static function fetchDisco(string $id): ?array
    {
        $disco = Db::one(
            "SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, d.d_DETALLES, b.ID_BANDA,
                    (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM disco d LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
             WHERE d.ID_DISCO = ?",
            [$id]
        );
        if ($disco === null) {
            return null;
        }
        $marchas = Db::all(
            "SELECT dm.N_DISCO, dm.NUMEROMARCHA, m.ID_MARCHA, m.TITULO, m.FECHA,
                    CASE WHEN dm.DM_ENLAZADA IS NULL THEN 0 ELSE 1 END AS ENLAZADA
             FROM disco d
             INNER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
             INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
             WHERE d.ID_DISCO = ?
               AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
             ORDER BY dm.N_DISCO ASC, dm.NUMEROMARCHA ASC, dm.DM_ENLAZADA ASC",
            [$id]
        );
        foreach ($marchas as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        self::attachAutores($marchas);

        $disco['marchasLength'] = count($marchas);
        $disco['marchas'] = $marchas;
        return $disco;
    }

    public static function searchDiscos(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        $nombre = (string) ($params['nombre'] ?? '');
        $where = $nombre !== '' ? 'd.NOMBRE_CD LIKE ?' : '1=1';
        $values = $nombre !== '' ? ['%' . $nombre . '%'] : [];

        $countRow = Db::one("SELECT COUNT(*) AS n FROM disco d WHERE $where", $values);
        $totalRows = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, b.ID_BANDA,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM disco d LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
             WHERE $where ORDER BY d.FECHA_CD ASC LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );
        return ['rowsReturned' => count($rows), 'totalRows' => $totalRows, 'data' => $rows];
    }

    // ── Últimas incorporaciones ──────────────────────────────────────────────

    public static function fetchUltimas(): array
    {
        $rows = Db::all(
            "SELECT m.ID_MARCHA, m.TITULO, m.FECHA
             FROM marcha m
             WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)
             ORDER BY m.ID_MARCHA DESC LIMIT 5"
        );
        foreach ($rows as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        self::attachAutores($rows);
        return $rows;
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    public static function fetchEstado(): array
    {
        return Db::counts();
    }

    public static function fetchMasAutor(): array
    {
        return Db::all(
            "SELECT a.ID_AUTOR, COUNT(m.ID_MARCHA) AS MARCHAS,
                    (a.NOMBRE || ' ' || a.APELLIDOS) AS AUTOR
             FROM autor a
             INNER JOIN marcha_autor am ON am.ID_AUTOR = a.ID_AUTOR
             INNER JOIN marcha m ON m.ID_MARCHA = am.ID_MARCHA
             GROUP BY a.ID_AUTOR, a.NOMBRE, a.APELLIDOS
             ORDER BY MARCHAS DESC LIMIT 10"
        );
    }

    public static function fetchMasDedica(): array
    {
        return Db::all(
            "SELECT COUNT(DEDICATORIA) AS CUENTA,
                    (DEDICATORIA || ' (' || LOCALIDAD || ')') AS LUGAR
             FROM marcha WHERE DEDICATORIA LIKE '%Hdad%' GROUP BY LUGAR
             HAVING CUENTA >= 15 ORDER BY CUENTA DESC"
        );
    }

    public static function fetchMasEstreno(): array
    {
        return Db::all(
            "SELECT b.ID_BANDA, COUNT(m.ID_MARCHA) AS MARCHAS,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM marcha m INNER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
             WHERE b.ID_BANDA != 0
             GROUP BY b.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
             ORDER BY MARCHAS DESC LIMIT 20"
        );
    }

    public static function fetchMasGrabada(): array
    {
        $rows = Db::all(
            "SELECT COUNT(dm.IDMARCHA) AS GRABACIONES, m.ID_MARCHA, m.TITULO
             FROM disco_marcha dm INNER JOIN marcha m ON m.ID_MARCHA = dm.IDMARCHA
             WHERE EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)
             GROUP BY dm.IDMARCHA, m.ID_MARCHA, m.TITULO
             ORDER BY GRABACIONES DESC LIMIT 20"
        );
        self::attachAutores($rows);
        return $rows;
    }
}
