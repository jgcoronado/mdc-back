# Consejo de sabios — evaluación integral del proyecto

> Fecha: 2026-07-11 · Base evaluada: rama `main` (commit `17565b2`)
> Método: cinco perspectivas expertas (UX, UI, QA, full-stack, PO), cada una con su
> DAFO, sus «zonas intocables» y sus propuestas de mejora (propias y hacia otros
> roles). Un juez sintetiza y optimiza las conclusiones con dos focos obligados:
> **centrar la experiencia en la marcha procesional** y **optimizar la web para
> SEO y robots IA**. Del veredicto sale un plan de acción corto/medio/largo con
> coste y repercusión, más el catálogo de automatizaciones.

---

## 0. Radiografía del proyecto (hechos que anclan el análisis)

- **Producto**: marchasdecristo.com — base de datos de música procesional española.
  4.212 marchas · 827 compositores · 268 bandas · 431 discos · hubs de dedicatorias.
  Audiencia de aficionados cofrades con pico extremo en Cuaresma/Semana Santa.
- **Stack (julio 2026)**: PHP 8.4 plano (sin composer, sin build) + PDO/SQLite FTS5 +
  plantillas PHP. Hosting compartido gratuito (HelioHost/Plesk), deploy por FTP.
  Migrado desde Next.js 15/VPS en junio-julio 2026 con validación de paridad 28/28.
- **SEO técnico ya presente**: URLs canónicas `slug-id` con 308, host canónico 301,
  sitemap de ~5.744 URLs, JSON-LD por entidad (MusicComposition, Person, MusicGroup,
  MusicAlbum, CollectionPage, VideoObject condicional, BreadcrumbList), robots.txt,
  `noindex` en búsquedas y hubs «thin», Cache-Control diferenciado, meta description
  y Open Graph básicos.
- **Ficha de marcha**: asiento bibliográfico, compositores con años de vida,
  grabaciones en orden cronológico con insignia «1.ª grabación», «Véase también»,
  navegación prev/next por registro, fachada YouTube sin cookies, botonera de
  streaming curada (Spotify/Apple/Deezer).
- **Operativa**: producción es de **solo lectura** (guard fail-safe `env=local`);
  el admin edita en su copia local y sube el `.db` por FTP (`sync_db_to_prod.php`
  con guardarraíl de backup reciente). Los **editores** proponen cambios en
  producción que se guardan como JSON (`PropuestaRepo`) y el admin los aplica en
  local. Ingesta de YouTube (yt-dlp) y matching de enlaces de streaming se ejecutan
  offline en el PC del mantenedor.
- **Sin** tests vigentes, sin CI/CD, sin monitorización externa, docs de
  arquitectura desactualizadas (describen el stack Next.js ya apagado).
- **Mantenedor único**: Javier Guerra.

---

## 1. Responsable UX

### DAFO

| | |
|---|---|
| **Fortalezas** | La ficha de marcha es excepcional para el nicho: asiento bibliográfico, contexto completo (autor, banda, año, dedicatoria), grabaciones cronológicas con «1.ª grabación» y «Véase también» que invita a seguir explorando. Escucha sin fricción (fachada YouTube que no carga nada hasta el clic). Buscador contextual por sección con atajo `/`. Facetas útiles en el catálogo (tipo, provincia, década). Modo oscuro automático. |
| **Debilidades** | La home no orienta: un párrafo de bienvenida y «últimas incorporaciones»; no hay ruta de descubrimiento (por año, estilo, advocación) ni pieza destacada. No existe búsqueda global: cada sección busca solo lo suyo. Si la marcha no tiene `AUDIO`, la sección «Escuchar» desaparece sin alternativa ni llamada a la acción. Jerga de archivo («M-330», «asiento», «registro 1.204 de 4.212») sin microcopy para el aficionado casual. Las dedicatorias —lo más singular del sitio— quedan como una entrada más del menú. El estilo (CCTT/AM) se cura en el admin pero no es navegable en público. |
| **Amenazas** | El visitante estacional llega desde Google o desde un chatbot en Cuaresma con una pregunta concreta; si la primera pantalla no le engancha en segundos, rebota. La expectativa de «reproductor tipo Spotify» choca con un catálogo textual si no se comunica bien el valor (rigor documental). |
| **Oportunidades** | «Marcha del día» y efemérides de compositores (contenido fresco automático). Recorridos guiados: por Semana Santa, por década, por advocación. Playlists por hermandad. PWA/offline básico para consultar en la calle durante la Semana Santa (cobertura móvil mala entre bullas). |

### Intocable (que nadie lo cambie sin pasar por UX)

- La **gramática de registro bibliográfico** de la ficha de marcha (asiento, filetes,
  descripción con términos de catálogo): es la seña de identidad y el diferenciador
  frente a webs genéricas.
- La **fachada de YouTube sin cookies**: privacidad + rendimiento; jamás sustituirla
  por un embed directo.
- El **buscador con atajo `/`** y el «Véase también»: patrones ya aprendidos por los
  usuarios recurrentes.

### A mejorar

**De su responsabilidad:**
1. Home de descubrimiento: marcha del día + accesos por estilo/año/advocación + explicación en una línea de qué es el sitio.
2. Estado «sin audio» con CTA («¿Conoces una grabación? Propónla») que alimente la cola editorial.
3. Microcopy/glosario: tooltip o línea explicando «M-330», «asiento», «1.ª grabación».
4. Búsqueda global unificada (una caja, resultados agrupados por entidad).

**Que pide a otros roles:**
- **A PO**: priorizar la cobertura de audio (la ingesta YouTube ya está construida; ejecutarla a fondo es la mejora de experiencia nº 1 posible por hora invertida).
- **Al dev**: autocompletado público y tolerancia a erratas en la búsqueda (FTS5 con prefijos ya lo permite).
- **A UI**: estados vacíos guiados y usabilidad de las facetas en móvil.

---

## 2. Responsable UI

### DAFO

| | |
|---|---|
| **Fortalezas** | Sistema de diseño «Índigo noche» con tokens CSS bien definidos (paleta clara/oscura, filetes, semánticos estreno/novedad/recuperación), 497 líneas de CSS sin build ni framework: barato de mantener y coherente. Tipografía serif de catálogo con identidad. Especificación documentada (`docs/revision-diseno-frontend.html`, prototipo de ficha). Favicon SVG. |
| **Debilidades** | **Sin `og:image` ni Twitter Card**: compartir una ficha en X o WhatsApp —donde vive la audiencia cofrade— sale sin imagen. Sin hoja de estilos de impresión, que el formato bibliográfico pide a gritos. Fragmentos de HTML construidos en strings PHP dentro de las plantillas (p. ej. el bloque «Véase también») dificultan mantener consistencia visual. Accesibilidad no auditada: foco visible, skip-link, `aria-sort` en tablas ordenables. |
| **Amenazas** | La estética «archivo» puede leerse como «web antigua» si se descuida el detalle (es una línea fina entre sobrio y desfasado). Drift del CSS al crecer sin metodología si entran más manos. |
| **Oportunidades** | Imagen social generada por entidad (título + compositor + año sobre la marca): cada ficha compartida se convierte en un cartel. Iconografía propia y consistente para servicios de streaming. Ficha imprimible = material que las bandas pueden usar. |

### Intocable

- Los **tokens CSS** y la paleta clara/oscura: cualquier color nuevo se deriva de ahí,
  no se inventa en línea.
- La decisión **CSS único sin build**: es una restricción del hosting y una virtud
  operativa; no introducir preprocesadores ni frameworks.
- La **gramática visual de catálogo** (filetes, asiento, serif): igual que UX, es identidad.

### A mejorar

**De su responsabilidad:**
1. `og:image` de marca (corto plazo) y por entidad (medio plazo) + `twitter:card`.
2. Hoja de impresión (`@media print`) para las fichas.
3. Pasada de accesibilidad: foco visible, skip-link, `aria-sort`/`scope` en tablas, contraste de `--faint`.
4. Estados vacíos y de error con la misma voz que el resto del catálogo.

**Que pide a otros roles:**
- **Al dev**: extraer parciales de plantilla para los bloques repetidos (botonera de streaming, «Véase también») en vez de HTML en strings.
- **A UX**: definir la jerarquía de la home antes de maquetarla (no rediseñar por rediseñar).

---

## 3. QA

### DAFO

| | |
|---|---|
| **Fortalezas** | La migración se validó con un harness de paridad estricta (28/28 casos, tipo y valor). Guard fail-safe de solo lectura en producción (`ReadOnlyModeException` → 503 con aviso). Seguridad de sesión seria: HMAC-SHA256 con `hash_equals`, CSRF por token derivado, PBKDF2 210k iteraciones con auto-upgrade desde MD5, rate-limit persistido a fichero. Audit log de acciones admin. Backups con `VACUUM INTO` + retención. Allowlists de campos y prepared statements en todas las escrituras. |
| **Debilidades** | **Cero tests vigentes**: el harness de paridad comparaba contra el Next.js ya apagado (es el espejo de un muerto; da falsa sensación de red de seguridad). Sin CI: ni siquiera `php -l` automático antes de subir por FTP. El flujo más crítico y frágil del sistema —propuestas en prod → sync a local → edición local → subida del `.db` a prod— no tiene tests, ni checklist ejecutable, ni verificación de que no se pisan propuestas nuevas. Sin monitorización externa ni alertas (el `/health` existe pero nadie lo mira). Validación de JSON-LD y del sitemap solo manual. |
| **Amenazas** | Una regresión silenciosa en canónicas o sitemap puede desindexar 5.744 URLs y no se notaría hasta Search Console, semanas después. Un sync mal ordenado puede pisar propuestas de editores o subir un `.db` corrupto. El pico de Semana Santa puede tumbar el hosting compartido sin previo aviso justo cuando más importa. |
| **Oportunidades** | Una suite de smoke tests HTTP es baratísima aquí: servidor PHP embebido + una BD fixture pequeña y aserciones de status/canónicas/JSON-LD. Presupuestos Lighthouse y validación schema.org automatizables en CI gratuito (GitHub Actions). |

### Intocable

- El **guard de solo lectura** (`Db::assertWritable`, fail-safe por defecto a
  `production`): nadie lo relaja «para ir más rápido».
- Las **allowlists de campos + prepared statements + CSRF**: patrón obligatorio para
  cualquier endpoint nuevo de escritura.
- El **guardarraíl del sync** (exigir backup reciente de prod antes de subir): se
  endurece, nunca se elimina.

### A mejorar

**De su responsabilidad:**
1. Suite mínima de smoke tests (rutas doradas de las 5 entidades: 200, redirección 308 de slug incorrecto, 404, JSON-LD parseable, sitemap muestral sin errores) ejecutada en CI.
2. Test del ciclo de propuestas (crear propuesta como editor → aceptar como admin → verificar aplicación) sobre BD fixture.
3. Retirar o archivar el harness de paridad con honestidad (documentar que ya no valida nada vivo).
4. Monitorización: uptime externo a `/health` + alerta.

**Que pide a otros roles:**
- **Al dev**: pipeline CI con lint + tests + deploy FTP automatizado (el deploy manual es la mayor fuente de riesgo humano).
- **A PO**: definir la «ventana de sync» como norma operativa (orden obligatorio: bajar propuestas → aplicar → backup prod → subir `.db`), y que el script la imponga.

---

## 4. Desarrollador full-stack

### DAFO

| | |
|---|---|
| **Fortalezas** | Stack radicalmente adecuado al contexto: PHP plano + SQLite para un catálogo read-mostly de 4k filas en hosting gratuito; cero dependencias, cero build, deploy = copiar ficheros. Router de 85 líneas, front controller limpio, `app/` y `data/` fuera del webroot. FTS5 con `remove_diacritics`. Canónicas 308 + host canónico 301 bien resueltos. Caché HTTP diferenciada por tipo de página. Código legible y comentado con intención. |
| **Debilidades** | `docs/context.md` y `architecture.md` describen el stack Next.js/VPS **que ya no existe**: confunden a cualquier colaborador humano o asistente IA que entre al repo. Dos `slugify` coexisten (`Slug` y `Seo::slugify`, herencia de la paridad con `schema.ts`) — ya innecesario y fuente de divergencias futuras entre URL canónica y URL del JSON-LD. El sitemap se regenera en cada MISS leyendo 4 tablas completas y **no emite `<lastmod>`**. Sin cabeceras CSP ni HSTS. Deploy FTP manual sin manifiesto (fácil olvidar un fichero). `/health` da poca señal. |
| **Amenazas** | El proveedor puede cambiar la versión de PHP sin aviso (sin CI que lo detecte). Límites opacos del hosting gratuito (CPU, inodos, procesos). La **BD maestra vive en el portátil del admin**: perder esa máquina es perder la capacidad de edición (los backups de prod mitigan la pérdida de datos, no la operativa). |
| **Oportunidades** | El mismo `Repo` puede servir JSON con coste marginal: API pública de solo lectura barata. `llms.txt` y feeds se generan igual que el sitemap. IndexNow tras cada sync. Deploy FTP automatizable con `lftp mirror` en GitHub Actions. |

### Intocable

- El **front controller + Router minimalista** y la decisión «sin composer, sin
  build»: restricción real del hosting y a la vez la razón de que todo sea mantenible
  por una persona.
- `Db` (singleton PDO + prepared statements) y el **esquema FTS5**.
- El flujo **PropuestaRepo (JSON, nunca BD)** para editores: es lo que permite que
  producción sea de solo lectura.
- Las **canónicas slug-id con 308**: cambiar el formato de URL rompería el SEO acumulado.

### A mejorar

**De su responsabilidad:**
1. Unificar los dos slugify (mantener `Slug`, hacer que `Seo` lo use; verificar con un test que JSON-LD y canónica coinciden).
2. `<lastmod>` en el sitemap (derivado de `admin_log` o de la fecha del último sync) y valorar volcarlo a fichero estático en el sync.
3. Cabeceras CSP (sencilla: `default-src 'self'` + YouTube nocookie + GoatCounter) y HSTS en `.htaccess`.
4. Endurecer `sync_db_to_prod.php`: checksum del fichero subido, verificación de propuestas pendientes en prod antes de subir, modo mantenimiento durante el reemplazo.
5. Actualizar/archivar la documentación del stack anterior.

**Que pide a otros roles:**
- **A QA**: la BD fixture compartida (sin ella no hay tests reproducibles).
- **A PO**: decidir formalmente que PHP+HelioHost es el stack «definitivo por ahora» y reflejarlo en docs, para dejar de arrastrar el relato de la migración.

---

## 5. Product Owner

### DAFO

| | |
|---|---|
| **Fortalezas** | Activo de datos único en el nicho: nadie más tiene 4.212 marchas relacionadas con compositores, bandas, discos y —sobre todo— advocaciones/dedicatorias. Coste operativo ≈ 0 €/mes. Independencia tecnológica total (datos propios, sin plataformas). Flujo editorial multiusuario recién estrenado (admin/editor con propuestas) que permite crecer contenido sin ceder control. Doble pipeline de crecimiento ya construido: ingesta de YouTube y matching de enlaces de streaming. |
| **Debilidades** | Bus factor = 1, agravado por la edición solo-local. Roadmap y contexto documental obsoletos (describen fases ya muertas). Sin KPIs definidos ni cuadro de mando (¿GoatCounter activo? ¿objetivos en Search Console?). Sin licencia explícita de los datos ni política de atribución (clave si queremos que las IA citen). Sin canal de contribución para el aficionado no-editor. Monetización/sostenibilidad sin definir (ni siquiera un «apóyanos»). |
| **Amenazas** | **Los LLM responden preguntas del nicho con nuestros datos sin citarnos** → valor extraído, tráfico cero. Un competidor con mejor UX puede scrapear el catálogo. Estacionalidad extrema: el año se juega en ~8 semanas; una caída en Cuaresma es catastrófica. Desmotivación del mantenedor único. |
| **Oportunidades** | Convertirse en **la fuente canónica que citan los buscadores IA**: datos estructurados ya existen, faltan `llms.txt`, dumps con licencia de atribución y API. Comunidad de editores como palanca de contenido (biografías, notas históricas: contenido único que el scraping no replica). Calendario editorial de Cuaresma. Alianzas con bandas (sus canales ya están mapeados en `ingest_canal`). |

### Intocable

- El **foco de nicho** (música procesional española) y la **propiedad del dato**: no
  diluirse en «música cofrade general» ni ceder el catálogo a terceros sin atribución.
- El **flujo editorial admin/editor**: acaba de estrenarse; se consolida antes de
  ampliarlo.
- La **estructura de costes ≈ 0**: cualquier propuesta con coste recurrente necesita
  justificación explícita.

### A mejorar

**De su responsabilidad:**
1. Reescribir el roadmap sobre el stack real y archivar el histórico.
2. Definir KPIs y activar el tablero: GoatCounter + Search Console revisados con cadencia fija.
3. Licencia de datos + política de citación (p. ej. CC BY con atribución a marchasdecristo.com) publicada en una página «Datos».
4. Plan editorial de Cuaresma 2027 (qué se publica, qué se cura, qué se automatiza) empezando en otoño.

**Que pide a otros roles:**
- **A UX**: home que convierta al visitante estacional en recurrente (marcha del día, recorridos).
- **Al dev**: API/feeds/dumps para ser citables por máquinas.
- **A QA**: que Semana Santa no nos pille sin monitorización.

---

## 6. Veredicto del juez

### Principios de decisión

1. **La marcha es el átomo.** Toda mejora se mide por una vara: ¿acerca al usuario a
   una ficha de marcha y a su escucha, o no? La ficha ya es excelente; el déficit
   está en **los caminos que llevan a ella** (home, hubs, búsqueda) y en **la
   cobertura de audio**.
2. **Lo que los robots no pueden descubrir, no existe.** El sitio tiene el mejor
   JSON-LD de su nicho, pero los listados navegables están en `noindex` y el sitemap
   no declara frescura. Las 4.212 fichas dependen de un sitemap plano sin `lastmod`
   y de enlaces internos escasos. **Crear superficies indexables (hubs por año,
   estilo, provincia, advocación) es la palanca SEO nº 1** — y a la vez mejora la
   navegación humana: los dos focos del encargo convergen en la misma tarea.
3. **Para las IA, ser citable es ser visible.** El dato estructurado ya existe;
   falta publicarlo *para máquinas* con atribución: `llms.txt`, feeds, API JSON de
   solo lectura y dumps con licencia que exija cita. Mejor ser la fuente que los LLM
   citan que la que ignoran.
4. **La operativa manual es el riesgo existencial silencioso.** Deploy FTP a mano,
   sync del `.db` a mano y cero tests: cualquier crecimiento multiplica la
   probabilidad de un accidente que borre lo ganado en SEO o en datos. Automatizar
   *antes* de crecer.
5. **Las señas de identidad no se tocan.** Gramática bibliográfica, simplicidad del
   stack, flujo de propuestas, privacidad (fachada YouTube, GoatCounter). El consejo
   coincide unánimemente: se protege lo que ya funciona.

### Arbitrajes entre roles

- UX pedía búsqueda global y PO más contenido: el juez antepone los **hubs
  indexables** porque sirven a ambos con las consultas de facetas que ya existen
  (coste bajo, doble impacto). La búsqueda global pasa a medio plazo.
- QA pedía suite completa: se recorta a **smoke tests de rutas doradas + validación
  de sitemap/JSON-LD en CI** (máximo valor por hora; protege el activo SEO, que es
  lo irreemplazable).
- UI pedía más superficie de rediseño: se limita a **og:image, impresión y
  accesibilidad**; la identidad visual actual es una fortaleza, no un problema.
- El dev pedía refactors: se aprueban solo los que sirven a los focos (unificar
  slugify —coherencia canónica/JSON-LD—, `lastmod`, CSP/HSTS, endurecer sync).
  La API pública va a medio plazo con `llms.txt`.
- PO debe pagar primero la deuda barata que desbloquea todo: **docs actualizadas**
  (colaboración humana e IA), **licencia/atribución** (prerrequisito de dumps y
  `llms.txt`) y **tablero de métricas** (sin él, el resto no se puede evaluar).

### Conclusiones optimizadas (orden de prioridad)

1. Hubs indexables por año, estilo y provincia con URL propia (path, no query),
   enlazados desde ficha y home; los listados filtrados por query siguen `noindex`.
2. `lastmod` en sitemap + ping (IndexNow/Google) tras cada sync de BD.
3. «Marcha del día» + home de descubrimiento (contenido fresco automático, punto de
   entrada estacional).
4. Campaña de cobertura de audio con la ingesta ya construida (objetivo medible:
   % de marchas con escucha).
5. CI con smoke tests + deploy FTP automatizado + sync endurecido (protege todo lo
   anterior).
6. `og:image`/Twitter Card (el share social es el canal natural del nicho).
7. `llms.txt` + API JSON de solo lectura + feed de novedades + página «Datos» con
   licencia y atribución (estrategia IA).
8. Monitorización externa y alertas antes de Cuaresma 2027.
9. Docs realineadas con el stack real.
10. Contenido único (biografías, notas) como foso defensivo a largo plazo frente a
    scraping y respuestas IA sin cita.

---

## 7. Plan de acción

Coste en horas de trabajo del mantenedor (equipo = 1 persona + asistentes IA).
Repercusión sobre los dos focos: 🎺 experiencia marcha-céntrica · 🔍 SEO/robots IA ·
⚙️ operativa (habilitador).

### Corto plazo (0–1 mes) — total ≈ 30–35 h

| # | Tarea | Rol líder | Coste | Repercusión | Foco |
|---|-------|-----------|-------|-------------|------|
| C1 | Hubs indexables `/marchas/ano/{yyyy}`, `/marchas/estilo/{cctt\|am}`, `/marchas/provincia/{slug}` con título/description/canónica propios, enlazados desde «Véase también» y home; query strings siguen `noindex` | dev + UX | 8 h | **Alta** — abre el long-tail de 4.212 fichas a Google y da navegación humana | 🎺🔍 |
| C2 | `<lastmod>` en sitemap (desde `admin_log`/fecha de sync) + ping a Google e IndexNow en el post-sync | dev | 3 h | **Alta** — recrawl eficiente de 5.744 URLs | 🔍 |
| C3 | «Marcha del día» en home (determinista por fecha, prioriza marchas con audio) + bloque de accesos por estilo/año/dedicatorias | UX + dev | 5 h | **Alta** — la home pasa de estática a viva sin trabajo editorial | 🎺 |
| C4 | `og:image` de marca + `twitter:card` + `alt` correcto en portadas | UI | 3 h | Media-alta — cada share en X/WhatsApp gana un cartel | 🔍 |
| C5 | CI GitHub Actions: `php -l`, smoke tests con servidor embebido + BD fixture (200/308/404, JSON-LD parseable, sitemap muestral) | QA | 6 h | **Alta** — red de seguridad del activo SEO | ⚙️ |
| C6 | Uptime externo (UptimeRobot u similar) a `/health` con alerta | QA | 1 h | Media — enterarse de las caídas antes que los usuarios | ⚙️ |
| C7 | Endurecer `sync_db_to_prod.php`: checksum, comprobación de propuestas pendientes en prod antes de subir, modo mantenimiento durante el swap | dev | 4 h | **Alta** — elimina el mayor riesgo de pérdida de datos | ⚙️ |
| C8 | Actualizar `docs/context.md`/`architecture.md` al stack PHP y archivar los del stack Next.js | PO + dev | 3 h | Media — desbloquea colaboración humana e IA | ⚙️ |

### Medio plazo (1–4 meses) — total ≈ 55–70 h

| # | Tarea | Rol líder | Coste | Repercusión | Foco |
|---|-------|-----------|-------|-------------|------|
| M1 | `llms.txt` + API JSON de solo lectura (`/api/marcha/{id}.json` y equivalentes, mismo `Repo`) + feed RSS/JSON de novedades + página «Datos» con licencia y política de citación | dev + PO | 10 h | **Alta** — la apuesta estratégica por ser la fuente citada por las IA | 🔍 |
| M2 | Campaña de cobertura de audio: ejecutar la ingesta sobre todas las bandas mapeadas, curar candidatos; KPI: % de marchas con escucha (medir antes/después) | PO + admin | 15 h+ | **Alta** — la mejora de experiencia por hora más rentable | 🎺 |
| M3 | Búsqueda global unificada (una caja, resultados agrupados por entidad, prefijos FTS5) + autocompletado público | dev + UX | 10 h | Media-alta | 🎺 |
| M4 | `og:image` dinámica por entidad (GD, cacheada a disco: título + compositor + año) | UI + dev | 8 h | Media | 🔍 |
| M5 | Deploy FTP automatizado desde CI (`lftp mirror` con manifiesto, solo en `main` verde) | dev | 5 h | Media-alta — cierra el ciclo de C5 | ⚙️ |
| M6 | Accesibilidad (foco visible, skip-link, `aria-sort`, contraste) + hoja de impresión de fichas | UI | 6 h | Media | 🎺 |
| M7 | Notificaciones editoriales: email al editor al aceptar/rechazar propuesta; digest semanal al admin (propuestas + ingesta + enlaces pendientes) | dev | 6 h | Media — mantiene vivo el flujo editorial | ⚙️ |
| M8 | Unificar slugify (`Seo` usa `Slug`) con test de coherencia canónica ↔ JSON-LD; CSP + HSTS en `.htaccess` | dev | 4 h | Media | 🔍⚙️ |
| M9 | Estadísticas ampliadas como contenido indexable (récords, series por década, mapas por provincia) | dev | 6 h | Media | 🔍 |

### Largo plazo (4–12 meses)

| # | Tarea | Rol líder | Coste | Repercusión | Foco |
|---|-------|-----------|-------|-------------|------|
| L1 | Dumps abiertos versionados (CSV/SQLite) con licencia CC BY y atribución obligatoria, publicados en «Datos» | PO + dev | 8 h | **Alta** para visibilidad IA — el dataset citable del nicho | 🔍 |
| L2 | Hubs enriquecidos por advocación/hermandad: texto editorial + playlist embebida (streaming curado) | UX + PO | 20 h+ | **Alta** — contenido único, el foso defensivo | 🎺🔍 |
| L3 | Biografías de compositores vía editores (plan de contribución con plantilla y revisión) | PO + editores | continuo | Alta — texto original que el scraping no replica | 🔍 |
| L4 | Formulario público «propón una grabación» (marcha sin audio → URL de YouTube → cola de propuestas) | UX + dev | 12 h | Media-alta — abre la contribución más allá de los editores | 🎺 |
| L5 | PWA básica offline (fichas visitadas, marcha del día) para la Semana Santa en la calle | dev | 15 h | Media | 🎺 |
| L6 | Revisión del hosting si el tráfico crece (VPS pequeño o estático + API); decisión con datos del tablero, no antes | PO + dev | decisión | — | ⚙️ |

**Regla de secuencia**: C5/C7 (red de seguridad) se hacen antes o a la vez que C1–C3
(cambios de superficie pública). M1 requiere la licencia de datos definida (tarea PO
del corto plazo). Nada del largo plazo empieza sin el tablero de KPIs activo.

---

## 8. Automatizaciones

### Para los usuarios (contenido vivo sin trabajo manual)

| Automatización | Mecanismo | Estado |
|---|---|---|
| «Marcha del día» y efemérides de compositores en la home | Determinista por fecha sobre la BD; cero mantenimiento | Propuesta (C3) |
| Feed RSS/JSON de últimas incorporaciones | Generado como el sitemap, desde `Repo::fetchUltimas` | Propuesta (M1) |
| Frescura para buscadores (`lastmod`, ping IndexNow) | Hook en el script de sync | Propuesta (C2) |
| Sugerencias/autocompletado en la búsqueda | FTS5 por prefijo, endpoint público cacheado | Propuesta (M3) |

### Para el administrador

| Automatización | Mecanismo | Estado |
|---|---|---|
| Backup diario/semanal del `.db` con retención | `app/tools/backup.php` vía cron de Plesk | ✅ Existe — verificar que el cron está dado de alta |
| Verificación de integridad del backup | Añadir `PRAGMA integrity_check` + aviso al fallo en `backup.php` | Propuesta |
| Copia externa del backup (fuera de HelioHost) | rclone/curl a un almacenamiento gratuito desde el PC local o GitHub Action | Propuesta |
| Lint + smoke tests en cada push | GitHub Actions (C5) | Propuesta |
| Deploy FTP automático en `main` verde | `lftp mirror` en CI (M5) | Propuesta |
| Sync BD local→prod con guardarraíles | `sync_db_to_prod.php` ya exige backup reciente; añadir checksum, chequeo de propuestas y modo mantenimiento (C7) | Parcial |
| Digest semanal de colas editoriales (propuestas, candidatos de ingesta, enlaces por curar) | Script PHP + cron (email) o Action programada | Propuesta (M7) |
| Link-checker mensual del sitemap (detectar ≠200/308) | GitHub Action programada que recorre una muestra + informe | Propuesta |
| Validación schema.org/JSON-LD de fichas doradas | Paso de CI con parser JSON-LD | Propuesta (dentro de C5) |
| Lighthouse/axe mensual con presupuesto | GitHub Action programada | Propuesta |
| Informe mensual de GoatCounter + Search Console (páginas top, consultas) | Export API + script; alimenta el plan editorial | Propuesta |
| Ejecución mensual de ingesta YouTube y matchers de streaming | Un solo script orquestador local (extract→classify→dedup→import + resumen) lanzado con recordatorio programado | Parcial (piezas existen, falta orquestación) |

### Para los editores

| Automatización | Mecanismo | Estado |
|---|---|---|
| Previsualización de la ficha antes de proponer | `propuesta_preview` | ✅ Existe |
| Notificación al aceptar/rechazar su propuesta | Email desde el flujo de revisión (M7) | Propuesta |
| Recordatorio al admin de propuestas estancadas (> N días) | Incluido en el digest semanal | Propuesta |
| Aviso de duplicados al proponer | `marcha/checkDuplicate` ya existe en el panel; asegurar que el editor lo ve | Parcial |

---

## Cierre

El proyecto está en el mejor estado técnico de su historia: stack simple y adecuado,
datos únicos, ficha de marcha sobresaliente y una base SEO que la mayoría de sitios
del nicho no tiene. Los dos focos del encargo no compiten entre sí: **las mismas
tareas que centran la experiencia en la marcha (hubs, marcha del día, cobertura de
audio) son las que abren el sitio a Google y a los robots IA**. El riesgo real no
está en lo que falta, sino en lo que no está protegido: cero tests, deploy y sync
manuales, y un único mantenedor. Por eso el plan intercala cada mejora visible con
su red de seguridad correspondiente.
