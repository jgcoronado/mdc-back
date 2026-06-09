# Deuda técnica — marchasdecristo.com

> Última actualización: 2026-06-05 (Bloque 4 completado)
> La auditoría de la BD vive en [db-analysis.md](db-analysis.md). El análisis del panel en [admin-panel.md](admin-panel.md).

## Resumen ejecutivo

| Categoría | Items | Severidad máxima |
|-----------|-------|------------------|
| Bugs de integridad | ~~2~~ 0 | ✅ Resueltos |
| Panel de administración | ~~6~~ 1 | 🟢 Baja |
| BD (SQLite) | ~~5~~ 1 | 🟢 Baja |
| Operativa | 3 | 🟠 Alta |
| Calidad Next.js | ~~2~~ 1 | 🟡 Media |

**Nota**: los bugs de Express (endpoints `/all`, SQL WHERE vacío, getTimeline, catches mudos) y todos los problemas de seguridad básica (credenciales en repo, COOKIE_SECURE, MD5) fueron resueltos en la sesión de 2026-06-05 al migrar a Next.js y completar la Fase 0.

---

---

## 0. Bugs de integridad ✅ Resueltos (2026-06-05)

### 0.1 `addMarcha` sin transacción ✅
- Resuelto: INSERT marcha + INSERT marcha_autor en `dbTransaction()`. También corregido bug de payload: el handler leía `body.marcha` (siempre undefined) en lugar de `body.fieldsToInsert/valuesToInsert`.

### 0.2 `autoresIds` no validados contra la BD ✅
- Resuelto: validación previa con COUNT + 400 `INVALID_AUTHORS`. Sin autores devuelve 400 `AUTHORS_REQUIRED`.

---

## 1. Operativa

### 1.1 Sin CI/CD 🟠
- Despliegue completamente manual: `git fetch` en VPS + `docker compose build` + `up -d` + verificación.
- No hay validación automática antes de subir a producción.
- **Fix**: GitHub Actions con lint + typecheck + build en cada PR; deploy automático a main vía SSH.

### 1.2 Sin observabilidad 🟠
- `docker logs --tail 200` es el único panel disponible.
- No hay alerta si el contenedor cae o la app devuelve errores 5xx.
- **Fix mínimo**:
  - Healthcheck en Docker (`HEALTHCHECK CMD curl -f http://localhost:3000/api/login/verify || exit 1`).
  - UptimeRobot (gratis) apuntando a `https://marchasdecristo.com/api/login/verify`.

### 1.3 Sin tests 🟡
- Ningún test de Route Handlers ni de páginas.
- **Fix**: `vitest` + `supertest` para Route Handlers; snapshot del HTML de `/marcha/<slug>-330` para detectar regresiones SEO.
- Mínimo útil: 20 tests cubriendo golden paths de cada entidad.

---

## 2. Base de datos (SQLite)

### ~~2.1 FK constraints declaradas ausentes~~ ✅ Resuelto (Bloque 2, 2026-06-05)
- `marcha_autor` y `disco_marcha` recreadas con `REFERENCES ... ON DELETE CASCADE`.
- 43 huérfanos eliminados con `scripts/clean-orphans.sql`. PRAGMA FK check: 0 violaciones.
- Nota: `marcha.BANDA_ESTRENO` y `disco.BANDADISCO` no tienen FK declarada porque usan `0` como sentinel.

### ~~2.2 Serialización `GROUP_CONCAT` de autores frágil~~ ✅ Resuelto (Bloque 2, 2026-06-05)
- Sustituido por `json_group_array(json_object('autorId', ID_AUTOR, 'nombre', ...))` en 5 queries de `lib/api.ts`.
- `formatAutor` en `lib/db.ts` simplificado a `JSON.parse`.

### ~~2.3 `autor.NOMBRE_ART` indexado en FTS5 pero no gestionable~~ ✅ Resuelto (Bloque 3, 2026-06-05)
- `NOMBRE_ART` añadido a `INSERTABLE_FIELDS` en `addAutor`, a `EDITABLE_AUTOR_FIELDS` en `editAutor` y al formulario `autor/add/page.tsx`.

### ~~2.4 `FECHA` como `INTEGER` con `0` = "sin fecha"~~ ✅ Resuelto (Bloque 4, 2026-06-05)
- 245 filas migradas a `NULL` vía Node.js + better-sqlite3 en el contenedor (script `normalize-fecha.sql`).
- `normalizeFecha` en `lib/api.ts` simplificada: ahora comprueba `null` en lugar de `0`.

### 2.5 Tablas muertas en la BD 🟢
- `videos` (357 filas) y `users` (0 filas) existen pero ningún Route Handler las usa. `login_autor` fue eliminada en la migración.
- **Fix**: decidir si `videos` se reactiva o se borra (backup previo).

---

## 3. Panel de administración

### ~~3.1 SQL y parámetros expuestos en la UI~~ ✅ Resuelto (Bloque 4, 2026-06-05)
- Bloques `<pre>` con SQL y parámetros eliminados de `marcha/add`, `autor/add` y `marcha/[id]`.

### ~~3.2 Sin audit log~~ ✅ Resuelto (Bloques 2+3, 2026-06-05)
- Tabla `admin_log` creada. `logAdmin()` llamado en addMarcha, editMarcha, addAutor, editAutor, editMarchaAutores.

### ~~3.3 Funciones de edición incompletas~~ ✅ Resuelto (Bloque 3, 2026-06-05)
- `PROVINCIA` añadida al formulario de edición de marcha.
- `editMarchaAutores` creado: DELETE+INSERT atómico, `AutocompleteMulti` pre-cargado con autores actuales.
- `editAutor` creado: endpoint + página `/dashboard/autor/[id]` + nav por ID desde el dashboard.

### ~~3.4 Sin buscador en el dashboard~~ ✅ Resuelto (Bloque 3, 2026-06-05)
- Buscador de texto libre en `/dashboard` usando FTS (`marcha_fts`, `autor_fts`). Resultados clickables (mín. 3 chars, máx. 15) que navegan directamente a la edición.

### ~~3.5 Sin navegación post-creación~~ ✅ Resuelto (Bloque 4, 2026-06-05)
- `marcha/add` y `autor/add` muestran el ID creado con botones "Ir a editar" y "Ver en público" tras éxito.

### 3.6 Gestión de bandas y discos ausente 🟢
- No hay alta ni edición de bandas ni de discos. La banda estreno se asigna por ID numérico en el formulario de edición de marcha, sin ningún autocomplete ni validación.
- **Fix** (baja prioridad): `/dashboard/banda/*` y `/dashboard/disco/*` cuando las entidades principales estén cubiertas.

---

## 4. Calidad del código Next.js

### 4.1 Rate limiting de login en memoria 🟡
- El `Map<string, state>` en `app/api/login/route.ts` se pierde con cada restart del contenedor.
- Aceptable con un solo proceso y tráfico bajo.
- **Fix futuro** (solo si se escala): persistir en una tabla SQLite `login_attempts` con TTL, o usar Redis.

### ~~4.2 `revalidatePath` no se llama tras ediciones admin~~ ✅ Resuelto (Bloque 4, 2026-06-05)
- `editMarcha` llama a `revalidatePath('/marcha', 'layout')` tras cada UPDATE.
- `editAutor` llama a `revalidatePath('/autor', 'layout')` tras cada UPDATE.
