<?php use App\View as V; use App\Slug as S; use App\Pages as P;
/** @var list<array<string,mixed>> $ultimas */
/** @var array{MARCHAS:int,AUTORES:int,BANDAS:int,DISCOS:int}|null $estado */
/** @var list<array{K:string,N:int}> $hubEstilos @var list<array{K:string,N:int}> $hubProvincias */
$num = static fn($n): string => number_format((int) $n, 0, ',', '.');
?>
<div class="stack home">
    <section class="card">
        <p class="welcome-text">
            Base de datos de <strong>música procesional</strong> española: marchas, compositores,
            bandas de cornetas y tambores, agrupaciones musicales y discos.
            Usa el menú para explorar o buscar por cualquier criterio.
        </p>
<?php if ($estado): ?>
        <div class="welcome-counts">
            <?= $estado['MARCHAS'] ?> marchas · <?= $estado['AUTORES'] ?> compositores · <?= $estado['BANDAS'] ?> bandas · <?= $estado['DISCOS'] ?> discos
        </div>
<?php endif; ?>
    </section>

<?php if (($hubEstilos ?? []) !== [] || ($hubProvincias ?? []) !== []): ?>
    <section>
        <h2 class="section-title">Explorar el catálogo</h2>
        <ul class="vease">
<?php foreach ($hubEstilos as $e):
    $ePath = P::estiloHubPath((string) $e['K']);
    $eLabel = P::estiloHubLabel((string) $e['K']);
    if ($ePath === null || $eLabel === null) continue; ?>
            <li>→ <a href="<?= V::e($ePath) ?>">Marchas de <?= V::e($eLabel) ?></a> <span class="cnt">(<?= $num($e['N']) ?> registros)</span></li>
<?php endforeach; ?>
<?php foreach ($hubProvincias as $pr): ?>
            <li>→ <a href="<?= V::e(P::provinciaHubPath((string) $pr['K'])) ?>">Marchas de la provincia de <?= V::e($pr['K']) ?></a> <span class="cnt">(<?= $num($pr['N']) ?> registros)</span></li>
<?php endforeach; ?>
            <li>→ <a href="/dedicatorias">Dedicatorias — advocaciones y hermandades</a></li>
        </ul>
    </section>
<?php endif; ?>

<?php if ($ultimas): ?>
    <section>
        <h2 class="section-title">Últimas incorporaciones</h2>
        <div class="ultimas">
<?php foreach ($ultimas as $m):
    $authors = implode(', ', array_map(static fn(array $a): string => (string) $a['nombre'], $m['AUTOR'])); ?>
            <a class="ultima-row" href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>">
                <span class="ultima-main">
                    <span class="ultima-title"><?= V::e($m['TITULO']) ?></span>
                    <span class="ultima-authors"><?= V::e($authors) ?></span>
                </span>
                <span class="ultima-date"><?= V::e($m['FECHA']) ?></span>
            </a>
<?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <p class="firma">Javier Guerra — <a href="https://x.com/JaviWarSVQ">@JaviWarSVQ</a></p>
</div>
