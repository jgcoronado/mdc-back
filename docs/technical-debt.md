# Deuda técnica — marchasdecristo.com

> Última actualización: 2026-06-05
> La auditoría de la BD vive en [db-analysis.md](db-analysis.md).

## Resumen ejecutivo

| Categoría | Items | Severidad máxima |
|-----------|-------|------------------|
| Operativa | 3 | 🟠 Alta |
| BD (SQLite) | 3 | 🟡 Media |
| Calidad Next.js | 2 | 🟡 Media |

**Nota**: los bugs de Express (endpoints `/all`, SQL WHERE vacío, getTimeline, catches mudos) y todos los problemas de seguridad básica (credenciales en repo, COOKIE_SECURE, MD5) fueron resueltos en la sesión de 2026-06-05 al migrar a Next.js y completar la Fase 0.

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

### 2.1 Sin foreign keys declaradas 🟡
- Las relaciones existen en el código pero no en la BD. Un `DELETE` incorrecto puede dejar huérfanos.
- SQLite soporta FKs pero requiere `PRAGMA foreign_keys = ON` por conexión.
- **Fix**: añadir `db.pragma('foreign_keys = ON')` en `lib/db.ts` y verificar que los datos existentes no tengan huérfanos (ver [db-analysis.md](db-analysis.md)).

### 2.2 `FECHA` como `INTEGER` con `0` = "sin fecha" 🟡
- Semánticamente mejor como `INTEGER NULL` con `NULL` = "sin fecha".
- En SQLite es trivial cambiar: `NULL` y `0` coexisten sin migración forzada.
- **Fix**: cambiar la lógica en `lib/api.ts` para tratar `FECHA = 0` como `null`, y actualizar las filas eventualmente.

### 2.3 Tabla `videos` (357 filas) sin uso en la API 🟢
- Existe en la BD pero ningún Route Handler la expone.
- **Fix**: decidir si se reactiva o se borra. Si se borra, hacer backup primero.

---

## 3. Calidad del código Next.js

### 3.1 Rate limiting de login en memoria 🟡
- El `Map<string, state>` en `app/api/login/route.ts` se pierde con cada restart del contenedor.
- Aceptable con un solo proceso y tráfico bajo.
- **Fix futuro** (solo si se escala): persistir en una tabla SQLite `login_attempts` con TTL, o usar Redis.

### 3.2 `revalidatePath` no se llama tras ediciones admin 🟡
- Al editar una marcha desde el admin, la página pública puede tardar hasta 1 hora en reflejar el cambio (según el ISR `revalidate: 3600`).
- **Fix**: añadir `revalidatePath('/marcha/[slug]-[id]')` al final del Route Handler `editMarcha`, e importar `revalidatePath` de `next/cache`.
