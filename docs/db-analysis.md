# Análisis de base de datos — SQLite (estado actual)

> Actualizado: 2026-07-10 (estilo de marcha) · 2026-07-08 (modelo de linaje de bandas) · 2026-06-05 (sesión 2)
> El documento original analizaba el esquema MySQL (2026-06-01). Ese análisis es histórico — todos los bugs de motores mixtos, collation y FULLTEXT con `%` quedaron resueltos o irrelevantes al migrar a SQLite en la Fase 3b.
> 2026-07-08: el linaje de bandas dejó de guardarse en columnas `FORMACION_ANT/SIG` y pasó a la tabla `banda_relacion` (ver §Modelo de linaje).
> 2026-07-10: nueva columna `marcha.ESTILO` (`CCTT`/`AM`/`NULL`), ver §Estilo de marcha.

---

## Inventario de tablas

| Tabla | Filas | Uso en la API |
|-------|-------|---------------|
| `marcha` | 4 212 | ✅ lectura + escritura admin |
| `autor` | 827 | ✅ lectura + escritura admin |
| `banda` | 268 | ✅ lectura |
| `banda_relacion` | 14 | ⚠️ modelo de linaje (creada 2026-07-08; sin lectura en `Repo` todavía) |
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
marcha      : ID_MARCHA, TITULO, DEDICATORIA, LOCALIDAD, PROVINCIA, AUDIO, FECHA, BANDA_ESTRENO, ESTILO, DETALLES_MARCHA
autor       : ID_AUTOR, NOMBRE, APELLIDOS, NOMBRE_ART, F_NAC, LUGAR_NAC, F_DEF, BIO
banda        : ID_BANDA, NOMBRE_COMPLETO, NOMBRE_BREVE, LOCALIDAD, PROVINCIA, FECHA_FUND, FECHA_EXT, DIRECTOR_ACTUAL, DIR_MUS_ACTUAL, WEB, LINK_FORO
banda_relacion: ID_RELACION, ID_ORIGEN, ID_DESTINO, TIPO, FECHA_INICIO, FECHA_FIN, NOTA
disco       : ID_DISCO, NOMBRE_CD, FECHA_CD, BANDADISCO, DISCOS, d_DETALLES
marcha_autor: ID_MARCHA, ID_AUTOR
disco_marcha: ID_DM, ID_DISCO, IDMARCHA, N_DISCO, NUMEROMARCHA, DM_ENLAZADA
usuarios    : USUARIO, CLAVE
```

Inconsistencia de nomenclatura heredada del MySQL: `marcha_autor` usa `ID_MARCHA` pero `disco_marcha` usa `IDMARCHA` (sin guión bajo).

---

## Modelo de linaje de bandas (`banda_relacion`)

Hasta 2026-07-08 el linaje se guardaba como lista enlazada lineal en `banda`
(`FORMACION_ANT` / `FORMACION_SIG`, + los slots `-2` que nunca se usaron). Ese
modelo no admitía fusiones (N→1), divisiones (1→N) ni bandas juveniles. Se
sustituyó por una tabla de aristas tipadas (DDL en
[`app/tools/sql/002_banda_relacion.sql`](../php/app/tools/sql/002_banda_relacion.sql);
migración one-shot en `app/tools/migrate_banda_relacion.php`).

Cada fila es un vínculo dirigido `ID_ORIGEN → ID_DESTINO`; el significado lo da `TIPO`:

| `TIPO` | Dirección | Cardinalidad |
|--------|-----------|--------------|
| `renombrado` | formación anterior → formación nueva | 1→1 |
| `fusion` | cada banda que se une → formación resultante | N→1 |
| `division` | banda que se rompe → cada formación nueva | 1→N |
| `juvenil` | banda madre → banda juvenil (usa `FECHA_INICIO`/`FECHA_FIN`) | 1→N |

- `FECHA_INICIO` = año del evento (sucesión) o inicio del vínculo (juvenil); `FECHA_FIN` solo aplica a `juvenil` (`NULL` = vigente).
- Absorción = `fusion` cuyo destino es una banda preexistente (no requiere nada especial).
- Tiene FK reales a `banda(ID_BANDA)` y `UNIQUE(ID_ORIGEN, ID_DESTINO, TIPO, FECHA_INICIO)`.
- **Migración**: los 15 vínculos lineales previos entraron como `renombrado`, menos la arista inversa anómala `41→68` (par recíproco: se conservó solo `68→41`, 2003). Resultado: 14 filas.
- **Pendiente**: `Repo::fetchBanda` aún no lee esta tabla (el `timeline` es de un solo elemento); el render del linaje está por construir.

---

## Estilo de marcha (`marcha.ESTILO`)

Columna nueva (`TEXT CHECK (ESTILO IN ('CCTT','AM'))`, `NULL` = sin asignar),
añadida y rellenada por la migración one-shot
[`app/tools/migrate_marcha_estilo.php`](../php/app/tools/migrate_marcha_estilo.php).
El estilo no se guarda en `banda` — no hay columna de tipo de banda — sino que
se deriva por nombre cada vez que se ejecuta el backfill:

1. Se clasifica cada banda por su nombre: `NOMBRE_COMPLETO` con "Cornetas y
   Tambores" → `CCTT`; con "Agrupación Musical" (o el prefijo `AM `) → `AM`.
   Si el nombre completo no lo deja claro se cae a `NOMBRE_BREVE` (prefijo
   `AM `/`BCT `). Sin ninguna de las dos señales, la banda queda sin estilo
   (2 de 268: `banda#0` "Varias bandas" y `banda#80`, banda militar sin
   nomenclatura CCTT/AM).
2. Cada marcha toma el estilo de su banda de estreno (`marcha.BANDA_ESTRENO`).
3. Si no hay estreno (o la banda de estreno no tiene estilo claro), toma el
   estilo de la banda de su primera grabación documentada — mismo criterio y
   orden que usa `Repo::fetchMarcha()` para "primera grabación": `disco_marcha`
   + `disco`, `ORDER BY FECHA_CD ASC, NOMBRE_CD ASC`, banda = `DM_BANDA` si
   existe, si no `BANDADISCO`.
4. Si ninguna de las dos resuelve, la marcha queda `ESTILO = NULL` (pendiente
   de asignar a mano desde el panel admin).

Resultado del backfill (2026-07-10, 4 271 marchas): 1 586 `CCTT`, 2 087 `AM`,
598 pendientes. Editable por marcha desde `/dashboard/marcha/{id}` (campo
"Estilo"), o en bloque desde `/dashboard/estilos` (ver
[admin-panel.md §8](admin-panel.md)) — pensada para resolver las pendientes;
se muestra en la ficha pública cuando está asignado.

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
| `idx_rel_origen` | `banda_relacion.ID_ORIGEN` | Linaje hacia delante / juveniles de una banda |
| `idx_rel_destino` | `banda_relacion.ID_DESTINO` | Linaje hacia atrás / madre de una juvenil |
| `idx_rel_tipo` | `banda_relacion.TIPO` | Filtrar por tipo de relación |

### Prepared statements
`dbAll` y `dbRun` en `lib/db.ts` usan `getDb().prepare(sql).all/run`. No hay concatenación de SQL en ningún punto del código.

### WAL mode
Permite lecturas sin bloquear escrituras. En un servidor de un solo usuario admin esto no es crítico, pero es la configuración correcta para SQLite en producción.

---

## Problemas activos

### 1. `foreign_keys = ON` sin FK constraints declaradas 🟠
El PRAGMA activa la verificación, pero si las tablas no tienen `FOREIGN KEY` en sus `CREATE TABLE`, el PRAGMA no tiene nada que verificar. La integridad referencial no está siendo forzada — **salvo en `banda_relacion`** (creada 2026-07-08), que sí declara FK a `banda(ID_BANDA)`.

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
