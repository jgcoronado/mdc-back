<?php

declare(strict_types=1);

/*
 * Sincroniza el .db local con producción (HelioHost/Plesk) por FTP, con un
 * guardarraíl obligatorio: solo sube si hay un backup de producción con
 * menos de N días (10 por defecto) en private/backups/. Si no lo hay, para
 * sin tocar nada y avisa de que hace falta lanzar un backup primero.
 *
 * Requiere .env.ftp en la raíz del repo (gitignored) con:
 *   FTP_HOST, FTP_PORT, FTP_USER, FTP_PASSWORD, FTP_REMOTE_DIR (puede ir vacío)
 *
 * Usa curl (no la extensión ftp de PHP, que no está disponible en todos los
 * entornos) para listar, renombrar (RNFR/RNTO) y subir por FTP.
 *
 * Uso:
 *   php scripts/sync_db_to_prod.php                    # comprueba y sube si procede
 *   php scripts/sync_db_to_prod.php --dry-run           # solo comprueba, no sube nada
 *   php scripts/sync_db_to_prod.php --max-days 15       # umbral distinto a 10 días
 *   php scripts/sync_db_to_prod.php --local ruta/mdc.db # otro fichero local
 */

// ── args ─────────────────────────────────────────────────────────────────
$args = ['dryRun' => false, 'maxDays' => 10, 'local' => __DIR__ . '/../php/data/mdc.db'];
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if ($a === '--dry-run') $args['dryRun'] = true;
    elseif ($a === '--max-days') $args['maxDays'] = (int) $argv[++$i];
    elseif ($a === '--local') $args['local'] = $argv[++$i];
    else { fwrite(STDERR, "Argumento no reconocido: $a\n"); exit(2); }
}

// ── cargar .env.ftp ──────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env.ftp';
if (!is_file($envFile)) {
    fwrite(STDERR, "No existe .env.ftp en la raíz del repo. Necesito FTP_HOST/FTP_PORT/FTP_USER/FTP_PASSWORD/FTP_REMOTE_DIR.\n");
    exit(1);
}
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}
foreach (['FTP_HOST', 'FTP_USER', 'FTP_PASSWORD'] as $req) {
    if (empty($env[$req])) { fwrite(STDERR, "Falta $req en .env.ftp\n"); exit(1); }
}
$host = $env['FTP_HOST'];
$port = (int) ($env['FTP_PORT'] ?? 21);
// OJO: CURLOPT_USERPWD quiere el valor literal, NO codificado en URL (a
// diferencia de credenciales embebidas directamente en una URL ftp://user:pass@host).
$user = $env['FTP_USER'];
$pass = $env['FTP_PASSWORD'];
$remoteDir = trim($env['FTP_REMOTE_DIR'] ?? '', '/'); // vacío = raíz de la cuenta (enjaulada)
$prefix = $remoteDir !== '' ? $remoteDir . '/' : '';

if (!is_file($args['local'])) {
    fwrite(STDERR, "No existe el fichero local: {$args['local']}\n");
    exit(1);
}

// ── helpers curl/FTP ─────────────────────────────────────────────────────
function ftpBaseUrl(string $host, int $port): string
{
    return "ftp://$host:$port/";
}

/** Lista nombres de fichero (sin ruta) de un directorio remoto. */
function ftpList(string $baseUrl, string $user, string $pass, string $dir): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $dir . '/',
        CURLOPT_USERPWD => "$user:$pass",
        CURLOPT_FTP_USE_EPSV => false,
        CURLOPT_FTPLISTONLY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    $out = curl_exec($ch);
    if ($out === false) {
        fwrite(STDERR, 'Error listando ' . $dir . ': ' . curl_error($ch) . "\n");
        exit(1);
    }
    $names = array_filter(array_map('trim', explode("\n", $out)));
    return array_map('basename', $names); // por si el servidor devuelve rutas completas
}

/** Ejecuta comandos FTP crudos (RNFR/RNTO, etc.) contra el directorio dado. */
function ftpQuote(string $baseUrl, string $user, string $pass, string $dir, array $commands): bool
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $dir . '/',
        CURLOPT_USERPWD => "$user:$pass",
        CURLOPT_QUOTE => $commands,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    $ok = curl_exec($ch) !== false;
    if (!$ok) fwrite(STDERR, 'Error ejecutando comando FTP: ' . curl_error($ch) . "\n");
    return $ok;
}

/** Sube (crea/sobrescribe) un fichero local a una ruta remota. */
function ftpUpload(string $baseUrl, string $user, string $pass, string $remotePath, string $localPath): bool
{
    $fh = fopen($localPath, 'rb');
    if ($fh === false) return false;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $remotePath,
        CURLOPT_USERPWD => "$user:$pass",
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $fh,
        CURLOPT_INFILESIZE => filesize($localPath),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
    ]);
    $ok = curl_exec($ch) !== false;
    if (!$ok) fwrite(STDERR, 'Error subiendo: ' . curl_error($ch) . "\n");
    fclose($fh);
    return $ok;
}

// ── 1. Buscar el backup más reciente en private/backups/ ────────────────
$baseUrl = ftpBaseUrl($host, $port);
$backupsDir = $prefix . 'private/backups';
echo "Listando $backupsDir por FTP…\n";
$files = ftpList($baseUrl, $user, $pass, $backupsDir);

$masReciente = null; // ['fecha' => DateTimeImmutable, 'nombre' => string]
foreach ($files as $f) {
    if (preg_match('/^mdc-(\d{8})-(\d{6})\.db$/', $f, $m)) {
        $fecha = DateTimeImmutable::createFromFormat('Ymd-His', $m[1] . '-' . $m[2]);
        if ($fecha !== false && ($masReciente === null || $fecha > $masReciente['fecha'])) {
            $masReciente = ['fecha' => $fecha, 'nombre' => $f];
        }
    }
}

if ($masReciente === null) {
    fwrite(STDERR, "\n⛔ ABORTADO: no se encontró ningún backup (mdc-YYYYMMDD-HHMMSS.db) en $backupsDir.\n");
    fwrite(STDERR, "   RECORDATORIO: lanza un backup manual antes de sincronizar (Scheduled Tasks → backup.php, o ejecútalo tú vía cron/Plesk) y vuelve a intentarlo.\n");
    exit(1);
}

$dias = (new DateTimeImmutable())->diff($masReciente['fecha'])->days;
echo "Backup más reciente: {$masReciente['nombre']} ({$dias} día(s))\n";

if ($dias > $args['maxDays']) {
    fwrite(STDERR, "\n⛔ ABORTADO: el backup más reciente tiene {$dias} días (máximo permitido: {$args['maxDays']}).\n");
    fwrite(STDERR, "   RECORDATORIO: lanza un backup manual antes de sincronizar (Scheduled Tasks → backup.php, o ejecútalo tú vía cron/Plesk) y vuelve a intentarlo.\n");
    exit(1);
}

echo "✓ Backup reciente (≤ {$args['maxDays']} días) — se puede sincronizar.\n";

if ($args['dryRun']) {
    echo "--dry-run: no se sube nada.\n";
    exit(0);
}

// ── 2. Mover el .db actual de producción a backups/ antes de sobrescribir ──
// (red de seguridad extra, sin coste: renombrado en el propio FTP, no hace
// falta descargar+resubir. Queda con el mismo patrón de nombre que usa
// backup.php, así futuras ejecuciones de este script lo cuentan también
// como backup válido.)
$tsAhora = (new DateTimeImmutable())->format('Ymd-His');
$nombrePreSync = "mdc-$tsAhora.db";
echo "Moviendo private/mdc.db → private/backups/$nombrePreSync (red de seguridad)…\n";
$privateDir = $prefix . 'private';
$renombrado = ftpQuote($baseUrl, $user, $pass, $privateDir, [
    'RNFR mdc.db',
    "RNTO backups/$nombrePreSync",
]);
if (!$renombrado) {
    fwrite(STDERR, "\n⛔ ABORTADO: no se pudo mover el .db actual a backups/ antes de sobrescribir. No se ha subido nada.\n");
    exit(1);
}

// ── 3. Subir el .db local a private/mdc.db ───────────────────────────────
echo "Subiendo {$args['local']} → private/mdc.db…\n";
$subido = ftpUpload($baseUrl, $user, $pass, $privateDir . '/mdc.db', $args['local']);
if (!$subido) {
    fwrite(STDERR, "\n⛔ La subida falló. private/mdc.db quedó renombrado como backups/$nombrePreSync — restáuralo manualmente si hace falta.\n");
    exit(1);
}

echo "\n✅ Sincronizado: private/mdc.db actualizado. Copia previa a salvo en private/backups/$nombrePreSync.\n";
