<?php use App\View as V; use App\Auth; use App\Slug as S;
/** @var array $session @var string $anio
 *  @var list<array{ID_CONTRATO:int,HERMANDAD:string,TITULAR:?string,FUENTE:?string,ID_BANDA:int,BANDA:string,ANIO:int,ANIO_FIN:?int}> $contratos
 *  @var array|null $notice */
$csrf = Auth::csrfToken($session);
?>
<div class="crumbs">
    <span><a href="/dashboard">Panel</a> › Temporada <?= V::e($anio) ?></span>
    <span class="regnav">
        <a href="/dashboard/temporada/<?= (int) $anio - 1 ?>">&larr; <?= (int) $anio - 1 ?></a>
        &nbsp;·&nbsp;
        <a href="/dashboard/temporada/<?= (int) $anio + 1 ?>"><?= (int) $anio + 1 ?> &rarr;</a>
    </span>
</div>

<h1>Temporada <?= V::e($anio) ?> — contratos</h1>
<p class="muted">Alta manual (N-06, la ingesta automática de anuncios, queda pendiente). La hermandad es texto libre: escríbela igual cada vez para que se agrupe bien en <a href="/temporada/<?= V::e($anio) ?>">la página pública</a>. Aquí se ven los acompañamientos <strong>vigentes</strong> en <?= V::e($anio) ?> (empezados ese año o antes y sin cerrar todavía); para editarlos uno a uno, <a href="/dashboard/acompanamientos">los editores por localidad y por banda</a>.</p>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<section>
    <h2 class="section-title">Añadir contrato</h2>
    <form class="panel" action="/dashboard/temporada/<?= V::e($anio) ?>/add" method="POST" id="contratoForm">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">

        <div class="field">
            <label class="field-label" for="contratoBandaSearch">Banda</label>
            <input type="hidden" name="ID_BANDA" id="ID_BANDA" value="">
            <div class="autocomplete">
                <input class="input" id="contratoBandaSearch" type="text" placeholder="Buscar banda (mín. 3 caracteres)…" autocomplete="off">
                <div id="contratoBandaSuggest" class="suggest" hidden></div>
            </div>
            <p class="muted small">Seleccionada: <strong id="contratoBandaChosen">(ninguna)</strong></p>
        </div>

        <div class="field">
            <label class="field-label" for="HERMANDAD">Hermandad</label>
            <input class="input" id="HERMANDAD" name="HERMANDAD" type="text" placeholder="p. ej. Hermandad de la Esperanza de Triana" required>
        </div>

        <div class="field">
            <label class="field-label" for="TITULAR">Titular / paso (opcional)</label>
            <input class="input" id="TITULAR" name="TITULAR" type="text" placeholder="p. ej. Virgen de la Esperanza">
        </div>

        <?php /* El año de inicio es el de la URL; aquí solo se cierra la
                 vigencia si ya se sabe que el acompañamiento termina. */ ?>
        <div class="field">
            <label class="field-label" for="ANIO_FIN">Año de fin (opcional)</label>
            <input class="input acomp-anio" id="ANIO_FIN" name="ANIO_FIN" type="number" min="1900" max="2100" step="1" placeholder="vigente">
            <p class="muted small">En blanco = sigue vigente y aparecerá también en las temporadas siguientes.</p>
        </div>

        <div class="field">
            <label class="field-label" for="FUENTE">Fuente (opcional, se muestra público)</label>
            <input class="input" id="FUENTE" name="FUENTE" type="text" placeholder="URL del anuncio">
        </div>

        <div class="field">
            <label class="field-label" for="NOTA">Nota interna (opcional, NO se muestra público)</label>
            <textarea class="input" id="NOTA" name="NOTA" rows="2"></textarea>
        </div>

        <div><button class="btn btn-neutral" type="submit">Añadir contrato</button></div>
    </form>
</section>

<section>
    <h2 class="section-title">Contratos de <?= V::e($anio) ?> (<?= count($contratos) ?>)</h2>
<?php if ($contratos): ?>
    <div class="tableList"><table class="table table-zebra table-sm">
        <thead class="thead-neutral"><tr><td>Hermandad</td><td>Titular</td><td>Banda</td><td>Vigencia</td><td>Fuente</td><td></td></tr></thead>
        <tbody>
<?php foreach ($contratos as $c): ?>
            <tr>
                <td><?= V::e($c['HERMANDAD']) ?></td>
                <td><?= V::e($c['TITULAR'] ?? '—') ?></td>
                <td><a href="<?= V::e(S::buildDetailPath('banda', $c['ID_BANDA'], (string) $c['BANDA'])) ?>"><?= V::e($c['BANDA']) ?></a></td>
                <td class="small"><?= (int) $c['ANIO'] ?>–<?= $c['ANIO_FIN'] !== null ? (int) $c['ANIO_FIN'] : '…' ?></td>
                <td class="small"><?= !empty($c['FUENTE']) ? '<a href="' . V::e($c['FUENTE']) . '" rel="noopener">enlace</a>' : '—' ?></td>
                <td>
                    <form action="/dashboard/temporada/<?= V::e($anio) ?>/<?= (int) $c['ID_CONTRATO'] ?>/borrar" method="POST" class="inline-form" onsubmit="return confirm('¿Eliminar este contrato?');">
                        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Borrar</button>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table></div>
<?php else: ?>
    <p class="muted">Todavía no hay contratos registrados para <?= V::e($anio) ?>.</p>
<?php endif; ?>
</section>

<script src="/assets/admin.js" defer></script>
