<?php use App\View as V; use App\Slug as S; use App\Html as H;
/** @var array<string,string> $criteria @var array|null $result @var int $page @var int $limit */
$val = static fn(string $k): string => V::e($criteria[$k] ?? '');
?>
<div class="stack list-page">
    <form class="panel" action="/marcha" method="GET">
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="titulo">Título</label>
                <input id="titulo" class="input" type="text" name="titulo" value="<?= $val('titulo') ?>" placeholder="Consuelo Gitano…">
            </div>
            <div class="field">
                <label class="field-label">Fecha</label>
                <div class="row">
                    <input class="input" type="text" name="fechaDesde" value="<?= $val('fechaDesde') ?>" maxlength="4" placeholder="Desde">
                    <input class="input" type="text" name="fechaHasta" value="<?= $val('fechaHasta') ?>" maxlength="4" placeholder="Hasta">
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="dedicatoria">Dedicatoria</label>
                <input id="dedicatoria" class="input" type="text" name="dedicatoria" value="<?= $val('dedicatoria') ?>" placeholder="Hdad Cristo de la Corona…">
            </div>
            <div class="field">
                <label class="field-label" for="localidad">Localidad</label>
                <input id="localidad" class="input" type="text" name="localidad" value="<?= $val('localidad') ?>" placeholder="Osuna…">
            </div>
            <div class="field">
                <label class="field-label" for="provincia">Provincia</label>
                <input id="provincia" class="input" type="text" name="provincia" value="<?= $val('provincia') ?>" placeholder="Almería…">
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
    echo 'No se han encontrado marchas.';
} else {
    echo $total . ' ' . ($total === 1 ? 'marcha encontrada' : 'marchas encontradas');
    if ($total > $limit) echo ' — página ' . $page . ' de ' . (int) ceil($total / $limit);
}
?>
        </p>
<?php if ($result['totalRows'] > 0): ?>
        <div class="tableList">
            <table class="table table-zebra table-sm">
                <thead><tr><th>Título</th><th>Compositor/es</th><th>Año</th></tr></thead>
                <tbody>
<?php foreach ($result['data'] as $m): ?>
                    <tr>
                        <td><a href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>"><?= V::e($m['TITULO']) ?></a></td>
                        <td class="small">
<?php foreach ($m['AUTOR'] as $a): ?>
                            <div><a href="<?= V::e(S::buildDetailPath('autor', $a['autorId'], (string) $a['nombre'])) ?>"><?= V::e($a['nombre']) ?></a></div>
<?php endforeach; ?>
                        </td>
                        <td class="small nums"><?= V::e($m['FECHA']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= H::pagination($page, $result['totalRows'], $limit, '/marcha', $criteria) ?>
<?php endif; ?>
    </section>
<?php endif; ?>
</div>
