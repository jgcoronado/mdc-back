# Deuda técnica — marchasdecristo.com

> Generado: 2026-06-01
> La auditoría detallada de la BD vive en [db-analysis.md](db-analysis.md). Aquí se prioriza el lado de aplicación y operativa.

## Resumen ejecutivo

| Categoría | Items | Severidad máxima |
|-----------|-------|------------------|
| Bugs funcionales | 4 | 🔴 Crítica (rompe endpoints) |
| Seguridad | 5 | 🔴 Crítica (credenciales en repo) |
| Frontend legacy | 1 bloque | 🟠 Alta (confusión y bundle muerto) |
| Calidad de código | 6 | 🟡 Media |
| Operativa | 4 | 🟠 Alta (no hay backups documentados) |

---

## 1. Bugs activos (rompen funcionalidad)

### 1.1 `db.connection` no existe → endpoints `/all` crashean 🔴
- **Archivos**: [src/routes/banda.js:82](../src/routes/banda.js#L82), [src/routes/disco.js:14](../src/routes/disco.js#L14).
- `db.js` solo exporta `pool`. `db.connection.query(...)` produce `TypeError: Cannot read properties of undefined`.
- **Impacto**: `GET /api/banda/all` y `GET /api/disco/all` devuelven 500 (en realidad cuelgan, porque el catch hace solo `console.log`).
- **Fix**: cambiar a `db.pool.query(...)` o (mejor) borrar — son rutas de debug.

### 1.2 SQL inválido cuando se llama `/search` sin parámetros 🔴
- **Archivos**: [src/routes/marcha.js:54](../src/routes/marcha.js#L54), [src/routes/autor.js:65](../src/routes/autor.js#L65), [src/routes/banda.js:111](../src/routes/banda.js#L111), [src/routes/disco.js:36](../src/routes/disco.js#L36).
- `sql_head` termina con `WHERE ` y `sql_search.join(' AND ')` produce cadena vacía → SQL inválido: `... WHERE  GROUP BY ...`.
- **Fix**: inicializar `sql_search = ['1=1']` o construir condicionalmente la cláusula `WHERE`.

### 1.3 `getTimeline` no funciona (forEach con async) 🔴
- **Archivo**: [src/routes/banda.js:13](../src/routes/banda.js#L13).
- `[[idAnt,'ANT'],[idSig,'SIG']].forEach(async e => { ... })` ignora los `await` dentro del callback. La función `getTimeline` retorna antes de que se rellene el array.
- **Resultado**: la timeline de una banda siempre muestra solo la banda inicial.
- **Fix**: reescribir con `for...of` + `await`.

### 1.4 `MATCH(...) AGAINST('%term%')` ineficiente 🟠
- **Archivos**: [src/routes/marcha.js:22-24](../src/routes/marcha.js#L22), [src/routes/autor.js:60-62](../src/routes/autor.js#L60).
- Los `%` son sintaxis `LIKE`, irrelevantes en FULLTEXT NATURAL LANGUAGE. MySQL los trata como caracteres literales y los resultados se degradan.
- **Fix**: pasar `term` sin wildcards.

### 1.5 Sin transacciones en `addMarcha` 🟠
- **Archivo**: [src/routes/adminMarcha.js:108-131](../src/routes/adminMarcha.js#L108).
- Inserta en `marcha` y después en `marcha_autor`. Si la segunda falla, queda una marcha huérfana sin posibilidad de rollback (la tabla `marcha` es MyISAM).
- **Fix**: migrar `marcha` y `marcha_autor` a InnoDB y envolver en transacción.

### 1.6 Catches que silencian errores 🟠
- Decenas de `} catch (err) { console.log(err); }` sin `res.status(500).send(...)`. El cliente queda esperando hasta timeout.
- Visible en `disco.js`, `autor.js`, `banda.js`, `stats.js`.
- **Fix**: helper `wrapAsync` o middleware de error global.

---

## 2. Seguridad

### 2.1 Credenciales versionadas en `.env` 🔴
- El archivo `.env` está en el repo con `DB_PASSWORD`, `DB_PASSWORD_ADMIN`, `SECRET_KEY`.
- Aunque `jaguerra27.helioho.st` solo concede privilegios limitados, la rotación se hace difícil y cualquiera con acceso al repo (futuro colaborador, CI, etc.) las ve.
- **Fix**:
  1. Rotar las tres claves.
  2. Añadir `.env` a `.gitignore` (¡comprobar primero que el repo es privado!).
  3. Añadir `.env.example` con placeholders.
  4. Eliminar del historial con `git filter-repo` si el repo es público.

### 2.2 `COOKIE_SECURE=false` en producción 🟠
- [.env:17](../.env). En el VPS la app sí va por HTTPS, así que la cookie debería marcarse `Secure`.
- **Fix**: poner `COOKIE_SECURE=true` o eliminar la variable (Express ya detecta `NODE_ENV=production` y activa Secure por defecto en `getSessionCookieOptions`).

### 2.3 MD5 sin salt en tablas legacy 🔴
- `login_autor` (9 filas) y `usuarios` (2 de 3) contienen hashes MD5 sin salt.
- Cualquier rainbow table los crackea. Si la BD se filtra (ver 2.1), las contraseñas están comprometidas.
- **Fix**:
  1. Forzar reset a los 2 usuarios pendientes en `usuarios` (o llamarlos para que loguen y se auto-actualicen).
  2. Eliminar `login_autor` (tabla obsoleta, no usada por la API).

### 2.4 Rate limiting de login en memoria 🟡
- `attemptsByKey = new Map()` en `login.js:22` se pierde con cada `docker compose up -d`.
- Aceptable para tráfico bajo + un solo proceso. **Crítico** si se escalara horizontalmente.
- **Fix futuro**: tabla `login_attempts` en MySQL con TTL o Redis.

### 2.5 Validación de input mínima 🟡
- Los endpoints de búsqueda aceptan cualquier string sin tamaño máximo (más allá del implícito de la querystring). Aceptable porque van con `LIKE ?` parametrizado, pero un usuario malintencionado puede meter strings enormes y forzar trabajo de DB.
- **Fix**: validar `req.query.titulo?.length < 100` etc.

---

## 3. Frontend Vue — código muerto pendiente de eliminar 🟠

El directorio `frontend/` sigue en el repositorio y en la imagen Docker de `mdc-app`, aunque ya no sirve ninguna ruta relevante:

**Componentes públicos sin uso** (14 componentes, Next.js los reemplaza):
- `Home.vue`, `MarchaSearch.vue`/`List`/`Detail`, `AutorSearch.vue`/`List`/`Detail`
- `BandaSearch.vue`/`List`/`Detail`, `DiscoSearch.vue`/`List`/`Detail`, `Stats.vue`
- `molecules/CdList.vue`, `CdCard.vue`, `Timeline.vue`, `DbCount.vue`

**Componentes admin migrados a Next.js** (junio 2026):
- `Login.vue`, `Dashboard.vue`, `MarchaEdit.vue`, `MarchaAdd.vue`, `AutorAdd.vue`
- `AutocompleteMulti.vue`, `AutocompleteSingle.vue`
- `services/authService.js`, `services/edits.js`, `composables/useAutocompleteSelect.js`

Nginx ya no enruta `/login` ni `/dashboard` a `mdc-app`. El bundle Vue sigue compilándose en la imagen pero no sirve ninguna URL pública.

**Próximo paso**: borrar `frontend/` del repositorio y simplificar el `Dockerfile` raíz. Ver [roadmap.md](roadmap.md) §3.

---

## 4. Calidad de código

| # | Item | Severidad |
|---|------|-----------|
| 4.1 | `LIKE ?` sobre `INT` PKs (`banda.js:126`, `autor.js:79`, `disco.js:54`, `marcha.js:78`) — debería ser `=` | 🟡 |
| 4.2 | `formatAutor` muta el objeto recibido y además lo devuelve (efectos secundarios + retorno) — confunde | 🟡 |
| 4.3 | `.map(r => r.FECHA === 0 ? r.FECHA = 's/f' : r.FECHA)` — `.map` para side effects, usar `.forEach` | 🟡 |
| 4.4 | Pool wrappers: `resolveQuery`/`poolExecute` llaman `getConnection()` y `releaseConnection()` manualmente. `pool.execute()` ya hace pooling automático — el código tal cual está añade complejidad innecesaria | 🟡 |
| 4.5 | ~~`nextjs/lib/auth.ts` y `nextjs/lib/adminApi.ts` sin terminar~~ — **resuelto** (junio 2026): admin migrado a Next.js | ✅ |
| 4.6 | `index.js` mezcla bootstrap + sitemap (~80 líneas) + robots + estáticos. Sacar el sitemap a `src/routes/seo.js` | 🟢 |
| 4.7 | `frontend/src/App.vue:7-12` tiene código comentado de `postLogin` con `BASIC_USER/BASIC_PASS` env vars que no se usan | 🟢 |

---

## 5. Operativa

### 5.1 Sin backups documentados de la BD 🔴
- No hay script de `mysqldump` ni cron documentado.
- 4 200 marchas + relaciones serían dolorosas de reintroducir si se pierden.
- **Fix mínimo**: cron en el VPS con `mysqldump | gzip > /var/backups/mdc-$(date +%F).sql.gz` rotando los 14 últimos días.

### 5.2 Sin CI/CD 🟠
- Despliegue: tar + scp + ssh + `docker compose up`. Manual y propenso a olvidar pasos (ej.: `docker system prune` para no llenar el disco).
- **Fix mínimo**: script `scripts/deploy.sh` que lo automatice. CI completo (GitHub Actions) es opcional.

### 5.3 Sin observabilidad 🟠
- `docker logs --tail 200` es el único panel disponible.
- En el VPS no hay alerta si el contenedor cae, ni si la BD se desconecta.
- **Fix mínimo**: healthcheck en Docker + `restart: unless-stopped` (ya está) + uptime checker externo (UptimeRobot gratis).

### 5.4 Sin tests 🟡
- Ningún test de API ni de frontend.
- Para 4 entidades y una API simple, una suite de smoke tests (10-15 tests) cubriría el 80% de regresiones.
- **Fix opcional**: `vitest` + `supertest` para Express + Playwright para Next.js (golden paths).

### 5.5 Configuraciones fantasma 🟢
- `.htaccess` (Apache) no se usa — el VPS corre Nginx.
- `.vercel/` y `vercel.json` no se usan — el deploy es a VPS.
- `ecosystem.config.js` (PM2) no se usa — el deploy es Docker.
- **Fix**: borrarlos o moverlos a `archive/` con un README explicando que son históricos.

---

## 6. Inconsistencias

| # | Item |
|---|------|
| 6.1 | `disco_marcha.IDMARCHA` (sin underscore) vs `marcha.ID_MARCHA` — confunde, pero no es trivial de cambiar |
| 6.2 | Motores mixtos InnoDB/MyISAM — imposibilita transacciones cross-table |
| 6.3 | Collations mixtos (`utf8_spanish_ci`, `utf8_general_ci`, `utf8mb4_spanish_ci`) — genera warnings en JOIN |
| 6.4 | `FECHA` como `INT(11)` con `0` significando "sin fecha" — debería ser `SMALLINT UNSIGNED NULL` |
| 6.5 | `videos` (357 filas) no aparece en la API — tabla zombi, decidir si reactivar o borrar |
| 6.6 | `users` (0 filas) tabla vacía sin uso — borrar |

(Ver [db-analysis.md](db-analysis.md) para detalle de cada uno.)

---

## 7. Resumen de prioridades

| Prioridad | Items | Esfuerzo aproximado |
|-----------|-------|---------------------|
| 🔴 Urgente | 1.1-1.3, 2.1-2.3, 5.1 | 4-8 horas |
| 🟠 Alta | 1.4-1.6, 2.4, 3, 4.1-4.4, 5.2-5.3 | 1-3 días |
| 🟡 Media | 2.5, 4.5-4.7, 5.4, 6.3-6.4 | A criterio |
| 🟢 Baja | 5.5, 6.5-6.6, fast follows | Cuando toque |

Plan secuenciado en [roadmap.md](roadmap.md).
