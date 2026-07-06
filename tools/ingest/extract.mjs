#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// Fase 1 — Extractor yt-dlp (offline), en dos pasadas
//
// PASADA 1 (barata): lista cada pestaña del canal en modo --flat-playlist con
// fecha aproximada (un puñado de peticiones por canal, sin abrir cada vídeo) y
// descarta ahí mismo lo que no interesa: vídeos solo-para-miembros
// (`availability`), fuera de rango de fecha, o cuyo título ya delata que es
// ruido (ensayo/cover/tutorial/...). Esto es clave: muchos canales de banda
// suben contenido con muchísima frecuencia y una fracción notable son vídeos
// exclusivos para "socios"/members — abrir cada uno con --dump-json normal
// generaba un aluvión de errores "members-only" y tardaba horas.
//
// PASADA 2: solo para los vídeos que sobreviven al filtro, extrae metadatos
// completos (con descripción, imprescindible para la Fase 2) con un pequeño
// pool de workers concurrentes en vez de ir uno a uno en serie.
//
// Caché por vídeo (no por canal): out/raw/<ID_BANDA>-<slug>.ndjson se escribe
// incrementalmente, así que cortar el proceso a mitad de un canal no pierde lo
// ya conseguido — la siguiente ejecución solo pide lo que falta.
//
// Uso:
//   node extract.mjs --dry-run              # solo cuenta candidatos por canal, sin descargar nada
//   node extract.mjs --only 16 --max 20     # smoke test acotado
//   node extract.mjs                        # pasada real completa (reanudable)
//   node extract.mjs --force                # ignora la caché de vídeos y repite todo
//   node extract.mjs --concurrency 6        # más paralelismo (por defecto 4)
// ─────────────────────────────────────────────────────────────────────────────

import { spawn } from 'node:child_process';
import { readFile, mkdir, appendFile, writeFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const CSV = join(HERE, 'config', 'canales.csv');
const KEYWORDS_FILE = join(HERE, 'config', 'keywords.json');
const OUT = join(HERE, 'out');
const RAW = join(OUT, 'raw');

// Disponibilidades de YouTube que no podemos leer sin sesión de socio/compra.
const EXCLUDED_AVAILABILITY = new Set(['subscriber_only', 'premium_only', 'private', 'needs_auth']);

// ── args ─────────────────────────────────────────────────────────────────────
function parseArgs(argv) {
  const a = { force: false, max: 0, only: null, since: 2019, sleep: 0.4, concurrency: 4, dryRun: false };
  for (let i = 0; i < argv.length; i++) {
    const t = argv[i];
    if (t === '--force') a.force = true;
    else if (t === '--dry-run') a.dryRun = true;
    else if (t === '--max') a.max = parseInt(argv[++i], 10) || 0;
    else if (t === '--only') a.only = parseInt(argv[++i], 10);
    else if (t === '--since') a.since = parseInt(argv[++i], 10) || 2019;
    else if (t === '--sleep') a.sleep = Number(argv[++i]) || 0;
    else if (t === '--concurrency') a.concurrency = Math.max(1, parseInt(argv[++i], 10) || 4);
    else { console.error(`Argumento no reconocido: ${t}`); process.exit(2); }
  }
  return a;
}
const args = parseArgs(process.argv.slice(2));

// ── util ─────────────────────────────────────────────────────────────────────
const slug = (s) =>
  (s || '')
    .toLowerCase()
    .replace(/^https?:\/\/(www\.)?youtube\.com\//, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
    .slice(0, 40) || 'canal';

const isoDate = (yyyymmdd) =>
  /^\d{8}$/.test(yyyymmdd || '')
    ? `${yyyymmdd.slice(0, 4)}-${yyyymmdd.slice(4, 6)}-${yyyymmdd.slice(6, 8)}`
    : null;

const normalize = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function tabUrls(url) {
  const u = url.trim().replace(/\/+$/, '');
  if (/\/(videos|streams|shorts|playlists|featured|community|podcasts)$/i.test(u)) return [u];
  return [`${u}/videos`, `${u}/streams`];
}

async function readCanales() {
  if (!existsSync(CSV)) {
    console.error(`No existe ${CSV}. Copia config/canales.example.csv y rellénalo.`);
    process.exit(1);
  }
  const text = await readFile(CSV, 'utf8');
  const lines = text.split(/\r?\n/).filter((l) => l.trim() !== '');
  const header = (lines.shift() || '').split(',').map((s) => s.trim().toUpperCase());
  if (header[0] !== 'ID_BANDA' || header[1] !== 'CANAL_URL') {
    console.error('Cabecera inválida. Se espera: ID_BANDA,CANAL_URL');
    process.exit(1);
  }
  const out = [];
  for (const line of lines) {
    const [id, url] = line.split(',');
    const idBanda = parseInt((id || '').trim(), 10);
    const canalUrl = (url || '').trim();
    if (idBanda > 0 && canalUrl) out.push({ idBanda, canalUrl });
  }
  return out;
}

/** Ejecuta yt-dlp y devuelve todo el stdout (usado para --flat-playlist, una sola respuesta grande). */
function runYtDlp(ytArgs) {
  return new Promise((resolve, reject) => {
    const child = spawn('yt-dlp', ytArgs, { shell: process.platform === 'win32' });
    let out = '';
    child.stdout.setEncoding('utf8');
    child.stdout.on('data', (d) => (out += d));
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (d) => process.stderr.write(d));
    child.on('error', reject);
    child.on('close', () => resolve(out)); // yt-dlp puede salir !=0 aunque haya datos parciales útiles
  });
}

/** Pasada 1: listado barato de una pestaña (sin abrir cada vídeo). */
async function fetchFlatTab(tabUrl, since) {
  const ytArgs = [
    '--ignore-errors', '--no-warnings', '--flat-playlist', '--dump-json',
    '--extractor-args', 'youtubetab:approximate_date',
    '--dateafter', `${since - 1}0101`, // -1 año de colchón: la fecha aproximada puede errar por semanas/meses
    tabUrl,
  ];
  const out = await runYtDlp(ytArgs);
  const entries = [];
  for (const line of out.split('\n')) {
    if (!line.trim()) continue;
    try { entries.push(JSON.parse(line)); } catch { /* línea no-JSON, ignorar */ }
  }
  return entries;
}

/** Pasada 2: metadatos completos (con descripción) de un único vídeo. */
function fetchVideoFull(videoId, sleepSec) {
  return new Promise((resolve) => {
    const url = `https://www.youtube.com/watch?v=${videoId}`;
    const ytArgs = ['--ignore-errors', '--no-warnings', '--skip-download', '--dump-json'];
    if (sleepSec > 0) ytArgs.push('--sleep-requests', String(sleepSec));
    ytArgs.push(url);
    const child = spawn('yt-dlp', ytArgs, { shell: process.platform === 'win32' });
    let out = '';
    let errOut = '';
    child.stdout.setEncoding('utf8');
    child.stdout.on('data', (d) => (out += d));
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (d) => (errOut += d));
    child.on('error', () => resolve({ ok: false, error: 'spawn-error' }));
    child.on('close', () => {
      const line = out.split('\n').find((l) => l.trim());
      if (!line) {
        const reason = errOut.split('\n')[0]?.replace(/^ERROR:\s*/, '').slice(0, 120) || 'sin datos';
        resolve({ ok: false, error: reason });
        return;
      }
      try { resolve({ ok: true, video: JSON.parse(line) }); }
      catch { resolve({ ok: false, error: 'json-parse-error' }); }
    });
  });
}

/** Pool de concurrencia simple: corre `worker` sobre `items` con como mucho `limit` a la vez. */
async function pool(items, limit, worker) {
  let next = 0;
  async function runOne() {
    while (next < items.length) {
      const idx = next++;
      await worker(items[idx], idx);
    }
  }
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, runOne));
}

function slimRecord(idBanda, canalUrl, tab, v) {
  return {
    id_banda: idBanda,
    canal_url: canalUrl,
    tab,
    video_id: v.id,
    url: v.webpage_url || (v.id ? `https://www.youtube.com/watch?v=${v.id}` : null),
    titulo: v.title ?? null,
    descripcion: v.description ?? null,
    publicado: isoDate(v.upload_date),
    duracion_seg: Number.isFinite(v.duration) ? Math.round(v.duration) : null,
    live_status: v.live_status ?? (v.was_live ? 'was_live' : null),
    channel: v.channel ?? v.uploader ?? null,
    channel_id: v.channel_id ?? null,
  };
}

/** Carga los video_id ya presentes en la caché de un canal (para no re-pedirlos). */
async function loadCachedIds(cacheFile) {
  if (!existsSync(cacheFile)) return new Map();
  const text = await readFile(cacheFile, 'utf8');
  const map = new Map();
  for (const line of text.split(/\r?\n/)) {
    if (!line.trim()) continue;
    try { const r = JSON.parse(line); map.set(r.video_id, line); } catch { /* ignorar línea corrupta */ }
  }
  return map;
}

async function processChannel(idBanda, canalUrl, kw) {
  console.log(`\n· banda ${idBanda} → ${canalUrl}`);

  // ── Pasada 1: listar barato y filtrar ────────────────────────────────────
  const seenFlat = new Map(); // video_id -> {entry, tab}
  for (const tabUrl of tabUrls(canalUrl)) {
    const tabName = /\/streams$/i.test(tabUrl) ? 'streams' : 'videos';
    process.stdout.write(`  [1/2] listando ${tabName}… `);
    const entries = await fetchFlatTab(tabUrl, args.since);
    let added = 0;
    for (const v of entries) {
      if (!v.id || seenFlat.has(v.id)) continue;
      seenFlat.set(v.id, { entry: v, tab: tabName });
      added++;
    }
    console.log(`${entries.length} listados, ${added} nuevos`);
  }

  const dropped = { miembros: 0, fecha: 0, titulo_excluido: 0, upcoming: 0 };
  const excluirNorm = kw.excluir.map(normalize);
  const candidates = [];
  for (const [videoId, { entry: v, tab }] of seenFlat) {
    if (EXCLUDED_AVAILABILITY.has(v.availability)) { dropped.miembros++; continue; }
    if (v.live_status === 'is_upcoming') { dropped.upcoming++; continue; }
    const year = v.upload_date ? parseInt(v.upload_date.slice(0, 4), 10) : null;
    if (year && year < args.since - 1) { dropped.fecha++; continue; } // colchón; filtro fino tras pasada 2
    const tituloNorm = normalize(v.title);
    if (excluirNorm.some((k) => tituloNorm.includes(k))) { dropped.titulo_excluido++; continue; }
    candidates.push({ videoId, tab });
  }

  console.log(
    `  [1/2] filtrado: ${seenFlat.size} vistos → ${candidates.length} candidatos ` +
    `(descartados: ${dropped.miembros} solo-miembros, ${dropped.fecha} fuera de fecha, ` +
    `${dropped.titulo_excluido} título excluido, ${dropped.upcoming} próximos)`
  );

  if (args.dryRun) return { listados: seenFlat.size, candidatos: candidates.length, dropped };

  // ── Pasada 2: extracción completa, solo de los candidatos, con concurrencia ──
  const cacheFile = join(RAW, `${idBanda}-${slug(canalUrl)}.ndjson`);
  await mkdir(RAW, { recursive: true });
  const cached = args.force ? new Map() : await loadCachedIds(cacheFile);

  let toFetch = candidates.filter((c) => !cached.has(c.videoId));
  if (args.max > 0) toFetch = toFetch.slice(0, args.max);

  console.log(`  [2/2] extrayendo ${toFetch.length} vídeos nuevos (${cached.size} ya en caché, concurrencia ${args.concurrency})…`);

  let writeQueue = Promise.resolve();
  const enqueueWrite = (line) => { writeQueue = writeQueue.then(() => appendFile(cacheFile, line + '\n', 'utf8')); return writeQueue; };

  let done = 0, errores = 0, fueraDeRango = 0;
  const startTs = Date.now();
  await pool(toFetch, args.concurrency, async ({ videoId }) => {
    const res = await fetchVideoFull(videoId, args.sleep);
    done++;
    if (!res.ok) {
      errores++;
    } else {
      const v = res.video;
      const year = v.upload_date ? parseInt(v.upload_date.slice(0, 4), 10) : null;
      if (v.live_status === 'is_upcoming' || (year && year < args.since)) {
        fueraDeRango++;
      } else {
        const rec = slimRecord(idBanda, canalUrl, seenFlat.get(videoId)?.tab ?? 'videos', v);
        await enqueueWrite(JSON.stringify(rec));
      }
    }
    if (done % 25 === 0 || done === toFetch.length) {
      const secs = ((Date.now() - startTs) / 1000).toFixed(0);
      process.stdout.write(`  [2/2] ${done}/${toFetch.length} (${errores} errores) · ${secs}s\r\n`);
    }
  });
  await writeQueue;

  console.log(`  [2/2] hecho: ${done - errores - fueraDeRango} guardados, ${errores} errores, ${fueraDeRango} fuera de rango tras confirmar fecha real.`);
  return { listados: seenFlat.size, candidatos: candidates.length, dropped, extraidos: done - errores - fueraDeRango, errores };
}

async function main() {
  await mkdir(RAW, { recursive: true });
  let canales = await readCanales();
  if (args.only != null) canales = canales.filter((c) => c.idBanda === args.only);
  if (canales.length === 0) {
    console.error('No hay canales que procesar.');
    process.exit(1);
  }
  const kw = JSON.parse(await readFile(KEYWORDS_FILE, 'utf8'));

  console.log(
    `Extractor yt-dlp (2 pasadas) · ${canales.length} canal(es) · desde ${args.since} · concurrencia ${args.concurrency}` +
    (args.dryRun ? ' · --dry-run' : '') + (args.force ? ' · --force' : '') + (args.max ? ` · máx ${args.max}/canal` : '')
  );

  const resumen = [];
  for (const { idBanda, canalUrl } of canales) {
    const r = await processChannel(idBanda, canalUrl, kw);
    resumen.push({ idBanda, ...r });
  }

  if (args.dryRun) {
    console.log(`\n── Resumen (--dry-run, nada descargado) ──`);
    for (const r of resumen) console.log(`  banda ${r.idBanda}: ${r.listados} listados → ${r.candidatos} candidatos a extraer`);
    return;
  }

  // ── combinar todas las cachés en out/videos.ndjson (dedup global por video_id) ──
  const files = (await readdir(RAW)).filter((f) => f.endsWith('.ndjson'));
  const combined = [];
  const seenGlobal = new Set();
  const perBanda = {};
  for (const f of files) {
    const text = await readFile(join(RAW, f), 'utf8');
    for (const line of text.split(/\r?\n/)) {
      if (!line.trim()) continue;
      const r = JSON.parse(line);
      if (seenGlobal.has(r.video_id)) continue;
      seenGlobal.add(r.video_id);
      combined.push(r);
      perBanda[r.id_banda] = (perBanda[r.id_banda] || 0) + 1;
    }
  }
  const outFile = join(OUT, 'videos.ndjson');
  await writeFile(outFile, combined.map((r) => JSON.stringify(r)).join('\n') + (combined.length ? '\n' : ''), 'utf8');

  console.log(`\n── Resumen ──`);
  for (const [banda, n] of Object.entries(perBanda).sort((a, b) => a[0] - b[0])) console.log(`  banda ${banda}: ${n} vídeos`);
  console.log(`  TOTAL: ${combined.length} vídeos → ${outFile.replace(HERE, '.')}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
