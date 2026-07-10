<?php use App\View as V;
/** @var string $q @var bool $soloPersonales
 *  @var list<array{ID_DEDIC:int,NOMBRE:string,LOCALIDAD:string,N_VAR:int,N_MAR:int,PERSONAL:int}> $items
 *  @var array|null $notice @var array $session */
$num = static fn($n): string => number_format((int) $n, 0, ',', '.');
$titulo = $soloPersonales ? 'Marcadas como personales' : ($q === '' ? 'Advocaciones con varias variantes' : 'Resultados');
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Dedicatorias — curación</h1>
        <div class="row">
            <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
            <a class="btn btn-sm btn-ghost" href="/dedicatorias" target="_blank" rel="noopener">Ver índice público ↗</a>
        </div>
    </div>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

    <p class="muted">Cada <strong>advocación canónica</strong> agrupa una o varias variantes de texto de <span class="mono">DEDICATORIA</span> (por advocación + localidad). Las marcadas <strong>personales</strong> (dedicatorias particulares: «A Fulano…», «Al Padre Del Autor»…) no aparecen en el índice público ni en el sitemap.</p>

    <form class="panel" action="/dashboard/dedicatorias" method="GET">
        <div class="field">
            <label class="field-label" for="q">Buscar advocación, localidad o variante</label>
            <div class="row">
                <input class="input" id="q" name="q" type="text" value="<?= V::e($q) ?>" placeholder="Esperanza, Sevilla, Hdad…" autofocus>
                <button class="btn btn-sm btn-neutral" type="submit">Buscar</button>
<?php if ($q !== '' || $soloPersonales): ?>
                <a class="btn btn-sm btn-ghost" href="/dashboard/dedicatorias">limpiar</a>
<?php endif; ?>
            </div>
        </div>
    </form>

    <p class="muted small">
<?php if (!$soloPersonales): ?>
        <a href="/dashboard/dedicatorias?personales=1">Ver solo las marcadas como personales</a> — para auditar la clasificación automática.
<?php else: ?>
        <a href="/dashboard/dedicatorias">← Ver todas</a>
<?php endif; ?>
    </p>

    <section>
        <h2 class="section-title"><?= V::e($titulo) ?>
            <span class="muted small">· <?= $num(count($items)) ?><?= $q === '' && !$soloPersonales ? ' (revisa que la fusión sea correcta)' : '' ?></span></h2>
<?php if ($items): ?>
        <div class="tableList"><table class="table table-zebra table-sm">
            <thead class="thead-neutral"><tr><td>Advocación</td><td>Localidad</td><td>Variantes</td><td>Marchas</td><td></td></tr></thead>
            <tbody>
<?php foreach ($items as $it): ?>
            <tr>
                <td><a href="/dashboard/dedicatoria/<?= (int) $it['ID_DEDIC'] ?>">#<?= (int) $it['ID_DEDIC'] ?> · <?= V::e($it['NOMBRE']) ?></a></td>
                <td class="small muted"><?= V::e($it['LOCALIDAD'] !== '' ? $it['LOCALIDAD'] : '—') ?></td>
                <td class="small nums"><?= $num($it['N_VAR']) ?></td>
                <td class="small nums"><?= $num($it['N_MAR']) ?></td>
                <td><?php if ((int) $it['PERSONAL'] === 1): ?><span class="badge badge-warn">personal</span><?php endif; ?></td>
            </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php else: ?>
        <p class="muted"><?= $soloPersonales ? 'Ninguna marcada como personal.' : ($q === '' ? 'No hay advocaciones con más de una variante.' : 'Sin resultados.') ?></p>
<?php endif; ?>
    </section>
</div>
