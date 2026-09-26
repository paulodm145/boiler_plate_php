<?php

namespace App\Core;

/**
 * Checagens booleanas simples, sem builder/regras encadeadas — cada
 * Controller monta sua propria sequencia de ifs com a mensagem de erro
 * que fizer sentido pro fluxo (ver InstallController::saveAdmin() e
 * AdminUserController::validate()). Nao e um framework de validacao.
 */
class Validator
{
    public static function required(string $value): bool
    {
        return trim($value) !== '';
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength(string $value, int $length): bool
    {
        return strlen($value) >= $length;
    }
}
