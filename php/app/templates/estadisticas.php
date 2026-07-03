<?php use App\View as V; use App\Slug as S;
/** @var list<array<string,mixed>> $masAutor, $masDedica, $masEstreno, $masGrabada */
?>
<div class="stack">
    <details class="collapse">
        <summary class="collapse-title">Autores que más marchas han compuesto</summary>
        <div class="collapse-content">
            <div class="tableList">
                <table class="table table-zebra">
                    <thead class="thead-neutral"><tr><td>Nombre</td><td>Marchas compuestas</td></tr></thead>
                    <tbody>
<?php foreach ($masAutor as $a): ?>
                        <tr>
                            <td><a href="<?= V::e(S::buildDetailPath('autor', $a['ID_AUTOR'], (string) $a['AUTOR'])) ?>"><?= V::e($a['AUTOR']) ?></a></td>
                            <td><?= V::e($a['MARCHAS']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <details class="collapse">
        <summary class="collapse-title">Hermandades con más marchas dedicadas</summary>
        <div class="collapse-content">
            <div class="tableList">
                <table class="table table-zebra">
                    <thead class="thead-neutral"><tr><td>Nombre</td><td>Marchas dedicadas</td></tr></thead>
                    <tbody>
<?php foreach ($masDedica as $d): ?>
                        <tr><td><?= V::e($d['LUGAR']) ?></td><td><?= V::e($d['CUENTA']) ?></td></tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <details class="collapse">
        <summary class="collapse-title">Bandas que más marchas han estrenado</summary>
        <div class="collapse-content">
            <div class="tableList">
                <table class="table table-zebra">
                    <thead class="thead-neutral"><tr><td>Banda</td><td>Marchas estrenadas</td></tr></thead>
                    <tbody>
<?php foreach ($masEstreno as $e): ?>
                        <tr>
                            <td><a href="<?= V::e(S::buildDetailPath('banda', $e['ID_BANDA'], (string) $e['BANDA'])) ?>"><?= V::e($e['BANDA']) ?></a></td>
                            <td><?= V::e($e['MARCHAS']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <details class="collapse">
        <summary class="collapse-title">Marchas que más veces han sido grabadas</summary>
        <div class="collapse-content">
            <div class="tableList">
                <table class="table table-zebra">
                    <thead class="thead-neutral"><tr><td>Marcha</td><td>Autor/es</td><td>Grabaciones</td></tr></thead>
                    <tbody>
<?php foreach ($masGrabada as $g): ?>
                        <tr>
                            <td><a href="<?= V::e(S::buildDetailPath('marcha', $g['ID_MARCHA'], (string) $g['TITULO'])) ?>"><?= V::e($g['TITULO']) ?></a></td>
                            <td>
<?php foreach ($g['AUTOR'] as $a): ?>
                                <div><a href="<?= V::e(S::buildDetailPath('autor', $a['autorId'], (string) $a['nombre'])) ?>"><?= V::e($a['nombre']) ?></a></div>
<?php endforeach; ?>
                            </td>
                            <td><?= V::e($g['GRABACIONES']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
</div>
