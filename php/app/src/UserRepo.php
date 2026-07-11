<?php

declare(strict_types=1);

namespace App;

/**
 * Gestión de usuarios del panel (solo admin). Reutiliza el hashing PBKDF2 actual
 * de Auth, de modo que las cuentas creadas aquí son indistinguibles de las
 * existentes. La contraseña en claro solo se devuelve una vez (al crear o
 * resetear) para mostrarla; nunca se almacena en claro.
 */
final class UserRepo
{
    private const MAX_USER = 120;

    /** @return list<array{id:int,usuario:string,rol:string}> */
    public static function all(): array
    {
        $rows = Db::all('SELECT id, usuario, ROL FROM usuarios ORDER BY usuario COLLATE NOCASE');
        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'usuario' => (string) $r['usuario'],
            'rol' => Roles::normalize(is_string($r['ROL'] ?? null) ? (string) $r['ROL'] : null),
        ], $rows);
    }

    public static function countAdmins(): int
    {
        $row = Db::one("SELECT COUNT(*) AS c FROM usuarios WHERE ROL = ?", [Roles::ADMIN]);
        return (int) ($row['c'] ?? 0);
    }

    /** @return array{code:string, usuario?:string, clave?:string} */
    public static function create(string $usuario): array
    {
        $usuario = trim($usuario);
        if ($usuario === '') return ['code' => 'USER_REQUIRED'];
        if (mb_strlen($usuario) > self::MAX_USER) return ['code' => 'USER_TOO_LONG'];
        if (Db::one('SELECT 1 AS x FROM usuarios WHERE usuario = ? COLLATE NOCASE', [$usuario]) !== null) {
            return ['code' => 'USER_EXISTS'];
        }
        $clave = Auth::generatePassword();
        Db::run(
            'INSERT INTO usuarios (usuario, clave, ROL) VALUES (?, ?, ?)',
            [$usuario, Auth::hashPassword($clave), Roles::EDITOR]
        );
        Db::logAdmin('INSERT', 'usuarios', Db::lastInsertId(), ['usuario' => $usuario, 'rol' => Roles::EDITOR]);
        return ['code' => 'CREATED', 'usuario' => $usuario, 'clave' => $clave];
    }

    /** @return array{code:string, clave?:string, usuario?:string} */
    public static function resetPassword(int $id): array
    {
        $row = Db::one('SELECT usuario FROM usuarios WHERE id = ?', [$id]);
        if ($row === null) return ['code' => 'NOT_FOUND'];
        $clave = Auth::generatePassword();
        Db::run('UPDATE usuarios SET clave = ? WHERE id = ?', [Auth::hashPassword($clave), $id]);
        Db::logAdmin('RESET_PASSWORD', 'usuarios', $id, ['usuario' => $row['usuario']]);
        return ['code' => 'RESET', 'clave' => $clave, 'usuario' => (string) $row['usuario']];
    }

    /**
     * Cambia el rol. Guardarraíl: no permite dejar el sistema sin ningún admin
     * (evita un bloqueo total del panel).
     *
     * @return array{code:string}
     */
    public static function changeRole(int $id, string $rol): array
    {
        if (!in_array($rol, Roles::ALL, true)) return ['code' => 'INVALID_ROL'];
        $row = Db::one('SELECT usuario, ROL FROM usuarios WHERE id = ?', [$id]);
        if ($row === null) return ['code' => 'NOT_FOUND'];
        $actual = Roles::normalize(is_string($row['ROL'] ?? null) ? (string) $row['ROL'] : null);
        if ($actual === $rol) return ['code' => 'NO_CHANGE'];
        if ($actual === Roles::ADMIN && $rol !== Roles::ADMIN && self::countAdmins() <= 1) {
            return ['code' => 'LAST_ADMIN'];
        }
        Db::run('UPDATE usuarios SET ROL = ? WHERE id = ?', [$rol, $id]);
        Db::logAdmin('UPDATE', 'usuarios', $id, ['usuario' => $row['usuario'], 'rol' => $rol]);
        return ['code' => 'UPDATED'];
    }
}
