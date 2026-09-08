# Acompañamientos 2026 — Granada, Cádiz y Huelva

**Estado: pendiente de los datos. El pipeline está listo, el listado no.**

## Qué falta y por qué

La sesión en la que se preparó esta carga corrió en un entorno con la salida a
internet restringida por política de egress: la búsqueda web devuelve títulos y
URLs, pero **la descarga del contenido de esas páginas está bloqueada**
(`403` en el CONNECT del proxy, `EGRESS_BLOCKED` en el fetch). Con solo los
resúmenes del buscador no se puede transcribir un listado de ~30 hermandades por
ciudad con su banda y su paso sin inventar filas, así que **no se ha generado
ningún CSV de datos**: un acompañamiento mal atribuido es peor que uno ausente.

Lo que sí queda hecho y probado es todo lo demás (esquema con vigencia por
rango, los dos editores del panel y `seed_acompanamientos.php`), de modo que
en cuanto haya listado la carga es un comando.

## Fuentes localizadas para Granada 2026

- «Así sonará la Semana Santa de Granada 2026: las 29 bandas que acompañarán a
  cada paso» — Ahora Granada.
  <https://www.ahoragranada.com/noticias/asi-sonara-la-semana-santa-de-granada-2026-las-29-bandas-que-acompanaran-a-cada-paso/>
  Es el listado canónico: 32 hermandades de la capital, 29 bandas más cuatro
  acompañamientos de capilla musical, de Domingo de Ramos (29 de marzo de 2026)
  a Domingo de Resurrección (5 de abril).
- «Las Maravillas y El Rosario de Granada renuevan sus bandas para la Semana
  Santa 2026» — CofradiasTV.
  <https://cofradiastv.com/las-maravillas-y-el-rosario-de-granada-renuevan-sus-bandas-para-la-semana-santa-2026/>
  Útil como contraste sobre los cambios respecto a 2025.

Para **Cádiz** y **Huelva** no se llegó a identificar un equivalente publicado:
hay que buscarlo (consejos de hermandades, prensa local cofrade) antes de
cargar nada.

## Cómo terminarlo

1. Conseguir el texto de los listados (pegarlo en un fichero, o permitir esos
   dominios en la política de egress de la sesión).
2. Volcarlo a un CSV por ciudad con las columnas de
   [`acompanamientos_PLANTILLA.csv`](acompanamientos_PLANTILLA.csv), una fila
   **por paso** (Cruz de Guía / Misterio / Palio…), no por hermandad.
   `ANIO = 2026`; `ANIO_FIN` vacío salvo que la fuente diga que el contrato
   termina, y con los años del contrato cuando los publique.
3. Dry-run y revisión de pendientes:
   ```bash
   php php/app/tools/seed_acompanamientos.php docs/data/acompanamientos_granada_2026.csv
   ```
4. Las bandas del CSV de pendientes: darlas de alta en `/dashboard/banda/add`
   (o corregir la grafía del CSV) y relanzar. El script es idempotente.
5. `--commit`, y repaso final en
   `/dashboard/acompanamientos/localidad?loc=Granada`.
