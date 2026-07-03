<?php

declare(strict_types=1);

namespace App;

/**
 * Renderizado con plantillas PHP nativas. Una vista se captura en un buffer y se
 * inyecta dentro de layout.php como $content.
 */
final class View
{
    /**
     * @param array<string,mixed> $data  variables para la plantilla
     * @param array<string,mixed> $meta  title, description, noindex, og, jsonld
     */
    public static function render(string $template, array $data = [], array $meta = []): void
    {
        $content = self::capture($template, $data);
        $config = $GLOBALS['config'];
        require APP_DIR . '/templates/layout.php';
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_DIR . '/templates/' . $template . '.php';
        return (string) ob_get_clean();
    }

    /** Escape HTML seguro para interpolar en las plantillas. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
