<?php

namespace App\Helpers;

/** Dinheiro para exibir: real no formato brasileiro (R$ 1.234,56), dolar no americano (US$ 1,234.56). */
class MoneyFormat
{
    private const STYLE = [
        'BRL' => ['R$ ', ',', '.'],
        'USD' => ['US$ ', '.', ','],
    ];

    public static function format(int $cents, string $currency = 'BRL'): string
    {
        [$symbol, $decimal, $thousands] = self::STYLE[$currency] ?? [$currency . ' ', ',', '.'];
        $sign = $cents < 0 ? '-' : '';

        return $sign . $symbol . number_format(abs($cents) / 100, 2, $decimal, $thousands);
    }
}
