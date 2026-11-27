<?php

namespace App\Helpers\Br;

use App\Helpers\Mask;
use App\Helpers\Str;

/** CEP: 8 digitos, "12345-678". Valida so o formato (nao consulta os Correios). */
final class Cep
{
    private function __construct()
    {
    }

    public static function clean(string $cep): string
    {
        return Str::digits($cep);
    }

    public static function isValid(string $cep): bool
    {
        $digits = self::clean($cep);

        return strlen($digits) === 8 && $digits !== '00000000';
    }

    /** "12345678" => "12345-678"; o valor volta como veio se nao tiver 8 digitos. */
    public static function format(string $cep): string
    {
        $digits = self::clean($cep);

        return strlen($digits) === 8 ? Mask::apply($digits, Mask::CEP) : $cep;
    }
}
