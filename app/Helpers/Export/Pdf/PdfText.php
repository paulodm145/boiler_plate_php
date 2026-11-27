<?php

namespace App\Helpers\Export\Pdf;

/**
 * Texto para a FPDF, que so conhece Windows-1252 (as fontes padrao do PDF).
 * Unico lugar da conversao: acentos, cedilha, travessao, reticencias e euro
 * passam; o que nao existe em Windows-1252 (ideogramas, emoji) vira "?", e
 * UTF-8 invalido tambem -- nunca excecao nem texto cortado. mbstring, e nao
 * iconv, porque o //TRANSLIT do iconv muda de glibc para musl.
 */
final class PdfText
{
    public static function fromUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $previous = mb_substitute_character();
        mb_substitute_character(0x3F);
        $converted = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        mb_substitute_character($previous);

        return (string) $converted;
    }
}
