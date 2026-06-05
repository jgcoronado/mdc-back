# Panel de administración — marchasdecristo.com

> Última actualización: 2026-06-05 (análisis sesión 2)
> Documento complementario de [context.md](context.md) y [roadmap.md](roadmap.md).

---

## 1. Acceso

| | |
|---|---|
| **URL** | `https://marchasdecristo.com/dashboard` |
| **Login** | `https://marchasdecristo.com/login` |
| **Guard** | `nextjs/middleware.ts` — verifica cookie HMAC-SHA256 en todas las rutas `/dashboard/*` antes de servir cualquier página |
| **Sesión** | Cookie `mdc_session`, HttpOnly + Secure + SameSite=lax, TTL 8h |
| **Rate limit** | 6 intentos / 15 min por IP+usuario; bloqueo de 15 min |
| **Contraseñas** | PBKDF2-SHA512 / 210 000 iteraciones; upgrade automático desde MD5 en primer login exitoso |

El middleware verifica el token inline (sin HTTP round-trip). Los Client Components también llaman a `/api/login/verify` al montarse como segunda capa, pero el guard real es el middleware de servidor.

---

## 2. Funcionalidades existentes

### `/dashboard` — Home
- Campo numérico para navegar directamente a la edición de una marcha por ID.
- Botones: "Nueva marcha" → `/dashboard/marcha/add`, "Nuevo autor" → `/dashboard/autor/add`, "Logout".

### `/dashboard/marcha/[id]` — Edición de marcha
Campos editables: `TITULO`, `FECHA`, `DEDICATORIA`, `LOCALIDAD`, `AUDIO`, `BANDA_ESTRENO` (ID numérico a ciegas), `DETALLES_MARCHA`.

**Faltante**: `PROVINCIA` está en la BD y en el alta pero no en este formulario. Los autores se muestran en modo lectura — no se pueden añadir ni quitar.

Muestra previsualización de cambios (diff viejo/nuevo) y SQL preparada con parámetros antes de guardar.

### `/dashboard/marcha/add` — Alta de marcha
Campos: `TITULO`, `FECHA`, `DEDICATORIA`, `LOCALIDAD`, `PROVINCIA`, `BANDA_ESTRENO` (autocomplete por nombre), `DETALLES_MARCHA`, autores (autocomplete multi, mínimo 6 caracteres).

Muestra SQL y parámetros antes de crear.

### `/dashboard/autor/add` — Alta de autor
Campos: `NOMBRE`, `APELLIDOS`, `F_NAC`, `LUGAR_NAC`, `F_DEF`, `BIO`.

**Faltante**: `NOMBRE_ART` (nombre artístico) no está en el formulario ni en el endpoint, aunque sí está indexado en `autor_fts`.

Muestra SQL y parámetros antes de crear.

---

## 3. API de escritura (Route Handlers)

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/api/admin/editMarcha` | POST | cookie/Bearer | Allowlist de 8 campos. Devuelve `UPDATED` / `NOT_FOUND` / `NO_CHANGES` |
| `/api/admin/addMarcha` | POST | cookie/Bearer | INSERT marcha + INSERT marcha_autor (⚠️ sin transacción) |
| `/api/admin/addAutor` | POST | cookie/Bearer | INSERT autor con 6 campos |

Todos verifican sesión con `verifySession(getTokenFromRequest(req))` antes de cualquier operación de BD.

`getTokenFromRequest` acepta tanto cookie como header `Authorization: Bearer`. El middleware solo usa cookies, por lo que el flujo Bearer es un camino alternativo que no pasa por el guard de servidor.

---

## 4. Problemas de seguridad identificados

### 4.1 Sin transacción en `addMarcha` 🔴
`addMarcha/route.ts:25-41` — dos `dbRun` separados. Si el INSERT en `marcha_autor` falla, la marcha queda en la BD sin autores. Las búsquedas públicas la filtran (por `EXISTS marcha_autor`), pero es basura silenciosa en la BD. Ver [roadmap.md §U1](roadmap.md).

### 4.2 `autoresIds` no validados contra la BD 🔴
Los IDs de autor se parsean del body pero no se verifica que existan en la tabla `autor`. Sin FK constraints activos, se crean relaciones huérfanas. Ver [roadmap.md §U2](roadmap.md).

### 4.3 SQL y parámetros expuestos en la UI 🟡
Los formularios muestran la SQL preparada y los valores en pantalla. Sin riesgo de ataque directo (solo el admin accede), pero puede quedar en capturas de pantalla o en el historial del navegador. Ver [roadmap.md §M6](roadmap.md).

### 4.4 Sin audit log 🟠
No hay registro de qué cambió, cuándo y quién. Ver [roadmap.md §M1](roadmap.md).

### 4.5 Flujo Bearer alternativo 🟡
`getTokenFromRequest` acepta `Authorization: Bearer` además de la cookie. Es un camino que no pasa por el middleware, aunque sí pasa por `verifySession`. Si se quiere simplificar la superficie: restringir a cookie-only en los Route Handlers admin.

---

## 5. Funciones faltantes (por prioridad)

| Prioridad | Función | Ficheros a crear/modificar |
|-----------|---------|---------------------------|
| 🔴 U3 | Aviso en UI al crear marcha sin autores | `marcha/add/page.tsx` |
| 🟠 A2 | `PROVINCIA` en edición de marcha | `marcha/[id]/page.tsx`, `editMarcha/route.ts` |
| 🟠 A3 | `NOMBRE_ART` en alta/edición de autor | `addAutor/route.ts`, `autor/add/page.tsx` |
| 🟠 A4 | Edición de autores (`/dashboard/autor/[id]`) | nuevo page + `editAutor/route.ts` |
| 🟠 A5 | Editar autores de una marcha | `marcha/[id]/page.tsx` + `editMarchaAutores/route.ts` |
| 🟡 M4 | Buscador de marchas/autores en el dashboard | `/dashboard/page.tsx` |
| 🟡 M5 | Enlace a edición/público tras crear | `marcha/add`, `autor/add` |
| 🟡 M6 | Eliminar SQL preview de la UI | `marcha/add`, `autor/add`, `marcha/[id]` |
| 🟢 B3 | Alta y edición de bandas | nuevo `/dashboard/banda/*` |
| 🟢 B4 | Alta y edición de discos + pistas | nuevo `/dashboard/disco/*` |

---

## 6. Lo que funciona bien

- **Middleware server-side**: el guard en `middleware.ts` es síncrono y no tiene red — es la capa de protección real.
- **Allowlist de campos**: `EDITABLE_FIELDS` en `editMarcha` y `INSERTABLE_FIELDS` en `addMarcha`/`addAutor` previenen mass-assignment.
- **Timing-safe en verificación**: `verifySession` usa `crypto.timingSafeEqual`.
- **Autocomplete con mínimo de caracteres**: 6 chars mínimos antes de buscar evitan queries triviales.
- **Previsualización de cambios**: el diff viejo/nuevo antes de guardar en edición de marcha es útil para revisión manual.
- **Prepared statements**: sin concatenación de SQL en ningún Route Handler.
