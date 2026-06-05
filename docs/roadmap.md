# Hoja de ruta — marchasdecristo.com

> Generado: 2026-06-01
> Plan secuenciado para resolver la deuda técnica identificada y materializar las decisiones tomadas.

## Resumen

| Fase | Objetivo | Estado | Riesgo |
|------|----------|--------|--------|
| 0 | Limpieza y seguridad inmediata | Pendiente | Bajo |
| 1 | Bugfix funcionales | Pendiente | Bajo |
| 2 | Migrar MySQL a Docker en el VPS | ✅ Completada (junio 2026) | — |
| 3a | Migrar páginas admin a Next.js | ✅ Completada (junio 2026) | — |
| 3b | Migrar API a Next.js Route Handlers y apagar Express | ⏳ Código listo, pendiente despliegue VPS | Medio-alto |
| 4 | Endurecimiento de BD (índices, motores, FKs) | Pendiente | Medio |
| 5 | Tests, CI/CD, observabilidad | Pendiente | Bajo |
| 6 | Mejoras opcionales (SQLite, build estático) | Pendiente | — |

---

## Fase 0 — Limpieza inmediata (2-4 h)

Objetivo: cerrar agujeros de seguridad y eliminar fricción operativa **sin tocar lógica de negocio**.

### Tareas

1. **Rotar credenciales y desacoplar `.env`**
   - Rotar `DB_PASSWORD`, `DB_PASSWORD_ADMIN`, `SECRET_KEY`.
   - Verificar visibilidad del repo. Si es público, considerar `git filter-repo` para purgar `.env` del historial.
   - Añadir `.env` a `.gitignore` (sin romper despliegue: ya está en el VPS).
   - Crear `.env.example` con placeholders documentados.

2. **`COOKIE_SECURE=true`**
   - Editar `.env` en el VPS. Hot-reload con `docker compose restart app`.

3. **Forzar reset de los 2 usuarios MD5 pendientes**
   - O bien comunicarlo a los usuarios y pedirles que loguen (auto-upgrade ocurre solo).
   - O bien generar PBKDF2 manualmente con un script `scripts/reset-user.js` y `UPDATE usuarios SET CLAVE = ?`.

4. **Eliminar `login_autor`** (tabla obsoleta).
   ```sql
   DROP TABLE login_autor;
   ```

5. **Backup mínimo viable**
   - Cron en el VPS: `mysqldump | gzip > /var/backups/mdc-$(date +\%F).sql.gz`.
   - Rotar 14 días (`find /var/backups -name 'mdc-*.gz' -mtime +14 -delete`).
   - Documentar en `README.md` y en memoria del proyecto.

6. **Eliminar artefactos fantasma**
   - Borrar (o mover a `archive/`): `.htaccess`, `.vercel/`, `vercel.json`, `ecosystem.config.js`.
   - Documentar el contexto en un `archive/README.md` ("estos eran de la era helioho/Vercel/PM2").

---

## Fase 1 — Bugfix funcionales (3-5 h)

Objetivo: que los endpoints **funcionen** sin tocar arquitectura.

### Tareas

1. **`src/routes/banda.js:82`** y **`src/routes/disco.js:14`** — corregir `db.connection.query` → `db.pool.query` o **borrar los endpoints `/all`** (no se usan en producción).

2. **Búsquedas con `WHERE` vacío** ([src/routes/marcha.js:54](../src/routes/marcha.js#L54), `autor.js:65`, `banda.js:111`, `disco.js:36`).
   - Patch: inicializar `const sql_search = ['1=1'];` para que el SQL sea siempre válido.
   - Alternativa más limpia: construir condicionalmente `WHERE ${conditions}` o no incluir `WHERE` si está vacío.

3. **`getTimeline` en `banda.js`** — reescribir con `for...of` + `await`:
   ```js
   const getTimeline = async (banda) => {
     const timeline = [{ ID_BANDA: banda.ID_BANDA, FECHA_FUND: banda.FECHA_FUND, FECHA_EXT: banda.FECHA_EXT, NOMBRE_BREVE: banda.NOMBRE_BREVE }];
     for (const direction of ['ANT', 'SIG']) {
       let id = banda[`FORMACION_${direction}`];
       while (id) {
         const [rows] = await db.pool.query(
           `SELECT ID_BANDA, FORMACION_${direction}, NOMBRE_BREVE, FECHA_FUND, FECHA_EXT FROM banda WHERE ID_BANDA = ?`,
           [id]
         );
         if (!rows.length) break;
         const r = rows[0];
         timeline.push({ ID_BANDA: r.ID_BANDA, FECHA_FUND: r.FECHA_FUND, FECHA_EXT: r.FECHA_EXT, NOMBRE_BREVE: r.NOMBRE_BREVE });
         id = r[`FORMACION_${direction}`];
       }
     }
     return timeline;
   };
   ```

4. **Quitar `%` de `MATCH AGAINST`** ([src/routes/marcha.js:24](../src/routes/marcha.js#L24), `autor.js:62`).

5. **`UPDATE marcha` y `marcha_autor` a InnoDB + transacción en `addMarcha`**:
   ```sql
   ALTER TABLE marcha ENGINE=InnoDB;
   ALTER TABLE marcha_autor ENGINE=InnoDB;
   ```
   Después en `adminMarcha.js:addMarcha`:
   ```js
   const conn = await poolAdmin.getConnection();
   try {
     await conn.beginTransaction();
     const [r] = await conn.execute(insertSql, insertParams);
     // ... insertar marcha_autor con conn.execute
     await conn.commit();
   } catch (err) {
     await conn.rollback();
     throw err;
   } finally {
     conn.release();
   }
   ```

6. **Middleware de error global** para reemplazar los catches mudos:
   ```js
   const asyncHandler = (fn) => (req, res, next) =>
     Promise.resolve(fn(req, res, next)).catch(next);

   app.use((err, req, res, next) => {
     console.error(err);
     res.status(500).json({ error: 'Internal server error' });
   });
   ```

---

## Fase 2 — MySQL en Docker ✅ Completada (junio 2026)

~~Objetivo: cortar dependencia con helioho.st sin tocar la app.~~

**Realizado**: contenedor `mdc-mysql` (MySQL 8.0) levantado en el VPS con volumen persistente `mdc-back_mysql_data`. Dump importado desde HelioHost. Usuarios `jaguerra27_readonly` y `jaguerra27_user` creados. `DB_HOST=mysql` en `.env` del VPS. Ver ADR-002 en [architecture.md](architecture.md).

---

## Fase 2 — MySQL en Docker (detalle original, conservado como referencia)

### Tareas

1. Añadir servicio `mysql` al `docker-compose.yml`:
   ```yaml
   mysql:
     image: mariadb:11
     container_name: mdc-mysql
     restart: unless-stopped
     environment:
       MARIADB_ROOT_PASSWORD: ...
       MARIADB_DATABASE: jaguerra27_mdc
       MARIADB_USER: jaguerra27_user
       MARIADB_PASSWORD: ...
     volumes:
       - mdc-db-data:/var/lib/mysql
       - ./db/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
     networks: [internal]
   volumes:
     mdc-db-data:
   ```

2. **Dump y restore**:
   ```bash
   # Local
   mysqldump -h jaguerra27.helioho.st -u jaguerra27_user -p jaguerra27_mdc > mdc.sql
   # Subir mdc.sql al VPS
   scp mdc.sql claude@104.245.245.27:/var/www/mdc-back/db/init.sql
   # En el VPS
   docker compose down
   docker volume rm mdc-back_mdc-db-data
   docker compose up -d mysql
   docker logs mdc-mysql  # ver que init.sql se aplique
   ```

3. **Crear usuario readonly**:
   ```sql
   CREATE USER 'jaguerra27_readonly'@'%' IDENTIFIED BY '...';
   GRANT SELECT ON jaguerra27_mdc.* TO 'jaguerra27_readonly'@'%';
   ```

4. **Cambiar `.env`** para apuntar a `DB_HOST=mysql` (nombre del servicio Docker).

5. **Levantar app**: `docker compose up -d`. Verificar.

6. **Cron de backup en el VPS**:
   ```bash
   0 3 * * * docker exec mdc-mysql mysqldump -u root -p${MARIADB_ROOT_PASSWORD} jaguerra27_mdc | gzip > /var/backups/mdc-$(date +\%F).sql.gz
   ```

---

## Fase 3a — Migrar páginas admin a Next.js ✅ Completada (junio 2026)

**Realizado**: las 5 páginas admin (`/login`, `/dashboard`, `/dashboard/marcha/add`, `/dashboard/marcha/:id`, `/dashboard/autor/add`) viven en Next.js. Nginx enruta `/login` y `/dashboard` a `mdc-nextjs:3000`. El middleware protege `/dashboard/*` verificando la cookie contra Express. Express sigue sirviendo la API.

Archivos añadidos:
- `nextjs/middleware.ts` — auth guard servidor a servidor
- `nextjs/lib/auth.ts` — login / logout / verifySession
- `nextjs/lib/adminApi.ts` — payloads + llamadas a la API admin
- `nextjs/hooks/useAutocompleteSelect.ts` — hook React para autocompletados
- `nextjs/components/admin/AutocompleteMulti.tsx` / `AutocompleteSingle.tsx`
- `nextjs/app/login/page.tsx`, `dashboard/page.tsx`, `dashboard/marcha/[id]/page.tsx`, `dashboard/marcha/add/page.tsx`, `dashboard/autor/add/page.tsx`

---

## Fase 3b — Migrar API a Next.js Route Handlers y apagar Express ✅ Código completado (junio 2026)

> **Estado**: código listo en rama `main`, **pendiente de desplegar en el VPS**.
> Ver guía de despliegue completa en [vps-migration-3b.md](vps-migration-3b.md).

### Qué se hizo (sesión junio 2026)

**Decisiones de diseño tomadas:**
- Los Server Components leen la BD directamente (sin HTTP round-trip a Route Handlers).
- El middleware verifica la cookie HMAC inline (`node:crypto`), sin llamar a Express.
- `better-sqlite3` instalado en `nextjs/` con `serverExternalPackages` para evitar bundling.
- El volumen SQLite `mdc-sqlite-data` se reasigna de `app` a `nextjs` en `docker-compose.yml`.

**Ficheros creados en `nextjs/`:**
- `lib/db.ts` — singleton SQLite lazy (no abre la BD en `next build`)
- `lib/auth-session.ts` — port TypeScript de `authSession.js` (HMAC sign/verify)
- `app/api/login/route.ts` — POST login con rate limiting en memoria y upgrade MD5→PBKDF2
- `app/api/login/verify/route.ts` — GET verify sesión
- `app/api/login/logout/route.ts` — POST logout
- `app/api/autor/fastSearch/route.ts` — GET autocomplete autores
- `app/api/banda/fastSearch/route.ts` — GET autocomplete bandas
- `app/api/admin/editMarcha/route.ts` — POST editar marcha (auth + allowlist campos)
- `app/api/admin/addMarcha/route.ts` — POST añadir marcha + relaciones marcha_autor
- `app/api/admin/addAutor/route.ts` — POST añadir autor
- `app/sitemap.ts` — sitemap generado desde BD, ISR 1h

**Ficheros modificados:**
- `lib/api.ts` — eliminado fetch HTTP, todas las funciones leen SQLite directamente
- `middleware.ts` — verifica HMAC inline (`runtime = 'nodejs'`), sin red
- `next.config.ts` — `serverExternalPackages: ['better-sqlite3']`, sin rewrites Express
- `Dockerfile` — añade `python3 make g++` en builder y `libstdc++` en runtime (necesario para compilar `better-sqlite3` en Alpine)
- `docker-compose.yml` — servicio `app` (Express) eliminado, volumen SQLite en `nextjs`
- `nginx.conf.example` — simplificado a una sola location (`/cover/` directo + resto a `:3000`)

### Siguiente paso obligatorio: despliegue en VPS

Seguir **en orden** los pasos de [vps-migration-3b.md](vps-migration-3b.md):

1. `git pull` en el VPS.
2. Backup del volumen SQLite (obligatorio antes de tocar contenedores).
3. `docker compose stop app && docker compose rm -f app`.
4. `docker compose build nextjs && docker compose up -d nextjs`.
5. Verificar endpoints con `curl`.
6. Actualizar nginx con el nuevo `nginx.conf.example` y recargar.
7. `docker system prune -f`.

### Pendiente tras el despliegue

- **Eliminar código muerto** del repo (Express ya no se usa pero el código sigue):
  - `src/`, `frontend/`, `index.js`, `Dockerfile` raíz, `package.json` raíz.
  - Artefactos legacy: `.htaccess`, `.vercel/`, `vercel.json`, `ecosystem.config.js`.
- **Actualizar `docs/context.md` y `docs/architecture.md`** para reflejar el stack final (solo Next.js + SQLite).
- **Fase 0** (seguridad): rotar credenciales, `.env` fuera del repo. Hacerlo antes de compartir el repo públicamente.
- **Fase 5** (tests + CI/CD): ahora el stack es suficientemente simple para añadir tests con Vitest.

---

## Fase 4 — Endurecer la BD (4-6 h)

Objetivo: corregir motores, collation, índices, integridad referencial.

Detalle en [db-analysis.md](db-analysis.md). Resumen:

1. **Migrar a InnoDB**:
   ```sql
   ALTER TABLE marcha ENGINE=InnoDB;
   ALTER TABLE autor ENGINE=InnoDB;
   ALTER TABLE banda ENGINE=InnoDB;
   ALTER TABLE marcha_autor ENGINE=InnoDB;
   ```

2. **Añadir índices**:
   ```sql
   ALTER TABLE disco_marcha ADD INDEX idx_dm_disco (ID_DISCO);
   ALTER TABLE disco_marcha ADD INDEX idx_dm_marcha (IDMARCHA);
   ALTER TABLE disco ADD INDEX idx_disco_banda (BANDADISCO);
   ALTER TABLE marcha ADD INDEX idx_marcha_banda_estreno (BANDA_ESTRENO);
   ALTER TABLE marcha_autor ADD INDEX idx_ma_autor (ID_AUTOR);
   ```

3. **Limpiar huérfanos** (queries en db-analysis.md), añadir **foreign keys** después.

4. **Unificar collation a `utf8mb4_spanish_ci`** en todas las tablas (cuidado con índices FULLTEXT — habrá que recrearlos).

5. **Eliminar tablas muertas**: `users`, `videos` (si confirmado), `login_autor`.

6. **`FECHA` a `SMALLINT UNSIGNED NULL`** sustituyendo el `0` por `NULL` semántico.

---

## Fase 5 — Tests, CI/CD, observabilidad (2 días)

### Tests
- `vitest` + `supertest` en Next.js para Route Handlers.
- Mínimo 20 tests cubriendo golden paths.
- Snapshot del HTML de `/marcha/<slug>-330` para detectar regresiones SEO.

### CI/CD
- GitHub Actions:
  - Lint + typecheck en cada PR.
  - Tests + build en cada PR.
  - Deploy automático a main: SSH al VPS + `git pull && docker compose up -d --build && docker system prune -f`.

### Observabilidad
- Healthcheck en Docker (`HEALTHCHECK CMD curl -f http://localhost:3000/api/stats/estado || exit 1`).
- UptimeRobot externo apuntando a `https://marchasdecristo.com/api/stats/estado` (gratis).
- Logs centralizados: opcional, con el VPS de 1 GB es razonable seguir con `docker logs`.

---

## Fase 6 — Mejoras opcionales

### 6.1 Pasar a SQLite (3-4 días)
- Solo si tras Fase 2-3 quieres simplificar al máximo.
- `better-sqlite3` + FTS5 reemplaza MySQL.
- Consumo de RAM cae ~150 MB → VPS holgado.
- Backups: copia de un archivo.

### 6.2 Build estático nocturno + Cloudflare Pages (1 día)
- Cron en VPS: `next build` con todas las páginas estáticas + `wrangler pages deploy`.
- El admin sigue en el VPS escribiendo en la BD.
- Cada `editMarcha` puede disparar un `revalidatePath` o un redeploy.

### 6.3 Validación con Zod (medio día)
- Schemas en `nextjs/lib/schemas.ts`: `MarchaSearchSchema`, `MarchaUpdatePayloadSchema`, `LoginSchema`.
- Errores 400 consistentes con `{ code, message, path }`.

### 6.4 Drizzle ORM tipado (medio día)
- Solo si quieres refactor seguro y autocompletado en queries.
- Compatible con MySQL/SQLite.

---

## Cómo usar este roadmap

- **Hazlo por fases**, no todo a la vez. Tras cada fase: commit + test manual + descansar.
- **Fase 0** debe ir antes de cualquier compartición del repo.
- **Fase 1** se puede hacer **sin** Fase 2-3 si no quieres unificar todavía.
- **Fase 3** es la decisión grande: si la pospones, el código sigue funcionando pero la complejidad operativa se queda.
- **Fase 4** se puede hacer en paralelo a Fase 3 (son SQL puro).
- **Fase 6** es opcional. No es necesaria para que la app sea sólida.
