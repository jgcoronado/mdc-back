<?php

declare(strict_types=1);

namespace App;

/**
 * Lecturas del panel de curación de enlaces de streaming (candidatos de
 * Spotify / Apple / Deezer generados por tools/music_links/). Solo SELECT —
 * las escrituras (aprobar/rechazar) viven en AdminRepo, como el resto del panel.
 *
 * Fase 1 cura enlaces de DISCO; el modelo (enlace_candidato.TIPO_ENT) admite
 * banda y marcha para las fases siguientes.
 */
final class EnlaceRepo
{
    public const ESTADOS = ['pendiente', 'aprobado', 'rechazado'];
    public const SERVICIOS = ['spotify', 'apple', 'deezer', 'youtube', 'tidal', 'amazon'];
    public const CONFIANZAS = ['ALTA', 'MEDIA', 'BAJA', 'SIN_MATCH'];

    /** Conteos por estado, para las pestañas/badges del panel. */
    public static function counts(): array
    {
        $rows = Db::all('SELECT ESTADO, COUNT(*) AS n FROM enlace_candidato GROUP BY ESTADO');
        $out = array_fill_keys(self::ESTADOS, 0);
        foreach ($rows as $r) {
            if (isset($out[$r['ESTADO']])) $out[$r['ESTADO']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * @param array{estado?:string,servicio?:string,confianza?:string,banda?:string} $filters
     * @return array{rowsReturned:int,totalRows:int,data:list<array<string,mixed>>}
     */
    public static function listCandidatos(array $filters, int $page = 1, int $limit = 40): array
    {
        $conditions = [];
        $values = [];

        $estado = (string) ($filters['estado'] ?? 'pendiente');
        if ($estado !== '' && $estado !== 'todos' && in_array($estado, self::ESTADOS, true)) {
            $conditions[] = 'c.ESTADO = ?';
            $values[] = $estado;
        }
        if (!empty($filters['servicio']) && in_array($filters['servicio'], self::SERVICIOS, true)) {
            $conditions[] = 'c.SERVICIO = ?';
            $values[] = $filters['servicio'];
        }
        if (!empty($filters['confianza']) && in_array($filters['confianza'], self::CONFIANZAS, true)) {
            $conditions[] = 'c.CONFIANZA = ?';
            $values[] = $filters['confianza'];
        }
        if (!empty($filters['banda'])) {
            $conditions[] = 'd.BANDADISCO = ?';
            $values[] = (int) $filters['banda'];
        }
        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';

        // Contexto de disco/banda (fase 1). Para tipos futuros el LEFT JOIN deja NULL.
        $from = "FROM enlace_candidato c
                 LEFT JOIN disco d ON c.TIPO_ENT = 'disco' AND d.ID_DISCO = c.ID_ENT
                 LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO";

        $countRow = Db::one("SELECT COUNT(*) AS n $from WHERE $where", $values);
        $total = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT c.ID_CAND, c.TIPO_ENT, c.ID_ENT, c.SERVICIO, c.URL, c.TITULO_ENC, c.ARTISTA_ENC,
                    c.ANIO_ENC, c.SCORE, c.CONFIANZA, c.ESTADO,
                    d.NOMBRE_CD, d.FECHA_CD, d.BANDADISCO, b.NOMBRE_BREVE
             $from
             WHERE $where
             ORDER BY CASE WHEN c.ESTADO = 'pendiente' THEN 0 ELSE 1 END,
                      c.ID_ENT, c.SCORE DESC
             LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );

        return ['rowsReturned' => count($rows), 'totalRows' => $total, 'data' => $rows];
    }

    /** Bandas con al menos un disco candidato (para el <select> de filtro). */
    public static function bandasConCandidatos(): array
    {
        return Db::all(
            "SELECT DISTINCT d.BANDADISCO AS ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
             FROM enlace_candidato c
             JOIN disco d ON c.TIPO_ENT = 'disco' AND d.ID_DISCO = c.ID_ENT
             LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
             ORDER BY b.NOMBRE_BREVE"
        );
    }
}
