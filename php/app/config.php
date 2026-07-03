<?php

declare(strict_types=1);

// Valores por defecto (sin secretos). Los secretos y overrides locales van en
// config.local.php (NO se sube al git). Ver config.local.example.php.
$defaults = [
    'debug'            => false,
    'site_url'         => 'https://marchasdecristo.com',
    'db_path'          => DATA_DIR . '/mdc.db',
    'secret_key'       => '',                 // Fase 3 (auth)
    'auth_cookie_name' => 'mdc_session',
    'login_ttl_ms'     => 8 * 60 * 60 * 1000, // 8 h
];

$localFile = APP_DIR . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_merge($defaults, is_array($local) ? $local : []);
