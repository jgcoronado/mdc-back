<?php use App\View as V;
/** @var array $session
 *  @var list<array{LOCALIDAD:string,N:int}> $localidades
 *  @var array|null $notice */
?>
<div class="crumbs"><span><a href="/dashboard">Panel</a> › Acompañamientos</span></div>

<h1>Acompañamientos</h1>
<p class="muted">Dos formas de trabajar sobre lo mismo: <strong>por localidad</strong> para repasar una Semana Santa entera (se edita la banda y la vigencia de cada acompañamiento) y <strong>por banda</strong> para dar de alta lo que toca una banda concreta. La vista por año sigue en <a href="/dashboard/temporada/<?= (int) date('Y') ?>">Temporada</a>.</p>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<section>
    <h2 class="section-title">Por localidad</h2>
<?php if ($localidades): ?>
    <div class="chips">
<?php foreach ($localidades as $l): $loc = (string) $l['LOCALIDAD']; ?>
        <a class="btn btn-sm btn-ghost" href="/dashboard/acompanamientos/localidad?loc=<?= V::e(rawurlencode($loc)) ?>">
            <?= V::e($loc !== '' ? $loc : 'Sin localidad') ?> <span class="chip"><?= (int) $l['N'] ?></span>
        </a>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="muted">Todavía no hay acompañamientos cargados.</p>
<?php endif; ?>
    <?php /* Localidad libre: la primera carga de una ciudad nueva no aparece
             arriba porque aún no tiene ninguna fila. */ ?>
    <form class="panel" method="GET" action="/dashboard/acompanamientos/localidad">
        <div class="field">
            <label class="field-label" for="loc">Otra localidad</label>
            <input class="input" id="loc" name="loc" type="text" placeholder="p. ej. Granada" autocomplete="off">
            <p class="muted small">Escríbela igual que en la carga (se compara literal, sin normalizar).</p>
        </div>
        <div><button class="btn btn-neutral" type="submit">Abrir</button></div>
    </form>
</section>

<section>
    <h2 class="section-title">Por banda</h2>
    <div class="panel">
        <div class="field">
            <label class="field-label" for="acBandaSearch">Buscar banda</label>
            <div class="autocomplete">
                <input class="input" id="acBandaSearch" type="text" placeholder="Nombre o localidad (mín. 3 caracteres)…" autocomplete="off">
                <div id="acBandaSuggest" class="suggest" hidden></div>
            </div>
            <p class="muted small">Al elegirla se abre su listado de acompañamientos.</p>
        </div>
    </div>
</section>

<script src="/assets/acompanamientos.js" defer></script>
