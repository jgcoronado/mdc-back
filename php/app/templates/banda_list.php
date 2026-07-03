<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,string> $criteria @var array|null $result @var int $page @var int $limit */
$val = static fn(string $k): string => V::e($criteria[$k] ?? '');

$showDate = static function ($fund, $ext): string {
    $funRes = ((int) $fund) > 1800 ? (string) $fund : 's/f';
    $extRes = ($ext === null || (int) $ext === 0) ? '' : ' – ' . $ext;
    return $funRes . $extRes;
};
$showLocalidad = static function ($loc, $prov): string {
    $isLoc = $loc && $loc !== '0';
    $isProv = $prov && $prov !== '0';
    if ($isLoc && $isProv) return "$loc ($prov)";
    if ($isLoc) return (string) $loc;
    return '';
};
?>
<div class="stack list-page">
    <form class="panel" action="/banda" method="GET">
        <div class="form-grid">
            <div class="field col-span-2">
                <label class="field-label" for="titulo">Nombre</label>
                <input id="titulo" class="input" type="text" name="titulo" value="<?= $val('titulo') ?>" placeholder="Sagrada Columna y Azotes…">
            </div>
            <div class="field">
                <label class="field-label" for="localidad">Localidad</label>
                <input id="localidad" class="input" type="text" name="localidad" value="<?= $val('localidad') ?>" placeholder="Palma del Río…">
            </div>
            <div class="field">
                <label class="field-label" for="provincia">Provincia</label>
                <input id="provincia" class="input" type="text" name="provincia" value="<?= $val('provincia') ?>" placeholder="Huelva…">
            </div>
        </div>
        <div class="search-actions">
            <label class="muted" for="limit">Resultados por página</label>
            <select id="limit" name="limit" class="select">
<?php foreach ([10, 20, 50] as $opt): ?>
                <option value="<?= $opt ?>"<?= $opt === $limit ? ' selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-neutral" type="submit">Buscar</button>
        </div>
    </form>

<?php if ($result !== null): ?>
    <section>
        <p class="search-info">
<?php
$total = $result['totalRows'];
if ($total === 0) {
    echo 'No se han encontrado bandas.';
} else {
    echo $total . ' ' . ($total === 1 ? 'banda encontrada' : 'bandas encontradas');
    if ($total > $limit) echo ' — página ' . $page . ' de ' . (int) ceil($total / $limit);
}
?>
        </p>
<?php if ($result['totalRows'] > 0): ?>
        <div class="tableList">
            <table class="table table-zebra table-sm">
                <thead><tr><th>Nombre</th><th>Localidad</th><th>Fundación</th></tr></thead>
                <tbody>
<?php foreach ($result['data'] as $b): ?>
                    <tr>
                        <td><a href="<?= V::e(S::buildDetailPath('banda', $b['ID_BANDA'], (string) $b['NOMBRE_COMPLETO'])) ?>"><?= V::e($b['NOMBRE_COMPLETO']) ?></a></td>
                        <td class="small"><?= V::e($showLocalidad($b['LOCALIDAD'], $b['PROVINCIA'])) ?></td>
                        <td class="small nums"><?= V::e($showDate($b['FECHA_FUND'], $b['FECHA_EXT'])) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= H::pagination($page, $result['totalRows'], $limit, '/banda', $criteria) ?>
<?php endif; ?>
    </section>
<?php endif; ?>
</div>
