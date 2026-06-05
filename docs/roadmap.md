# Hoja de ruta — marchasdecristo.com

> Generado: 2026-06-01 · Última actualización: 2026-06-05 (Bloque 2 completado)
> Plan secuenciado para resolver la deuda técnica identificada y materializar las decisiones tomadas.

## Resumen

| Fase | Objetivo | Estado | Riesgo |
|------|----------|--------|--------|
| 0 | Limpieza y seguridad inmediata | ✅ Completada (2026-06-05) | — |
| 1 | Bugfix funcionales | ✅ Superada (Express eliminado) | — |
| 2 | Migrar MySQL a Docker en el VPS | ✅ Completada (junio 2026) | — |
| 3a | Migrar páginas admin a Next.js | ✅ Completada (junio 2026) | — |
| 3b | Migrar API a Next.js Route Handlers y apagar Express | ✅ Completada (2026-06-05) | — |
| 4 | Integridad BD + panel admin completo | Pendiente | Bajo (SQLite) |
| 5 | Tests, CI/CD, observabilidad | Pendiente | Bajo |
| 6 | Mejoras opcionales (build estático, Zod, Drizzle) | Pendiente | — |

---

## Fase 0 — Limpieza inmediata ✅ Completada (2026-06-05)

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

## Fase 1 — Bugfix funcionales ✅ Superada (Express eliminado en Fase 3b)

Los bugs documentados (db.connection, WHERE vacío, getTimeline, catches mudos) vivían en el código Express. Al eliminar Express en la Fase 3b, los nuevos Route Handlers se escribieron sin estos antipatrones. No requiere acción.

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

**Realizado**: contenedor `mdc-mysql` (MySQL 8.0) levantado en el VPS con volumen persistente `mdc-back_mysql_data`. Dump importado desde HelioHost. Usuarios readonly y admin creados. `DB_HOST=mysql` en `.env` del VPS. Ver ADR-002 en [architecture.md](architecture.md).

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
       MARIADB_DATABASE: <db_name>
       MARIADB_USER: <db_user>
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
   mysqldump -h <db_host> -u <db_user> -p <db_name> > mdc.sql
   # Subir mdc.sql al VPS
   scp mdc.sql <usuario>@<vps-ip>:/var/www/mdc-back/db/init.sql
   # En el VPS
   docker compose down
   docker volume rm mdc-back_mdc-db-data
   docker compose up -d mysql
   docker logs mdc-mysql  # ver que init.sql se aplique
   ```

3. **Crear usuario readonly**:
   ```sql
   CREATE USER '<db_readonly_user>'@'%' IDENTIFIED BY '...';
   GRANT SELECT ON <db_name>.* TO '<db_readonly_user>'@'%';
   ```

4. **Cambiar `.env`** para apuntar a `DB_HOST=mysql` (nombre del servicio Docker).

5. **Levantar app**: `docker compose up -d`. Verificar.

6. **Cron de backup en el VPS**:
   ```bash
   0 3 * * * docker exec mdc-mysql mysqldump -u root -p${MARIADB_ROOT_PASSWORD} <db_name> | gzip > /var/backups/mdc-$(date +\%F).sql.gz
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

## Fase 3b — Migrar API a Next.js Route Handlers y apagar Express ✅ Completada (2026-06-05)

> **Estado**: desplegado en VPS. Express apagado. Ver guía ejecutada en [vps-migration-3b.md](vps-migration-3b.md).

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

### Realizado en sesión 2026-06-05

1. Merge `feat/nextjs-migration` → `main` y despliegue en VPS.
2. Backup SQLite antes de tocar contenedores (`/var/backups/mdc-backup-fase3b-2026-06-05.db`).
3. `docker stop mdc-app && docker rm mdc-app` (Express apagado).
4. `docker compose build nextjs && docker compose up -d nextjs` (Ready in 76ms).
5. Endpoints verificados vía HTTPS.
6. Nginx simplificado: todo → `:3000` excepto `/cover/`.
7. `docker system prune -f` → 1.34 GB liberados.
8. Código muerto eliminado: `src/`, `frontend/`, `index.js`, `Dockerfile` raíz, artefactos legacy.

---

## Fase 4 — Integridad de BD + panel admin completo

> Análisis completo en [db-analysis.md](db-analysis.md) y [admin-panel.md](admin-panel.md).  
> Separado en bloques de prioridad para poder hacer commits independientes.

### Bloque U — Urgente (bugs que generan datos corruptos) ✅ Completado (2026-06-05)

**U1 — Transacción en `addMarcha`** ✅
- INSERT marcha + INSERT marcha_autor ahora en `dbTransaction()` de better-sqlite3.
- También corregido bug previo: el Route Handler leía `body.marcha` (undefined) en lugar de `body.fieldsToInsert/valuesToInsert`.
- Alineados campos insert entre cliente (`INSERTABLE_MARCHA_FIELDS` en adminApi.ts) y servidor.

**U2 — Validar existencia de autoresIds** ✅
- `SELECT COUNT(*) FROM autor WHERE ID_AUTOR IN (...)` antes del INSERT; devuelve 400 `INVALID_AUTHORS` si alguno falta.
- También devuelve 400 `AUTHORS_REQUIRED` si no se envía ningún autor.

**U3 — Advertir en UI al guardar sin autores** ✅
- Botón "Crear marcha" deshabilitado cuando `AUTORES_IDS` está vacío.
- Alert `alert-warning` visible mientras no haya autores seleccionados.

---

### Bloque A — Alta prioridad (integridad + cobertura funcional crítica)

**A1 — FK constraints en schema SQLite** ✅ Completado (2026-06-05)
- 43 huérfanos eliminados con `scripts/clean-orphans.sql` (27 dm→m, 2 dm→d solapados, 4 ma→m, 10 ma→a).
- `marcha_autor` y `disco_marcha` recreadas con `REFERENCES ... ON DELETE CASCADE` vía `scripts/add-fk-constraints.sql`.
- `admin_log` creada en el mismo script. FK check PRAGMA devuelve 0 violaciones.
- Nota: `marcha.BANDA_ESTRENO` y `disco.BANDADISCO` no incluidos (valor 0 = sentinel, requeriría valor NULL).

**A2 — `PROVINCIA` en formulario de edición de marcha**
- Fichero: `nextjs/app/dashboard/marcha/[id]/page.tsx:56`
- Cambio: añadir `{ label: 'Provincia', key: 'PROVINCIA' }` al array `fields`.

**A3 — `NOMBRE_ART` en alta y edición de autor**
- Ficheros: `nextjs/app/api/admin/addAutor/route.ts` y futuro `editAutor/route.ts`
- Cambio: añadir `NOMBRE_ART` a `INSERTABLE_FIELDS` y al formulario `dashboard/autor/add/page.tsx`.

**A4 — `editAutor`: edición de autores existentes**
- Crear `nextjs/app/dashboard/autor/[id]/page.tsx` (Client Component, mismo patrón que `marcha/[id]`).
- Crear `nextjs/app/api/admin/editAutor/route.ts` con allowlist de campos editables.
- Campos editables: `NOMBRE`, `APELLIDOS`, `NOMBRE_ART`, `F_NAC`, `LUGAR_NAC`, `F_DEF`, `BIO`.

**A5 — Editar autores de una marcha existente**
- Modificar `nextjs/app/dashboard/marcha/[id]/page.tsx`: añadir `AutocompleteMulti` para autores (igual que en el alta).
- Crear `nextjs/app/api/admin/editMarchaAutores/route.ts`: recibe `marchaId` + `autoresIds` nuevos, hace DELETE + INSERT en `marcha_autor` dentro de una transacción.

---

### Bloque M — Media prioridad (operabilidad diaria)

**M1 — Audit log** ✅ Completado (2026-06-05)
- Tabla `admin_log` creada vía `scripts/add-fk-constraints.sql` y documentada en `db/schema.sql`.
- `logAdmin()` helper en `lib/db.ts`. Llamado en addMarcha, editMarcha, addAutor.
- Pendiente: editAutor y editMarchaAutores (se añadirán al crear esos Route Handlers en Bloque 3).

**M2 — Serialización JSON de autores** ✅ Completado (2026-06-05)
- `nextjs/lib/api.ts`: los 5 `GROUP_CONCAT('#'/'|')` sustituidos por `json_group_array(json_object('autorId', ID_AUTOR, 'nombre', NOMBRE || ' ' || APELLIDOS))`.
- `nextjs/lib/db.ts`: `formatAutor` simplificado a `JSON.parse`.
- Elimina el riesgo de corrupción si un nombre contiene `#` o `|`.

**M3 — Validación de `FECHA` en Route Handlers**
- `addMarcha/route.ts` y `editMarcha/route.ts`: rechazar `FECHA` que no sea vacío ni número de 4 dígitos (`/^\d{4}$/`).

**M4 — Buscador de marchas/autores en el dashboard**
- Añadir en `/dashboard/page.tsx` un campo de búsqueda por título que consulte `marcha_fts` y devuelva resultados con enlace directo a `/dashboard/marcha/[id]`.

**M5 — Navegación post-creación**
- En `marcha/add` y `autor/add`: tras `status === 'success'`, mostrar el ID creado con botones "Ir a editar" y "Ver en público".

**M6 — Eliminar SQL preview de la UI**
- En `marcha/add/page.tsx:139-143` y `autor/add/page.tsx:71-75`: eliminar los bloques `<pre>` con SQL y parámetros, o ponerlos tras un toggle `[modo debug]`.

---

### Bloque B — Baja prioridad (deuda diferible)

**B1 — Eliminar tablas muertas**
- `videos` (357 filas, nunca consultada): backup previo, luego `DROP TABLE videos`.
- `users` (0 filas): `DROP TABLE users`.

**B2 — Normalizar `FECHA` a `INTEGER NULL`**
- Script: `UPDATE marcha SET FECHA = NULL WHERE FECHA = 0`.
- Simplificar `normalizeFecha` en `lib/api.ts`.

**B3 — Gestión de bandas desde el panel**
- `/dashboard/banda/add` y `/dashboard/banda/[id]` con los campos: `NOMBRE_BREVE`, `NOMBRE_COMPLETO`, `LOCALIDAD`, `PROVINCIA`, `FECHA_FUND`, `FECHA_EXT`.
- Autocomplete de banda en edición de marcha en lugar del campo numérico actual.

**B4 — Gestión de discos y relaciones disco_marcha**
- `/dashboard/disco/add` y `/dashboard/disco/[id]` con gestión de la lista de pistas.

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
