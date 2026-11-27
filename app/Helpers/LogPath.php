<?php

namespace App\Helpers;

/**
 * Caminho de URL pronto para o log: links de convite, de reuniao e de troca de
 * senha levam o token no proprio caminho, e quem le o log nao pode usa-los.
 * Segmento com 40+ letras, digitos, "_" ou "-" (os tokens sao 64 hex) vira
 * ":token"; uuid (36) continua, porque ajuda a achar o registro e nao e segredo.
 */
final class LogPath
{
    private function __construct()
    {
    }

    public static function mask(string $path): string
    {
        return (string) preg_replace('#(?<=/)[A-Za-z0-9_-]{40,}(?=/|$)#', ':token', $path);
    }
}
