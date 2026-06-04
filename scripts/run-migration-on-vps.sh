#!/bin/sh
# Run inside a one-shot node:22-alpine container on the VPS.
# Uses DB_USER_ADMIN / DB_PASSWORD_ADMIN (read from /var/www/mdc-back/.env)
# so that the connection from the migration container's IP is allowed.
set -e

apk add --no-cache python3 make g++ > /dev/null

cd /work
cat > package.json <<'JSON'
{"name":"mig","type":"module","version":"1.0.0","dependencies":{"better-sqlite3":"^11.5.0","mysql2":"^3.14.3","dotenv":"^17.2.1"}}
JSON

npm install --silent --no-audit --no-fund 2>&1 | tail -3

export DB_USER="$DB_USER_ADMIN"
export DB_PASSWORD="$DB_PASSWORD_ADMIN"
echo "Connecting as $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME (charset utf8mb4)"
node scripts/migrate-mysql-to-sqlite.mjs --out /work/mdc.db

ls -la /work/mdc.db
