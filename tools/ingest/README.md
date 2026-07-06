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

## Próximas fases

- **Fase 1** — extractor yt-dlp (Node, sin dependencias nativas; emite metadatos crudos).
- **Fase 2** — clasificador + extractor heurístico de campos.
- **Fase 3** — dedup contra `marcha` (recuperaciones solo si no existen).
- **Fase 4** — panel de revisión PHP (import NDJSON + aceptar/descartar).
- **Fase 5** — primera pasada real con tu lista de bandas.
