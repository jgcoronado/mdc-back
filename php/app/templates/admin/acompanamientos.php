<?php use App\View as V; use App\Auth; use App\Slug as S;
/** Acompañamientos de una localidad — alta en bloque por rango de años (N-04,
 *  rehecho 2026-08-29, reemplaza al panel de /dashboard/temporada por año).
 *  @var array $session @var string $slug @var string $localidad @var bool $esNueva
 *  @var list<array{slug:string,nombre:string,titulares:list<array{titular:?string,
 *       rangos:list<array{anioInicio:int,anioFin:int,idBanda:int,banda:string,actual:bool,contratos:list<int>}>}>}> $hermandades
 *  @var array|null $notice */
$csrf = Auth::csrfToken($session);

// Datalists de ayuda (no obligan a nada, solo reducen el riesgo de escribir la
// misma hermandad/paso de dos formas distintas — HERMANDAD y TITULAR siguen
// siendo texto libre, no hay entidad `hermandad` todavía, ver N-03).
$hermandadesNombres = array_unique(array_column($hermandades, 'nombre'));
sort($hermandadesNombres, SORT_STRING | SORT_FLAG_CASE);
$titularesNombres = [];
foreach ($hermandades as $h) {
    foreach ($h['titulares'] as $t) {
        if ($t['titular'] !== null && $t['titular'] !== 'Sin especificar') {
            $titularesNombres[$t['titular']] = true;
        }
    }
}
$titularesNombres = array_keys($titularesNombres);
sort($titularesNombres, SORT_STRING | SORT_FLAG_CASE);
?>
<div class="crumbs">
    <span><a href="/dashboard">Panel</a> › <a href="/dashboard/acompanamientos">Acompañamientos</a> › <?= V::e($localidad) ?></span>
</div>

<h1>Acompañamientos — <?= V::e($localidad) ?></h1>
<p class="muted">La hermandad y el paso son texto libre: escríbelos igual que ya están cargados (usa el desplegable) para que se agrupen bien en <a href="/acompanamientos/<?= V::e($slug) ?>">la página pública</a>. Un alta cubre todo el rango de años con la misma banda de una vez; los años ya cargados para esa banda+hermandad+paso se saltan sin duplicar.</p>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<section>
    <h2 class="section-title">Añadir acompañamiento (rango de años)</h2>
    <form class="panel" action="/dashboard/acompanamientos/<?= V::e($slug) ?>/add" method="POST" id="contratoForm">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
<?php if ($esNueva): ?>
        <input type="hidden" name="LOCALIDAD" value="<?= V::e($localidad) ?>">
        <p class="muted small">Localidad nueva: <strong><?= V::e($localidad) ?></strong> (se crea al guardar el primer acompañamiento).</p>
<?php endif; ?>

        <div class="field">
            <label class="field-label" for="contratoBandaSearch">Banda</label>
            <input type="hidden" name="ID_BANDA" id="ID_BANDA" value="">
            <div class="autocomplete">
                <input class="input" id="contratoBandaSearch" type="text" placeholder="Buscar banda (mín. 3 caracteres)…" autocomplete="off">
                <div id="contratoBandaSuggest" class="suggest" hidden></div>
            </div>
            <p class="muted small">Seleccionada: <strong id="contratoBandaChosen">(ninguna)</strong></p>
        </div>

        <div class="field">
            <label class="field-label" for="HERMANDAD">Hermandad</label>
            <input class="input" id="HERMANDAD" name="HERMANDAD" type="text" list="hermandadesList" placeholder="p. ej. Hermandad de la Esperanza de Triana" required>
            <datalist id="hermandadesList">
<?php foreach ($hermandadesNombres as $n): ?>
                <option value="<?= V::e($n) ?>">
<?php endforeach; ?>
            </datalist>
        </div>

        <div class="field">
            <label class="field-label" for="TITULAR">Titular / paso (opcional — solo si la hermandad saca más de un paso de Cristo)</label>
            <input class="input" id="TITULAR" name="TITULAR" type="text" list="titularesList" placeholder="p. ej. Cruz de Guía">
            <datalist id="titularesList">
<?php foreach ($titularesNombres as $n): ?>
                <option value="<?= V::e($n) ?>">
<?php endforeach; ?>
            </datalist>
        </div>

        <div class="adv-grid">
            <div class="field">
                <label class="field-label" for="ANIO_INICIO">Año inicio</label>
                <input class="input" id="ANIO_INICIO" name="ANIO_INICIO" type="number" min="1900" max="2100" required>
            </div>
            <div class="field">
                <label class="field-label" for="ANIO_FIN">Año fin (vacío = solo el año de inicio)</label>
                <input class="input" id="ANIO_FIN" name="ANIO_FIN" type="number" min="1900" max="2100">
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="FUENTE">Fuente (opcional, uso interno — no se muestra público)</label>
            <input class="input" id="FUENTE" name="FUENTE" type="text" placeholder="URL del anuncio o del hilo del foro">
        </div>

        <div class="field">
            <label class="field-label" for="NOTA">Nota interna (opcional, NO se muestra público)</label>
            <textarea class="input" id="NOTA" name="NOTA" rows="2"></textarea>
        </div>

        <div><button class="btn btn-neutral" type="submit">Añadir</button></div>
    </form>
</section>

<section>
    <h2 class="section-title">Histórico cargado</h2>
<?php if ($hermandades): ?>
    <div class="tableList"><table class="table table-zebra table-sm" data-contratos-table>
        <thead class="thead-neutral"><tr><td>Hermandad / paso</td><td>Años</td><td>Banda</td><td></td></tr></thead>
        <tbody>
<?php foreach ($hermandades as $h): ?>
<?php foreach ($h['titulares'] as $t): ?>
<?php foreach ($t['rangos'] as $i => $rg): ?>
<?php $idsCsv = implode(',', $rg['contratos']); ?>
            <tr>
                <td>
<?php if ($i === 0): ?>
                    <strong><?= V::e($h['nombre']) ?></strong><?= $t['titular'] !== null ? '<br><span class="muted small">' . V::e($t['titular']) . '</span>' : '' ?>
<?php endif; ?>
                </td>
                <td><?= $rg['anioInicio'] === $rg['anioFin'] ? (int) $rg['anioInicio'] : ((int) $rg['anioInicio'] . '–' . (int) $rg['anioFin']) ?></td>
                <td>
                    <span data-banda-display>
                        <a href="<?= V::e(S::buildDetailPath('banda', $rg['idBanda'], $rg['banda'])) ?>"><?= V::e($rg['banda']) ?></a>
                        <button type="button" class="btn btn-sm btn-ghost" data-editar-banda>Editar</button>
                    </span>
                    <form action="/dashboard/acompanamientos/<?= V::e($slug) ?>/banda-rango" method="POST" class="inline-form" data-banda-edit-form hidden>
                        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                        <input type="hidden" name="ids" value="<?= V::e($idsCsv) ?>">
                        <input type="hidden" name="ID_BANDA" value="<?= (int) $rg['idBanda'] ?>" data-banda-edit-hidden>
                        <div class="autocomplete">
                            <input class="input" type="text" value="<?= V::e($rg['banda']) ?>" placeholder="Buscar banda (mín. 3 caracteres)…" autocomplete="off" data-banda-edit-search>
                            <div class="suggest" data-banda-edit-suggest hidden></div>
                        </div>
                        <button class="btn btn-sm btn-neutral" type="submit">Guardar</button>
                        <button type="button" class="btn btn-sm btn-ghost" data-editar-cancelar>Cancelar</button>
                    </form>
                </td>
                <td>
                    <form action="/dashboard/acompanamientos/<?= V::e($slug) ?>/borrar-rango" method="POST" class="inline-form" onsubmit="return confirm('¿Eliminar <?= count($rg['contratos']) ?> acompañamiento(s) (<?= (int) $rg['anioInicio'] ?>–<?= (int) $rg['anioFin'] ?>)?');">
                        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                        <input type="hidden" name="ids" value="<?= V::e($idsCsv) ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Borrar</button>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endforeach; ?>
        </tbody>
    </table></div>
<?php else: ?>
    <p class="muted">Todavía no hay acompañamientos registrados para <?= V::e($localidad) ?>.</p>
<?php endif; ?>
</section>

<script src="/assets/admin.js" defer></script>
