<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,mixed> $b */
?>
<div>
    <div class="headDetail"><?= V::e($b['NOMBRE_BREVE']) ?></div>
    <div class="tableList">
        <table class="table table-zebra">
            <tbody>
                <tr><th>Nombre completo</th><td><?= V::e($b['NOMBRE_COMPLETO']) ?></td></tr>
                <tr><th>Localidad</th><td><?= V::e($b['LOCALIDAD']) ?></td></tr>
<?php if ((int) $b['FECHA_FUND'] > 1800): ?>
                <tr><th>Fecha de fundación</th><td><?= V::e($b['FECHA_FUND']) ?></td></tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="timeline-wrap"><?= H::timeline($b) ?></div>

<?php if ($b['discosLength'] > 0): ?>
    <div class="divider">Esta banda ha grabado <?= $b['discosLength'] ?> discos:</div>
<?php foreach ($b['discos'] as $d): ?>
    <?= H::cdList($d) ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($b['marchasLength'] > 0): ?>
    <div class="divider">Esta banda ha estrenado <?= $b['marchasLength'] ?> marchas:</div>
<?php endif; ?>
    <div class="tableList">
        <table class="table table-zebra">
            <thead class="thead-neutral"><tr><td>Marcha</td><td>Fecha</td><td>Compositor/es</td></tr></thead>
            <tbody>
<?php foreach ($b['marchas'] as $m): ?>
                <tr>
                    <td><a href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>"><?= V::e($m['TITULO']) ?></a></td>
                    <td><?= V::e($m['FECHA']) ?></td>
                    <td>
<?php foreach ($m['AUTOR'] as $a): ?>
                        <div><a href="<?= V::e(S::buildDetailPath('autor', $a['autorId'], (string) $a['nombre'])) ?>"><?= V::e($a['nombre']) ?></a></div>
<?php endforeach; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
