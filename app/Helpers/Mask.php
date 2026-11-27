<?php

namespace App\Helpers;

/**
 * Mascara por padrao: cada "#" do padrao recebe o proximo caractere util do
 * valor (letra ou digito; pontuacao do valor e ignorada) e o resto do padrao
 * entra como esta. Mask::apply('12345678', '#####-###') => "12345-678".
 * Os formatos prontos de CPF/CNPJ/CEP/telefone estao em App\Helpers\Br.
 */
final class Mask
{
    public const CPF = '###.###.###-##';
    public const CNPJ = '##.###.###/####-##';
    public const CEP = '#####-###';
    public const PHONE = '(##) ####-####';
    public const MOBILE = '(##) #####-####';

    private function __construct()
    {
    }

    /**
     * Aplica $pattern. Se o valor tem menos caracteres que o padrao, para no
     * ultimo que coube (sem pontuacao sobrando); os que sobram sao descartados.
     */
    public static function apply(string $value, string $pattern): string
    {
        $chars = str_split(Str::alnum($value));
        $result = '';
        $next = 0;

        foreach (str_split($pattern) as $slot) {
            if ($next >= count($chars)) {
                break;
            }

            if ($slot === '#') {
                $result .= $chars[$next++];
                continue;
            }

            $result .= $slot;
        }

        return $result;
    }

    /** Tira a mascara: so letras e digitos. */
    public static function remove(string $value): string
    {
        return Str::alnum($value);
    }
}
