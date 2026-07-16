# Deuda técnica — marchasdecristo.com

> Última actualización: 2026-07-16 (C8 — reescrito con la deuda real del stack PHP; el análisis Next.js/VPS anterior es historia, ver `docs/archive/`)
> La auditoría de la BD vive en [db-analysis.md](db-analysis.md). El análisis del panel en [admin-panel.md](admin-panel.md). El plan priorizado de mejoras (no solo deuda) vive en [consejo-de-sabios-2026-07.md](consejo-de-sabios-2026-07.md) y su estado de ejecución en [roadmap.md](roadmap.md).

## Resumen ejecutivo

| Categoría | Items abiertos | Severidad máxima |
|-----------|-----------------|-------------------|
| Operativa / observabilidad | 2 | 🟠 Alta |
| Deploy | 1 | 🟡 Media |
| Calidad de código PHP | 2 | 🟡 Media |
| Base de datos (SQLite) | 1 | 🟢 Baja |
| Panel de administración | 1 | 🟢 Baja |

**Contexto**: desde el consejo de sabios (2026-07-12) se han cerrado 6 de las 8
tareas de corto plazo — incluyendo CI (C5) y el endurecimiento del sync (C7),
que resolvían la mayor parte de la deuda operativa crítica que tenía el
proyecto en ese momento. Lo que queda aquí es deuda real vigente, no un
resumen del informe del consejo (ver ese documento para el plan de mejora
completo, que incluye trabajo de producto y SEO además de deuda).

---

## 1. Operativa / observabilidad

### 1.1 Sin monitorización externa de uptime 🟠
- Nadie vigila `https://marchasdecristo.com/health` salvo el CI (que solo lo
  ejercita contra el fixture local, no contra producción). Una caída del
  hosting compartido en plena Cuaresma/Semana Santa pasaría desapercibida
  hasta que la reportaran los propios usuarios.
- **Fix**: dar de alta un monitor externo gratuito (p. ej. UptimeRobot) contra
  `/health` con alerta por email/Telegram. Es la tarea **C6** del plan del
  consejo ([issue #12](https://github.com/jgcoronado/mdc-back/issues/12)) —
  requiere una acción manual del mantenedor en un servicio de terceros, no
  solo cambios de código.

### 1.2 CI verifica, pero no despliega ni alerta si producción diverge 🟡
- El workflow de GitHub Actions (`ci.yml`) da confianza sobre el código antes
  de subirlo, pero el sync a producción (`sync_db_to_prod.php`) y el deploy de
  código siguen siendo **manuales por FTP**. Si CI está roja, nada impide
  ejecutar el sync igualmente — depende de que el mantenedor lo compruebe.
- **Fix** (medio plazo, tarea **M5** del consejo): deploy FTP automatizado
  desde CI (`lftp mirror` con manifiesto, solo en `main` verde), cerrando el
  ciclo que hoy abre C5.

---

## 2. Deploy

### 2.1 Sin verificación de integridad periódica del backup 🟡
- `app/tools/backup.php` genera el backup (`VACUUM INTO` + retención), pero no
  comprueba que el fichero resultante sea íntegro más allá de que la copia
  termine sin excepción.
- **Fix**: añadir `PRAGMA integrity_check` sobre el backup recién creado y
  avisar (log o email) si falla. Además, hoy el backup vive en el mismo host
  que el `.db` — una copia externa (rclone/GitHub Action hacia almacenamiento
  gratuito) mitigaría un fallo del hosting completo. Ambos puntos están en el
  catálogo de automatizaciones del consejo (§8, "Para el administrador"),
  todavía sin issue propio.

---

## 3. Calidad del código PHP

### 3.1 Autoload manual sin PSR-4 ni gestor de paquetes 🟢
- `bootstrap.php` mapea clase → fichero a mano en vez de un autoloader
  estándar. Es una decisión deliberada (ADR-001 en `architecture.md`, motivada
  por el hosting compartido sin Composer), no un descuido, pero significa que
  añadir una clase nueva requiere tocar el mapa de autoload además de crear el
  fichero — un paso manual que es fácil de olvidar.
- **Fix** (opcional, bajo impacto): autoload por convención de directorio
  (`spl_autoload_register` con `str_replace('App\\', '', $class)` sobre
  `src/`) en vez de un mapa explícito. No requiere Composer.

### 3.2 Rate limiting de login persistido a fichero, sin purga automática 🟡
- `Auth` escribe los intentos de login a fichero (correcto para hosting
  compartido sin memoria persistente entre peticiones — ver ADR-007), pero no
  hay una purga periódica de entradas expiradas; el fichero solo crece.
- **Fix**: purgar entradas con `window`/`lock` ya expirados en cada escritura,
  o un cron ligero mensual. Impacto bajo mientras el volumen de intentos de
  login sea pequeño (proyecto de un solo mantenedor + editores conocidos).

---

## 4. Base de datos (SQLite)

### 4.1 Tablas heredadas sin revisar tras el cutover 🟢
- El esquema conserva columnas/tablas de la era MySQL (p. ej. sentinelas
  numéricos como `BANDA_ESTRENO = 0` en vez de `NULL`, documentados en
  [db-analysis.md](db-analysis.md)) que no se han limpiado porque no bloquean
  nada funcionalmente.
- **Fix**: revisar `db-analysis.md` tras el cutover y decidir qué se normaliza
  ahora que SQLite (y no MySQL) es el motor definitivo. Baja prioridad — no
  hay corrupción de datos, solo aspereza del esquema.

---

## 5. Panel de administración

### 5.1 Gestión de discos ausente 🟢
- Hay alta/edición de marcha, autor y banda (con linaje), pero no de disco —
  las relaciones `disco_marcha` solo se pueden tocar indirectamente. Ver
  detalle en [admin-panel.md](admin-panel.md).
- **Fix**: `/dashboard/disco/add` y `/dashboard/disco/{id}` con gestión de la
  lista de pistas, siguiendo el mismo patrón que `banda_form.php`.

---

## Ítems verificados como ya resueltos (no confundir con deuda abierta)

Para que una sesión nueva no reabra trabajo ya hecho:

- **Botonera de streaming en fichas públicas**: `Html::streaming()` está
  invocado en los tres templates de detalle (`marcha_detail.php`,
  `banda_detail.php`, `disco_detail.php`) — verificado en el código actual
  (2026-07-16), no solo en un issue cerrado.
- **Checksum + rollback + modo mantenimiento en el sync**: implementado en
  `scripts/sync_db_to_prod.php` (C7, [issue #13](https://github.com/jgcoronado/mdc-back/issues/13)).
- **CI con smoke tests**: `.github/workflows/ci.yml` + `php/tools/ci_fixture.php`
  + `php/tools/ci_smoke.php` (33 aserciones) en cada push/PR (C5,
  [issue #11](https://github.com/jgcoronado/mdc-back/issues/11)).
- **Hubs SEO, `og:image`/Twitter Card, `lastmod`+IndexNow, marcha del día**:
  C1–C4, todos cerrados — ver [roadmap.md](roadmap.md) para el estado
  completo de las tareas de corto plazo del consejo.

## Cómo mantener este documento

- Un hallazgo nuevo → añadirlo aquí con severidad (🔴🟠🟡🟢) y un fix propuesto.
- Al resolver un ítem → táchalo con `~~texto~~` y una nota de fecha/commit, o
  muévelo a la sección de verificados si conviene documentar explícitamente
  que ya no hay que buscarlo. No lo borres sin más: el historial de qué se
  resolvió y cuándo es parte del valor de este documento.
- Deuda que en realidad es una mejora de producto/SEO (no un bug ni un
  riesgo) va en `consejo-de-sabios-2026-07.md`/`roadmap.md`, no aquí.
