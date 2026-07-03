<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,mixed> $d */
$coverSrc = '/cover/' . $d['ID_DISCO'] . '.png';
$bandaPath = S::buildDetailPath('banda', $d['ID_BANDA'], (string) $d['BANDA']);
$multi = (int) $d['DISCOS'] > 1;
?>
<div class="disco-detail">
    <div class="disco-head">
        <figure class="disco-cover">
            <?= H::cover($coverSrc, "Portada del disco '" . $d['NOMBRE_CD'] . "'", 'cover-large') ?>
        </figure>
        <div class="disco-meta">
            <div class="headDetail"><?= V::e($d['NOMBRE_CD']) ?></div>
            <div class="tableList">
                <table class="table table-zebra">
                    <tbody>
                        <tr><th>Fecha</th><td><?= V::e($d['FECHA_CD']) ?></td></tr>
                        <tr><th>Banda</th><td><a href="<?= V::e($bandaPath) ?>"><?= V::e($d['BANDA']) ?></a></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="divider">Este disco contiene <?= $d['marchasLength'] ?> marchas:</div>
    <div class="tableList">
        <table class="table table-zebra">
            <thead class="thead-neutral">
                <tr>
<?php if ($multi): ?><td>Disco</td><?php endif; ?>
                    <td>#</td><td>Marcha</td><td>Autor</td><td>Fecha</td>
                </tr>
            </thead>
            <tbody>
<?php foreach ($d['marchas'] as $m): ?>
                <tr>
<?php if ($multi): ?><td><?= V::e($m['N_DISCO']) ?></td><?php endif; ?>
                    <td><?= V::e($m['NUMEROMARCHA']) ?></td>
                    <td><a href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>"><?= V::e($m['TITULO']) ?></a></td>
                    <td>
<?php foreach ($m['AUTOR'] as $a): ?>
                        <div><a href="<?= V::e(S::buildDetailPath('autor', $a['autorId'], (string) $a['nombre'])) ?>"><?= V::e($a['nombre']) ?></a></div>
<?php endforeach; ?>
                    </td>
                    <td><?= V::e($m['FECHA']) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
