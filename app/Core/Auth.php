<?php

namespace App\Core;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::start();

        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        self::start();

        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user): void
    {
        self::start();

        $_SESSION['user'] = [
            'id' => $user['id'],
            'uuid' => $user['uuid'] ?? null,
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (bool) ($user['is_admin'] ?? true),
        ];
    }

    public static function logout(): void
    {
        self::start();

        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    /**
     * Guarda de controller para as rotas /admin/*. Complementado pela
     * middleware App\Core\Middleware\RequireAuth nas rotas registradas
     * com $router->get(..., [RequireAuth::class]) — use uma ou outra
     * dependendo se a rota já passa pelo pipeline de middleware do
     * Router ou não.
     */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
    }
}
