<?php

namespace App\Helpers;

use App\Helpers\Br\Cep;
use App\Helpers\Br\Cnpj;
use App\Helpers\Br\Cpf;
use App\Helpers\Br\Phone;

/**
 * Ocultacao de dados pessoais para exibir onde a LGPD pede (listas, logs,
 * telas de terceiros). So mascara a exibicao: o valor guardado nao muda, e
 * isso nao substitui a regra de quem pode ver o dado inteiro.
 */
final class Lgpd
{
    public const CHAR = '*';

    /** Conectivos que nao viram inicial no nome. */
    private const NAME_CONNECTORS = ['da', 'das', 'de', 'do', 'dos', 'e', 'di', 'du', 'van', 'von'];

    private function __construct()
    {
    }

    /**
     * Oculta tudo menos os $showStart primeiros e os $showEnd ultimos
     * caracteres. Se nao sobrar nada para ocultar, oculta tudo.
     */
    public static function hide(string $value, int $showStart = 0, int $showEnd = 0, string $char = self::CHAR): string
    {
        $length = mb_strlen($value);
        $hidden = $length - $showStart - $showEnd;

        if ($hidden <= 0) {
            return str_repeat($char, $length);
        }

        return Str::mask($value, $char, $showStart, $hidden);
    }

    /** "123.456.789-09" => "***.456.789-**" (o padrao das publicacoes oficiais). */
    public static function cpf(string $cpf): string
    {
        $digits = Cpf::clean($cpf);

        if (strlen($digits) !== 11) {
            return self::hide($cpf);
        }

        return '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**';
    }

    /** "12.ABC.345/01DE-35" => "**.ABC.345/****-**" (so o meio da raiz aparece). */
    public static function cnpj(string $cnpj): string
    {
        $value = Cnpj::clean($cnpj);

        if (strlen($value) !== 14) {
            return self::hide($cnpj);
        }

        return '**.' . substr($value, 2, 3) . '.' . substr($value, 5, 3) . '/****-**';
    }

    /** "paulo@gmail.com" => "p***@gmail.com". Asteriscos fixos, para nao revelar o tamanho. */
    public static function email(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return self::hide($email);
        }

        return mb_substr($email, 0, 1) . str_repeat(self::CHAR, 3) . substr($email, $at);
    }

    /** "(11) 98765-4321" => "(11) *****-4321"; fixo: "(11) ****-7890". */
    public static function phone(string $phone): string
    {
        $digits = Phone::clean($phone);
        $length = strlen($digits);

        if ($length !== 10 && $length !== 11) {
            return self::hide($phone, 0, 4);
        }

        return '(' . substr($digits, 0, 2) . ') ' . str_repeat(self::CHAR, $length - 6) . '-' . substr($digits, -4);
    }

    /** "Paulo Roberto da Silva" => "Paulo R. S." (primeiro nome inteiro, iniciais dos demais). */
    public static function name(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === []) {
            return '';
        }

        $first = array_shift($parts);
        $initials = [];

        foreach ($parts as $part) {
            if (!in_array(Str::lower($part), self::NAME_CONNECTORS, true)) {
                $initials[] = Str::upper(mb_substr($part, 0, 1)) . '.';
            }
        }

        return trim($first . ' ' . implode(' ', $initials));
    }

    /** "12345-678" => "12345-***". */
    public static function cep(string $cep): string
    {
        $digits = Cep::clean($cep);

        return strlen($digits) === 8 ? substr($digits, 0, 5) . '-***' : self::hide($cep);
    }

    /** "192.168.10.25" => "192.168.*.*"; IPv6 mostra so os quatro primeiros grupos. */
    public static function ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);

            return $octets[0] . '.' . $octets[1] . '.*.*';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $expanded = implode(':', str_split(bin2hex(inet_pton($ip)), 4));

            return implode(':', array_slice(explode(':', $expanded), 0, 4)) . ':*:*:*:*';
        }

        return self::hide($ip);
    }
}
