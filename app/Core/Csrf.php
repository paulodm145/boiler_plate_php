<?php

namespace App\Core;

/**
 * Token CSRF sincronizador por sessao (mesmo padrao estatico de Auth/Tenant).
 * Gerado uma vez por sessao, inclusive pra visitante nao autenticado -- cobre
 * tambem os forms publicos (login, cadastro, recuperar senha, aceitar
 * convite), nao so as rotas de equipe. Comparado com hash_equals() pra
 * evitar timing attack.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        Auth::start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Pronto pra colar dentro de um <form method="post">. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        Auth::start();

        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
