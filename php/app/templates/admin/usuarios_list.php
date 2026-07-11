<?php use App\View as V; use App\Auth; use App\Roles;
/** @var array $session @var list<array{id:int,usuario:string,rol:string}> $usuarios
 *  @var list<string> $roles @var array<string,string> $labels
 *  @var array|null $notice @var string|null $error
 *  @var string|null $nuevaClave @var string|null $nuevoUsuario */
$csrf = Auth::csrfToken($session);
$yo = (string) ($session['user'] ?? '');

$errMsg = [
    'CSRF' => 'Token de seguridad no válido, reinténtalo.',
    'USER_REQUIRED' => 'Escribe un nombre de usuario.',
    'USER_TOO_LONG' => 'El nombre de usuario es demasiado largo.',
    'USER_EXISTS' => 'Ese usuario ya existe.',
    'INVALID_ROL' => 'Rol no válido.',
    'NOT_FOUND' => 'Usuario no encontrado.',
    'LAST_ADMIN' => 'No puedes quitar el rol de administrador al último admin.',
];
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Usuarios y roles</h1>
        <div class="row">
            <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
        </div>
    </div>

<?php if ($error): ?><div class="alert alert-error"><?= V::e($errMsg[$error] ?? ('Error: ' . $error)) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<?php if ($nuevaClave !== null): ?>
    <div class="alert alert-success">
        <p><strong>Contraseña generada para <?= V::e($nuevoUsuario ?? '') ?></strong> — cópiala ahora, no se volverá a mostrar:</p>
        <p><code style="font-size:1.15em; user-select:all;"><?= V::e($nuevaClave) ?></code></p>
    </div>
<?php endif; ?>

    <section>
        <h2 class="section-title">Crear usuario</h2>
        <form class="panel" action="/dashboard/usuarios/crear" method="POST">
            <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
            <div class="field">
                <label class="field-label" for="usuario">Nombre de usuario</label>
                <div class="row">
                    <input class="input" id="usuario" name="usuario" type="text" autocomplete="off" placeholder="p. ej. redactor2" required>
                    <button class="btn btn-sm btn-neutral" type="submit">Crear (Editor)</button>
                </div>
                <p class="muted small">Se crea con rol <strong>Editor</strong> y una contraseña aleatoria que se mostrará una sola vez.</p>
            </div>
        </form>
    </section>

    <section>
        <h2 class="section-title">Usuarios existentes</h2>
        <div class="tableList"><table class="table table-zebra table-sm">
            <thead class="thead-neutral"><tr><td>Usuario</td><td>Rol</td><td>Contraseña</td></tr></thead>
            <tbody>
<?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <?= V::e($u['usuario']) ?>
                        <?php if ($u['usuario'] === $yo): ?><span class="muted small">· tú</span><?php endif; ?>
                    </td>
                    <td>
                        <form action="/dashboard/usuarios/<?= (int) $u['id'] ?>/rol" method="POST" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                            <select class="input" name="rol" onchange="this.form.submit()">
<?php foreach ($roles as $r): ?>
                                <option value="<?= V::e($r) ?>"<?= $u['rol'] === $r ? ' selected' : '' ?>><?= V::e($labels[$r] ?? $r) ?></option>
<?php endforeach; ?>
                            </select>
                            <noscript><button class="btn btn-sm" type="submit">Cambiar</button></noscript>
                        </form>
                    </td>
                    <td>
                        <form action="/dashboard/usuarios/<?= (int) $u['id'] ?>/reset" method="POST" class="inline-form" onsubmit="return confirm('¿Generar una nueva contraseña para <?= V::e($u['usuario']) ?>? La actual dejará de funcionar.');">
                            <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                            <button class="btn btn-sm btn-ghost" type="submit">Resetear contraseña</button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>
