<?php

namespace App\Helpers\Br;

use App\Helpers\Mask;
use App\Helpers\Str;

/** CPF: 11 digitos, os 2 ultimos verificadores (modulo 11). */
final class Cpf
{
    private function __construct()
    {
    }

    /** So os digitos. */
    public static function clean(string $cpf): string
    {
        return Str::digits($cpf);
    }

    public static function isValid(string $cpf): bool
    {
        $digits = self::clean($cpf);

        // 11 digitos iguais passam na conta, mas nao sao CPF.
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        return substr($digits, 9) === self::checkDigits(substr($digits, 0, 9));
    }

    /** "12345678909" => "123.456.789-09"; o valor volta como veio se nao tiver 11 digitos. */
    public static function format(string $cpf): string
    {
        $digits = self::clean($cpf);

        return strlen($digits) === 11 ? Mask::apply($digits, Mask::CPF) : $cpf;
    }

    /** Os 2 verificadores dos 9 primeiros digitos. */
    public static function checkDigits(string $base): string
    {
        $first = self::digit($base, 10);

        return $first . self::digit($base . $first, 11);
    }

    private static function digit(string $digits, int $weight): int
    {
        $sum = 0;

        foreach (str_split($digits) as $digit) {
            $sum += (int) $digit * $weight--;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
