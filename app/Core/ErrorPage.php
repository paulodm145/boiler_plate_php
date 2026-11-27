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
        if (self::wantsJson($_SERVER, headers_list())) {
            $codes = [404 => 'not_found', 403 => 'forbidden', 500 => 'server_error'];
            self::json($statusCode, $codes[$statusCode] ?? 'server_error');
            return;
        }

        self::setStatus($statusCode);

        $viewPath = dirname(__DIR__) . '/Views/errors/' . $statusCode . '.php';

        if (!is_file($viewPath)) {
            echo $statusCode . ' - ' . $title;
            return;
        }

        $settings = self::settings();
        $requestId = Logger::requestId();
        require $viewPath;
    }

    /** Pura: decide so pelos argumentos, para ser testavel sem superglobais. */
    public static function wantsJson(array $server, array $sentHeaders): bool
    {
        foreach ($sentHeaders as $header) {
            if (stripos(ltrim((string) $header), 'Content-Type: application/json') === 0) {
                return true;
            }
        }

        if (stripos((string) ($server['HTTP_ACCEPT'] ?? ''), 'application/json') !== false) {
            return true;
        }

        return ($server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    // No CLI headers_sent() fica true apos qualquer saida, mas o codigo de status
    // e inofensivo ali (e os testes o inspecionam); no SAPI web so antes do corpo.
    private static function setStatus(int $statusCode): void
    {
        if (!headers_sent() || PHP_SAPI === 'cli') {
            http_response_code($statusCode);
        }
    }

    private static function json(int $statusCode, string $error): void
    {
        self::setStatus($statusCode);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(['error' => $error, 'request_id' => Logger::requestId()]);
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
            Logger::warning('Falha ao ler settings para a pagina de erro', ['exception' => $exception]);

            return $defaults;
        }
    }
}
