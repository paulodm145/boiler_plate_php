<?php

namespace App\Http;

use App\Core\ErrorPage;
use App\Helpers\ContentDisposition;
use App\Storage\MimeTypes;
use App\Storage\StoredFile;

/**
 * O unico que manda um arquivo guardado ao navegador. nosniff + CSP com sandbox:
 * mesmo que um arquivo engane o finfo, o navegador nao executa nada dele; e so
 * imagem raster e PDF abrem inline (MimeTypes::INLINE_SAFE) -- SVG e HTML
 * sempre baixam. Quem chama ja conferiu a permissao.
 */
final class FileResponse
{
    public const NO_CACHE = 'private, max-age=0, must-revalidate';

    private const CONTENT_SECURITY_POLICY = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";

    private function __construct()
    {
    }

    public static function send(StoredFile $file, string $cacheControl = self::NO_CACHE): void
    {
        if (!$file->exists()) {
            ErrorPage::notFound();

            return;
        }

        if (!headers_sent()) {
            foreach (self::headersFor($file, $cacheControl) as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        $output = fopen('php://output', 'wb');
        $file->disk->writeTo($file->path, $output);
        fclose($output);
    }

    /** @return array<string, string> */
    public static function headersFor(StoredFile $file, string $cacheControl): array
    {
        $inline = $file->inline && MimeTypes::isInlineSafe($file->mimeType);
        $headers = [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => ContentDisposition::header($file->downloadName, $inline),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'Cache-Control' => $cacheControl,
        ];
        $size = $file->size ?? $file->disk->size($file->path);

        if ($size !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        return $headers;
    }
}
