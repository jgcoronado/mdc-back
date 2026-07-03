<?php

declare(strict_types=1);

// Autoload PSR-4 mínimo para el namespace App\ (sin composer).
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 4));
    $file = APP_DIR . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/** @var array<string,mixed> $config */
$config = require APP_DIR . '/config.php';
$GLOBALS['config'] = $config;

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

$router = new App\Router();
require APP_DIR . '/routes.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
