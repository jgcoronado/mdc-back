# Nómina de hermandades y pasos + recuperación del histórico (2026-09-07)

Estado del trabajo de acompañamientos musicales para poder retomarlo en otra
sesión. Complementa a [roadmap.md](roadmap.md) §2 (N-03) y a
[technical-debt.md](technical-debt.md).

## Alcance acordado

- **Solo CCTT y AM.** Bandas de cornetas y tambores (CCTT, BCT, CyT) y
  agrupaciones musicales (AM). **Nunca** bandas de música, capillas musicales,
  escolanías ni silencio.
- **Solo pasos de Cristo.** Los palios se descartan — el sitio es «marchas de
  Cristo» y los palios ya se borraron de `contrato` el 2026-08-29 (backup
  `mdc-20260829-123724-pre-borrado-paso-virgen.db`).
- **Localidades:** Sevilla, Málaga, Córdoba y Jerez de la Frontera.
- **Día y orden = foto de la Semana Santa de 2026**, no serie histórica por año.
- **Las hermandades sin CCTT/AM se listan igualmente**, con «no tiene
  acompañamiento de CCTT/AM hasta la fecha».
- **La cruz de guía se lista pero no es un paso**: va siempre la primera.
- **Los pasos se nombran por su titular o su nombre popular**, no con la
  etiqueta genérica de la fuente («Paso de Misterio»).
- **Jerez no tiene Viernes de Dolores.**
- Las vísperas (Viernes de Dolores, Sábado de Pasión) no hacen carrera oficial:
  se ordenan por hora de salida.

## Hecho

### 1. Esquema — `012_hermandad_paso.sql`

Tres tablas, **creadas y vacías**:

- `hermandad` — LOCALIDAD, NOMBRE, SLUG, DIA, DIA_ORDEN, ORDEN, HORA_SALIDA,
  FUENTE. `SLUG` es la juntura con `contrato.HERMANDAD_SLUG`; como
  `Slug::slugify()` normaliza a NFD y quita diacríticos, corregir las tildes de
  NOMBRE no rompe el enlace con los contratos ya cargados.
- `paso` — NOMBRE (el del titular), ORDEN (0 = cruz de guía, luego 1..n),
  ES_CRUZ_GUIA.
- `contrato_paso` — satélite `ID_CONTRATO → ID_PASO`. Tabla aparte en vez de
  columna nueva por el mismo motivo que `contrato_localidad` (009): SQLite no
  tiene `ADD COLUMN IF NOT EXISTS` y `migrate_ingest.php` reejecuta todos los
  `.sql` a ciegas en cada despliegue.

«Sin acompañamiento CCTT/AM» se deduce de que la hermandad no tenga contratos;
no hay columna que mantener sincronizada.

### 2. `corregir_hermandades.php`

- 13 hermandades sin tildes (253 filas): la carga del foro venía de texto plano.
- Fusión `El Carmen` → `El Carmen Doloroso` (16 filas). Eran la misma hermandad
  (Miércoles Santo) partida en dos porque el foro la llama «EL CARMEN» y
  musicofrades «El Carmen Doloroso»; el histórico se quedaba colgando en
  1993-2019 y 2026 aparecía como hermandad nueva. Aquí sí cambia el slug.

### 3. `recuperar_historico_acompanamientos.php`

**Contratos 1.431 → 2.507.**

El resolutor de 2026-08-29 (`scripts/tmp_acompanamientos/resolve_acompanamientos.py`)
casaba el nombre de banda contra `banda` por conjuntos de tokens **sin mirar la
localidad**; ante dos bandas homónimas en sitios distintos se rendía y tiraba la
fila. Se perdió el 40% de lo que estaba en ámbito.

El nuevo desambigua en este orden: **localidad explícita → estilo AM/CCTT →
juvenil → Sevilla por defecto**. Lo que no encaja no se inventa: sale por
pantalla y se queda fuera.

Además **corrigió 234 filas ya cargadas que apuntaban a la banda equivocada**:

| banda_raw | iba a | debía ir a | filas |
|---|---|---|---|
| `CCTT Tres Caídas` | BCT Tres Caídas de **Arcos de la Frontera** | BCT Tres Caídas de **Triana** | 222 |
| `AM Sta. María de la Esperanza` | BCT La Esperanza de **Málaga** | AM Fraternitas (Sevilla) | 9 |
| `AM Sagrada Lanzada` | AM Lanzada de **Granada** | AM Sagrada Lanzada (Sevilla) | 4 |

Tras la pasada, todas las bandas de fuera de Sevilla son plausibles (Arahal, Dos
Hermanas, Cádiz, Utrera, Huelva, Linares, Jerez, Mairena, La Algaba,
Castilleja); desaparecieron León, Palma, Palencia y Granada.

Se revisaron **a mano las 63 resoluciones distintas** antes de escribir nada.

El script es idempotente: la clave de deduplicación es el *hecho*
(hermandad, paso, año, texto de la fuente), **no** el id de banda. Indexar por
banda fue un error intermedio que apiló la fila buena encima de la mala en vez
de corregirla.

## Pendiente

Poblar `hermandad` y `paso` con la nómina 2026 de las cuatro ciudades, y enlazar
`contrato_paso`. Es la mitad más grande del trabajo.

### Dudas abiertas antes de seguir

1. **Córdoba no tiene fuente consolidada** de acompañamientos 2026. Habría que
   ir hermandad por hermandad, con menos fiabilidad. ¿Se hace igual marcando la
   confianza, o se deja fuera?
2. **El orden de carrera oficial no está en HTML** en ninguna de las cuatro
   ciudades (ver «Fuentes» abajo). Si un día no se puede confirmar con dos
   fuentes, ¿se deja el orden en blanco y anotado, o se para?
3. **Forma del nombre de los pasos:** ¿corta y popular («La Sentencia», «El
   Cachorro») o completa («Nuestro Padre Jesús de la Sentencia»)? Son ~250
   pasos, conviene decidirlo antes de empezar.
4. **Cómo llega a producción.** `contrato` está vacía en prod y el `mdc.db` de
   allí tiene escrituras propias, así que no vale subir el local encima (ver
   `reference_db_deploy`). Tiene que ir como migración + scripts ejecutados *in
   situ*, y los dos scripts nuevos leen `scripts/tmp_acompanamientos/`, que está
   fuera del `php/` que se despliega.

## Fuentes y sus trampas

- **musicofrades.com** — `/acompanamientos-musicales-de-la-semana-santa-de-{ciudad}-{año}/`.
  Sevilla, Málaga y Jerez de 2026 completos, con el estilo de cada formación
  paso a paso. **No hay página de Córdoba.**
- ⚠️ **El orden en que Musicofrades lista las hermandades NO es el de carrera
  oficial.** Comprobado en el Domingo de Ramos de Sevilla 2026: Musicofrades
  pone La Cena la 5.ª, el orden oficial la 2.ª, y Amargura y Estrella van
  cambiadas. El orden oficial es: Borriquita, Cena, Jesús Despojado, Hiniesta,
  Paz, San Roque, Estrella, Amargura, Amor.
- **hermandades-de-sevilla.org** (Consejo) — la nómina y los horarios solo están
  dentro de PDFs enlazados, no en el HTML.
- **semana-santa.org** — inservible: el dominio es hoy spam de casinos.
- **sevillaactualidad.com** — devuelve 403.
- La prensa solo publica los **cambios** del año, nunca la lista completa.
- Los resúmenes de búsqueda web mienten con soltura sobre el orden: uno devolvió
  el mismo orden para Miércoles y Jueves Santo de Sevilla, que es imposible.
  **Contrastar siempre con una segunda fuente.**

## Deuda que deja este trabajo

- **La Macarena y La Redención siguen solo con 2026**, y no se puede arreglar
  desde estos CSV: la fuente extraída solo trae su paso de palio. El hueco está
  en la extracción del foro, no en la resolución — habría que reparsear el hilo.
- **28 nombres de banda sin resolver** (~125 filas). Varios son bandas que no
  existen en `banda` y habría que dar de alta: «AM San Esteban» (18 filas),
  «AM Santo Domingo El Sabio», «AM La O», «AM El Cachorro»,
  «CCTT Policía Municipal», «CCTT Cruz Roja».
- **Las 6 Cruces de Guía de 2026** de Baratillo, Hiniesta, Gitanos, San José
  Obrero, San Roque y Santa Genoveva apuntan a `AM Angustias` **adulta** (id
  157) cuando musicofrades dice la **juvenil**, que no existe en `banda`.
- **`banda` id 92** se llama `AM Angustias` en NOMBRE_BREVE siendo una *Banda de
  Cornetas y Tambores*. El prefijo del breve no es fiable como estilo; usar
  NOMBRE_COMPLETO.
- **La taxonomía de `contrato.TITULAR` sigue rota** hasta que se pueble `paso`:
  15 variantes para lo mismo, y una fila cuyo TITULAR arrastra un comentario del
  foro de origen. En `/acompanamientos/sevilla` se ve en La Hiniesta, con la
  misma serie partida entre «Paso de Cristo» y «Paso de Misterio».
- **`ci_smoke.php` sigue probando `/temporada`**, que esta rama sustituyó por
  `/acompanamientos`: 4 FAIL que no son regresiones.

## Cómo reejecutar

```bash
cd php
DB_PATH=data/mdc.db php app/tools/migrate_ingest.php
DB_PATH=data/mdc.db php app/tools/corregir_hermandades.php
DB_PATH=data/mdc.db php app/tools/recuperar_historico_acompanamientos.php --dry-run
DB_PATH=data/mdc.db php app/tools/recuperar_historico_acompanamientos.php
```

Los tres son idempotentes y hacen copia de seguridad (`VACUUM INTO`) en
`php/data/backups/` antes de tocar nada.

Verificación usada: PHPStan limpio en los ficheros nuevos, y `ci_smoke.php`
contra la fixture de CI da 77 OK / 6 FAIL — los 6 ajenos (2 por GD/FreeType no
cargado en local, 4 por `/temporada`).
