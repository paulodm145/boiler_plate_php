<?php

namespace App\Core;

class Text
{
    /**
     * Transliteria (nunca remove a letra, troca pelo equivalente sem
     * acento) antes de reduzir a [a-z0-9-] — util pra slug publico de
     * qualquer entidade (posts, times, paginas...).
     */
    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    /**
     * Trunca um texto num limite de caracteres, cortando na ultima palavra
     * inteira e acrescentando "…" — util pra meta description/og:description
     * pra nao passar do que buscadores costumam exibir (~155-160 caracteres).
     */
    public static function truncate(string $text, int $length): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " .,;:") . '…';
    }
}
