<?php

declare(strict_types=1);

// Valores por defecto (sin secretos). Los secretos y overrides locales van en
// config.local.php (NO se sube al git). Ver config.local.example.php.
$defaults = [
    'debug'            => false,
    'site_url'         => 'https://marchasdecristo.com',
    'db_path'          => getenv('DB_PATH') ?: (DATA_DIR . '/mdc.db'),
    'secret_key'       => '',                 // Fase 3 (auth) — definir en config.local.php
    'auth_cookie_name' => 'mdc_session',
    'login_ttl_ms'     => 8 * 60 * 60 * 1000, // 8 h
    'cookie_secure'    => false,              // true en producción (o se autodetecta por HTTPS)
    'login_max_attempts' => 6,
    'login_window_ms'    => 15 * 60 * 1000,   // 15 min
    'login_lock_ms'      => 15 * 60 * 1000,   // 15 min
    'password_pbkdf2_iterations' => 210000,
];

$localFile = APP_DIR . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_merge($defaults, is_array($local) ? $local : []);
