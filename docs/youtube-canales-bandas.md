# Canales de YouTube de las bandas — estado de la investigación

## Objetivo

Completar `tools/ingest/config/canales.csv` (real, gitignored, se carga con
`php php/app/tools/load_canales.php`) con el canal oficial de YouTube de
cada banda de la tabla `banda`.

## Origen de los datos

Este repo no trae `data/mdc.db` (está en `.gitignore`, vive en el VPS). Para
esta investigación el usuario subió una copia de `mdc.db` a la sesión de
Claude Code. Esa copia **no se ha commiteado** (es un dump de producción) y
no queda guardada de una sesión a otra — si se retoma este trabajo en otra
sesión y hace falta volver a consultar la BD, hay que pedir al usuario que
la vuelva a subir, o trabajar solo con `canales_propuesta.csv` (ver abajo),
que ya contiene todo el contexto necesario (nombre, localidad, fechas,
último disco).

Query usada para extraer el listado base (267 bandas, excluyendo el
ID 0 "Varias bandas"):

```sql
SELECT b.ID_BANDA, b.NOMBRE_COMPLETO, b.NOMBRE_BREVE, b.LOCALIDAD, b.PROVINCIA,
  b.FECHA_FUND, b.FECHA_EXT, b.WEB,
  (SELECT MAX(CAST(d.FECHA_CD AS REAL)) FROM disco d WHERE d.BANDADISCO = b.ID_BANDA) AS ULTIMO_DISCO
FROM banda b
WHERE b.ID_BANDA <> 0
ORDER BY b.ID_BANDA
```

## Metodología

- Se calculó una `PRIORIDAD` por banda: **alta** (activa, disco publicado
  desde 2006 — últimos 20 años), **media** (activa, sin disco reciente o sin
  dato) o **baja** (`FECHA_EXT` rellena, banda extinta).
- Se repartió el listado en 10 lotes de ~27 bandas y se lanzó un agente por
  lote (herramienta `WebSearch`; `WebFetch` a sitios externos no funciona en
  este entorno, está bloqueado por política de red — solo `WebSearch`, que
  corre en infraestructura de Anthropic, consigue resultados).
- Cada banda se clasificó como:
  - **exacto**: canal oficial identificado con razonable seguridad (nombre/handle
    coincide, contenido son marchas/actuaciones de esa banda concreta en esa
    localidad). Se descartan explícitamente los canales "Topic" autogenerados
    por YouTube como "exacto" (no están gestionados por la banda).
  - **dudoso**: hay candidato(s) pero con ambigüedad (homónimos en otra
    localidad, canal Topic, playlist sin canal propio claro, posible canal de
    la hermandad/cofradía en vez de la banda, fusiones de bandas, etc).
  - **no_encontrado**: nada plausible tras la búsqueda.
- Se tuvo cuidado especial con bandas homónimas en distintas localidades
  (mismo nombre, distinto pueblo/ciudad) para no asignar el canal equivocado.

## Resultado (267 bandas revisadas)

- **102 exactas** → ya cargadas en `tools/ingest/config/canales.csv` (real,
  entregado directamente al usuario como fichero, no está en git).
- **33 dudosas** → pendientes de confirmación manual del usuario.
- **132 no encontradas** → la mayoría bandas extintas o sin disco en 20+
  años, como se esperaba.

Todo el detalle (incluidas las 132 "no_encontrado") está en
`tools/ingest/config/canales_propuesta.csv`, que sí está versionado, con
columnas: `ID_BANDA, NOMBRE_COMPLETO, LOCALIDAD, PROVINCIA, FECHA_FUND,
FECHA_EXT, ULTIMO_DISCO, STATUS, CANAL_URL, CANDIDATOS, NOTA`.

- Filas `STATUS=exacto`: `CANAL_URL` ya rellena.
- Filas `STATUS=dudoso`: `CANAL_URL` vacía a propósito; `CANDIDATOS` lista
  uno o más `url (motivo de la duda)` separados por ` | `; `NOTA` da contexto
  adicional. **Editar `CANAL_URL` a mano** con la URL elegida (o dejarla
  vacía si se descarta) es la forma de resolver cada caso.
- Filas `STATUS=no_encontrado`: sin acción prevista, salvo que el usuario
  quiera reabrir la búsqueda de alguna en concreto.

## Los 33 casos dudosos (resumen para decidir)

Agrupados por motivo de duda — ver `canales_propuesta.csv` para el detalle
completo de cada uno:

1. **Solo canal "Topic" autogenerado** (no gestionado por la banda): IDs
   2, 57, 62, 74, 75, 83, 121, 130.
2. **Solo playlist localizada, canal propietario no confirmado**: IDs 176,
   183, 226, 242, 263, 271.
3. **Dos canales candidatos, no está claro cuál es el vigente**: IDs 110
   (Vera Cruz, Campillos), 136 (Amor y Sacrificio, Lebrija), 255 (Virgen de
   Gracia, Vila-real).
4. **Podría ser el canal de la hermandad/cofradía, no de la banda musical**:
   IDs 63, 79, 97, 157, 227, 239.
5. **Riesgo de confusión con banda homónima de otra localidad**: IDs 17, 61,
   89, 98, 150, 184, 243.
6. **Banda fusionada con otra (cambió de nombre)**: IDs 86, 209, 210 (209 y
   210 se fusionaron en 2019 en "Expiración, Salud y Esperanza"; el canal
   encontrado es de la banda sucesora, no de cada entidad original).

## Cómo retomar este trabajo en otra sesión

1. Abrir `tools/ingest/config/canales_propuesta.csv`.
2. Filtrar `STATUS=dudoso` y revisar con el usuario, fila a fila, cuál
   `CANDIDATOS` (si alguno) rellenar en `CANAL_URL`, o dejarla vacía si se
   descarta.
3. Una vez resuelto, regenerar el `canales.csv` real (gitignored) con las
   filas `STATUS=exacto` **más** las `dudoso` ya confirmadas (con la
   `CANAL_URL` rellena), columnas `ID_BANDA,CANAL_URL` únicamente.
4. Cargar en la BD con:
   `DB_PATH=data/mdc.db php php/app/tools/load_canales.php tools/ingest/config/canales.csv`
5. Opcional: si se quiere reintentar alguna de las 132 "no_encontrado" (por
   ejemplo porque el usuario sabe que sí tienen canal), añadirlas a mano o
   repetir la búsqueda con `WebSearch` para esa banda concreta.
