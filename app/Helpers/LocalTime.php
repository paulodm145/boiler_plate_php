<?php

namespace App\Helpers;

use DateTime;
use DateTimeZone;

/**
 * Hora local da aplicacao (date.timezone) <-> UTC do banco, para horarios
 * absolutos como o inicio de um evento (ver o gotcha de fuso no CLAUDE.md).
 */
class LocalTime
{
    /** Horario de relogio HH:MM, 00:00 a 23:59 -- o unico padrao do app (\z: nem "\n" no fim passa). */
    public const CLOCK_PATTERN = '/^([01][0-9]|2[0-3]):[0-5][0-9]\z/';

    public static function isClockTime(string $value): bool
    {
        return preg_match(self::CLOCK_PATTERN, $value) === 1;
    }

    /** 'Y-m-d H:i' local -> 'Y-m-d H:i:s' UTC; null se a data/hora nao existir. */
    public static function toUtc(string $local): ?string
    {
        $time = DateTime::createFromFormat('!Y-m-d H:i', $local, new DateTimeZone(date_default_timezone_get()));
        $errors = DateTime::getLastErrors();

        if ($time === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** 'Y-m-d H:i:s' UTC -> hora local no formato pedido. */
    public static function fromUtc(string $utc, string $format = 'Y-m-d H:i'): string
    {
        return (new DateTime($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format($format);
    }
}
