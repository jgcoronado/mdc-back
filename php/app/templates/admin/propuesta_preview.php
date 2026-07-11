<?php use App\View as V; use App\Auth;
/** @var array $session @var string $entidad @var string $accion @var int|null $targetId
 *  @var array<string,mixed> $datos @var list<int> $autoresIds
 *  @var list<array<string,mixed>> $authors @var string|null $bandaNombre @var string $formAction */
$csrf = Auth::csrfToken($session);
$entLabel = ['marcha' => 'Marcha', 'banda' => 'Banda', 'autor' => 'Compositor'][$entidad] ?? $entidad;
$accLabel = $accion === 'add' ? 'Alta' : 'Edición';
?>
<div class="stack admin-form">
    <div class="admin-bar">
        <h1>Previsualizar propuesta</h1>
        <div class="row">
            <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
        </div>
    </div>

    <div class="alert alert-info">
        Así quedará la ficha (<strong><?= V::e($entLabel) ?> · <?= V::e($accLabel) ?><?= $targetId !== null ? ' #' . (int) $targetId : '' ?></strong>).
        Revísala: si es correcta, <strong>envíala</strong>; si no, vuelve a editar. Aún no se ha enviado nada.
    </div>

    <div class="panel"><?= V::capture('admin/_ficha_preview', ['entidad' => $entidad, 'datos' => $datos, 'authors' => $authors, 'bandaNombre' => $bandaNombre]) ?></div>

    <form class="panel" action="<?= V::e($formAction) ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= V::e($csrf) ?>">
        <input type="hidden" name="accion" value="enviar">
<?php foreach ($datos as $k => $val): ?>
        <input type="hidden" name="<?= V::e((string) $k) ?>" value="<?= V::e((string) $val) ?>">
<?php endforeach; ?>
<?php foreach ($autoresIds as $aid): ?>
        <input type="hidden" name="autoresIds[]" value="<?= (int) $aid ?>">
<?php endforeach; ?>
        <div class="row">
            <button class="btn btn-neutral" type="submit">Enviar propuesta</button>
            <a class="btn btn-ghost" href="<?= V::e($formAction) ?>" onclick="if(history.length>1){history.back();return false;}">← Seguir editando</a>
        </div>
    </form>
</div>
