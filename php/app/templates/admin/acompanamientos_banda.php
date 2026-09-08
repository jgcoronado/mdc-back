<?php use App\View as V; use App\Auth; use App\Slug as S;
/** @var array $session @var array<string,mixed> $banda
 *  @var list<array<string,mixed>> $filas
 *  @var list<array{LOCALIDAD:string,N:int}> $localidades
 *  @var array|null $notice */
$csrf = Auth::csrfToken($session);
$idBanda = (int) $banda['ID_BANDA'];
$nombre  = (string) ($banda['NOMBRE_BREVE'] ?? ('#' . $idBanda));
?>
<div class="crumbs">
    <span><a href="/dashboard">Panel</a> › <a href="/dashboard/acompanamientos">Acompañamientos</a> › <?= V::e($nombre) ?></span>
    <span class="regnav"><a href="/dashboard/banda/<?= $idBanda ?>">Editar banda</a></span>
</div>

<h1>Acompañamientos de <?= V::e($nombre) ?></h1>
<p class="muted"><?= V::e((string) ($banda['NOMBRE_COMPLETO'] ?? '')) ?><?php if (!empty($banda['LOCALIDAD'])): ?> · <?= V::e((string) $banda['LOCALIDAD']) ?><?php endif; ?> · <a href="<?= V::e(S::buildDetailPath('banda', $idBanda, $nombre)) ?>">ficha pública</a></p>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<section>
    <h2 class="section-title">Añadir acompañamiento</h2>
    <?php /* Orden del formulario = orden de trabajo: primero la localidad (acota
             el predictivo de hermandades a esa Semana Santa), después la
             hermandad, y por último la vigencia. */ ?>
    <form class="panel" method="POST" action="/dashboard/acompanamientos/banda/<?= $idBanda ?>/add" id="acAltaForm">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">

        <div class="field">
            <label class="field-label" for="LOCALIDAD">Localidad del acompañamiento</label>
            <input class="input" id="LOCALIDAD" name="LOCALIDAD" type="text" list="acLocalidades"
                   placeholder="p. ej. Granada" autocomplete="off" required>
            <datalist id="acLocalidades">
<?php foreach ($localidades as $l): if ((string) $l['LOCALIDAD'] === '') continue; ?>
                <option value="<?= V::e((string) $l['LOCALIDAD']) ?>"></option>
<?php endforeach; ?>
            </datalist>
            <p class="muted small">La de la <strong>Semana Santa</strong> de la que sale el acompañamiento, no la sede de la banda.</p>
        </div>

        <div class="field">
            <label class="field-label" for="HERMANDAD">Hermandad</label>
            <div class="autocomplete">
                <input class="input" id="HERMANDAD" name="HERMANDAD" type="text"
                       placeholder="Buscar entre las ya cargadas, o escribirla nueva…" autocomplete="off" required
                       data-acomp-hermandad-search data-acomp-localidad="LOCALIDAD">
                <div class="suggest" id="acHermandadSuggest" hidden></div>
            </div>
            <p class="muted small">Texto libre (aún no existe la entidad hermandad): si ya está cargada, elígela del predictivo para que se escriba igual que las demás.</p>
        </div>

        <div class="field">
            <label class="field-label" for="TITULAR">Paso / titular (opcional)</label>
            <input class="input" id="TITULAR" name="TITULAR" type="text" placeholder="p. ej. Cruz de Guía, Paso de Misterio, Palio">
        </div>

        <div class="row acomp-row-anios">
            <div class="field">
                <label class="field-label" for="ANIO">Año de inicio</label>
                <input class="input acomp-anio" id="ANIO" name="ANIO" type="number" min="1900" max="2100" step="1"
                       value="<?= (int) date('Y') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="ANIO_FIN">Año de fin (opcional)</label>
                <input class="input acomp-anio" id="ANIO_FIN" name="ANIO_FIN" type="number" min="1900" max="2100" step="1"
                       placeholder="vigente">
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="FUENTE">Fuente (opcional, se muestra público)</label>
            <input class="input" id="FUENTE" name="FUENTE" type="text" placeholder="URL del anuncio">
        </div>

        <div class="field">
            <label class="field-label" for="NOTA">Nota interna (opcional, NO se muestra público)</label>
            <textarea class="input" id="NOTA" name="NOTA" rows="2"></textarea>
        </div>

        <div><button class="btn btn-neutral" type="submit">Añadir acompañamiento</button></div>
    </form>
</section>

<section>
    <h2 class="section-title">Acompañamientos registrados (<?= count($filas) ?>)</h2>
<?php if ($filas): ?>
    <div class="acomp-list">
<?php foreach ($filas as $c): $cid = (int) $c['ID_CONTRATO']; $titular = trim((string) ($c['TITULAR'] ?? '')); $loc = (string) $c['LOCALIDAD']; ?>
        <div class="acomp-item">
            <div class="acomp-item-head">
                <strong><?= V::e((string) $c['HERMANDAD']) ?></strong>
                <span class="muted small"><?= $titular !== '' ? V::e($titular) : '—' ?></span>
                <span class="chip"><?= V::e($loc !== '' ? $loc : 'sin localidad') ?></span>
            </div>
            <form class="acomp-row" method="POST" action="/dashboard/acompanamientos/banda/<?= $idBanda ?>/<?= $cid ?>">
                <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
                <input type="hidden" name="LOCALIDAD" value="<?= V::e($loc) ?>">
                <div class="acomp-field acomp-field-anio">
                    <label class="field-label small" for="banio-<?= $cid ?>">Desde</label>
                    <input class="input input-sm" id="banio-<?= $cid ?>" type="number" name="ANIO"
                           min="1900" max="2100" step="1" value="<?= (int) $c['ANIO'] ?>" required>
                </div>
                <div class="acomp-field acomp-field-anio">
                    <label class="field-label small" for="bfin-<?= $cid ?>">Hasta</label>
                    <input class="input input-sm" id="bfin-<?= $cid ?>" type="number" name="ANIO_FIN"
                           min="1900" max="2100" step="1" placeholder="vigente"
                           value="<?= $c['ANIO_FIN'] !== null ? (int) $c['ANIO_FIN'] : '' ?>">
                </div>
                <div class="acomp-field acomp-field-acciones">
                    <button class="btn btn-sm btn-neutral" type="submit">Guardar</button>
                    <button class="btn btn-sm btn-ghost" type="submit" name="borrar" value="1"
                            onclick="return confirm('¿Eliminar este acompañamiento?');">Borrar</button>
                </div>
            </form>
        </div>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="muted">Esta banda todavía no tiene acompañamientos registrados.</p>
<?php endif; ?>
</section>

<script src="/assets/acompanamientos.js" defer></script>
