<?php

declare(strict_types=1);

// Servidor embebido de PHP (php -S) en local: deja que sirva ficheros estáticos
// existentes (portadas, CSS) tal cual. En Apache esto lo resuelve el .htaccess.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

define('PUBLIC_DIR', __DIR__);
define('BASE_DIR', dirname(__DIR__));   // php/ en local · /home/USER en HelioHost
define('APP_DIR', BASE_DIR . '/app');
define('DATA_DIR', BASE_DIR . '/data');

require APP_DIR . '/bootstrap.php';
