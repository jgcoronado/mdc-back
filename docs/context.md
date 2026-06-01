# Contexto del proyecto — marchasdecristo.com

> Generado: 2026-06-01 · Documento de entrada para nuevas sesiones.
> Documentos complementarios en esta misma carpeta:
> - [architecture.md](architecture.md) — diagrama, flujos y decisiones arquitectónicas.
> - [technical-debt.md](technical-debt.md) — bugs activos, deuda y código muerto.
> - [redesign-options.md](redesign-options.md) — análisis de cambios profundos (lenguaje, BD, hosting).
> - [roadmap.md](roadmap.md) — plan de acción priorizado.
> - [db-analysis.md](db-analysis.md) — auditoría de la base de datos (preexistente).

## 1. Visión general

**Marchas de Cristo** ([marchasdecristo.com](https://marchasdecristo.com)) es una base de datos de **música procesional** española. Permite consultar de forma rápida y eficiente desde navegador (PC, móvil o tablet) cuatro entidades relacionadas:

| Entidad | Volumen | Descripción |
|---------|---------|-------------|
| Marchas | 4 212 | Marchas procesionales (título, fecha, dedicatoria, banda de estreno, autores) |
| Autores | 827 | Compositores |
| Bandas | 268 | Formaciones musicales (cornetas y tambores, agrupaciones musicales) |
| Discos | 431 | Grabaciones (CDs) que contienen marchas |

Relaciones:
- `marcha_autor` (4 724 filas) — marcha N:N autor.
- `disco_marcha` (4 478 filas) — disco N:N marcha (con número de pista).
- `marcha.BANDA_ESTRENO` → `banda.ID_BANDA` (banda que estrenó la marcha).
- `disco.BANDADISCO` → `banda.ID_BANDA` (banda titular del disco).

**Audiencia**: aficionados a la música cofrade, especialmente en Semana Santa. **Tráfico real: bajo**, con picos previstos en Cuaresma/Semana Santa (febrero-abril).

**Autor / mantenedor único**: Javier Guerra ([@JaviWarSVQ](https://x.com/JaviWarSVQ)).

## 2. Stack tecnológico

### Backend API
- **Node.js 22** + **Express 5** (ESM, `"type": "module"`).
- **mysql2/promise** con pool de conexiones (límite 10).
- **dos pools separados** — `pool` (usuario `jaguerra27_readonly`) y `poolAdmin` (`jaguerra27_user`) para segregación de privilegios.
- Sesiones firmadas con **HMAC-SHA256 propio** (no JWT externo). Cookies HttpOnly + `Bearer` fallback.
- Passwords con **PBKDF2-SHA512 / 210 000 iteraciones**, auto-upgrade desde MD5 legado en primer login.
- CORS configurable por variable de entorno con allowlist.
- Rate limiting de login: `Map` en memoria (6 intentos / 15 min).

### Frontend público
- **Next.js 15** (App Router) + **React 19** + **TypeScript** + **Tailwind 4** + **DaisyUI 5**.
- SSR con **ISR** (revalidación periódica): detalles cada hora, estadísticas cada 30 min, búsquedas sin caché.
- Standalone output (`next.config.ts`) para contenedor Docker minimalista.
- Las URLs de detalle son `/<entidad>/<slug>-<id>` (SEO-friendly), con redirect 301 desde `/<entidad>/<id>` legado.

### Frontend admin (legacy + activo)
- **Vue 3** + **Vite 7** + **Vue Router 4** + **axios** + **Tailwind 4** + **DaisyUI**.
- Servido como SPA estática desde Express (`/public/index.html`) tras `npm run build` en `frontend/`.
- Rutas vivas: `/login`, `/dashboard`, `/dashboard/marcha/:id`, `/dashboard/marcha/add`, `/dashboard/autor/add`.
- **Las rutas públicas (`/marcha`, `/banda`, etc.) están duplicadas como restos de la migración**. Nginx ya no las sirve, pero el código sigue compilando en el bundle (ver [technical-debt.md](technical-debt.md)).

### Base de datos
- **MySQL** alojado en el VPS (en el host, no en contenedor). Accesible desde Docker en `172.17.0.1:3306`.
- Motor mixto: **MyISAM** (marcha, autor, banda, marcha_autor, videos) e **InnoDB** (disco, disco_marcha, usuarios, login_autor, users).
- Collation principalmente `utf8_spanish_ci` con excepciones (ver [db-analysis.md](db-analysis.md)).
- **No hay foreign keys**, no hay migraciones, no hay ORM. SQL crudo en cada ruta.

### Infraestructura
- **VPS**: 104.245.245.27, Ubuntu 22.04, 1 vCPU, 1 GB RAM, 15 GB disco, 2 GB swap.
- **Nginx** como reverse proxy con TLS (Let's Encrypt).
- **Docker Compose** en `/var/www/mdc-back/` con dos servicios:
  - `mdc-app` (Express + admin Vue) → `127.0.0.1:8080`.
  - `mdc-nextjs` (público SSR) → `127.0.0.1:3000`.
- **Imágenes de portada** (`/cover/*.png`): volumen montado desde `/var/www/mdc-assets/cover/` (no se empaquetan en la imagen). Nginx las sirve directamente con cache `Cache-Control: public, immutable`.
- **Acceso SSH**: clave en `~/.ssh/mdc_vps`, usuario `claude` con sudo sin contraseña.

### Hosting alternativo desactivado
- Existe `.vercel/` y `vercel.json` — abandonados.
- `ecosystem.config.js` de **PM2** — abandonado (sustituido por Docker).
- `.htaccess` para **Apache** — abandonado (era de la era de hosting compartido en helioho.st). La BD sigue accesible públicamente en `jaguerra27.helioho.st:3306` como herencia.

## 3. Estructura del repositorio

```
mysql-simple/
├── index.js                    # Bootstrap Express (sirve API, SPA admin, sitemap, robots)
├── package.json                # Backend deps (express, mysql2, cors, dotenv, nodemon)
├── Dockerfile                  # Multi-stage: build Vue + runtime Node
├── docker-compose.yml          # Producción: app + nextjs
├── docker-compose-local.yml    # Local: solo app
├── nginx.conf.example          # Ejemplo de configuración del reverse proxy
├── .env                        # Credenciales (¡versionado, ver deuda!)
├── README.md
│
├── src/                        # Backend
│   ├── db.js                   # Pool readonly
│   ├── helpers/
│   │   ├── index.js            # resolveQuery, poolExecute, formatAutor
│   │   ├── admin.js            # Pool de escritura (poolAdmin)
│   │   └── authSession.js      # Firma HMAC-SHA256, cookies, getTokenFromRequest
│   └── routes/
│       ├── login.js            # POST /, GET /verify, POST /logout
│       ├── marcha.js           # GET /search, /:id, /:id/disco
│       ├── autor.js            # GET /fastSearch, /search, /:id
│       ├── banda.js            # GET /fastSearch, /all (roto), /search, /:id
│       ├── disco.js            # GET /all (roto), /search, /:id
│       ├── stats.js            # GET /masAutor, /masDedica, /masEstreno, /masGrabada, /estado
│       ├── admin.js            # Monta los dos sub-routers admin
│       ├── adminMarcha.js      # POST /editMarcha, /addMarcha
│       └── adminAutor.js       # POST /addAutor
│
├── frontend/                   # Vue admin SPA (con código público legacy)
│   ├── package.json
│   ├── vite.config.js
│   └── src/
│       ├── App.vue
│       ├── main.js
│       ├── router/
│       │   ├── index.js        # Rutas admin con guard requiresAuth
│       │   └── viewPages.js    # Rutas públicas legacy (sin uso real)
│       ├── components/
│       │   ├── admin/          # Login, Dashboard, MarchaEdit, MarchaAdd, AutorAdd, Autocomplete*
│       │   ├── molecules/      # CdList, CdCard, DbCount, Timeline (legacy)
│       │   └── *.vue           # Public legacy: Home, *Detail, *List, *Search, Stats
│       ├── services/
│       │   ├── authService.js  # login/logout/isAuthenticated
│       │   ├── edits.js        # buildMarchaUpdatePayload, executeMarchaUpdate, etc.
│       │   ├── getData.js      # Helpers axios para rutas públicas (legacy)
│       │   ├── autor.js
│       │   ├── goTo.js
│       │   └── admin.js
│       └── composables/
│           └── useAutocompleteSelect.js
│
├── nextjs/                     # Frontend público SSR
│   ├── package.json
│   ├── next.config.ts          # Standalone + rewrites /api → INTERNAL_API_URL
│   ├── Dockerfile              # Multi-stage standalone
│   ├── tsconfig.json
│   ├── public/
│   │   └── banner_mdc.png
│   ├── app/
│   │   ├── layout.tsx          # Nav + footer con conteos
│   │   ├── page.tsx            # Home
│   │   ├── globals.css
│   │   ├── robots.ts
│   │   ├── marcha/
│   │   │   ├── page.tsx                # Formulario búsqueda
│   │   │   ├── search/page.tsx         # Resultados (SSR)
│   │   │   └── [slugAndId]/page.tsx    # Detalle (ISR 1h)
│   │   ├── autor/  (misma estructura)
│   │   ├── banda/  (misma estructura)
│   │   ├── disco/  (misma estructura)
│   │   └── estadisticas/page.tsx       # ISR 30 min
│   ├── components/
│   │   ├── CdList.tsx
│   │   └── Timeline.tsx
│   └── lib/
│       ├── api.ts              # fetchMarcha, fetchAutor, etc. con ISR
│       ├── auth.ts             # login/logout/verifySession (no usado aún — el admin sigue en Vue)
│       ├── adminApi.ts         # buildMarchaUpdatePayload, etc. (preparado para migrar admin)
│       └── slugify.ts          # slugify, buildDetailPath, extractId
│
└── docs/                       # Esta carpeta
    ├── context.md              # Este documento
    ├── architecture.md
    ├── technical-debt.md
    ├── redesign-options.md
    ├── roadmap.md
    └── db-analysis.md
```

## 4. Convenciones y patrones detectados

### Buenos patrones que conviene mantener
- **Segregación de privilegios de BD** (`readonly` + `user`) — defensa en profundidad real.
- **Allowlist explícita de campos editables/insertables** (`EDITABLE_MARCHA_FIELDS`, `INSERTABLE_MARCHA_FIELDS`) — previene mass-assignment.
- **Prepared statements** uniformes (`pool.execute(sql, params)`).
- **Slug + ID en URLs** (`/marcha/consuelo-gitano-330`) — SEO-friendly, robusto frente a renames porque el ID es la verdad.
- **ISR con revalidación corta para detalles, sin caché para búsqueda** — buen equilibrio para datos casi inmutables.
- **`SITE_URL` canónica + `metadataBase`** — emite siempre URLs absolutas correctas.
- **Helpers de auth puros sin estado** (`signSession`/`verifySession`) — fáciles de testear.
- **Reverse proxy con TLS terminado en nginx + cabeceras `X-Forwarded-*` correctas + `trust proxy`** — bien hecho.
- **Imágenes servidas como volumen, no empaquetadas** — permite añadir portadas sin rebuild.

### Antipatrones y vicios recurrentes
- **`LIKE ?` sobre claves primarias enteras** — funciona pero confunde y es ligeramente más lento que `=`.
- **`%termino%` pasado a `MATCH(...) AGAINST(?)`** — los `%` son sintaxis LIKE, irrelevantes en FULLTEXT, degradan los resultados.
- **`WHERE ` vacío** — si no hay filtros, los endpoints `/search` generan SQL inválido (debería iniciarse con `1=1`).
- **`db.connection.query` en `banda.js` y `disco.js`** — `db.js` solo expone `pool`. Estas rutas crashean (ver [technical-debt.md](technical-debt.md)).
- **`forEach` con función `async`** — usado en `getTimeline` y rompe la lógica (la función vuelve antes de que terminen los awaits).
- **Sin transacciones** en altas que tocan dos tablas (`addMarcha` inserta en `marcha` y luego en `marcha_autor`).
- **Catches que silencian errores con `console.log(err)`** y no devuelven respuesta — el cliente se queda colgado.
- **Configuración duplicada** (`router/index.js` en Vue + `app/*/page.tsx` en Next.js + `.htaccess`).
- **`.env` con credenciales reales en el repo**.

## 5. Entornos

### Local (desarrollo)
- `docker-compose-local.yml` levanta solo el contenedor `app` (Express + admin Vue + Next.js no incluido).
- Vue admin desarrollo: `cd frontend && npm run dev` (Vite en `:5173`).
- Next.js desarrollo: `cd nextjs && npm run dev` (en `:3000`).
- Backend desarrollo: `npm start` en raíz (nodemon).
- Variable `VITE_BASE_URL` y `INTERNAL_API_URL` apuntan a la API según contexto.

### Producción
- VPS con docker compose ejecutando `mdc-app` + `mdc-nextjs`.
- Despliegue: `tar+scp` de los archivos modificados, luego `docker compose build && docker compose up -d`.
- Después de build: `docker system prune -f` (el disco se llena rápido con 15 GB).

### Variables de entorno (`.env`)
```
DB_HOST=jaguerra27.helioho.st       # Aún externo, no en el VPS
DB_PORT=3306
DB_USER=jaguerra27_readonly
DB_PASSWORD=...                     # Versionado, debe rotarse
DB_NAME=jaguerra27_mdc
DB_USER_ADMIN=jaguerra27_user
DB_PASSWORD_ADMIN=...               # Versionado, debe rotarse
CORS_ORIGINS=https://marchasdecristo.com,http://localhost:8080,http://localhost:5173
SECRET_KEY=...                      # 40 chars random, OK
COOKIE_SECURE=false                 # ⚠ Debería ser true en producción
LOGIN_MAX_ATTEMPTS=6
LOGIN_WINDOW_MS=900000
LOGIN_LOCK_MS=900000
PASSWORD_PBKDF2_ITERATIONS=210000
```

## 6. Estado actual (junio 2026)

- **Migración a Next.js SSR completada** el 2026-05-20 — Google Search Console ya puede indexar correctamente.
- **Frontend Vue público es código muerto** servido junto al admin — se decidió migrar también el admin a Next.js (ver [roadmap.md](roadmap.md)).
- **Bugs activos** en `/api/banda/all`, `/api/disco/all`, búsquedas sin parámetros y `getTimeline` (ver [technical-debt.md](technical-debt.md)).
- **2 usuarios de admin aún con MD5** en `usuarios` (auto-upgrade pendiente de su próximo login).
- **Sin tests** automatizados.
- **Sin CI/CD** — despliegue manual.
- **Sin monitoring** — logs vía `docker logs`, sin alertas.
