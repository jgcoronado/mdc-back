<?php use App\View as V; use App\Slug as S;
/** @var array<string,mixed> $m */
/** @var string|null $url  URL canónica absoluta (permalink) */

// "truthy" al estilo JS: null, '', 0, 0.0 y false son falsos; '0' (string) es verdadero.
$t = static fn($v): bool => !($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === false);

$num = static fn($n): string => number_format((int) $n, 0, ',', '.');

/** 208 → "3 min 28 s" */
$dur = static function ($seg): string {
    $s = (int) $seg;
    return $s > 0 ? intdiv($s, 60) . ' min ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . ' s' : '';
};

/** Forma de autoridad: "Apellidos, Nombre" (o lo que exista). */
$autoridad = static function (array $a): string {
    $ap = trim((string) ($a['APELLIDOS'] ?? ''));
    $no = trim((string) ($a['NOMBRE'] ?? ''));
    if ($ap !== '' && $no !== '') return $ap . ', ' . $no;
    return $ap !== '' ? $ap : $no;
};

/** "(1896–1970)", "(1896–)" o "" según los datos del autor. */
$vida = static function (array $a): string {
    $n = (int) ($a['F_NAC'] ?? 0);
    $d = (int) ($a['F_DEF'] ?? 0);
    if ($n > 1000 && $d > 1000) return " ({$n}–{$d})";
    if ($n > 1000) return " ({$n}–)";
    if ($d > 1000) return " (†{$d})";
    return '';
};

$mid = (int) $m['ID_MARCHA'];
$tipo = $t($m['TIPO'] ?? null) ? ucfirst(mb_strtolower((string) $m['TIPO'])) : 'Marcha';
$duracion = $dur($m['DURACION_SEG'] ?? 0);
$autores = $m['AUTORES_FICHA'] ?? [];

// Asiento bibliográfico bajo el título, segmentos ". —" solo con datos presentes.
$asientoAutores = [];
foreach ($autores as $a) {
    $path = S::buildDetailPath('autor', $a['ID_AUTOR'], trim(($a['NOMBRE'] ?? '') . ' ' . ($a['APELLIDOS'] ?? '')));
    $asientoAutores[] = '<a href="' . V::e($path) . '">' . V::e($autoridad($a)) . '</a>' . V::e($vida($a));
}
$asiento = [implode('; ', $asientoAutores)];
$lugarAnio = trim(implode(', ', array_filter([
    $t($m['LOCALIDAD']) ? (string) $m['LOCALIDAD'] : ($t($m['PROVINCIA']) ? (string) $m['PROVINCIA'] : ''),
    $t($m['FECHA']) ? (string) $m['FECHA'] : '',
], static fn($v) => $v !== '')));
if ($lugarAnio !== '') $asiento[] = V::e($lugarAnio);
if ($duracion !== '') $asiento[] = V::e($duracion);

// Localidad (Provincia) para la descripción.
$localidad = '';
if ($t($m['LOCALIDAD'])) {
    $localidad = (string) $m['LOCALIDAD'] . ($t($m['PROVINCIA']) ? ' (' . $m['PROVINCIA'] . ')' : '');
} elseif ($t($m['PROVINCIA'])) {
    $localidad = (string) $m['PROVINCIA'];
}

// Notas: la BD guarda '<br>' literales; se escapan y se restauran solo esos saltos.
$notas = '';
if ($t($m['DETALLES_MARCHA'])) {
    $notas = str_replace(['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], '<br>', V::e($m['DETALLES_MARCHA']));
}

$prev = $m['REG_PREV'] ?? null;
$next = $m['REG_NEXT'] ?? null;
$nGrab = (int) $m['discosLength'];
$badge1a = null; // primera fila cuya fecha coincide con la primera grabación
?>
<div class="crumbs">
    <span><a href="/">Inicio</a> › <a href="/marcha">Marchas</a><?php if ($t($m['FECHA'])): ?> › <a href="<?= V::e('/marcha?fechaDesde=' . $m['FECHA'] . '&fechaHasta=' . $m['FECHA']) ?>"><?= V::e($m['FECHA']) ?></a><?php endif; ?> › M-<?= $mid ?></span>
    <span class="regnav">
<?php if ($prev): ?>
        <a href="<?= V::e(S::buildDetailPath('marcha', $prev['ID_MARCHA'], (string) $prev['TITULO'])) ?>">‹ M-<?= (int) $prev['ID_MARCHA'] ?></a> ·
<?php endif; ?>
        registro <?= $num($m['REG_POS']) ?> de <?= $num($m['REG_TOTAL']) ?>
<?php if ($next): ?>
        · <a href="<?= V::e(S::buildDetailPath('marcha', $next['ID_MARCHA'], (string) $next['TITULO'])) ?>">M-<?= (int) $next['ID_MARCHA'] ?> ›</a>
<?php endif; ?>
    </span>
</div>

<article class="record">
    <div class="head">
        <span class="eb"><?= V::e($tipo) ?></span>
        <span class="sig">MDC · M-<?= $mid ?></span>
    </div>
    <h1><?= V::e($m['TITULO']) ?></h1>
<?php if ($asiento[0] !== '' || count($asiento) > 1): ?>
    <p class="asiento"><?= implode('. — ', array_filter($asiento, static fn($s) => $s !== '')) ?>.</p>
<?php endif; ?>

    <dl class="desc">
<?php foreach ($autores as $a): ?>
        <div class="f"><dt>Compositor</dt><dd><a href="<?= V::e(S::buildDetailPath('autor', $a['ID_AUTOR'], trim(($a['NOMBRE'] ?? '') . ' ' . ($a['APELLIDOS'] ?? '')))) ?>"><?= V::e($autoridad($a)) ?></a><?php if ((int) $a['N_MARCHAS'] > 1): ?> <span class="cnt">(<?= $num($a['N_MARCHAS']) ?> marchas)</span><?php endif; ?></dd></div>
<?php endforeach; ?>
<?php if ($t($m['FECHA'])): ?>
        <div class="f"><dt>Año</dt><dd><?= V::e($m['FECHA']) ?></dd></div>
<?php endif; ?>
<?php if ($t($m['TIPO'])): ?>
        <div class="f"><dt>Tipo</dt><dd><?= V::e($tipo) ?></dd></div>
<?php endif; ?>
<?php if ($duracion !== ''): ?>
        <div class="f"><dt>Duración</dt><dd><?= V::e($duracion) ?> <span class="cnt">(<?= (int) $m['DURACION_SEG'] ?> s)</span></dd></div>
<?php endif; ?>
<?php if ($t($m['DEDICATORIA']) && $m['DEDICATORIA'] !== '0'): ?>
        <div class="f"><dt>Dedicatoria</dt><dd><?= V::e($m['DEDICATORIA']) ?></dd></div>
<?php endif; ?>
<?php if ($localidad !== ''): ?>
        <div class="f"><dt>Localidad</dt><dd><?= V::e($localidad) ?></dd></div>
<?php endif; ?>
<?php if ($t($m['BANDA_ESTRENO'])): ?>
        <div class="f"><dt>Estreno</dt><dd><a href="<?= V::e(S::buildDetailPath('banda', $m['BANDA_ESTRENO'], (string) $m['BANDA_NOMBRE'])) ?>"><?= V::e($m['BANDA_NOMBRE']) ?></a><?php if ($t($m['BANDA_LOC'])): ?>, <?= V::e($m['BANDA_LOC']) ?><?php endif; ?><?php if ((int) $m['BANDA_ESTRENOS'] > 1): ?> <span class="cnt">(<?= $num($m['BANDA_ESTRENOS']) ?> estrenos)</span><?php endif; ?></dd></div>
<?php endif; ?>
        <div class="f"><dt>Grabaciones</dt><dd><?= $num($nGrab) ?><?php if ($m['PRIMERA_GRABACION']): ?> <span class="cnt">· primera en <?= (int) $m['PRIMERA_GRABACION'] ?></span><?php endif; ?></dd></div>
    </dl>

<?php if ($t($m['AUDIO'])): ?>
    <div class="listen">
        <div class="listen-head"><span class="pt">Escuchar</span><span class="todo">TODO · más servicios</span></div>
        <div class="svcs">
            <a class="svc" href="<?= V::e($m['AUDIO']) ?>" rel="noopener" target="_blank">▶ YouTube ↗</a>
            <span class="svc off">♪ Apple Music</span>
            <span class="svc off">● Spotify</span>
            <span class="svc off">≋ Tidal</span>
        </div>
    </div>
<?php endif; ?>

<?php if ($notas !== ''): ?>
    <div class="shead"><h2>Notas</h2></div>
    <p class="notas"><?= $notas ?></p>
<?php endif; ?>

    <div class="shead">
        <h2>Grabaciones</h2>
<?php if ($nGrab > 0): ?>
        <span class="n" id="grab-count"><?= $num($nGrab) ?> · orden cronológico</span>
<?php if ($nGrab >= 8): ?>
        <input class="filter" type="text" placeholder="filtrar grabaciones…" aria-label="Filtrar grabaciones" data-filter="grab-table" data-count="grab-count" data-total="<?= $nGrab ?>">
<?php endif; ?>
<?php endif; ?>
    </div>
<?php if ($nGrab === 0): ?>
    <p class="bio-empty">Aún sin grabaciones documentadas.</p>
<?php else: ?>
    <div class="scrollx">
    <table class="reg" id="grab-table" data-sortable>
        <thead><tr>
            <th data-type="num">Año <span class="ar">↕</span></th>
            <th>Grabación <span class="ar">↕</span></th>
            <th>Banda <span class="ar">↕</span></th>
            <th class="num" data-type="num">Pista <span class="ar">↕</span></th>
        </tr></thead>
        <tbody>
<?php foreach ($m['discos'] as $d):
    $anio = (int) (float) ($d['FECHA_CD'] ?? 0);
    $es1a = $badge1a === null && $anio > 1800 && $anio === (int) $m['PRIMERA_GRABACION'];
    if ($es1a) $badge1a = $d;
?>
            <tr>
                <td><?= $anio > 1800 ? $anio : '—' ?></td>
                <td><a href="<?= V::e(S::buildDetailPath('disco', $d['ID_DISCO'], (string) $d['NOMBRE_CD'])) ?>"><?= V::e($d['NOMBRE_CD']) ?></a><?php if ($es1a): ?><span class="badge-1a">◆ 1.ª grabación</span><?php endif; ?></td>
                <td><?php if ($t($d['ID_BANDA'])): ?><a href="<?= V::e(S::buildDetailPath('banda', $d['ID_BANDA'], (string) $d['BANDA_BREVE'])) ?>"><?= V::e($d['BANDA_BREVE']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="num"><?= $t($d['NUMEROMARCHA']) ? (int) $d['NUMEROMARCHA'] : '—' ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php
$vease = [];
foreach ($autores as $a) {
    $p = S::buildDetailPath('autor', $a['ID_AUTOR'], trim(($a['NOMBRE'] ?? '') . ' ' . ($a['APELLIDOS'] ?? '')));
    $label = (int) $a['N_MARCHAS'] > 1
        ? 'las ' . $num($a['N_MARCHAS']) . ' marchas del compositor'
        : 'ficha del compositor';
    $vease[] = '→ <a href="' . V::e($p) . '">' . V::e($autoridad($a)) . '</a> — ' . $label . ' <span class="cnt">(A-' . (int) $a['ID_AUTOR'] . ')</span>';
}
if ($t($m['BANDA_ESTRENO']) && (int) $m['BANDA_ESTRENOS'] > 1) {
    $p = S::buildDetailPath('banda', $m['BANDA_ESTRENO'], (string) $m['BANDA_NOMBRE']);
    $vease[] = '→ <a href="' . V::e($p) . '">' . V::e($m['BANDA_NOMBRE']) . '</a> — los ' . $num($m['BANDA_ESTRENOS']) . ' estrenos de la banda <span class="cnt">(B-' . (int) $m['BANDA_ESTRENO'] . ')</span>';
}
if ($t($m['FECHA']) && (int) $m['N_MISMO_ANIO'] > 1) {
    $vease[] = '→ <a href="' . V::e('/marcha?fechaDesde=' . $m['FECHA'] . '&fechaHasta=' . $m['FECHA']) . '">Marchas del año ' . V::e($m['FECHA']) . '</a> <span class="cnt">(' . $num($m['N_MISMO_ANIO']) . ' registros)</span>';
}
if ($t($m['PROVINCIA']) && (int) $m['N_MISMA_PROV'] > 1) {
    $vease[] = '→ <a href="' . V::e('/marcha?provincia=' . rawurlencode((string) $m['PROVINCIA'])) . '">Marchas de la provincia de ' . V::e($m['PROVINCIA']) . '</a> <span class="cnt">(' . $num($m['N_MISMA_PROV']) . ' registros)</span>';
}
?>
<?php if ($vease !== []): ?>
    <div class="shead"><h2>Véase también</h2></div>
    <ul class="vease">
<?php foreach ($vease as $vs): ?>
        <li><?= $vs ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

    <div class="ids">
<?php if (!empty($url)): ?>
        <span>permalink: <?= V::e(preg_replace('#^https?://#', '', (string) $url)) ?></span>
<?php endif; ?>
        <span>registro M-<?= $mid ?></span>
    </div>
</article>
