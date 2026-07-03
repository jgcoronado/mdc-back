<?php

declare(strict_types=1);

namespace App;

final class Http
{
    /** Redirección permanente (canónica slug-id). 308 preserva el método GET. */
    public static function redirect(string $location, int $status = 308): never
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    /** 404 con la plantilla propia dentro del layout. */
    public static function notFound(): never
    {
        http_response_code(404);
        View::render('404', [], ['title' => 'Página no encontrada — Marchas de Cristo']);
        exit;
    }
}
