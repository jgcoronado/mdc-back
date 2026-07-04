# Fase 5 — Cutover de marchasdecristo.com a HelioHost

> Runbook paso a paso. Marca las casillas a medida que avanzas.
> **Principio rector:** el SEO es el activo crítico. El VPS (Next.js) sigue siendo
> la fuente de verdad y el rollback hasta que el cambio esté validado.

La estructura de URLs del PHP es **idéntica** a la del Next.js (mismas rutas
`slug-id`, mismos redirects 308, mismo sitemap), así que el cutover **no cambia
ninguna URL** — es solo mover el dominio de un servidor a otro.

---

## 0. Datos que necesitarás a mano

- **IP de HelioHost** de tu cuenta: Plesk → *Sitios web y dominios → jaguerra27.helioho.st
  → Webspace settings → IP addresses* (o `ping jaguerra27.helioho.st`).
- **Dónde se gestiona el DNS de marchasdecristo.com** (registrador o Cloudflare):
  ahí cambiarás el registro `A`.
- Acceso al **VPS** (para la sincronización final del `.db`).

---

## 1. Pre-requisitos (días antes) — sin tocar el DNS todavía

- [ ] Fases 3-4 subidas y **verificadas en `jaguerra27.helioho.st`**: `/login` con tus
      credenciales, una edición de prueba, `/health` anónimo no filtra la ruta.
- [ ] **Portadas**: subir todos los PNG a `httpdocs/cover/` (si no, las carátulas de
      discos salen vacías; el resto funciona).
- [ ] `secret_key` definido en `app/config.local.php` del host (ideal: la `SECRET_KEY`
      del VPS, `/var/www/mdc-back/.env`, para no invalidar sesiones).
- [ ] **Cron de backup** configurado y probado a mano una vez:
      `/usr/local/bin/php /home/USUARIO/app/tools/backup.php` → comprueba que aparece
      `private/backups/mdc-*.db`.
- [ ] En `config.local.php` del host: **`'debug' => false`** (producción, no filtra errores).
- [ ] Añadir **marchasdecristo.com** (y `www`) al panel de HelioHost/Plesk como dominio o
      alias del sitio, apuntando al **mismo `httpdocs`**.
- [ ] **SSL** (Let's Encrypt / AutoSSL) para `marchasdecristo.com` y `www`. Puede requerir
      que el DNS ya apunte, o validación por DNS.
- [ ] En Plesk → *Hosting Settings*: activar **"Redirect HTTP → HTTPS (301)"** y fijar el
      **dominio preferido** (`www` → no-`www` con 301, o al revés — el sitio usa **no-www**).
- [ ] **Bajar el TTL** de los registros DNS de marchasdecristo.com a **300s** (5 min) al
      menos 24-48 h antes del cambio → propagación y rollback rápidos.

---

## 2. Sincronización final de datos (el día del cutover)

> El `.db` del host es una foto antigua. Hay que traer los últimos datos del VPS.

- [ ] **Congelar ediciones** en el sitio viejo: a partir de aquí, no toques el admin del VPS.
- [ ] Extraer el `.db` más reciente del VPS (copia limpia con checkpoint del WAL):
      ```bash
      cd /var/www/mdc-back
      docker compose stop
      docker cp mdc-nextjs:/app/data/mdc.db ./mdc.db
      docker compose start
      ```
- [ ] Subir ese `mdc.db` a `private/` del host, **reemplazando** el de staging.
      ⚠️ Esto sobreescribe cualquier edición de prueba que hayas hecho en el admin del host.
- [ ] Verificar en el host (con sesión admin) que `/health` muestra los conteos correctos.

---

## 3. El cambio (ventana de cutover)

- [ ] Cambiar el registro **`A`** (y `AAAA` si tienes IPv6) de marchasdecristo.com →
      **IP de HelioHost**. **No toques los registros `MX`** ni otros de correo salvo que
      sepas lo que haces (el correo del dominio, si lo hay, no debe verse afectado).
- [ ] Esperar propagación (con TTL a 300s, minutos). Verificar:
      `nslookup marchasdecristo.com` → debe devolver la IP de HelioHost.
- [ ] Confirmar **SSL válido** en `https://marchasdecristo.com` (candado, sin aviso).

---

## 4. Verificación post-cutover (inmediata)

- [ ] `https://marchasdecristo.com/` carga (home + conteos en el pie).
- [ ] Detalle: `/marcha/consuelo-gitano-330` → 200, `<title>`, 2 bloques JSON-LD.
- [ ] Canónica: `/marcha/330` → **308** → `/marcha/consuelo-gitano-330`.
- [ ] `http://` → `https://` redirige; `www.` → no-`www` redirige (301).
- [ ] `/sitemap.xml` (5.744 URLs) y `/robots.txt` (Sitemap apunta a marchasdecristo.com).
- [ ] Admin: `/login`, entrar, una edición real de prueba y comprobar que persiste.
- [ ] `/health` anónimo: solo `status: ok` + versión (sin ruta del `.db`).
- [ ] `curl -I` de una página: aparecen `Cache-Control` y `X-Content-Type-Options`.
- [ ] Portadas de discos cargan.

---

## 5. Subdominio de staging (evitar contenido duplicado)

`jaguerra27.helioho.st` sigue sirviendo el mismo sitio. Como todas las `og:url`/JSON-LD/
canónicas apuntan a `marchasdecristo.com`, Google **consolida** hacia el dominio bueno,
así que el riesgo es bajo. Aun así, recomendable cerrar la puerta:

- [ ] Opción A (recomendada): **301 del subdominio a marchasdecristo.com** — te lo puedo
      implementar en la app (un chequeo de `HTTP_HOST` en el bootstrap).
- [ ] Opción B: `noindex` para el host de staging.

---

## 6. Search Console (primeros días)

- [ ] Reenviar `sitemap.xml` en Search Console.
- [ ] Revisar **Cobertura/Indexación**: vigilar 404/500 y páginas excluidas nuevas.
- [ ] Inspeccionar URLs clave (home + un par de detalles) → "URL está en Google" /
      solicitar indexación.
- [ ] Vigilar **Rendimiento** (clics/impresiones) unos días por si hay caída.

---

## 7. Rollback (si algo va mal)

1. Volver a apuntar el registro `A` de marchasdecristo.com → **IP del VPS**.
2. Con el TTL a 300s, propaga en minutos. El VPS sigue sirviendo Next.js intacto.

No borres ni apagues el VPS hasta que la Fase 8 esté hecha.

---

## 8. Limpieza (semanas después, con todo estable)

- [ ] Apagar/retirar el VPS (o mantenerlo un tiempo más como backup).
- [ ] Subir de nuevo el **TTL** del DNS a su valor normal.
- [ ] Revisar los logs del host y el espacio en disco (backups).

---

## Notas

- **`site_url`** ya vale `https://marchasdecristo.com` por defecto, así que tras el cutover
  todas las URLs canónicas/OG/sitemap son correctas sin tocar nada.
- Si HelioHost pone Cloudflare delante del dominio, considera **purgar su caché** justo
  tras el cutover y en cada cambio grande de contenido.
- El `.db` y sus backups viven en `private/` (fuera del webroot). Confirma que esa carpeta
  tiene escritura (el admin, el rate-limiter y el cron escriben ahí).
