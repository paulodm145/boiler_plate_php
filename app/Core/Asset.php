<?php

namespace App\Core;

/**
 * Cache-busting simples pra assets estaticos (?v=<mtime do arquivo>).
 * Sem manifest/build step — so um filemtime() no arquivo real em public/.
 */
class Asset
{
    public static function version(string $publicRelativePath): string
    {
        $file = dirname(__DIR__, 2) . '/public/' . ltrim($publicRelativePath, '/');

        if (!is_file($file)) {
            return '1';
        }

        $mtime = filemtime($file);

        return $mtime === false ? '1' : (string) $mtime;
    }
}
