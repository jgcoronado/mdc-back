# Marchas de Cristo - Proyecto Unificado

Aplicación unificada en un solo proyecto:

- `backend`: Node.js + Express (`/api/*`)
- `frontend`: Vue + Vite (SPA servida por Express)
- `db`: SQLite embebido (`better-sqlite3`, archivo en volumen Docker)

En producción se ejecuta en **un único contenedor Docker**.  
La API y la base de datos **no se exponen públicamente** por puertos directos.

## Estructura

- `index.js`: servidor Express principal
- `src/`: rutas y lógica backend
- `frontend/`: código Vue
- `Dockerfile`: build multi-stage (frontend + backend)
- `docker-compose.yml`: despliegue servidor
- `docker-compose-local.yml`: despliegue local

## Variables de entorno

Configura `.env` (especialmente en VPS):

```env
DB_PATH=/app/data/mdc.db
SECRET_KEY=tu_secret
APP_PORT=80
CORS_ORIGINS=https://marchasdecristo.com,http://localhost:8080
SITE_URL=https://marchasdecristo.com
```

Para ejecutar el script de migración desde MySQL (una sola vez) también necesitas:

```env
DB_HOST=...
DB_PORT=3306
DB_USER=...
DB_PASSWORD=...
DB_NAME=...
```

Notas:

- `DB_PATH` apunta al archivo SQLite dentro del contenedor. El volumen `mdc-sqlite-data` lo persiste.
- `COVER_DIR` se define en el entorno del servidor (no en `.env` de la app) para montar portadas externas.
- `SITE_URL` define el dominio canónico para `sitemap.xml` y `robots.txt`.

## Migración inicial desde MySQL

Para crear el archivo SQLite a partir del MySQL existente:

```bash
# Con el .env apuntando al MySQL origen (DB_HOST, DB_USER, etc.)
npm install
npm run db:migrate -- --out ./data/mdc.db
```

El script vuelca todas las tablas (`autor`, `banda`, `disco`, `marcha`, `marcha_autor`, `disco_marcha`, `usuarios`), preserva nombres de columna, y al final aplica `db/schema.sql` para crear las tablas FTS5 (`marcha_fts`, `autor_fts`), sus triggers y los índices que faltaban en MySQL.

Tras generar el `.db`, copia el archivo al volumen Docker del VPS:

```bash
scp data/mdc.db claude@VPS:/tmp/
ssh claude@VPS "docker cp /tmp/mdc.db mdc-app:/app/data/mdc.db && docker compose restart app"
```

## Verificar la migración (snapshot diff)

Para confirmar que SQLite devuelve lo mismo que MySQL, captura las respuestas de los endpoints públicos en ambos backends y haz diff:

```bash
# 1) Con MySQL aún activo:
npm run snapshot -- --base http://localhost:8080 --out snapshots/mysql

# 2) Genera el .db, conmuta a SQLite, levanta de nuevo:
npm run db:migrate -- --out ./data/mdc.db
docker compose up -d --build app
npm run snapshot -- --base http://localhost:8080 --out snapshots/sqlite

# 3) Compara:
npm run snapshot:diff snapshots/mysql snapshots/sqlite
```

El diff sale 0 si todo coincide, distinto de 0 si hay diferencias (con el primer campo divergente impreso). Sigue los pasos en este orden — no toques el código de las rutas en mitad del proceso.

## Gestión de portadas (sin rebuild)

Las portadas de discos no se empaquetan en la imagen Docker.  
Se sirven desde un directorio del host montado en `/app/public/cover`.

En `docker-compose.yml`:

```yaml
volumes:
  - ${COVER_DIR:-/var/www/mdc-assets/cover}:/app/public/cover:ro
```

En VPS:

```bash
sudo mkdir -p /var/www/mdc-assets/cover
# opcional: imagen por defecto
sudo cp /ruta/default.png /var/www/mdc-assets/cover/default.png
```

Y exporta `COVER_DIR` antes de levantar contenedor (si quieres otra ruta):

```bash
export COVER_DIR=/var/www/mdc-assets/cover
docker compose up -d --build
```

El frontend carga las imágenes en `/cover/{ID_DISCO}.png`.

## SEO técnico

La app publica automáticamente:

- `/sitemap.xml`: incluye solo URLs públicas (home, listados públicos y detalles de marcha/autor/banda/disco).
- `/robots.txt`: permite indexación general y bloquea zona privada:
  - `/login`
  - `/dashboard`

Recomendación:

- configura `SITE_URL` con tu dominio HTTPS final para que el sitemap use URLs canónicas correctas.

## Ejecutar en local

Requisitos:

- Docker + Docker Compose

Comandos:

```bash
git checkout unified-single-container
docker compose -f docker-compose-local.yml up -d --build
```

Acceso:

- `http://localhost:8080`

Parar:

```bash
docker compose -f docker-compose-local.yml down
```

## Desplegar en servidor (VPS)

```bash
git fetch origin
git checkout unified-single-container
git pull
docker compose down
docker compose up -d --build
```

Verificación:

```bash
docker ps
docker logs --tail 200 mdc-app
curl -i http://127.0.0.1:8080/
curl -i "http://127.0.0.1:8080/api/marcha/search?titulo=cristo"
```

## Nginx (recomendado)

Publicar solo por Nginx (HTTPS), proxy al contenedor local:

```nginx
location / {
  proxy_pass http://127.0.0.1:8080;
  proxy_http_version 1.1;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
}
```

## Problemas comunes

- `SQLITE_CANTOPEN: unable to open database file`: el directorio `data/` no existe o no tiene permisos; el `Dockerfile` ya lo crea con `chown node:node /app/data`.
- `no such table: marcha_fts`: el `db/schema.sql` no se ha ejecutado tras la migración. Ejecuta `npm run db:migrate` de nuevo o aplica manualmente: `sqlite3 data/mdc.db < db/schema.sql`.
- `database is locked`: WAL está activo, pero un proceso de backup puede colisionar con escrituras. Para backup en caliente usa `sqlite3 data/mdc.db ".backup /var/backups/mdc.db"`.
