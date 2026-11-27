<?php

namespace App\Helpers;

/**
 * Numeros no formato brasileiro (milhar com ponto, decimal com virgula), no
 * estilo do Number do Laravel, sem a extensao intl. Dinheiro continua em
 * MoneyFormat (centavos + moeda).
 */
final class Number
{
    private function __construct()
    {
    }

    /** 1234.5 => "1.234,50" com $decimals = 2. */
    public static function format(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    /** 12.5 => "12,5%". $value ja e o percentual (nao a fracao). */
    public static function percentage(int|float $value, int $decimals = 0): string
    {
        return self::format($value, $decimals) . '%';
    }

    /** 1536 => "1,5 KB". Base 1024. */
    public static function fileSize(int $bytes, int $decimals = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) max(0, $bytes);
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return self::format($size, $unit === 0 ? 0 : $decimals) . ' ' . $units[$unit];
    }

    /** 1500 => "1,5 mil", 2300000 => "2,3 mi", 4100000000 => "4,1 bi". */
    public static function abbreviate(int|float $value, int $decimals = 1): string
    {
        $abs = abs($value);
        [$divisor, $suffix] = match (true) {
            $abs >= 1_000_000_000_000 => [1_000_000_000_000, ' tri'],
            $abs >= 1_000_000_000 => [1_000_000_000, ' bi'],
            $abs >= 1_000_000 => [1_000_000, ' mi'],
            $abs >= 1_000 => [1_000, ' mil'],
            default => [1, ''],
        };
        $formatted = self::format($value / $divisor, $divisor === 1 ? 0 : $decimals);

        // "1,0 mil" fica "1 mil".
        return preg_replace('/,0+$/', '', $formatted) . $suffix;
    }

    /** "1.234,56" => 1234.56; aceita tambem "1234.56". null se nao for numero. */
    public static function parse(string $value): ?float
    {
        $clean = preg_replace('/[^\d,.\-]/', '', $value);

        if (str_contains($clean, ',')) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    public static function clamp(int|float $value, int|float $min, int|float $max): int|float
    {
        return max($min, min($max, $value));
    }
}
