# Marchas de Cristo — Backend

Aplicación web de música procesional española ([marchasdecristo.com](https://marchasdecristo.com)).

**Stack actual (junio 2026):** Next.js 15 · SQLite (better-sqlite3) · Docker · Nginx

---

## Estructura del repositorio

```
mdc-back/
├── nextjs/                     # Toda la aplicación
│   ├── Dockerfile
│   ├── package.json
│   ├── next.config.ts
│   ├── app/
│   │   ├── api/                # Route Handlers (API)
│   │   │   ├── login/          # POST /api/login, GET /verify, POST /logout
│   │   │   ├── autor/fastSearch
│   │   │   ├── banda/fastSearch
│   │   │   └── admin/          # editMarcha, addMarcha, addAutor
│   │   ├── marcha/             # Páginas públicas SSR/ISR
│   │   ├── autor/
│   │   ├── banda/
│   │   ├── disco/
│   │   ├── dashboard/          # Admin (protegido por middleware)
│   │   └── login/
│   ├── lib/
│   │   ├── db.ts               # Singleton SQLite (better-sqlite3)
│   │   ├── api.ts              # Lecturas directas a SQLite para Server Components
│   │   ├── auth-session.ts     # HMAC-SHA256 sign/verify
│   │   └── adminApi.ts         # Helpers admin (buildMarchaUpdatePayload, etc.)
│   └── middleware.ts           # Guard de /dashboard/* (verifica cookie HMAC)
├── db/
│   └── schema.sql              # Esquema de referencia + FTS5 + índices
├── scripts/                    # Utilidades de migración y snapshot
├── docker-compose.yml
├── nginx.conf.example
└── .env.example
```

---

## Variables de entorno

Copia `.env.example` a `.env` y rellena los valores:

```env
SECRET_KEY='genera con: openssl rand -base64 48'
AUTH_COOKIE_NAME=mdc_session
LOGIN_TTL_MS=28800000
COOKIE_SECURE=true

LOGIN_MAX_ATTEMPTS=6
LOGIN_WINDOW_MS=900000
LOGIN_LOCK_MS=900000
PASSWORD_PBKDF2_ITERATIONS=210000
```

`DB_PATH` y `NODE_ENV` se inyectan desde `docker-compose.yml`.

---

## Desplegar en el VPS

```bash
# 1. Subir cambios
git push origin main

# 2. En el VPS
ssh <usuario>@<vps-ip>
cd /var/www/mdc-back
git fetch origin main && git checkout FETCH_HEAD -- .

# 3. Reconstruir y reiniciar
sudo docker compose build nextjs
sudo docker compose up -d nextjs
sudo docker logs --tail=20 mdc-nextjs

# 4. Verificar
curl -s http://localhost:3000/api/login/verify   # → {"authenticated":false}

# 5. Limpiar
sudo docker system prune -f
```

---

## Desarrollo local

```bash
cd nextjs
cp ../.env.example .env.local    # ajusta COOKIE_SECURE=false y DB_PATH
npm install
npm run dev                       # http://localhost:3000
```

La BD SQLite en local debe estar en la ruta indicada en `DB_PATH`.  
Para obtenerla desde producción: `scp <usuario>@<vps-ip>:/var/lib/docker/volumes/mdc-back_mdc-sqlite-data/_data/mdc.db ./data/mdc.db`

---

## Gestión de portadas

Las portadas (`/cover/*.png`) no se empaquetan en la imagen. Se sirven desde el host:

```yaml
# docker-compose.yml
volumes:
  - ${COVER_DIR:-/var/www/mdc-assets/cover}:/app/public/cover:ro
```

Para añadir una portada: `scp imagen.png <usuario>@<vps-ip>:/var/www/mdc-assets/cover/<ID_DISCO>.png`

Nginx las sirve directamente con `Cache-Control: public, immutable; expires 30d`.

---

## Backups

Cron configurado en el VPS (3:00 AM diario, retención 14 días):

```
0 3 * * * cp /var/lib/docker/volumes/mdc-back_mdc-sqlite-data/_data/mdc.db \
  /var/backups/mdc-$(date +%F).db && \
  find /var/backups -name "mdc-*.db" -mtime +14 -delete
```

Backup manual:
```bash
sudo cp /var/lib/docker/volumes/mdc-back_mdc-sqlite-data/_data/mdc.db \
  /var/backups/mdc-manual-$(date +%F-%H%M).db
```

---

## Rollback rápido

```bash
# Restaurar backup de BD
sudo docker compose stop nextjs
sudo cp /var/backups/mdc-backup-YYYY-MM-DD.db \
  /var/lib/docker/volumes/mdc-back_mdc-sqlite-data/_data/mdc.db
sudo docker compose up -d nextjs

# Restaurar nginx
sudo cp /etc/nginx/sites-available/default.bak /etc/nginx/sites-available/default
sudo systemctl reload nginx
```

---

## Problemas comunes

| Síntoma | Causa probable | Solución |
|---------|---------------|----------|
| `SQLITE_CANTOPEN` | Volumen no montado o permisos | Verificar `docker inspect mdc-nextjs` → Mounts |
| `no such table: marcha_fts` | `db/schema.sql` no aplicado | Ejecutar schema contra la BD |
| `database is locked` | Proceso de backup simultáneo | Esperar o usar `.backup` de sqlite3 |
| 502 en nginx | Contenedor caído | `sudo docker compose up -d nextjs` |
