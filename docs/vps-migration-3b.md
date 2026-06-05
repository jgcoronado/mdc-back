# Migración VPS — Fase 3b: apagar Express, activar Next.js standalone

> Fecha estimada: junio 2026  
> Tiempo estimado: ~20 min (más build Docker)

## Qué cambia

- Se elimina el contenedor `mdc-app` (Express).
- El contenedor `mdc-nextjs` ahora sirve toda la app: páginas públicas, admin, API, sitemap.
- El volumen SQLite `mdc-sqlite-data` pasa de `mdc-app` a `mdc-nextjs`.
- La configuración de Nginx se simplifica: todo va al puerto 3000.

---

## Pasos

### 1. Subir los cambios al VPS

```bash
# Desde tu máquina local
git push origin main

# En el VPS
ssh claude@104.245.245.27
cd /var/www/mdc-back
git pull origin main
```

### 2. Hacer backup del volumen SQLite ANTES de tocar nada

```bash
# En el VPS
docker exec mdc-app cp /app/data/mdc.db /tmp/mdc-backup-fase3b.db
cp /tmp/mdc-backup-fase3b.db /var/backups/mdc-backup-fase3b-$(date +%F).db
ls -lh /var/backups/mdc-backup-fase3b-*.db   # verificar que existe
```

### 3. Copiar el fichero de BD al nuevo volumen

El volumen `mdc-sqlite-data` ya existe y tiene los datos del contenedor Express.
Next.js usará ese mismo volumen montado en `/app/data/`. No hay que mover nada —
el volumen se declara en `docker-compose.yml` con el mismo nombre, así Docker lo reutiliza.

Verifica que el volumen tiene datos:

```bash
docker run --rm -v mdc-back_mdc-sqlite-data:/data alpine ls -lh /data/
# Debe mostrar mdc.db
```

> **Nota:** el nombre completo del volumen Docker incluye el prefijo del directorio del proyecto
> (normalmente `mdc-back_mdc-sqlite-data`). Ajusta si es diferente en tu instalación.

### 4. Parar el contenedor Express

```bash
docker compose stop app
docker compose rm -f app
```

### 5. Construir y levantar Next.js con la nueva configuración

```bash
docker compose build nextjs
docker compose up -d nextjs
docker logs -f mdc-nextjs   # observar arranque; Ctrl+C cuando esté listo
```

El build tarda varios minutos porque compila `better-sqlite3` desde fuente.

### 6. Verificar que la app funciona

```bash
# Health check básico
curl -s https://marchasdecristo.com/api/login/verify | jq .
# Debe responder: {"authenticated":false}

curl -s "https://marchasdecristo.com/api/autor/fastSearch?nombre=Perez" | jq .rowsReturned
# Debe devolver un número >= 0

curl -s https://marchasdecristo.com/sitemap.xml | head -5
# Debe empezar con <?xml ...

# Comprobar login admin
curl -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -X POST https://marchasdecristo.com/api/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"TU_USUARIO","password":"TU_PASSWORD"}' | jq .
```

### 7. Actualizar la configuración de Nginx

```bash
sudo cp /etc/nginx/sites-available/marchasdecristo.com /etc/nginx/sites-available/marchasdecristo.com.bak
sudo cp /var/www/mdc-back/nginx.conf.example /etc/nginx/sites-available/marchasdecristo.com
sudo nginx -t && sudo systemctl reload nginx
```

### 8. Limpiar imágenes Docker antiguas

```bash
docker system prune -f
```

---

## Rollback

Si algo va mal, vuelve atrás en menos de 5 minutos:

```bash
# Revertir nginx
sudo cp /etc/nginx/sites-available/marchasdecristo.com.bak /etc/nginx/sites-available/marchasdecristo.com
sudo systemctl reload nginx

# Levantar Express de nuevo (desde el commit anterior)
git stash   # o git checkout <commit-anterior>
docker compose up -d app
```

---

## Notas post-migración

- `SECRET_KEY`, `COOKIE_SECURE` y demás variables de entorno las lee Next.js desde `.env`
  (el `env_file: .env` en `docker-compose.yml` las inyecta en el contenedor).
- El rate limiting de login usa un `Map` en memoria del proceso Node.js. Con un solo worker
  (configuración actual) esto es correcto. Si en el futuro se escala a varios workers,
  hay que moverlo a una tabla SQLite.
- El sitemap se regenera automáticamente cada hora gracias a `export const revalidate = 3600`
  en `nextjs/app/sitemap.ts`.
