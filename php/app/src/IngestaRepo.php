<?php

declare(strict_types=1);

namespace App;

/**
 * Lecturas del panel de revisión de ingesta (candidatos de marcha desde
 * YouTube, ver tools/ingest/). Solo SELECT — las escrituras (aceptar/
 * descartar) viven en AdminRepo, junto al resto de operaciones de escritura
 * del panel.
 */
final class IngestaRepo
{
    public const ESTADOS = ['pendiente', 'aceptado', 'descartado', 'duplicado'];
    public const CLASIFICACIONES = ['estreno', 'novedad', 'recuperacion'];

    /** Mismos umbrales que tools/ingest/dedup.mjs, para que el criterio sea consistente. */
    private const UMBRAL_MEDIA = 0.75;

    /**
     * Al crear una marcha (a mano o al aceptar un candidato), revisa los
     * demás candidatos aún pendientes o ya descartados de la misma banda de
     * estreno por si alguno coincide por título con la marcha recién creada
     * — p.ej. dos vídeos distintos de la misma marcha, o una marcha que se
     * descartó antes de que existiera y ahora sí existe. Si hay coincidencia
     * ≥75% se anota MATCH_MARCHA_ID/MATCH_SCORE; los descartados que
     * coincidan vuelven a "pendiente" para que el revisor decida de nuevo.
     *
     * Se compara tanto contra $bandaEstreno (la banda de estreno final de la
     * marcha, que el revisor puede haber corregido a mano) como contra
     * $bandaOrigenCand (el canal de YouTube del candidato aceptado, si
     * aplica) — un candidato duplicado suele compartir canal con el
     * aceptado aunque la banda de estreno real sea otra.
     */
    public static function reevaluarTrasCrearMarcha(
        int $marchaId,
        ?int $bandaEstreno,
        string $tituloMarcha,
        ?int $excluirIdCand = null,
        ?int $bandaOrigenCand = null
    ): void {
        $tituloMarcha = trim($tituloMarcha);
        $bandas = array_values(array_unique(array_filter([$bandaEstreno, $bandaOrigenCand])));
        if ($tituloMarcha === '' || $bandas === []) return;

        $ph = implode(',', array_fill(0, count($bandas), '?'));
        $rows = Db::all(
            "SELECT ID_CAND, P_TITULO, VIDEO_TITULO, ESTADO, MOTIVO
             FROM ingest_candidato
             WHERE ESTADO IN ('pendiente', 'descartado')
               AND (COALESCE(P_BANDA_ESTRENO, ID_BANDA) IN ($ph) OR ID_BANDA IN ($ph))
               AND ID_CAND != ?",
            [...$bandas, ...$bandas, $excluirIdCand ?? 0]
        );

        foreach ($rows as $r) {
            $tituloCand = (string) ($r['P_TITULO'] ?: $r['VIDEO_TITULO']);
            $score = Similarity::ratio($tituloMarcha, $tituloCand);
            if ($score < self::UMBRAL_MEDIA) continue;

            if ($r['ESTADO'] === 'descartado') {
                $nota = 'Reabierto: posible coincidencia con la marcha recién creada #' . $marchaId . ' (similitud ' . (int) round($score * 100) . '%).';
                $motivo = $r['MOTIVO'] ? $r['MOTIVO'] . ' | ' . $nota : $nota;
                Db::run(
                    "UPDATE ingest_candidato
                     SET ESTADO = 'pendiente', MATCH_MARCHA_ID = ?, MATCH_SCORE = ?, MOTIVO = ?, REVIEWED_AT = NULL
                     WHERE ID_CAND = ?",
                    [$marchaId, $score, $motivo, $r['ID_CAND']]
                );
                Db::logAdmin('REOPEN', 'ingest_candidato', (int) $r['ID_CAND'], ['marchaId' => $marchaId, 'score' => $score]);
            } else {
                Db::run(
                    'UPDATE ingest_candidato SET MATCH_MARCHA_ID = ?, MATCH_SCORE = ? WHERE ID_CAND = ?',
                    [$marchaId, $score, $r['ID_CAND']]
                );
            }
        }
    }

    /** Conteos por estado, para las pestañas/badges del panel. */
    public static function counts(): array
    {
        $rows = Db::all('SELECT ESTADO, COUNT(*) AS n FROM ingest_candidato GROUP BY ESTADO');
        $out = array_fill_keys(self::ESTADOS, 0);
        foreach ($rows as $r) {
            if (isset($out[$r['ESTADO']])) $out[$r['ESTADO']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * @param array{estado?:string,banda?:string,clasificacion?:string} $filters
     * @return array{rowsReturned:int,totalRows:int,data:list<array<string,mixed>>}
     */
    public static function listCandidatos(array $filters, int $page = 1, int $limit = 30): array
    {
        $conditions = [];
        $values = [];

        $estado = (string) ($filters['estado'] ?? 'pendiente');
        if ($estado !== '' && $estado !== 'todos' && in_array($estado, self::ESTADOS, true)) {
            $conditions[] = 'c.ESTADO = ?';
            $values[] = $estado;
        }
        if (!empty($filters['banda'])) {
            $conditions[] = 'c.ID_BANDA = ?';
            $values[] = (int) $filters['banda'];
        }
        if (!empty($filters['clasificacion']) && in_array($filters['clasificacion'], self::CLASIFICACIONES, true)) {
            $conditions[] = 'c.CLASIFICACION = ?';
            $values[] = $filters['clasificacion'];
        }
        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';

        $countRow = Db::one("SELECT COUNT(*) AS n FROM ingest_candidato c WHERE $where", $values);
        $total = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT c.ID_CAND, c.ID_BANDA, c.VIDEO_ID, c.VIDEO_URL, c.VIDEO_TITULO, c.PUBLICADO_AT,
                    c.DURACION_SEG, c.CLASIFICACION, c.CONFIANZA, c.FLAGS, c.P_TITULO, c.P_FECHA,
                    c.MATCH_MARCHA_ID, c.MATCH_SCORE, c.ESTADO, b.NOMBRE_BREVE
             FROM ingest_candidato c
             LEFT JOIN banda b ON b.ID_BANDA = c.ID_BANDA
             WHERE $where
             ORDER BY CASE WHEN c.ESTADO = 'pendiente' THEN 0 ELSE 1 END, c.CONFIANZA DESC, c.ID_CAND DESC
             LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );

        return ['rowsReturned' => count($rows), 'totalRows' => $total, 'data' => $rows];
    }

    /** Ficha completa de un candidato (para el detalle/revisión), con el título de la posible marcha duplicada. */
    public static function fetchCandidato(int $id): ?array
    {
        return Db::one(
            "SELECT c.*, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO AS BANDA_NOMBRE_COMPLETO, b.LOCALIDAD AS BANDA_LOCALIDAD, mm.TITULO AS MATCH_TITULO
             FROM ingest_candidato c
             LEFT JOIN banda b ON b.ID_BANDA = c.ID_BANDA
             LEFT JOIN marcha mm ON mm.ID_MARCHA = c.MATCH_MARCHA_ID
             WHERE c.ID_CAND = ?",
            [$id]
        );
    }

    /** Bandas que tienen al menos un candidato (para el <select> de filtro). */
    public static function bandasConCandidatos(): array
    {
        return Db::all(
            "SELECT DISTINCT c.ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
             FROM ingest_candidato c LEFT JOIN banda b ON b.ID_BANDA = c.ID_BANDA
             ORDER BY b.NOMBRE_BREVE"
        );
    }
}
