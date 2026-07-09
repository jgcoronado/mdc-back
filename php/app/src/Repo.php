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
                    m.BANDA_ESTRENO, m.DETALLES_MARCHA, m.TIPO, m.DURACION_SEG,
                    b.NOMBRE_BREVE AS BANDA_NOMBRE, b.LOCALIDAD AS BANDA_LOC,
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

        // Autores en forma de autoridad ("Apellidos, Nombre") con recuento de obra,
        // para el asiento y la descripción de la ficha de catálogo.
        $marcha['AUTORES_FICHA'] = Db::all(
            "SELECT a.ID_AUTOR, a.NOMBRE, a.APELLIDOS, a.F_NAC, a.F_DEF,
                    (SELECT COUNT(*) FROM marcha_autor x WHERE x.ID_AUTOR = a.ID_AUTOR) AS N_MARCHAS
             FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = ? ORDER BY a.APELLIDOS",
            [$id]
        );

        $marcha['BANDA_ESTRENOS'] = 0;
        if (!empty($marcha['BANDA_ESTRENO'])) {
            $row = Db::one('SELECT COUNT(*) AS n FROM marcha WHERE BANDA_ESTRENO = ?', [$marcha['BANDA_ESTRENO']]);
            $marcha['BANDA_ESTRENOS'] = (int) ($row['n'] ?? 0);
        }

        // Grabaciones: una fila por aparición en disco, con pista y banda intérprete
        // (la del vínculo dm.DM_BANDA si existe; si no, la banda propietaria del disco).
        $discos = Db::all(
            "SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, dm.NUMEROMARCHA,
                    COALESCE(bi.ID_BANDA, b.ID_BANDA) AS ID_BANDA,
                    COALESCE(bi.NOMBRE_BREVE, b.NOMBRE_BREVE) AS BANDA_BREVE,
                    (COALESCE(bi.NOMBRE_BREVE, b.NOMBRE_BREVE) || ' (' || COALESCE(bi.LOCALIDAD, b.LOCALIDAD) || ')') AS BANDA
             FROM disco d
             INNER JOIN disco_marcha dm ON dm.ID_DISCO = d.ID_DISCO
             LEFT OUTER JOIN banda b  ON b.ID_BANDA  = d.BANDADISCO
             LEFT OUTER JOIN banda bi ON bi.ID_BANDA = dm.DM_BANDA
             WHERE dm.IDMARCHA = ? ORDER BY CAST(d.FECHA_CD AS REAL) ASC, d.NOMBRE_CD ASC",
            [$id]
        );
        $marcha['discosLength'] = count($discos);
        $marcha['discos'] = $discos;

        $primera = null;
        foreach ($discos as $d) {
            $y = (int) (float) ($d['FECHA_CD'] ?? 0);
            if ($y > 1800 && ($primera === null || $y < $primera)) $primera = $y;
        }
        $marcha['PRIMERA_GRABACION'] = $primera;

        // Posición en el catálogo y registros vecinos (‹ M-x · n de N · M-y ›).
        $valid = 'EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)';
        $marcha['REG_TOTAL'] = (int) (Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE $valid")['n'] ?? 0);
        $marcha['REG_POS'] = (int) (Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE m.ID_MARCHA <= ? AND $valid", [$id])['n'] ?? 0);
        $marcha['REG_PREV'] = Db::one("SELECT m.ID_MARCHA, m.TITULO FROM marcha m WHERE m.ID_MARCHA < ? AND $valid ORDER BY m.ID_MARCHA DESC LIMIT 1", [$id]);
        $marcha['REG_NEXT'] = Db::one("SELECT m.ID_MARCHA, m.TITULO FROM marcha m WHERE m.ID_MARCHA > ? AND $valid ORDER BY m.ID_MARCHA ASC LIMIT 1", [$id]);

        // Recuentos para las remisiones «véase también».
        $marcha['N_MISMO_ANIO'] = 0;
        if (!empty($marcha['FECHA'])) {
            $marcha['N_MISMO_ANIO'] = (int) (Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE m.FECHA = ? AND $valid", [$marcha['FECHA']])['n'] ?? 0);
        }
        $marcha['N_MISMA_PROV'] = 0;
        if (!empty($marcha['PROVINCIA'])) {
            $marcha['N_MISMA_PROV'] = (int) (Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE m.PROVINCIA = ? AND $valid", [$marcha['PROVINCIA']])['n'] ?? 0);
        }
        return $marcha;
    }

    /**
     * WHERE + values de la búsqueda de marchas. $exclude omite un criterio
     * (para calcular facetas sin su propio filtro; 'fecha' excluye desde+hasta).
     * @return array{0:string,1:list<mixed>}
     */
    private static function marchaWhere(array $params, ?string $exclude = null): array
    {
        $conditions = [];
        $values = [];
        $on = static fn(string $k): bool => $k !== $exclude && !empty($params[$k]);

        $titulo = $exclude !== 'titulo' ? (string) ($params['titulo'] ?? '') : '';
        $fts = $titulo !== '' ? self::buildFtsQuery($titulo) : null;
        if ($fts !== null) {
            $conditions[] = 'm.ID_MARCHA IN (SELECT rowid FROM marcha_fts WHERE marcha_fts MATCH ?)';
            $values[] = $fts;
        }
        if ($exclude !== 'fecha' && !empty($params['fechaDesde'])) { $conditions[] = 'm.FECHA >= ?'; $values[] = $params['fechaDesde']; }
        if ($exclude !== 'fecha' && !empty($params['fechaHasta'])) { $conditions[] = 'm.FECHA <= ?'; $values[] = $params['fechaHasta']; }
        if ($on('dedicatoria')) { $conditions[] = 'NOACC(m.DEDICATORIA) LIKE ?'; $values[] = '%' . Db::noAcc($params['dedicatoria']) . '%'; }
        if ($on('localidad')) { $conditions[] = 'NOACC(m.LOCALIDAD) LIKE ?'; $values[] = '%' . Db::noAcc($params['localidad']) . '%'; }
        if ($on('provincia')) { $conditions[] = 'NOACC(m.PROVINCIA) LIKE ?'; $values[] = '%' . Db::noAcc($params['provincia']) . '%'; }
        if ($on('tipo')) { $conditions[] = 'm.TIPO = ?'; $values[] = $params['tipo']; }

        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';
        return ["EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA) AND $where", $values];
    }

    public static function searchMarchas(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        [$baseWhere, $values] = self::marchaWhere($params);

        $orderBy = match ((string) ($params['orden'] ?? '')) {
            'grabaciones' => 'N_GRAB DESC, m.TITULO ASC',
            'fecha' => 'm.FECHA DESC, m.TITULO ASC',
            default => 'm.TITULO ASC',
        };

        $countRow = Db::one("SELECT COUNT(*) AS n FROM marcha m WHERE $baseWhere", $values);
        $totalRows = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT m.ID_MARCHA, m.TITULO, m.DEDICATORIA, m.LOCALIDAD, m.PROVINCIA, m.AUDIO, m.FECHA,
                    m.BANDA_ESTRENO, b.NOMBRE_BREVE AS BANDA_BREVE,
                    (SELECT COUNT(*) FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA) AS N_GRAB
             FROM marcha m
             LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
             WHERE $baseWhere
             ORDER BY $orderBy LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );
        foreach ($rows as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        self::attachAutores($rows);

        return ['rowsReturned' => count($rows), 'totalRows' => $totalRows, 'data' => $rows];
    }

    /**
     * Facetas del explorador de marchas: tipo, provincia y década, cada una
     * contada sobre el resultado filtrado sin su propio criterio.
     * @return array{tipo:list<array>,provincia:list<array>,decada:list<array>}
     */
    public static function marchaFacets(string $query): array
    {
        parse_str($query, $params);

        [$w, $v] = self::marchaWhere($params, 'tipo');
        $tipo = Db::all("SELECT m.TIPO AS K, COUNT(*) AS N FROM marcha m
                         WHERE $w AND m.TIPO IS NOT NULL AND m.TIPO != ''
                         GROUP BY m.TIPO ORDER BY N DESC LIMIT 6", $v);

        [$w, $v] = self::marchaWhere($params, 'provincia');
        $prov = Db::all("SELECT m.PROVINCIA AS K, COUNT(*) AS N FROM marcha m
                         WHERE $w AND m.PROVINCIA IS NOT NULL AND m.PROVINCIA != ''
                         GROUP BY m.PROVINCIA ORDER BY N DESC LIMIT 8", $v);

        [$w, $v] = self::marchaWhere($params, 'fecha');
        $dec = Db::all("SELECT (m.FECHA / 10) * 10 AS K, COUNT(*) AS N FROM marcha m
                        WHERE $w AND m.FECHA > 1900
                        GROUP BY K ORDER BY K DESC LIMIT 8", $v);

        return ['tipo' => $tipo, 'provincia' => $prov, 'decada' => $dec];
    }

    // ── Admin: cargadores en crudo (para formularios de edición) ─────────────

    /** Fila cruda de marcha (sin normalizar FECHA, sin filtrar por autores). */
    public static function fetchMarchaRaw(string $id): ?array
    {
        return Db::one('SELECT * FROM marcha WHERE ID_MARCHA = ?', [$id]);
    }

    /** Autores actuales de una marcha: [{ID_AUTOR, NOMBRE_COMPLETO}]. */
    public static function currentAutoresForMarcha(string $id): array
    {
        return Db::all(
            "SELECT a.ID_AUTOR, (a.NOMBRE || ' ' || a.APELLIDOS) AS NOMBRE_COMPLETO
             FROM marcha_autor ma INNER JOIN autor a ON a.ID_AUTOR = ma.ID_AUTOR
             WHERE ma.ID_MARCHA = ? ORDER BY a.APELLIDOS",
            [$id]
        );
    }

    /** Fila cruda de autor (para el formulario de edición). */
    public static function fetchAutorRaw(string $id): ?array
    {
        return Db::one('SELECT * FROM autor WHERE ID_AUTOR = ?', [$id]);
    }

    /** @param list<int> $ids  @return list<array{ID_AUTOR:int,NOMBRE_COMPLETO:string}> */
    public static function autoresByIds(array $ids): array
    {
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return Db::all(
            "SELECT ID_AUTOR, (NOMBRE || ' ' || APELLIDOS) AS NOMBRE_COMPLETO
             FROM autor WHERE ID_AUTOR IN ($ph) ORDER BY APELLIDOS",
            array_values($ids)
        );
    }

    /**
     * Predictivo de dedicatorias (panel de ingesta): combinaciones distintas de
     * DEDICATORIA/LOCALIDAD/PROVINCIA ya usadas en marchas existentes, para no
     * reescribir a mano hermandades que ya están en la BD. Ordenado por
     * frecuencia (la combinación más repetida primero).
     *
     * @return list<array{DEDICATORIA:string,LOCALIDAD:?string,PROVINCIA:?string,N:int}>
     */
    public static function searchDedicatorias(string $q, int $limit = 10): array
    {
        if (mb_strlen($q) < 7) return [];
        return Db::all(
            "SELECT DEDICATORIA, LOCALIDAD, PROVINCIA, COUNT(*) AS N
             FROM marcha
             WHERE DEDICATORIA IS NOT NULL AND DEDICATORIA <> '' AND DEDICATORIA LIKE ?
             GROUP BY DEDICATORIA, LOCALIDAD, PROVINCIA
             ORDER BY N DESC, DEDICATORIA ASC
             LIMIT ?",
            ['%' . $q . '%', $limit]
        );
    }

    // ── Autor ────────────────────────────────────────────────────────────────

    public static function fetchAutor(string $id): ?array
    {
        $autor = Db::one('SELECT * FROM autor WHERE ID_AUTOR = ?', [$id]);
        if ($autor === null) {
            return null;
        }
        $marchas = Db::all(
            "SELECT m.ID_MARCHA, m.TITULO, m.FECHA, m.DEDICATORIA, m.PROVINCIA,
                    m.BANDA_ESTRENO, b.NOMBRE_BREVE AS BANDA_BREVE,
                    (SELECT COUNT(*) FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA) AS N_GRAB
             FROM marcha m
             INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
             LEFT OUTER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
             WHERE ma.ID_AUTOR = ? ORDER BY m.FECHA ASC",
            [$id]
        );
        foreach ($marchas as &$r) {
            self::normalizeFecha($r);
        }
        unset($r);
        $autor['marchasLength'] = count($marchas);
        $autor['marchas'] = $marchas;

        // Ficha de catálogo: grabaciones totales, periodo de actividad,
        // banda con más estrenos de su obra y posición del registro.
        $autor['N_GRAB_TOTAL'] = array_sum(array_map(static fn(array $x): int => (int) $x['N_GRAB'], $marchas));
        $years = array_values(array_filter(array_map(static fn(array $x): int => (int) $x['FECHA'], $marchas), static fn(int $y): bool => $y > 1000));
        $autor['ACT_DESDE'] = $years !== [] ? min($years) : 0;
        $autor['ACT_HASTA'] = $years !== [] ? max($years) : 0;
        $autor['BANDA_PPAL'] = Db::one(
            "SELECT b.ID_BANDA, b.NOMBRE_BREVE, COUNT(*) AS N
             FROM marcha m
             INNER JOIN banda b ON b.ID_BANDA = m.BANDA_ESTRENO
             INNER JOIN marcha_autor ma ON ma.ID_MARCHA = m.ID_MARCHA
             WHERE ma.ID_AUTOR = ? GROUP BY b.ID_BANDA ORDER BY N DESC LIMIT 1",
            [$id]
        );
        $autor['REG_TOTAL'] = (int) (Db::one('SELECT COUNT(*) AS n FROM autor')['n'] ?? 0);
        $autor['REG_POS'] = (int) (Db::one('SELECT COUNT(*) AS n FROM autor WHERE ID_AUTOR <= ?', [$id])['n'] ?? 0);
        return $autor;
    }

    /**
     * Autores cuyo NOMBRE, APELLIDOS o NOMBRE_ART contienen (por subcadena,
     * insensible a mayúsculas/acentos) cada palabra de $q — igual criterio
     * que searchDedicatorias/searchBandas, en vez de FTS5 (que solo hace
     * match de palabra completa y por eso el predictivo de autor iba peor
     * que el de dedicatoria). Pensado para autocompletar desde 3 caracteres.
     *
     * @return list<array{ID_AUTOR:int,NOMBRE_COMPLETO:string}>
     */
    public static function autorCandidatosPorTexto(string $q, int $limit = 15): array
    {
        $tokens = preg_split('/\s+/u', trim(Db::noAcc($q)), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === []) return [];

        $conditions = [];
        $values = [];
        foreach ($tokens as $t) {
            $conditions[] = '(NOACC(NOMBRE) LIKE ? OR NOACC(APELLIDOS) LIKE ? OR NOACC(NOMBRE_ART) LIKE ?)';
            $needle = '%' . $t . '%';
            array_push($values, $needle, $needle, $needle);
        }
        $where = implode(' AND ', $conditions);

        return Db::all(
            "SELECT ID_AUTOR, (NOMBRE || ' ' || APELLIDOS) AS NOMBRE_COMPLETO
             FROM autor WHERE $where
             ORDER BY APELLIDOS ASC LIMIT ?",
            [...$values, $limit]
        );
    }

    /**
     * Mejor autor existente para un nombre de texto libre (p.ej. extraído de
     * un título de YouTube), comparado por similitud de texto. Devuelve null
     * si no hay ningún candidato con solapamiento léxico.
     *
     * @return array{ID_AUTOR:int,NOMBRE_COMPLETO:string,score:float}|null
     */
    public static function mejorAutorPorNombre(string $nombre): ?array
    {
        $rows = self::autorCandidatosPorTexto($nombre, 50);

        $mejor = null;
        foreach ($rows as $r) {
            $score = Similarity::ratio($nombre, (string) $r['NOMBRE_COMPLETO']);
            if ($mejor === null || $score > $mejor['score']) {
                $mejor = ['ID_AUTOR' => (int) $r['ID_AUTOR'], 'NOMBRE_COMPLETO' => (string) $r['NOMBRE_COMPLETO'], 'score' => $score];
            }
        }
        return $mejor;
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
            "SELECT m.TITULO, m.ID_MARCHA, m.DEDICATORIA, m.LOCALIDAD, m.FECHA,
                    (SELECT COUNT(*) FROM disco_marcha dm WHERE dm.IDMARCHA = m.ID_MARCHA) AS N_GRAB
             FROM marcha m
             WHERE m.BANDA_ESTRENO = ?
               AND EXISTS (SELECT 1 FROM marcha_autor am WHERE am.ID_MARCHA = m.ID_MARCHA)
             ORDER BY m.FECHA DESC, m.TITULO ASC",
            [$id]
        );
        self::attachAutores($marchas); // fetchBanda solo aplica formatAutor, NO normalizeFecha

        usort($timeline, static fn(array $a, array $b): int => (int) $a['FECHA_FUND'] <=> (int) $b['FECHA_FUND']);

        $banda['timeline'] = $timeline;
        $banda['linaje'] = self::bandaLinaje($id);
        $banda['discosLength'] = count($discos);
        $banda['discos'] = $discos;
        $banda['marchasLength'] = count($marchas);
        $banda['marchas'] = $marchas;

        // Estrenos por formación (línea de sucesión) y posición del registro.
        $ids = [(int) $banda['ID_BANDA']];
        if ($banda['linaje'] !== null) {
            foreach (array_merge($banda['linaje']['up'], $banda['linaje']['down']) as $lvl) {
                foreach ($lvl as $n) $ids[] = (int) $n['ID'];
            }
            foreach (array_merge($banda['linaje']['juveniles'], $banda['linaje']['madres']) as $n) {
                $ids[] = (int) $n['ID_BANDA'];
            }
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $map = [];
        foreach (Db::all("SELECT BANDA_ESTRENO AS B, COUNT(*) AS N FROM marcha WHERE BANDA_ESTRENO IN ($ph) GROUP BY BANDA_ESTRENO", $ids) as $r) {
            $map[(int) $r['B']] = (int) $r['N'];
        }
        $banda['ESTRENOS_MAP'] = $map;
        $banda['REG_TOTAL'] = (int) (Db::one('SELECT COUNT(*) AS n FROM banda')['n'] ?? 0);
        $banda['REG_POS'] = (int) (Db::one('SELECT COUNT(*) AS n FROM banda WHERE ID_BANDA <= ?', [$id])['n'] ?? 0);
        return $banda;
    }

    public static function searchBandas(string $query, int $page = 1, int $limit = 20): array
    {
        parse_str($query, $params);
        $conditions = [];
        $values = [];
        if (!empty($params['titulo'])) { $conditions[] = 'NOACC(b.NOMBRE_COMPLETO) LIKE ?'; $values[] = '%' . Db::noAcc($params['titulo']) . '%'; }
        if (!empty($params['localidad'])) { $conditions[] = 'NOACC(b.LOCALIDAD) LIKE ?'; $values[] = '%' . Db::noAcc($params['localidad']) . '%'; }
        if (!empty($params['provincia'])) { $conditions[] = 'NOACC(b.PROVINCIA) LIKE ?'; $values[] = '%' . Db::noAcc($params['provincia']) . '%'; }
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

    /** Fila cruda de banda (para el panel de relaciones). */
    public static function fetchBandaRaw(string $id): ?array
    {
        return Db::one('SELECT * FROM banda WHERE ID_BANDA = ?', [$id]);
    }

    /**
     * Bandas cuyo NOMBRE_BREVE, NOMBRE_COMPLETO o LOCALIDAD contienen (subcadena,
     * insensible a mayúsculas/acentos) cada palabra de $q. Para el autocompletar
     * del selector "otra banda" en las relaciones de linaje.
     *
     * @return list<array{ID_BANDA:int,NOMBRE_BREVE:string,LOCALIDAD:?string,LABEL:string}>
     */
    public static function bandaCandidatosPorTexto(string $q, int $limit = 15): array
    {
        $tokens = preg_split('/\s+/u', trim(Db::noAcc($q)), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === []) return [];

        $conditions = [];
        $values = [];
        foreach ($tokens as $t) {
            $conditions[] = '(NOACC(NOMBRE_BREVE) LIKE ? OR NOACC(NOMBRE_COMPLETO) LIKE ? OR NOACC(LOCALIDAD) LIKE ?)';
            $needle = '%' . $t . '%';
            array_push($values, $needle, $needle, $needle);
        }
        $where = implode(' AND ', $conditions);

        return Db::all(
            "SELECT ID_BANDA, NOMBRE_BREVE, LOCALIDAD,
                    (NOMBRE_BREVE || CASE WHEN LOCALIDAD IS NOT NULL AND LOCALIDAD <> ''
                                          THEN ' (' || LOCALIDAD || ')' ELSE '' END) AS LABEL
             FROM banda WHERE $where
             ORDER BY NOMBRE_BREVE ASC LIMIT ?",
            [...$values, $limit]
        );
    }

    /**
     * Relaciones de linaje en las que participa una banda (como origen o destino),
     * con el nombre de las dos puntas resuelto. Ordenadas por tipo y fecha.
     *
     * @return list<array<string,mixed>>
     */
    public static function bandaRelaciones(string $id): array
    {
        return Db::all(
            "SELECT r.ID_RELACION, r.TIPO, r.FECHA_INICIO, r.FECHA_FIN, r.NOTA,
                    r.ID_ORIGEN, r.ID_DESTINO,
                    bo.NOMBRE_BREVE AS ORIGEN_NOMBRE, bo.LOCALIDAD AS ORIGEN_LOC,
                    bd.NOMBRE_BREVE AS DESTINO_NOMBRE, bd.LOCALIDAD AS DESTINO_LOC
             FROM banda_relacion r
             LEFT JOIN banda bo ON bo.ID_BANDA = r.ID_ORIGEN
             LEFT JOIN banda bd ON bd.ID_BANDA = r.ID_DESTINO
             WHERE r.ID_ORIGEN = ? OR r.ID_DESTINO = ?
             ORDER BY r.TIPO ASC, r.FECHA_INICIO ASC",
            [$id, $id]
        );
    }

    /**
     * Linaje de una banda para la ficha pública. Recorre `banda_relacion` por
     * niveles (BFS) hacia atrás (predecesoras) y hacia delante (sucesoras), más
     * las juveniles (lateral) y la madre si la banda es juvenil. Cada nodo lleva
     * el TIPO de la arista que lo conecta hacia el lado del foco (para el chip).
     * De-duplica por banda y corta a 5 niveles y en ciclos.
     *
     * @return array{focus:array,up:list<list<array>>,down:list<list<array>>,juveniles:list<array>,madres:list<array>}|null
     */
    public static function bandaLinaje(string $id): ?array
    {
        $focus = Db::one(
            'SELECT ID_BANDA, NOMBRE_BREVE, LOCALIDAD, FECHA_FUND, FECHA_EXT FROM banda WHERE ID_BANDA = ?',
            [$id]
        );
        if ($focus === null) return null;
        $fid = (int) $focus['ID_BANDA'];

        $bfs = static function (string $dir) use ($fid): array {
            // 'up' = predecesoras (aristas cuyo DESTINO es el nodo → tomamos ORIGEN).
            $anchor = $dir === 'up' ? 'ID_DESTINO' : 'ID_ORIGEN';
            $take   = $dir === 'up' ? 'ID_ORIGEN' : 'ID_DESTINO';
            $levels = [];
            $frontier = [$fid];
            $visited = [$fid => true];
            $depth = 0;
            while ($frontier !== [] && $depth < 5) {
                $ph = implode(',', array_fill(0, count($frontier), '?'));
                $rows = Db::all(
                    "SELECT r.TIPO, r.$take AS BID, b.NOMBRE_BREVE, b.LOCALIDAD, b.FECHA_FUND, b.FECHA_EXT
                     FROM banda_relacion r JOIN banda b ON b.ID_BANDA = r.$take
                     WHERE r.$anchor IN ($ph) AND r.TIPO IN ('renombrado','fusion','division')
                     ORDER BY b.FECHA_FUND ASC, b.NOMBRE_BREVE ASC",
                    $frontier
                );
                $nodes = [];
                $next = [];
                foreach ($rows as $r) {
                    $bid = (int) $r['BID'];
                    if (isset($visited[$bid])) continue;
                    $visited[$bid] = true;
                    $nodes[] = [
                        'ID' => $bid, 'NOMBRE' => $r['NOMBRE_BREVE'], 'LOC' => $r['LOCALIDAD'],
                        'FUND' => $r['FECHA_FUND'], 'EXT' => $r['FECHA_EXT'], 'TIPO' => $r['TIPO'],
                    ];
                    $next[] = $bid;
                }
                if ($nodes === []) break;
                $levels[] = $nodes;
                $frontier = $next;
                $depth++;
            }
            return $levels;
        };

        $up = $bfs('up');
        $down = $bfs('down');
        $juveniles = Db::all(
            "SELECT r.FECHA_INICIO, r.FECHA_FIN, b.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
             FROM banda_relacion r JOIN banda b ON b.ID_BANDA = r.ID_DESTINO
             WHERE r.ID_ORIGEN = ? AND r.TIPO = 'juvenil' ORDER BY r.FECHA_INICIO ASC",
            [$fid]
        );
        $madres = Db::all(
            "SELECT r.FECHA_INICIO, r.FECHA_FIN, b.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
             FROM banda_relacion r JOIN banda b ON b.ID_BANDA = r.ID_ORIGEN
             WHERE r.ID_DESTINO = ? AND r.TIPO = 'juvenil' ORDER BY r.FECHA_INICIO ASC",
            [$fid]
        );

        if ($up === [] && $down === [] && $juveniles === [] && $madres === []) return null;
        return ['focus' => $focus, 'up' => $up, 'down' => $down, 'juveniles' => $juveniles, 'madres' => $madres];
    }

    // ── Disco ──────────────────────────────────────────────────────────────

    public static function fetchDisco(string $id): ?array
    {
        $disco = Db::one(
            "SELECT d.ID_DISCO, d.NOMBRE_CD, d.FECHA_CD, d.d_DETALLES, b.ID_BANDA,
                    b.NOMBRE_BREVE AS BANDA_BREVE, b.LOCALIDAD AS BANDA_LOC,
                    (SELECT MAX(m.N_DISCO) FROM disco_marcha m WHERE m.ID_DISCO = d.ID_DISCO) AS DISCOS,
                    (b.NOMBRE_BREVE || ' (' || b.LOCALIDAD || ')') AS BANDA
             FROM disco d LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
             WHERE d.ID_DISCO = ?",
            [$id]
        );
        if ($disco === null) {
            return null;
        }
        $disco['REG_TOTAL'] = (int) (Db::one('SELECT COUNT(*) AS n FROM disco')['n'] ?? 0);
        $disco['REG_POS'] = (int) (Db::one('SELECT COUNT(*) AS n FROM disco WHERE ID_DISCO <= ?', [$id])['n'] ?? 0);
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
        $where = $nombre !== '' ? 'NOACC(d.NOMBRE_CD) LIKE ?' : '1=1';
        $values = $nombre !== '' ? ['%' . Db::noAcc($nombre) . '%'] : [];

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
