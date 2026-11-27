<?php

namespace App\Helpers;

use DateTime;

/** Datas de calendario (YYYY-MM-DD) sem fuso: normalizar, deslocar e contar dias. */
class DateOnly
{
    /** null (sem data na rota) -> hoje; formato invalido -> null; senao devolve a propria data. */
    public static function normalize(?string $date): ?string
    {
        if ($date === null) {
            return date('Y-m-d');
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }

    /** Data (YYYY-MM-DD) deslocada em $days dias, para frente ou para tras. */
    public static function shift(string $date, int $days): string
    {
        $parsed = new DateTime($date);
        $parsed->modify(($days >= 0 ? '+' : '') . $days . ' day');

        return $parsed->format('Y-m-d');
    }

    /** Dias inteiros entre duas datas (YYYY-MM-DD), sem sinal. */
    public static function daysBetween(string $from, string $to): int
    {
        return (int) (new DateTime($from))->diff(new DateTime($to))->days;
    }
}
