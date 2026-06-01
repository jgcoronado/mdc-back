# Documentación — marchasdecristo.com

Esta carpeta contiene la documentación viva del proyecto, escrita para que cualquier sesión nueva (humano o asistente) pueda entender la aplicación sin tener que leer el código entero.

## Índice

1. **[context.md](context.md)** — Punto de entrada. Visión general, stack, estructura de carpetas, convenciones, estado actual.
2. **[architecture.md](architecture.md)** — Diagrama de componentes, flujos de petición, ADRs (decisiones arquitectónicas registradas), patrones y antipatrones detectados.
3. **[technical-debt.md](technical-debt.md)** — Bugs activos, vulnerabilidades, código muerto y deuda priorizada.
4. **[redesign-options.md](redesign-options.md)** — Reflexión sobre alternativas de fondo: lenguaje, framework, BD, hosting, arquitectura. Con veredictos.
5. **[roadmap.md](roadmap.md)** — Plan secuenciado en 6 fases para resolver la deuda y materializar las decisiones tomadas.
6. **[db-analysis.md](db-analysis.md)** — Auditoría detallada del esquema MySQL (preexistente).

## Cómo mantener estos docs

- Si cambias arquitectura → actualiza `architecture.md` (sección de ADRs si es una decisión nueva).
- Si encuentras un bug → añádelo a `technical-debt.md`.
- Si completas una fase del roadmap → marca la fase como hecha en `roadmap.md`.
- El resto (context.md, redesign-options.md, db-analysis.md) cambia menos.

## Convenciones de los docs

- Idioma: español (consistente con el código y los commits).
- Citas a archivos: con formato `[ruta:linea](../ruta#Llinea)` para que sean clickables desde IDE.
- Severidades: 🔴 Crítica, 🟠 Alta, 🟡 Media, 🟢 Baja.
- Generación: cada doc lleva una línea `> Generado: YYYY-MM-DD` al inicio.
