<?php

declare(strict_types=1);

use App\Services\Storage\UploadService;
use App\Storage\LocalDisk;
use App\Storage\MimeTypes;
use App\Storage\StoragePath;

echo "\nArmazenamento e upload\n";

const TEST_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

/** Um "upload" de teste: arquivo temporario + o array que o PHP montaria em $_FILES. */
function fakeUpload(string $contents, string $name, int $error = UPLOAD_ERR_OK): array
{
    $tmp = tempDir() . '/php' . bin2hex(random_bytes(4));
    file_put_contents($tmp, $contents);

    return ['name' => $name, 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => $error, 'size' => 1];
}

/** UploadService com disco num tempDir e um mover que aceita arquivos que nao vieram de upload HTTP. */
function uploadService(?string &$root = null): UploadService
{
    $root = tempDir();

    return new UploadService(new LocalDisk($root), $root . '/.staging', static fn (string $from, string $to): bool => rename($from, $to));
}

it('MimeTypes detecta pelo conteudo e nunca trata SVG como inline seguro', function (): void {
    $png = fakeUpload(base64_decode(TEST_PNG_BASE64), 'a.png');

    assertSame('image/png', MimeTypes::detect($png['tmp_name']));
    assertTrue(MimeTypes::matches($png['tmp_name'], ['png']));
    assertFalse(MimeTypes::matches($png['tmp_name'], ['jpg']));
    assertFalse(MimeTypes::isInlineSafe('image/svg+xml'));
    assertFalse(MimeTypes::isInlineSafe('text/html'));
    assertTrue(MimeTypes::isInlineSafe('application/pdf'));
});

it('StoragePath recusa caminhos perigosos', function (): void {
    foreach (['../x', '/x', 'a\\b', 'a//b', "a\0b", 'a/./b', ''] as $path) {
        assertThrows(fn () => StoragePath::assertSafe($path), 'deveria recusar ' . json_encode($path));
    }
    StoragePath::assertSafe('users/2026/11/abc.png');
});

it('LocalDisk grava, mede, le e apaga', function (): void {
    $root = tempDir();
    $disk = new LocalDisk($root);
    $source = tempDir() . '/origem.txt';
    file_put_contents($source, 'conteudo');

    $disk->put('docs/a.txt', $source);
    assertTrue($disk->exists('docs/a.txt'));
    assertSame(8, $disk->size('docs/a.txt'));

    $out = fopen('php://memory', 'w+');
    assertTrue($disk->writeTo('docs/a.txt', $out));
    rewind($out);
    assertSame('conteudo', stream_get_contents($out));

    $disk->delete('docs/a.txt');
    assertFalse($disk->exists('docs/a.txt'));
    assertSame(null, $disk->size('docs/a.txt'));
});

it('upload de PNG real grava como <pasta>/<ano>/<mes>/<uuid>.png', function (): void {
    $service = uploadService($root);
    $result = $service->store(fakeUpload(base64_decode(TEST_PNG_BASE64), 'Foto de Férias.png'), 'avatars', 1024, MimeTypes::IMAGES);

    assertTrue($result->isOk());
    $data = $result->data();
    assertSame(1, preg_match('#^avatars/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#', $data['path']));
    assertSame('image/png', $data['mime']);
    assertSame('local', $data['disk']);
    assertSame('Foto de Férias.png', $data['original_name']);
    assertTrue(is_file($root . '/' . $data['path']));
});

it('png que na verdade e html e recusado', function (): void {
    $result = uploadService()->store(fakeUpload('<html><script>alert(1)</script></html>', 'x.png'), 'avatars', 1024, MimeTypes::IMAGES);

    assertSame('invalid_type', $result->code());
});

it('tamanho vem do arquivo real, nao do size enviado', function (): void {
    $upload = fakeUpload(base64_decode(TEST_PNG_BASE64) . str_repeat("\0", 4000), 'a.png');
    $upload['size'] = 10;

    assertSame('file_too_large', uploadService()->store($upload, 'avatars', 1024, MimeTypes::IMAGES)->code());
});

it('falhas do PHP viram codigos proprios', function (): void {
    $service = uploadService();

    assertSame('missing_file', $service->store(null, 'x', 1024, MimeTypes::IMAGES)->code());
    assertSame('missing_file', $service->store(['error' => UPLOAD_ERR_NO_FILE], 'x', 1024, MimeTypes::IMAGES)->code());
    assertSame('file_too_large', $service->store(fakeUpload('x', 'a.png', UPLOAD_ERR_INI_SIZE), 'x', 1024, MimeTypes::IMAGES)->code());
    assertSame('upload_failed', $service->store(fakeUpload('x', 'a.png', UPLOAD_ERR_PARTIAL), 'x', 1024, MimeTypes::IMAGES)->code());
});

it('arquivo que nao veio de upload HTTP e recusado pelo mover padrao', function (): void {
    $root = tempDir();
    $service = new UploadService(new LocalDisk($root), $root . '/.staging');

    assertSame('upload_failed', $service->store(fakeUpload(base64_decode(TEST_PNG_BASE64), 'a.png'), 'x', 1024, MimeTypes::IMAGES)->code());
});

it('nome original com ../ e \r\n e limpo e nao entra no caminho gravado', function (): void {
    $result = uploadService()->store(fakeUpload(base64_decode(TEST_PNG_BASE64), "../../etc/pass\r\nwd.png"), 'avatars', 1024, MimeTypes::IMAGES);

    assertTrue($result->isOk());
    assertFalse(str_contains($result->data()['path'], 'etc'));
    assertFalse(str_contains($result->data()['original_name'], '/'));
    assertFalse(str_contains($result->data()['original_name'], "\n"));
});

it('pasta de passagem fica vazia depois do upload', function (): void {
    $service = uploadService($root);
    $service->store(fakeUpload(base64_decode(TEST_PNG_BASE64), 'a.png'), 'avatars', 1024, MimeTypes::IMAGES);

    assertSame([], glob($root . '/.staging/*') ?: []);
});

it('pasta de destino perigosa lanca excecao', function (): void {
    assertThrows(fn () => uploadService()->store(fakeUpload(base64_decode(TEST_PNG_BASE64), 'a.png'), '../fora', 1024, MimeTypes::IMAGES));
});

it('removeStaleStaging apaga so o que e mais velho que o limite', function (): void {
    $service = uploadService($root);
    mkdir($root . '/.staging', 0775, true);
    file_put_contents($root . '/.staging/velho', 'x');
    touch($root . '/.staging/velho', time() - 7200);
    file_put_contents($root . '/.staging/novo', 'x');

    assertSame(1, $service->removeStaleStaging(time() - 3600));
    assertTrue(is_file($root . '/.staging/novo'));
});
