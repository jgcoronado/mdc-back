<?php use App\View as V; use App\Html as H; use App\Auth;
/** @var array $session @var array{estado:string,q:string} $filters
 *  @var int $page @var array{rowsReturned:int,totalRows:int,data:list<array<string,mixed>>} $result
 *  @var array{todos:int,pendiente:int,CCTT:int,AM:int} $counts @var string $backQs @var array|null $notice */
$limit = 50;
$estadoLabels = ['pendiente' => 'Pendientes', 'todos' => 'Todas', 'CCTT' => 'Cornetas y Tambores', 'AM' => 'Agrupación Musical'];
$csrf = Auth::csrfToken($session);
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Estilo de marcha (CCTT / AM)</h1>
        <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
    </div>

<?php if ($notice): ?>
    <div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['asignadas'])): ?>
    <div class="alert alert-success"><?= (int) $_GET['asignadas'] ?> marcha(s) actualizada(s).</div>
<?php endif; ?>

    <div class="row" style="flex-wrap:wrap;gap:0.5rem">
<?php foreach ($estadoLabels as $key => $label): ?>
        <a class="btn btn-sm <?= $filters['estado'] === $key ? 'btn-neutral' : 'btn-ghost' ?>"
           href="/dashboard/estilos?estado=<?= $key ?>"><?= $label ?> (<?= $counts[$key] ?? 0 ?>)</a>
<?php endforeach; ?>
    </div>

    <form class="panel" action="/dashboard/estilos" method="GET">
        <input type="hidden" name="estado" value="<?= V::e($filters['estado']) ?>">
        <div class="field">
            <label class="field-label" for="q">Buscar por título</label>
            <div class="row">
                <input class="input" id="q" name="q" type="text" value="<?= V::e($filters['q']) ?>" placeholder="Título…">
                <button class="btn btn-sm btn-neutral" type="submit">Buscar</button>
            </div>
        </div>
    </form>

<?php if ($result['data']): ?>
    <form id="bulkForm" method="POST" action="/dashboard/estilos/asignar">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
        <input type="hidden" name="ref" value="<?= V::e($backQs) ?>">
        <div class="row" style="align-items:center;gap:0.75rem;margin-bottom:0.5rem">
            <span class="small muted"><span id="numSeleccionadas">0</span> seleccionada(s)</span>
            <button type="submit" name="estilo" value="CCTT" id="btnBulkCCTT" class="btn btn-sm" disabled>Asignar CCTT a seleccionadas</button>
            <button type="submit" name="estilo" value="AM" id="btnBulkAM" class="btn btn-sm" disabled>Asignar AM a seleccionadas</button>
        </div>
    <div class="tableList"><table class="table table-zebra table-sm"><tbody>
<?php foreach ($result['data'] as $m):
    $ctx = $m['BANDA_ESTRENO_NOMBRE'] ?? null;
    $ctxLabel = 'Estreno';
    if ($ctx === null && ($m['PRIMERA_GRAB_BANDA'] ?? null) !== null) {
        $ctx = $m['PRIMERA_GRAB_BANDA'];
        $ctxLabel = '1.ª grabación';
    }
?>
        <tr>
            <td style="width:1.5rem"><input type="checkbox" class="estilo-check" name="ids[]" value="<?= (int) $m['ID_MARCHA'] ?>" form="bulkForm"></td>
            <td><a href="/dashboard/marcha/<?= (int) $m['ID_MARCHA'] ?>" target="_blank">#<?= (int) $m['ID_MARCHA'] ?> · <?= V::e($m['TITULO']) ?></a></td>
            <td class="small nums"><?= V::e($m['FECHA'] ?? '') ?></td>
            <td class="small muted"><?= $ctx !== null ? V::e($ctxLabel . ': ' . $ctx) : '—' ?></td>
            <td class="small">
<?php if ($m['ESTILO']): ?>
                <span class="badge"><?= V::e($m['ESTILO']) ?></span>
<?php else: ?>
                <span class="muted">sin asignar</span>
<?php endif; ?>
            </td>
            <td>
                <form method="POST" action="/dashboard/estilos/asignar" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                    <input type="hidden" name="ref" value="<?= V::e($backQs) ?>">
                    <input type="hidden" name="ids[]" value="<?= (int) $m['ID_MARCHA'] ?>">
                    <button class="btn btn-sm <?= $m['ESTILO'] === 'CCTT' ? 'btn-neutral' : 'btn-ghost' ?>" type="submit" name="estilo" value="CCTT">CCTT</button>
                    <button class="btn btn-sm <?= $m['ESTILO'] === 'AM' ? 'btn-neutral' : 'btn-ghost' ?>" type="submit" name="estilo" value="AM">AM</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody></table></div>
    </form>
    <?= H::pagination($page, $result['totalRows'], $limit, '/dashboard/estilos', $filters) ?>
<?php else: ?>
    <p class="muted">No hay marchas con estos filtros.</p>
<?php endif; ?>

<script>
(function () {
    var checks = function () { return Array.from(document.querySelectorAll('.estilo-check')); };
    var num = document.getElementById('numSeleccionadas');
    var btnCCTT = document.getElementById('btnBulkCCTT');
    var btnAM = document.getElementById('btnBulkAM');
    if (!num || !btnCCTT || !btnAM) return;

    function actualizar() {
        var n = checks().filter(function (c) { return c.checked; }).length;
        num.textContent = n;
        btnCCTT.disabled = n === 0;
        btnAM.disabled = n === 0;
    }
    checks().forEach(function (c) { c.addEventListener('change', actualizar); });
})();
</script>
</div>
