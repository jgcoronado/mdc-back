# Análisis de base de datos — SQLite (estado actual)

> Actualizado: 2026-06-05 (sesión 2)
> El documento original analizaba el esquema MySQL (2026-06-01). Ese análisis es histórico — todos los bugs de motores mixtos, collation y FULLTEXT con `%` quedaron resueltos o irrelevantes al migrar a SQLite en la Fase 3b.

---

## Inventario de tablas

| Tabla | Filas | Uso en la API |
|-------|-------|---------------|
| `marcha` | 4 212 | ✅ lectura + escritura admin |
| `autor` | 827 | ✅ lectura + escritura admin |
| `banda` | 268 | ✅ lectura |
| `disco` | 431 | ✅ lectura |
| `marcha_autor` | 4 724 | ✅ lectura + escritura admin |
| `disco_marcha` | 4 478 | ✅ lectura |
| `usuarios` | 3 | ✅ auth |
| `marcha_fts` | virtual | ✅ búsqueda full-text |
| `autor_fts` | virtual | ✅ búsqueda full-text |
| `videos` | 357 | ❌ nunca consultada |
| `users` | 0 | ❌ vacía, nunca usada |

`login_autor` fue eliminada durante la migración MySQL → SQLite (tenía 9 hashes MD5 sin salt).

---

## Campos principales por tabla

```
marcha      : ID_MARCHA, TITULO, DEDICATORIA, LOCALIDAD, PROVINCIA, AUDIO, FECHA, BANDA_ESTRENO, DETALLES_MARCHA
autor       : ID_AUTOR, NOMBRE, APELLIDOS, NOMBRE_ART, F_NAC, LUGAR_NAC, F_DEF, BIO
banda       : ID_BANDA, NOMBRE_BREVE, NOMBRE_COMPLETO, LOCALIDAD, PROVINCIA, FECHA_FUND, FECHA_EXT, FORMACION_ANT, FORMACION_SIG
disco       : ID_DISCO, NOMBRE_CD, FECHA_CD, BANDADISCO, DISCOS, d_DETALLES
marcha_autor: ID_MARCHA, ID_AUTOR
disco_marcha: ID_DM, ID_DISCO, IDMARCHA, N_DISCO, NUMEROMARCHA, DM_ENLAZADA
usuarios    : USUARIO, CLAVE
```

Inconsistencia de nomenclatura heredada del MySQL: `marcha_autor` usa `ID_MARCHA` pero `disco_marcha` usa `IDMARCHA` (sin guión bajo).

---

## Configuración SQLite (lib/db.ts)

```ts
_db.pragma('journal_mode = WAL');    // lecturas concurrentes sin bloquear escrituras
_db.pragma('foreign_keys = ON');     // ⚠️ activo pero sin FK declarations en las tablas
_db.pragma('busy_timeout = 5000');   // 5s antes de SQLITE_BUSY en contención
```

---

## Puntos fuertes

### FTS5 con triggers sincronizados
`schema.sql` define `marcha_fts` y `autor_fts` con triggers AFTER INSERT / UPDATE / DELETE. Cuando el panel de admin edita un título o un nombre de autor, el índice FTS5 se actualiza automáticamente. No hay desincronización posible.

FTS configurado con `tokenize="unicode61 remove_diacritics 2"` — ignora tildes y normaliza Unicode. Las búsquedas de `garcia` encuentran `García`.

### Índices completos
Todos los índices identificados como faltantes en el análisis MySQL ya están en `schema.sql`:

| Índice | Columna | Para qué sirve |
|--------|---------|----------------|
| `idx_dm_disco` | `disco_marcha.ID_DISCO` | Listar marchas de un disco |
| `idx_dm_marcha` | `disco_marcha.IDMARCHA` | Discos de una marcha |
| `idx_disco_banda` | `disco.BANDADISCO` | Discos de una banda |
| `idx_marcha_banda_estreno` | `marcha.BANDA_ESTRENO` | Marchas estrenadas por una banda |
| `idx_ma_marcha` | `marcha_autor.ID_MARCHA` | Autores de una marcha |
| `idx_ma_autor` | `marcha_autor.ID_AUTOR` | Marchas de un autor |
| `idx_banda_formacion_ant` | `banda.FORMACION_ANT` | Timeline de bandas hacia atrás |
| `idx_banda_formacion_sig` | `banda.FORMACION_SIG` | Timeline de bandas hacia adelante |

### Prepared statements
`dbAll` y `dbRun` en `lib/db.ts` usan `getDb().prepare(sql).all/run`. No hay concatenación de SQL en ningún punto del código.

### WAL mode
Permite lecturas sin bloquear escrituras. En un servidor de un solo usuario admin esto no es crítico, pero es la configuración correcta para SQLite en producción.

---

## Problemas activos

### 1. `foreign_keys = ON` sin FK constraints declaradas 🟠
El PRAGMA activa la verificación, pero si las tablas no tienen `FOREIGN KEY` en sus `CREATE TABLE`, el PRAGMA no tiene nada que verificar. La integridad referencial no está siendo forzada.

**Huérfanos heredados de la migración MySQL** (presentes en la BD de producción):

| Relación | Huérfanos |
|----------|-----------|
| `disco_marcha.IDMARCHA` → marchas inexistentes | 27 |
| `disco_marcha.ID_DISCO` → discos inexistentes | 2 |
| `marcha_autor.ID_MARCHA` → marchas inexistentes | 4 |
| `marcha_autor.ID_AUTOR` → autores inexistentes | 10 |

Script para verificar estado actual:
```sql
-- Huérfanos en disco_marcha → marcha
SELECT COUNT(*) FROM disco_marcha dm
  WHERE NOT EXISTS (SELECT 1 FROM marcha m WHERE m.ID_MARCHA = dm.IDMARCHA);

-- Huérfanos en disco_marcha → disco
SELECT COUNT(*) FROM disco_marcha dm
  WHERE NOT EXISTS (SELECT 1 FROM disco d WHERE d.ID_DISCO = dm.ID_DISCO);

-- Huérfanos en marcha_autor → marcha
SELECT COUNT(*) FROM marcha_autor ma
  WHERE NOT EXISTS (SELECT 1 FROM marcha m WHERE m.ID_MARCHA = ma.ID_MARCHA);

-- Huérfanos en marcha_autor → autor
SELECT COUNT(*) FROM marcha_autor ma
  WHERE NOT EXISTS (SELECT 1 FROM autor a WHERE a.ID_AUTOR = ma.ID_AUTOR);
```

Plan de acción en [roadmap.md §A1](roadmap.md).

### 2. Serialización `GROUP_CONCAT` de autores frágil 🟠
Todas las queries que devuelven autores usan:
```sql
GROUP_CONCAT(a.ID_AUTOR || '#' || a.NOMBRE || ' ' || a.APELLIDOS, '|')
```
Y `lib/db.ts:formatAutor` parsea el resultado por `|` y `#`. Si un nombre contiene `#` o `|`, el parseo devuelve datos corruptos silenciosamente.

Con 827 autores actuales el riesgo es bajo (ningún nombre cofrade conocido contiene esos caracteres), pero es frágil por diseño.

**Fix**: sustituir por `json_group_array(json_object('autorId', a.ID_AUTOR, 'nombre', a.NOMBRE || ' ' || a.APELLIDOS))` + `JSON.parse` en `formatAutor`. Plan en [roadmap.md §M2](roadmap.md).

### 3. Marchas sin autores son invisibles en búsquedas 🟡
Todas las queries públicas filtran con:
```sql
EXISTS (SELECT 1 FROM marcha_autor ma WHERE ma.ID_MARCHA = m.ID_MARCHA)
```
Una marcha creada sin autores no aparece en ninguna búsqueda pública. Esta es una regla de negocio válida, pero combinada con el bug U1 (sin transacción en addMarcha), puede dejar marchas invisibles en la BD sin que el admin lo sepa.

### 4. `autor.NOMBRE_ART` indexado pero no gestionable 🟡
El trigger `autor_ai` sincroniza `NOMBRE_ART` al FTS5. El endpoint `addAutor` no incluye `NOMBRE_ART` en `INSERTABLE_FIELDS`. Los compositores con nombre artístico (p.ej. Abel Moreno "Miguelito") no pueden registrarlo desde el panel. Plan en [roadmap.md §A3](roadmap.md).

### 5. `FECHA` como INTEGER con `0` = "sin fecha" 🟡
247 de 4 212 marchas tienen `FECHA = 0`. `lib/api.ts:normalizeFecha` convierte `0` y `''` a `'s/f'` como parche de presentación. Semánticamente correcto sería `NULL`.

No tiene impacto en rendimiento ni en búsquedas actuales (las búsquedas por fecha usan `>=` y `<=`, y `0` no interfiere con esos rangos en la práctica). Plan en [roadmap.md §B2](roadmap.md).

### 6. Tablas muertas 🟢
- `videos` (357 filas): existía en MySQL para vídeos de YouTube. No hay Route Handler que la exponga. Ninguna página la usa.
- `users` (0 filas): tabla vacía sin uso conocido.

Plan en [roadmap.md §B1](roadmap.md).

---

## Calidad de datos

| Campo | Vacíos / Total | % | Impacto |
|-------|---------------|---|---------|
| `marcha.AUDIO` | ~2 082 / 4 212 | ~49% | Sin impacto en búsquedas; campo informativo |
| `marcha.PROVINCIA` | ~1 652 / 4 212 | ~39% | Filtro de búsqueda por provincia menos efectivo |
| `marcha.BANDA_ESTRENO` | ~782 / 4 212 | ~19% | No enlaza a la banda en la página de detalle |
| `marcha.DEDICATORIA` | ~650 / 4 212 | ~15% | Normal para marchas antiguas |
| `marcha.FECHA` | 247 / 4 212 | 6% | Aparece "s/f" en la UI |

Los títulos duplicados (Misericordia ×10, Jesús Nazareno ×7, etc.) son legítimos — distintas marchas con el mismo nombre compuestas por distintos autores.

---

## Prioridades de acción

Ver plan detallado en [roadmap.md §Fase 4](roadmap.md).

| Prioridad | Ítem |
|-----------|------|
| ✅ U1 | Transacción en `addMarcha` — resuelto 2026-06-05 |
| ✅ U2 | Validar existencia de `autoresIds` — resuelto 2026-06-05 |
| 🟠 A1 | Limpiar huérfanos + declarar FK constraints |
| 🟠 A3 | `NOMBRE_ART` en alta/edición de autor |
| 🟠 M2 | Migrar serialización AUTHORS a JSON |
| 🟡 B2 | Normalizar `FECHA = 0` → `NULL` |
| 🟢 B1 | Eliminar tablas muertas (`videos`, `users`) |
