<?php

namespace App\Helpers;

use DateTime;

/** Datas por extenso em pt-BR, sem depender de locale/intl do servidor. */
final class DateFormat
{
    private const MONTHS = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    private const FULL_MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    private const WEEKDAYS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

    /** "segunda-feira, 5 de out de 2026" */
    public static function long(string $date): string
    {
        $parsed = new DateTime($date);

        return self::WEEKDAYS[(int) $parsed->format('w')] . ', '
            . (int) $parsed->format('j') . ' de ' . self::month($parsed) . ' de ' . $parsed->format('Y');
    }

    /** "2026-11-24" -> "24 de novembro de 2026" (documentos, e-mails). */
    public static function full(string $date): string
    {
        $parsed = new DateTime($date);

        return (int) $parsed->format('j') . ' de ' . self::FULL_MONTHS[(int) $parsed->format('n')] . ' de ' . $parsed->format('Y');
    }

    /** "2026-10-01" -> "01/10/2026" (relatorios e documentos). */
    public static function numeric(string $date): string
    {
        return substr($date, 8, 2) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
    }

    /** "5 out" */
    public static function short(string $date): string
    {
        $parsed = new DateTime($date);

        return (int) $parsed->format('j') . ' ' . self::month($parsed);
    }

    /** "7 set – 18 set" no ano de $today; senao com o ano em cada ponta: "21 dez 2026 – 1 jan 2027". */
    public static function shortRange(string $from, string $to, string $today): string
    {
        $year = substr($today, 0, 4);

        if (substr($from, 0, 4) === $year && substr($to, 0, 4) === $year) {
            return self::short($from) . ' – ' . self::short($to);
        }

        return self::short($from) . ' ' . substr($from, 0, 4) . ' – ' . self::short($to) . ' ' . substr($to, 0, 4);
    }

    private static function month(DateTime $date): string
    {
        return self::MONTHS[(int) $date->format('n')];
    }
}
