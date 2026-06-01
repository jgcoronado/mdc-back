# Arquitectura — marchasdecristo.com

> Última actualización: 2026-06-01

## 1. Diagrama de componentes

```
                            Internet (HTTPS :443)
                                    │
                                    ▼
                  ┌──────────────────────────────────────┐
                  │       Nginx (host, :443/:80)         │
                  │    TLS termination + reverse proxy   │
                  └──┬──────────┬──────────┬────────────┘
                     │          │          │
         /cover/*    │  /api/*  │  resto (público SSR)
          (directo)  │  /sitemap│  /login  /dashboard
                     │  .xml    │
                     │          │          │
                     │          ▼          ▼
           ┌─────────┘  ┌──────────────┐  ┌──────────────────┐
           │            │   mdc-app    │  │   mdc-nextjs     │
           │            │  Express 5   │  │  (Next.js 15)    │
           │            │  API + admin │  │  :3000           │
           │            │  :8080       │  │  React 19 SSR    │
           │            └──────┬───────┘  │  ISR cache       │
           │                   │          │  admin pages     │
           │               SQL queries   └────────┬─────────┘
           │             (mysql2 prepared)         │ /api/* rewrite
           ▼                   │                   │ → mdc-app:80
   ┌─────────────────┐         ▼                   │
   │  /var/www/      │  ┌────────────────┐         │
   │  mdc-assets/    │  │   mdc-mysql    │◄────────┘
   │  cover/*.png    │  │  MySQL 8.0     │
   │  (volume, ro)   │  │  Docker :3306  │
   └─────────────────┘  │  (solo interno)│
                        └────────────────┘
```

## 2. Flujos de petición

### 2.1 Visita pública a `/marcha/consuelo-gitano-330`
1. **Cliente** → Nginx (`/marcha/...`).
2. **Nginx** → `127.0.0.1:3000` (Next.js).
3. **Next.js** (`app/marcha/[slugAndId]/page.tsx`):
   - Extrae `id=330` de la URL.
   - Comprueba caché ISR (revalidate 3 600 s).
   - Si miss: `fetch('http://app:80/api/marcha/330')` vía `INTERNAL_API_URL`.
4. **Express** (`src/routes/marcha.js` → `GET /:id`):
   - Ejecuta dos consultas en serie: detalle de la marcha + discos relacionados.
   - Usa `pool` (readonly).
5. **MySQL** devuelve filas → Express las formatea (`formatAutor`) → JSON.
6. **Next.js** renderiza HTML completo (SEO-ready) → Nginx → cliente.
7. Si URL no es canónica (sin slug o slug diferente), Next.js responde **307/308** a la canónica.

### 2.2 Búsqueda pública `/marcha/search?titulo=cristo`
- Igual que arriba pero con `revalidate: 0` (sin caché).
- El formulario es un `<form action="/marcha/search" method="GET">` puro — funciona sin JS.

### 2.3 Imagen de portada `/cover/153.png`
- **Nginx** sirve el archivo directamente desde `/var/www/mdc-assets/cover/153.png` con `Cache-Control: public, immutable; expires 30d`.
- No pasa por Node ni Next.js.

### 2.4 Login admin
1. **Cliente** → Nginx → `/login` → **Next.js** (`mdc-nextjs:3000`).
2. Next.js renderiza `app/login/page.tsx` (Client Component).
3. Usuario envía credenciales → `POST /api/login` (rewrite de Next.js → Express).
4. Express verifica rate limit (Map en memoria), busca usuario, verifica password (PBKDF2 o MD5 legacy).
5. Si OK: firma sesión HMAC-SHA256, setea cookie `mdc_session` HttpOnly, devuelve `{ login: true, user, expiresAt }`.
6. Next.js redirige a `/dashboard`.
7. El middleware (`nextjs/middleware.ts`) protege `/dashboard/*`: en cada petición verifica la cookie contra `GET /api/login/verify` via `INTERNAL_API_URL` (servidor a servidor). Si falla → redirect a `/login`.

### 2.5 Edición de marcha (admin)
1. **Cliente** → Nginx → `/dashboard/marcha/:id` → **Next.js** (`mdc-nextjs:3000`).
2. Next.js renderiza `app/dashboard/marcha/[id]/page.tsx` (Client Component).
3. `useEffect` → `GET /api/marcha/:id` → Express (vía rewrite de Next.js).
4. Usuario modifica campos → `buildMarchaUpdatePayload(oldData, apiData)` produce solo los keys que cambiaron.
5. `POST /api/admin/editMarcha` (rewrite → Express) con `{ marchaId, keysToUpdate, valuesToUpdate }`.
6. Express (`adminMarcha.js`):
   - Valida sesión (`getTokenFromRequest` + `verifySession`).
   - Filtra `keysToUpdate` contra `EDITABLE_MARCHA_FIELDS` (allowlist).
   - Construye `UPDATE marcha SET k1=?, k2=? WHERE ID_MARCHA = ?` y ejecuta con `poolAdmin`.
7. Responde con código (`UPDATED` / `NOT_FOUND` / `INVALID_PAYLOAD`).

### 2.6 Sitemap dinámico
- Nginx enruta `/sitemap.xml` a Express.
- Express ejecuta 4 queries en paralelo (`Promise.all`) y emite un XML con todos los detalles + URLs estáticas.
- Cache HTTP: `public, max-age=3600`.

## 3. Decisiones arquitectónicas (ADRs)

### ADR-001 · Dos contenedores: Express + Next.js
- **Contexto**: Migración desde Vue SPA por SEO. Necesidad de SSR sin reescribir API.
- **Decisión**: Mantener Express como API + sitemap, añadir contenedor Next.js con SSR público y páginas admin.
- **Tradeoffs**: Más procesos a vigilar; 2 imágenes Docker; mayor uso de RAM. A cambio: cero reescritura de queries SQL.
- **Estado**: Vigente. El siguiente paso natural es migrar la API a Next.js Route Handlers y apagar Express (ver Fase 3 completa en [roadmap.md](roadmap.md)).

### ADR-002 · MySQL en contenedor Docker local (antes: helioho.st)
- **Contexto**: La BD nació en hosting compartido. Migrada al VPS en junio 2026.
- **Decisión**: Contenedor `mdc-mysql` (MySQL 8.0) en el mismo Docker Compose, volumen `mdc-back_mysql_data` para persistencia. Puerto 3306 solo interno. `DB_HOST=mysql` en `.env`.
- **Tradeoffs**: Dependencia del VPS para la BD; requiere backup explícito. A cambio: sin latencia de red externa, sin dependencia de terceros, coste cero.
- **Estado**: Vigente. Candidato a SQLite si se quiere simplificar aún más (ver [roadmap.md](roadmap.md) §6.1).

### ADR-003 · Sesiones firmadas HMAC-SHA256 propias (no JWT)
- **Contexto**: Necesidad mínima de auth solo para admin.
- **Decisión**: Implementar firma/verificación con `crypto` nativo, sin dependencias externas.
- **Tradeoffs**: Cero deps; control total. A cambio: hay que mantener el código y revisar primitives.
- **Estado**: Bien implementado (timing-safe compare, validación de longitud, expiración). Mantener.

### ADR-004 · Allowlist de campos editables y prepared statements
- **Contexto**: Endpoint genérico `editMarcha` que recibe arrays paralelos `keys/values`.
- **Decisión**: Validar cada clave contra `EDITABLE_MARCHA_FIELDS` y solo permitir las conocidas. Construir el SQL dinámico con esas claves y bindear valores.
- **Tradeoffs**: Algo de boilerplate. A cambio: imposible SQL injection y mass assignment.
- **Estado**: Modelo a replicar para `editAutor` y futuros endpoints.

### ADR-005 · URLs `slug-id` con redirect 301 desde id legado
- **Contexto**: SEO-friendly URLs, soportar enlaces antiguos.
- **Decisión**: Express genera `/marcha/<slug>-<id>` en sitemap. Next.js valida que la URL recibida coincida con la canónica y redirige si no.
- **Tradeoffs**: Si cambia el título, las URLs viejas siguen funcionando (un redirect extra).
- **Estado**: Bien diseñado. Mantener.

### ADR-006 · Imágenes de portada fuera del contenedor
- **Contexto**: Subir portadas sin rebuild de la imagen.
- **Decisión**: Volumen `host:/var/www/mdc-assets/cover:ro` montado en `app:/app/public/cover`. Nginx sirve directamente desde el host (no pasa por el contenedor en producción).
- **Tradeoffs**: Hay que recordar montar el volumen. A cambio: contenedor inmutable, despliegue ágil de assets.
- **Estado**: Excelente patrón. Mantener.

### ADR-007 · ISR (Incremental Static Regeneration) en lugar de SSR puro
- **Contexto**: 4 200 marchas, datos casi inmutables.
- **Decisión**: `revalidate: 3600` para detalles, `revalidate: 1800` para estadísticas, `revalidate: 0` para búsquedas.
- **Tradeoffs**: Una edición tarda hasta 1 h en propagarse al público. A cambio: latencia mínima para visitantes.
- **Estado**: Bien. Si se quiere instantáneo, añadir `revalidatePath('/marcha/...')` en `adminMarcha.js` tras un `UPDATE` (necesita exponer el endpoint en Next.js o llamar a su API interna).

## 4. Patrones recurrentes en el código

### Backend
- **Router por entidad** (`marcha.js`, `autor.js`, …). Cada router declara `/`, `/search`, `/:id` y opcionalmente `/fastSearch`.
- **Helpers genéricos**: `resolveQuery(sql, params)` devuelve `{ rowsReturned, data }`; `poolExecute(sql, params)` devuelve `[results, fields]`.
- **`formatAutor`** transforma `GROUP_CONCAT('id#nombre apellidos' SEPARATOR '|')` en `[{ autorId, nombre }, ...]`. Patrón consistente que evita N+1.
- **`async/await` con try/catch** por endpoint, log a `console.error/console.log`. La consistencia es desigual (algunos catch silencian errores).
- **Allowlist + normalización** (`normalizeValue`) en endpoints admin.

### Frontend público (Next.js)
- **Server Components puros** en cada page — sin client components excepto cuando hay interactividad (no la hay en el público).
- **`generateMetadata`** dinámico por detalle (título + descripción con datos de la entidad).
- **`fetchEntity(id).catch(() => null)` + `notFound()`** — manejo defensivo limpio.
- **`buildDetailPath` + `extractId`** centralizados en `lib/slugify.ts`.
- **Redirect a canónica** dentro del componente con `redirect()`.
- **Tipado fuerte** (`MarchaDetail`, `AutorRef`, etc. en `lib/api.ts`).

### Frontend admin (Next.js — migrado de Vue en junio 2026)
- **Client Components** (`'use client'`) para todas las páginas admin (interactividad pura).
- **`buildMarchaUpdatePayload`** portado a `lib/adminApi.ts` — produce el delta + preview SQL antes de guardar.
- **`useAutocompleteSelect`** hook React en `hooks/useAutocompleteSelect.ts` (equivalente al composable Vue).
- **`middleware.ts`** protege `/dashboard/*` verificando cookie servidor a servidor (sin exponer lógica al cliente).
- **Estado global**: ninguno. Cada página fetcha lo suyo on mount. Sin localStorage (la cookie httpOnly es suficiente).

## 5. Buenas prácticas detectadas

1. **Separación readonly/write** en pools MySQL.
2. **Allowlist explícita** de columnas editables.
3. **Prepared statements** universales.
4. **Timing-safe compare** en verificación de tokens y contraseñas.
5. **Auto-upgrade transparente** de contraseñas MD5 → PBKDF2.
6. **Cookies HttpOnly + sameSite=lax + path=/**.
7. **`X-Forwarded-*` correctamente propagado** + `trust proxy`.
8. **CORS con allowlist explícita** y verificación dinámica del origin.
9. **ISR + Cache-Control en sitemap**.
10. **Standalone build de Next.js** → imagen Docker pequeña.
11. **`generateMetadata` con `metadataBase`** para URLs absolutas.
12. **Volumen separado para assets mutables**.
13. **Multi-stage Docker builds** (Vue build → Node runtime; Next.js builder → standalone).
14. **`USER node`** en los Dockerfile — contenedores no-root.

## 6. Antipatrones y debilidades

Detallados en [technical-debt.md](technical-debt.md). Resumen:

- Catches que silencian errores (`console.log(err)` sin `res.status(500)`).
- `LIKE ?` sobre IDs enteros (debería ser `=`).
- `%term%` en `MATCH(...) AGAINST(?)`.
- `WHERE ` vacío cuando no hay filtros.
- `db.connection` que no existe (rompe `/all`).
- `forEach(async)` en `getTimeline`.
- Sin transacciones en altas multi-tabla.
- Sin tests, sin CI/CD, sin observabilidad.
- `.env` con credenciales en el repo.
- Frontend Vue público duplicado e inerte.
