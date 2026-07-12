<?php use App\View as V; use App\Slug as S; use App\Pages as P; use App\Media as MD;
/** @var list<array<string,mixed>> $ultimas */
/** @var array{MARCHAS:int,AUTORES:int,BANDAS:int,DISCOS:int}|null $estado */
/** @var array<string,mixed>|null $marchaDelDia */
/** @var list<array{K:string,N:int}> $hubEstilos @var list<array{K:string,N:int}> $hubProvincias @var list<array{K:int,N:int}> $hubAniosRecientes */
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

<?php if ($marchaDelDia):
    $mdd = $marchaDelDia;
    $mddYtid = MD::youtubeId($mdd['AUDIO'] ?? null);
    $mddAutores = implode(', ', array_map(static fn(array $a): string => (string) $a['nombre'], $mdd['AUTOR']));
    $mddPath = S::buildDetailPath('marcha', $mdd['ID_MARCHA'], (string) $mdd['TITULO']);
    $mddFecha = (string) ($mdd['FECHA'] ?? ''); // ya normalizada a 's/f' por Repo::fetchMarcha si no hay año
?>
    <section class="card marcha-dia">
        <div class="shead"><h2>Marcha del día</h2></div>
        <a class="ultima-row" href="<?= V::e($mddPath) ?>">
            <span class="ultima-main">
                <span class="ultima-title"><?= V::e($mdd['TITULO']) ?></span>
                <span class="ultima-authors"><?= V::e($mddAutores) ?></span>
            </span>
<?php if ($mddFecha !== '' && $mddFecha !== 's/f'): ?>
            <span class="ultima-date"><?= V::e($mddFecha) ?></span>
<?php endif; ?>
        </a>
<?php if ($mddYtid !== null): ?>
        <div class="ytembed" data-ytid="<?= V::e($mddYtid) ?>">
            <button type="button" class="ytfacade" aria-label="Reproducir el vídeo (carga YouTube al pulsar)">
                <img class="ytfacade-img" src="<?= V::e(MD::youtubeThumb($mddYtid)) ?>" alt="" loading="lazy" width="480" height="270">
                <span class="ytfacade-play" aria-hidden="true"></span>
            </button>
        </div>
<?php endif; ?>
    </section>
<?php endif; ?>

<?php if (($hubEstilos ?? []) !== [] || ($hubProvincias ?? []) !== [] || ($hubAniosRecientes ?? []) !== []): ?>
    <section>
        <h2 class="section-title">Explorar el catálogo</h2>
        <ul class="vease">
<?php foreach ($hubAniosRecientes as $a): ?>
            <li>→ <a href="<?= V::e(P::anioHubPath($a['K'])) ?>">Marchas de <?= V::e((string) $a['K']) ?></a> <span class="cnt">(<?= $num($a['N']) ?> registros)</span></li>
<?php endforeach; ?>
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
