<?php

namespace App\Helpers;

/**
 * Nome original de um arquivo enviado, pronto para guardar e mostrar. Nunca
 * vira caminho no disco (o arquivo e gravado com um uuid), mas aparece na tela
 * e no download.
 */
final class FileName
{
    public const MAX_LENGTH = 255;
    private const FALLBACK = 'arquivo';

    private function __construct()
    {
    }

    /**
     * So o nome (sem pasta, de qualquer sistema), sem caracteres de controle
     * nem de formato -- inclusive os que invertem a direcao do texto, como o
     * U+202E que faz "fdp.exe" parecer "exe.pdf" --, espacos juntados e no
     * maximo 255 caracteres, preservando a extensao.
     */
    public static function clean(string $name): string
    {
        $name = mb_scrub($name, 'UTF-8');
        $name = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $name);
        $name = Str::afterLast(str_replace('\\', '/', $name), '/');
        $name = Str::squish($name);

        if ($name === '' || trim($name, '.') === '') {
            return self::FALLBACK;
        }

        return self::limited($name);
    }

    private static function limited(string $name): string
    {
        if (mb_strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' && mb_strlen($extension) <= 10 ? '.' . $extension : '';

        return mb_substr($name, 0, self::MAX_LENGTH - mb_strlen($suffix)) . $suffix;
    }
}
