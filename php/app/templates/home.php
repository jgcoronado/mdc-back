<?php use App\View as V; use App\Slug as S;
/** @var list<array<string,mixed>> $ultimas */
/** @var array{MARCHAS:int,AUTORES:int,BANDAS:int,DISCOS:int}|null $estado */
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
