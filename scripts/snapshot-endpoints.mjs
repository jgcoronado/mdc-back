#!/usr/bin/env node
/**
 * Hit a list of API endpoints against a running instance of the app and
 * persist each response as JSON. The result is a snapshot directory you
 * can diff before and after the SQLite migration.
 *
 * Usage:
 *   node scripts/snapshot-endpoints.mjs \
 *     --base http://localhost:8080 \
 *     --out snapshots/mysql
 *
 * The endpoint list is intentionally read-only (no admin POSTs), so the
 * snapshot is safe to run against any environment.
 */

import fs from 'node:fs';
import path from 'node:path';

const READ_ONLY_ENDPOINTS = [
  '/api/stats/estado',
  '/api/stats/masAutor',
  '/api/stats/masDedica',
  '/api/stats/masEstreno',
  '/api/stats/masGrabada',
  '/api/marcha/search?titulo=Amargura',
  '/api/marcha/search?titulo=Esperanza',
  '/api/marcha/search?dedicatoria=Hdad',
  '/api/marcha/search?localidad=Sevilla',
  '/api/marcha/search?fechaDesde=1990&fechaHasta=2000',
  '/api/marcha/330',
  '/api/marcha/330/disco',
  '/api/autor/search?nombre=Gamez',
  '/api/autor/fastSearch?nombre=Gam',
  '/api/autor/1',
  '/api/banda/search?titulo=Tres',
  '/api/banda/fastSearch?nombre=Tres',
  '/api/banda/1',
  '/api/disco/search?nombre=Pasion',
  '/api/disco/1',
  '/sitemap.xml',
  '/robots.txt',
];

const parseCliArgs = (argv) => {
  const args = { base: 'http://localhost:8080', out: 'snapshots/run' };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--base' && argv[index + 1]) {
      args.base = argv[index + 1].replace(/\/$/, '');
    }
    if (argv[index] === '--out' && argv[index + 1]) {
      args.out = argv[index + 1];
    }
  }
  return args;
};

const buildFileNameFromPath = (endpointPath) => {
  return endpointPath
    .replace(/^\//, '')
    .replace(/[?&=]/g, '_')
    .replace(/\//g, '__')
    || 'root';
};

const fetchEndpoint = async (baseUrl, endpointPath) => {
  const fullUrl = `${baseUrl}${endpointPath}`;
  const response = await fetch(fullUrl, {
    headers: { Accept: 'application/json, text/xml, text/plain;q=0.9, */*;q=0.5' },
  });
  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const body = isJson ? await response.json() : await response.text();
  return {
    endpoint: endpointPath,
    status: response.status,
    contentType,
    body,
  };
};

const writeSnapshot = (outDir, endpointPath, payload) => {
  const fileName = `${buildFileNameFromPath(endpointPath)}.json`;
  const filePath = path.join(outDir, fileName);
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2));
  return filePath;
};

const runSnapshot = async (baseUrl, outDir) => {
  fs.mkdirSync(outDir, { recursive: true });
  const summary = { ok: 0, failed: 0, total: READ_ONLY_ENDPOINTS.length };

  for (const endpointPath of READ_ONLY_ENDPOINTS) {
    try {
      const payload = await fetchEndpoint(baseUrl, endpointPath);
      writeSnapshot(outDir, endpointPath, payload);
      const indicator = payload.status >= 400 ? 'WARN' : 'OK';
      console.log(`[${indicator}] ${payload.status} ${endpointPath}`);
      if (payload.status >= 400) summary.failed += 1;
      else summary.ok += 1;
    } catch (err) {
      console.log(`[ERR ] ${endpointPath} -> ${err.message}`);
      writeSnapshot(outDir, endpointPath, { endpoint: endpointPath, error: err.message });
      summary.failed += 1;
    }
  }

  console.log(`\nDone. ${summary.ok}/${summary.total} OK, ${summary.failed} failures.`);
  console.log(`Snapshot dir: ${outDir}`);
};

const main = async () => {
  const args = parseCliArgs(process.argv.slice(2));
  console.log(`Snapshotting ${args.base} -> ${args.out}`);
  await runSnapshot(args.base, args.out);
};

main().catch((err) => {
  console.error('Snapshot failed:', err);
  process.exit(1);
});
