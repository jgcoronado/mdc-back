# Marchas de Cristo - Proyecto Unificado

Aplicación unificada en un solo proyecto:

- `backend`: Node.js + Express (`/api/*`)
- `frontend`: Vue + Vite (SPA servida por Express)
- `db`: MySQL externo (instalado en el VPS)

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
DB_HOST=172.17.0.1
DB_PORT=3306
DB_USER=usuario_mysql
DB_PASSWORD=clave_mysql
DB_NAME=nombre_bd
SECRET_KEY=tu_secret
APP_PORT=80
CORS_ORIGINS=https://marchasdecristo.com,http://localhost:8080
API_LOCAL_ONLY=true
```

Notas:

- `DB_HOST` debe ser accesible desde el contenedor.
- El usuario MySQL debe tener permisos para conectarse desde la red Docker.
- `COVER_DIR` se define en el entorno del servidor (no en `.env` de la app) para montar portadas externas.
- `API_LOCAL_ONLY=true` (valor por defecto) restringe `/api/*` a llamadas locales del servidor.
  Si necesitas exponer API a clientes externos, cambia a `API_LOCAL_ONLY=false` y revisa CORS.

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

Prueba de bloqueo de API desde fuera del host (debe responder `403` si `API_LOCAL_ONLY=true`):

```bash
curl -i "https://tu-dominio/api/marcha/search?titulo=cristo"
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

- `ECONNREFUSED 172.17.0.1:3306`: MySQL no escucha en esa interfaz/puerto.
- `Host '172.x.x.x' is not allowed`: faltan grants MySQL para red Docker.
- `ENOTFOUND host.docker.internal` en Linux: requiere `extra_hosts` con `host-gateway` o usar IP del host.
