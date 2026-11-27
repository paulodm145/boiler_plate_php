<?php

namespace App\Helpers\Br;

/**
 * Data no formato brasileiro (dd/mm/aaaa) <-> data do banco (aaaa-mm-dd).
 * Datas inexistentes (31/02, 29/02 fora de ano bissexto) nao viram outra
 * data: o resultado e null, nunca um dia "corrigido".
 */
final class Date
{
    private function __construct()
    {
    }

    /** "24/11/2026" (ou "2026-11-24" ja normalizada) -> "2026-11-24"; vazio, formato errado ou data inexistente -> null. */
    public static function parse(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})\z#', $value, $br) === 1) {
            [, $day, $month, $year] = $br;
        } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})\z#', $value, $iso) === 1) {
            [, $year, $month, $day] = $iso;
        } else {
            return null;
        }

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return $year . '-' . $month . '-' . $day;
    }

    /** "2026-11-24" ou "2026-11-24 13:00:00" -> "24/11/2026"; vazio ou o que nao for data -> ''. */
    public static function format(?string $value): string
    {
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})( \d{2}:\d{2}(:\d{2})?)?\z#', trim((string) $value), $parts) !== 1) {
            return '';
        }

        return $parts[3] . '/' . $parts[2] . '/' . $parts[1];
    }
}
