# Análisis de base de datos `jaguerra27_mdc`

> Generado: 2026-06-01

## Inventario de tablas

| Tabla | Motor | Collation | Filas |
|-------|-------|-----------|-------|
| `marcha` | MyISAM | utf8_spanish_ci | 4 212 |
| `marcha_autor` | MyISAM | utf8_spanish_ci | 4 724 |
| `autor` | MyISAM | utf8_spanish_ci | 827 |
| `banda` | MyISAM | utf8_spanish_ci | 268 |
| `disco_marcha` | InnoDB | utf8_general_ci | 4 478 |
| `disco` | InnoDB | utf8mb4_spanish_ci | 431 |
| `videos` | MyISAM | utf8_general_ci | 357 |
| `usuarios` | InnoDB | utf8_spanish_ci | 3 |
| `login_autor` | InnoDB | utf8_general_ci | 9 |
| `users` | InnoDB | utf8_spanish_ci | 0 (vacía) |

---

## Puntos fuertes

- Pool de conexiones bien configurado (límite 10, keepAlive), dos pools separados readonly/admin.
- Segregación de privilegios: `jaguerra27_readonly` para lectura, `jaguerra27_user` para escritura.
- FULLTEXT indexes en `marcha.TITULO`, `marcha.DEDICATORIA`, `autor.APELLIDOS/NOMBRE/NOMBRE_ART`.
- Collation `utf8_spanish_ci` en tablas principales — maneja ñ, tildes y orden español correctamente.
- Prepared statements generalizados (`pool.execute(sql, params)`).
- Login robusto: PBKDF2/SHA-512 con 210 000 iteraciones, timing-safe comparison, rate limiting (6 intentos/15 min), auto-upgrade desde MD5 en primer login exitoso.
- Allowlist de campos en admin (`EDITABLE_MARCHA_FIELDS`, `INSERTABLE_MARCHA_FIELDS`) — previene mass assignment.

---

## Bugs activos (rompen funcionalidad)

### 1. `db.connection` no existe
`banda.js:82` y `disco.js:14` usan `db.connection.query()`, pero `db.js` solo exporta `pool`.  
Las rutas `GET /api/banda/all` y `GET /api/disco/all` crashean con `TypeError` en cada petición.

### 2. SQL inválido sin parámetros de búsqueda
`/api/marcha/search`, `/api/autor/search`, `/api/banda/search` y `/api/disco/search` construyen:
```
SELECT ... WHERE  GROUP BY ...   ← SQL inválido
```
Si el usuario llama sin ningún query param, se genera error 500. Solución: inicializar `sql_search` con `['1=1']`.

### 3. `getTimeline` nunca funciona
`banda.js:13`: `forEach` con función `async` no es awaitable. Los `while` loops de `FORMACION_ANT/SIG` se ejecutan después de que `getTimeline` ya retornó. La timeline siempre devuelve solo la banda inicial. Reescribir con `for...of` + `await`.

### 4. FULLTEXT con wildcards incorrectos
`marcha.js:22` y `autor.js:61` pasan `%termino%` a `MATCH(...) AGAINST(?)`.  
El `%` es sintaxis LIKE, irrelevante en FULLTEXT natural language mode → resultados degradados.  
Corregir: pasar `termino` sin wildcards.

---

## Vulnerabilidades de seguridad

| Severidad | Descripción |
|-----------|-------------|
| 🔴 Crítica | `login_autor`: 9 registros con contraseñas MD5 sin salt (hashes de 32 chars). MD5 es criptográficamente roto. Tabla no usada por la API actual pero existe en la BD. |
| 🔴 Alta | `usuarios`: 2 de 3 registros (`estprocesional`, `121`) aún con MD5. El auto-upgrade solo ocurre en el login — si no vuelven a autenticarse, nunca se migran. |
| 🟠 Media | `COOKIE_SECURE=false` en `.env` de producción. Con HTTPS activo en nginx, debería ser `true`. |
| 🟠 Media | Rate limiting en memoria (`Map` en Node.js). Se pierde en cada restart del proceso. Migrar a Redis o tabla BD para persistir entre reinicios. |
| 🟡 Baja | `WHERE ID_BANDA LIKE ?` sobre columna `INT` en `banda.js:125` y `autor.js:78`. Funciona sin wildcards, pero debería ser `= ?`. |

---

## Índices faltantes (impacto en rendimiento)

Las queries más frecuentes de la API hacen JOINs en columnas sin índice:

| Columna | Tabla | Impacto |
|---------|-------|---------|
| `ID_DISCO` | `disco_marcha` | Full scan 4 478 filas en cada `GET /api/disco/:id` |
| `IDMARCHA` | `disco_marcha` | Full scan en cada `GET /api/marcha/:id` y detalle de banda |
| `BANDADISCO` | `disco` | Query de discos de banda sin índice |
| `BANDA_ESTRENO` | `marcha` | Query de estrenos en `GET /api/banda/:id` sin índice |
| `ID_AUTOR` | `marcha_autor` | Solo hay índice compuesto (ID_MARCHA, ID_AUTOR); buscar por autor solo es lento |

```sql
-- Ejecutar con usuario jaguerra27_user:
ALTER TABLE disco_marcha ADD INDEX idx_dm_disco (ID_DISCO);
ALTER TABLE disco_marcha ADD INDEX idx_dm_marcha (IDMARCHA);
ALTER TABLE disco ADD INDEX idx_disco_banda (BANDADISCO);
ALTER TABLE marcha ADD INDEX idx_marcha_banda_estreno (BANDA_ESTRENO);
ALTER TABLE marcha_autor ADD INDEX idx_ma_autor (ID_AUTOR);
```

---

## Integridad referencial

No hay foreign keys declarados. Huérfanos actuales:

| Problema | Cantidad |
|----------|----------|
| `disco_marcha` → marchas inexistentes | 27 |
| `disco_marcha` → discos inexistentes | 2 |
| `marcha_autor` → marchas inexistentes | 4 |
| `marcha_autor` → autores inexistentes | 10 |

`addMarcha` (`adminMarcha.js:108`) hace INSERT en `marcha` + INSERT en `marcha_autor` sin transacción.  
Si el segundo falla, queda una marcha sin autor sin posibilidad de rollback (`marcha` es MyISAM).

---

## Inconsistencias de arquitectura

- **Motores mixtos**: `autor`, `banda`, `marcha`, `marcha_autor`, `videos` en MyISAM; resto en InnoDB. No se pueden hacer transacciones entre tablas MyISAM.
- **Collation mixto**: `utf8_spanish_ci` vs `utf8_general_ci` (disco_marcha, videos) vs `utf8mb4_spanish_ci` (disco). Puede generar advertencias en JOINs y ordenación inconsistente.
- **Índices FULLTEXT redundantes en `autor`**: existen 3 índices (`APELLIDOS`, `APELLIDOS_2`, `APELLIDOS_3`). Solo `APELLIDOS_3` (APELLIDOS+NOMBRE+NOMBRE_ART) es necesario. Los otros dos duplican espacio y ralentizan escrituras.
- **`FECHA` como `int(11)`**: almacenar años como enteros impide funciones de fecha de MySQL. El valor `0` se usa como "sin fecha" en vez de `NULL`.
- **Tablas muertas**: `users` (0 filas), `videos` (357 filas, nunca consultada por la API), `login_autor` (sistema de login anterior).

---

## Calidad de datos

| Campo | Vacíos / Total | % |
|-------|---------------|---|
| `marcha.AUDIO` | 2 082 / 4 212 | 49% |
| `marcha.PROVINCIA` | 1 652 / 4 212 | 39% |
| `marcha.BANDA_ESTRENO` | 782 / 4 212 | 19% |
| `marcha.DEDICATORIA` | 650 / 4 212 | 15% |
| `marcha.FECHA` | 247 / 4 212 | 6% |
| `disco.BANDADISCO` | 6 / 431 | 1% |

Los títulos duplicados (Misericordia ×10, Jesús Nazareno ×7, etc.) son legítimos — distintas marchas con el mismo nombre. Conviene mostrar autor y año junto al título en la UI para diferenciarlas.

---

## Prioridades de acción

| Prioridad | Acción |
|-----------|--------|
| 🔴 Urgente | Migrar los 2 usuarios con MD5 en `usuarios` (forzar password reset) |
| 🔴 Urgente | Limpiar o eliminar `login_autor` (MD5 sin salt, tabla obsoleta) |
| 🔴 Bug | Corregir `WHERE` vacío en los 4 endpoints de search (`WHERE 1=1`) |
| 🔴 Bug | Corregir `db.connection` → `db.pool` en `banda.js` y `disco.js` |
| 🔴 Bug | Reescribir `getTimeline` con `for...of` + `await` |
| 🟠 Alta | Añadir los 5 índices faltantes en columnas de JOIN |
| 🟠 Alta | `COOKIE_SECURE=true` en producción |
| 🟠 Alta | Corregir parámetros de FULLTEXT (quitar `%`) |
| 🟡 Media | Migrar tablas MyISAM a InnoDB y añadir FK constraints |
| 🟡 Media | Unificar collation a `utf8mb4_spanish_ci` |
| 🟡 Media | Eliminar índices FULLTEXT redundantes en `autor` |
| 🟢 Baja | Reemplazar `FECHA int` por `SMALLINT UNSIGNED NULL` consistente |
| 🟢 Baja | Persistir rate limiting en Redis o tabla BD |
