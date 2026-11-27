<?php

namespace App\Helpers\Br;

use App\Helpers\Mask;
use App\Helpers\Str;

/**
 * Telefone brasileiro com DDD: fixo (10 digitos) ou celular (11, comecando
 * com 9). O "+55"/"55" do pais e o "0" de discagem na frente sao ignorados.
 */
final class Phone
{
    private function __construct()
    {
    }

    /** DDD + numero, sem o codigo do pais. */
    public static function clean(string $phone): string
    {
        $digits = ltrim(Str::digits($phone), '0');

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function isValid(string $phone): bool
    {
        $digits = self::clean($phone);

        // DDD de 11 a 99; fixo comeca com 2-5, celular tem 11 digitos e comeca com 9.
        return (bool) match (strlen($digits)) {
            10 => preg_match('/^[1-9][1-9][2-5]\d{7}$/', $digits),
            11 => preg_match('/^[1-9][1-9]9\d{8}$/', $digits),
            default => false,
        };
    }

    public static function isMobile(string $phone): bool
    {
        return strlen(self::clean($phone)) === 11;
    }

    /** "11987654321" => "(11) 98765-4321", "1134567890" => "(11) 3456-7890"; outro tamanho volta como veio. */
    public static function format(string $phone): string
    {
        $digits = self::clean($phone);

        return match (strlen($digits)) {
            10 => Mask::apply($digits, Mask::PHONE),
            11 => Mask::apply($digits, Mask::MOBILE),
            default => $phone,
        };
    }
}
