<?php

namespace App\Core;

class View
{
    public static function render(string $template, array $data = []): void
    {
        $viewPath = dirname(__DIR__) . '/Views/' . trim($template, '/') . '.php';

        if (!is_file($viewPath)) {
            ErrorPage::serverError();
            return;
        }

        extract($data, EXTR_SKIP);
        require $viewPath;
    }

    /** Renderiza o template e devolve o HTML como texto (e-mails, pedacos de tela). */
    public static function capture(string $template, array $data = []): string
    {
        ob_start();

        try {
            self::render($template, $data);
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
