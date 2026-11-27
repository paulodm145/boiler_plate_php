<?php

namespace App\Helpers\Br;

use App\Helpers\Mask;

/**
 * CNPJ, ja no formato alfanumerico (IN RFB 2.229/2024, a partir de julho de
 * 2026): 12 posicoes com A-Z ou 0-9 e 2 verificadores numericos. Cada
 * caractere vale o codigo ASCII menos 48 ("0"-"9" = 0-9, "A" = 17 ... "Z" =
 * 42) e o calculo e o modulo 11 de sempre, entao o CNPJ so numerico continua
 * valido pela mesma conta. Minusculas viram maiusculas.
 */
final class Cnpj
{
    private const FIRST_WEIGHTS = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    private const SECOND_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private function __construct()
    {
    }

    /** Maiusculo, so letras e digitos ("12.abc.345/01de-35" => "12ABC34501DE35"). */
    public static function clean(string $cnpj): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($cnpj));
    }

    public static function isValid(string $cnpj): bool
    {
        $value = self::clean($cnpj);

        // 12 posicoes alfanumericas + 2 verificadores numericos; 14 caracteres iguais nao valem.
        if (!preg_match('/^[A-Z0-9]{12}\d{2}$/', $value) || preg_match('/^(.)\1{13}$/', $value)) {
            return false;
        }

        return substr($value, 12) === self::checkDigits(substr($value, 0, 12));
    }

    /** "12ABC34501DE35" => "12.ABC.345/01DE-35"; o valor volta como veio se nao tiver 14 posicoes. */
    public static function format(string $cnpj): string
    {
        $value = self::clean($cnpj);

        return strlen($value) === 14 ? Mask::apply($value, Mask::CNPJ) : $cnpj;
    }

    /** true se o CNPJ tem alguma letra (o formato novo). */
    public static function isAlphanumeric(string $cnpj): bool
    {
        return preg_match('/[A-Z]/', self::clean($cnpj)) === 1;
    }

    /** Os 2 verificadores das 12 primeiras posicoes. */
    public static function checkDigits(string $base): string
    {
        $base = self::clean($base);
        $first = self::digit($base, self::FIRST_WEIGHTS);

        return $first . self::digit($base . $first, self::SECOND_WEIGHTS);
    }

    private static function digit(string $value, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $position => $weight) {
            $sum += (ord($value[$position]) - 48) * $weight;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
