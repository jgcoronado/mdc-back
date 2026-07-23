# Hoja de ruta — marchasdecristo.com

> Generado: 2026-06-01 · Reescrito: 2026-07-16 (C8)
>
> Las fases 0–6 originales (limpieza de seguridad, migración MySQL→Docker,
> migración Next.js/Express→Route Handlers, integridad de BD, tests/CI/CD sobre
> Next.js, mejoras opcionales) están **completadas y superadas**: el cutover del
> 2026-07-04 sustituyó ese stack entero por PHP 8.4 + SQLite (ver
> [context.md](context.md) y [architecture.md](architecture.md)). El detalle de
> esas fases se conserva solo como referencia histórica en el historial de git
> de este fichero (`git log -p -- docs/roadmap.md`); no se reproduce aquí para
> no confundirlo con el plan vigente.

## Marco vigente (consolidado 2026-07-23)

El proyecto tenía **dos planes solapados** redactados casi a la vez:

- **Plan de palancas 2026-27** (2026-07-09): P-01…P-09 + transversales T-01…T-03
  + 11 pantallas nuevas **N-01…N-11**, con calendario estacional hacia Semana
  Santa 2027. Dossier: artefacto `1a31cc69`.
- **Consejo de sabios** (2026-07-12): DAFO integral + plan C1–C8 / M1–M9 / L1–L6.
  Dossier: [consejo-de-sabios-2026-07.md](consejo-de-sabios-2026-07.md).

Solapan ~70%. **Decisión (2026-07-23): el marco forward es el plan de palancas
(pantallas N-*)**, porque es más granular y tiene el ritmo estacional correcto;
la lista M-x del consejo está mayormente **absorbida o completada**. Los dos
únicos ítems del consejo que las palancas no cubrían —M6 (accesibilidad +
impresión) y M7 (notificaciones editoriales)— se **pliegan** aquí como tareas
de calidad. Este documento es el **tracker único**: enlaza ambos dossieres y
mantiene el estado real verificado, no reescribe los informes.

El detalle histórico del avance C1–C8 / M1–M9 (todos cerrados o absorbidos) se
conserva en las tablas de más abajo y en `git log -p -- docs/roadmap.md`.

### Corto plazo (0–1 mes) — issues `consejo-sabios` + `corto-plazo`

| # | Tarea | Estado | Issue |
|---|-------|--------|-------|
| C1 | Hubs indexables por año / estilo / provincia | ✅ Completado | [#7](https://github.com/jgcoronado/mdc-back/issues/7) |
| C2 | `lastmod` en sitemap + ping IndexNow/Google tras el sync | ✅ Completado | [#8](https://github.com/jgcoronado/mdc-back/issues/8) |
| C3 | «Marcha del día» + bloque de descubrimiento en la home | ✅ Completado | [#9](https://github.com/jgcoronado/mdc-back/issues/9) |
| C4 | `og:image` de marca + Twitter Card | ✅ Completado | [#10](https://github.com/jgcoronado/mdc-back/issues/10) |
| C5 | CI con smoke tests (GitHub Actions) | ✅ Completado | [#11](https://github.com/jgcoronado/mdc-back/issues/11) |
| C6 | Monitorización externa de uptime con alerta | ✅ Completado — monitor UptimeRobot activo sobre `/health`, ver [monitoring.md](monitoring.md) | [#12](https://github.com/jgcoronado/mdc-back/issues/12) |
| C7 | Endurecer `sync_db_to_prod.php`: checksum, chequeo de propuestas, modo mantenimiento | ✅ Completado | [#13](https://github.com/jgcoronado/mdc-back/issues/13) |
| C8 | Actualizar documentación (`context.md`/`architecture.md`/`roadmap.md`/`technical-debt.md`) al stack PHP real | ✅ Completado (este documento es parte del entregable) | [#14](https://github.com/jgcoronado/mdc-back/issues/14) |

**8 de 8 tareas de corto plazo completadas.** El plan de acción de corto
plazo del consejo de sabios está cerrado.

### Medio plazo del consejo (M1–M9) — fotografía histórica cerrada

> Tabla congelada: 5 de 9 completadas. Los 4 pendientes se reencauzan en el
> **Plan forward activo** más abajo — M2 al carril manual de audio, M6/M7 como
> tareas de calidad, M9 dentro de las pantallas N-07/N-08/N-09. No se marca ni
> se añade nada más en esta tabla.

| # | Tarea | Coste | Repercusión | Foco | Estado | Issue |
|---|-------|-------|-------------|------|--------|-------|
| M1 | API JSON de solo lectura + `llms.txt` + feed de novedades + página «Datos» | 10 h | Alta | 🔍 | ✅ Completado — licencia CC BY 4.0; de paso corrigió otro caso de URL de banda no canónica (308) en la ficha de disco | [#15](https://github.com/jgcoronado/mdc-back/issues/15) |
| M2 | Campaña de cobertura de audio (ingesta + curación) | 15 h+ | Alta | 🎺 | ⏳ Pendiente — trabajo mayoritariamente manual del admin, no solo código | [#16](https://github.com/jgcoronado/mdc-back/issues/16) |
| M3 | Búsqueda global unificada + autocompletado público | 10 h | Media-alta | 🎺 | ✅ Completado — una caja global (cabecera) + `/buscar` + endpoint `/api/buscar` con desplegable accesible; FTS5 prefijo (marcha/autor) + LIKE (banda/disco) | [#17](https://github.com/jgcoronado/mdc-back/issues/17) |
| M4 | `og:image` dinámica por entidad | 8 h | Media | 🔍 | ✅ Completado — `/og/{tipo}/{id}.png` con GD/FreeType (IBM Plex, OFL), cacheada a disco, fallback a la imagen de marca si no hay FreeType | [#18](https://github.com/jgcoronado/mdc-back/issues/18) |
| M5 | Deploy FTP automatizado desde CI en `main` verde | 5 h | Media-alta | ⚙️ | ✅ Completado: push a `main` → CI (`verify`); deploy a PRO manual (Actions → *Run workflow*) con modo mantenimiento + smoke remoto. Validación previa en local. El entorno de preproducción se intentó (M5 ampliado) pero se **retiró el 2026-07-23** porque HelioHost no permite mover el document root del subdominio desde el panel | [#19](https://github.com/jgcoronado/mdc-back/issues/19) |
| M6 | Accesibilidad + hoja de impresión de fichas | 6 h | Media | 🎺 | ⏳ Pendiente | [#20](https://github.com/jgcoronado/mdc-back/issues/20) |
| M7 | Notificaciones editoriales (email + digest semanal) | 6 h | Media | ⚙️ | ⏳ Pendiente | [#21](https://github.com/jgcoronado/mdc-back/issues/21) |
| M8 | Unificar slugify + test canónica↔JSON-LD + CSP/HSTS | 4 h | Media | 🔍⚙️ | ✅ Completado — de paso corrigió un bug real (URL de banda en JSON-LD nunca coincidía con la canónica) | [#22](https://github.com/jgcoronado/mdc-back/issues/22) |
| M9 | Estadísticas ampliadas como contenido indexable | 6 h | Media | 🔍 | ⏳ Pendiente | [#23](https://github.com/jgcoronado/mdc-back/issues/23) |

Detalle completo de cada tarea en `consejo-de-sabios-2026-07.md` §7 y en el
cuerpo de cada issue. Regla de secuencia del consejo: "nada del largo plazo
empieza sin el tablero de KPIs activo" — L1-L6 siguen sin issues por eso.

**Nota**: la numeración M-x queda **cerrada** como fotografía histórica. El
tracker activo desde el 2026-07-23 es la sección siguiente (pantallas N-* del
plan de palancas + M6/M7 plegados).

## Plan forward activo — pantallas N-* + calidad (tracker vivo)

> Estado verificado contra `php/app/routes.php` el 2026-07-23. Detalle de cada
> N-* en el dossier de palancas (artefacto `1a31cc69`, §08). Todos los cambios
> de BD son **aditivos**, migrables in situ (patrón de `001`/`002`).

### Ya en producción (base sobre la que se construye)
- Hubs año/estilo/provincia (C1/P-05) · Dedicatorias **N-01/N-02** (índice +
  hub + panel de curación) · Búsqueda global **N-11** (`/buscar` + `/api/buscar`)
  · API+feeds+«Datos» (M1; el feed `/feed.xml` **es** el «novedades» de P-09) ·
  og:image dinámica (M4) · Vídeo YouTube en ficha (P-02, `App\Media`) ·
  GoatCounter opt-in (P-08) · Slugify unificado + CSP/HSTS (M8) · **N-07
  `/rankings`** (rankings de siempre + drill-down `/rankings/{año}`; ver detalle
  abajo).

### Cola de código (agosto–septiembre) — solo queries sobre datos existentes — ✅ CERRADA 2026-07-23
| # | Pantalla / tarea | Depende de | Estado |
|---|------------------|-----------|--------|
| N-07 | `/rankings` — parametrizar por año las queries `fetchMas*` existentes | — | ✅ Completado 2026-07-23 — `/estadisticas` renombrado con 301 permanente; `/rankings/{año}` con umbral `HUB_MIN_MARCHAS` (thin → noindex, como los demás hubs), índice por décadas, cross-link con `/marcha/ano/{año}` |
| N-09 | `/aniversarios/{año}` — 25/50/75/100 años, centenarios | — | ✅ Completado 2026-07-23 — tramos de 25 en 25 hasta 200 (centenarios destacados 🎉); `/aniversarios` redirige 302 al año en curso; `/aniversarios/{año}` fuera de [1900, actual+1] → 404 (evita espacio infinito de URLs); cross-link recíproco desde `/marcha/ano/{año}` cuando ese año cumple aniversario redondo hoy |
| N-08 | Anuario `/marchas/{año}` (ampliar el hub `/marcha/ano/{año}` actual) | — | ✅ Completado 2026-07-23 — sin ruta nueva: panel «Resumen del año» en el hub existente (compositor con más marchas, banda con más estrenos, marcha más grabada), reutilizando las queries de N-07; se omite en años thin (< `HUB_MIN_MARCHAS`) |
| N-10 | `/mapa` — coropleta SVG por provincia | ~~P-07 en prod~~ | ✅ Completado 2026-07-23 — mapa base SVG (52 provincias, ISO 3166-2:ES) adaptado de [jboekesteijn/provinces-of-spain](https://github.com/jboekesteijn/provinces-of-spain) (CC BY-SA 4.0, atribución en `assets/mapa-provincias.README.md`); `App\Mapa` colorea 5 niveles de intensidad (cortes no lineales: 1-9/10-49/50-149/150-399/400+, ajustados a lo concentrado del catálogo en Andalucía) y enlaza cada provincia con marchas a su hub; tabla accesible bajo el mapa con los mismos datos, sin depender del SVG. Verificado en navegador real, claro y oscuro |
| — | Ejecutar P-07 (`completar_provincia.php`) en **prod** | deploy hecho | ✅ Completado 2026-07-23 vía Plesk Scheduled Tasks ("Run a PHP script", requiere seleccionar PHP 8.4 explícitamente — el CLI por defecto del host es PHP 5.x y falla con `Unsupported declare 'strict_types'`). Resultado: 0 filas por actualizar (ya llegadas a prod en un sync anterior), 2 localidades sucias pendientes de curación manual («Hdad Cristo De Gracia», «El Sol») — no bloquean nada |
| — | Ejecutar `seed_dedicatorias.php` en **prod** | deploy hecho | ⏳ Pendiente in situ (mismo mecanismo que P-07, recordar seleccionar PHP 8.4) |

Cubren también **M9** (estadísticas ampliadas como contenido indexable).

**Las 4 pantallas de esta cola están completadas.** Siguiente bloque del plan de
palancas: entidades nuevas (N-03 hermandad → N-04/05 contratos → N-06 ingesta de
contratos, septiembre-noviembre, migraciones aditivas) — ver el dossier del
artefacto `1a31cc69`.

### Entidades nuevas (septiembre–noviembre) — migraciones aditivas
| # | Pantalla / tarea | Estado |
|---|------------------|--------|
| N-03 | Ficha de hermandad (Fase 2: hub ligero → entidad `hermandad` + `marcha_hermandad`) | ⏳ |
| N-04/05 | Contratos banda↔hermandad↔año (tabla `contrato`; `/temporada/{año}`) | ⏳ |
| N-06 | Ingesta semi-automática de anuncios de contrato (extender `tools/ingest`) | ⏳ |

### Calidad (plegado del consejo)
| # | Tarea | Depende de | Estado |
|---|-------|-----------|--------|
| M6 | Accesibilidad (foco, skip-link, `aria-sort`, contraste) + hoja de impresión | rediseño frontend | ⏳ |
| M7 | Notificaciones editoriales (email al aceptar/rechazar + digest semanal) | validar email/cron en HelioHost | ⏳ |
| T-03 | Vigilancia: cron backup (pendiente post-cutover), uptime (✅), link-checker mensual | — | Parcial |

### Carril manual en paralelo (lo conduce el admin, no es código)
- **P-01 / M2** — curación de candidatos de ingesta (meta <300 antes de octubre)
  y campaña de cobertura de audio; requiere la **lista de canales** de YouTube
  (rama `feat/ingest-youtube`) y curar los **264 candidatos de streaming**
  pendientes (rama `feature/music-apps`).
- **T-02** — pipeline de ingesta mensual semi-automático (piezas existen, falta
  orquestación).

### Largo plazo (4–12 meses) — no iniciado
L1–L6 del consejo: dumps abiertos versionados (L1), hubs enriquecidos por
advocación/hermandad con playlist (L2), biografías de compositores vía editores
(L3), formulario público «propón una grabación» (L4), PWA básica offline (L5),
revisión del hosting si el tráfico lo justifica (L6). Regla del consejo: nada
del largo plazo empieza sin el tablero de KPIs activo.

---

## Cómo mantener este roadmap

- El tracker vivo es la sección **«Plan forward activo»** (pantallas N-* + M6/M7).
  Al cerrar una N-*/tarea: marcarla ✅ aquí y actualizar el dossier de palancas
  (artefacto `1a31cc69`, misma URL) — **no** reescribir los informes de origen
  (consejo/palancas son evaluaciones puntuales, no trackers).
- Las tablas C1–C8 / M1–M9 quedan como **fotografía histórica cerrada**; no se
  añaden filas nuevas ahí.
- Si surge una decisión arquitectónica nueva → `architecture.md` (ADRs), no aquí.
- Si se descubre deuda técnica nueva → `technical-debt.md`, no aquí.
