<?php

declare(strict_types=1);

namespace App;

/**
 * Operaciones de escritura del panel admin. Ports de app/api/admin/*.
 * Devuelven ['code' => ...] con los mismos códigos que los Route Handlers.
 */
final class AdminRepo
{
    public const EDITABLE_MARCHA = ['TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'AUDIO', 'BANDA_ESTRENO', 'DETALLES_MARCHA'];
    public const INSERTABLE_MARCHA = ['TITULO', 'FECHA', 'DEDICATORIA', 'LOCALIDAD', 'PROVINCIA', 'BANDA_ESTRENO', 'DETALLES_MARCHA'];
    public const EDITABLE_AUTOR = ['NOMBRE', 'APELLIDOS', 'NOMBRE_ART', 'F_NAC', 'LUGAR_NAC', 'F_DEF', 'BIO'];

    public static function normalize(mixed $v): mixed
    {
        if ($v === null) return null;
        if (is_string($v)) {
            $t = trim($v);
            return $t === '' ? null : $t;
        }
        return $v;
    }

    /** @param list<int> $ids */
    private static function allAutoresExist(array $ids): bool
    {
        if ($ids === []) return false;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $row = Db::one("SELECT COUNT(*) AS c FROM autor WHERE ID_AUTOR IN ($ph)", array_values($ids));
        return (int) ($row['c'] ?? 0) === count($ids);
    }

    // ── editMarcha ─────────────────────────────────────────────────────────
    /**
     * @param list<string> $keys
     * @param list<mixed>  $values
     * @return array{code:string, changes?:int}
     */
    public static function editMarcha(int $marchaId, array $keys, array $values): array
    {
        $safe = [];
        foreach ($keys as $i => $k) {
            if (in_array($k, self::EDITABLE_MARCHA, true)) $safe[$k] = self::normalize($values[$i] ?? null);
        }
        if ($safe === []) return ['code' => 'INVALID_FIELDS'];

        if (array_key_exists('FECHA', $safe) && $safe['FECHA'] !== null && !preg_match('/^\d{4}$/', (string) $safe['FECHA'])) {
            return ['code' => 'INVALID_FECHA'];
        }

        $set = implode(', ', array_map(static fn(string $k): string => "$k = ?", array_keys($safe)));
        $changes = Db::run("UPDATE marcha SET $set WHERE ID_MARCHA = ?", [...array_values($safe), $marchaId]);
        if ($changes === 0) return ['code' => 'NOT_FOUND'];

        Db::logAdmin('UPDATE', 'marcha', $marchaId, ['campos' => array_keys($safe)]);
        return ['code' => 'UPDATED', 'changes' => $changes];
    }

    // ── addMarcha ──────────────────────────────────────────────────────────
    /**
     * @param array<string,mixed> $fields  campo => valor
     * @param list<int> $autoresIds
     * @return array{code:string, marchaId?:int}
     */
    public static function addMarcha(array $fields, array $autoresIds): array
    {
        $safe = [];
        foreach (self::INSERTABLE_MARCHA as $f) {
            if (array_key_exists($f, $fields)) $safe[$f] = self::normalize($fields[$f]);
        }
        if ($safe === []) return ['code' => 'INVALID_PAYLOAD'];

        if (array_key_exists('FECHA', $safe) && $safe['FECHA'] !== null && !preg_match('/^\d{4}$/', (string) $safe['FECHA'])) {
            return ['code' => 'INVALID_FECHA'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn($v): int => (int) $v, $autoresIds),
            static fn(int $n): bool => $n > 0
        )));
        if ($ids === []) return ['code' => 'AUTHORS_REQUIRED'];
        if (!self::allAutoresExist($ids)) return ['code' => 'INVALID_AUTHORS'];

        $cols = array_keys($safe);
        $marchaId = Db::transaction(static function () use ($cols, $safe, $ids): int {
            Db::run(
                'INSERT INTO marcha (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                array_values($safe)
            );
            $newId = Db::lastInsertId();
            if (!$newId) throw new \RuntimeException('Could not create marcha');
            $rowsPh = implode(', ', array_fill(0, count($ids), '(?, ?)'));
            $params = [];
            foreach ($ids as $aid) { $params[] = $newId; $params[] = $aid; }
            Db::run("INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES $rowsPh", $params);
            return $newId;
        });

        Db::logAdmin('INSERT', 'marcha', $marchaId, ['campos' => $cols, 'autores' => $ids]);
        return ['code' => 'CREATED', 'marchaId' => $marchaId];
    }

    // ── editMarchaAutores ──────────────────────────────────────────────────
    /**
     * @param list<int> $autoresIds
     * @return array{code:string}
     */
    public static function editMarchaAutores(int $marchaId, array $autoresIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($v): int => (int) $v, $autoresIds),
            static fn(int $n): bool => $n > 0
        )));
        if ($ids === []) return ['code' => 'BAD_REQUEST'];
        if (!self::allAutoresExist($ids)) return ['code' => 'INVALID_AUTORES'];

        Db::transaction(static function () use ($marchaId, $ids): void {
            Db::run('DELETE FROM marcha_autor WHERE ID_MARCHA = ?', [$marchaId]);
            foreach ($ids as $aid) {
                Db::run('INSERT INTO marcha_autor (ID_MARCHA, ID_AUTOR) VALUES (?, ?)', [$marchaId, $aid]);
            }
        });

        Db::logAdmin('UPDATE', 'marcha_autor', $marchaId, ['autoresIds' => $ids]);
        return ['code' => 'UPDATED'];
    }

    // ── editAutor ──────────────────────────────────────────────────────────
    /**
     * @param list<string> $keys
     * @param list<mixed>  $values  (ya normalizados por el controlador)
     * @return array{code:string}
     */
    public static function editAutor(int $autorId, array $keys, array $values): array
    {
        if ($keys === []) return ['code' => 'BAD_REQUEST'];
        foreach ($keys as $k) {
            if (!in_array($k, self::EDITABLE_AUTOR, true)) return ['code' => 'BAD_REQUEST'];
        }
        $set = implode(', ', array_map(static fn(string $k): string => "$k = ?", $keys));
        Db::run("UPDATE autor SET $set WHERE ID_AUTOR = ?", [...$values, $autorId]);
        Db::logAdmin('UPDATE', 'autor', $autorId, ['keysToUpdate' => $keys, 'valuesToUpdate' => $values]);
        return ['code' => 'UPDATED'];
    }

    // ── addAutor ───────────────────────────────────────────────────────────
    /**
     * @param array<string,mixed> $autor  campo => valor
     * @return array{code:string, autorId?:int}
     */
    public static function addAutor(array $autor): array
    {
        $values = array_map(static fn(string $f) => self::normalize($autor[$f] ?? null), self::EDITABLE_AUTOR);
        $ph = implode(', ', array_fill(0, count(self::EDITABLE_AUTOR), '?'));
        Db::run('INSERT INTO autor (' . implode(', ', self::EDITABLE_AUTOR) . ") VALUES ($ph)", $values);
        $autorId = Db::lastInsertId();
        if (!$autorId) return ['code' => 'INTERNAL_ERROR'];
        Db::logAdmin('INSERT', 'autor', $autorId);
        return ['code' => 'CREATED', 'autorId' => $autorId];
    }

    // ── Ingesta (candidatos de YouTube, ver tools/ingest/) ──────────────────

    /**
     * Acepta un candidato: crea la marcha (con el mismo camino que addMarcha)
     * y además fija AUDIO con la URL del vídeo de origen (addMarcha no admite
     * AUDIO al crear porque una marcha añadida a mano normalmente no tiene
     * vídeo todavía; aquí sí lo tenemos siempre).
     *
     * @param array<string,mixed> $fields
     * @param list<int> $autoresIds
     * @return array{code:string, marchaId?:int}
     */
    public static function aceptarCandidato(int $idCand, array $fields, array $autoresIds): array
    {
        $cand = Db::one('SELECT ESTADO, VIDEO_URL FROM ingest_candidato WHERE ID_CAND = ?', [$idCand]);
        if ($cand === null) return ['code' => 'NOT_FOUND'];
        if ($cand['ESTADO'] !== 'pendiente') return ['code' => 'NOT_PENDING'];

        $r = self::addMarcha($fields, $autoresIds);
        if (($r['code'] ?? '') !== 'CREATED') return $r;

        if (!empty($cand['VIDEO_URL'])) {
            self::editMarcha($r['marchaId'], ['AUDIO'], [$cand['VIDEO_URL']]);
        }

        Db::run(
            "UPDATE ingest_candidato SET ESTADO = 'aceptado', MARCHA_CREADA = ?, REVIEWED_AT = datetime('now') WHERE ID_CAND = ?",
            [$r['marchaId'], $idCand]
        );
        Db::logAdmin('ACCEPT', 'ingest_candidato', $idCand, ['marchaId' => $r['marchaId']]);
        return $r;
    }

    /** @return array{code:string} */
    public static function descartarCandidato(int $idCand, ?string $motivo): array
    {
        $changes = Db::run(
            "UPDATE ingest_candidato SET ESTADO = 'descartado', MOTIVO = ?, REVIEWED_AT = datetime('now')
             WHERE ID_CAND = ? AND ESTADO = 'pendiente'",
            [self::normalize($motivo), $idCand]
        );
        if ($changes === 0) return ['code' => 'NOT_FOUND_OR_NOT_PENDING'];
        Db::logAdmin('DISCARD', 'ingest_candidato', $idCand, ['motivo' => $motivo]);
        return ['code' => 'DISCARDED'];
    }
}
