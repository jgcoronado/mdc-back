# Acompañamientos musicales 2025 y 2024 — las siete localidades

Extiende hacia atrás la carga de 2026
([acompanamientos-2026-huelva-cadiz-granada.md](acompanamientos-2026-huelva-cadiz-granada.md),
[acompanamientos-nomina-2026.md](acompanamientos-nomina-2026.md)) a los dos años
anteriores, con el **mismo alcance, el mismo formato de CSV y los mismos
convenios de nombres**. **Nada de esto está todavía en la BD**: son ficheros de
entrada, y el paso de resolución de `ID_BANDA` hay que darlo en local contra
`php/data/mdc.db`.

## Qué hay

14 ficheros en la raíz del repositorio, `contratos_ss_<localidad>_<año>.csv`:

| Localidad | 2024 | 2025 | 2026 (ya cargado, en alcance) |
|---|---:|---:|---:|
| Sevilla   | 86 | 79 | 92 |
| Málaga    | 47 | 51 | 54 |
| Jerez     | 38 | 33 | 37 |
| Córdoba   | 32 | 29 | 28 |
| Granada   | 27 | 27 | 28 |
| Cádiz     | 21 | 23 | 24 |
| Huelva    | 21 | 21 | 24 |
| **Total** | **272** | **263** | — |

Las cifras cuadran año a año con lo que ya está cargado de 2026, que es la
comprobación más útil que hay: si un año se saliera del orden de magnitud del
siguiente, sería síntoma de que la fuente está incompleta o el filtro mal puesto.

## Alcance

El mismo que se fijó para 2026, sin excepciones:

- **Sólo CCTT y AM.** Bandas de cornetas y tambores (CCTT, BCT, CyT) y
  agrupaciones musicales. Se descarta cualquier paso acompañado por banda de
  música, banda municipal, sinfónica, filarmónica, asociación o agrupación
  músico-cultural, capilla, escolanía, coro, trío, quinteto o silencio, **sea
  Cristo, Misterio, Palio o Cruz de Guía**.
- Las formaciones institucionales cuentan si son CCTT: la BCT del Real Cuerpo de
  Bomberos de Málaga o la BCT del Tercio Gran Capitán entran, igual que en 2026;
  la Banda de Guerra de la BRIPAC o la Banda de la Legión no, porque no son CCTT.
- Se conservan los tres casos en que una CCTT/AM va tras un palio o una virgen
  (Despojado de Cádiz 2025, Columna y Servitas de Málaga): son lo que dice la
  fuente, no un fallo del filtro.

`TITULAR` respeta el convenio de cada localidad tal y como está ya en la BD, para
que la serie por paso no se parta:

- Sevilla, Huelva, Cádiz, Granada y Málaga → etiqueta genérica (`Cruz de Guia`,
  `Paso de Misterio`, `Paso de Cristo`, `Paso de Palio`, `Paso de Virgen`, y en
  Sevilla también `Paso del Nazareno` y `Paso Alegorico`, que ya existen en 2026).
- Córdoba → nombre corto del paso, el mismo de `cordoba_pasos_propuesta.csv`.
- Jerez → nombre de la hermandad, que es el marcador de posición que usó la carga
  de 2026. **El titular real sí se ha capturado** y va en `NOTA`
  (`titular real: Stmo. Cristo de la Coronación`): es justo el dato que faltaba
  para renombrar los pasos de Jerez a mano, apuntado como pendiente en
  `acompanamientos-nomina-2026.md`.

`ID_BANDA` llega **vacío**, como en los CSV de Huelva/Cádiz/Granada de 2026.

## Fuentes

Musicofrades **no sirve para estos dos años**: el sitio sólo publicó guías de
2026. Comprobado por tres vías —su API REST de WordPress, sondeo directo de las
14 URLs con el patrón `/acompanamientos-musicales-de-la-semana-santa-de-{ciudad}-{año}/`
(todas 404) y el CDX del Internet Archive, que sólo tiene archivadas las de 2026.

El sustituto principal son los **programas de mano de Canal Sur**, que edita uno
por capital y año con una ficha por hermandad y el campo `Música:` paso a paso.
Es una fuente mejor que musicofrades para este trabajo porque da el titular de
cada paso, no sólo la etiqueta genérica.

| Localidad | 2024 | 2025 |
|---|---|---|
| Sevilla | *El Llamador* (Canal Sur) | *El Llamador* (Canal Sur) |
| Huelva | *El Llamador de Huelva* (Canal Sur) | *Cruz de Guía* (programa de mano) |
| Cádiz | *Semana Mayor* (Canal Sur) | semanasantacadiz.com |
| Jerez | *Estación de Penitencia* (Canal Sur) | *Estación de Penitencia* (Canal Sur) |
| Córdoba | *Paso a Paso* (Canal Sur) | gentedepaz.es |
| Granada | ahoragranada.com | ahoragranada.com |
| Málaga | malagamusical.blogspot.com | 101tv.es |

Enlaces exactos:

- Canal Sur 2024, PDF por capital:
  `https://www.canalsur.es/resources/archivos_offline/2024/3/21/1711021707639Sevillaprograma2024.pdf`
  y equivalentes de Cádiz, Córdoba, Granada y Huelva (índice recogido en
  <https://www.extradigital.es/andalucia-canal-sur-programa-mano/>).
- Canal Sur 2025, PDF por capital:
  `https://www.canalsur.es/resources/archivos_offline/2025/4/9/1744190687716SEVILLA_2025.pdf`
  y equivalentes (índice en
  <https://www.extradigital.es/descarga-en-tu-movil-el-llamador-canal-sur-2025-de-cada-capital-andalucia/>).
  ⚠️ **Estas URLs ya dan 404**: `archivos_offline` rota. Los PDF que se han usado
  se han bajado de las copias que publica cofradiastv.com en Google Drive
  (`https://cofradiastv.com/descarga-programa-de-mano-el-llamador-semana-santa-de-sevilla-2025/`
  y sus hermanas). Si hay que rehacer el trabajo, ir directamente a cofradiastv.
- Granada — <https://www.ahoragranada.com/noticias/asi-sonara-la-semana-santa-de-granada-2024-las-bandas-que-acompanaran-a-cada-hermandad/>
  y `...-granada-2025-las-30-bandas-que-acompanaran-a-cada-hermandad/`. Es la
  misma fuente y el mismo formato que se usó para Granada 2026, así que las tres
  temporadas salen del mismo sitio.
- Málaga 2024 — <https://malagamusical.blogspot.com/2024/05/2024.html>
  («Acompañamientos Musicales y Militares de la Semana Santa de 2024»). El blog
  tiene además 2010, 2016-2023 y sueltos de 1977, 1978, 1981, 1990, 1998 y 1999:
  es la mejor vía si algún día se quiere reconstruir el histórico de Málaga.
- Málaga 2025 — <https://www.101tv.es/malaga-semana-santa/cuales-son-los-acompanamientos-musicales-para-la-semana-santa-de-malaga-2025/>
- Cádiz 2025 — <https://semanasantacadiz.com/semana-santa-de-cadiz-2025/>
- Córdoba 2025 — <https://www.gentedepaz.es/todos-los-acompanamientos-musicales-de-las-hermandades-cordobesas-para-la-semana-santa-de-2025/>

## Cómo se ha construido

Todo el andamiaje está en `scripts/tmp_acompanamientos_2025_2024/`
(ver su `README.md`). En corto:

1. Un parseador por familia de fuente vuelca `raw_<localidad>_<año>.csv` con lo
   que dice el original, sin filtrar: hermandad, etiqueta o titular del paso y
   texto de la banda.
2. `construir_contratos.py` aplica el alcance, normaliza el nombre de la banda,
   traduce la hermandad al nombre que ya usa la BD y escribe los 14 CSV finales.

```powershell
# Windows/PowerShell — desde la raíz del proyecto
python scripts\tmp_acompanamientos_2025_2024\construir_contratos.py
```

Es idempotente y no toca la BD: sólo reescribe los `contratos_ss_*.csv`.

## Cómo cargarlo

El mismo procedimiento de 2026, localidad a localidad y año a año:

```powershell
# Windows/PowerShell — desde la raíz del proyecto
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2025.csv
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2025.csv --faltantes
# revisa NOMBRE_BREVE en bandas_a_crear_contratos_ss_huelva_2025.csv, y entonces:
php php/tools/seed_bandas_2026.php bandas_a_crear_contratos_ss_huelva_2025.csv --commit
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2025.csv --write
php php/app/tools/seed_contratos_2026.php contratos_ss_huelva_2025.csv.resuelto.csv
php php/app/tools/seed_contratos_2026.php contratos_ss_huelva_2025.csv.resuelto.csv --commit
```

**Haz una localidad entera antes de pasar a la siguiente**, y dentro de ella
2024 antes que 2025: los 14 ficheros comparten bandas y si no el segundo
`--faltantes` vuelve a proponer altas que el primero ya hizo.

Córdoba y Jerez no pasan por `seed_contratos_2026.php`, porque su carga de 2026
fue por `cargar_contratos_cordoba.php` / `cargar_contratos_jerez.php` (enlazan
`contrato_paso`). Para 2025/2024 hay que decidir si se enlaza también el paso
—recomendado, ya que `paso` está poblado— o si se cargan sólo como `contrato` +
`contrato_localidad`.

## Avisos y dudas conocidas

- **Los datos son de la propia Semana Santa de cada año.** Los programas de mano
  se cierran días antes, así que pueden faltar cambios de última hora.
- **Sevilla, forma corta de los nombres de banda.** *El Llamador* nombra a las
  bandas como se las conoce en la calle («Rosario de Cádiz», «Las Cigarreras»,
  «Tres Caídas») y **no dice el estilo**, así que la clasificación CCTT/AM no se
  puede deducir del texto. Está hecha a mano en
  `scripts/tmp_acompanamientos_2025_2024/sevilla_bandas.py`, con el nombre
  completo de cada banda contrastado contra `banda_dump.csv`. Ojo con el caso
  peliagudo: «Las Cigarreras» es a la vez BCT y banda de música, y sólo se
  distinguen por si el texto dice «Banda de música» o no.
- **Sevilla, páginas que pypdf lee desordenadas.** En las jornadas con tres
  fichas por página (Sábado Santo, Jueves Santo) el orden de lectura mezcla
  columnas. Las filas afectadas están corregidas a mano en `SEV_QUITAR` /
  `SEV_ANADIR` dentro de `construir_contratos.py`, contrastadas contra el texto
  de la propia página. La más enredada: los pasos de **La Trinidad** aparecen
  repartidos entre las páginas 77 y 78 y el parser se los atribuía a **La
  Soledad**.
- **Jerez, titular real vs marcador de posición.** Ver arriba: `TITULAR` es el
  nombre de la hermandad y el titular real va en `NOTA`. Si se renombran los
  pasos de Jerez, hay que renombrarlos **también en las filas de 2026** o la
  serie se parte en dos.
- **Córdoba 2025 viene de prosa.** gentedepaz publica el listado en párrafos
  corridos, uno por jornada. La transcripción a filas está en
  `raw_cordoba_2025.csv` y el párrafo original, palabra por palabra, en
  `cordoba2025_prosa.txt` para poder cotejarla.
- **Huelva 2025**: el programa *Cruz de Guía* no pone el nombre de la hermandad
  en la ficha, sólo en la página de horarios. El emparejamiento se hace casando
  el rótulo del horario con los titulares de la ficha; en tres jornadas el
  rótulo une dos hermandades («La Salud La Lanzada») y se reparte a mano
  (`HUE_2025_PAG`).
- **La columna `LOCALIDAD`** sigue sin llegar a la BD como agrupación visible:
  `Repo::temporada()` agrupa por `banda.LOCALIDAD`. Es la misma deuda que dejó
  2026, descrita en `technical-debt.md` §2.2.
- **No se han generado inventarios de bandas a crear.** Ese paso lo da
  `resolver_contratos_banda.php --faltantes` contra la BD real, que no existe en
  el entorno donde se ha hecho esto. Hay 266 formas distintas de nombre de banda
  en los 14 ficheros; muchas son la misma banda escrita de dos maneras y el
  resolutor las une por slug, pero habrá altas que dar.
