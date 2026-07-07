<?php use App\View as V; use App\Html as H; use App\IngestaRepo;
/** @var array $session @var array{estado:string,banda:string,clasificacion:string} $filters
 *  @var int $page @var array{rowsReturned:int,totalRows:int,data:list<array<string,mixed>>} $result
 *  @var list<array{ID_BANDA:int,NOMBRE_BREVE:string}> $bandas @var array<string,int> $counts */
$limit = 30;
$estadoLabels = ['pendiente' => 'Pendientes', 'aceptado' => 'Aceptados', 'descartado' => 'Descartados', 'duplicado' => 'Duplicados'];
$claseBadge = ['estreno' => 'badge-estreno', 'novedad' => 'badge-novedad', 'recuperacion' => 'badge-recuperacion'];
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Ingesta desde YouTube</h1>
        <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
    </div>

<?php if (isset($_GET['descartado'])): ?>
    <div class="alert alert-info">Candidato descartado.</div>
<?php endif; ?>
<?php if (isset($_GET['aceptado'])): ?>
    <div class="alert alert-success">Marcha añadida correctamente (<a href="/dashboard/marcha/<?= (int) $_GET['aceptado'] ?>" target="_blank">#<?= (int) $_GET['aceptado'] ?> ↗</a>).</div>
<?php endif; ?>

    <div class="row" style="flex-wrap:wrap;gap:0.5rem">
<?php foreach ($estadoLabels as $key => $label): ?>
        <a class="btn btn-sm <?= $filters['estado'] === $key ? 'btn-neutral' : 'btn-ghost' ?>"
           href="/dashboard/ingesta?estado=<?= $key ?>"><?= $label ?> (<?= $counts[$key] ?? 0 ?>)</a>
<?php endforeach; ?>
    </div>

    <form class="panel" action="/dashboard/ingesta" method="GET">
        <input type="hidden" name="estado" value="<?= V::e($filters['estado']) ?>">
        <div class="row" style="flex-wrap:wrap;gap:0.75rem">
            <div class="field">
                <label class="field-label" for="banda">Banda</label>
                <select class="input" id="banda" name="banda" onchange="this.form.submit()">
                    <option value="">Todas</option>
<?php foreach ($bandas as $b): ?>
                    <option value="<?= (int) $b['ID_BANDA'] ?>" <?= (string) $b['ID_BANDA'] === $filters['banda'] ? 'selected' : '' ?>>
                        <?= V::e($b['NOMBRE_BREVE'] ?? ('Banda #' . $b['ID_BANDA'])) ?>
                    </option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="clasificacion">Clasificación</label>
                <select class="input" id="clasificacion" name="clasificacion" onchange="this.form.submit()">
                    <option value="">Todas</option>
<?php foreach (IngestaRepo::CLASIFICACIONES as $c): ?>
                    <option value="<?= $c ?>" <?= $filters['clasificacion'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
<?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

<?php if ($result['data']): ?>
    <div class="tableList"><table class="table table-zebra table-sm"><tbody>
<?php foreach ($result['data'] as $c): ?>
        <tr>
            <td><span class="badge <?= $claseBadge[$c['CLASIFICACION']] ?? '' ?>"><?= V::e($c['CLASIFICACION']) ?></span></td>
            <td>
                <a href="/dashboard/ingesta/<?= (int) $c['ID_CAND'] ?>"><?= V::e($c['P_TITULO'] ?: $c['VIDEO_TITULO']) ?></a>
<?php if ($c['MATCH_MARCHA_ID']): ?>
                <span class="badge badge-warn" title="Posible coincidencia con una marcha ya existente">⚠ posible duplicado</span>
<?php endif; ?>
            </td>
            <td class="small"><?= V::e($c['NOMBRE_BREVE'] ?? ('Banda #' . $c['ID_BANDA'])) ?></td>
            <td class="small nums"><?= V::e($c['P_FECHA']) ?></td>
            <td class="small nums"><?= (int) round(((float) $c['CONFIANZA']) * 100) ?>%</td>
            <td class="small"><?= V::e($c['ESTADO']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody></table></div>
    <?= H::pagination($page, $result['totalRows'], $limit, '/dashboard/ingesta', $filters) ?>
<?php else: ?>
    <p class="muted">No hay candidatos con estos filtros.</p>
<?php endif; ?>
</div>
