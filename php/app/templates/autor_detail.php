<?php use App\View as V; use App\Slug as S;
/** @var array<string,mixed> $a @var string $fullName @var string|null $url */
$t = static fn($v): bool => !($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === false);
$num = static fn($n): string => number_format((int) $n, 0, ',', '.');

$aid = (int) $a['ID_AUTOR'];
$ap = trim((string) ($a['APELLIDOS'] ?? ''));
$no = trim((string) ($a['NOMBRE'] ?? ''));
$autoridad = ($ap !== '' && $no !== '') ? "$ap, $no" : ($ap !== '' ? $ap : $no);
$nac = (int) ($a['F_NAC'] ?? 0);
$def = (int) ($a['F_DEF'] ?? 0);
$letra = $ap !== '' ? mb_strtoupper(mb_substr($ap, 0, 1)) : '';

// Asiento: "Linares, 1896 — 1970. — Actividad documentada: 1923–1956."
$asiento = [];
$vidaParts = array_filter([
    $t($a['LUGAR_NAC']) ? (string) $a['LUGAR_NAC'] : '',
    $nac > 1000 ? (string) $nac : '',
], static fn($v) => $v !== '');
$vida = implode(', ', $vidaParts);
if ($def > 1000) $vida .= ($vida !== '' ? ' — ' : '† ') . $def;
if ($vida !== '') $asiento[] = V::e($vida);
if ((int) $a['ACT_DESDE'] > 0) {
    $act = $a['ACT_DESDE'] === $a['ACT_HASTA'] ? (string) $a['ACT_DESDE'] : $a['ACT_DESDE'] . '–' . $a['ACT_HASTA'];
    $asiento[] = 'Actividad documentada: ' . $act;
}
$nM = (int) $a['marchasLength'];
$nG = (int) $a['N_GRAB_TOTAL'];
$ppal = $a['BANDA_PPAL'] ?? null;
?>
<div class="crumbs">
    <span><a href="/">Inicio</a> › <a href="/autor">Compositores</a><?php if ($letra !== ''): ?> › <?= V::e($letra) ?><?php endif; ?> › A-<?= $aid ?></span>
    <span class="regnav">registro <?= $num($a['REG_POS']) ?> de <?= $num($a['REG_TOTAL']) ?></span>
</div>

<article class="record">
    <div class="head">
        <span class="eb">Compositor</span>
        <span class="sig">MDC · A-<?= $aid ?></span>
    </div>
    <h1><?= V::e($autoridad) ?></h1>
<?php if ($asiento !== []): ?>
    <p class="asiento"><?= implode('. — ', $asiento) ?>.</p>
<?php endif; ?>

    <dl class="desc">
<?php if ($t($a['NOMBRE_ART'])): ?>
        <div class="f"><dt>Nombre artístico</dt><dd><?= V::e($a['NOMBRE_ART']) ?></dd></div>
<?php endif; ?>
<?php if ($nac > 1000 || $t($a['LUGAR_NAC'])): ?>
        <div class="f"><dt>Nacimiento</dt><dd><?= V::e(trim(($a['LUGAR_NAC'] ?? '') . ($nac > 1000 ? ($t($a['LUGAR_NAC']) ? ', ' : '') . $nac : ''))) ?></dd></div>
<?php endif; ?>
<?php if ($def > 1000): ?>
        <div class="f"><dt>Fallecimiento</dt><dd><?= $def ?></dd></div>
<?php endif; ?>
<?php if ((int) $a['ACT_DESDE'] > 0): ?>
        <div class="f"><dt>Actividad</dt><dd><?= $a['ACT_DESDE'] === $a['ACT_HASTA'] ? $a['ACT_DESDE'] : $a['ACT_DESDE'] . '–' . $a['ACT_HASTA'] ?></dd></div>
<?php endif; ?>
        <div class="f"><dt>Marchas</dt><dd><?= $num($nM) ?></dd></div>
<?php if ($nG > 0): ?>
        <div class="f"><dt>Grabaciones de su obra</dt><dd><?= $num($nG) ?></dd></div>
<?php endif; ?>
<?php if ($ppal && (int) $ppal['N'] > 1): ?>
        <div class="f"><dt>Vinculación ppal.</dt><dd><a href="<?= V::e(S::buildDetailPath('banda', $ppal['ID_BANDA'], (string) $ppal['NOMBRE_BREVE'])) ?>"><?= V::e($ppal['NOMBRE_BREVE']) ?></a> <span class="cnt">(<?= $num($ppal['N']) ?> estrenos)</span></dd></div>
<?php endif; ?>
    </dl>

<?php if ($t($a['BIO'])): ?>
    <div class="shead"><h2>Biografía</h2></div>
    <p class="notas"><?= str_replace(['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], '<br>', V::e($a['BIO'])) ?></p>
<?php else: ?>
    <p class="bio-empty">Sin biografía documentada todavía.</p>
<?php endif; ?>

    <div class="shead">
        <h2>Obra</h2>
        <span class="n" id="obra-count"><?= $num($nM) ?> marchas · orden cronológico</span>
<?php if ($nM >= 8): ?>
        <input class="filter" type="text" placeholder="filtrar…" aria-label="Filtrar marchas del compositor" data-filter="obra-table" data-count="obra-count" data-total="<?= $nM ?>">
<?php endif; ?>
    </div>
    <div class="scrollx">
    <table class="reg" id="obra-table" data-sortable>
        <thead><tr>
            <th>Marcha <span class="ar">↕</span></th>
            <th data-type="num">Año <span class="ar">↕</span></th>
            <th>Banda de estreno <span class="ar">↕</span></th>
            <th class="num" data-type="num">Grab. <span class="ar">↕</span></th>
        </tr></thead>
        <tbody>
<?php foreach ($a['marchas'] as $m): ?>
            <tr>
                <td><a href="<?= V::e(S::buildDetailPath('marcha', $m['ID_MARCHA'], (string) $m['TITULO'])) ?>"><?= V::e($m['TITULO']) ?></a></td>
                <td><?= $t($m['FECHA']) ? V::e($m['FECHA']) : '—' ?></td>
                <td><?php if ($t($m['BANDA_ESTRENO'])): ?><a href="<?= V::e(S::buildDetailPath('banda', $m['BANDA_ESTRENO'], (string) $m['BANDA_BREVE'])) ?>"><?= V::e($m['BANDA_BREVE']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="num"><?= (int) $m['N_GRAB'] ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    </div>

<?php if ($ppal && (int) $ppal['N'] > 1): ?>
    <div class="shead"><h2>Véase también</h2></div>
    <ul class="vease">
        <li>→ <a href="<?= V::e(S::buildDetailPath('banda', $ppal['ID_BANDA'], (string) $ppal['NOMBRE_BREVE'])) ?>"><?= V::e($ppal['NOMBRE_BREVE']) ?></a> — banda que estrenó <?= $num($ppal['N']) ?> de sus marchas <span class="cnt">(B-<?= (int) $ppal['ID_BANDA'] ?>)</span></li>
    </ul>
<?php endif; ?>

    <div class="ids">
<?php if (!empty($url)): ?>
        <span>permalink: <?= V::e(preg_replace('#^https?://#', '', (string) $url)) ?></span>
<?php endif; ?>
        <span>registro A-<?= $aid ?></span>
    </div>
</article>
