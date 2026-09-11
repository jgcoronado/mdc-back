# Andamiaje de la carga de acompañamientos 2022-2025

Ficheros de trabajo de [docs/acompanamientos-2020-2025.md](../../docs/acompanamientos-2020-2025.md).
Nada de esto se ejecuta en producción; sirve para poder rehacer o auditar los
`contratos_ss_<localidad>_<año>.csv` de la raíz.

(2020 y 2021 no tienen parseador ni `raw_*.csv`: no hubo salida procesional. Eso
va por `temporadas_sin_salida.csv` y `cargar_temporadas_sin_salida.php`.)

## Flujo

```
fuente (PDF o HTML)  ->  parseador  ->  raw_<localidad>_<año>.csv  ->  construir_contratos.py  ->  contratos_ss_*.csv
```

`construir_contratos.py` es el único paso que hay que reejecutar si se cambia
una regla de alcance o un nombre de hermandad; los `raw_*.csv` ya están en el
repositorio y no dependen de tener los PDF delante:

```bash
python3 scripts/tmp_acompanamientos_2020_2025/construir_contratos.py
```

## Piezas

| Fichero | Qué hace |
|---|---|
| `normalizar.py` | Regla de alcance (CCTT/AM sí, todo lo demás no, y un destacamento de banda tampoco), expansión de abreviaturas de nombre de banda, poda de la prosa que el PDF encadena al nombre y `clave_normalizada`. |
| `prosa.py` | Trocea una línea de música en prosa en pares (papel del paso, banda). Entiende las dos construcciones: nombre delante del papel (`X (cruz), Y (misterio)`) y papel delante del nombre (`El misterio irá acompañado por X`). |
| `vocab.py` | Compara rótulos de hermandad ignorando espacios, acentos y puntuación. Imprescindible en 2022 y 2023: pypdf parte las palabras (`LA HINIEST A`, `P ASIÓN`). |
| `sevilla_bandas.py` | Diccionario a mano de los nombres cortos de *El Llamador* → nombre completo. Es imprescindible: la fuente no dice el estilo de la banda. |
| `construir_contratos.py` | Mapas de hermandad y de titular por localidad + escritura de los 28 CSV. |
| `sevilla.py` | Parseador de *El Llamador* (Sevilla), fichas con `Primer/Segundo/Tercer paso`. |
| `jerez.py` | Parseador de *Estación de Penitencia* (Jerez), bloques `TITULAR / Capataz / Música:`. |
| `csur.py` | Parseador genérico de los programas de Canal Sur que emparejan `Música:` con el rótulo en versales de la página (Cádiz, Córdoba, y Granada como respaldo). |
| `cadiz22.py` | Las siete guías por jornada de andaluciainformacion.es (Cádiz 2022), que es prosa corrida con un campo `Música:` por hermandad. |
| `huelva24.py` | *El Llamador de Huelva*: el rótulo de la hermandad cae entre los dos pasos de su propia ficha, así que gana el rótulo más cercano. |
| `huelva25.py` | *Cruz de Guía* (Huelva 2025): ficha por página + página de horarios con los rótulos, emparejados por los titulares. |
| `html_srcs.py` | ahoragranada (Granada), 101tv (Málaga 2025) y malagamusical (Málaga 2022-2024, en dos formatos distintos). |
| `raw_*.csv` | Extracción en bruto, sin filtrar por alcance. Es la que hay que mirar para auditar una fila. |
| `cordoba2025_prosa.txt` | Los párrafos originales de gentedepaz de los que sale `raw_cordoba_2025.csv`. |
| `malaga2020.txt` | El listado de malagamusical para 2020: los acompañamientos **contratados** para una Semana Santa que no se celebró. Se guarda para dejar constancia de por qué no se carga, no para cargarlo. |

## Reejecutar un parseador

Los parseadores leen `txt/<localidad>_<año>.txt`, la extracción de texto del PDF
(`pypdf`), que **no está en el repositorio** por tamaño. Para regenerarla:

```bash
# bajar el PDF (ver docs/acompanamientos-2020-2025.md para las URL) y
python3 -c "
from pypdf import PdfReader
r = PdfReader('sevilla_2022.pdf')
open('txt/sevilla_2022.txt','w',encoding='utf-8').write(
    ''.join(f'\n===== PAG {i+1} =====\n' + (p.extract_text() or '')
            for i, p in enumerate(r.pages)))
"
python3 sevilla.py 2022
```

Las fuentes HTML (`gr2022.html` … `gr2025.html`, `ml2025.html`) se bajan con
`curl` y se pasan a `html_srcs.py`; las de Cádiz 2022, a `cadiz22.py`.

## Trampas encontradas

- **`archivos_offline` de canalsur.es rota**: las URL directas de los PDF de
  2022-2025 ya dan 404. Las copias vivas están en cofradiastv.com (Google Drive).
- **Google Drive** exige el parámetro de confirmación para ficheros grandes:
  `https://drive.usercontent.google.com/download?id=<ID>&export=download&confirm=t`.
- **pypdf y las páginas a varias columnas**: en Sevilla, las jornadas con tres o
  cuatro fichas por página salen entrelazadas. Las correcciones están listadas
  explícitamente en `SEV_QUITAR` / `SEV_ANADIR`, no escondidas en un heurístico.
  Lo que sí es regla general: la segunda ficha de la página a veces empieza por
  `Nombre: Sede:` en vez de `Sede:`, y el recuadro de «información de servicio»
  del Viernes Santo mete rótulos de hermandad que no son fichas.
- **pypdf y las palabras partidas**: los programas de 2022 y 2023 sacan
  `LA HINIEST A`, `P ASIÓN`, `SANT A CENA`. Cualquier comparación de rótulo va
  por `vocab.compacta()`, nunca por el texto literal.
- **Jerez 2022 pega la cabecera del día al rótulo** (`20 11 DE ABRILLUNES SANTO
  LA CANDELARIA`). `jerez.quita_cabecera()` la quita antes de decidir si el
  bloque es una hermandad o una tabla de horarios; sin eso se perdían diez
  hermandades por «traer dígitos».
- **Jerez, `Herman` como palabra de corte** se comía la continuación de línea
  `(Dos \nHermanas, Sevilla)` y truncaba el nombre de la banda. Ahora el corte
  exige `Hermano Mayor` / `Hermanos:`.
- **Abreviaturas con punto** (`A.M.`, `Ntro.`, `Stmo.`) rompen cualquier corte
  por final de frase: `normalizar.expande()` primero las expande y sólo después
  corta por el punto.
