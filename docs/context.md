# Contexto del proyecto — marchasdecristo.com

> Última actualización: 2026-06-05
> Documento de entrada para nuevas sesiones.
> Documentos complementarios en esta misma carpeta:
> - [architecture.md](architecture.md) — diagrama, flujos y decisiones arquitectónicas.
> - [technical-debt.md](technical-debt.md) — deuda pendiente.
> - [roadmap.md](roadmap.md) — plan de acción priorizado.
> - [db-analysis.md](db-analysis.md) — auditoría de la base de datos.

---

## 1. Visión general

**Marchas de Cristo** ([marchasdecristo.com](https://marchasdecristo.com)) es una base de datos de **música procesional** española. Permite consultar cuatro entidades relacionadas:

| Entidad | Volumen | Descripción |
|---------|---------|-------------|
| Marchas | 4 212 | Marchas procesionales (título, fecha, dedicatoria, banda de estreno, autores) |
| Autores | 827 | Compositores |
| Bandas | 268 | Formaciones musicales |
| Discos | 431 | Grabaciones (CDs) que contienen marchas |

Relaciones principales:
- `marcha_autor` (4 724 filas) — marcha N:N autor.
- `disco_marcha` (4 478 filas) — disco N:N marcha (con número de pista).
- `marcha.BANDA_ESTRENO` → `banda.ID_BANDA`.
- `disco.BANDADISCO` → `banda.ID_BANDA`.

**Audiencia**: aficionados a la música cofrade, picos en Cuaresma/Semana Santa.  
**Mantenedor único**: Javier Guerra ([@JaviWarSVQ](https://x.com/JaviWarSVQ)).

---

## 2. Stack tecnológico (estado actual)

### Aplicación — Next.js 15 (único servicio)
- **Next.js 15** (App Router) + **React 19** + **TypeScript** + **Tailwind 4** + **DaisyUI 5**.
- **Server Components** para todas las páginas públicas — lectura directa a SQLite, sin HTTP round-trip.
- **Route Handlers** (`app/api/`) para la API REST: login, autocomplete, admin (addMarcha, editMarcha, addAutor).
- **ISR**: detalles cada hora, estadísticas cada 30 min, búsquedas sin caché.
- **Standalone output** para imagen Docker minimalista.
- **Admin**: Client Components protegidos por `middleware.ts` (verifica cookie HMAC inline).

### Base de datos — SQLite embebido
- **SQLite** vía **better-sqlite3** (síncrono, sin pool).
- Singleton lazy en `lib/db.ts` — no abre la BD en `next build`.
- Volumen Docker `mdc-back_mdc-sqlite-data` montado en `/app/data/mdc.db`.
- FTS5 (`marcha_fts`, `autor_fts`) para búsqueda full-text.
- **Sin ORM** — SQL crudo en `lib/api.ts` y Route Handlers.

### Auth/sesiones
- **HMAC-SHA256 propio** implementado en `lib/auth-session.ts` (sin dependencias externas).
- Cookies **HttpOnly + Secure + SameSite=lax**.
- Passwords: **PBKDF2-SHA512 / 210 000 iteraciones** (`pbkdf2$sha512$iters$salt$derived`).
- Rate limiting de login: `Map` en memoria (6 intentos / 15 min, ventana y bloqueo de 15 min).

### Infraestructura
- **VPS**: Ubuntu 22.04, 1 vCPU, 1 GB RAM, 15 GB disco.
- **Nginx** como reverse proxy con TLS (Let's Encrypt). Config en `/etc/nginx/sites-enabled/default`.
- **Docker Compose** en `/var/www/mdc-back/` con un único servicio: `mdc-nextjs` → `127.0.0.1:3000`.
- **Portadas** (`/cover/*.png`): volumen host `/var/www/mdc-assets/cover/` montado como `:ro`. Nginx las sirve directamente.
- **Backup**: cron diario a las 3:00 AM copia `mdc.db` a `/var/backups/`, retención 14 días.

---

## 3. Estructura del repositorio

```
mdc-back/
├── .env.example                # Plantilla de variables de entorno (sin secretos)
├── .gitignore
├── docker-compose.yml          # Un solo servicio: nextjs
├── nginx.conf.example          # Config nginx actual (todo → :3000 excepto /cover/)
├── README.md
│
├── db/
│   └── schema.sql              # Esquema de referencia: tablas, FTS5, índices
│
├── scripts/                    # Utilidades de migración y verificación
│   ├── migrate-mysql-to-sqlite.mjs
│   ├── snapshot-endpoints.mjs
│   ├── diff-snapshots.mjs
│   ├── run-migration-on-vps.sh
│   └── verify-utf8.sh
│
├── nextjs/                     # Toda la aplicación
│   ├── Dockerfile              # Multi-stage: builder (compila better-sqlite3) + runtime Alpine
│   ├── package.json            # next, react, better-sqlite3, daisyui, tailwind
│   ├── next.config.ts          # standalone, serverExternalPackages: ['better-sqlite3']
│   ├── middleware.ts           # Auth guard: protege /dashboard/* verificando cookie HMAC
│   ├── tsconfig.json
│   ├── public/
│   │   └── banner_mdc.png
│   ├── app/
│   │   ├── layout.tsx          # Nav + footer con conteos de BD
│   │   ├── page.tsx            # Home
│   │   ├── globals.css
│   │   ├── robots.ts
│   │   ├── sitemap.ts          # Sitemap generado desde BD, ISR 1h
│   │   ├── marcha/
│   │   │   ├── page.tsx                # Formulario búsqueda
│   │   │   ├── search/page.tsx         # Resultados (SSR, sin caché)
│   │   │   └── [slugAndId]/page.tsx    # Detalle (ISR 1h)
│   │   ├── autor/              (misma estructura)
│   │   ├── banda/              (misma estructura)
│   │   ├── disco/              (misma estructura)
│   │   ├── estadisticas/page.tsx       # ISR 30 min
│   │   ├── login/page.tsx      # Client Component
│   │   ├── dashboard/          # Admin — Client Components
│   │   │   ├── page.tsx
│   │   │   ├── marcha/[id]/page.tsx
│   │   │   ├── marcha/add/page.tsx
│   │   │   └── autor/add/page.tsx
│   │   └── api/
│   │       ├── login/route.ts          # POST login (rate limit + PBKDF2 + MD5 upgrade)
│   │       ├── login/verify/route.ts   # GET verify sesión
│   │       ├── login/logout/route.ts   # POST logout
│   │       ├── autor/fastSearch/route.ts
│   │       ├── banda/fastSearch/route.ts
│   │       └── admin/
│   │           ├── editMarcha/route.ts  # POST (auth + allowlist campos)
│   │           ├── addMarcha/route.ts   # POST (auth + transacción)
│   │           └── addAutor/route.ts    # POST (auth)
│   ├── components/
│   │   ├── CdList.tsx
│   │   └── Timeline.tsx
│   ├── hooks/
│   │   └── useAutocompleteSelect.ts
│   └── lib/
│       ├── db.ts               # Singleton SQLite lazy (no abre BD en build)
│       ├── api.ts              # fetchMarcha, fetchAutor, etc. — lectura directa SQLite
│       ├── auth-session.ts     # signSession, verifySession (HMAC-SHA256)
│       ├── adminApi.ts         # buildMarchaUpdatePayload, tipos admin
│       └── slugify.ts          # slugify, buildDetailPath, extractId
│
└── docs/
    ├── context.md              # Este documento
    ├── architecture.md
    ├── technical-debt.md
    ├── roadmap.md
    ├── db-analysis.md
    ├── redesign-options.md
    └── vps-migration-3b.md     # Guía de despliegue Fase 3b (ejecutada 2026-06-05)
```

---

## 4. Variables de entorno (`.env` en VPS)

```env
SECRET_KEY='...'                # HMAC key — 48+ bytes random, rotar si se compromete
AUTH_COOKIE_NAME=mdc_session
LOGIN_TTL_MS=28800000           # 8 horas
COOKIE_SECURE=true

LOGIN_MAX_ATTEMPTS=6
LOGIN_WINDOW_MS=900000          # 15 min
LOGIN_LOCK_MS=900000            # 15 min
PASSWORD_PBKDF2_ITERATIONS=210000
```

`DB_PATH=/app/data/mdc.db` y `NODE_ENV=production` se inyectan desde `docker-compose.yml`.

---

## 5. Patrones y convenciones

### Buenos patrones a mantener
- **Server Components leen SQLite directamente** — sin HTTP round-trip, sin over-fetching.
- **Allowlist explícita** de campos editables (`EDITABLE_MARCHA_FIELDS`) — previene mass-assignment.
- **Prepared statements** siempre (`dbAll(sql, params)`, `dbRun(sql, params)`).
- **Slug + ID en URLs** (`/marcha/consuelo-gitano-330`) — SEO-friendly, robusto frente a renombres.
- **ISR diferenciado**: detalles 1h, estadísticas 30min, búsquedas sin caché.
- **Helpers de auth puros** (`signSession`/`verifySession`) — sin estado, fáciles de testear.
- **Cookies HttpOnly + Secure + SameSite=lax**.
- **Timing-safe compare** en verificación de passwords y tokens.
- **Auto-upgrade** de contraseñas MD5 → PBKDF2 en primer login.
- **Rate limiting** de login en memoria (aceptable para un solo proceso).
- **Portadas como volumen** — se añaden sin rebuild de imagen.
- **`serverExternalPackages: ['better-sqlite3']`** — evita bundling de módulo nativo.

### Antipatrones pendientes (ver technical-debt.md)
- Sin tests automatizados.
- Sin CI/CD — despliegue manual.
- Sin observabilidad (solo `docker logs`).
- BD sin foreign keys ni índices completos (ver db-analysis.md).

---

## 6. Estado actual (junio 2026)

| Aspecto | Estado |
|---------|--------|
| Stack | Next.js 15 + SQLite — un solo contenedor Docker |
| Express | ✅ Eliminado (Fase 3b desplegada 2026-06-05) |
| Vue SPA | ✅ Eliminada (código muerto borrado 2026-06-05) |
| Seguridad básica | ✅ COOKIE_SECURE, SECRET_KEY rotada, usuarios MD5 reseteados |
| Backups | ✅ Cron diario en VPS |
| Tests | ❌ Ninguno |
| CI/CD | ❌ Despliegue manual |
| Observabilidad | ❌ Solo docker logs |
| BD — índices/FKs | ❌ Pendiente (Fase 4) |
