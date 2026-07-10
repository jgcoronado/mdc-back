<?php

declare(strict_types=1);

namespace App;

/**
 * Utilidades de medios. Por ahora solo YouTube: el campo marcha.AUDIO guarda
 * una URL de vídeo (formato watch?v=, youtu.be/, embed/…). A medio plazo esto
 * se sustituirá por una tabla marcha_audio multi-servicio (ver plan P-02).
 */
final class Media
{
    /**
     * Extrae el ID de 11 caracteres de una URL de YouTube, o null si la cadena
     * no es una URL de YouTube reconocible (p.ej. texto suelto o un enlace de
     * otro servicio). Cubre www./m./youtube-nocookie.com, youtu.be y las rutas
     * watch / embed / shorts / v, con parámetros extra en cualquier orden.
     */
    public static function youtubeId(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $re = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~';
        return preg_match($re, $url, $m) === 1 ? $m[1] : null;
    }

    /**
     * Miniatura del vídeo. hqdefault siempre existe; en un contenedor 16:9 con
     * object-fit: cover se recortan las bandas negras del 4:3 original.
     */
    public static function youtubeThumb(string $id): string
    {
        return 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
    }

    /**
     * URL de incrustación sin cookies (youtube-nocookie). No se carga hasta que
     * el usuario pulsa la fachada, así que ninguna cookie de terceros se envía
     * en la primera visita.
     */
    public static function youtubeEmbed(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . $id;
    }
}
