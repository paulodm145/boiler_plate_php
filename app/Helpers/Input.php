<?php

namespace App\Helpers;

/**
 * Normalizacao de entrada HTTP (cast + trim, vazio vira null). Dinheiro,
 * decimal e peso nunca passam por float: o valor sai como centavos (int) ou
 * string decimal, pronto para a coluna INT/DECIMAL.
 */
final class Input
{
    public static function string($value): string
    {
        return trim((string) $value);
    }

    /**
     * E-mail digitado no login ou na recuperacao: aparado e so em ASCII imprimivel, senao null. A coluna
     * users.email compara ignorando acentos (utf8mb4_unicode_ci); aceitar variantes deixaria contornar o
     * limite por e-mail com "ána@..." chegando na mesma conta.
     */
    public static function accessEmail($value): ?string
    {
        $email = is_string($value) ? trim($value) : '';

        return $email === '' || preg_match('/[^\x21-\x7E]/', $email) === 1 ? null : $email;
    }

    public static function nullableString($value): ?string
    {
        $string = self::string($value);

        return $string === '' ? null : $string;
    }

    public static function nullableInt($value): ?int
    {
        $string = self::string($value);

        return $string === '' ? null : (int) $string;
    }

    public static function isDigitsOrBlank($value): bool
    {
        $string = self::string($value);

        return $string === '' || ctype_digit($string);
    }

    /** Horario HH:MM vindo de <input type="time"> como HH:MM:SS; fora do formato, 09:00:00. */
    public static function time($value): string
    {
        $value = is_string($value) ? $value : '09:00';

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value . ':00' : '09:00:00';
    }

    /**
     * Duracao digitada -> minutos: "1h30", "1h 30m", "2h", "45m" ou "90"
     * (minutos soltos). Vazio, zero, minutos >= 60 junto de horas ou texto
     * livre -> null.
     */
    public static function duration($value): ?int
    {
        $text = strtolower((string) preg_replace('/\s+/', '', (string) $value));

        if (ctype_digit($text)) {
            return (int) $text > 0 ? (int) $text : null;
        }

        if (!preg_match('/^(?:(\d+)h)?(?:(\d+)m?)?$/', $text, $parts) || $text === '') {
            return null;
        }

        $hours = (int) ($parts[1] ?? 0);
        $minutes = (int) ($parts[2] ?? 0);

        if ($hours > 0 && $minutes >= 60) {
            return null;
        }

        $total = $hours * 60 + $minutes;

        return $total > 0 ? $total : null;
    }

    /**
     * Dinheiro digitado no formato da moeda -> centavos, sem passar por float:
     * BRL "1.234,56", USD "1,234.56"; sem milhar, virgula ou ponto valem como
     * decimal ("42,5", "42.50"). Ambiguo ou invalido ("1.234.56", "12,345")
     * -> null, nunca um valor errado.
     */
    public static function money($value, string $currency = 'BRL'): ?int
    {
        $separators = ['BRL' => ['.', ','], 'USD' => [',', '.']];

        if (!isset($separators[$currency])) {
            return null;
        }

        [$thousands, $decimal] = $separators[$currency];
        $text = trim((string) $value);
        $grouped = '/^\d{1,3}(' . preg_quote($thousands, '/') . '\d{3})+(' . preg_quote($decimal, '/') . '\d{1,2})?$/';

        if (preg_match($grouped, $text) === 1) {
            $text = str_replace([$thousands, $decimal], ['', '.'], $text);
        } elseif (preg_match('/^\d+([.,]\d{1,2})?$/', $text) === 1) {
            $text = str_replace(',', '.', $text);
        } else {
            return null;
        }

        $parts = explode('.', $text);

        // Mais de 12 digitos inteiros nao e dinheiro digitado; e nao cabe com folga num int.
        if (strlen(ltrim($parts[0], '0')) > 12) {
            return null;
        }

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '0', 2, '0');
    }

    /** "3,45" ou "3.45" -> 345 centavos; invalido -> 0. Mesma leitura do money(), sem float. */
    public static function cents($value): int
    {
        return self::money($value) ?? 0;
    }

    /**
     * Decimal digitado (peso, quantidade, percentual) -> string decimal canonica com ponto,
     * sem passar por float: "1.234,567" -> "1234.567", "1234,5" e "1234.5" -> "1234.5",
     * "-0,5" -> "-0.5". Ponto so vale como decimal sem virgula e sem grupo de milhar.
     * Vazio, mais casas que $scale, mais de 15 digitos inteiros ou formato ambiguo -> null.
     */
    public static function decimal($value, int $scale): ?string
    {
        $text = trim((string) $value);

        if (preg_match('/^(-?)(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d+))?\z/', $text, $parts) === 1) {
            [$sign, $integer, $fraction] = [$parts[1], str_replace('.', '', $parts[2]), $parts[3] ?? ''];
        } elseif (preg_match('/^(-?)(\d+)\.(\d+)\z/', $text, $parts) === 1) {
            [$sign, $integer, $fraction] = [$parts[1], $parts[2], $parts[3]];
        } else {
            return null;
        }

        $integer = ltrim($integer, '0') === '' ? '0' : ltrim($integer, '0');

        if (strlen($fraction) > $scale || strlen($integer) > 15) {
            return null;
        }

        $number = $fraction === '' ? $integer : $integer . '.' . $fraction;

        return $sign === '-' && trim($number, '0.') !== '' ? '-' . $number : $number;
    }

    /** Data digitada em dd/mm/aaaa (ou aaaa-mm-dd) -> aaaa-mm-dd; invalida -> null (ver Br\Date). */
    public static function date($value): ?string
    {
        return Br\Date::parse(is_string($value) ? $value : null);
    }
}
