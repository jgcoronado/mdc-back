# Ingesta de marchas desde YouTube

Herramienta **offline** para descubrir marchas nuevas (2019→) a partir de los
canales de YouTube de las bandas de la BD, proponerlas como candidatos y
revisarlas/aceptarlas desde el panel de administración PHP.

## Por qué offline

Producción corre en hosting compartido (HelioHost: solo PHP + cron, sin SSH /
Node / Python). El extractor usa **yt-dlp**, que no puede correr en el host, así
que la extracción y el parseo se hacen en tu PC y solo el **resultado**
(candidatos) se lleva al host para revisarlo. El `.db` de producción **nunca** se
sobrescribe: los candidatos viajan como NDJSON y se importan a la tabla de
staging.

## Flujo completo

```
  canales.csv ─┐
               ▼
        [yt-dlp]  descarga vídeos + metadatos desde 2019      (Fase 1, Node)
               ▼
     [clasificar] estreno / novedad / recuperación / otro     (Fase 2, Node)
               ▼
      [extraer]  título, autor(es), año, dedicatoria, duración (Fase 2, Node)
               ▼
       [dedup]   cruce contra `marcha` existentes             (Fase 3, Node)
               ▼
     candidatos.ndjson ──► import ──► ingest_candidato         (Fase 4, PHP)
               ▼
      panel admin: revisar / editar / aceptar / descartar     (Fase 4, PHP)
               ▼
        marcha + marcha_autor  (al aceptar, en transacción)
```

## Estado: Fase 0 — cimientos ✅

Ya entregado:

- **Esquema de staging** (fuente de verdad): [`php/app/tools/sql/001_ingest_staging.sql`](../../php/app/tools/sql/001_ingest_staging.sql)
  - `ingest_canal` — mapeo banda ↔ canal de YouTube.
  - `ingest_candidato` — marchas propuestas (con campos `P_*` editables y estado de revisión).
  - `ingest_run` — traza de cada ejecución del extractor.
- **Aplicador de migración** (PHP, idempotente, corre en el host):
  `php app/tools/migrate_ingest.php`
- **Cargador del mapeo de canales** (PHP): `php app/tools/load_canales.php <csv>`
- **Diccionario de clasificación**: [`config/keywords.json`](config/keywords.json)
- **Plantilla de canales**: [`config/canales.example.csv`](config/canales.example.csv)

### Cómo aplicar la Fase 0

```bash
# 1. Crear las tablas de staging (en local apunta a tu copia con DB_PATH)
DB_PATH=data/mdc.db php php/app/tools/migrate_ingest.php

# 2. Preparar tu lista de canales (copia la plantilla y edítala)
cp tools/ingest/config/canales.example.csv tools/ingest/config/canales.csv
#    ...rellena ID_BANDA + CANAL_URL de cada banda...

# 3. Cargarla en ingest_canal
DB_PATH=data/mdc.db php php/app/tools/load_canales.php tools/ingest/config/canales.csv
```

En el host es igual pero sin `DB_PATH` (usa el `db_path` de `config.php`):

```bash
/usr/local/bin/php /home/USUARIO/app/tools/migrate_ingest.php
```

## Fase 1 — extractor yt-dlp ✅

[`extract.mjs`](extract.mjs) (Node, sin dependencias nativas; requiere `yt-dlp` en el
PATH). Lee `config/canales.csv`, raspa las pestañas `/videos` y `/streams` de cada
canal (los directos son clave: muchos estrenos son emisiones en vivo), filtra desde
2019 y vuelca:

- `out/raw/<ID_BANDA>-<slug>.ndjson` — caché slim por canal (reanudable).
- `out/videos.ndjson` — dataset combinado y deduplicado por `video_id`.

Cada registro: `id_banda, video_id, url, titulo, descripcion, publicado (ISO),
duracion_seg, live_status, tab, channel, channel_id`. Los vídeos de solo-miembros o
próximos estrenos se omiten.

```bash
cd tools/ingest
node extract.mjs --only 16 --max 3 --sleep 0   # smoke test (3 vídeos de una banda)
node extract.mjs                               # pasada real: todos los canales, desde 2019
node extract.mjs --force                       # ignora la caché y vuelve a bajar
```

## Fase 2 — clasificador + extractor heurístico ✅

[`classify.mjs`](classify.mjs) lee `out/videos.ndjson` (salida de la Fase 1) y por
cada vídeo:

1. **Clasifica** por keywords de [`config/keywords.json`](config/keywords.json):
   `estreno` / `novedad` / `recuperacion` / `otro` (los `otro` se descartan y no
   pasan a candidatos — la mayoría de vídeos de un canal son conciertos de
   marchas ya existentes, no estrenos).
2. **Extrae** los campos propuestos (`P_*`) de título/descripción:
   - `P_TITULO` — primero busca texto entrecomillado; si no hay, corta por el
     primer separador (`|`, `-`, `:`, …) tras quitar prefijos tipo "ESTRENO MUNDIAL:".
   - `P_AUTORES` — busca el nombre del compositor tras el título usando
     conectores habituales ("de", "autoría de", "compuesta por", "original de"…).
   - `P_LOCALIDAD` — patrón "... en `<Localidad>` `<Año>`" del título.
   - `P_FECHA` — año de publicación del vídeo (por defecto; el texto puede
     mencionar un año distinto de composición, que queda para revisión manual).
3. Asigna **confianza** (0–1) y **flags** (`sin_autor_detectado`,
   `sin_localidad_detectada`, `titulo_sin_comillas`, …) para que el panel
   resalte lo que hay que revisar a mano.

Salida: `out/candidatos.ndjson` (solo estreno/novedad/recuperación).

```bash
cd tools/ingest
node classify.mjs                 # out/videos.ndjson → out/candidatos.ndjson
node classify.mjs --debug         # + out/descartados.ndjson con el motivo de cada descarte
```

## Próximas fases

- **Fase 3** — dedup contra `marcha` (recuperaciones solo si no existen).
- **Fase 4** — panel de revisión PHP (import NDJSON + aceptar/descartar).
- **Fase 5** — primera pasada real con tu lista de bandas.
