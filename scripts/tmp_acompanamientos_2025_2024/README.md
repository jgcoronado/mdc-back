# Andamiaje de la carga de acompañamientos 2025 y 2024

Ficheros de trabajo de [docs/acompanamientos-2025-2024.md](../../docs/acompanamientos-2025-2024.md).
Nada de esto se ejecuta en producción; sirve para poder rehacer o auditar los
`contratos_ss_<localidad>_<año>.csv` de la raíz.

## Flujo

```
fuente (PDF o HTML)  ->  parseador  ->  raw_<localidad>_<año>.csv  ->  construir_contratos.py  ->  contratos_ss_*.csv
```

`construir_contratos.py` es el único paso que hay que reejecutar si se cambia
una regla de alcance o un nombre de hermandad; los `raw_*.csv` ya están en el
repositorio y no dependen de tener los PDF delante:

```bash
python3 scripts/tmp_acompanamientos_2025_2024/construir_contratos.py
```

## Piezas

| Fichero | Qué hace |
|---|---|
| `normalizar.py` | Regla de alcance (CCTT/AM sí, todo lo demás no), expansión de abreviaturas de nombre de banda y `clave_normalizada`. |
| `prosa.py` | Trocea una línea de música en prosa (`X (cruz), Y (misterio) y Z (palio)`) en pares (papel del paso, banda). |
| `sevilla_bandas.py` | Diccionario a mano de los nombres cortos de *El Llamador* → nombre completo. Es imprescindible: la fuente no dice el estilo de la banda. |
| `construir_contratos.py` | Mapas de hermandad y de titular por localidad + escritura de los 14 CSV. |
| `sevilla.py` | Parseador de *El Llamador* (Sevilla), fichas con `Primer/Segundo/Tercer paso`. |
| `jerez.py` | Parseador de *Estación de Penitencia* (Jerez), bloques `TITULAR / Capataz / Música:`. |
| `csur.py` | Parseador genérico de los programas de Canal Sur que emparejan `Música:` con el rótulo en versales de la página (Cádiz, Córdoba, y Granada como respaldo). |
| `huelva24.py` | *El Llamador de Huelva*: el rótulo de la hermandad cae entre los dos pasos de su propia ficha, así que gana el rótulo más cercano. |
| `huelva25.py` | *Cruz de Guía* (Huelva 2025): ficha por página + página de horarios con los rótulos, emparejados por los titulares. |
| `html_srcs.py` | ahoragranada (Granada), 101tv (Málaga 2025) y malagamusical (Málaga 2024). |
| `raw_*.csv` | Extracción en bruto, sin filtrar por alcance. Es la que hay que mirar para auditar una fila. |
| `cordoba2025_prosa.txt` | Los párrafos originales de gentedepaz de los que sale `raw_cordoba_2025.csv`. |

## Reejecutar un parseador

Los parseadores leen `txt/<localidad>_<año>.txt`, la extracción de texto del PDF
(`pypdf`), que **no está en el repositorio** por tamaño. Para regenerarla:

```bash
# bajar el PDF (ver docs/acompanamientos-2025-2024.md para las URL) y
python3 -c "
from pypdf import PdfReader
r = PdfReader('sevilla_2025.pdf')
open('txt/sevilla_2025.txt','w',encoding='utf-8').write(
    ''.join(f'\n===== PAG {i+1} =====\n' + (p.extract_text() or '')
            for i, p in enumerate(r.pages)))
"
python3 sevilla.py 2025
```

Las fuentes HTML (`gr2024.html`, `gr2025.html`, `ml2025.html`) se bajan con
`curl` y se pasan a `html_srcs.py`.

## Trampas encontradas

- **`archivos_offline` de canalsur.es rota**: las URL directas de los PDF de 2024
  y 2025 ya dan 404. Las copias vivas están en cofradiastv.com (Google Drive).
- **Google Drive** exige el parámetro de confirmación para ficheros grandes:
  `https://drive.usercontent.google.com/download?id=<ID>&export=download&confirm=t`.
- **pypdf y las páginas a tres columnas**: en Sevilla, Sábado Santo y Jueves
  Santo salen con las fichas entrelazadas. Las correcciones están listadas
  explícitamente en `SEV_QUITAR` / `SEV_ANADIR`, no escondidas en un heurístico.
- **Abreviaturas con punto** (`A.M.`, `Ntro.`, `Stmo.`) rompen cualquier corte
  por final de frase: `normalizar.expande()` exige cuatro letras minúsculas
  antes del punto para considerarlo fin de frase.
