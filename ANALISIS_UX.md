# Análisis UX comparativo — mdc-back vs. patrimoniomusical

> Generado a partir de una sesión de exploración con navegador sobre `localhost:8010`
> comparando el proyecto actual con [patrimoniomusical.com](https://patrimoniomusical.com)
> (portal de referencia del mismo dominio: marchas procesionales).

## Contratiempo detectado durante la exploración

El servidor local se volvió intermitente y llegó a caer del todo
(`ERR_CONNECTION_REFUSED`). El fichero `assets/app.css` devolvió en algún momento un
**503**, lo que hizo que home y listados se renderizaran sin estilos (viñetas, SVGs
como manchas negras, enlaces azules subrayados), mientras que la ficha de detalle sí
cargaba con estilo. No se pudieron explorar las vistas de Bandas, Discos,
Dedicatorias, Estadísticas, Mapa ni Temporada en vivo por este motivo.

Diagnóstico: `app.css` se sirve como fichero estático real (no pasa por
`routes.php`/`index.php`), así que el fallo no está en el código de la app. Encaja con
el **servidor embebido de PHP (`php -S`)**, que por defecto atiende **una conexión a
la vez** (`PHP_CLI_SERVER_WORKERS=1`): cuando el navegador pide HTML y CSS a la vez,
una de las dos peticiones queda en cola o se descarta bajo carga.

- [ ] Arrancar el servidor local con varios workers para desarrollo con assets:
      `PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t php/public php/public/index.php`
- [ ] Documentar esto en `php/README.md` (sección "Desarrollo en local").
- [ ] Para pruebas de UX/carga realistas, servir detrás de Apache/Nginx + PHP-FPM.

## Análisis comparativo por vistas

**Ficha de marcha.** Mejor tipografía y navegación cruzada ("Véase también") que
patrimoniomusical, pero los datos se dispersan en dos columnas muy separadas y el
vídeo, al desplegarse, invade la pantalla. Patrimoniomusical usa una tabla compacta de
una columna y pestañas "Datos / Grabaciones (n)" que se escanean de un vistazo.
→ Compactar la ficha y separar en pestañas o bloques rotulados con recuento.

**Home.** Muy bien estructurada semánticamente (regiones "Marcha del día", "Explorar
el catálogo", "Últimas incorporaciones"), más rica y moderna que la home-portal de
patrimoniomusical (revista con cabecera pesada y bloques recargados). Aquí gana
claramente el proyecto actual en concepto; el único problema es el fallo de CSS.
→ Arreglar la carga del CSS es prioridad cero: la buena estructura no se aprecia sin
estilos.

**Listados y búsqueda.** La diferencia más instructiva. El proyecto actual ofrece una
única caja de búsqueda global con autocompletado (elegante y rápida para quien sabe
qué busca). Patrimoniomusical ofrece un **buscador avanzado por criterios** (Título,
Autor, Año, Tipo, Estilo, País, Región, Provincia, Localidad, Dedicatoria, más filtros
como "Cumple Aniversario", "No Datada", "Reciente", "Anónima") con resultados en
**tabla ordenable por columnas**. Para casi 4.800 marchas, la búsqueda facetada es un
complemento muy valioso.
→ Añadir filtros facetados y tablas ordenables a los listados, sin renunciar a la
búsqueda global existente.

**Entidades (compositores, bandas, discos).** Patrimoniomusical las trata como fichas
con datos tabulados y listados asociados; el modelo actual de "página de entidad +
enlaces con recuentos" es conceptualmente igual o mejor.
→ Mantener el enfoque, aplicando la misma compactación visual de la ficha de marcha.

## Plan de actuación consolidado

- [ ] **Prioridad 0 — Infraestructura.** Resolver el 503 del CSS y las caídas del
      servidor en desarrollo local (workers del servidor embebido de PHP o servir
      detrás de Apache/Nginx + PHP-FPM). Verificar cabeceras MIME del CSS.
- [x] **Prioridad 1 — Ficha de marcha.** Compactar la rejilla de datos (menos espacio
      vertical, columnas más juntas), introducir pestañas/anclas "Datos / Escuchar /
      Grabaciones (n)", y limitar la altura del reproductor de vídeo para que no
      desplace el contenido.
- [ ] **Prioridad 2 — Legibilidad global.** Revisar las etiquetas en versalitas
      monoespaciadas (COMPOSITOR, DEDICATORIA…) hacia un estilo más legible para
      público general, conservando la monoespaciada solo como acento.
- [ ] **Prioridad 3 — Listados.** Añadir filtros facetados (autor, año, tipo, estilo,
      dedicatoria, ubicación) y tablas con ordenación por columna, complementando la
      búsqueda global existente.
- [ ] **Prioridad 4 — Datos.** Valorar campos adicionales que aporta
      patrimoniomusical (ubicación geográfica, distinción tipo/estilo, campo libre de
      "notas/observaciones").
- [ ] **Prioridad 5 — Consistencia.** Aplicar la compactación y el patrón de bloques a
      todas las vistas de entidad (compositor, banda, disco) y a home, manteniendo los
      puntos fuertes actuales (breadcrumbs, búsqueda global, "Véase también" con
      recuentos).
