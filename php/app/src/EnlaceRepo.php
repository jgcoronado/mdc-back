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
            // La banda de un candidato es la dueña del disco, o la propia entidad si TIPO_ENT='banda'.
            $conditions[] = "(d.BANDADISCO = ? OR (c.TIPO_ENT = 'banda' AND c.ID_ENT = ?))";
            $values[] = (int) $filters['banda'];
            $values[] = (int) $filters['banda'];
        }
        $where = $conditions !== [] ? implode(' AND ', $conditions) : '1=1';

        // Contexto polimórfico: disco (fase 1) o banda (fase 2). Los LEFT JOIN dejan
        // NULL el que no aplica; ENT_NOMBRE/ENT_BANDA/ENT_ANIO unifican la vista.
        $from = "FROM enlace_candidato c
                 LEFT JOIN disco d  ON c.TIPO_ENT = 'disco' AND d.ID_DISCO  = c.ID_ENT
                 LEFT JOIN banda b  ON b.ID_BANDA = d.BANDADISCO
                 LEFT JOIN banda bb ON c.TIPO_ENT = 'banda' AND bb.ID_BANDA = c.ID_ENT";

        $countRow = Db::one("SELECT COUNT(*) AS n $from WHERE $where", $values);
        $total = (int) ($countRow['n'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Db::all(
            "SELECT c.ID_CAND, c.TIPO_ENT, c.ID_ENT, c.SERVICIO, c.URL, c.TITULO_ENC, c.ARTISTA_ENC,
                    c.ANIO_ENC, c.SCORE, c.CONFIANZA, c.ESTADO,
                    COALESCE(d.NOMBRE_CD, bb.NOMBRE_BREVE) AS ENT_NOMBRE,
                    COALESCE(b.NOMBRE_BREVE, bb.NOMBRE_BREVE) AS ENT_BANDA,
                    d.FECHA_CD AS ENT_ANIO
             $from
             WHERE $where
             ORDER BY CASE WHEN c.ESTADO = 'pendiente' THEN 0 ELSE 1 END,
                      c.TIPO_ENT, c.ID_ENT, c.SCORE DESC
             LIMIT ? OFFSET ?",
            [...$values, $limit, $offset]
        );

        return ['rowsReturned' => count($rows), 'totalRows' => $total, 'data' => $rows];
    }

    /**
     * Enlaces PUBLICADOS (aprobados) de una entidad, para la ficha pública.
     * Devuelve [servicio => url] en el orden canónico de SERVICIOS.
     *
     * @return array<string,string>
     */
    public static function publicadosDe(string $tipo, int $id): array
    {
        $rows = Db::all(
            'SELECT SERVICIO, URL FROM enlace_streaming WHERE TIPO_ENT = ? AND ID_ENT = ?',
            [$tipo, $id]
        );
        $map = [];
        foreach ($rows as $r) $map[(string) $r['SERVICIO']] = (string) $r['URL'];
        $out = [];
        foreach (self::SERVICIOS as $s) {
            if (isset($map[$s])) $out[$s] = $map[$s];
        }
        return $out;
    }

    /** Bandas con al menos un candidato (disco propio o enlace de banda), para el <select> de filtro. */
    public static function bandasConCandidatos(): array
    {
        return Db::all(
            "SELECT DISTINCT ID_BANDA, NOMBRE_BREVE, LOCALIDAD FROM (
                SELECT d.BANDADISCO AS ID_BANDA, b.NOMBRE_BREVE, b.LOCALIDAD
                FROM enlace_candidato c
                JOIN disco d ON c.TIPO_ENT = 'disco' AND d.ID_DISCO = c.ID_ENT
                LEFT JOIN banda b ON b.ID_BANDA = d.BANDADISCO
                UNION
                SELECT bb.ID_BANDA, bb.NOMBRE_BREVE, bb.LOCALIDAD
                FROM enlace_candidato c
                JOIN banda bb ON c.TIPO_ENT = 'banda' AND bb.ID_BANDA = c.ID_ENT
             )
             WHERE ID_BANDA IS NOT NULL
             ORDER BY NOMBRE_BREVE"
        );
    }
}
