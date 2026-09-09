# Acompañamientos musicales 2026 — Jaén y Almería

Cierra la vuelta a las ocho capitales andaluzas: Sevilla, Córdoba y Jerez ya están
en la BD; Huelva, Cádiz, Granada y Málaga quedaron en CSV en
[acompanamientos-2026-huelva-cadiz-granada.md](acompanamientos-2026-huelva-cadiz-granada.md).
Aquí van las dos que faltaban. **Nada de esto está todavía en la BD**: son ficheros
de entrada, y el paso de resolución de `ID_BANDA` hay que darlo en local contra
`php/data/mdc.db`.

## Ficheros

| Fichero | Filas | Bandas distintas | Hermandades | Fuente |
|---|---|---|---|---|
| `contratos_ss_jaen_2026.csv`    | 44 | 31 | 21 de 22 | Guía oficial de la Agrupación de Cofradías (PDF) |
| `contratos_ss_almeria_2026.csv` | 39 | 26 | 20 de 22 | lavozdealmeria.com |
| `bandas_ss2026_jaen_almeria.csv` | 56 bandas | — | — | derivado de los dos anteriores |

(31 + 26 = 57, pero la BCT Nuestra Señora del Carmen de Almería sale en los dos.
Las hermandades que faltan para llegar a 22 son las que van sin música ninguna:
Silencio en Jaén, Gran Poder y Prendimiento en Almería.)

Fuentes exactas:

- Jaén — «Guía de Actos, Cultos e Itinerarios Semana Santa Jaén 2026», Agrupación
  de Cofradías y Hermandades Ciudad de Jaén, publicada por el Ayuntamiento:
  <https://www.aytojaen.es/portal/RecursosWeb/DOCUMENTOS/1/0_35179_1.pdf>
  (última modificación 2026-02-16). 164 páginas; cada cofradía trae un bloque
  `ACOMPAÑAMIENTO MUSICAL` paso a paso. Texto extraído con `pypdf`.
- Almería — La Voz de Almería, sección «El Contador Cofrade», los ocho artículos
  de itinerarios y horarios (uno por jornada), enlazados desde
  <https://www.lavozdealmeria.com/vivir/el-contador-cofrade/501098/consulta-aqui-todos-horarios-recorridos-semana-santa-almeria-2026.html>
  (actualizados entre el 26-03 y el 02-04 de 2026). Cada hermandad lleva líneas
  `Acompañamiento Musical de <titular>: <banda>`.

**Musicofrades no cubre ninguna de las dos.** Su sitemap de 2026 solo tiene
Sevilla, Huelva, Cádiz, Jerez de la Frontera y Málaga; las URL equivalentes de
Jaén y Almería dan 404. Por eso `FUENTE` vuelve a no ser uniforme, igual que
pasó con Granada.

Descartadas por el camino: la web de la Agrupación de Hermandades y Cofradías de
Almería (`cofradiasdealmeria.es`) tiene ficha de las 21 hermandades de penitencia
pero **no publica el acompañamiento musical**, y sus «Guías de Semana Santa» y
«Programas e Itinerarios» solo llegan hasta 1996 y 2019 respectivamente.
`semanasantaenespana.com` sale bien posicionada en las búsquedas pero su ficha de
hermandad no trae bandas.

## Formato

Mismas columnas que el resto de CSV de 2026:

```
ID_BANDA,BANDA,CLAVE_BANDA,HERMANDAD,TITULAR,ANIO,FUENTE,NOTA,LOCALIDAD
```

- `ID_BANDA` llega **vacío**: las fuentes solo dan el nombre de la banda y este
  entorno no tiene la BD.
- `HERMANDAD` y `TITULAR` van **sin acentos**, como Sevilla, Huelva, Cádiz y
  Granada. (Málaga sí los lleva; se apartó del criterio documentado. Da igual para
  la juntura, porque `Slug::slugify()` quita diacríticos, pero el nombre visible
  queda mezclado.)
- `LOCALIDAD` es la de la **Semana Santa** (`Jaen` / `Almeria`), no la de la banda.
- `NOTA` se usa aquí más que en las localidades anteriores: **guarda el nombre del
  titular** que da la fuente cuando la etiqueta de paso no lo dice. Es el dato que
  hará falta para poblar `paso` sin volver a las fuentes (en Sevilla/Málaga/Jerez
  hubo que buscarlo aparte porque musicofrades no lo publica).

### Etiquetas de paso

Jaén las trae en la propia guía (`Misterio`, `Cristo`, `Palio`) y se han
respetado. **Almería no las trae**: La Voz nombra el titular y nada más, así que
la clasificación Cristo / Misterio / Virgen es lectura nuestra sobre el titular,
no dato de la fuente. Donde el paso es una escena (Borriquita, Santa Cena, Huerto,
Humildad y Paciencia, Sentencia) va `Paso de Misterio`; donde es la imagen sola
del Señor, `Paso de Cristo`.

## Alcance aplicado

El mismo del resto del proyecto: **solo CCTT y AM**, y **solo pasos de Cristo**
(Cristo, Misterio y Cruz de Guía). Se descarta cualquier paso llevado por banda de
música, capilla musical, coral o coro, sea de Cristo o de palio.

En la práctica el filtro es el prefijo del nombre: sobreviven `Agrupación
Musical …` y `Banda de Cornetas y Tambores …`; se caen `Asociación Musical`,
`Asociación Cultural Musical`, `Asociación Músico-Cultural`, `Banda de Música`,
`Banda Municipal de Música`, `Banda Sinfónica`, `Capilla Musical`, `Coral` y
`Coro`. Es el mismo corte que quedó aplicado en Granada y Málaga.

Con eso, de las 83 filas de los dos CSV quedan **38 en ámbito** (22 de Jaén, 16 de
Almería), con **25 bandas distintas**. Los CSV se entregan **completos**, sin
filtrar: el recorte se hace al generar el `.resuelto.csv`, igual que en
Huelva/Cádiz/Granada/Málaga.

### Hermandades que se quedan sin ninguna fila

No es un hueco de la fuente, es su Semana Santa:

- **Jaén** — Silencio (la guía dice literalmente «En riguroso silencio»); Jesús
  (el Nazareno va con la Banda Sinfónica «Ciudad de Jaén» *y* la Banda Municipal
  de Música de Jaén, las dos de música); Soledad (Coral Villa de Mengíbar y Coro
  Ciudad de Granada); y el paso del Santo Sepulcro, «En silencio», dentro de una
  hermandad que sí aporta el Misterio del Calvario.
- **Almería** — Gran Poder (su ficha es la única de la jornada sin bloque de
  acompañamiento: el titular de la sección es «Recorrido, horario **y estrenos**»,
  sin «acompañamiento musical»); Prendimiento (lo mismo el Miércoles Santo);
  Perdón (banda de tambores y timbales de la propia hermandad); Cristo de la
  Escucha (viacrucis de madrugada, sin música); Silencio, Santo Entierro, Caridad
  y Soledad (capillas musicales, banda municipal y cuarteto vocal).

### Dos filas marcadas como duda de alcance

Pasos que llevan CCTT/AM pero **no tienen imagen de Cristo**; van en el CSV con la
duda escrita en `NOTA` para que no entren sin decidirlo:

- `Almeria` / Encuentro / «Santa Mujer Verónica» → Agrupación Musical Bentomiz de
  Arenas. Es la única de las dos que pasaría el filtro automático.
- `Jaen` / Jesús / «La Verónica» → Asociación Musical-Cultural «Maestro Miguel»,
  que ya se cae por ser Asociación Musical.

(De paso: `Almeria` / Soledad / «San Juan Evangelista» tiene el mismo problema
conceptual, pero lo lleva una capilla musical y se cae igualmente.)

## Bandas: qué hay que dar de alta y qué NO

`bandas_ss2026_jaen_almeria.csv` inventaría las **56** bandas distintas de los dos
CSV en el formato de `php/tools/seed_bandas_2026.php`
(`NOMBRE_COMPLETO,NOMBRE_BREVE,LOCALIDAD,PROVINCIA,clave_normalizada`), estén o no
en ámbito. `NOMBRE_BREVE` es propuesta derivada: **revísalo antes de dar de alta.**

⚠️ **Aviso importante, y es la diferencia real respecto a las tandas anteriores.**
El resolutor casa por slug **exacto** contra `NOMBRE_COMPLETO`/`NOMBRE_BREVE`, y
en `banda` los nombres **no llevan la localidad**, mientras que la convención de
estos CSV sí la lleva («… de Córdoba»). Resultado: una banda que ya existe sale
como *sin match*, y `--faltantes` propone darla de alta otra vez. Eso ya pasó en
la tanda anterior y dejó duplicados en `banda`: **307** duplica a 143 (Santa Cruz
de Almería), **321** a 269, **322** a 58, **323** a 67, **327** a 152, **331** a 37
y **342** a 59.

De las 25 bandas en ámbito de Jaén y Almería:

- **8 ya se cargaron con la tanda anterior** y resuelven solas: 307, 321, 322,
  323, 327, 331, 341, 342.
- **10 ya están en `banda` con el nombre sin localidad.** El resolutor las dará
  por *sin match*: **asigna el ID a mano en el CSV y no las des de alta.**
- **7 son altas reales** (6 si la duda de la Verónica se resuelve descartando).

| Nombre en el CSV | Ya está en `banda` como | Nota |
|---|---|---|
| `Agrupación Musical Santísimo Cristo de Gracia de Córdoba` | **40** — Agrupación Musical Santísimo Cristo De Gracia (Córdoba) | nombre único |
| `Agrupación Musical Santísimo Cristo de la Salud de Alcalá la Real` | **49** — Agrupación Musical Santísimo Cristo De La Salud (Alcalá la Real) | nombre único |
| `Banda de Cornetas y Tambores Santísimo Cristo del Mar de Vélez-Málaga` | **46** — Banda De Cornetas Y Tambores Santísimo Cristo Del Mar (Vélez-Málaga) | nombre único |
| `Banda de Cornetas y Tambores María Santísima de las Penas de Úbeda` | **93** — BCT María Santísima De Las Penas (Úbeda) | nombre único |
| `Banda de Cornetas y Tambores Nuestra Señora de la Asunción de Jódar` | **112** — BCT Nuestra Señora De La Asunción (Jódar) | nombre único |
| `Banda de Cornetas y Tambores María Santísima del Amor de Úbeda` | **135** — BCT María Santísima Del Amor (Úbeda) | nombre único |
| `Banda de Cornetas y Tambores Santísimo Cristo de la Columna – El Amarrado – de Ávila` | **139** — BCT Santísimo Cristo de la Columna "El Amarrado" (Ávila) | nombre único |
| `Banda de Cornetas y Tambores Nuestra Señora de la Salud de Córdoba` | **129** — BCT Nuestra Señora De La Salud (Córdoba) | ojo: 2 homónimas (56 Utrera) |
| `Banda de Cornetas y Tambores Nuestra Señora del Rosario de Linares` | **131** — BCT Nuestra Señora Del Rosario (Linares) | ojo: 4 homónimas (24 Cádiz, 107 Arriate, 145 Brenes) |
| `Banda de Cornetas y Tambores Santísimo Cristo de la Expiración de Jaén` | **119** — BCT Santísimo Cristo De La Expiración (Jaén) | ojo: 4 homónimas (86 Morón, 114 Huéscar, 229 Sabiote) |

(Las 8 que resuelven solas, por si hace falta comprobarlas: 307 BCT Santa Cruz de
Almería, 321 BCT Victoria de Granada, 322 AM Virgen de la Cabeza de Exfiliana,
323 AM del Dulce Nombre de Jesús de Granada, 327 BCT Fe y Consuelo de Martos,
331 AM Jesús Despojado de Jaén, 341 AM Nuestra Señora del Mar de Huércal,
342 BCT Monte Calvario de Martos.)

Las **7 altas reales**:

| Nombre | Localidad | Provincia |
|---|---|---|
| `Banda de Cornetas y Tambores Nuestra Señora del Carmen de Almería` | Almería | Almería |
| `Banda de Cornetas y Tambores Sentencia de Cuevas del Almanzora` | Cuevas del Almanzora | Almería |
| `Agrupación Musical Nuestro Padre Jesús de la Piedad en su Presentación al Pueblo – La Estrella – de Jaén` | Jaén | Jaén |
| `Agrupación Musical Villa de Mancha Real` | Mancha Real | Jaén |
| `Agrupación Musical El Carpio` | Carpio (El) | Córdoba |
| `Banda de Cornetas y Tambores Maestro Valero de Aguilar de la Frontera` | Aguilar de la Frontera | Córdoba |
| `Agrupación Musical Bentomiz de Arenas` (solo si la duda de la Verónica se resuelve a favor) | Arenas | Málaga |

La BCT «Maestro Valero» ya salió como pendiente de alta en Córdoba
(`acompanamientos-nomina-2026.md`, «4 filas quedaron sin banda candidata»): darla
de alta aquí cierra también aquella.

`LOCALIDAD`/`PROVINCIA` salen de casar el final del nombre contra
`php/app/geo/municipios_es.php`, con la forma invertida que usa esa semilla
(`El Carpio` → `Carpio (El)`, `Los Villares` → `Villares (Los)`). Tres no son
municipio y se dejan con el nombre de la pedanía, como ya se hizo con Exfiliana:
**Pitres** (La Taha, Granada), **Villargordo** (Villatorres, Jaén) y la propia
**Exfiliana** (Valle del Zalabí, Granada). Cuatro formaciones se quedan sin
localidad porque no la tienen o no consta: la Banda de Guerra de la Legión, el
Cuarteto Vocal ANACRUSA, la Capilla Musical de Viento BAM y la Banda de Música de
Huécija-Alicún, que es de dos municipios y va anotada en Huécija.

## Normalizaciones aplicadas

| Como lo publica la fuente | Nombre unificado | Por qué |
|---|---|---|
| `Agrupación Musical Nuestro Padre Jesús Despojado (Jaén)` | `Agrupación Musical Jesús Despojado de Jaén` | es la forma con la que ya se cargó en Granada (id 331) |
| `Agrupación Musical María Santísima de la Cabeza de Exfiliana` | `Agrupación Musical Virgen de la Cabeza de Exfiliana` | misma unificación que se hizo en Granada (id 322) |
| `Agrupación Musical Dulce Nombre de Jesús de Granada` | `Agrupación Musical del Dulce Nombre de Jesús de Granada` | ídem (id 323) |
| `Banda de Cornetas y Tambores Santísimo Cristo a la columna “El Amarrado” (Ávila)` | `… Santísimo Cristo de la Columna – El Amarrado – de Ávila` | «de la Columna» es como está en `banda` (139); los guiones son la convención de apodos de estos CSV |
| `Banda de Música Huécija-Alicún` / `Banda de Música de Huécija-Alicún` | `Banda de Música de Huécija-Alicún` | La Voz la escribe de las dos formas |

La Banda de Música de Torredonjimeno vuelve a salir, ahora en Almería (palio de la
Macarena). En el CSV de Granada aparecía marcada `(Má)` por errata de la fuente,
ya anotada allí: es de Jaén.

## Procedimiento para cargarlo (local, luego sync a prod)

```powershell
# Windows/PowerShell — desde la raíz del proyecto
php php/app/tools/resolver_contratos_banda.php contratos_ss_jaen_2026.csv
php php/app/tools/resolver_contratos_banda.php contratos_ss_almeria_2026.csv
```

Solo informa. Contrasta la lista de *sin match* con la tabla de arriba **antes**
de pasar a `--faltantes`: lo que salga ahí y esté en la tabla de «ya está en
`banda`» no se da de alta, se le pone el ID a mano en la columna `ID_BANDA` del
CSV (el resolutor respeta las filas que ya traen ID y las copia tal cual).

```powershell
# Windows/PowerShell
php php/app/tools/resolver_contratos_banda.php contratos_ss_jaen_2026.csv --faltantes --inventario=bandas_ss2026_jaen_almeria.csv
# revisa NOMBRE_BREVE en bandas_a_crear_contratos_ss_jaen_2026.csv, quita lo que ya exista, y entonces:
php php/tools/seed_bandas_2026.php bandas_a_crear_contratos_ss_jaen_2026.csv            # dry-run
php php/tools/seed_bandas_2026.php bandas_a_crear_contratos_ss_jaen_2026.csv --commit
```

Ojo con el `--inventario=`: por defecto el resolutor busca
`bandas_ss2026_hue_cad_gra.csv`, que no tiene estas bandas.

Y con las bandas ya dadas de alta, se vuelve al resolutor con `--write`, se recorta
el `.resuelto.csv` al alcance (solo CCTT/AM y solo Cristo/Misterio/Cruz de Guía) y
se carga:

```powershell
# Windows/PowerShell
php php/app/tools/resolver_contratos_banda.php contratos_ss_jaen_2026.csv --write
php php/app/tools/seed_contratos_2026.php contratos_ss_jaen_2026.csv.resuelto.csv            # dry-run
php php/app/tools/seed_contratos_2026.php contratos_ss_jaen_2026.csv.resuelto.csv --commit
```

Igual para Almería. Haz una localidad entera antes de pasar a la otra: comparten
la BCT Nuestra Señora del Carmen de Almería (el Misterio del Calvario del Santo
Sepulcro de Jaén lo lleva esa banda almeriense), y si no, el segundo `--faltantes`
volvería a proponer un alta que el primero ya hizo. La subida a producción va por
`scripts/sync_db_to_prod.php`, como el resto.

## Pendiente / avisos

- Sigue en pie lo de `docs/technical-debt.md` §2.2: `Repo::temporada()` agrupa por
  `banda.LOCALIDAD`, no por `contrato_localidad`. Con Jaén y Almería cargadas
  aparecerán, por ejemplo, las bandas de Úbeda y Linares bajo su propio pueblo
  aunque toquen en Jaén capital.
- **Ninguna de las dos tiene nómina** (`hermandad`/`paso`/`contrato_paso`): igual
  que Sevilla, Málaga, Huelva, Cádiz y Granada, solo van a `contrato`. La guía de
  Jaén sí trae día, orden e **imágenes titulares** ficha a ficha, así que es la
  mejor fuente que hay para poblarla cuando toque; la de Almería trae día, hora de
  salida y hora de entrada en Carrera Oficial, con lo que el orden se puede
  calcular igual que se hizo con Córdoba.
- Los `NOTA` con nombre de titular de estos dos CSV son justo el material que
  faltaba para poblar `paso` sin volver a las fuentes.
- Los datos son de febrero (Jaén) y marzo-abril (Almería) de 2026 y la Semana
  Santa ya pasó: puede haber cambios de última hora que las fuentes no recogieran.
