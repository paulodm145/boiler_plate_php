<?php

declare(strict_types=1);

use App\Helpers\UploadedFiles;

echo "\nUploadedFiles\n";

it('campo de varios arquivos do $_FILES vira uma lista, um arquivo por item', function (): void {
    $field = [
        'name' => ['a.png', 'b.pdf'],
        'type' => ['image/png', 'application/pdf'],
        'tmp_name' => ['/tmp/a', '/tmp/b'],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size' => [10, 20],
    ];

    $files = UploadedFiles::fromField($field);

    assertSame(2, count($files));
    assertSame(['name' => 'b.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/b', 'error' => UPLOAD_ERR_OK, 'size' => 20], $files[1]);
});

it('campo de um arquivo so vira lista de um; campo ausente vira lista vazia', function (): void {
    $single = ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 10];

    assertSame([$single], UploadedFiles::fromField($single));
    assertSame([], UploadedFiles::fromField(null));
});
