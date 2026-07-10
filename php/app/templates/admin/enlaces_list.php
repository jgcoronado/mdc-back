<?php use App\View as V; use App\Html as H; use App\EnlaceRepo; use App\Auth;
/** @var array $session @var array{estado:string,servicio:string,confianza:string,banda:string} $filters
 *  @var int $page @var array{rowsReturned:int,totalRows:int,data:list<array<string,mixed>>} $result
 *  @var list<array{ID_BANDA:int,NOMBRE_BREVE:?string,LOCALIDAD:?string}> $bandas
 *  @var array<string,int> $counts @var string $backQs */
$limit = 40;
$estadoLabels = ['pendiente' => 'Pendientes', 'aprobado' => 'Aprobados', 'rechazado' => 'Rechazados'];
$confBadge = ['ALTA' => 'badge-estreno', 'MEDIA' => 'badge-novedad', 'BAJA' => 'badge-recuperacion', 'SIN_MATCH' => 'badge-warn'];
$csrf = Auth::csrfToken($session);
$refAttr = $backQs !== '' ? '?ref=' . rawurlencode($backQs) : '';
$puedeRechazarMultiple = $filters['estado'] === 'pendiente' && $result['data'];
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Enlaces de streaming</h1>
        <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
    </div>

<?php if (isset($_GET['aprobado'])): ?>
    <div class="alert alert-success">Enlace publicado.</div>
<?php endif; ?>
<?php if (isset($_GET['rechazado'])): ?>
    <div class="alert alert-info">Candidato rechazado.</div>
<?php endif; ?>
<?php if (isset($_GET['rechazados'])): ?>
    <div class="alert alert-info"><?= (int) $_GET['rechazados'] ?> candidato(s) rechazado(s).</div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-error">Error: <?= V::e(preg_replace('/[^A-Z_]/', '', (string) $_GET['err'])) ?></div>
<?php endif; ?>

    <div class="row" style="flex-wrap:wrap;gap:0.5rem">
<?php foreach ($estadoLabels as $key => $label): ?>
        <a class="btn btn-sm <?= $filters['estado'] === $key ? 'btn-neutral' : 'btn-ghost' ?>"
           href="/dashboard/enlaces?estado=<?= $key ?>"><?= $label ?> (<?= $counts[$key] ?? 0 ?>)</a>
<?php endforeach; ?>
    </div>

    <form class="panel" action="/dashboard/enlaces" method="GET">
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
                <label class="field-label" for="servicio">Servicio</label>
                <select class="input" id="servicio" name="servicio" onchange="this.form.submit()">
                    <option value="">Todos</option>
<?php foreach (EnlaceRepo::SERVICIOS as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['servicio'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="confianza">Confianza</label>
                <select class="input" id="confianza" name="confianza" onchange="this.form.submit()">
                    <option value="">Todas</option>
<?php foreach (EnlaceRepo::CONFIANZAS as $cf): ?>
                    <option value="<?= $cf ?>" <?= $filters['confianza'] === $cf ? 'selected' : '' ?>><?= ucfirst(strtolower($cf)) ?></option>
<?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

<?php if ($result['data']): ?>
    <form id="bulkForm" method="POST" action="/dashboard/enlaces/rechazar-multiple">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
        <input type="hidden" name="ref" value="<?= V::e($backQs) ?>">
<?php if ($puedeRechazarMultiple): ?>
        <div class="row" style="align-items:center;gap:0.75rem;margin-bottom:0.5rem">
            <button type="button" id="btnRechazarSeleccionados" class="btn btn-sm btn-danger" disabled>Rechazar seleccionados (<span id="numSeleccionados">0</span>)</button>
        </div>
<?php endif; ?>
    <div class="tableList"><table class="table table-zebra table-sm"><tbody>
<?php foreach ($result['data'] as $c):
        $disco = $c['NOMBRE_CD'] ?? ('#' . $c['ID_ENT']);
        $anioDisco = $c['FECHA_CD'] ? preg_replace('/\D.*/', '', (string) $c['FECHA_CD']) : '';
        $score = (int) round(((float) $c['SCORE']) * 100);
?>
        <tr>
<?php if ($puedeRechazarMultiple): ?>
            <td style="width:1.5rem">
                <input type="checkbox" class="enlace-check" name="ids[]" value="<?= (int) $c['ID_CAND'] ?>"
                       data-disco="<?= V::e($disco) ?>" data-servicio="<?= V::e($c['SERVICIO']) ?>">
            </td>
<?php endif; ?>
            <td><span class="badge"><?= V::e(ucfirst((string) $c['SERVICIO'])) ?></span></td>
            <td>
                <strong><?= V::e($disco) ?></strong><?= $anioDisco ? ' <span class="small muted">(' . V::e($anioDisco) . ')</span>' : '' ?>
                <div class="small muted"><?= V::e($c['NOMBRE_BREVE'] ?? ('Banda #' . $c['BANDADISCO'])) ?></div>
            </td>
            <td class="small">
<?php if ($c['TITULO_ENC']): ?>
                “<?= V::e($c['TITULO_ENC']) ?>”<?= $c['ANIO_ENC'] ? ' <span class="muted">(' . V::e($c['ANIO_ENC']) . ')</span>' : '' ?>
                <div class="muted"><?= V::e($c['ARTISTA_ENC']) ?></div>
<?php else: ?>
                <span class="muted">— sin coincidencia —</span>
<?php endif; ?>
            </td>
            <td class="small nums"><span class="badge <?= $confBadge[$c['CONFIANZA']] ?? '' ?>"><?= V::e($c['CONFIANZA']) ?></span> <?= $score ?>%</td>
            <td class="small">
<?php if ($c['URL']): ?>
                <a href="<?= V::e($c['URL']) ?>" target="_blank" rel="noopener noreferrer">Escuchar ↗</a>
<?php endif; ?>
            </td>
<?php if ($c['ESTADO'] === 'pendiente'): ?>
            <td style="white-space:nowrap">
<?php if ($c['URL']): ?>
                <form method="POST" action="/dashboard/enlaces/<?= (int) $c['ID_CAND'] ?>/aprobar" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                    <input type="hidden" name="ref" value="<?= V::e($backQs) ?>">
                    <button class="btn btn-sm btn-neutral" type="submit">Aprobar</button>
                </form>
<?php endif; ?>
                <form method="POST" action="/dashboard/enlaces/<?= (int) $c['ID_CAND'] ?>/rechazar" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                    <input type="hidden" name="ref" value="<?= V::e($backQs) ?>">
                    <button class="btn btn-sm btn-ghost" type="submit">Rechazar</button>
                </form>
            </td>
<?php else: ?>
            <td class="small muted"><?= V::e($c['ESTADO']) ?></td>
<?php endif; ?>
        </tr>
<?php endforeach; ?>
    </tbody></table></div>
    </form>
    <?= H::pagination($page, $result['totalRows'], $limit, '/dashboard/enlaces', $filters) ?>
<?php else: ?>
    <p class="muted">No hay candidatos con estos filtros.</p>
<?php endif; ?>

<?php if ($puedeRechazarMultiple): ?>
    <dialog id="dlgRechazar" class="panel">
        <p>Vas a rechazar <strong id="dlgNum">0</strong> candidato(s):</p>
        <ul id="dlgLista" class="stack small" style="max-height:40vh;overflow-y:auto"></ul>
        <div class="row" style="justify-content:flex-end;gap:0.5rem">
            <button type="button" id="btnCancelarRechazo" class="btn btn-sm btn-ghost">Cancelar</button>
            <button type="submit" form="bulkForm" class="btn btn-sm btn-danger">Confirmar rechazo</button>
        </div>
    </dialog>
    <script>
    (function () {
        var checks = function () { return Array.from(document.querySelectorAll('.enlace-check')); };
        var btn = document.getElementById('btnRechazarSeleccionados');
        var num = document.getElementById('numSeleccionados');
        var dlg = document.getElementById('dlgRechazar');
        var dlgNum = document.getElementById('dlgNum');
        var dlgLista = document.getElementById('dlgLista');
        var btnCancelar = document.getElementById('btnCancelarRechazo');

        function actualizar() {
            var n = checks().filter(function (c) { return c.checked; }).length;
            num.textContent = n;
            btn.disabled = n === 0;
        }
        checks().forEach(function (c) { c.addEventListener('change', actualizar); });
        btn.addEventListener('click', function () {
            var sel = checks().filter(function (c) { return c.checked; });
            dlgNum.textContent = sel.length;
            dlgLista.innerHTML = '';
            sel.forEach(function (c) {
                var li = document.createElement('li');
                li.textContent = c.dataset.disco + ' — ' + c.dataset.servicio;
                dlgLista.appendChild(li);
            });
            dlg.showModal();
        });
        btnCancelar.addEventListener('click', function () { dlg.close(); });
    })();
    </script>
<?php endif; ?>
</div>
