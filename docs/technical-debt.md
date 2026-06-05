# Deuda técnica — marchasdecristo.com

> Última actualización: 2026-06-05 (sesión 2 — análisis panel + BD)
> La auditoría de la BD vive en [db-analysis.md](db-analysis.md). El análisis del panel en [admin-panel.md](admin-panel.md).

## Resumen ejecutivo

| Categoría | Items | Severidad máxima |
|-----------|-------|------------------|
| Bugs de integridad | ~~2~~ 0 | ✅ Resueltos |
| Panel de administración | 6 | 🟠 Alta |
| BD (SQLite) | 5 | 🟠 Alta |
| Operativa | 3 | 🟠 Alta |
| Calidad Next.js | 2 | 🟡 Media |

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

### 2.1 FK constraints declaradas ausentes 🟠
- `lib/db.ts:16` activa `PRAGMA foreign_keys = ON`, pero las tablas no tienen `FOREIGN KEY` en sus `CREATE TABLE`. El PRAGMA no tiene nada que verificar: la integridad referencial no está siendo forzada en tiempo real.
- Hay 43 huérfanos heredados de la migración MySQL (ver [db-analysis.md](db-analysis.md)).
- **Fix**: (1) limpiar los 43 huérfanos con script, (2) añadir FK constraints en el schema, (3) migrar la BD con script ALTER o recreación de tablas.

### 2.2 Serialización `GROUP_CONCAT` de autores frágil 🟠
- `lib/api.ts` recupera los autores como `"1#Nombre Apellido|2#Otro"` y `lib/db.ts:formatAutor` los parsea por `|` y `#`. Si un nombre contiene alguno de esos caracteres, el parseo se corrompe silenciosamente.
- **Fix**: sustituir `GROUP_CONCAT` por `json_group_array(json_object(...))` en SQLite. `better-sqlite3` devuelve el JSON como string y se parsea con `JSON.parse`.

### 2.3 `autor.NOMBRE_ART` indexado en FTS5 pero no gestionable 🟠
- `schema.sql:56-60` sincroniza `NOMBRE_ART` al índice FTS5. `addAutor/route.ts:6` no incluye `NOMBRE_ART` en `INSERTABLE_FIELDS`. Los compositores con nombre artístico no se pueden registrar correctamente desde el panel.
- **Fix**: añadir `NOMBRE_ART` a `INSERTABLE_FIELDS` en `addAutor` y al futuro `editAutor`.

### 2.4 `FECHA` como `INTEGER` con `0` = "sin fecha" 🟡
- Semánticamente mejor como `INTEGER NULL`. `normalizeFecha` en `lib/api.ts:92-95` convierte `0`/`''` a `'s/f'` como parche.
- **Fix**: actualizar las filas `WHERE FECHA = 0` a `NULL` con script, simplificar `normalizeFecha`.

### 2.5 Tablas muertas en la BD 🟢
- `videos` (357 filas) y `users` (0 filas) existen pero ningún Route Handler las usa. `login_autor` fue eliminada en la migración.
- **Fix**: decidir si `videos` se reactiva o se borra (backup previo).

---

## 3. Panel de administración

### 3.1 SQL y parámetros expuestos en la UI 🟡
- Los formularios de alta (`marcha/add`, `autor/add`) y edición muestran la SQL preparada y los parámetros en pantalla. Información innecesaria que puede quedar en capturas de pantalla.
- **Fix**: eliminar el bloque de previsualización SQL o ponerlo detrás de un toggle oculto por defecto.

### 3.2 Sin audit log 🟠
- No hay registro de qué cambió, cuándo y con qué valor anterior. Si algo se corrompe en la BD, no hay trazabilidad.
- **Fix**: tabla `admin_log (id, accion, tabla, id_registro, usuario, ts, payload_json)` + INSERT en cada Route Handler de escritura.

### 3.3 Funciones de edición incompletas 🟠
- **`PROVINCIA` falta en edición de marcha**: el campo está en la BD y en el alta, pero no en `dashboard/marcha/[id]/page.tsx:56-63`.
- **Autores de marcha no editables**: en la edición de marcha los autores se muestran en lectura. No hay forma de añadir ni quitar relaciones `marcha_autor` desde el panel.
- **Sin `editAutor`**: los 827 autores solo se pueden crear, no corregir. Falta `/dashboard/autor/[id]` + endpoint `editAutor`.

### 3.4 Sin buscador en el dashboard 🟠
- Para editar una marcha hay que conocer su ID numérico. No hay listado ni búsqueda por título dentro del panel.
- **Fix**: añadir un campo de búsqueda FTS en `/dashboard` que devuelva resultados directamente enlazados a la edición.

### 3.5 Sin navegación post-creación 🟡
- Tras crear una marcha o un autor, no hay enlace a la página pública ni al formulario de edición del registro recién creado.
- **Fix**: mostrar el ID creado y botones "Ir a editar" / "Ver en público" en el estado `success`.

### 3.6 Gestión de bandas y discos ausente 🟢
- No hay alta ni edición de bandas ni de discos. La banda estreno se asigna por ID numérico en el formulario de edición de marcha, sin ningún autocomplete ni validación.
- **Fix** (baja prioridad): `/dashboard/banda/*` y `/dashboard/disco/*` cuando las entidades principales estén cubiertas.

---

## 4. Calidad del código Next.js

### 4.1 Rate limiting de login en memoria 🟡
- El `Map<string, state>` en `app/api/login/route.ts` se pierde con cada restart del contenedor.
- Aceptable con un solo proceso y tráfico bajo.
- **Fix futuro** (solo si se escala): persistir en una tabla SQLite `login_attempts` con TTL, o usar Redis.

### 4.2 `revalidatePath` no se llama tras ediciones admin 🟡
- Al editar una marcha desde el admin, la página pública puede tardar hasta 1 hora en reflejar el cambio (según el ISR `revalidate: 3600`).
- **Fix**: añadir `revalidatePath('/marcha/[slug]-[id]')` al final del Route Handler `editMarcha`, e importar `revalidatePath` de `next/cache`.
