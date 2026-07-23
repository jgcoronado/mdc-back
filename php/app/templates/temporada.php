<?php use App\View as V; use App\Slug as S;
/** Contratos banda↔hermandad de una temporada (N-04/N-05).
 *  @var string $h1  @var string $anio
 *  @var array<string,array{nombre:string,items:list<array<string,mixed>>}> $grupos  agrupados por HERMANDAD_SLUG */
$anioInt = (int) $anio;
?>
<div class="stack">
    <div class="crumbs">
        <span><a href="/">Inicio</a> › <?= V::e($h1) ?></span>
        <span class="regnav">
            <a href="/temporada/<?= $anioInt - 1 ?>">&larr; <?= $anioInt - 1 ?></a>
            &nbsp;·&nbsp;
            <a href="/temporada/<?= $anioInt + 1 ?>"><?= $anioInt + 1 ?> &rarr;</a>
        </span>
    </div>

    <article class="record">
        <div class="head">
            <span class="eb">Temporada</span>
            <span class="sig">MDC · contratos</span>
        </div>
        <h1><?= V::e($h1) ?></h1>
        <p class="asiento">Qué banda toca este año tras cada paso, hermandad a hermandad.</p>

<?php if ($grupos === []): ?>
        <p class="bio-empty">Todavía no hay contratos registrados para <?= V::e($anio) ?>.</p>
<?php else: ?>
<?php foreach ($grupos as $g): ?>
        <div class="shead"><h2><?= V::e($g['nombre']) ?></h2></div>
        <ul class="vease">
<?php foreach ($g['items'] as $it): ?>
            <li>→
<?php if (!empty($it['TITULAR'])): ?>
                <strong><?= V::e($it['TITULAR']) ?></strong> —
<?php endif; ?>
                <a href="<?= V::e(S::buildDetailPath('banda', $it['ID_BANDA'], (string) $it['BANDA'])) ?>"><?= V::e($it['BANDA']) ?></a>
<?php if (!empty($it['FUENTE'])): ?>
                <span class="cnt">(<a href="<?= V::e($it['FUENTE']) ?>" rel="noopener nofollow">fuente</a>)</span>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endforeach; ?>
<?php endif; ?>
    </article>
</div>
