<?php

namespace App\Http;

use App\Core\Auth;

/** Mensagem de retorno que sobrevive a um redirect e e lida uma vez (admin/partials/flash.php). */
final class Flash
{
    private const KEY = '_flash';

    private function __construct()
    {
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('danger', $message);
    }

    /** @return array{type: string, message: string}|null */
    public static function pull(): ?array
    {
        Auth::start();
        $flash = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        return is_array($flash) ? $flash : null;
    }

    private static function set(string $type, string $message): void
    {
        Auth::start();
        $_SESSION[self::KEY] = ['type' => $type, 'message' => $message];
    }
}
