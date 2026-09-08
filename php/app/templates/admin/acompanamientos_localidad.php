<?php use App\View as V; use App\Auth;
/** @var array $session @var string $localidad
 *  @var list<array<string,mixed>> $filas
 *  @var array|null $notice */
$csrf = Auth::csrfToken($session);
$titulo = $localidad !== '' ? $localidad : 'Sin localidad';
?>
<div class="crumbs">
    <span><a href="/dashboard">Panel</a> › <a href="/dashboard/acompanamientos">Acompañamientos</a> › <?= V::e($titulo) ?></span>
</div>

<h1>Acompañamientos de <?= V::e($titulo) ?></h1>
<p class="muted">Cada acompañamiento se guarda por separado: se cambian la <strong>banda</strong> y la <strong>vigencia</strong> (año de inicio y de fin). Fin en blanco = sigue vigente. La hermandad y el paso no se editan aquí — cambiarlos sería otro acompañamiento distinto: dalo de alta desde <a href="/dashboard/acompanamientos">la vista por banda</a>.</p>

<?php /* Lista de tarjetas en vez de tabla: cada fila lleva su propio <form> (un
         form no puede repartirse entre celdas) y así los controles se reparten
         en varias líneas cuando no caben, sin scroll horizontal. */ ?>
<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<section>
    <h2 class="section-title"><?= count($filas) ?> acompañamiento<?= count($filas) === 1 ? '' : 's' ?></h2>
<?php if ($filas): ?>
    <div class="acomp-list">
<?php foreach ($filas as $c): $cid = (int) $c['ID_CONTRATO']; $titular = trim((string) ($c['TITULAR'] ?? '')); ?>
        <div class="acomp-item">
            <div class="acomp-item-head">
                <strong><?= V::e((string) $c['HERMANDAD']) ?></strong>
                <span class="muted small"><?= $titular !== '' ? V::e($titular) : '—' ?></span>
            </div>
            <form class="acomp-row" method="POST" action="/dashboard/acompanamientos/localidad/<?= $cid ?>">
                <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                <input type="hidden" name="LOCALIDAD" value="<?= V::e($localidad) ?>">
                <input type="hidden" name="ID_BANDA" value="<?= (int) $c['ID_BANDA'] ?>" data-acomp-banda-id>

                <div class="acomp-field acomp-field-banda">
                    <label class="field-label small" for="banda-<?= $cid ?>">Banda</label>
                    <div class="autocomplete">
                        <input class="input input-sm" id="banda-<?= $cid ?>" type="text"
                               placeholder="Cambiar banda (mín. 3 caracteres)…" autocomplete="off" data-acomp-banda-search>
                        <div class="suggest" hidden data-acomp-banda-suggest></div>
                    </div>
                    <p class="muted small" data-acomp-banda-chosen><?= V::e((string) $c['BANDA']) ?> (#<?= (int) $c['ID_BANDA'] ?>)</p>
                </div>

                <div class="acomp-field acomp-field-anio">
                    <label class="field-label small" for="anio-<?= $cid ?>">Desde</label>
                    <input class="input input-sm" id="anio-<?= $cid ?>" type="number" name="ANIO"
                           min="1900" max="2100" step="1" value="<?= (int) $c['ANIO'] ?>" required>
                </div>

                <div class="acomp-field acomp-field-anio">
                    <label class="field-label small" for="fin-<?= $cid ?>">Hasta</label>
                    <input class="input input-sm" id="fin-<?= $cid ?>" type="number" name="ANIO_FIN"
                           min="1900" max="2100" step="1" placeholder="vigente"
                           value="<?= $c['ANIO_FIN'] !== null ? (int) $c['ANIO_FIN'] : '' ?>">
                </div>

                <div class="acomp-field acomp-field-acciones">
                    <button class="btn btn-sm btn-neutral" type="submit">Guardar</button>
                </div>
            </form>
            <form method="POST" action="/dashboard/acompanamientos/localidad/<?= $cid ?>/borrar" class="acomp-borrar"
                  onsubmit="return confirm('¿Eliminar este acompañamiento?');">
                <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                <input type="hidden" name="LOCALIDAD" value="<?= V::e($localidad) ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Borrar</button>
            </form>
        </div>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="muted">No hay acompañamientos cargados con la localidad «<?= V::e($titulo) ?>».</p>
<?php endif; ?>
</section>

<script src="/assets/acompanamientos.js" defer></script>
