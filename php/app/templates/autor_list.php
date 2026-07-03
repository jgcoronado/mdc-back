<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,string> $criteria @var array|null $result @var int $page @var int $limit */
$val = static fn(string $k): string => V::e($criteria[$k] ?? '');
?>
<div class="stack list-page">
    <form class="panel" action="/autor" method="GET">
        <div class="form-grid">
            <div class="field col-span-2">
                <label class="field-label" for="nombre">Nombre</label>
                <input id="nombre" class="input" type="text" name="nombre" value="<?= $val('nombre') ?>" placeholder="Apellidos o nombre…">
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
    echo 'No se han encontrado compositores.';
} else {
    echo $total . ' ' . ($total === 1 ? 'compositor encontrado' : 'compositores encontrados');
    if ($total > $limit) echo ' — página ' . $page . ' de ' . (int) ceil($total / $limit);
}
?>
        </p>
<?php if ($result['totalRows'] > 0): ?>
        <div class="tableList">
            <table class="table table-zebra table-sm">
                <thead><tr><th>Nombre</th><th>Marchas</th></tr></thead>
                <tbody>
<?php foreach ($result['data'] as $a): ?>
                    <tr>
                        <td><a href="<?= V::e(S::buildDetailPath('autor', $a['ID_AUTOR'], (string) $a['NOMBRE_COMPLETO'])) ?>"><?= V::e($a['NOMBRE_COMPLETO']) ?></a></td>
                        <td class="nums"><?= V::e($a['MARCHAS']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= H::pagination($page, $result['totalRows'], $limit, '/autor', $criteria) ?>
<?php endif; ?>
    </section>
<?php endif; ?>
</div>
