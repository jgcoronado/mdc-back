<?php

declare(strict_types=1);

// Copia este archivo a config.local.php (NO se sube al git) y ajusta lo que necesites.
//   - En local: activa debug para ver errores.
//   - En el host (staging): apunta site_url al subdominio de pruebas.
//   - Fase 3 (auth): define secret_key con 48+ bytes aleatorios.

return [
    'debug' => true,

    // 'site_url' => 'https://jaguerra27.helioho.st', // staging durante la migración
    // 'db_path'  => __DIR__ . '/../data/mdc.db',      // por defecto ya apunta aquí
    // 'secret_key' => 'genera-48-bytes-aleatorios',   // Fase 3
    // 'force_canonical_host' => true,                 // ACTIVAR TRAS EL CUTOVER: 301 de
    //                                                 // jaguerra27.helioho.st y www a site_url
];
