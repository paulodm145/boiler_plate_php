<?php

namespace App\Helpers;

/**
 * Cabecalho Content-Disposition pela RFC 6266: filename="..." em ASCII para
 * navegador antigo e filename*=UTF-8''... com o nome real. Nada do nome chega
 * cru ao cabecalho (aspas, barra, controle e quebra de linha saem).
 */
final class ContentDisposition
{
    private function __construct()
    {
    }

    public static function header(string $fileName, bool $inline): string
    {
        $clean = preg_replace('/[\p{Cc}\p{Cf}"\\\\]+/u', '', mb_scrub($fileName, 'UTF-8'));
        // Lista branca depois da transliteracao: iconv transforma aspas tipograficas em ".
        $ascii = preg_replace('/[^A-Za-z0-9 .()_\-]/', '_', Str::ascii($clean));

        return ($inline ? 'inline' : 'attachment')
            . '; filename="' . ($ascii === '' ? 'arquivo' : $ascii) . '"'
            . "; filename*=UTF-8''" . rawurlencode($clean);
    }
}
