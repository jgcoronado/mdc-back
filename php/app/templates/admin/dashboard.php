<?php use App\View as V; use App\Auth;
/** @var string $q @var list<array<string,mixed>> $marchas @var list<array<string,mixed>> $autores @var array $session */
$csrf = Auth::csrfToken($session);
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Panel de administración</h1>
        <div class="row">
            <a class="btn btn-sm" href="/dashboard/marcha/add">+ Marcha</a>
            <a class="btn btn-sm" href="/dashboard/autor/add">+ Compositor</a>
            <form action="/logout" method="POST" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </div>

    <form class="panel" action="/dashboard" method="GET">
        <div class="field">
            <label class="field-label" for="q">Buscar marcha o compositor</label>
            <div class="row">
                <input class="input" id="q" name="q" type="text" value="<?= V::e($q) ?>" placeholder="Título o nombre…" autofocus>
                <button class="btn btn-sm btn-neutral" type="submit">Buscar</button>
            </div>
        </div>
    </form>

<?php if ($q !== ''): ?>
    <section>
        <h2 class="section-title">Marchas</h2>
<?php if ($marchas): ?>
        <div class="tableList"><table class="table table-zebra table-sm"><tbody>
<?php foreach ($marchas as $m): ?>
            <tr>
                <td><a href="/dashboard/marcha/<?= (int) $m['ID_MARCHA'] ?>">#<?= (int) $m['ID_MARCHA'] ?> · <?= V::e($m['TITULO']) ?></a></td>
                <td class="small nums"><?= V::e($m['FECHA']) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody></table></div>
<?php else: ?>
        <p class="muted">Sin resultados.</p>
<?php endif; ?>
    </section>

    <section>
        <h2 class="section-title">Compositores</h2>
<?php if ($autores): ?>
        <div class="tableList"><table class="table table-zebra table-sm"><tbody>
<?php foreach ($autores as $a): ?>
            <tr>
                <td><a href="/dashboard/autor/<?= (int) $a['ID_AUTOR'] ?>">#<?= (int) $a['ID_AUTOR'] ?> · <?= V::e($a['NOMBRE_COMPLETO']) ?></a></td>
                <td class="small nums"><?= V::e($a['MARCHAS']) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody></table></div>
<?php else: ?>
        <p class="muted">Sin resultados.</p>
<?php endif; ?>
    </section>
<?php endif; ?>
</div>
