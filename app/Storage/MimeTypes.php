<?php

namespace App\Storage;

/**
 * Tipos de arquivo aceitos, sempre detectados pelo conteudo (finfo), nunca pela
 * extensao do nome nem pelo Content-Type do navegador. Fonte unica para o
 * UploadService (MIME real => extensao gravada) e para a regra "mimes" do
 * Validator (extensao pedida => MIMEs aceitos).
 */
final class MimeTypes
{
    /** MIME real => extensao gravada (listas prontas para UploadService::store). */
    public const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public const DOCUMENTS = ['application/pdf' => 'pdf'];
    public const SPREADSHEETS = [
        'text/csv' => 'csv',
        'text/plain' => 'csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /** Tipos que podem abrir no navegador (inline). SVG e HTML nunca: carregam script. */
    public const INLINE_SAFE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    /** Extensao pedida na regra "mimes" => MIMEs reais aceitos. */
    private const BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'csv' => ['text/plain', 'text/csv'],
        'txt' => ['text/plain'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip'],
    ];

    private function __construct()
    {
    }

    public static function detect(string $path): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    /** O conteudo de $path e de um dos tipos de $extensions (['jpg', 'png'])? */
    public static function matches(string $path, array $extensions): bool
    {
        $detected = self::detect($path);

        foreach ($extensions as $extension) {
            if (in_array($detected, self::BY_EXTENSION[strtolower(trim((string) $extension))] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public static function isInlineSafe(string $mime): bool
    {
        return in_array(strtolower($mime), self::INLINE_SAFE, true);
    }
}
