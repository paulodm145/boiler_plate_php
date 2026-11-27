<?php

namespace App\Helpers;

/**
 * Cores vindas do banco (prioridade, coluna, cor personalizada da tarefa)
 * vao parar em style="" -- htmlspecialchars impede sair do atributo, mas
 * nao impede CSS arbitrario dentro dele. So #rgb/#rrggbb passa; o resto
 * vira o fallback.
 */
class Color
{
    public static function hexOr(?string $color, ?string $fallback): ?string
    {
        return $color !== null && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1 ? $color : $fallback;
    }
}
