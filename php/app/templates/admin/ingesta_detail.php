<?php use App\View as V; use App\Auth;
/** @var array $session @var array<string,mixed> $cand @var list<string> $autoresSugeridos
 *  @var array|null $notice @var string|null $error */
$csrf = Auth::csrfToken($session);
$val = static fn(string $k, string $default = ''): string => V::e($cand[$k] ?? $default);
$flags = $cand['FLAGS'] ? (json_decode((string) $cand['FLAGS'], true) ?: []) : [];

$fields = [
    ['TITULO', 'Título', 'text', $cand['P_TITULO'] ?? ''],
    ['FECHA', 'Fecha (año de 4 dígitos)', 'text', $cand['P_FECHA'] ?? ''],
    ['DEDICATORIA', 'Dedicatoria', 'text', $cand['P_DEDICATORIA'] ?? ''],
    ['LOCALIDAD', 'Localidad', 'text', $cand['P_LOCALIDAD'] ?? ''],
    ['PROVINCIA', 'Provincia', 'text', $cand['P_PROVINCIA'] ?? ''],
    ['BANDA_ESTRENO', 'ID de la banda de estreno', 'number', $cand['P_BANDA_ESTRENO'] ?? $cand['ID_BANDA'] ?? ''],
];
?>
<div class="stack admin-form">
    <div class="admin-bar">
        <h1>Revisar candidato #<?= (int) $cand['ID_CAND'] ?></h1>
        <div class="row">
            <a class="btn btn-sm btn-ghost" href="<?= V::e($cand['VIDEO_URL']) ?>" target="_blank">Vídeo original ↗</a>
            <a class="btn btn-sm btn-ghost" href="/dashboard/ingesta">← Volver</a>
        </div>
    </div>

<?php if ($error): ?><div class="alert alert-error">Error: <?= V::e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<?php if ($cand['ESTADO'] !== 'pendiente'): ?>
    <div class="alert alert-info">Este candidato ya está <strong><?= V::e($cand['ESTADO']) ?></strong> y no admite más acciones.</div>
<?php endif; ?>

<?php if (!empty($cand['MATCH_MARCHA_ID'])): ?>
    <div class="alert alert-error">
        ⚠ Posible coincidencia con una marcha ya existente:
        <a href="/dashboard/marcha/<?= (int) $cand['MATCH_MARCHA_ID'] ?>" target="_blank"><?= V::e($cand['MATCH_TITULO']) ?> (#<?= (int) $cand['MATCH_MARCHA_ID'] ?>)</a>
        — similitud <?= (int) round(((float) $cand['MATCH_SCORE']) * 100) ?>%. Revisa antes de aceptar.
    </div>
<?php endif; ?>

    <div class="panel">
        <div class="row" style="flex-wrap:wrap;gap:1rem">
            <div style="flex:1;min-width:280px">
                <iframe width="100%" height="220" style="border-radius:var(--radius-sm);border:1px solid var(--border)"
                        src="https://www.youtube.com/embed/<?= V::e($cand['VIDEO_ID']) ?>"
                        title="Vídeo de YouTube" frameborder="0" allowfullscreen></iframe>
            </div>
            <div style="flex:1;min-width:280px" class="stack">
                <p><strong>Título original:</strong> <?= V::e($cand['VIDEO_TITULO']) ?></p>
                <p class="small muted">Publicado: <?= V::e($cand['PUBLICADO_AT']) ?> ·
                    Duración: <?= $cand['DURACION_SEG'] ? gmdate('i:s', (int) $cand['DURACION_SEG']) : '—' ?> ·
                    Clasificación: <span class="badge badge-<?= V::e($cand['CLASIFICACION']) ?>"><?= V::e($cand['CLASIFICACION']) ?></span> ·
                    Confianza: <?= (int) round(((float) $cand['CONFIANZA']) * 100) ?>%
                </p>
<?php if ($flags): ?>
                <p class="small">Revisar: <?= V::e(implode(', ', $flags)) ?></p>
<?php endif; ?>
                <details>
                    <summary class="small muted" style="cursor:pointer">Ver descripción original del vídeo</summary>
                    <p class="small" style="white-space:pre-wrap"><?= V::e($cand['VIDEO_DESC']) ?></p>
                </details>
            </div>
        </div>
    </div>

<?php if ($cand['ESTADO'] === 'pendiente'): ?>
    <form class="panel" action="/dashboard/ingesta/<?= (int) $cand['ID_CAND'] ?>/aceptar" method="POST" id="aceptarForm">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
<?php foreach ($fields as [$key, $label, $type, $default]): ?>
        <div class="field">
            <label class="field-label" for="<?= $key ?>"><?= $label ?></label>
            <input class="input" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" value="<?= V::e($default) ?>">
        </div>
<?php endforeach; ?>
        <div class="field">
            <label class="field-label" for="DETALLES_MARCHA">Detalles</label>
            <textarea class="input" id="DETALLES_MARCHA" name="DETALLES_MARCHA" rows="3"></textarea>
        </div>

        <div class="field">
            <label class="field-label">Autor(es)</label>
<?php if ($autoresSugeridos): ?>
            <p class="small muted">
                Sugeridos por el vídeo:
<?php foreach ($autoresSugeridos as $nombre): ?>
                <button type="button" class="btn btn-sm btn-ghost sugerido-autor" data-nombre="<?= V::e($nombre) ?>"><?= V::e($nombre) ?></button>
                <a class="small" href="/dashboard/autor/add?nombre=<?= rawurlencode($nombre) ?>" target="_blank">(＋ crear)</a>
<?php endforeach; ?>
            </p>
<?php endif; ?>
            <div id="autoresBox" class="chips"></div>
            <div class="autocomplete">
                <input class="input" id="autorSearch" type="text" placeholder="Buscar compositor (mín. 3 caracteres)…" autocomplete="off">
                <div id="autorSuggest" class="suggest" hidden></div>
            </div>
            <p class="muted">Debe haber al menos un autor. Si no existe, créalo con el enlace "＋ crear" y vuelve a buscarlo aquí.</p>
        </div>

        <div class="row">
            <button class="btn btn-neutral" type="submit">Aceptar y crear marcha</button>
        </div>
    </form>

    <form class="panel" action="/dashboard/ingesta/<?= (int) $cand['ID_CAND'] ?>/descartar" method="POST">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
        <div class="field">
            <label class="field-label" for="motivo">Motivo del descarte (opcional)</label>
            <input class="input" id="motivo" name="motivo" type="text" placeholder="p.ej. no es una marcha nueva, es un cover…">
        </div>
        <div><button class="btn btn-sm btn-danger" type="submit">Descartar candidato</button></div>
    </form>
<?php endif; ?>
</div>
<script src="/assets/admin.js" defer></script>
<script>
document.querySelectorAll('.sugerido-autor').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var search = document.getElementById('autorSearch');
        search.value = btn.dataset.nombre;
        search.dispatchEvent(new Event('input'));
        search.focus();
    });
});
</script>
