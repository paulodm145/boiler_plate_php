<?php

namespace App\Core;

use App\Repositories\SettingRepository;

class ErrorPage
{
    public static function notFound(): void
    {
        self::render(404, 'Pagina nao encontrada');
    }

    public static function forbidden(): void
    {
        self::render(403, 'Acesso negado');
    }

    public static function serverError(): void
    {
        self::render(500, 'Erro interno');
    }

    public static function render(int $statusCode, string $title): void
    {
        http_response_code($statusCode);

        $viewPath = dirname(__DIR__) . '/Views/errors/' . $statusCode . '.php';

        if (!is_file($viewPath)) {
            echo $statusCode . ' - ' . $title;
            return;
        }

        $settings = self::settings();
        require $viewPath;
    }

    private static function settings(): array
    {
        $defaults = [
            'site_name' => 'App Boilerplate',
            'site_description' => 'Boilerplate de PHP puro (MVC hand-rolled, sem framework).',
        ];

        try {
            return array_merge($defaults, (new SettingRepository())->all());
        } catch (\Throwable $exception) {
            return $defaults;
        }
    }
}
