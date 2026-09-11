<?php use App\View as V; use App\Slug as S;
/** Acompañamientos históricos de una localidad, por hermandad y paso (N-04,
 *  rehecho 2026-08-29 — reemplaza a /temporada/{anio}, que enseñaba un año
 *  cada vez). Los años se colapsan en rangos con la misma banda (ver
 *  Repo::agruparAcompanamientos): más reciente arriba, hasta el año más
 *  antiguo con datos — no hay huecos rellenados, un año sin contrato
 *  conocido simplemente no aparece. La excepción son los años que sí sabemos
 *  por qué faltan (2020 y 2021, sin salida procesional): esos se anotan, ver
 *  014_temporada_sin_salida.sql.
 *  @var string $h1 @var string $localidad
 *  @var array<int,string> $sinSalida
 *  @var list<array{slug:string,nombre:string,titulares:list<array{titular:?string,
 *       rangos:list<array{anioInicio:int,anioFin:int,idBanda:int,banda:string,actual:bool,
 *       sinSalida:bool,motivo:?string}>}>}> $hermandades */
?>
<div class="stack">
    <div class="crumbs">
        <span><a href="/">Inicio</a> › <a href="/acompanamientos">Acompañamientos</a> › <?= V::e($localidad) ?></span>
    </div>

    <article class="record">
        <h1><?= V::e($h1) ?></h1>
        <p class="asiento">Qué banda ha tocado cada año tras cada paso de Cristo en <?= V::e($localidad) ?>, hermandad a hermandad — de más reciente a más antiguo.</p>
<?php if ($sinSalida !== []): ?>
<?php $aniosSin = array_keys($sinSalida); sort($aniosSin); ?>
        <p class="asiento"><strong><?= V::e(implode(' y ', array_map('strval', $aniosSin))) ?></strong> — <?= V::e((string) reset($sinSalida)) ?>: ninguna hermandad de <?= V::e($localidad) ?> hizo estación de penitencia.</p>
<?php endif; ?>

<?php if ($hermandades === []): ?>
        <p class="bio-empty">Todavía no hay acompañamientos registrados para <?= V::e($localidad) ?>.</p>
<?php else: ?>
<?php if (count($hermandades) > 8): ?>
        <nav class="acomp-jump" aria-label="Índice de hermandades">
<?php foreach ($hermandades as $h): ?>
            <a href="#h-<?= V::e($h['slug']) ?>"><?= V::e($h['nombre']) ?></a>
<?php endforeach; ?>
        </nav>
<?php endif; ?>

<?php foreach ($hermandades as $h): ?>
        <div class="shead" id="h-<?= V::e($h['slug']) ?>"><h2><?= V::e($h['nombre']) ?></h2></div>
<?php foreach ($h['titulares'] as $t): ?>
<?php if ($t['titular'] !== null): ?>
        <p class="acomp-paso"><?= V::e($t['titular']) ?></p>
<?php endif; ?>
        <ul class="vease acomp-rangos">
<?php foreach ($t['rangos'] as $rg): ?>
<?php $anios = $rg['anioInicio'] === $rg['anioFin'] ? (string) (int) $rg['anioInicio'] : ((int) $rg['anioInicio'] . '–' . (int) $rg['anioFin']); ?>
<?php if (!empty($rg['sinSalida'])): ?>
            <li class="acomp-sin-salida">
                <span class="anio"><?= V::e($anios) ?>:</span>
                <span><?= V::e((string) $rg['motivo']) ?></span>
            </li>
<?php else: ?>
            <li class="<?= $rg['actual'] ? 'acomp-actual' : '' ?>">
                <span class="anio"><?= V::e($anios) ?>:</span>
                <a href="<?= V::e(S::buildDetailPath('banda', $rg['idBanda'], $rg['banda'])) ?>"><?= V::e($rg['banda']) ?></a>
            </li>
<?php endif; ?>
<?php endforeach; ?>
        </ul>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
    </article>
</div>
