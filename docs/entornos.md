# Entornos: preproducción y producción

> Generado: 2026-07-16 (M5 ampliado — [issue #19](https://github.com/jgcoronado/mdc-back/issues/19))

## Mapa

| | **PRE** | **PRO** |
|---|---|---|
| URL | `https://marchasdecristo.jaguerra27.helioho.st` | `https://marchasdecristo.com` |
| Docroot (Plesk) | `pre/httpdocs/` | `marchasdecristo.com/` |
| Código | `pre/app/` | `app/` |
| BD + privados | `pre/private/` | `private/` |
| `config.local.php` | `preproduccion => true`, `env => production`, `site_url` al subdominio, `indexnow_key/goatcounter` null, `secret_key` propio | como hasta ahora |
| Indexación | **Bloqueada** (Basic Auth + noindex global + X-Robots-Tag + robots.txt Disallow) | Normal |
| Señas visibles | Cinta «Entorno de preproducción» en todas las páginas; `/health` dice `entorno: pre` | `/health` dice `entorno: prod` |
| Deploy de código | **Automático** en cada push a `main` con CI verde | **Manual**: botón *Run workflow* en Actions (target `pro`) |
| Deploy de BD | Manual: `php scripts/sync_db_to_prod.php --env pre` | Manual: `php scripts/sync_db_to_prod.php` (guardarraíles completos) |
| Backups (cron Plesk) | No (BD desechable, copia de la maestra local) | Sí (`app/tools/backup.php`) |
| Monitor uptime | No | UptimeRobot ([monitoring.md](monitoring.md)) |

Los dos entornos comparten cuenta de HelioHost pero **no comparten ningún
fichero**: cada uno tiene su código, su BD, su config y su `secret_key`. El
aislamiento sale gratis porque `index.php` resuelve `app/` y `private/` como
hermanos del padre del docroot — con el docroot de PRE dentro de `pre/`, todo
queda dentro de `pre/`.

## Flujo normal de trabajo

```
cambio de código → push/merge a main
                 → CI (lint + 44 smoke sobre fixture)
                 → deploy automático a PRE (lftp mirror)
                 → smoke remoto contra PRE (datos reales, exige noindex+auth)
                 → LO VALIDAS EN EL NAVEGADOR
                 → Actions → Deploy → Run workflow → target: pro
                 → mantenimiento ON → mirror a PRO → mantenimiento OFF
                 → smoke remoto contra PRO
```

La **BD va aparte y siempre manual** (la maestra es la local, como siempre):
para validar datos+código juntos en PRE, `--env pre` antes de mirar el
navegador. El pipeline **nunca** toca `private/`, `config.local.php`,
`cover/` ni `.well-known/`.

**Rollback de código en PRO**: Actions → Deploy → *Run workflow* → en el
desplegable de ref, elegir el commit/tag anterior → target `pro`.

## Puesta en marcha (una sola vez)

### En Plesk
1. Subdominio `marchasdecristo.jaguerra27.helioho.st` → *Hosting Settings* →
   document root = `pre/httpdocs`.
2. Emitir certificado **Let's Encrypt** para el subdominio (SSL/TLS).
3. Desactivar «Serve static files directly by nginx» (igual que en PRO).
4. **Password-protected directories** sobre el docroot de PRE (usuario/clave
   del Basic Auth). ⚠️ Si al activarlo Plesk escribiera las directivas de
   auth en `pre/httpdocs/.htaccess`, hay que moverlas a *Apache & nginx
   Settings → Additional directives* del subdominio: el deploy sobrescribe el
   `.htaccess` del docroot con el del repo y las borraría. El smoke remoto
   tiene una prueba explícita (401 sin credenciales) que avisa si la
   protección desaparece.

### Por FTP / en local
5. Crear `pre/app/config.local.php` en el host (no lo toca el pipeline):
   ```php
   <?php
   return [
       'debug' => false,
       'env'   => 'production',           // solo lectura, paridad con PRO
       'preproduccion' => true,           // noindex + robots Disallow + cinta
       'site_url' => 'https://marchasdecristo.jaguerra27.helioho.st',
       'force_canonical_host' => false,
       'db_path' => '/home/USUARIO/pre/private/mdc.db',
       'secret_key' => '...(96 chars nuevos, NO el de PRO)...',
       // indexnow_key y goatcounter_code se quedan null (defaults)
   ];
   ```
6. Crear `.env.ftp.pre` en la raíz del repo local (gitignored), mismas claves
   que `.env.ftp` con `FTP_REMOTE_DIR=pre`.
7. Subir la BD: `php scripts/sync_db_to_prod.php --env pre` (crea
   `pre/private/` y `backups/` si no existen; el primer sync no tiene copia
   previa que apartar y lo dice).

### En GitHub (Settings → Secrets and variables → Actions)
8. Secrets: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD` (los de `.env.ftp`) y
   `PRE_BASIC_AUTH` (`usuario:clave` del paso 4).
9. Primer despliegue: push a `main` (o *Run workflow* con target `pre`) y
   comprobar el job. **Orden importante**: config.local.php (5) y BD (7)
   antes del primer deploy — sin BD, el smoke remoto de PRE falla con
   `db: error`, que es su forma de decirte que falta el paso 7.

### Validación única del conjunto
10. Con todo verde: abrir PRE en el navegador (pedirá el Basic Auth), ver la
    cinta, `/health` con `entorno: pre` y `db: ok`.
11. Lanzar un *Run workflow* → target `pro` y comprobar que PRO despliega y
    su smoke pasa. Desde ese momento, el FTP manual de código queda retirado.

## Qué vigilar / limitaciones conocidas

- **FTP desde GitHub Actions**: primer riesgo a validar en la puesta en
  marcha (paso 9). Si HelioHost bloqueara las IPs de Actions, el mirror
  fallará con timeout — se vería en el log del job.
- El mirror usa **FTP plano**, igual que los scripts de sync existentes (es
  lo que ofrece el FTP de HelioHost tal y como está configurado hoy). Si el
  servidor admite FTPS explícito, cambiar `set ftp:ssl-allow false` a `true`
  en `deploy.yml` y probar.
- El deploy de PRO activa el **modo mantenimiento** (503 + Retry-After)
  durante el mirror — la ventana es de segundos, pero puede cruzarse con un
  chequeo de UptimeRobot y generar el aviso esperado documentado en
  [monitoring.md](monitoring.md).
- El deploy **no es transaccional**: si el mirror de PRO falla a mitad, el
  código queda mixto. El paso de mantenimiento minimiza el impacto (nadie lo
  ve servir) y el arreglo es relanzar el deploy (o el rollback por ref).
- `pre/httpdocs/.htaccess` y `pre/app/*` los pisa cada deploy: cualquier
  ajuste manual hecho por FTP en PRE se pierde. Los sitios legítimos para
  configuración por-host son `config.local.php` y Plesk.
