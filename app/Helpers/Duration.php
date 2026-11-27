<?php

namespace App\Helpers;

/** Minutos em texto curto para telas: 90 -> "1h30", 120 -> "2h", 45 -> "45min". */
class Duration
{
    public static function label(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest . 'min';
        }

        return $hours . 'h' . ($rest === 0 ? '' : str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }
}
