<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Acceso a SQLite vía PDO. Singleton perezoso + prepared statements.
 * Espejo intencionado de nextjs/lib/db.ts (all/run) para portar el resto sin fricción.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = (string) ($GLOBALS['config']['db_path'] ?? '');
        // No dejamos que PDO CREE una BD vacía si el fichero no existe: sería un
        // falso "todo va bien" con cero tablas. Mejor un error claro.
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException("Base de datos no encontrada en: {$path} — descarga mdc.db (ver php/README.md).");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // WAL para lecturas concurrentes; si el FS del host no lo admite,
        // SQLite mantiene el modo anterior y seguimos funcionando.
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (Throwable) {
            // journal_mode se queda como esté; irrelevante con un solo admin.
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->sqliteCreateFunction('NOACC', [self::class, 'noAcc'], 1);

        self::$pdo = $pdo;
        return $pdo;
    }

    /**
     * Minúsculas + sin diacríticos, para búsquedas LIKE insensibles a mayúsculas
     * y acentos (p.ej. "redencion" encuentra "Redención"). Expuesta a SQLite como
     * función NOACC(), y usada también en PHP para normalizar el parámetro del bind.
     */
    public static function noAcc(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $lower = mb_strtolower($s, 'UTF-8');
        $decomposed = \Normalizer::normalize($lower, \Normalizer::FORM_D);
        if ($decomposed === false) {
            return $lower;
        }
        return (string) preg_replace('/\p{Mn}/u', '', $decomposed);
    }

    /**
     * @param  list<mixed> $params
     * @return list<array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * @param  list<mixed> $params
     * @return array<string,mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Devuelve el número de filas afectadas.
     *
     * @param list<mixed> $params
     */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Ejecuta $fn dentro de una transacción (commit/rollback automático). */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Registro de auditoría (equivale a lib/db.ts logAdmin). */
    public static function logAdmin(string $accion, string $tabla, ?int $idRegistro, mixed $payload = null): void
    {
        self::run(
            'INSERT INTO admin_log (accion, tabla, id_registro, usuario, ts, payload) VALUES (?, ?, ?, ?, ?, ?)',
            [$accion, $tabla, $idRegistro, 'admin', time(), $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]
        );
    }

    /**
     * Conteos globales para el pie de página (equivalente a fetchEstado()).
     *
     * @return array{MARCHAS:int,AUTORES:int,BANDAS:int,DISCOS:int}
     */
    public static function counts(): array
    {
        $row = self::one(
            'SELECT (SELECT COUNT(*) FROM marcha) AS MARCHAS,
                    (SELECT COUNT(*) FROM autor)  AS AUTORES,
                    (SELECT COUNT(*) FROM banda)  AS BANDAS,
                    (SELECT COUNT(*) FROM disco)  AS DISCOS'
        );

        return [
            'MARCHAS' => (int) ($row['MARCHAS'] ?? 0),
            'AUTORES' => (int) ($row['AUTORES'] ?? 0),
            'BANDAS'  => (int) ($row['BANDAS'] ?? 0),
            'DISCOS'  => (int) ($row['DISCOS'] ?? 0),
        ];
    }
}
