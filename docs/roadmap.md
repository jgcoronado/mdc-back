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

## El roadmap vigente es el plan de acción del consejo de sabios

La hoja de ruta activa del proyecto es el plan de acción de
**[consejo-de-sabios-2026-07.md](consejo-de-sabios-2026-07.md)** (§7 "Plan de
acción" y §8 "Automatizaciones"), fruto de una evaluación integral (DAFO de
UX/UI/QA/desarrollo/producto + veredicto de síntesis) centrada en dos focos:
la experiencia de la marcha procesional y la optimización para SEO/robots IA.

Este documento no repite ese plan — lo enlaza y mantiene el **estado real de
ejecución**, tarea a tarea, porque el informe del consejo es una fotografía
del 2026-07-12 y no se reescribe con cada avance.

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

### Medio plazo (1–4 meses) — issues `consejo-sabios` + `medio-plazo`

| # | Tarea | Coste | Repercusión | Foco | Estado | Issue |
|---|-------|-------|-------------|------|--------|-------|
| M1 | API JSON de solo lectura + `llms.txt` + feed de novedades + página «Datos» | 10 h | Alta | 🔍 | ⏳ Pendiente — requiere antes decidir la licencia de datos (nota en el propio issue) | [#15](https://github.com/jgcoronado/mdc-back/issues/15) |
| M2 | Campaña de cobertura de audio (ingesta + curación) | 15 h+ | Alta | 🎺 | ⏳ Pendiente — trabajo mayoritariamente manual del admin, no solo código | [#16](https://github.com/jgcoronado/mdc-back/issues/16) |
| M3 | Búsqueda global unificada + autocompletado público | 10 h | Media-alta | 🎺 | ⏳ Pendiente | [#17](https://github.com/jgcoronado/mdc-back/issues/17) |
| M4 | `og:image` dinámica por entidad | 8 h | Media | 🔍 | ⏳ Pendiente | [#18](https://github.com/jgcoronado/mdc-back/issues/18) |
| M5 | Deploy FTP automatizado desde CI en `main` verde | 5 h | Media-alta | ⚙️ | ⏳ Pendiente — sin dependencias, menor riesgo del lote | [#19](https://github.com/jgcoronado/mdc-back/issues/19) |
| M6 | Accesibilidad + hoja de impresión de fichas | 6 h | Media | 🎺 | ⏳ Pendiente | [#20](https://github.com/jgcoronado/mdc-back/issues/20) |
| M7 | Notificaciones editoriales (email + digest semanal) | 6 h | Media | ⚙️ | ⏳ Pendiente | [#21](https://github.com/jgcoronado/mdc-back/issues/21) |
| M8 | Unificar slugify + test canónica↔JSON-LD + CSP/HSTS | 4 h | Media | 🔍⚙️ | ✅ Completado — de paso corrigió un bug real (URL de banda en JSON-LD nunca coincidía con la canónica) | [#22](https://github.com/jgcoronado/mdc-back/issues/22) |
| M9 | Estadísticas ampliadas como contenido indexable | 6 h | Media | 🔍 | ⏳ Pendiente | [#23](https://github.com/jgcoronado/mdc-back/issues/23) |

Detalle completo de cada tarea en `consejo-de-sabios-2026-07.md` §7 y en el
cuerpo de cada issue. Regla de secuencia del consejo: "nada del largo plazo
empieza sin el tablero de KPIs activo" — L1-L6 siguen sin issues por eso.

### Largo plazo (4–12 meses) — no iniciado

L1–L6 en `consejo-de-sabios-2026-07.md` §7: dumps abiertos versionados (L1),
hubs enriquecidos por advocación/hermandad (L2), biografías de compositores
vía editores (L3), formulario público de propuesta de grabación (L4), PWA
básica offline (L5), revisión del hosting si el tráfico lo justifica (L6).

---

## Cómo mantener este roadmap

- Cuando se cierre una tarea C/M/L: marcarla aquí como ✅ con el enlace al
  issue, **no** reescribir el informe del consejo (ese es un documento de
  evaluación puntual, no un tracker).
- Al empezar el medio o el largo plazo: crear los issues correspondientes con
  las labels `consejo-sabios` + `medio-plazo`/`largo-plazo`, y añadir sus
  filas a este documento igual que las de corto plazo.
- Si surge una decisión arquitectónica nueva → `architecture.md` (ADRs), no aquí.
- Si se descubre deuda técnica nueva → `technical-debt.md`, no aquí.
