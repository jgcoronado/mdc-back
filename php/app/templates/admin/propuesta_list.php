<?php use App\View as V;
/** @var array $session @var list<array<string,mixed>> $items @var array|null $notice */
$entLabel = ['marcha' => 'Marcha', 'banda' => 'Banda', 'autor' => 'Compositor'];
$accLabel = ['add' => 'Alta', 'edit' => 'Edición'];
?>
<div class="stack">
    <div class="admin-bar">
        <h1>Propuestas de editores</h1>
        <div class="row">
            <a class="btn btn-sm btn-ghost" href="/dashboard">← Panel</a>
        </div>
    </div>

<?php if ($notice): ?><div class="alert alert-<?= $notice['type'] === 'ok' ? 'success' : ($notice['type'] === 'error' ? 'error' : 'info') ?>"><?= V::e($notice['msg']) ?></div><?php endif; ?>

<?php if (!$items): ?>
    <p class="muted">No hay propuestas pendientes de revisión.</p>
<?php else: ?>
    <div class="tableList"><table class="table table-zebra table-sm">
        <thead class="thead-neutral"><tr><td>Tipo</td><td>Resumen</td><td>Editor</td><td>Fecha</td><td></td></tr></thead>
        <tbody>
<?php foreach ($items as $it):
        $ent = (string) ($it['entidad'] ?? '');
        $acc = (string) ($it['accion'] ?? '');
        $datos = (array) ($it['datos'] ?? []);
        $titulo = (string) ($datos['TITULO'] ?? $datos['NOMBRE_BREVE'] ?? trim((string) ($datos['NOMBRE'] ?? '') . ' ' . (string) ($datos['APELLIDOS'] ?? '')));
        $target = $it['target_id'] !== null ? ' #' . (int) $it['target_id'] : '';
?>
            <tr>
                <td><span class="chip"><?= V::e($entLabel[$ent] ?? $ent) ?></span> <span class="muted small"><?= V::e($accLabel[$acc] ?? $acc) ?><?= V::e($target) ?></span></td>
                <td><?= $titulo !== '' ? V::e($titulo) : '<span class="muted">(sin título)</span>' ?></td>
                <td class="small"><?= V::e($it['autor'] ?? '') ?></td>
                <td class="small nums"><?= V::e(date('Y-m-d H:i', (int) ($it['creado_ts'] ?? 0))) ?></td>
                <td><a class="btn btn-sm" href="/dashboard/propuesta/<?= V::e($it['id'] ?? '') ?>">Revisar</a></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
</div>
