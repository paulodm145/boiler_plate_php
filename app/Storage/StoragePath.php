<?php

namespace App\Storage;

use InvalidArgumentException;

/**
 * O unico lugar que decide se um caminho relativo pode ir para um disco
 * Os caminhos sao montados pelo codigo e por uuids, nunca pelo usuario; isto e
 * a trava caso um dia venham.
 */
final class StoragePath
{
    private function __construct()
    {
    }

    /** Recusa vazio, "..", "/" no comeco, "\", byte nulo e segmento vazio ("a//b", "a/"). */
    public static function assertSafe(string $path): void
    {
        $unsafe = $path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || in_array('', explode('/', $path), true)
            || in_array('..', explode('/', $path), true)
            || in_array('.', explode('/', $path), true);

        if ($unsafe) {
            throw new InvalidArgumentException('Caminho de arquivo invalido.');
        }
    }
}
