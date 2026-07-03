<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,mixed> $m */
// "truthy" al estilo JS: null, '', 0, 0.0 y false son falsos; '0' (string) es verdadero.
$t = static fn($v): bool => !($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === false);
$getDedicatoria = static function ($ded, $loc): string {
    $isDed = $ded && $ded !== '0';
    $isLoc = $loc && $loc !== '0';
    if ($isDed && $isLoc) return "$ded ($loc)";
    if ($isDed) return (string) $ded;
    return '';
};
?>
<div>
    <div class="headDetail"><?= V::e($m['TITULO']) ?></div>
    <div class="tableList">
        <table class="table table-zebra">
            <tbody>
<?php if ($t($m['FECHA'])): ?>
                <tr><th>Fecha</th><td><?= V::e($m['FECHA']) ?></td></tr>
<?php endif; ?>
                <tr>
                    <th>Autor</th>
                    <td>
<?php foreach ($m['AUTOR'] as $a): ?>
                        <div><a href="<?= V::e(S::buildDetailPath('autor', $a['autorId'], (string) $a['nombre'])) ?>"><?= V::e($a['nombre']) ?></a></div>
<?php endforeach; ?>
                    </td>
                </tr>
<?php if ($t($m['DEDICATORIA'])): ?>
                <tr><th>Dedicatoria</th><td><?= V::e($getDedicatoria($m['DEDICATORIA'], $m['LOCALIDAD'])) ?></td></tr>
<?php endif; ?>
<?php if ($t($m['BANDA_ESTRENO'])): ?>
                <tr><th>Estrenada por</th><td><a href="<?= V::e(S::buildDetailPath('banda', $m['BANDA_ESTRENO'], (string) $m['BANDA'])) ?>"><?= V::e($m['BANDA']) ?></a></td></tr>
<?php endif; ?>
<?php if ($t($m['DETALLES_MARCHA'])): ?>
                <tr><th>Información adicional</th><td><?= V::e($m['DETALLES_MARCHA']) ?></td></tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

<?php if ($m['discosLength'] !== 0): ?>
    <div class="divider">Esta marcha se ha grabado en <?= $m['discosLength'] ?> discos:</div>
<?php else: ?>
    <div class="divider">Esta marcha aún no ha sido grabada en disco.</div>
<?php endif; ?>

<?php foreach ($m['discos'] as $d): ?>
    <?= H::cdList($d) ?>
<?php endforeach; ?>
</div>
