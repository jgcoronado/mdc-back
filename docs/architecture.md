# Arquitectura — marchasdecristo.com

> Última actualización: 2026-06-05

## 1. Diagrama de componentes

```
                        Internet (HTTPS :443)
                                │
                                ▼
              ┌──────────────────────────────────────┐
              │       Nginx (host, :443/:80)         │
              │    TLS termination + reverse proxy   │
              └──┬───────────────────────────────────┘
                 │                         │
     /cover/*   │          todo lo demás  │
      (directo) │                         ▼
                │         ┌───────────────────────────┐
                │         │        mdc-nextjs         │
                │         │      (Next.js 15)         │
                │         │       :3000               │
                │         │   React 19 SSR/ISR        │
                │         │   Route Handlers (API)    │
                │         │   Admin (Client Components│
                │         │   + middleware auth)      │
                │         └────────────┬──────────────┘
                │                      │
                │          better-sqlite3 (síncrono)
                │                      ▼
  ┌─────────────┘          ┌───────────────────────┐
  │                        │   mdc-sqlite-data     │
  │  /var/www/             │   (Docker volume)     │
  │  mdc-assets/cover/     │   /app/data/mdc.db    │
  │  (volumen host, :ro)   └───────────────────────┘
  └────────────────────────
```

**Un solo contenedor Node.js** sirve páginas públicas, admin y API. No hay servicios de BD separados en el compose.

---

## 2. Flujos de petición

### 2.1 Visita pública a `/marcha/consuelo-gitano-330`
1. **Cliente** → Nginx (`/marcha/...`).
2. **Nginx** → `127.0.0.1:3000` (Next.js).
3. **Next.js** (`app/marcha/[slugAndId]/page.tsx`, Server Component):
   - Extrae `id=330` del slug.
   - Comprueba caché ISR (`revalidate: 3600`).
   - Si miss: llama a `fetchMarcha(330)` en `lib/api.ts`.
4. **`lib/api.ts`** ejecuta `dbAll(sql, [id])` directamente contra SQLite (sin HTTP).
5. **SQLite** devuelve filas → tipado en TypeScript → HTML renderizado con metadatos SEO.
6. **Nginx** → cliente.
7. Si URL no canónica (slug distinto o solo ID), Next.js responde **308** a la canónica.

### 2.2 Búsqueda pública `/marcha/search?titulo=cristo`
- Server Component sin caché (`revalidate: 0`).
- `lib/api.ts` ejecuta query FTS5 (`MATCH ? ORDER BY rank`) directamente.

### 2.3 Imagen de portada `/cover/153.png`
- **Nginx** sirve desde `/var/www/mdc-assets/cover/153.png` con `Cache-Control: public, immutable; expires 30d`.
- No pasa por Node ni Next.js.

### 2.4 Login admin
1. **Cliente** → Nginx → `/login` → **Next.js** (Client Component).
2. Usuario envía credenciales → `POST /api/login` (Route Handler).
3. Route Handler: verifica rate limit (Map en memoria), busca usuario en SQLite, verifica password (PBKDF2 o MD5 legacy con auto-upgrade).
4. Si OK: `signSession` (HMAC-SHA256) → cookie `mdc_session` HttpOnly + Secure → `{ login: true }`.
5. Next.js redirige a `/dashboard`.
6. El `middleware.ts` protege `/dashboard/*`: en cada petición verifica la cookie HMAC inline (`runtime = 'nodejs'`). Si falla → redirect a `/login`.

### 2.5 Edición de marcha (admin)
1. **Cliente** → Nginx → `/dashboard/marcha/:id` → **Next.js** (Client Component).
2. On mount: `GET /api/marcha/:id` → Server Component data fetch vía `lib/api.ts`.
3. Usuario modifica → `buildMarchaUpdatePayload(old, new)` produce solo los campos que cambiaron.
4. `POST /api/admin/editMarcha` (Route Handler):
   - Verifica sesión (`verifySession` sobre cookie).
   - Filtra campos contra `EDITABLE_MARCHA_FIELDS` (allowlist).
   - `dbRun(UPDATE marcha SET ... WHERE id = ?, params)`.
5. Responde `{ code: 'UPDATED' | 'NOT_FOUND' | 'INVALID_PAYLOAD' }`.

### 2.6 Sitemap dinámico
- `app/sitemap.ts` (Server Component, `revalidate: 3600`).
- Lee marchas, autores, bandas y discos directamente de SQLite.
- Next.js genera el XML y lo cachea 1 hora.

---

## 3. Decisiones arquitectónicas (ADRs)

### ADR-001 · Un solo contenedor: Next.js sirve todo ✅ Vigente
- **Contexto**: Fase 3b (junio 2026) migró la API de Express a Route Handlers. Express fue apagado.
- **Decisión**: `mdc-nextjs` es el único proceso Node. Los Server Components leen SQLite directamente (sin HTTP round-trip). Los Route Handlers exponen la API REST para el admin y los autocompletes.
- **Tradeoffs**: Menor complejidad operativa (1 contenedor, 1 imagen, 1 log). A cambio: todo el código vive en Next.js; si se necesita escalar la API independientemente, hay que extraerla.
- **Anterior**: ADR-001 original documentaba dos contenedores (Express + Next.js). Superado.

### ADR-002 · SQLite embebido (antes: MySQL en contenedor) ✅ Vigente
- **Contexto**: MySQL migrado de HelioHost a Docker (junio 2026), luego a SQLite embebido en la misma sesión.
- **Decisión**: `better-sqlite3` (síncrono) montado en volumen Docker. Sin servidor de BD separado.
- **Tradeoffs**: Consumo de RAM ~150 MB menos. Backups = copiar un archivo. Sin concurrencia de escritura en múltiples procesos (aceptable: un solo worker). Sin separación readonly/write (los Route Handlers admin usan el mismo singleton).
- **`serverExternalPackages: ['better-sqlite3']`** en `next.config.ts` evita que webpack intente bundlear el módulo nativo.

### ADR-003 · Sesiones firmadas HMAC-SHA256 propias (sin JWT) ✅ Vigente
- **Decisión**: `signSession`/`verifySession` en `lib/auth-session.ts` con `node:crypto`. Sin dependencias externas.
- **Tradeoffs**: Cero deps, control total. A cambio: hay que mantener el código.
- **Implementación**: timing-safe compare, validación de longitud y expiración.

### ADR-004 · Allowlist de campos editables ✅ Vigente
- **Decisión**: `EDITABLE_MARCHA_FIELDS` en `editMarcha/route.ts`. Cada clave del payload se valida antes de construir el SQL dinámico.
- **Previene**: SQL injection por campo y mass-assignment. Modelo a replicar para futuros endpoints de edición.

### ADR-005 · URLs `slug-id` con redirect desde id legado ✅ Vigente
- **Decisión**: URLs `/marcha/<slug>-<id>`. Si llega `/<id>` sin slug o con slug incorrecto, `redirect()` a la canónica.
- **Tradeoffs**: Un redirect extra si el título cambia. A cambio: SEO estable y enlaces históricos funcionan.

### ADR-006 · Portadas fuera del contenedor ✅ Vigente
- **Decisión**: Volumen `host:/var/www/mdc-assets/cover:ro` → `container:/app/public/cover`. Nginx sirve directamente.
- **Tradeoffs**: Hay que recordar montar el volumen en cada VPS. A cambio: añadir portadas sin rebuild.

### ADR-007 · ISR diferenciado ✅ Vigente
- **Decisión**: `revalidate: 3600` para detalles, `revalidate: 1800` para estadísticas, `revalidate: 0` para búsquedas, `revalidate: 3600` para sitemap.
- **Tradeoffs**: Una edición admin tarda hasta 1h en propagarse. Fix opcional: `revalidatePath('/marcha/...')` en los Route Handlers admin tras un UPDATE.

### ADR-008 · Middleware de auth inline (`runtime = 'nodejs'`) ✅ Vigente
- **Contexto**: El middleware de Next.js corre por defecto en Edge Runtime, que no tiene `node:crypto`.
- **Decisión**: `export const runtime = 'nodejs'` en `middleware.ts`. Verifica la cookie HMAC directamente sin llamar a ningún endpoint.
- **Tradeoffs**: El middleware no corre en Edge (no hay cold start ultra-rápido). A cambio: lógica de auth centralizada y sin dependencias externas.

---

## 4. Patrones del código

### Server Components (páginas públicas)
- **Lectura directa a SQLite** — `lib/api.ts` exporta funciones síncronas que usan `dbAll`.
- **`generateMetadata`** dinámico por detalle (título + descripción).
- **`fetchEntity(id) → notFound()`** como patrón estándar de manejo de 404.
- **`buildDetailPath` + `extractId`** centralizados en `lib/slugify.ts`.
- **Redirect a canónica** con `redirect()` dentro del Server Component.

### Route Handlers (API)
- **Auth guard reutilizable**: `verifySession(cookie)` al inicio de cada handler admin.
- **Allowlist + normalización** en endpoints de escritura.
- **Respuestas tipadas**: `{ code: string }` para admin, `{ rowsReturned, data }` para búsquedas.
- **Rate limiting stateful en memoria** solo en `/api/login` — aceptable con un proceso.

### Client Components (admin)
- Sin estado global. Cada página fetcha sus datos on mount.
- **`useAutocompleteSelect`** hook para los campos con autocompletado de autores/bandas.
- **`buildMarchaUpdatePayload`** — produce el delta (solo campos modificados) antes de enviar.

---

## 5. Buenas prácticas vigentes

1. **Lectura directa a BD desde Server Components** — sin N+1, sin over-fetching.
2. **Allowlist explícita** de columnas editables/insertables.
3. **Prepared statements** en todas las queries (`dbAll(sql, params)`, `dbRun(sql, params)`).
4. **Timing-safe compare** en verificación de passwords y tokens.
5. **Auto-upgrade transparente** MD5 → PBKDF2 en primer login.
6. **Cookies HttpOnly + Secure + SameSite=lax**.
7. **Cabeceras `X-Forwarded-*`** correctamente propagadas por Nginx.
8. **ISR + Cache-Control** diferenciados por tipo de contenido.
9. **Standalone build de Next.js** → imagen Docker mínima.
10. **`generateMetadata` con `metadataBase`** → URLs absolutas correctas.
11. **Volumen separado para assets mutables** (portadas).
12. **Multi-stage Docker build** (builder con python3/g++ para compilar better-sqlite3 → runtime Alpine sin herramientas de build).
13. **Singleton SQLite lazy** — no abre la BD en `next build`, solo en runtime.

---

## 6. Deuda técnica pendiente

Detallada en [technical-debt.md](technical-debt.md). Resumen:

- Sin tests, sin CI/CD, sin observabilidad (Fase 5).
- BD sin foreign keys, índices incompletos, motores mixtos (Fase 4, solo aplica si se vuelve a MySQL; en SQLite ya está resuelto parcialmente por FTS5 y los índices del schema.sql).
