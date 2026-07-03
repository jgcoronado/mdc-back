<?php use App\View as V; use App\Slug as S;
/** @var array<string,mixed> $a @var string $fullName */
$t = static fn($v): bool => !($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === false);
?>
<div>
    <div class="headDetail"><?= V::e($fullName) ?></div>
    <div class="tableList">
        <table class="table table-zebra">
            <tbody>
<?php if ($t($a['F_NAC'])): ?>
                <tr><th>Fecha de nacimiento</th><td><?= V::e($a['F_NAC']) ?></td></tr>
<?php endif; ?>
<?php if ($t($a['LUGAR_NAC'])): ?>
                <tr><th>Lugar de nacimiento</th><td><?= V::e($a['LUGAR_NAC']) ?></td></tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="divider">Ha compuesto <?= $a['marchasLength'] ?> marchas:</div>
    <div class="tableList">
        <table class="table table-zebra">
            <thead class="thead-neutral"><tr><td>Marcha</td><td>Fecha</td></tr></thead>
            <tbody>
<?php foreach ($a['marchas'] as $m): ?>
                <tr>
                    <td><a href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>"><?= V::e($m['TITULO']) ?></a></td>
                    <td><?= V::e($m['FECHA']) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
