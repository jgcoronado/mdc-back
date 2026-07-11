<?php use App\View as V; use App\Auth; use App\Slug as S;
/** @var string $mode @var array $session @var string $action
 *  @var array<string,mixed> $marcha @var list<array{ID_AUTOR:int,NOMBRE_COMPLETO:string}> $authors
 *  @var bool $proposalMode @var array|null $notice @var string|null $error */
$csrf = Auth::csrfToken($session);
$isEdit = $mode === 'edit';
$proposalMode = $proposalMode ?? false;
$val = static fn(string $k): string => V::e($marcha[$k] ?? '');

$fields = [
    ['TITULO', 'Título', 'text'],
    ['FECHA', 'Fecha (año de 4 dígitos)', 'text'],
    ['DEDICATORIA', 'Dedicatoria', 'text'],
    ['LOCALIDAD', 'Localidad', 'text'],
    ['PROVINCIA', 'Provincia', 'text'],
];
if ($isEdit) $fields[] = ['AUDIO', 'Audio (URL)', 'text'];
$fields[] = ['BANDA_ESTRENO', 'ID de la banda de estreno', 'number'];
$estilo = (string) ($marcha['ESTILO'] ?? '');
?>
<div class="stack admin-form">
    <div class="admin-bar">
        <h1><?= $isEdit ? 'Editar marcha #' . V::e($marcha['ID_MARCHA']) : 'Añadir marcha' ?></h1>
        <div class="row">
<?php if ($isEdit): ?>
            <a class="btn btn-sm btn-ghost" href="<?= V::e(S::buildDetailPath('marcha', $marcha['ID_MARCHA'], (string) ($marcha['TITULO'] ?? ''))) ?>" target="_blank">Ver ↗</a>
<?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
        </div>
    </div>

<?php if ($error): ?><div class="alert alert-error">Error: <?= V::e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>
<?php if ($proposalMode): ?><div class="alert alert-info">Verás una <strong>previsualización</strong> antes de enviar. Tu propuesta la revisará un administrador; no se guarda directamente en la base de datos.</div><?php endif; ?>

    <form class="panel" action="<?= V::e($action) ?>" method="POST" id="marchaForm">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
<?php foreach ($fields as [$key, $label, $type]): ?>
        <div class="field">
            <label class="field-label" for="<?= $key ?>"><?= $label ?></label>
            <input class="input" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" value="<?= $val($key) ?>">
        </div>
<?php endforeach; ?>
        <div class="field">
            <label class="field-label" for="ESTILO">Estilo</label>
            <select class="input" id="ESTILO" name="ESTILO">
                <option value="" <?= $estilo === '' ? 'selected' : '' ?>>— Sin asignar —</option>
                <option value="CCTT" <?= $estilo === 'CCTT' ? 'selected' : '' ?>>Cornetas y Tambores (CCTT)</option>
                <option value="AM" <?= $estilo === 'AM' ? 'selected' : '' ?>>Agrupación Musical (AM)</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="DETALLES_MARCHA">Detalles</label>
            <textarea class="input" id="DETALLES_MARCHA" name="DETALLES_MARCHA" rows="4"><?= $val('DETALLES_MARCHA') ?></textarea>
        </div>

        <div class="field">
            <label class="field-label">Autor(es)</label>
            <div id="autoresBox" class="chips">
<?php foreach ($authors as $a): ?>
                <span class="chip" data-id="<?= (int) $a['ID_AUTOR'] ?>">
                    <input type="hidden" name="autoresIds[]" value="<?= (int) $a['ID_AUTOR'] ?>">
                    <span><?= V::e($a['NOMBRE_COMPLETO']) ?></span>
                    <button type="button" class="chip-x" aria-label="Quitar">×</button>
                </span>
<?php endforeach; ?>
            </div>
            <div class="autocomplete">
                <input class="input" id="autorSearch" type="text" placeholder="Buscar compositor (mín. 3 caracteres)…" autocomplete="off">
                <div id="autorSuggest" class="suggest" hidden></div>
            </div>
            <p class="muted">Debe haber al menos un autor.</p>
        </div>

        <div><button class="btn btn-neutral" type="submit"><?= $proposalMode ? 'Previsualizar propuesta' : ($isEdit ? 'Guardar cambios' : 'Crear marcha') ?></button></div>
    </form>
</div>
<script src="/assets/admin.js" defer></script>
