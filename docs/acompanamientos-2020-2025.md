# Acompañamientos musicales 2022-2025 y las temporadas sin salida de 2020-2021

Extiende hacia atrás la carga de 2026
([acompanamientos-2026-huelva-cadiz-granada.md](acompanamientos-2026-huelva-cadiz-granada.md),
[acompanamientos-nomina-2026.md](acompanamientos-nomina-2026.md)) hasta 2020, con
el **mismo alcance, el mismo formato de CSV y los mismos convenios de nombres**.

Son dos cosas distintas:

1. **2022-2025**, cuatro temporadas con datos: 28 ficheros
   `contratos_ss_<localidad>_<año>.csv` en la raíz.
2. **2020 y 2021**, dos temporadas **sin salida procesional** en las siete
   localidades. No hay contratos que cargar; lo que se carga es el motivo, en
   una tabla nueva (`temporada_sin_salida`), para que `/acompanamientos` lo diga
   en vez de dejar un hueco mudo entre 2022 y 2019.

**Nada de esto está todavía en la BD**: son ficheros de entrada, y el paso de
resolución de `ID_BANDA` hay que darlo en local contra `php/data/mdc.db`.

## Qué hay

| Localidad | 2022 | 2023 | 2024 | 2025 | 2026 (ya cargado) |
|---|---:|---:|---:|---:|---:|
| Sevilla   | 79 | 79 | 86 | 79 | 92 |
| Málaga    | 54 | 49 | 46 | 49 | 54 |
| Jerez     | 33 | 35 | 39 | 34 | 37 |
| Córdoba   | 30 | 32 | 34 | 29 | 28 |
| Granada   | 26 | 26 | 27 | 27 | 28 |
| Cádiz     | 18 | 23 | 21 | 23 | 24 |
| Huelva    | 20 | 21 | 21 | 21 | 24 |
| **Total** | **260** | **265** | **274** | **262** | — |

Más `temporadas_sin_salida.csv`: 14 filas (7 localidades × 2020 y 2021).

Las cifras cuadran año a año con lo que ya está cargado de 2026, que es la
comprobación más útil que hay: si un año se saliera del orden de magnitud del
siguiente, sería síntoma de que la fuente está incompleta o el filtro mal puesto.
Los años flojos y por qué están en «Avisos» al final.

> Las cifras de 2024 y 2025 no son idénticas a las del primer volcado
> (2026-09-10): al reprocesar 2022 y 2023 se arreglaron cosas que afectaban a
> los cuatro años (páginas mal leídas en Sevilla y Jerez, coletillas dentro del
> nombre de banda, destacamentos de banda contados como banda). Los CSV de 2024
> y 2025 se han regenerado con el resto.

## 2020 y 2021: temporadas sin salida

Ninguna hermandad de ninguna de las siete localidades hizo estación de
penitencia en 2020 ni en 2021. El sitio **no rellena huecos** a propósito
(`Repo::agruparAcompanamientos()`), así que sin más información esos dos años
serían un salto inexplicado en la serie de cada paso. Ahora se dicen:

- **Migración `014_temporada_sin_salida.sql`**: tabla `(LOCALIDAD, ANIO, MOTIVO,
  FUENTE)`, texto libre igual que `contrato_localidad` (009).
- **`temporadas_sin_salida.csv`** en la raíz, con las 14 filas y el motivo tal y
  como se enseña: *No hubo salida procesional (pandemia de COVID-19)*.
- **`php/app/tools/cargar_temporadas_sin_salida.php`**, idempotente
  (`INSERT OR IGNORE` por la clave primaria), con `--dry-run`.
- `Repo::temporadasSinSalida()` + `Repo::agruparAcompanamientos()` insertan la
  anotación **sólo si el hueco entero está explicado**: si a un paso le faltan
  2020, 2021 y 2023, el hueco sigue mudo, porque de 2023 no sabemos nada. No se
  inventa continuidad.
- La plantilla lo enseña dos veces: un aviso de cabecera en la página de la
  localidad y una línea en gris entre los dos rangos afectados.

### Contratos de 2020/2021 que sí están cargados

La carga histórica de Sevilla (`contratos_ss_sevilla_historico.csv`, de
elforocofrade.es) trae **13 filas de 2020 y 14 de 2021**: contratos firmados que
nunca llegaron a sonar. Una fila en `contrato` significa «esta banda tocó tras
este paso ese año», así que sobran, y además tapan el aviso (el hueco deja de
estar entero). Se quitan con:

```powershell
# Windows/PowerShell — desde la raíz del proyecto
php php/app/tools/backup.php
php php/app/tools/purgar_contratos_sin_salida.php            # dry-run, lista lo que borraría
php php/app/tools/purgar_contratos_sin_salida.php --commit
```

Borra las filas de `contrato` cuya `(LOCALIDAD, ANIO)` esté en
`temporada_sin_salida`, más sus satélites `contrato_localidad` y
`contrato_paso`. Si sale mal, la serie de esa hermandad pierde un año que sí
debería estar; se vuelve atrás restaurando el backup previo.

`contratos_ss_sevilla_historico.csv` **se deja como está** (es la transcripción
fiel de la fuente), así que hay que reejecutar la purga después de cualquier
recarga del histórico.

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
- **Un destacamento no es la banda.** «Tambores roncos de la Banda X», «cuatro
  tambores de la Banda Y», «cornetín de órdenes»: fuera
  (`normalizar.DESTACAMENTO`). Antes entraban con ese nombre literal y creaban
  una banda fantasma por cada variante.
- Se conservan los pocos casos en que una CCTT/AM va tras un palio o una virgen
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
  `acompanamientos-nomina-2026.md`. En 2022 hay fichas cuyo titular no se puede
  emparejar (ver «Avisos»): esas van con `NOTA` vacía, no con un titular
  inventado.

`ID_BANDA` llega **vacío**, como en los CSV de Huelva/Cádiz/Granada de 2026.

## Fuentes

Musicofrades **sólo sirve para 2026**: el sitio no publicó guías de ningún año
anterior. Comprobado por tres vías —su API REST de WordPress, sondeo directo de
las URL con el patrón
`/acompanamientos-musicales-de-la-semana-santa-de-{ciudad}-{año}/` (todas 404) y
el CDX del Internet Archive, que sólo tiene archivadas las de 2026.

El sustituto principal son los **programas de mano de Canal Sur**, que edita uno
por capital y año con una ficha por hermandad y el campo `Música:` paso a paso.
Es una fuente mejor que musicofrades para este trabajo porque da el titular de
cada paso, no sólo la etiqueta genérica.

| Localidad | 2022 | 2023 | 2024 | 2025 |
|---|---|---|---|---|
| Sevilla | *El Llamador* | *El Llamador* | *El Llamador* | *El Llamador* |
| Huelva | *El Llamador de Huelva* | *El Llamador de Huelva* | *El Llamador de Huelva* | *Cruz de Guía* |
| Cádiz | andaluciainformacion.es | semanasantacadiz.com | *Semana Mayor* | semanasantacadiz.com |
| Jerez | *Estación de Penitencia* | *Estación de Penitencia* | *Estación de Penitencia* | *Estación de Penitencia* |
| Córdoba | *Paso a Paso* | *Paso a Paso* | *Paso a Paso* | gentedepaz.es |
| Granada | ahoragranada.com | ahoragranada.com | ahoragranada.com | ahoragranada.com |
| Málaga | malagamusical.blogspot.com | malagamusical.blogspot.com | malagamusical.blogspot.com | 101tv.es |

Los programas en cursiva son de Canal Sur.

Enlaces y avisos:

- **canalsur.es rota las URL**: `archivos_offline` sirve los PDF de la temporada
  en curso y los quita después. Todos los enlaces directos de 2022-2025 dan ya
  404. Las copias vivas son las que publica **cofradiastv.com** en Google Drive
  (`https://cofradiastv.com/descarga-programa-de-mano-el-llamador-semana-santa-de-sevilla-2025/`
  y sus hermanas por ciudad y año). Si hay que rehacer el trabajo, ir
  directamente a cofradiastv.
- Granada — <https://www.ahoragranada.com/noticias/asi-sonara-la-semana-santa-de-granada-2024-las-bandas-que-acompanaran-a-cada-hermandad/>
  y sus equivalentes de 2022, 2023 y 2025. Es la misma fuente y el mismo formato
  que se usó para Granada 2026, así que las cinco temporadas salen del mismo
  sitio.
- Málaga — <https://malagamusical.blogspot.com/2024/05/2024.html>
  («Acompañamientos Musicales y Militares de la Semana Santa de 2024») y las
  entradas equivalentes de 2022 y 2023. El blog tiene además 2010, 2016-2021 y
  sueltos de 1977, 1978, 1981, 1990, 1998 y 1999: es la mejor vía si algún día
  se quiere reconstruir el histórico completo de Málaga.
- Málaga 2025 — <https://www.101tv.es/malaga-semana-santa/cuales-son-los-acompanamientos-musicales-para-la-semana-santa-de-malaga-2025/>
- Cádiz 2022 — las siete guías por jornada de andaluciainformacion.es. *Semana
  Mayor* de Canal Sur no traía el campo `Música:` hasta 2024, así que para 2022
  no sirve.
- Cádiz 2023 y 2025 — <https://semanasantacadiz.com/semana-santa-de-cadiz-2025/>
  y su equivalente de 2023.
- Córdoba 2025 — <https://www.gentedepaz.es/todos-los-acompanamientos-musicales-de-las-hermandades-cordobesas-para-la-semana-santa-de-2025/>

## Cómo se ha construido

Todo el andamiaje está en `scripts/tmp_acompanamientos_2020_2025/`
(ver su `README.md`). En corto:

1. Un parseador por familia de fuente vuelca `raw_<localidad>_<año>.csv` con lo
   que dice el original, sin filtrar: hermandad, etiqueta o titular del paso y
   texto de la banda.
2. `construir_contratos.py` aplica el alcance, normaliza el nombre de la banda,
   traduce la hermandad al nombre que ya usa la BD y escribe los 28 CSV finales.

```powershell
# Windows/PowerShell — desde la raíz del proyecto
python scripts\tmp_acompanamientos_2020_2025\construir_contratos.py
```

Es idempotente y no toca la BD: sólo reescribe los `contratos_ss_*.csv`.

## Cómo cargarlo

El mismo procedimiento de 2026, localidad a localidad y año a año:

```powershell
# Windows/PowerShell — desde la raíz del proyecto
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2022.csv
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2022.csv --faltantes
# revisa NOMBRE_BREVE en bandas_a_crear_contratos_ss_huelva_2022.csv, y entonces:
php php/tools/seed_bandas_2026.php bandas_a_crear_contratos_ss_huelva_2022.csv --commit
php php/app/tools/resolver_contratos_banda.php contratos_ss_huelva_2022.csv --write
php php/app/tools/seed_contratos_2026.php contratos_ss_huelva_2022.csv.resuelto.csv
php php/app/tools/seed_contratos_2026.php contratos_ss_huelva_2022.csv.resuelto.csv --commit
```

**Haz una localidad entera antes de pasar a la siguiente**, y dentro de ella de
más antiguo a más reciente: los 28 ficheros comparten bandas y si no, el segundo
`--faltantes` vuelve a proponer altas que el primero ya hizo.

Córdoba y Jerez no pasan por `seed_contratos_2026.php`, porque su carga de 2026
fue por `cargar_contratos_cordoba.php` / `cargar_contratos_jerez.php` (enlazan
`contrato_paso`). Para 2022-2025 hay que decidir si se enlaza también el paso
—recomendado, ya que `paso` está poblado— o si se cargan sólo como `contrato` +
`contrato_localidad`.

Y, al final, las dos temporadas sin salida:

```powershell
# Windows/PowerShell — desde la raíz del proyecto
php php/app/tools/migrate_ingest.php          # aplica 014_temporada_sin_salida.sql
php php/app/tools/cargar_temporadas_sin_salida.php --dry-run
php php/app/tools/cargar_temporadas_sin_salida.php
php php/app/tools/purgar_contratos_sin_salida.php --commit
```

## Avisos y dudas conocidas

- **Los datos son de la propia Semana Santa de cada año.** Los programas de mano
  se cierran días antes, así que pueden faltar cambios de última hora.
- **Sevilla, forma corta de los nombres de banda.** *El Llamador* nombra a las
  bandas como se las conoce en la calle («Rosario de Cádiz», «Las Cigarreras»,
  «Tres Caídas») y **no dice el estilo**, así que la clasificación CCTT/AM no se
  puede deducir del texto. Está hecha a mano en
  `scripts/tmp_acompanamientos_2020_2025/sevilla_bandas.py`, con el nombre
  completo de cada banda contrastado contra `banda_dump.csv`. Ojo con el caso
  peliagudo: «Las Cigarreras» es a la vez BCT y banda de música, y sólo se
  distinguen por si el texto dice «Banda de música» o no.
- **Sevilla, páginas que pypdf lee desordenadas.** En las jornadas con tres o
  cuatro fichas por página el orden de lectura mezcla columnas. Las filas
  afectadas están corregidas a mano en `SEV_QUITAR` / `SEV_ANADIR` dentro de
  `construir_contratos.py`, contrastadas contra el texto de la propia página. La
  más enredada: los pasos de **La Trinidad** aparecen repartidos entre las
  páginas 77 y 78 y el parser se los atribuía a **La Soledad**.
- **Los PDF de 2022 y 2023 meten espacios dentro de las palabras** («LA HINIEST
  A», «P ASIÓN», «SANT A CENA»). Todas las comparaciones de rótulo van sin
  espacios ni acentos (`vocab.compacta()` / `vocab.localiza()`), no con el texto
  literal.
- **Jerez 2022 está incompleto y se sabe.** El programa de ese año sale de pypdf
  con las dos columnas entremezcladas: de 68 líneas de música, 23 no se pueden
  emparejar con su titular (en 2024 son 7). Esas van con `NOTA` vacía y se
  tratan como paso de Cristo, que es lo que son casi siempre —los palios de
  Jerez llevan banda de música y se caen solas por alcance—. Además faltan
  hermandades del Jueves y el Viernes Santo que no aparecen en el texto
  extraído: 28 hermandades en 2022 frente a 35 en 2024.
- **Cádiz 2022 son siete guías de prensa, no un programa.** Redacción en prosa
  («la banda de cornetas y tambores X acompaña al paso del misterio; y la
  Filarmónica Y, a la Virgen»), de donde salen sólo 18 filas. Tres frases sueltas
  se resuelven a mano en `CAD_BANDA_FIX`, con el estilo de la banda contrastado
  con las fichas de 2023-2026.
- **La Sed de Sevilla, 2022.** La ficha trae dos `Música:` sin etiqueta de paso.
  La primera es la cruz de guía, con la banda que la propia hermandad acababa de
  crear («la hermandad ha creado una escuela musical para conformar la Banda de
  cornetas y tambores del Cristo de la Sed», dice el propio programa); va a mano
  en `SEV_ANADIR['2022']`.
- **Lo que se queda fuera y no es un fallo**: la cruz de guía del Divino Perdón
  de Alcosa en 2023 («La Resurrección») no se ha podido identificar con ninguna
  banda conocida y se descarta antes que adivinar; el «Nazareno de Rota» de San
  Jerónimo 2023 va tras el palio, y este conjunto de datos no tiene ni una sola
  fila de palio.
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
  el entorno donde se ha hecho esto. Hay 344 formas distintas de nombre de banda
  en los 28 ficheros (323 claves normalizadas); muchas son la misma banda escrita
  de dos maneras y el resolutor las une por slug, pero habrá altas que dar.
