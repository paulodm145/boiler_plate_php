<?php

namespace App\Helpers;

/**
 * Texto, no estilo do Str do Laravel, sempre seguro para UTF-8 (mb_*).
 */
final class Str
{
    private function __construct()
    {
    }

    /** Trecho depois da primeira ocorrencia de $search (o texto inteiro se nao achar). */
    public static function after(string $subject, string $search): string
    {
        if ($search === '' || !str_contains($subject, $search)) {
            return $subject;
        }

        return substr($subject, strpos($subject, $search) + strlen($search));
    }

    /** Trecho depois da ultima ocorrencia de $search. */
    public static function afterLast(string $subject, string $search): string
    {
        $position = $search === '' ? false : strrpos($subject, $search);

        return $position === false ? $subject : substr($subject, $position + strlen($search));
    }

    /** Trecho antes da primeira ocorrencia de $search. */
    public static function before(string $subject, string $search): string
    {
        $position = $search === '' ? false : strpos($subject, $search);

        return $position === false ? $subject : substr($subject, 0, $position);
    }

    /** Trecho antes da ultima ocorrencia de $search. */
    public static function beforeLast(string $subject, string $search): string
    {
        $position = $search === '' ? false : strrpos($subject, $search);

        return $position === false ? $subject : substr($subject, 0, $position);
    }

    /** Trecho entre $from e a ultima ocorrencia de $to. */
    public static function between(string $subject, string $from, string $to): string
    {
        return self::beforeLast(self::after($subject, $from), $to);
    }

    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        foreach ((array) $needles as $needle) {
            $found = $ignoreCase
                ? mb_stripos($haystack, $needle) !== false
                : str_contains($haystack, $needle);

            if ($needle !== '' && $found) {
                return true;
            }
        }

        return false;
    }

    public static function containsAll(string $haystack, array $needles, bool $ignoreCase = false): bool
    {
        foreach ($needles as $needle) {
            if (!self::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }

        return true;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Garante que o texto comeca com $prefix, sem repeti-lo. */
    public static function start(string $value, string $prefix): string
    {
        return $prefix . preg_replace('/^(?:' . preg_quote($prefix, '/') . ')+/u', '', $value);
    }

    /** Garante que o texto termina com $cap, sem repeti-lo. */
    public static function finish(string $value, string $cap): string
    {
        return preg_replace('/(?:' . preg_quote($cap, '/') . ')+$/u', '', $value) . $cap;
    }

    public static function length(string $value): int
    {
        return mb_strlen($value);
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /** Cada palavra com inicial maiuscula ("joão da silva" => "João Da Silva"). */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function ucfirst(string $value): string
    {
        return self::upper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    /** Corta em $limit caracteres e acrescenta $end (so quando cortou). */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /** Mantem as primeiras $words palavras e acrescenta $end (so quando cortou). */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        $parts = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) <= $words) {
            return $value;
        }

        return implode(' ', array_slice($parts, 0, $words)) . $end;
    }

    /** Tira espacos das pontas e junta espacos repetidos em um so. */
    public static function squish(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** Sem acentos, so ASCII ("ação" => "acao"). */
    public static function ascii(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $transliterated === false ? '' : preg_replace('/[^\x20-\x7E]/', '', $transliterated);
    }

    /** Slug de URL (Text::slugify). */
    public static function slug(string $value): string
    {
        return Text::slugify($value);
    }

    /** "Nome do campo" / "nome_do_campo" => "nomeDoCampo". */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /** "nome do campo" => "NomeDoCampo". */
    public static function studly(string $value): string
    {
        $words = preg_split('/[\s_\-]+/u', self::ascii($value), -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_map(static fn (string $word): string => ucfirst($word), $words));
    }

    /** "nomeDoCampo" / "Nome do campo" => "nome_do_campo". */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', self::ascii($value));
        $words = preg_split('/[\s_\-]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);

        return implode($delimiter, $words);
    }

    /** "nomeDoCampo" => "nome-do-campo". */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * Como o Str::mask do Laravel: troca $length caracteres a partir de $index
     * por $character. $index negativo conta do fim; $length null vai ate o fim.
     */
    public static function mask(string $value, string $character, int $index, ?int $length = null): string
    {
        $total = mb_strlen($value);
        $start = $index < 0 ? max(0, $total + $index) : min($index, $total);
        $size = $length === null ? $total - $start : max(0, min($length, $total - $start));

        return mb_substr($value, 0, $start)
            . str_repeat(mb_substr($character, 0, 1), $size)
            . mb_substr($value, $start + $size);
    }

    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        $missing = $length - mb_strlen($value);

        return $missing > 0 ? self::repeatTo($pad, $missing) . $value : $value;
    }

    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        $missing = $length - mb_strlen($value);

        return $missing > 0 ? $value . self::repeatTo($pad, $missing) : $value;
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $position = $search === '' ? false : strpos($subject, $search);

        return $position === false ? $subject : substr_replace($subject, $replace, $position, strlen($search));
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        $position = $search === '' ? false : strrpos($subject, $search);

        return $position === false ? $subject : substr_replace($subject, $replace, $position, strlen($search));
    }

    /** So os digitos ("123.456.789-09" => "12345678909"). */
    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    /** So letras A-Z/a-z e digitos, sem acento nem pontuacao. */
    public static function alnum(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]+/', '', $value);
    }

    /** null, "" ou so espacos. */
    public static function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    /** Texto aleatorio criptograficamente seguro, com letras e digitos. */
    public static function random(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $result;
    }

    private static function repeatTo(string $pad, int $length): string
    {
        if ($pad === '') {
            return '';
        }

        return mb_substr(str_repeat($pad, (int) ceil($length / mb_strlen($pad))), 0, $length);
    }
}
