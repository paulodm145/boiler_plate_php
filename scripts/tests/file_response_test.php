<?php

declare(strict_types=1);

use App\Http\FileResponse;
use App\Storage\LocalDisk;
use App\Storage\StoredFile;

echo "\nFileResponse\n";

function storedFile(string $mime, string $name, bool $inline): StoredFile
{
    $root = tempDir();
    $source = $root . '/src';
    file_put_contents($source, 'conteudo');
    $disk = new LocalDisk($root);
    $disk->put('f/arquivo', $source);

    return new StoredFile($disk, 'f/arquivo', $mime, $name, $inline);
}

it('PDF inline abre no navegador, mas sempre com nosniff e sandbox', function (): void {
    $headers = FileResponse::headersFor(storedFile('application/pdf', 'a.pdf', true), FileResponse::NO_CACHE);

    assertTrue(str_starts_with($headers['Content-Disposition'], 'inline;'));
    assertSame('nosniff', $headers['X-Content-Type-Options']);
    assertContains('sandbox', $headers['Content-Security-Policy']);
    assertSame('8', $headers['Content-Length']);
});

it('HTML ou SVG guardado nunca abre inline', function (): void {
    foreach (['text/html', 'image/svg+xml'] as $mime) {
        $headers = FileResponse::headersFor(storedFile($mime, 'x', true), FileResponse::NO_CACHE);

        assertTrue(str_starts_with($headers['Content-Disposition'], 'attachment;'), $mime);
    }
});

it('nome com acento vai em filename* e quebra de linha nao chega ao cabecalho', function (): void {
    $headers = FileResponse::headersFor(storedFile('application/pdf', "relatório\r\nX-Evil: 1.pdf", false), FileResponse::NO_CACHE);

    assertContains("filename*=UTF-8''relat%C3%B3rio", $headers['Content-Disposition']);
    assertFalse(str_contains($headers['Content-Disposition'], "\n"));
    assertFalse(str_contains($headers['Content-Disposition'], "\r"));
});

it('send entrega o conteudo do disco', function (): void {
    ob_start();
    FileResponse::send(storedFile('application/pdf', 'a.pdf', false));
    assertSame('conteudo', ob_get_clean());
});
