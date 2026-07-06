#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// Fase 1 — Extractor yt-dlp (offline)
//
// Lee config/canales.csv (ID_BANDA,CANAL_URL), lista los vídeos y directos de
// cada canal publicados desde 2019 con sus metadatos (título, descripción,
// fecha, duración) y los vuelca a:
//   out/raw/<ID_BANDA>-<slug>.ndjson  → caché slim por canal (reanudable)
//   out/videos.ndjson                 → dataset combinado y deduplicado
//
// NO clasifica ni extrae campos de marcha (eso es Fase 2). Sin dependencias
// nativas: solo Node + yt-dlp en el PATH.
//
// Uso:
//   node extract.mjs                 # todos los canales, desde 2019
//   node extract.mjs --only 16       # solo la banda 16
//   node extract.mjs --max 5         # solo los 5 vídeos más recientes por pestaña (smoke test)
//   node extract.mjs --force         # ignora la caché y vuelve a bajar
//   node extract.mjs --since 2019    # año mínimo (por defecto 2019)
//   node extract.mjs --sleep 1       # segundos entre peticiones (cortesía; por defecto 1)
// ─────────────────────────────────────────────────────────────────────────────

import { spawn } from 'node:child_process';
import { readFile, mkdir, writeFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const CSV = join(HERE, 'config', 'canales.csv');
const OUT = join(HERE, 'out');
const RAW = join(OUT, 'raw');

// ── args ─────────────────────────────────────────────────────────────────────
function parseArgs(argv) {
  const a = { force: false, max: 0, only: null, since: 2019, sleep: 1 };
  for (let i = 0; i < argv.length; i++) {
    const t = argv[i];
    if (t === '--force') a.force = true;
    else if (t === '--max') a.max = parseInt(argv[++i], 10) || 0;
    else if (t === '--only') a.only = parseInt(argv[++i], 10);
    else if (t === '--since') a.since = parseInt(argv[++i], 10) || 2019;
    else if (t === '--sleep') a.sleep = Number(argv[++i]) || 0;
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

/** Pestañas a raspar: /videos y /streams salvo que la URL ya apunte a una. */
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

/**
 * Ejecuta yt-dlp sobre una URL de pestaña y devuelve los registros slim.
 * Filtra por fecha (--dateafter) y parsea el NDJSON de --dump-json línea a línea.
 */
function fetchTab(tabUrl, { since, max, sleep }, onRecord) {
  return new Promise((resolve, reject) => {
    const ytArgs = [
      '--ignore-errors',
      '--no-warnings',
      '--skip-download',
      '--dateafter', `${since}0101`,
      '--dump-json',
    ];
    if (sleep > 0) ytArgs.push('--sleep-requests', String(sleep));
    if (max > 0) ytArgs.push('--playlist-end', String(max));
    ytArgs.push(tabUrl);

    const child = spawn('yt-dlp', ytArgs, {
      shell: process.platform === 'win32', // resolver yt-dlp.exe/shim en Windows
    });

    let buf = '';
    let count = 0;
    child.stdout.setEncoding('utf8');
    child.stdout.on('data', (chunk) => {
      buf += chunk;
      let nl;
      while ((nl = buf.indexOf('\n')) !== -1) {
        const line = buf.slice(0, nl).trim();
        buf = buf.slice(nl + 1);
        if (!line) continue;
        try {
          const v = JSON.parse(line);
          onRecord(v);
          count++;
        } catch {
          /* línea no-JSON (aviso de yt-dlp que se coló): ignorar */
        }
      }
    });
    // Progreso / errores de yt-dlp: reenviar a stderr para visibilidad.
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (d) => process.stderr.write(d));

    child.on('error', reject);
    child.on('close', (code) => {
      // yt-dlp devuelve !=0 si algún vídeo dio error aunque el resto fuera bien.
      if (code !== 0) console.error(`  (yt-dlp salió con código ${code} en ${tabUrl}; se continúa)`);
      resolve(count);
    });
  });
}

/** Convierte el JSON de yt-dlp al registro slim que persistimos. */
function slimRecord(idBanda, canalUrl, tab, v) {
  return {
    id_banda: idBanda,
    canal_url: canalUrl,
    tab, // 'videos' | 'streams'
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

async function main() {
  await mkdir(RAW, { recursive: true });
  let canales = await readCanales();
  if (args.only != null) canales = canales.filter((c) => c.idBanda === args.only);
  if (canales.length === 0) {
    console.error('No hay canales que procesar.');
    process.exit(1);
  }

  console.log(`Extractor yt-dlp · ${canales.length} canal(es) · desde ${args.since}` +
    (args.max ? ` · máx ${args.max}/pestaña` : '') + (args.force ? ' · --force' : ''));

  for (const { idBanda, canalUrl } of canales) {
    const cacheFile = join(RAW, `${idBanda}-${slug(canalUrl)}.ndjson`);
    if (existsSync(cacheFile) && !args.force) {
      console.log(`\n· banda ${idBanda}: caché encontrada, se omite (usa --force para rebajar).`);
      continue;
    }
    console.log(`\n· banda ${idBanda} → ${canalUrl}`);
    const records = [];
    const seen = new Set();
    for (const tab of tabUrls(canalUrl)) {
      const tabName = /\/streams$/i.test(tab) ? 'streams' : 'videos';
      process.stdout.write(`  ${tabName}… `);
      try {
        await fetchTab(tab, args, (v) => {
          if (!v.id || seen.has(v.id)) return;
          if (v.live_status === 'is_upcoming') return; // estreno futuro, aún sin contenido
          seen.add(v.id);
          records.push(slimRecord(idBanda, canalUrl, tabName, v));
        });
        console.log(`${records.length} acum.`);
      } catch (e) {
        console.log(`ERROR (${e.message}); se continúa.`);
      }
    }
    const ndjson = records.map((r) => JSON.stringify(r)).join('\n') + (records.length ? '\n' : '');
    await writeFile(cacheFile, ndjson, 'utf8');
    console.log(`  guardado ${records.length} vídeos → ${cacheFile.replace(HERE, '.')}`);
  }

  // ── combinar todas las cachés en out/videos.ndjson (dedup global por video_id)
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
  for (const [banda, n] of Object.entries(perBanda).sort((a, b) => a[0] - b[0])) {
    console.log(`  banda ${banda}: ${n} vídeos`);
  }
  console.log(`  TOTAL: ${combined.length} vídeos → ${outFile.replace(HERE, '.')}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
