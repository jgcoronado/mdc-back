import express from 'express';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import db from './src/db.js';
import loginRoutes from './src/routes/login.js';
import marchaRoutes from './src/routes/marcha.js';
import autorRoutes from './src/routes/autor.js';
import bandaRoutes from './src/routes/banda.js';
import discoRoutes from './src/routes/disco.js';
import statsRoutes from './src/routes/stats.js';
import cors from 'cors';

const app = express();
const port = Number(process.env.APP_PORT || 80);
const allowedOrigins = (process.env.CORS_ORIGINS || '')
  .split(',')
  .map(origin => origin.trim())
  .filter(Boolean);
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const frontendDistPath = path.join(__dirname, 'public');

const xmlEscape = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&apos;');

const normalizeSiteUrl = (rawUrl) => {
  if (!rawUrl) return null;
  const trimmed = rawUrl.trim();
  if (!trimmed) return null;
  return trimmed.endsWith('/') ? trimmed : `${trimmed}/`;
};

const getBaseUrl = (req) => {
  const siteUrl = normalizeSiteUrl(process.env.SITE_URL);
  if (siteUrl) {
    return siteUrl;
  }
  const fallbackOrigin = allowedOrigins[0];
  if (fallbackOrigin) {
    return normalizeSiteUrl(fallbackOrigin);
  }
  const protocol = req.get('x-forwarded-proto') || req.protocol;
  const host = req.get('host');
  return `${protocol}://${host}/`;
};

const buildSitemapEntry = (baseUrl, pathName, changefreq, priority) => {
  const loc = new URL(pathName, baseUrl).toString();
  return [
    '<url>',
    `<loc>${xmlEscape(loc)}</loc>`,
    `<changefreq>${changefreq}</changefreq>`,
    `<priority>${priority}</priority>`,
    '</url>'
  ].join('');
};

app.use(express.json());
app.use(cors());
// app.use(cors({
//   origin(origin, callback) {
//     if (!origin || allowedOrigins.length === 0 || allowedOrigins.includes(origin)) {
//       return callback(null, true);
//     }
//     return callback(new Error('CORS origin not allowed'));
//   },
// }));
app.use('/api/login', loginRoutes);
app.use('/api/marcha', marchaRoutes);
app.use('/api/autor', autorRoutes);
app.use('/api/banda', bandaRoutes);
app.use('/api/disco', discoRoutes);
app.use('/api/stats', statsRoutes);
app.set('trust proxy', true);

app.get('/robots.txt', (req, res) => {
  const baseUrl = getBaseUrl(req);
  const robots = [
    'User-agent: *',
    'Allow: /',
    'Disallow: /login',
    'Disallow: /dashboard',
    `Sitemap: ${new URL('/sitemap.xml', baseUrl).toString()}`,
    ''
  ].join('\n');

  res.type('text/plain').send(robots);
});

app.get('/sitemap.xml', async (req, res) => {
  try {
    const baseUrl = getBaseUrl(req);
    const staticRoutes = [
      { path: '/', changefreq: 'daily', priority: '1.0' },
      { path: '/marcha', changefreq: 'weekly', priority: '0.9' },
      { path: '/autor', changefreq: 'weekly', priority: '0.8' },
      { path: '/banda', changefreq: 'weekly', priority: '0.8' },
      { path: '/disco', changefreq: 'weekly', priority: '0.8' },
      { path: '/estadisticas', changefreq: 'weekly', priority: '0.7' }
    ];

    const [marchas, autores, bandas, discos] = await Promise.all([
      db.pool.execute('SELECT ID_MARCHA AS id FROM marcha WHERE ID_MARCHA IS NOT NULL'),
      db.pool.execute('SELECT ID_AUTOR AS id FROM autor WHERE ID_AUTOR IS NOT NULL'),
      db.pool.execute('SELECT ID_BANDA AS id FROM banda WHERE ID_BANDA IS NOT NULL'),
      db.pool.execute('SELECT ID_DISCO AS id FROM disco WHERE ID_DISCO IS NOT NULL')
    ]);

    const detailRoutes = [
      ...marchas[0].map((row) => ({ path: `/marcha/${row.id}`, changefreq: 'monthly', priority: '0.7' })),
      ...autores[0].map((row) => ({ path: `/autor/${row.id}`, changefreq: 'monthly', priority: '0.6' })),
      ...bandas[0].map((row) => ({ path: `/banda/${row.id}`, changefreq: 'monthly', priority: '0.6' })),
      ...discos[0].map((row) => ({ path: `/disco/${row.id}`, changefreq: 'monthly', priority: '0.6' }))
    ];

    const xmlBody = [...staticRoutes, ...detailRoutes]
      .map((route) => buildSitemapEntry(baseUrl, route.path, route.changefreq, route.priority))
      .join('');

    const xml = [
      '<?xml version="1.0" encoding="UTF-8"?>',
      '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
      xmlBody,
      '</urlset>'
    ].join('');

    res.set('Content-Type', 'application/xml; charset=utf-8');
    res.set('Cache-Control', 'public, max-age=3600');
    return res.send(xml);
  } catch (err) {
    console.error('GET /sitemap.xml failed:', err);
    return res.status(500).type('text/plain').send('Internal server error');
  }
});

if (fs.existsSync(frontendDistPath)) {
  app.use(express.static(frontendDistPath));

  app.use((req, res, next) => {
    if (req.path.startsWith('/api/')) {
      return next();
    }
    return res.sendFile(path.join(frontendDistPath, 'index.html'));
  });
}

app.listen(port, () => {
  console.log(`Server listening on port ${port}`);
});
